<?php

declare(strict_types=1);

namespace UnraidFirewall;

require_once __DIR__ . '/common.php';

// Unraid's /update.php includes this file before writing its generic INI
// output. This plugin owns three files and therefore performs the complete
// atomic save here, then disables the generic writer with $save = false.
$save = false;

try {
    firewallLog('WebUI save request received.');
    requireCsrfToken($_POST['csrf_token'] ?? null);

    $config = [
        'enabled' => postToggle($_POST, 'enabled'),
        'ipv4_enabled' => postToggle($_POST, 'ipv4_enabled'),
        'ipv4_default_allow' => postToggle($_POST, 'ipv4_default_allow'),
        'ipv6_enabled' => postToggle($_POST, 'ipv6_enabled'),
        'ipv6_default_allow' => postToggle($_POST, 'ipv6_default_allow'),
    ];

    $rules4 = postRuleRows($_POST, 'ipv4', 4);
    $rules6 = postRuleRows($_POST, 'ipv6', 6);

    writeAtomic(CONFIG_FILE, serializeConfig($config));
    writeAtomic(RULES4_FILE, serializeRuleRecords($rules4));
    writeAtomic(RULES6_FILE, serializeRuleRecords($rules6));

    if (function_exists('write_log')) {
        \write_log(translateText('Firewall settings saved. Applying rules...'));
    }
} catch (\Throwable $exception) {
    unset($_POST['#command']);
    firewallLog('WebUI save request failed: ' . $exception->getMessage());
    if (function_exists('write_log')) {
        \write_log(translateText('The firewall settings could not be saved.') . ' ' . $exception->getMessage());
    }
}
