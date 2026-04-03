#!/usr/bin/env bash
set -Eeuo pipefail

APP_CONFIG_PATH="/var/www/rafen-selfhosted-next/storage/app/wireguard/wg0.conf"
SYSTEM_CONFIG_PATH="/etc/wireguard/wg0.conf"
SYSTEM_SERVICE="wg-quick@wg0"

mkdir -p "/etc/wireguard"
install -m 600 "$APP_CONFIG_PATH" "$SYSTEM_CONFIG_PATH"
"systemctl" daemon-reload
"systemctl" enable --now "$SYSTEM_SERVICE"
"systemctl" restart "$SYSTEM_SERVICE"
