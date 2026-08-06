<?php

declare(strict_types=1);

namespace UnraidFirewall;

$config = loadConfig();
$rules4 = readRuleRecords(RULES4_FILE, 4);
$rules6 = readRuleRecords(RULES6_FILE, 6);
$csrf = csrfToken();
$state = firewallState();
$stateLabel = [
    'active' => translateText('Active'),
    'inactive' => translateText('Inactive'),
    'error' => translateText('Error applying rules'),
    'unknown' => translateText('Unknown'),
][$state] ?? translateText('Unknown');

$renderRuleRow = static function (string $family, array $rule): string {
    $prefix = $family === '4' ? 'ipv4' : 'ipv6';
    ob_start();
    ?>
    <tr class="ufw-rule-row">
        <td><input type="text" name="<?= html($prefix) ?>_name[]" value="<?= html($rule['name'] ?? '') ?>" placeholder="<?= html(translateText('Web UI')) ?>"></td>
        <td>
            <select name="<?= html($prefix) ?>_action[]">
                <option value="allow"<?= ($rule['action'] ?? 'allow') === 'allow' ? ' selected' : '' ?>><?= html(translateText('Allow')) ?></option>
                <option value="deny"<?= ($rule['action'] ?? '') === 'deny' ? ' selected' : '' ?>><?= html(translateText('Deny')) ?></option>
            </select>
        </td>
        <td><input type="text" name="<?= html($prefix) ?>_source[]" value="<?= html($rule['source'] ?? '') ?>" placeholder="<?= html(translateText('192.168.1.0/24 or blank')) ?>"></td>
        <td>
            <select name="<?= html($prefix) ?>_protocol[]">
                <option value="any"<?= ($rule['protocol'] ?? 'any') === 'any' ? ' selected' : '' ?>><?= html(translateText('Any')) ?></option>
                <option value="tcp"<?= ($rule['protocol'] ?? '') === 'tcp' ? ' selected' : '' ?>><?= html(translateText('TCP')) ?></option>
                <option value="udp"<?= ($rule['protocol'] ?? '') === 'udp' ? ' selected' : '' ?>><?= html(translateText('UDP')) ?></option>
            </select>
        </td>
        <td><input type="text" name="<?= html($prefix) ?>_port[]" value="<?= html($rule['port'] ?? '') ?>" placeholder="<?= html(translateText('5000 or 5000-5010')) ?>"></td>
        <td><button type="button" class="ufw-remove-rule" onclick="removeRuleRow(this)" aria-label="<?= html(translateText('Remove rule')) ?>"><?= html(translateText('Remove')) ?></button></td>
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
        <strong><?= html(translateText('Safety warning:')) ?></strong>
        <?= html(translateText('apply rules only after confirming that your management source IP is allowed.')) ?>
        <?= html(translateText('The plugin manages host')) ?> <code>INPUT</code>
        <?= html(translateText('traffic and Docker bridge forwarding via')) ?> <code>DOCKER-USER</code>；
        <?= html(translateText('it does not replace Unraid or Docker\'s existing firewall rules.')) ?>
    </div>

    <table class="unraid tablesorter"><thead><tr><td><?= html(translateText('Firewall policy')) ?></td></tr></thead></table>

    <form method="post" action="/plugins/unraid-firewall/include/apply.php" id="unraidFirewallForm">
        <input type="hidden" name="csrf_token" value="<?= html($csrf) ?>">

        <dl>
            <dt><?= html(translateText('Enable firewall policy')) ?></dt>
            <dd>
                <input type="hidden" name="enabled" value="0">
                <label class="ufw-switch">
                    <input type="checkbox" name="enabled" value="1"<?= checked($config['enabled']) ?>>
                    <span class="ufw-slider" aria-hidden="true"></span>
                </label>
                <span class="ufw-switch-label"><?= html(translateText('Apply the IPv4/IPv6 policies below')) ?></span>
            </dd>
        </dl>
        <blockquote class="inline_help"><?= html(translateText('When disabled, the plugin removes only its own chains and leaves existing Unraid rules unchanged.')) ?></blockquote>

        <div class="ufw-groups">
            <section class="ufw-group">
                <div class="ufw-group-heading">
                    <h3><?= html(translateText('IPv4')) ?></h3>
                    <span class="ufw-family-badge"><?= html(translateText('iptables')) ?></span>
                </div>

                <dl>
                    <dt><?= html(translateText('Enable IPv4 rules')) ?></dt>
                    <dd>
                        <input type="hidden" name="ipv4_enabled" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv4_enabled" value="1"<?= checked($config['ipv4_enabled']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                    </dd>
                </dl>

                <dl>
                    <dt><?= html(translateText('Default allow inbound')) ?></dt>
                    <dd>
                        <input type="hidden" name="ipv4_default_allow" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv4_default_allow" value="1"<?= checked($config['ipv4_default_allow']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                        <span class="ufw-switch-label"><?= html(translateText('Allow sources that do not match a rule')) ?></span>
                    </dd>
                </dl>

                <label class="ufw-textarea-label"><?= html(translateText('IPv4 inbound rules')) ?></label>
                <div class="ufw-rule-table-wrap">
                    <table class="ufw-rule-table">
                        <thead><tr><th><?= html(translateText('Name')) ?></th><th><?= html(translateText('Action')) ?></th><th><?= html(translateText('Source IP/CIDR')) ?></th><th><?= html(translateText('Protocol')) ?></th><th><?= html(translateText('Destination port')) ?></th><th></th></tr></thead>
                        <tbody id="ipv4RuleRows">
                            <?php foreach ($displayRules4 as $rule): ?><?= $renderRuleRow('4', $rule) ?><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="ufw-secondary" onclick="addRuleRow('ipv4')"><?= html(translateText('Add IPv4 rule')) ?></button>
                <p class="ufw-help"><?= html(translateText('Rules are evaluated top-to-bottom. Leave source blank for any source. Leave port blank for all ports. A port requires TCP or UDP.')) ?></p>
            </section>

            <section class="ufw-group">
                <div class="ufw-group-heading">
                    <h3><?= html(translateText('IPv6')) ?></h3>
                    <span class="ufw-family-badge"><?= html(translateText('ip6tables')) ?></span>
                </div>

                <dl>
                    <dt><?= html(translateText('Enable IPv6 rules')) ?></dt>
                    <dd>
                        <input type="hidden" name="ipv6_enabled" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv6_enabled" value="1"<?= checked($config['ipv6_enabled']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                    </dd>
                </dl>

                <dl>
                    <dt><?= html(translateText('Default allow inbound')) ?></dt>
                    <dd>
                        <input type="hidden" name="ipv6_default_allow" value="0">
                        <label class="ufw-switch">
                            <input type="checkbox" name="ipv6_default_allow" value="1"<?= checked($config['ipv6_default_allow']) ?>>
                            <span class="ufw-slider" aria-hidden="true"></span>
                        </label>
                        <span class="ufw-switch-label"><?= html(translateText('Allow sources that do not match a rule')) ?></span>
                    </dd>
                </dl>

                <label class="ufw-textarea-label"><?= html(translateText('IPv6 inbound rules')) ?></label>
                <div class="ufw-rule-table-wrap">
                    <table class="ufw-rule-table">
                        <thead><tr><th><?= html(translateText('Name')) ?></th><th><?= html(translateText('Action')) ?></th><th><?= html(translateText('Source IP/CIDR')) ?></th><th><?= html(translateText('Protocol')) ?></th><th><?= html(translateText('Destination port')) ?></th><th></th></tr></thead>
                        <tbody id="ipv6RuleRows">
                            <?php foreach ($displayRules6 as $rule): ?><?= $renderRuleRow('6', $rule) ?><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="ufw-secondary" onclick="addRuleRow('ipv6')"><?= html(translateText('Add IPv6 rule')) ?></button>
                <p class="ufw-help"><?= html(translateText('Use IPv6 addresses or CIDRs. Leave source blank for any source. Leave port blank for all ports. A port requires TCP or UDP.')) ?></p>
            </section>
        </div>

        <div class="ufw-actions">
            <button type="submit" class="ufw-primary" id="ufwApplyButton"><?= html(translateText('Apply settings')) ?></button>
            <span class="ufw-runtime-status"><?= html(translateText('Last apply state:')) ?> <strong class="ufw-status-<?= html($state) ?>" id="ufwState"><?= html($stateLabel) ?></strong></span>
        </div>
        <div class="ufw-result" id="ufwResult" role="status" aria-live="polite"></div>
    </form>
</div>

<?php
$javascriptText = [
    'webUi' => translateText('Web UI'),
    'allow' => translateText('Allow'),
    'deny' => translateText('Deny'),
    'sourcePlaceholder' => translateText('192.168.1.0/24 or blank'),
    'any' => translateText('Any'),
    'tcp' => translateText('TCP'),
    'udp' => translateText('UDP'),
    'portPlaceholder' => translateText('5000 or 5000-5010'),
    'removeRule' => translateText('Remove rule'),
    'remove' => translateText('Remove'),
    'applying' => translateText('Applying firewall rules...'),
    'applyFailed' => translateText('The firewall rules could not be applied.'),
    'applied' => translateText('Firewall rules applied.'),
];
?>
<script>
var unraidFirewallText = <?= json_encode($javascriptText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function addRuleRow(family) {
    var prefix = family === 'ipv6' ? 'ipv6' : 'ipv4';
    var rows = document.getElementById(prefix + 'RuleRows');
    if (!rows) return;

    var row = document.createElement('tr');
    row.className = 'ufw-rule-row';
    row.innerHTML =
        '<td><input type="text" name="' + prefix + '_name[]" placeholder="' + unraidFirewallText.webUi + '"></td>' +
        '<td><select name="' + prefix + '_action[]"><option value="allow">' + unraidFirewallText.allow + '</option><option value="deny">' + unraidFirewallText.deny + '</option></select></td>' +
        '<td><input type="text" name="' + prefix + '_source[]" placeholder="' + unraidFirewallText.sourcePlaceholder + '"></td>' +
        '<td><select name="' + prefix + '_protocol[]"><option value="any">' + unraidFirewallText.any + '</option><option value="tcp">' + unraidFirewallText.tcp + '</option><option value="udp">' + unraidFirewallText.udp + '</option></select></td>' +
        '<td><input type="text" name="' + prefix + '_port[]" placeholder="' + unraidFirewallText.portPlaceholder + '"></td>' +
        '<td><button type="button" class="ufw-remove-rule" onclick="removeRuleRow(this)" aria-label="' + unraidFirewallText.removeRule + '">' + unraidFirewallText.remove + '</button></td>';
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
        result.textContent = unraidFirewallText.applying;

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                    throw new Error(data.message || unraidFirewallText.applyFailed);
                }
                return data;
            });
        }).then(function (data) {
            result.className = 'ufw-result ufw-result-success';
            result.textContent = unraidFirewallText.applied;
            window.setTimeout(function () { window.location.reload(); }, 700);
        }).catch(function (error) {
            result.className = 'ufw-result ufw-result-error';
            result.textContent = error.message;
            button.disabled = false;
        });
    });
}());
</script>
