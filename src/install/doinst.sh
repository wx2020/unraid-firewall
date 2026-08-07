#!/bin/sh

set -eu

CONFIG_DIR="/boot/config/plugins/unraid-firewall"
mkdir -p "$CONFIG_DIR"
mkdir -p /etc/rc.d
mkdir -p /usr/local/emhttp/plugins/unraid-firewall/event

if [ ! -f "$CONFIG_DIR/unraid-firewall.cfg" ]; then
    cat > "$CONFIG_DIR/unraid-firewall.cfg" <<'EOF'
ENABLED="0"
IPV4_ENABLED="1"
IPV4_DEFAULT_ALLOW="1"
IPV6_ENABLED="1"
IPV6_DEFAULT_ALLOW="1"
EOF
fi

[ -f "$CONFIG_DIR/ipv4.rules" ] || : > "$CONFIG_DIR/ipv4.rules"
[ -f "$CONFIG_DIR/ipv6.rules" ] || : > "$CONFIG_DIR/ipv6.rules"
chmod 0600 "$CONFIG_DIR/unraid-firewall.cfg" "$CONFIG_DIR/ipv4.rules" "$CONFIG_DIR/ipv6.rules"

ln -sfn /usr/local/etc/rc.d/rc.unraid-firewall /etc/rc.d/rc.unraid-firewall
ln -sfn ../reapply.sh /usr/local/emhttp/plugins/unraid-firewall/event/array_started
ln -sfn ../reapply.sh /usr/local/emhttp/plugins/unraid-firewall/event/docker_started

chmod 0755 \
    /usr/local/etc/rc.d/rc.unraid-firewall \
    /usr/local/emhttp/plugins/unraid-firewall/reapply.sh \
    /usr/local/emhttp/plugins/unraid-firewall/webui-apply.sh

# Remove the legacy direct AJAX endpoint. Settings are saved through Unraid's
# standard /update.php include-and-command workflow.
rm -f /usr/local/emhttp/plugins/unraid-firewall/include/apply.php

mkdir -p /var/log
touch /var/log/unraid-firewall.log
chmod 0644 /var/log/unraid-firewall.log

# Rules are disabled by default, but remove any stale chain left by an older
# installation before the WebUI is used.
/etc/rc.d/rc.unraid-firewall apply >/dev/null 2>&1 || true
