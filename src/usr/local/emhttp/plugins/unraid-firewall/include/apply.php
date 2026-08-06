#!/usr/bin/php
<?php

declare(strict_types=1);

namespace UnraidFirewall;

require_once __DIR__ . '/common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $body, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function postToggle(string $name): bool
{
    if (!array_key_exists($name, $_POST)) {
        throw new \InvalidArgumentException('Missing setting: ' . $name);
    }

    $value = $_POST[$name];
    if (is_array($value)) {
        throw new \InvalidArgumentException('Invalid setting: ' . $name);
    }

    $value = strtolower(trim((string) $value));
    if (in_array($value, ['1', 'true', 'on', 'yes'], true)) {
        return true;
    }
    if (in_array($value, ['0', 'false', 'off', 'no'], true)) {
        return false;
    }

    throw new \InvalidArgumentException('Invalid setting: ' . $name);
}

function postRuleRows(string $prefix, int $family): array
{
    $fields = [];
    foreach (['name', 'action', 'source', 'protocol', 'port'] as $field) {
        $key = $prefix . '_' . $field;
        $value = $_POST[$key] ?? [];
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Invalid rule field: ' . $key);
        }
        $fields[$field] = array_values($value);
    }

    $count = 0;
    foreach ($fields as $values) {
        $count = max($count, count($values));
    }
    if ($count > 512) {
        throw new \InvalidArgumentException('A protocol group may contain at most 512 rules.');
    }

    $records = [];
    for ($index = 0; $index < $count; $index++) {
        $rule = [];
        foreach ($fields as $field => $values) {
            $value = $values[$index] ?? '';
            if (is_array($value)) {
                throw new \InvalidArgumentException('Invalid value in ' . $prefix . '_' . $field . '.');
            }
            $rule[$field] = (string) $value;
        }

        $isEmptyRow = trim($rule['name']) === ''
            && trim($rule['source']) === ''
            && trim($rule['port']) === ''
            && strtolower(trim($rule['action'])) === 'allow'
            && strtolower(trim($rule['protocol'])) === 'any';
        if ($isEmptyRow) {
            continue;
        }

        $records[] = normalizeRuleRecord($rule, $family, ucfirst($prefix) . ' rule ' . ($index + 1));
    }

    return $records;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        respond(['success' => false, 'message' => 'POST is required.'], 405);
    }

    requireCsrfToken($_POST['csrf_token'] ?? null);

    $config = [
        'enabled' => postToggle('enabled'),
        'ipv4_enabled' => postToggle('ipv4_enabled'),
        'ipv4_default_allow' => postToggle('ipv4_default_allow'),
        'ipv6_enabled' => postToggle('ipv6_enabled'),
        'ipv6_default_allow' => postToggle('ipv6_default_allow'),
    ];

    $rules4 = postRuleRows('ipv4', 4);
    $rules6 = postRuleRows('ipv6', 6);

    writeAtomic(CONFIG_FILE, serializeConfig($config));
    writeAtomic(RULES4_FILE, serializeRuleRecords($rules4));
    writeAtomic(RULES6_FILE, serializeRuleRecords($rules6));

    $command = '/etc/rc.d/rc.unraid-firewall';
    if (!is_executable($command)) {
        throw new \RuntimeException('The firewall service script is not installed.');
    }

    $output = [];
    $exitCode = 0;
    exec(escapeshellarg($command) . ' apply 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        $details = trim(implode("\n", $output));
        throw new \RuntimeException($details !== '' ? $details : 'The firewall service rejected the configuration.');
    }

    respond(['success' => true, 'message' => 'Firewall rules saved and applied.']);
} catch (\UnexpectedValueException $exception) {
    respond(['success' => false, 'message' => $exception->getMessage()], 403);
} catch (\Throwable $exception) {
    respond(['success' => false, 'message' => $exception->getMessage()], 400);
}
