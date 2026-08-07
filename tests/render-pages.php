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
    'action="/update.php"',
    'target="progressFrame"',
    'name="#file" value="unraid-firewall/unraid-firewall.cfg"',
    'name="#include" value="/plugins/unraid-firewall/include/update.php"',
    'name="#command" value="/plugins/unraid-firewall/webui-apply.sh"',
];

foreach ($requiredFragments as $fragment) {
    if (strpos($page, $fragment) === false) {
        throw new RuntimeException('Rendered WebUI is missing: ' . $fragment);
    }
}

if (strpos($page, 'XMLHttpRequest') !== false || strpos($page, 'fetch(') !== false) {
    throw new RuntimeException('Rendered WebUI still bypasses Unraid standard form submission.');
}

$updateHook = file_get_contents(__DIR__ . '/../src/usr/local/emhttp/plugins/unraid-firewall/include/update.php');
if ($updateHook === false || strpos($updateHook, 'requireCsrfToken') !== false) {
    throw new RuntimeException('The /update.php hook must rely on Unraid global CSRF validation.');
}

echo "WebUI render test passed.\n";
