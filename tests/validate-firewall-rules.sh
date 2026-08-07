#!/bin/bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export UNRAID_FIREWALL_SOURCE_ONLY=1
# shellcheck disable=SC1091
source "$ROOT_DIR/src/usr/local/etc/rc.d/rc.unraid-firewall"

FAKE_CALLS=()
fake_iptables() {
    FAKE_CALLS+=("$*")
}

fail() {
    echo "$1" >&2
    exit 1
}

FAKE_CALLS=()
append_policy_rule fake_iptables TEST_CHAIN "" "192.0.2.0/24" any 443 input ACCEPT
[ "${#FAKE_CALLS[@]}" -eq 2 ] || fail "Any-protocol host port rule did not expand to two commands."
case "${FAKE_CALLS[0]}" in
    *"-p tcp"*"-m tcp --dport 443"*"-j ACCEPT"*) ;;
    *) fail "Expanded TCP host rule is incorrect: ${FAKE_CALLS[0]}" ;;
esac
case "${FAKE_CALLS[1]}" in
    *"-p udp"*"-m udp --dport 443"*"-j ACCEPT"*) ;;
    *) fail "Expanded UDP host rule is incorrect: ${FAKE_CALLS[1]}" ;;
esac

FAKE_CALLS=()
append_policy_rule fake_iptables TEST_DOCKER_CHAIN "docker+" "" any 443 docker RETURN
[ "${#FAKE_CALLS[@]}" -eq 2 ] || fail "Any-protocol Docker port rule did not expand to two commands."
case "${FAKE_CALLS[0]}" in
    *"-o docker+"*"-p tcp"*"-m conntrack --ctorigdstport 443"*"-j RETURN"*) ;;
    *) fail "Expanded TCP Docker rule is incorrect: ${FAKE_CALLS[0]}" ;;
esac
case "${FAKE_CALLS[1]}" in
    *"-o docker+"*"-p udp"*"-m conntrack --ctorigdstport 443"*"-j RETURN"*) ;;
    *) fail "Expanded UDP Docker rule is incorrect: ${FAKE_CALLS[1]}" ;;
esac

FAKE_CALLS=()
append_policy_rule fake_iptables TEST_CHAIN "" "" any "" input ACCEPT
[ "${#FAKE_CALLS[@]}" -eq 1 ] || fail "Any-protocol all-port rule unexpectedly expanded."
case "${FAKE_CALLS[0]}" in
    *"-p "*) fail "Any-protocol all-port rule unexpectedly selected a protocol." ;;
esac

FAKE_CALLS=()
append_policy_rule fake_iptables TEST_CHAIN "" "" udp 53 input ACCEPT
[ "${#FAKE_CALLS[@]}" -eq 1 ] || fail "Explicit UDP rule did not remain a single command."
case "${FAKE_CALLS[0]}" in
    *"-p udp"*"-m udp --dport 53"*) ;;
    *) fail "Explicit UDP rule is incorrect: ${FAKE_CALLS[0]}" ;;
esac

echo "Firewall rule expansion tests passed."
