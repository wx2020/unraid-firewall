#!/bin/bash

success_message="${1:-Firewall rules applied.}"
failure_message="${2:-The firewall rules could not be applied.}"

if /etc/rc.d/rc.unraid-firewall apply; then
    printf '%s\n' "$success_message"
else
    printf '%s\n' "$failure_message"
    exit 1
fi
