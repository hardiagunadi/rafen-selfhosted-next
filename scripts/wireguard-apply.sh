#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(cd "${SCRIPT_DIR}/.." && pwd)}"
WG_SYSTEM_DIR="${WG_SYSTEM_DIR:-/etc/wireguard}"
WG_SYSTEM_INTERFACE="${WG_SYSTEM_INTERFACE:-wg0}"
SYSTEMCTL_BIN="${SYSTEMCTL_BIN:-systemctl}"
SYSTEM_SERVICE="${WG_SYSTEM_SERVICE:-wg-quick@${WG_SYSTEM_INTERFACE}}"
APP_CONFIG_PATH="${APP_DIR}/storage/app/wireguard/${WG_SYSTEM_INTERFACE}.conf"
SYSTEM_CONFIG_PATH="${WG_SYSTEM_DIR}/${WG_SYSTEM_INTERFACE}.conf"

mkdir -p "${WG_SYSTEM_DIR}"
install -m 600 "$APP_CONFIG_PATH" "$SYSTEM_CONFIG_PATH"
"${SYSTEMCTL_BIN}" daemon-reload
"${SYSTEMCTL_BIN}" enable --now "$SYSTEM_SERVICE"
"${SYSTEMCTL_BIN}" restart "$SYSTEM_SERVICE"
