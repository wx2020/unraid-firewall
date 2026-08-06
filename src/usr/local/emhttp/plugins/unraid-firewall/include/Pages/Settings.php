<?php

declare(strict_types=1);

namespace UnraidFirewall;

$config = loadConfig();
$rules4 = readRuleRecords(RULES4_FILE, 4);
$rules6 = readRuleRecords(RULES6_FILE, 6);
$csrf = csrfToken();
$state = firewallState();
$stateLabel = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'error' => 'Error applying rules',
    'unknown' => 'Unknown',
][$state] ?? 'Unknown';

$renderRuleRow = static function (string $family, array $rule): string {
    $prefix = $family === '4' ? 'ipv4' : 'ipv6';
    ob_start();
    ?>
    <tr class="ufw-rule-row">
        <td><input type="text" name="<?= html($prefix) ?>_name[]" value="<?= html($rule['name'] ?? '') ?>" placeholder="Web UI"></td>
        <td>
            <select name="<?= html($prefix) ?>_action[]">
                <option value="allow"<?= ($rule['action'] ?? 'allow') === 'allow' ? ' selected' : '' ?>>Allow</option>
                <option value="deny"<?= ($rule['action'] ?? '') === 'deny' ? ' selected' : '' ?>>Deny</option>
            </select>
        </td>
        <td><input type="text" name="<?= html($prefix) ?>_source[]" value="<?= html($rule['source'] ?? '') ?>" placeholder="192.168.1.0/24 or blank"></td>
        <td>
            <select name="<?= html($prefix) ?>_protocol[]">
                <option value="any"<?= ($rule['protocol'] ?? 'any') === 'any' ? ' selected' : '' ?>>Any</option>
                <option value="tcp"<?= ($rule['protocol'] ?? '') === 'tcp' ? ' selected' : '' ?>>TCP</option>
                <option value="udp"<?= ($rule['protocol'] ?? '') === 'udp' ? ' selected' : '' ?>>UDP</option>
            </select>
        </td>
        <td><input type="text" name="<?= html($prefix) ?>_port[]" value="<?= html($rule['port'] ?? '') ?>" placeholder="5000 or 5000-5010"></td>
        <td><button type="button" class="ufw-remove-rule" onclick="removeRuleRow(this)" aria-label="Remove rule">Remove</button></td>
    </tr>
    <?php
    return (string) ob_get_clean();
};

$emptyRule = [
    'name' => '',
    'action' => 'allow',
    'source' => '',
    'protocol' => 'any',
    'port' => '',
];
$displayRules4 = $rules4 === [] ? [$emptyRule] : $rules4;
$displayRules6 = $rules6 === [] ? [$emptyRule] : $rules6;
?>
<link rel="stylesheet" type="text/css" href="/plugins/unraid-firewall/styles/settings.css">

<div class="ufw-page" id="unraid-firewall-page">
    <div class="ufw-notice ufw-warning">
        <strong>Safety warning:</strong>
        apply rules only after confirming that your management source IP is allowed.
        The plugin manages host <code>INPUT</code> traffic and Docker bridge forwarding via <code>DOCKER-USER</code>;
        it does not replace Unraid or Docker's existing firewall rules.
    </div>

    <table class="unraid tablesorter"><thead><tr><td>Firewall policy</td></tr></thead></table>

    <form method="post" action="/plugins/unraid-firewall/include/apply.php" id="unraidFirewallForm">
        <input type="hidden" name="csrf_token" value="<?= html($csrf) ?>">

        <dl>
            <dt>Enable firewall policy</dt>
            <dd>
                <input type="hidden" name="enabled" value="0">
                <label class="ufw-switch">
                    <input type="checkbox" name="enabled" value="1"<?= checked($config['enabled']) ?>>
                    <span class="ufw-slider" aria-hidden="true"></span>
                </label>
                <span class="ufw-switch-label">Apply the IPv4/IPv6 policies below</span>
            </dd>
        </dl>
        <blockquote class="inline_help">When disabled, the plugin removes only its own chains and leaves existing Unraid rules unchanged.</blockquote>

        <div class="ufw-groups">
            <section class="ufw-group">
                <div class="ufw-group-heading">
                    <h3>IPv4</h3>
                    <span class="ufw-family-badge">iptables</span>
                </div>

                <dl>
                    <dt>Enable IPv4 rules</dt>
                    <dd>
                        <input type="hidden" name="ipv4_enabled" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv4_enabled" value="1"<?= checked($config['ipv4_enabled']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                    </dd>
                </dl>

                <dl>
                    <dt>Default allow inbound</dt>
                    <dd>
                        <input type="hidden" name="ipv4_default_allow" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv4_default_allow" value="1"<?= checked($config['ipv4_default_allow']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                        <span class="ufw-switch-label">Allow sources that do not match a rule</span>
                    </dd>
                </dl>

                <label class="ufw-textarea-label">IPv4 inbound rules</label>
                <div class="ufw-rule-table-wrap">
                    <table class="ufw-rule-table">
                        <thead><tr><th>Name</th><th>Action</th><th>Source IP/CIDR</th><th>Protocol</th><th>Destination port</th><th></th></tr></thead>
                        <tbody id="ipv4RuleRows">
                            <?php foreach ($displayRules4 as $rule): ?><?= $renderRuleRow('4', $rule) ?><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="ufw-secondary" onclick="addRuleRow('ipv4')">Add IPv4 rule</button>
                <p class="ufw-help">Rules are evaluated top-to-bottom. Leave source blank for any source. Leave port blank for all ports; a port requires TCP or UDP.</p>
            </section>

            <section class="ufw-group">
                <div class="ufw-group-heading">
                    <h3>IPv6</h3>
                    <span class="ufw-family-badge">ip6tables</span>
                </div>

                <dl>
                    <dt>Enable IPv6 rules</dt>
                    <dd>
                        <input type="hidden" name="ipv6_enabled" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv6_enabled" value="1"<?= checked($config['ipv6_enabled']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                    </dd>
                </dl>

                <dl>
                    <dt>Default allow inbound</dt>
                    <dd>
                        <input type="hidden" name="ipv6_default_allow" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv6_default_allow" value="1"<?= checked($config['ipv6_default_allow']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                        <span class="ufw-switch-label">Allow sources that do not match a rule</span>
                    </dd>
                </dl>

                <label class="ufw-textarea-label">IPv6 inbound rules</label>
                <div class="ufw-rule-table-wrap">
                    <table class="ufw-rule-table">
                        <thead><tr><th>Name</th><th>Action</th><th>Source IP/CIDR</th><th>Protocol</th><th>Destination port</th><th></th></tr></thead>
                        <tbody id="ipv6RuleRows">
                            <?php foreach ($displayRules6 as $rule): ?><?= $renderRuleRow('6', $rule) ?><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="ufw-secondary" onclick="addRuleRow('ipv6')">Add IPv6 rule</button>
                <p class="ufw-help">Use IPv6 addresses or CIDRs. Leave source blank for any source. Leave port blank for all ports; a port requires TCP or UDP.</p>
            </section>
        </div>

        <div class="ufw-actions">
            <button type="submit" class="ufw-primary" id="ufwApplyButton">Apply settings</button>
            <span class="ufw-runtime-status">Last apply state: <strong class="ufw-status-<?= html($state) ?>" id="ufwState"><?= html($stateLabel) ?></strong></span>
        </div>
        <div class="ufw-result" id="ufwResult" role="status" aria-live="polite"></div>
    </form>
</div>

<script>
function addRuleRow(family) {
    var prefix = family === 'ipv6' ? 'ipv6' : 'ipv4';
    var rows = document.getElementById(prefix + 'RuleRows');
    if (!rows) return;

    var row = document.createElement('tr');
    row.className = 'ufw-rule-row';
    row.innerHTML =
        '<td><input type="text" name="' + prefix + '_name[]" placeholder="Web UI"></td>' +
        '<td><select name="' + prefix + '_action[]"><option value="allow">Allow</option><option value="deny">Deny</option></select></td>' +
        '<td><input type="text" name="' + prefix + '_source[]" placeholder="192.168.1.0/24 or blank"></td>' +
        '<td><select name="' + prefix + '_protocol[]"><option value="any">Any</option><option value="tcp">TCP</option><option value="udp">UDP</option></select></td>' +
        '<td><input type="text" name="' + prefix + '_port[]" placeholder="5000 or 5000-5010"></td>' +
        '<td><button type="button" class="ufw-remove-rule" onclick="removeRuleRow(this)" aria-label="Remove rule">Remove</button></td>';
    rows.appendChild(row);
}

function removeRuleRow(button) {
    var row = button.closest('tr');
    if (!row) return;
    var rows = row.parentElement;
    row.remove();
    if (rows && rows.children.length === 0) {
        addRuleRow(rows.id.indexOf('ipv6') === 0 ? 'ipv6' : 'ipv4');
    }
}

(function () {
    var form = document.getElementById('unraidFirewallForm');
    var button = document.getElementById('ufwApplyButton');
    var result = document.getElementById('ufwResult');

    if (!form) return;

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        button.disabled = true;
        result.className = 'ufw-result ufw-result-info';
        result.textContent = 'Applying firewall rules…';

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'The firewall rules could not be applied.');
                }
                return data;
            });
        }).then(function (data) {
            result.className = 'ufw-result ufw-result-success';
            result.textContent = data.message || 'Firewall rules applied.';
            window.setTimeout(function () { window.location.reload(); }, 700);
        }).catch(function (error) {
            result.className = 'ufw-result ufw-result-error';
            result.textContent = error.message;
            button.disabled = false;
        });
    });
}());
</script>
