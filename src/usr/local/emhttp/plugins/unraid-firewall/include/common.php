<?php

declare(strict_types=1);

namespace UnraidFirewall;

const PLUGIN_NAME = 'unraid-firewall';
const PLUGIN_ROOT = '/usr/local/emhttp/plugins/unraid-firewall';
const CONFIG_DIR = '/boot/config/plugins/unraid-firewall';
const CONFIG_FILE = CONFIG_DIR . '/unraid-firewall.cfg';
const RULES4_FILE = CONFIG_DIR . '/ipv4.rules';
const RULES6_FILE = CONFIG_DIR . '/ipv6.rules';
const STATE_FILE = '/var/run/unraid-firewall.status';

function defaultConfig(): array
{
    return [
        'enabled' => false,
        'ipv4_enabled' => true,
        'ipv4_default_allow' => true,
        'ipv6_enabled' => true,
        'ipv6_default_allow' => true,
    ];
}

function parseBoolean($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (!is_scalar($value)) {
        return $default;
    }

    $value = strtolower(trim((string) $value));
    if (in_array($value, ['1', 'yes', 'true', 'on'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'no', 'false', 'off'], true)) {
        return false;
    }
    return $default;
}

function loadConfig(): array
{
    $config = defaultConfig();
    if (!is_file(CONFIG_FILE)) {
        return $config;
    }

    $saved = @parse_ini_file(CONFIG_FILE, false, INI_SCANNER_RAW);
    if (!is_array($saved)) {
        return $config;
    }

    $config['enabled'] = parseBoolean($saved['ENABLED'] ?? $config['enabled'], $config['enabled']);
    $config['ipv4_enabled'] = parseBoolean($saved['IPV4_ENABLED'] ?? $config['ipv4_enabled'], $config['ipv4_enabled']);
    $config['ipv4_default_allow'] = parseBoolean($saved['IPV4_DEFAULT_ALLOW'] ?? $config['ipv4_default_allow'], $config['ipv4_default_allow']);
    $config['ipv6_enabled'] = parseBoolean($saved['IPV6_ENABLED'] ?? $config['ipv6_enabled'], $config['ipv6_enabled']);
    $config['ipv6_default_allow'] = parseBoolean($saved['IPV6_DEFAULT_ALLOW'] ?? $config['ipv6_default_allow'], $config['ipv6_default_allow']);

    return $config;
}

function sourceIsValid(string $source, int $family): bool
{
    $parts = explode('/', $source, 2);
    if (count($parts) > 2 || $parts[0] === '') {
        return false;
    }

    $flags = $family === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
    if (filter_var($parts[0], FILTER_VALIDATE_IP, $flags) === false) {
        return false;
    }

    if (isset($parts[1])) {
        $mask = $parts[1];
        $max = $family === 4 ? 32 : 128;
        if (!preg_match('/^\d{1,3}$/', $mask) || (int) $mask > $max) {
            return false;
        }
    }

    return true;
}

function normalizePort(string $port, string $context = 'rule'): string
{
    $port = trim($port);
    if ($port === '') {
        return '';
    }

    if (preg_match('/^(\d{1,5})(?:[-:](\d{1,5}))?$/', $port, $match) !== 1) {
        throw new \InvalidArgumentException($context . ' has an invalid port. Use 1-65535 or a range such as 5000-5010.');
    }

    $start = (int) $match[1];
    $end = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : $start;
    if ($start < 1 || $start > 65535 || $end < 1 || $end > 65535 || $end < $start) {
        throw new \InvalidArgumentException($context . ' has a port outside the range 1-65535.');
    }

    return $start === $end ? (string) $start : $start . '-' . $end;
}

function normalizeRuleRecord(array $rule, int $family, string $context = 'Rule'): array
{
    $name = trim((string) ($rule['name'] ?? ''));
    if ($name === '' || strlen($name) > 64 || preg_match('/[|\r\n\x00]/', $name) === 1) {
        throw new \InvalidArgumentException($context . ' name is required and may not contain | or line breaks.');
    }

    $action = strtolower(trim((string) ($rule['action'] ?? '')));
    if (!in_array($action, ['allow', 'deny'], true)) {
        throw new \InvalidArgumentException($context . ' has an invalid action.');
    }

    $source = trim((string) ($rule['source'] ?? ''));
    if ($source !== '' && !sourceIsValid($source, $family)) {
        throw new \InvalidArgumentException($context . ' has an invalid IPv' . $family . ' source: ' . $source);
    }

    $protocol = strtolower(trim((string) ($rule['protocol'] ?? 'any')));
    if (!in_array($protocol, ['any', 'tcp', 'udp'], true)) {
        throw new \InvalidArgumentException($context . ' has an invalid protocol.');
    }

    $port = normalizePort((string) ($rule['port'] ?? ''), $context);
    if ($port !== '' && $protocol === 'any') {
        throw new \InvalidArgumentException($context . ' must select TCP or UDP when a destination port is specified.');
    }

    return [
        'name' => $name,
        'action' => $action,
        'source' => $source,
        'protocol' => $protocol,
        'port' => $port,
    ];
}

function legacyRuleRecord(string $line, int $family, int $lineNumber): array
{
    $action = 'allow';
    $source = trim($line);
    if (preg_match('/^(allow|deny)[ \t]+([^ \t]+)$/i', $source, $match) === 1) {
        $action = strtolower($match[1]);
        $source = $match[2];
    } elseif (preg_match('/^[^ \t]+$/', $source) !== 1) {
        throw new \InvalidArgumentException('Invalid legacy source rule on line ' . ($lineNumber + 1) . '.');
    }

    return normalizeRuleRecord([
        'name' => 'Legacy rule ' . ($lineNumber + 1),
        'action' => $action,
        'source' => $source,
        'protocol' => 'any',
        'port' => '',
    ], $family, 'Legacy rule ' . ($lineNumber + 1));
}

/**
 * Read the structured rule file.  Old bare-source files are accepted and
 * exposed as no-port "Legacy rule" records so existing installations can be
 * migrated by the next WebUI save.
 *
 * @return array<int, array{name:string,action:string,source:string,protocol:string,port:string}>
 */
function readRuleRecords(string $path, int $family): array
{
    if (!is_file($path)) {
        return [];
    }

    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        return [];
    }

    $lines = preg_split('/\R/', $contents);
    if (!is_array($lines)) {
        return [];
    }

    $records = [];
    foreach ($lines as $lineNumber => $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        try {
            if (substr_count($line, '|') === 4) {
                $parts = explode('|', $line, 5);
                $records[] = normalizeRuleRecord([
                    'name' => $parts[0],
                    'action' => $parts[1],
                    'source' => $parts[2],
                    'protocol' => $parts[3],
                    'port' => $parts[4],
                ], $family, 'Rule ' . ($lineNumber + 1));
            } else {
                $records[] = legacyRuleRecord($line, $family, $lineNumber);
            }
        } catch (\Throwable $exception) {
            // Keep a malformed manually-edited line from making the WebUI
            // unusable.  It will not be applied until corrected and saved.
        }
    }

    return $records;
}

function serializeRuleRecords(array $records): string
{
    if ($records === []) {
        return '';
    }

    $lines = [];
    foreach ($records as $record) {
        $lines[] = implode('|', [
            $record['name'],
            $record['action'],
            $record['source'],
            $record['protocol'],
            $record['port'],
        ]);
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function serializeConfig(array $config): string
{
    return implode(PHP_EOL, [
        'ENABLED="' . ($config['enabled'] ? '1' : '0') . '"',
        'IPV4_ENABLED="' . ($config['ipv4_enabled'] ? '1' : '0') . '"',
        'IPV4_DEFAULT_ALLOW="' . ($config['ipv4_default_allow'] ? '1' : '0') . '"',
        'IPV6_ENABLED="' . ($config['ipv6_enabled'] ? '1' : '0') . '"',
        'IPV6_DEFAULT_ALLOW="' . ($config['ipv6_default_allow'] ? '1' : '0') . '"',
        '',
    ]);
}

function writeAtomic(string $path, string $contents): void
{
    if (!is_dir(CONFIG_DIR) && !mkdir(CONFIG_DIR, 0700, true) && !is_dir(CONFIG_DIR)) {
        throw new \RuntimeException('Unable to create the plugin configuration directory.');
    }

    $temporary = tempnam(CONFIG_DIR, '.unraid-firewall-');
    if ($temporary === false) {
        throw new \RuntimeException('Unable to create a temporary configuration file.');
    }

    try {
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write the configuration file.');
        }
        chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            throw new \RuntimeException('Unable to replace the configuration file.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

function csrfToken(): string
{
    if (isset($GLOBALS['var']['csrf_token']) && is_string($GLOBALS['var']['csrf_token'])) {
        return $GLOBALS['var']['csrf_token'];
    }

    $varFile = '/var/local/emhttp/var.ini';
    if (is_file($varFile)) {
        $values = @parse_ini_file($varFile, false, INI_SCANNER_RAW);
        if (is_array($values) && isset($values['csrf_token']) && is_string($values['csrf_token'])) {
            return $values['csrf_token'];
        }
    }

    return '';
}

function requireCsrfToken($token): void
{
    $expected = csrfToken();
    if (!is_string($token) || $expected === '' || !hash_equals($expected, $token)) {
        throw new \UnexpectedValueException('Invalid CSRF token.');
    }
}

function html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function checked(bool $value): string
{
    return $value ? ' checked' : '';
}

function firewallState(): string
{
    $state = is_file(STATE_FILE) ? trim((string) @file_get_contents(STATE_FILE)) : 'inactive';
    return in_array($state, ['active', 'inactive', 'error'], true) ? $state : 'unknown';
}
