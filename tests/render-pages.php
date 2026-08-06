<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/usr/local/emhttp/plugins/unraid-firewall/include/page.php';

$page = \UnraidFirewall\getSettingsPage();

$requiredFragments = [
    'unraidFirewallForm',
    'ipv4_name[]',
    'ipv4_protocol[]',
    'ipv4_port[]',
    'ipv6_name[]',
    'ipv6_protocol[]',
    'ipv6_port[]',
    'DOCKER-USER',
];

foreach ($requiredFragments as $fragment) {
    if (strpos($page, $fragment) === false) {
        throw new RuntimeException('Rendered WebUI is missing: ' . $fragment);
    }
}

echo "WebUI render test passed.\n";
