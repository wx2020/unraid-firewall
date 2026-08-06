# unraid-firewall

`unraid-firewall` is a small Unraid plugin for controlling host inbound traffic from the WebUI.

It provides separate IPv4 and IPv6 groups, group enable switches, per-group default allow/deny behavior, and named rules with action, source IP/CIDR, protocol, and destination port/range. Rules are evaluated top-to-bottom. Rules are installed in plugin-owned `UNRAID_FIREWALL4` and `UNRAID_FIREWALL6` chains, plus plugin-owned chains hooked from Docker's `DOCKER-USER` chain.

The plugin manages host `INPUT` traffic and Docker bridge forwarding through `DOCKER-USER`. Docker interface patterns are scoped so container egress, container-to-container traffic, and unrelated host forwarding are not blocked by a deny-by-default Docker group. Host-network containers are covered by `INPUT`; macvlan/ipvlan and other non-bridge drivers are not guaranteed to use `DOCKER-USER`.

The initial configuration is disabled and defaults to allow unmatched sources to avoid locking out the WebUI during installation. Add the management source first, then enable a deny-by-default group when using an allowlist.

Build the package with `build.sh`, `build.ps1`, or `make`. Pull requests run the package, PHP, WebUI, shell, and metadata checks. Pushing a date-version tag such as `2026.08.06.0003` (or the equivalent `v2026.08.06.0003`) runs the GitHub Actions release job, which builds the package, calculates its SHA256, generates the final `.plg`, and publishes the GitHub Release. The checked-in `plugin/unraid-firewall.plg` is a release template; install the generated `unraid-firewall.plg` from a Release.
