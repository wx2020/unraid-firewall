# unraid-firewall

[中文文档](README_CN.md)

A lightweight inbound firewall plugin for Unraid, with separate IPv4 and IPv6 policy groups managed from the WebUI.

## Features

- Global switch to enable or disable the plugin policy.
- Independent IPv4 and IPv6 switches.
- Per-group default allow/deny behavior for sources that do not match a rule.
- IPv4 and IPv6 address or CIDR support, such as `192.168.1.0/24` and `fd00::/8`.
- Named rules with allow/deny action, source IP/CIDR, protocol, and destination port or port range, such as TCP `5000` or `5000-5010`.
- Blank source means any source. Blank port means all ports for the selected protocol.
- Rules are evaluated from top to bottom.
- Host inbound traffic is handled by plugin-owned `UNRAID_FIREWALL4` and `UNRAID_FIREWALL6` chains.
- Docker bridge published-port traffic is handled through plugin-owned chains hooked from Docker's `DOCKER-USER` chain.
- Docker forwarding rules are scoped to Docker bridge interfaces, preserving container egress, container-to-container traffic, and unrelated host forwarding.
- Rules are applied immediately when saved; plugin installation, array startup, Docker startup, and removal reapply or clean up the policy.

The plugin filters host `INPUT` traffic and Docker bridge published ports through `DOCKER-USER`. Host-network containers are covered by `INPUT`; macvlan/ipvlan and other non-bridge networks are not guaranteed to use `DOCKER-USER`. A Docker `DOCKER-USER` chain must exist for Docker forwarding rules to be applied. Docker userland-proxy traffic is covered by the host `INPUT` rules.

## Installation

### Method 1: Install from the plugin URL (recommended)

1. In Unraid, open **Settings > Plugins > Install Plugin**.
2. Paste the latest-release plugin URL:

`https://github.com/wx2020/unraid-firewall/releases/latest/download/unraid-firewall.plg`

3. Select **Install** and wait for the installation to complete.

### Method 2: Manual installation

1. Open the [latest release](https://github.com/wx2020/unraid-firewall/releases/latest).
2. Download the `unraid-firewall.plg` release asset.
3. In Unraid, open **Settings > Plugins > Install Plugin**.
4. Upload the downloaded `unraid-firewall.plg` file and select **Install**.

This URL always installs the most recently published plugin release. Changes
merged to `main` are not available through this URL until the **Build and
Release** workflow publishes a new release.

The `plugin/unraid-firewall.plg` file in the source tree is a release template.
Its package checksum is replaced by the release workflow, so the template
itself is not an installable release artifact.

## Usage

1. Open **Settings → Unraid Firewall** in Unraid.
2. Add a rule name, source IP/CIDR, protocol, and port. Add the management source first.
3. Enable the relevant IPv4/IPv6 group and the global policy switch.
4. For an allowlist, disable **Default allow inbound** for the relevant group.
5. Click **Apply settings**.

The plugin is disabled on first installation. Both protocol groups default to allowing unmatched sources to help prevent locking out the management WebUI.

## Build and release

```bash
./build.sh
# or Windows PowerShell
./build.ps1
```

The build creates `unraid-firewall-<version>-noarch-1.txz` and a SHA256 sidecar. Pull requests run GitHub Actions checks for PHP, WebUI rendering, shell syntax, metadata, and package structure. Pushing a date-version tag such as `2026.08.06.0004` (or the equivalent `v2026.08.06.0004`) runs the release workflow, which builds the package, calculates its SHA256, generates the final `.plg`, and publishes a GitHub Release.

The checked-in `plugin/unraid-firewall.plg` is a release template. Install the generated `unraid-firewall.plg` from a GitHub Release.

## Diagnostics

```bash
/etc/rc.d/rc.unraid-firewall status
/etc/rc.d/rc.unraid-firewall apply
tail -f /var/log/unraid-firewall.log
iptables -S UNRAID_FIREWALL4
ip6tables -S UNRAID_FIREWALL6
iptables -S DOCKER-USER
ip6tables -S DOCKER-USER
```
