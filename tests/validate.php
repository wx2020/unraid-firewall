<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/usr/local/emhttp/plugins/unraid-firewall/include/common.php';

use function UnraidFirewall\normalizeRuleRecord;
use function UnraidFirewall\postRuleRows;
use function UnraidFirewall\postToggle;
use function UnraidFirewall\serializeRuleRecords;

$ipv4 = normalizeRuleRecord([
    'name' => 'Management Web UI',
    'action' => 'allow',
    'source' => '192.168.1.0/24',
    'protocol' => 'tcp',
    'port' => '5000',
], 4);
if ($ipv4['port'] !== '5000' || $ipv4['protocol'] !== 'tcp') {
    throw new RuntimeException('IPv4 named rule was not normalized as expected.');
}

$ipv6 = normalizeRuleRecord([
    'name' => 'IPv6 range',
    'action' => 'deny',
    'source' => '2001:db8::/32',
    'protocol' => 'udp',
    'port' => '5000-5010',
], 6);
if ($ipv6['port'] !== '5000-5010' || $ipv6['action'] !== 'deny') {
    throw new RuntimeException('IPv6 named rule was not normalized as expected.');
}

$serialized = serializeRuleRecords([$ipv4, $ipv6]);
if ($serialized !== "Management Web UI|allow|192.168.1.0/24|tcp|5000\nIPv6 range|deny|2001:db8::/32|udp|5000-5010\n") {
    throw new RuntimeException('Named rule serialization did not match.');
}

$failed = false;
try {
    normalizeRuleRecord([
        'name' => 'Invalid port protocol',
        'action' => 'allow',
        'source' => '',
        'protocol' => 'any',
        'port' => '5000',
    ], 4);
} catch (InvalidArgumentException $exception) {
    $failed = true;
}
if (!$failed) {
    throw new RuntimeException('Any-protocol port validation did not fail.');
}

$posted = [
    'enabled' => '1',
    'ipv4_name' => ['Management Web UI', ''],
    'ipv4_action' => ['allow', 'allow'],
    'ipv4_source' => ['192.168.1.0/24', ''],
    'ipv4_protocol' => ['tcp', 'any'],
    'ipv4_port' => ['443', ''],
];
if (!postToggle($posted, 'enabled')) {
    throw new RuntimeException('Posted toggle was not parsed as enabled.');
}
$postedRules = postRuleRows($posted, 'ipv4', 4);
if (count($postedRules) !== 1 || $postedRules[0]['port'] !== '443') {
    throw new RuntimeException('Posted rule rows were not parsed as expected.');
}

echo "Rule validation tests passed.\n";
