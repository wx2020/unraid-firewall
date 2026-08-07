<?php

declare(strict_types=1);

$path = __DIR__ . '/../src/usr/local/emhttp/languages/zh_CN/unraidfirewall.txt';
$lines = @file($path, FILE_IGNORE_NEW_LINES);
if (!is_array($lines)) {
    throw new RuntimeException('Unable to read the Simplified Chinese translation file.');
}

$keys = [];
foreach ($lines as $line) {
    $separator = strpos($line, '=');
    if ($separator === false) {
        continue;
    }
    $keys[substr($line, 0, $separator)] = true;
}

// Unraid normalizes these source keys before looking them up.
$requiredKeys = [
    'Unraid Firewall',
    'Safety warning',
    'apply rules only after confirming that your management source IP is allowed',
    'it does not replace Unraid or Dockers existing firewall rules',
    'Apply the IPv4IPv6 policies below',
    'When disabled, the plugin removes only its own chains and leaves existing Unraid rules unchanged',
    'Source IPCIDR',
    '19216810 24 or blank',
    'Rules are evaluated top-to-bottom Leave source blank for any source Leave port blank for all ports A port requires TCP or UDP',
    'Use IPv6 addresses or CIDRs Leave source blank for any source Leave port blank for all ports A port requires TCP or UDP',
    'Last apply state',
    'Applying firewall rules',
    'The firewall rules could not be applied',
    'The WebUI could not contact the firewall service',
    'The firewall request timed out',
    'Firewall rules applied',
    'Firewall settings saved Applying rules',
    'The firewall settings could not be saved',
];

foreach ($requiredKeys as $key) {
    if (!isset($keys[$key])) {
        throw new RuntimeException('Missing normalized translation key: ' . $key);
    }
}

echo "Translation key test passed.\n";
