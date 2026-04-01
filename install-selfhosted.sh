#!/usr/bin/env bash
set -Eeuo pipefail

IFS=$'\n\t'

MODE="install"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$SCRIPT_DIR}"
EXPECTED_APP_DIR="${EXPECTED_APP_DIR:-/var/www/rafen-selfhosted-next}"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
ENV_EXAMPLE_FILE="${ENV_EXAMPLE_FILE:-$APP_DIR/.env.example}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_GROUP="${DEPLOY_GROUP:-$DEPLOY_USER}"
DEPLOY_PASSWORD="${DEPLOY_PASSWORD:-}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
SYSTEM_TIMEZONE="${SYSTEM_TIMEZONE:-Asia/Jakarta}"
PHP_PREFERRED_VERSION="${PHP_PREFERRED_VERSION:-8.4}"
PHP_BIN_EXPLICIT="${PHP_BIN+x}"
PHP_BIN="${PHP_BIN:-php}"
NODE_PREFERRED_MAJOR="${NODE_PREFERRED_MAJOR:-22}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
APT_GET_BIN="${APT_GET_BIN:-apt-get}"
SYSTEMCTL_BIN="${SYSTEMCTL_BIN:-systemctl}"
VISUDO_BIN="${VISUDO_BIN:-visudo}"
ALLOW_NON_ROOT="${ALLOW_NON_ROOT:-0}"
DRY_RUN="${DRY_RUN:-0}"
RUN_COMPOSER_INSTALL="${RUN_COMPOSER_INSTALL:-1}"
RUN_NPM_BUILD="${RUN_NPM_BUILD:-1}"
RUN_MIGRATE="${RUN_MIGRATE:-1}"
RUN_SUPER_ADMIN_SETUP="${RUN_SUPER_ADMIN_SETUP:-1}"
RUN_WIREGUARD_SYSTEM_BOOTSTRAP="${RUN_WIREGUARD_SYSTEM_BOOTSTRAP:-1}"
RUN_WIREGUARD_PACKAGE_INSTALL="${RUN_WIREGUARD_PACKAGE_INSTALL:-1}"
APP_URL_OVERRIDE="${APP_URL_OVERRIDE:-}"
APP_DOMAIN="${APP_DOMAIN:-}"
LICENSE_PUBLIC_KEY_VALUE="${LICENSE_PUBLIC_KEY_VALUE:-}"
SELF_HOSTED_REGISTRY_URL_VALUE="${SELF_HOSTED_REGISTRY_URL_VALUE:-}"
SELF_HOSTED_REGISTRY_TOKEN_VALUE="${SELF_HOSTED_REGISTRY_TOKEN_VALUE:-}"
ADMIN_NAME="${ADMIN_NAME:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
ADMIN_PHONE="${ADMIN_PHONE:-}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
RUN_SYSTEM_BOOTSTRAP="${RUN_SYSTEM_BOOTSTRAP:-1}"
DB_CONNECTION="${DB_CONNECTION:-sqlite}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-$APP_DIR/database/database.sqlite}"
DB_USERNAME="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
WG_SYSTEM_DIR="${WG_SYSTEM_DIR:-/etc/wireguard}"
WG_SYSTEM_INTERFACE="${WG_SYSTEM_INTERFACE:-wg0}"
WG_SYSTEM_SERVICE="${WG_SYSTEM_SERVICE:-wg-quick@${WG_SYSTEM_INTERFACE}}"
DEPLOY_SUDOERS_PATH="${DEPLOY_SUDOERS_PATH:-/etc/sudoers.d/rafen-deploy}"
WG_SUDOERS_PATH="${WG_SUDOERS_PATH:-/etc/sudoers.d/rafen-wireguard}"
WG_SYNC_HELPER_PATH="${WG_SYNC_HELPER_PATH:-$APP_DIR/scripts/wireguard-apply.sh}"
SERVER_HEALTH_SUDOERS_PATH="${SERVER_HEALTH_SUDOERS_PATH:-/etc/sudoers.d/rafen-server-health}"
SYSTEM_PRIMARY_IP="${SYSTEM_PRIMARY_IP:-}"
NGINX_BIN="${NGINX_BIN:-nginx}"
NGINX_SERVICE="${NGINX_SERVICE:-nginx}"
NGINX_SITE_AVAILABLE_PATH="${NGINX_SITE_AVAILABLE_PATH:-/etc/nginx/sites-available/rafen-selfhosted.conf}"
NGINX_SITE_ENABLED_PATH="${NGINX_SITE_ENABLED_PATH:-/etc/nginx/sites-enabled/rafen-selfhosted.conf}"
NGINX_DEFAULT_SITE_PATH="${NGINX_DEFAULT_SITE_PATH:-/etc/nginx/sites-enabled/default}"
NGINX_DEFAULT_CONFD_PATH="${NGINX_DEFAULT_CONFD_PATH:-/etc/nginx/conf.d/default.conf}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-}"

info() {
    printf '[INFO] %s\n' "$1"
}

warn() {
    printf '[WARN] %s\n' "$1"
}

fail() {
    printf '[ERROR] %s\n' "$1" >&2
    exit 1
}

usage() {
    cat <<'EOF'
Usage:
  bash install-selfhosted.sh [install|deploy|status] [options]

Modes:
  install   Prepare .env, runtime directories, dependencies, migrate, and optional super admin
  deploy    Refresh dependencies and rerun runtime deployment steps
  status    Print current deployment summary

Options:
  --app-url <url>           Override APP_URL
  --domain <host>           Domain/host untuk APP_URL dan server_name Nginx
  --license-public-key <key>
                            Public key untuk verifikasi lisensi self-hosted
  --registry-url <url>      Endpoint API registrasi install-time ke SaaS
  --registry-token <token>  Bearer token untuk API registrasi install-time
  --admin-name <name>       Name for initial super admin (opsional jika installer interaktif)
  --admin-email <email>     Email for initial super admin (opsional jika installer interaktif)
  --admin-phone <phone>     Nomor WhatsApp admin awal untuk sinkronisasi notifikasi SaaS
  --admin-password <value>  Password for initial super admin (opsional jika installer interaktif)
  --db-connection <driver>  Database connection (sqlite or mysql)
  --db-host <host>          Database host for non-sqlite setup
  --db-port <port>          Database port for non-sqlite setup
  --db-name <name|path>     Database name or sqlite file path
  --db-user <user>          Database username for non-sqlite setup
  --db-password <value>     Database password for non-sqlite setup
  --skip-composer-install   Skip composer install
  --skip-npm-build          Skip npm install/build
  --skip-migrate            Skip php artisan migrate --force
  --skip-super-admin        Skip php artisan user:create-super-admin
  --skip-system-bootstrap   Skip provisioning package sistem dan konfigurasi Nginx/PHP-FPM
  --wireguard-system        Force enable OS-level WireGuard bootstrap (default: enabled)
  --skip-wireguard-system   Disable OS-level WireGuard bootstrap
  --skip-wireguard-package-install
                            Skip apt-get install for WireGuard packages during bootstrap
  --dry-run                 Print actions without executing commands
  --help                    Show this help

Env overrides:
  APP_DIR, EXPECTED_APP_DIR, ENV_FILE, DEPLOY_USER, DEPLOY_GROUP, DEPLOY_PASSWORD,
  APP_USER, APP_GROUP, SYSTEM_TIMEZONE, PHP_PREFERRED_VERSION, NODE_PREFERRED_MAJOR,
  PHP_BIN, COMPOSER_BIN, NPM_BIN, APT_GET_BIN, SYSTEMCTL_BIN, VISUDO_BIN,
  NGINX_BIN, NGINX_SERVICE, ALLOW_NON_ROOT, RUN_COMPOSER_INSTALL, RUN_NPM_BUILD, RUN_MIGRATE,
  RUN_SUPER_ADMIN_SETUP, RUN_SYSTEM_BOOTSTRAP, RUN_WIREGUARD_SYSTEM_BOOTSTRAP, LICENSE_PUBLIC_KEY_VALUE,
  SELF_HOSTED_REGISTRY_URL_VALUE, SELF_HOSTED_REGISTRY_TOKEN_VALUE, ADMIN_PHONE,
  RUN_WIREGUARD_PACKAGE_INSTALL, DB_CONNECTION, DB_HOST, DB_PORT,
  DB_DATABASE, DB_USERNAME, DB_PASSWORD, WG_SYSTEM_DIR, WG_SYSTEM_INTERFACE,
  WG_SYSTEM_SERVICE, DEPLOY_SUDOERS_PATH, WG_SUDOERS_PATH, WG_SYNC_HELPER_PATH, SERVER_HEALTH_SUDOERS_PATH, APP_DOMAIN,
  SYSTEM_PRIMARY_IP, NGINX_SITE_AVAILABLE_PATH, NGINX_SITE_ENABLED_PATH,
  NGINX_DEFAULT_SITE_PATH, NGINX_DEFAULT_CONFD_PATH, PHP_FPM_SERVICE, PHP_FPM_SOCK.
EOF
}

elevate_with_sudo() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$(id -u)" -eq 0 ]; then
        return
    fi

    command_exists sudo || fail "sudo tidak ditemukan. Jalankan script ini sebagai root atau install sudo terlebih dahulu."

    info "Hak akses root diperlukan untuk provisioning fresh server. Silakan masukkan password sudo."

    sudo -v || fail "Autentikasi sudo gagal."

    exec sudo --preserve-env=APP_DIR,EXPECTED_APP_DIR,ENV_FILE,ENV_EXAMPLE_FILE,DEPLOY_USER,DEPLOY_GROUP,DEPLOY_PASSWORD,APP_USER,APP_GROUP,SYSTEM_TIMEZONE,PHP_BIN,COMPOSER_BIN,NPM_BIN,APT_GET_BIN,SYSTEMCTL_BIN,VISUDO_BIN,ALLOW_NON_ROOT,DRY_RUN,RUN_COMPOSER_INSTALL,RUN_NPM_BUILD,RUN_MIGRATE,RUN_SUPER_ADMIN_SETUP,RUN_SYSTEM_BOOTSTRAP,RUN_WIREGUARD_SYSTEM_BOOTSTRAP,RUN_WIREGUARD_PACKAGE_INSTALL,APP_URL_OVERRIDE,APP_DOMAIN,LICENSE_PUBLIC_KEY_VALUE,SELF_HOSTED_REGISTRY_URL_VALUE,SELF_HOSTED_REGISTRY_TOKEN_VALUE,ADMIN_NAME,ADMIN_EMAIL,ADMIN_PHONE,ADMIN_PASSWORD,DB_CONNECTION,DB_HOST,DB_PORT,DB_DATABASE,DB_USERNAME,DB_PASSWORD,WG_SYSTEM_DIR,WG_SYSTEM_INTERFACE,WG_SYSTEM_SERVICE,DEPLOY_SUDOERS_PATH,WG_SUDOERS_PATH,WG_SYNC_HELPER_PATH,SERVER_HEALTH_SUDOERS_PATH,SYSTEM_PRIMARY_IP,NGINX_BIN,NGINX_SERVICE,NGINX_SITE_AVAILABLE_PATH,NGINX_SITE_ENABLED_PATH,NGINX_DEFAULT_SITE_PATH,NGINX_DEFAULT_CONFD_PATH,PHP_FPM_SERVICE,PHP_FPM_SOCK bash "$0" "$@"
}

parse_args() {
    if [ "$#" -gt 0 ]; then
        case "$1" in
            install|deploy|status)
                MODE="$1"
                shift
                ;;
            --help|-h)
                usage
                exit 0
                ;;
        esac
    fi

    while [ "$#" -gt 0 ]; do
        case "$1" in
            --app-url)
                APP_URL_OVERRIDE="$2"
                shift 2
                ;;
            --domain)
                APP_DOMAIN="$2"
                shift 2
                ;;
            --license-public-key)
                LICENSE_PUBLIC_KEY_VALUE="$2"
                shift 2
                ;;
            --registry-url)
                SELF_HOSTED_REGISTRY_URL_VALUE="$2"
                shift 2
                ;;
            --registry-token)
                SELF_HOSTED_REGISTRY_TOKEN_VALUE="$2"
                shift 2
                ;;
            --admin-name)
                ADMIN_NAME="$2"
                shift 2
                ;;
            --admin-email)
                ADMIN_EMAIL="$2"
                shift 2
                ;;
            --admin-phone)
                ADMIN_PHONE="$2"
                shift 2
                ;;
            --admin-password)
                ADMIN_PASSWORD="$2"
                shift 2
                ;;
            --db-connection)
                DB_CONNECTION="$2"
                shift 2
                ;;
            --db-host)
                DB_HOST="$2"
                shift 2
                ;;
            --db-port)
                DB_PORT="$2"
                shift 2
                ;;
            --db-name)
                DB_DATABASE="$2"
                shift 2
                ;;
            --db-user)
                DB_USERNAME="$2"
                shift 2
                ;;
            --db-password)
                DB_PASSWORD="$2"
                shift 2
                ;;
            --skip-composer-install)
                RUN_COMPOSER_INSTALL=0
                shift
                ;;
            --skip-npm-build)
                RUN_NPM_BUILD=0
                shift
                ;;
            --skip-migrate)
                RUN_MIGRATE=0
                shift
                ;;
            --skip-super-admin)
                RUN_SUPER_ADMIN_SETUP=0
                shift
                ;;
            --skip-system-bootstrap)
                RUN_SYSTEM_BOOTSTRAP=0
                shift
                ;;
            --wireguard-system)
                RUN_WIREGUARD_SYSTEM_BOOTSTRAP=1
                shift
                ;;
            --skip-wireguard-system)
                RUN_WIREGUARD_SYSTEM_BOOTSTRAP=0
                shift
                ;;
            --skip-wireguard-package-install)
                RUN_WIREGUARD_PACKAGE_INSTALL=0
                shift
                ;;
            --dry-run)
                DRY_RUN=1
                shift
                ;;
            --help|-h)
                usage
                exit 0
                ;;
            *)
                fail "Argumen tidak dikenal: $1"
                ;;
        esac
    done
}

require_root() {
    if [ "$ALLOW_NON_ROOT" = "1" ]; then
        return
    fi

    if [ "$(id -u)" -ne 0 ]; then
        fail "Script ini harus dijalankan sebagai root. Gunakan ALLOW_NON_ROOT=1 hanya untuk pengujian."
    fi
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

resolve_command_path() {
    local candidate="$1"

    case "$candidate" in
        /*)
            printf '%s' "$candidate"
            return 0
            ;;
    esac

    command -v "$candidate" 2>/dev/null || return 1
}

resolve_php_cli_bin() {
    if [ -n "$PHP_BIN_EXPLICIT" ] && [ -n "$PHP_BIN" ] && command_exists "$PHP_BIN"; then
        printf '%s' "$PHP_BIN"
        return
    fi

    if command_exists "php${PHP_PREFERRED_VERSION}"; then
        printf 'php%s' "$PHP_PREFERRED_VERSION"
        return
    fi

    printf 'php'
}

normalize_php_runtime() {
    PHP_BIN="$(resolve_php_cli_bin)"
}

apt_package_exists() {
    apt-cache show "$1" >/dev/null 2>&1
}

detect_node_major_version() {
    local node_bin
    local version

    node_bin="$(command -v node || true)"
    [ -n "$node_bin" ] || return 1

    version="$("$node_bin" -v 2>/dev/null || true)"
    version="${version#v}"
    printf '%s' "${version%%.*}"
}

read_os_release_value() {
    local key="$1"

    [ -f /etc/os-release ] || return 1

    awk -F= -v target="$key" '
        $1 == target {
            gsub(/^"/, "", $2)
            gsub(/"$/, "", $2)
            print $2
            exit
        }
    ' /etc/os-release
}

write_file_with_content() {
    local target_path="$1"
    local content="$2"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] write file %s\n' "$target_path"
        return 0
    fi

    printf '%s\n' "$content" >"$target_path"
}

ensure_php_apt_repository() {
    local os_id
    local version_codename
    local ubuntu_codename
    local sury_list_path="/etc/apt/sources.list.d/sury-php.list"
    local sury_keyring_path="/etc/apt/keyrings/sury-php.gpg"
    local sury_repo_line

    apt_package_exists "php${PHP_PREFERRED_VERSION}" && return

    os_id="$(read_os_release_value ID || true)"
    version_codename="$(read_os_release_value VERSION_CODENAME || true)"
    ubuntu_codename="$(read_os_release_value UBUNTU_CODENAME || true)"

    case "$os_id" in
        ubuntu)
            info "Repository default belum menyediakan php${PHP_PREFERRED_VERSION}, menambahkan ppa:ondrej/php."
            run_command "$APT_GET_BIN" install -y software-properties-common ca-certificates
            run_command add-apt-repository -y ppa:ondrej/php
            ;;
        debian)
            [ -n "$version_codename" ] || fail "VERSION_CODENAME tidak ditemukan di /etc/os-release, tidak bisa menambahkan repository PHP Debian."

            info "Repository default belum menyediakan php${PHP_PREFERRED_VERSION}, menambahkan packages.sury.org/php."
            run_command "$APT_GET_BIN" install -y ca-certificates curl gnupg2 lsb-release apt-transport-https
            install_dir /etc/apt/keyrings

            if [ "$DRY_RUN" = "1" ]; then
                printf '[DRY-RUN] download sury key to %s\n' "$sury_keyring_path"
            else
                curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o "$sury_keyring_path"
            fi

            sury_repo_line="deb [signed-by=${sury_keyring_path}] https://packages.sury.org/php/ ${version_codename} main"
            write_file_with_content "$sury_list_path" "$sury_repo_line"
            ;;
        *)
            if [ -n "$ubuntu_codename" ]; then
                info "OS terdeteksi mirip Ubuntu (${os_id}), mencoba menambahkan ppa:ondrej/php."
                run_command "$APT_GET_BIN" install -y software-properties-common ca-certificates
                run_command add-apt-repository -y ppa:ondrej/php
            else
                fail "Paket php${PHP_PREFERRED_VERSION} tidak tersedia dan distro ${os_id:-unknown} belum didukung untuk bootstrap repository otomatis."
            fi
            ;;
    esac

    run_command "$APT_GET_BIN" update

    apt_package_exists "php${PHP_PREFERRED_VERSION}" || fail "Repository PHP tambahan sudah dicoba, tetapi paket php${PHP_PREFERRED_VERSION} masih belum tersedia."
    apt_package_exists "php${PHP_PREFERRED_VERSION}-fpm" || fail "Repository PHP tambahan sudah dicoba, tetapi paket php${PHP_PREFERRED_VERSION}-fpm masih belum tersedia."
}

ensure_node_apt_repository() {
    local current_node_major
    local os_id

    current_node_major="$(detect_node_major_version || true)"

    if [ -n "$current_node_major" ] && [ "$current_node_major" -ge "$NODE_PREFERRED_MAJOR" ]; then
        return
    fi

    os_id="$(read_os_release_value ID || true)"

    case "$os_id" in
        ubuntu|debian)
            info "Menyiapkan Node.js ${NODE_PREFERRED_MAJOR}.x repository untuk build frontend."
            run_command "$APT_GET_BIN" install -y ca-certificates curl gnupg
            install_dir /etc/apt/keyrings

            if [ "$DRY_RUN" = "1" ]; then
                printf '[DRY-RUN] download NodeSource key to /etc/apt/keyrings/nodesource.gpg\n'
                printf '[DRY-RUN] write file /etc/apt/sources.list.d/nodesource.list\n'
            else
                curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
                printf 'deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_%s.x nodistro main\n' "$NODE_PREFERRED_MAJOR" >/etc/apt/sources.list.d/nodesource.list
            fi

            run_command "$APT_GET_BIN" update
            ;;
        *)
            fail "Node.js ${NODE_PREFERRED_MAJOR}.x belum tersedia dan distro ${os_id:-unknown} belum didukung untuk bootstrap repository Node otomatis."
            ;;
    esac
}

check_node_runtime_requirements() {
    local current_node_major

    if [ "$RUN_NPM_BUILD" != "1" ]; then
        return
    fi

    current_node_major="$(detect_node_major_version || true)"

    [ -n "$current_node_major" ] || fail "Node.js tidak ditemukan. Installer membutuhkan Node.js ${NODE_PREFERRED_MAJOR}.x untuk build frontend."
    [ "$current_node_major" -ge "$NODE_PREFERRED_MAJOR" ] || fail "Node.js $(node -v 2>/dev/null || printf unknown) terlalu lama. Installer membutuhkan Node.js ${NODE_PREFERRED_MAJOR}.x atau lebih baru untuk build frontend."
}

run_command() {
    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] %s\n' "$*"
        return 0
    fi

    "$@"
}

run_in_app() {
    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] (cd %s && %s)\n' "$APP_DIR" "$*"
        return 0
    fi

    (
        cd "$APP_DIR"
        "$@"
    )
}

shell_quote() {
    printf '%q' "$1"
}

installer_exec_user() {
    if [ "$(id -u)" -eq 0 ] && [ "$ALLOW_NON_ROOT" != "1" ]; then
        printf '%s' "$DEPLOY_USER"
        return
    fi

    id -un
}

run_in_app_as_installer_user() {
    local install_user
    local install_group
    local command_string

    install_user="$(installer_exec_user)"
    install_group="$DEPLOY_GROUP"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] (cd %s && as %s:%s => %s)\n' "$APP_DIR" "$install_user" "$install_group" "$*"
        return 0
    fi

    if [ "$install_user" = "$(id -un)" ]; then
        run_in_app "$@"
        return
    fi

    printf -v command_string '%q ' "$@"
    command_string="${command_string% }"

    run_command runuser -u "$install_user" -g "$install_group" -- /bin/bash -lc "cd $(shell_quote "$APP_DIR") && $command_string"
}

ensure_expected_app_dir() {
    if [ "$APP_DIR" != "$EXPECTED_APP_DIR" ]; then
        warn "APP_DIR saat ini adalah $APP_DIR, bukan $EXPECTED_APP_DIR."
    fi
}

ensure_app_layout() {
    [ -d "$APP_DIR" ] || fail "APP_DIR tidak ditemukan: $APP_DIR"
    [ -f "$APP_DIR/artisan" ] || fail "File artisan tidak ditemukan di $APP_DIR"
    [ -f "$APP_DIR/composer.json" ] || fail "File composer.json tidak ditemukan di $APP_DIR"
    [ -f "$ENV_EXAMPLE_FILE" ] || fail "File .env.example tidak ditemukan di $ENV_EXAMPLE_FILE"
}

install_dir() {
    local path="$1"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] mkdir -p %s\n' "$path"
        return 0
    fi

    mkdir -p "$path"
}

group_exists() {
    getent group "$1" >/dev/null 2>&1
}

user_exists() {
    id "$1" >/dev/null 2>&1
}

prompt_deploy_password() {
    local password
    local password_confirm

    if [ -n "$DEPLOY_PASSWORD" ]; then
        return
    fi

    [ -t 0 ] || fail "DEPLOY_PASSWORD belum diisi dan installer tidak berjalan interaktif. Set env DEPLOY_PASSWORD untuk membuat user $DEPLOY_USER."

    while true; do
        printf 'Masukkan password untuk user %s: ' "$DEPLOY_USER" >&2
        read -r -s password
        printf '\n' >&2
        printf 'Konfirmasi password user %s: ' "$DEPLOY_USER" >&2
        read -r -s password_confirm
        printf '\n' >&2

        [ -n "$password" ] || {
            warn "Password tidak boleh kosong."
            continue
        }

        if [ "$password" != "$password_confirm" ]; then
            warn "Konfirmasi password tidak cocok. Coba lagi."
            continue
        fi

        DEPLOY_PASSWORD="$password"
        return
    done
}

is_interactive_install() {
    [ -t 0 ] && [ -t 1 ]
}

prompt_text_if_missing() {
    local current_value="$1"
    local label="$2"
    local result=""

    if [ -n "$current_value" ]; then
        printf '%s' "$current_value"
        return
    fi

    if ! is_interactive_install; then
        printf '%s' "$current_value"
        return
    fi

    while true; do
        printf '%s: ' "$label" >&2
        read -r result

        if [ -n "$result" ]; then
            printf '%s' "$result"
            return
        fi

        warn "$label tidak boleh kosong."
    done
}

prompt_optional_text_if_missing() {
    local current_value="$1"
    local label="$2"
    local result=""

    if [ -n "$current_value" ]; then
        printf '%s' "$current_value"
        return
    fi

    if ! is_interactive_install; then
        printf '%s' "$current_value"
        return
    fi

    printf '%s (opsional, kosongkan jika belum ada): ' "$label" >&2
    read -r result
    printf '%s' "$result"
}

preview_secret_value() {
    local value="$1"
    local length="${#value}"

    if [ "$length" -eq 0 ]; then
        printf 'missing'
        return
    fi

    if [ "$length" -le 8 ]; then
        printf 'set (%s)' "$value"
        return
    fi

    printf 'set (%s...%s)' "${value:0:4}" "${value:length-4:4}"
}

registry_sync_is_enabled() {
    if [ -n "$SELF_HOSTED_REGISTRY_URL_VALUE" ] && [ -n "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" ]; then
        return 0
    fi

    if [ -n "$(read_env SELF_HOSTED_REGISTRY_URL)" ] && [ -n "$(read_env SELF_HOSTED_REGISTRY_TOKEN)" ]; then
        return 0
    fi

    return 1
}

prompt_yes_no() {
    local label="$1"
    local default_answer="${2:-n}"
    local answer=""

    if ! is_interactive_install; then
        [ "$default_answer" = "y" ]
        return
    fi

    while true; do
        if [ "$default_answer" = "y" ]; then
            printf '%s [Y/n]: ' "$label" >&2
        else
            printf '%s [y/N]: ' "$label" >&2
        fi

        read -r answer
        answer="$(printf '%s' "$answer" | tr '[:upper:]' '[:lower:]')"

        case "$answer" in
            '')
                [ "$default_answer" = "y" ]
                return
                ;;
            y|yes)
                return 0
                ;;
            n|no)
                return 1
                ;;
        esac

        warn "Jawaban tidak dikenali. Masukkan y atau n."
    done
}

prompt_license_public_key_if_needed() {
    local existing_public_key=""

    if [ -n "$LICENSE_PUBLIC_KEY_VALUE" ]; then
        return
    fi

    existing_public_key="$(read_env LICENSE_PUBLIC_KEY)"

    if [ -n "$existing_public_key" ]; then
        LICENSE_PUBLIC_KEY_VALUE="$existing_public_key"
        return
    fi

    if ! is_interactive_install; then
        return
    fi

    LICENSE_PUBLIC_KEY_VALUE="$(prompt_text_if_missing "$LICENSE_PUBLIC_KEY_VALUE" "Paste LICENSE_PUBLIC_KEY dari server SaaS")"
}

prompt_registry_configuration_if_needed() {
    local existing_registry_url=""
    local existing_registry_token=""

    if [ -z "$SELF_HOSTED_REGISTRY_URL_VALUE" ]; then
        existing_registry_url="$(read_env SELF_HOSTED_REGISTRY_URL)"
        if [ -n "$existing_registry_url" ]; then
            SELF_HOSTED_REGISTRY_URL_VALUE="$existing_registry_url"
        fi
    fi

    if [ -z "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" ]; then
        existing_registry_token="$(read_env SELF_HOSTED_REGISTRY_TOKEN)"
        if [ -n "$existing_registry_token" ]; then
            SELF_HOSTED_REGISTRY_TOKEN_VALUE="$existing_registry_token"
        fi
    fi

    if [ -n "$SELF_HOSTED_REGISTRY_URL_VALUE" ] || [ -n "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" ]; then
        if [ -z "$SELF_HOSTED_REGISTRY_URL_VALUE" ]; then
            SELF_HOSTED_REGISTRY_URL_VALUE="$(prompt_text_if_missing "$SELF_HOSTED_REGISTRY_URL_VALUE" "URL endpoint registrasi self-hosted ke SaaS")"
        fi

        if [ -z "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" ]; then
            SELF_HOSTED_REGISTRY_TOKEN_VALUE="$(prompt_text_if_missing "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" "Token registrasi self-hosted dari SaaS")"
        fi

        return
    fi

    if ! prompt_yes_no "Aktifkan sinkronisasi install-time ke SaaS?" "n"; then
        return
    fi

    SELF_HOSTED_REGISTRY_URL_VALUE="$(prompt_text_if_missing "$SELF_HOSTED_REGISTRY_URL_VALUE" "URL endpoint registrasi self-hosted ke SaaS")"
    SELF_HOSTED_REGISTRY_TOKEN_VALUE="$(prompt_text_if_missing "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" "Token registrasi self-hosted dari SaaS")"
}

prompt_admin_password_if_missing() {
    local password
    local password_confirm

    if [ -n "$ADMIN_PASSWORD" ]; then
        return
    fi

    if ! is_interactive_install; then
        return
    fi

    while true; do
        printf 'Masukkan password super admin awal: ' >&2
        read -r -s password
        printf '\n' >&2
        printf 'Konfirmasi password super admin awal: ' >&2
        read -r -s password_confirm
        printf '\n' >&2

        [ -n "$password" ] || {
            warn "Password super admin tidak boleh kosong."
            continue
        }

        if [ "$password" != "$password_confirm" ]; then
            warn "Konfirmasi password super admin tidak cocok. Coba lagi."
            continue
        fi

        ADMIN_PASSWORD="$password"
        return
    done
}

prompt_admin_inputs_if_needed() {
    local registry_url
    local registry_token
    local registry_enabled=0

    registry_url="${SELF_HOSTED_REGISTRY_URL_VALUE:-$(read_env SELF_HOSTED_REGISTRY_URL)}"
    registry_token="${SELF_HOSTED_REGISTRY_TOKEN_VALUE:-$(read_env SELF_HOSTED_REGISTRY_TOKEN)}"

    if [ -n "$registry_url" ] && [ -n "$registry_token" ]; then
        registry_enabled=1
    fi

    if [ "$RUN_SUPER_ADMIN_SETUP" = "1" ]; then
        ADMIN_NAME="$(prompt_text_if_missing "$ADMIN_NAME" "Nama super admin awal")"
        ADMIN_EMAIL="$(prompt_text_if_missing "$ADMIN_EMAIL" "Email super admin awal")"
        prompt_admin_password_if_missing

        if [ "$registry_enabled" = "1" ]; then
            ADMIN_PHONE="$(prompt_text_if_missing "$ADMIN_PHONE" "Nomor WhatsApp super admin awal")"
        else
            ADMIN_PHONE="$(prompt_optional_text_if_missing "$ADMIN_PHONE" "Nomor WhatsApp super admin awal")"
        fi
    fi

    if [ "$registry_enabled" = "1" ]; then
        if [ -z "$ADMIN_NAME" ]; then
            ADMIN_NAME="$(prompt_text_if_missing "$ADMIN_NAME" "Nama admin untuk sinkronisasi SaaS")"
        fi

        if [ -z "$ADMIN_EMAIL" ]; then
            ADMIN_EMAIL="$(prompt_text_if_missing "$ADMIN_EMAIL" "Email admin untuk sinkronisasi SaaS")"
        fi

        if [ -z "$ADMIN_PHONE" ]; then
            ADMIN_PHONE="$(prompt_text_if_missing "$ADMIN_PHONE" "Nomor WhatsApp admin untuk sinkronisasi SaaS")"
        fi
    fi
}

prompt_install_configuration_if_needed() {
    if [ "$MODE" != "install" ]; then
        return
    fi

    if ! is_interactive_install; then
        return
    fi

    prompt_license_public_key_if_needed
    prompt_registry_configuration_if_needed
}

confirm_install_configuration_if_needed() {
    local registry_enabled="Tidak"
    local registry_url="-"
    local admin_name_display="-"
    local admin_email_display="-"
    local admin_phone_display="-"

    if ! is_interactive_install; then
        return
    fi

    if registry_sync_is_enabled; then
        registry_enabled="Ya"
        registry_url="${SELF_HOSTED_REGISTRY_URL_VALUE:-$(read_env SELF_HOSTED_REGISTRY_URL)}"
    fi

    if [ -n "$ADMIN_NAME" ]; then
        admin_name_display="$ADMIN_NAME"
    fi

    if [ -n "$ADMIN_EMAIL" ]; then
        admin_email_display="$ADMIN_EMAIL"
    fi

    if [ -n "$ADMIN_PHONE" ]; then
        admin_phone_display="$ADMIN_PHONE"
    fi

    printf '\n==== Konfirmasi konfigurasi instalasi self-hosted ====\n' >&2
    printf 'APP_URL                  : %s\n' "$(read_env APP_URL)" >&2
    printf 'LICENSE_PUBLIC_KEY       : %s\n' "$(preview_secret_value "${LICENSE_PUBLIC_KEY_VALUE:-$(read_env LICENSE_PUBLIC_KEY)}")" >&2
    printf 'Sinkronisasi ke SaaS     : %s\n' "$registry_enabled" >&2
    printf 'SELF_HOSTED_REGISTRY_URL : %s\n' "$registry_url" >&2
    printf 'SELF_HOSTED_REGISTRY_TOKEN: %s\n' "$(preview_secret_value "${SELF_HOSTED_REGISTRY_TOKEN_VALUE:-$(read_env SELF_HOSTED_REGISTRY_TOKEN)}")" >&2
    printf 'Nama Super Admin         : %s\n' "$admin_name_display" >&2
    printf 'Email Super Admin        : %s\n' "$admin_email_display" >&2
    printf 'Nomor WA Super Admin     : %s\n' "$admin_phone_display" >&2
    printf 'Password Super Admin     : %s\n' "$([ -n "$ADMIN_PASSWORD" ] && printf 'set' || printf 'missing')" >&2
    printf '======================================================\n' >&2

    if ! prompt_yes_no "Lanjutkan instalasi dengan konfigurasi di atas?" "y"; then
        fail "Instalasi dibatalkan agar konfigurasi bisa diperiksa ulang."
    fi
}

ensure_deploy_user() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$(id -u)" -ne 0 ]; then
        return
    fi

    if ! group_exists "$DEPLOY_GROUP"; then
        info "Membuat group deploy: $DEPLOY_GROUP"
        run_command groupadd "$DEPLOY_GROUP"
    fi

    if user_exists "$DEPLOY_USER"; then
        return
    fi

    prompt_deploy_password
    info "Membuat user deploy: $DEPLOY_USER"
    run_command useradd -m -s /bin/bash -g "$DEPLOY_GROUP" "$DEPLOY_USER"
    printf '%s:%s\n' "$DEPLOY_USER" "$DEPLOY_PASSWORD" | run_command chpasswd
}

ensure_deploy_group_membership() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$(id -u)" -ne 0 ]; then
        return
    fi

    group_exists "$APP_GROUP" || return

    if id -nG "$DEPLOY_USER" | tr ' ' '\n' | grep -Fx "$APP_GROUP" >/dev/null 2>&1; then
        return
    fi

    info "Menambahkan $DEPLOY_USER ke group $APP_GROUP agar proses deploy dan service web bisa berbagi akses."
    run_command usermod -a -G "$APP_GROUP" "$DEPLOY_USER"
}

write_deploy_sudoers() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$(id -u)" -ne 0 ]; then
        return
    fi

    user_exists "$DEPLOY_USER" || return

    local sudoers_content
    sudoers_content="$DEPLOY_USER ALL=(root) ALL"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] write sudoers %s => %s\n' "$DEPLOY_SUDOERS_PATH" "$sudoers_content"
        return 0
    fi

    printf '%s\n' "$sudoers_content" >"$DEPLOY_SUDOERS_PATH"
    chmod 0440 "$DEPLOY_SUDOERS_PATH"

    if command_exists "$VISUDO_BIN"; then
        run_command "$VISUDO_BIN" -cf "$DEPLOY_SUDOERS_PATH"
    fi
}

prepare_app_for_deploy_user() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$(id -u)" -ne 0 ]; then
        return
    fi

    if command_exists chown; then
        run_command chown -R "$DEPLOY_USER:$DEPLOY_GROUP" "$APP_DIR"
    fi

    if command_exists chmod; then
        run_command chmod -R u+rwX,g+rX "$APP_DIR"
    fi
}

ensure_runtime_directories() {
    local directories=(
        "$APP_DIR/bootstrap/cache"
        "$APP_DIR/database"
        "$APP_DIR/scripts"
        "$APP_DIR/storage/.pm2"
        "$APP_DIR/storage/app/license"
        "$APP_DIR/storage/app/radius"
        "$APP_DIR/storage/app/wireguard"
        "$APP_DIR/storage/framework/cache/data"
        "$APP_DIR/storage/framework/sessions"
        "$APP_DIR/storage/framework/views"
        "$APP_DIR/storage/logs"
        "$APP_DIR/tests/Unit"
        "$APP_DIR/wa-multi-session"
    )

    for directory in "${directories[@]}"; do
        install_dir "$directory"
    done
}

copy_env_file_if_missing() {
    if [ -f "$ENV_FILE" ]; then
        return
    fi

    info "Menyalin .env dari template."
    run_command cp "$ENV_EXAMPLE_FILE" "$ENV_FILE"
}

read_env() {
    local key="$1"
    local value=""

    if [ -f "$ENV_FILE" ]; then
        value="$(grep -E "^${key}=" "$ENV_FILE" | tail -n1 | cut -d= -f2- || true)"
    fi

    value="${value%\"}"
    value="${value#\"}"
    printf '%s' "$value"
}

set_env() {
    local key="$1"
    local value="$2"
    local escaped
    local formatted
    local tmp_file

    escaped="$(printf '%s' "$value" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g')"

    if printf '%s' "$value" | grep -q '[[:space:]]'; then
        formatted="\"${escaped}\""
    else
        formatted="${escaped}"
    fi

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] set %s=%s in %s\n' "$key" "$formatted" "$ENV_FILE"
        return 0
    fi

    tmp_file="$(mktemp)"

    if [ -f "$ENV_FILE" ]; then
        awk -v key="$key" -v val="$formatted" '
            BEGIN { found=0 }
            $0 ~ "^" key "=" {
                print key "=" val
                found=1
                next
            }
            { print }
            END {
                if (!found) {
                    print key "=" val
                }
            }
        ' "$ENV_FILE" >"$tmp_file"
    else
        printf '%s=%s\n' "$key" "$formatted" >"$tmp_file"
    fi

    mv "$tmp_file" "$ENV_FILE"
}

normalize_sqlite_path() {
    if [ "$DB_CONNECTION" != "sqlite" ]; then
        return
    fi

    case "$DB_DATABASE" in
        /*) ;;
        *)
            DB_DATABASE="$APP_DIR/$DB_DATABASE"
            ;;
    esac
}

normalize_host() {
    local value="$1"

    value="${value#http://}"
    value="${value#https://}"
    value="${value%%/*}"
    value="${value%%:*}"

    printf '%s' "$value"
}

detect_primary_ip() {
    local detected_ip=""

    if [ -n "$SYSTEM_PRIMARY_IP" ]; then
        printf '%s' "$SYSTEM_PRIMARY_IP"
        return
    fi

    if command_exists hostname; then
        detected_ip="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
    fi

    if [ -z "$detected_ip" ] && command_exists ip; then
        detected_ip="$(ip route get 1.1.1.1 2>/dev/null | awk '/src/ {for (i = 1; i <= NF; i++) if ($i == "src") { print $(i + 1); exit }}' || true)"
    fi

    if [ -z "$detected_ip" ]; then
        detected_ip="127.0.0.1"
    fi

    printf '%s' "$detected_ip"
}

resolve_public_host() {
    local current_app_url
    local current_host

    if [ -n "$APP_URL_OVERRIDE" ]; then
        normalize_host "$APP_URL_OVERRIDE"
        return
    fi

    if [ -n "$APP_DOMAIN" ]; then
        normalize_host "$APP_DOMAIN"
        return
    fi

    current_app_url="$(read_env APP_URL)"
    current_host="$(normalize_host "$current_app_url")"

    if [ -n "$current_host" ] && [ "$current_host" != "localhost" ] && [ "$current_host" != "127.0.0.1" ]; then
        printf '%s' "$current_host"
        return
    fi

    detect_primary_ip
}

resolve_app_url() {
    local host

    if [ -n "$APP_URL_OVERRIDE" ]; then
        printf '%s' "$APP_URL_OVERRIDE"
        return
    fi

    host="$(resolve_public_host)"

    printf 'http://%s' "$host"
}

resolve_access_mode() {
    if [ -n "$APP_DOMAIN" ]; then
        printf 'domain-based'
        return
    fi

    if [ -n "$APP_URL_OVERRIDE" ]; then
        case "$(normalize_host "$APP_URL_OVERRIDE")" in
            ''|localhost|127.0.0.1)
                printf 'ip-based'
                ;;
            *)
                printf 'custom-url'
                ;;
        esac
        return
    fi

    printf 'ip-based'
}

configure_environment() {
    normalize_sqlite_path
    local resolved_app_url
    local resolved_public_host

    resolved_app_url="$(resolve_app_url)"
    resolved_public_host="$(resolve_public_host)"

    set_env APP_URL "$resolved_app_url"

    set_env APP_NAME "Rafen Self-Hosted"
    set_env APP_ENV "production"
    set_env APP_DEBUG "false"
    set_env DB_CONNECTION "$DB_CONNECTION"
    set_env SESSION_DRIVER "file"
    set_env QUEUE_CONNECTION "sync"
    set_env CACHE_STORE "file"
    set_env LICENSE_SELF_HOSTED_ENABLED "true"
    set_env LICENSE_ENFORCE "true"
    if [ -n "$LICENSE_PUBLIC_KEY_VALUE" ]; then
        set_env LICENSE_PUBLIC_KEY "$LICENSE_PUBLIC_KEY_VALUE"
    fi
    set_env LICENSE_FILE_PATH "$APP_DIR/storage/app/license/rafen.lic"
    set_env LICENSE_MACHINE_ID_PATH "/etc/machine-id"
    set_env LICENSE_DEFAULT_GRACE_DAYS "21"
    if [ -n "$SELF_HOSTED_REGISTRY_URL_VALUE" ]; then
        set_env SELF_HOSTED_REGISTRY_URL "$SELF_HOSTED_REGISTRY_URL_VALUE"
    fi
    if [ -n "$SELF_HOSTED_REGISTRY_TOKEN_VALUE" ]; then
        set_env SELF_HOSTED_REGISTRY_TOKEN "$SELF_HOSTED_REGISTRY_TOKEN_VALUE"
    fi
    set_env GENIEACS_NBI_URL "http://127.0.0.1:7557"
    set_env GENIEACS_UI_URL "http://127.0.0.1:3000"
    set_env GENIEACS_NBI_TIMEOUT "10"
    set_env GENIEACS_LOG_PATH "$APP_DIR/storage/logs/genieacs.log"
    set_env GENIEACS_CWMP_STATUS_COMMAND "systemctl is-active genieacs-cwmp"
    set_env GENIEACS_CWMP_RESTART_COMMAND "systemctl restart genieacs-cwmp"
    set_env GENIEACS_NBI_STATUS_COMMAND "systemctl is-active genieacs-nbi"
    set_env GENIEACS_NBI_RESTART_COMMAND "systemctl restart genieacs-nbi"
    set_env GENIEACS_FS_STATUS_COMMAND "systemctl is-active genieacs-fs"
    set_env GENIEACS_FS_RESTART_COMMAND "systemctl restart genieacs-fs"
    set_env WG_HOST "$resolved_public_host"
    set_env WG_SERVER_IP "10.0.0.1"
    set_env WG_SERVER_ADDRESS "10.0.0.1/24"
    set_env WG_LISTEN_PORT "51820"
    set_env WG_INTERFACE "wg0"
    set_env WG_CONFIG_PATH "$APP_DIR/storage/app/wireguard/wg0.conf"
    set_env WG_SERVER_PRIVATE_KEY_PATH "$APP_DIR/storage/app/wireguard/server_private.key"
    set_env WG_SERVER_PUBLIC_KEY_PATH "$APP_DIR/storage/app/wireguard/server_public.key"
    set_env WG_POOL_START "10.0.0.2"
    set_env WG_POOL_END "10.0.0.254"
    set_env WA_MULTI_SESSION_PATH "$APP_DIR/wa-multi-session"
    set_env WA_MULTI_SESSION_HOST "127.0.0.1"
    set_env WA_MULTI_SESSION_PORT "3100"
    set_env WA_MULTI_SESSION_PM2_HOME "$APP_DIR/storage/.pm2"
    set_env WA_MULTI_SESSION_LOG_FILE "$APP_DIR/storage/logs/wa-multi-session-pm2.log"
    set_env RADIUS_CLIENTS_PATH "$APP_DIR/storage/app/radius/clients-selfhosted.conf"
    set_env RADIUS_LOG_PATH "$APP_DIR/storage/logs/freeradius.log"

    if [ "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP" = "1" ]; then
        if [ "$ALLOW_NON_ROOT" = "1" ]; then
            set_env WG_APPLY_COMMAND "$WG_SYNC_HELPER_PATH"
        else
            set_env WG_APPLY_COMMAND "sudo $WG_SYNC_HELPER_PATH"
        fi
    fi

    if [ "$DB_CONNECTION" = "sqlite" ]; then
        set_env DB_DATABASE "$DB_DATABASE"
    else
        set_env DB_HOST "$DB_HOST"
        set_env DB_PORT "$DB_PORT"
        set_env DB_DATABASE "$DB_DATABASE"
        set_env DB_USERNAME "$DB_USERNAME"
        set_env DB_PASSWORD "$DB_PASSWORD"
    fi
}

require_license_public_key() {
    local public_key

    if [ -n "$LICENSE_PUBLIC_KEY_VALUE" ]; then
        return
    fi

    public_key="$(read_env LICENSE_PUBLIC_KEY)"

    if [ -n "$public_key" ]; then
        return
    fi

    fail "LICENSE_PUBLIC_KEY belum diisi. Jalankan installer dengan --license-public-key atau set env LICENSE_PUBLIC_KEY_VALUE agar verifikasi lisensi self-hosted bisa berjalan."
}

install_system_packages() {
    if [ "$RUN_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    command_exists "$APT_GET_BIN" || fail "apt-get tidak ditemukan: $APT_GET_BIN"
    command_exists apt-cache || fail "apt-cache tidak ditemukan."
    command_exists add-apt-repository || true

    run_command "$APT_GET_BIN" update

    ensure_php_apt_repository
    ensure_node_apt_repository

    run_command "$APT_GET_BIN" install -y \
        nginx \
        git \
        unzip \
        curl \
        sudo \
        composer \
        nodejs \
        "php${PHP_PREFERRED_VERSION}" \
        "php${PHP_PREFERRED_VERSION}-cli" \
        "php${PHP_PREFERRED_VERSION}-fpm" \
        "php${PHP_PREFERRED_VERSION}-sqlite3" \
        "php${PHP_PREFERRED_VERSION}-mysql" \
        "php${PHP_PREFERRED_VERSION}-curl" \
        "php${PHP_PREFERRED_VERSION}-mbstring" \
        "php${PHP_PREFERRED_VERSION}-xml" \
        "php${PHP_PREFERRED_VERSION}-zip" \
        "php${PHP_PREFERRED_VERSION}-bcmath" \
        "php${PHP_PREFERRED_VERSION}-intl" \
        "php${PHP_PREFERRED_VERSION}-gd"
}

detect_php_fpm_service() {
    if [ -n "$PHP_FPM_SERVICE" ]; then
        printf '%s' "$PHP_FPM_SERVICE"
        return
    fi

    if [ -d "/etc/php/${PHP_PREFERRED_VERSION}/fpm" ]; then
        printf 'php%s-fpm' "$PHP_PREFERRED_VERSION"
        return
    fi

    local latest_dir
    latest_dir="$(find /etc/php -maxdepth 2 -type d -name fpm 2>/dev/null | sort -V | tail -1 || true)"

    if [ -n "$latest_dir" ]; then
        basename "$(dirname "$latest_dir")" | awk '{printf "php%s-fpm", $0}'
        return
    fi

    printf 'php-fpm'
}

detect_php_fpm_socket() {
    if [ -n "$PHP_FPM_SOCK" ]; then
        printf '%s' "$PHP_FPM_SOCK"
        return
    fi

    if [ -S "/run/php/php${PHP_PREFERRED_VERSION}-fpm.sock" ]; then
        printf '/run/php/php%s-fpm.sock' "$PHP_PREFERRED_VERSION"
        return
    fi

    local latest_sock
    latest_sock="$(find /run/php -maxdepth 1 -type s -name 'php*-fpm.sock' 2>/dev/null | sort -V | tail -1 || true)"

    if [ -n "$latest_sock" ]; then
        printf '%s' "$latest_sock"
        return
    fi

    printf '/run/php/php-fpm.sock'
}

write_nginx_site_config() {
    if [ "$RUN_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    local host
    local php_fpm_socket
    local site_dir
    local enabled_dir

    host="$(resolve_public_host)"
    php_fpm_socket="$(detect_php_fpm_socket)"
    site_dir="$(dirname "$NGINX_SITE_AVAILABLE_PATH")"
    enabled_dir="$(dirname "$NGINX_SITE_ENABLED_PATH")"

    install_dir "$site_dir"
    install_dir "$enabled_dir"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] write nginx site %s for host %s\n' "$NGINX_SITE_AVAILABLE_PATH" "$host"
        return 0
    fi

    cat >"$NGINX_SITE_AVAILABLE_PATH" <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${host} _;
    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 32m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${php_fpm_socket};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
}

enable_nginx_site() {
    if [ "$RUN_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] enable nginx site %s -> %s\n' "$NGINX_SITE_AVAILABLE_PATH" "$NGINX_SITE_ENABLED_PATH"
        return 0
    fi

    ln -sfn "$NGINX_SITE_AVAILABLE_PATH" "$NGINX_SITE_ENABLED_PATH"

    if [ -n "$NGINX_DEFAULT_SITE_PATH" ] && [ -e "$NGINX_DEFAULT_SITE_PATH" ]; then
        rm -f "$NGINX_DEFAULT_SITE_PATH"
    fi

    if [ -n "$NGINX_DEFAULT_CONFD_PATH" ] && [ -e "$NGINX_DEFAULT_CONFD_PATH" ]; then
        rm -f "$NGINX_DEFAULT_CONFD_PATH"
    fi
}

restart_web_services() {
    if [ "$RUN_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    local php_fpm_service

    php_fpm_service="$(detect_php_fpm_service)"

    if command_exists "$NGINX_BIN"; then
        run_command "$NGINX_BIN" -t
    fi

    run_command "$SYSTEMCTL_BIN" enable --now "$php_fpm_service"
    run_command "$SYSTEMCTL_BIN" restart "$php_fpm_service"
    run_command "$SYSTEMCTL_BIN" enable --now "$NGINX_SERVICE"
    run_command "$SYSTEMCTL_BIN" restart "$NGINX_SERVICE"
}

prepare_sqlite_database() {
    if [ "$DB_CONNECTION" != "sqlite" ]; then
        return
    fi

    install_dir "$(dirname "$DB_DATABASE")"

    if [ ! -f "$DB_DATABASE" ]; then
        info "Membuat file database sqlite: $DB_DATABASE"
        run_command touch "$DB_DATABASE"
    fi
}

apply_basic_permissions() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$DRY_RUN" = "1" ]; then
        return
    fi

    if command_exists chown; then
        run_command chown -R "$APP_USER:$APP_GROUP" \
            "$APP_DIR/storage" \
            "$APP_DIR/bootstrap/cache" \
            "$APP_DIR/database" \
            "$APP_DIR/scripts" \
            "$APP_DIR/wa-multi-session"

        if [ -f "$ENV_FILE" ]; then
            run_command chown "$APP_USER:$APP_GROUP" "$ENV_FILE"
        fi

        if [ -L "$APP_DIR/public/storage" ]; then
            run_command chown -h "$APP_USER:$APP_GROUP" "$APP_DIR/public/storage"
        fi
    fi

    if command_exists chmod; then
        run_command chmod -R ug+rwX \
            "$APP_DIR/storage" \
            "$APP_DIR/bootstrap/cache" \
            "$APP_DIR/database" \
            "$APP_DIR/scripts" \
            "$APP_DIR/wa-multi-session"

        if [ -f "$ENV_FILE" ]; then
            run_command chmod 640 "$ENV_FILE"
        fi
    fi
}

configure_timezone() {
    if [ "$ALLOW_NON_ROOT" = "1" ] || [ "$DRY_RUN" = "1" ]; then
        return
    fi

    if command_exists timedatectl; then
        run_command timedatectl set-timezone "$SYSTEM_TIMEZONE"
    fi
}

install_wireguard_packages() {
    if [ "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP" != "1" ] || [ "$RUN_WIREGUARD_PACKAGE_INSTALL" != "1" ]; then
        return
    fi

    command_exists "$APT_GET_BIN" || fail "apt-get tidak ditemukan: $APT_GET_BIN"
    run_command "$APT_GET_BIN" update
    run_command "$APT_GET_BIN" install -y wireguard-tools
}

ensure_wireguard_server_keypair() {
    if [ "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    local private_key_path
    local public_key_path
    local private_group
    private_key_path="${WG_SYSTEM_DIR}/server_private.key"
    public_key_path="${WG_SYSTEM_DIR}/server_public.key"
    private_group="root"

    if group_exists "$APP_GROUP"; then
        private_group="$APP_GROUP"
    fi

    if [ -s "$private_key_path" ] && [ -s "$public_key_path" ]; then
        return
    fi

    command_exists wg || fail "Binary wg tidak ditemukan. Pastikan wireguard-tools terinstall sebelum bootstrap keypair."

    info "Menyiapkan keypair server WireGuard di $WG_SYSTEM_DIR."

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] generate %s dan %s\n' "$private_key_path" "$public_key_path"
        return 0
    fi

    install -d -m 0755 "$WG_SYSTEM_DIR"

    if [ ! -s "$private_key_path" ]; then
        (
            umask 077
            wg genkey >"$private_key_path"
        )
    fi

    if [ ! -s "$public_key_path" ]; then
        wg pubkey <"$private_key_path" >"$public_key_path"
    fi

    chown root:"$private_group" "$private_key_path"
    chmod 0640 "$private_key_path"
    chown root:root "$public_key_path"
    chmod 0644 "$public_key_path"
}

write_wireguard_sync_helper() {
    if [ "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    local helper_dir
    helper_dir="$(dirname "$WG_SYNC_HELPER_PATH")"

    install_dir "$helper_dir"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] write helper %s\n' "$WG_SYNC_HELPER_PATH"
        return 0
    fi

    cat >"$WG_SYNC_HELPER_PATH" <<EOF
#!/usr/bin/env bash
set -Eeuo pipefail

APP_CONFIG_PATH="${APP_DIR}/storage/app/wireguard/${WG_SYSTEM_INTERFACE}.conf"
SYSTEM_CONFIG_PATH="${WG_SYSTEM_DIR}/${WG_SYSTEM_INTERFACE}.conf"
SYSTEM_SERVICE="${WG_SYSTEM_SERVICE}"

mkdir -p "${WG_SYSTEM_DIR}"
install -m 600 "\$APP_CONFIG_PATH" "\$SYSTEM_CONFIG_PATH"
"${SYSTEMCTL_BIN}" daemon-reload
"${SYSTEMCTL_BIN}" enable --now "\$SYSTEM_SERVICE"
"${SYSTEMCTL_BIN}" restart "\$SYSTEM_SERVICE"
EOF

    chmod 0755 "$WG_SYNC_HELPER_PATH"
}

write_wireguard_sudoers() {
    if [ "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP" != "1" ] || [ "$ALLOW_NON_ROOT" = "1" ]; then
        return
    fi

    local sudoers_content
    sudoers_content="$APP_USER ALL=(root) NOPASSWD: $WG_SYNC_HELPER_PATH"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] write sudoers %s => %s\n' "$WG_SUDOERS_PATH" "$sudoers_content"
        return 0
    fi

    printf '%s\n' "$sudoers_content" >"$WG_SUDOERS_PATH"
    chmod 0440 "$WG_SUDOERS_PATH"

    if command_exists "$VISUDO_BIN"; then
        run_command "$VISUDO_BIN" -cf "$WG_SUDOERS_PATH"
    fi
}

write_server_health_sudoers() {
    if [ "$ALLOW_NON_ROOT" = "1" ]; then
        return
    fi

    local systemctl_path
    local sudoers_content

    systemctl_path="$(resolve_command_path "$SYSTEMCTL_BIN" || true)"
    [ -n "$systemctl_path" ] || fail "Binary systemctl tidak ditemukan untuk sudoers Server Health: $SYSTEMCTL_BIN"

    sudoers_content="$APP_USER ALL=(root) NOPASSWD: ${systemctl_path} daemon-reload, ${systemctl_path} restart rafen-queue, ${systemctl_path} enable --now rafen-queue, ${systemctl_path} restart rafen-schedule.timer, ${systemctl_path} enable --now rafen-schedule.timer, ${systemctl_path} restart freeradius, ${systemctl_path} enable --now freeradius, ${systemctl_path} restart genieacs-cwmp, ${systemctl_path} enable --now genieacs-cwmp, ${systemctl_path} restart genieacs-nbi, ${systemctl_path} enable --now genieacs-nbi, /bin/sync, /usr/bin/tee /proc/sys/vm/drop_caches"

    if [ "$DRY_RUN" = "1" ]; then
        printf '[DRY-RUN] write sudoers %s => %s\n' "$SERVER_HEALTH_SUDOERS_PATH" "$sudoers_content"
        return 0
    fi

    printf '%s\n' "$sudoers_content" >"$SERVER_HEALTH_SUDOERS_PATH"
    chmod 0440 "$SERVER_HEALTH_SUDOERS_PATH"

    if command_exists "$VISUDO_BIN"; then
        run_command "$VISUDO_BIN" -cf "$SERVER_HEALTH_SUDOERS_PATH"
    fi
}

bootstrap_wireguard_system_service() {
    if [ "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP" != "1" ]; then
        return
    fi

    install_dir "$WG_SYSTEM_DIR"
    install_wireguard_packages
    ensure_wireguard_server_keypair
    write_wireguard_sync_helper
    write_wireguard_sudoers
}

bootstrap_server_health_sudoers() {
    write_server_health_sudoers
}

composer_install() {
    if [ "$RUN_COMPOSER_INSTALL" != "1" ]; then
        return
    fi

    command_exists "$COMPOSER_BIN" || fail "Composer tidak ditemukan: $COMPOSER_BIN"
    run_in_app_as_installer_user "$COMPOSER_BIN" install --no-interaction --prefer-dist --optimize-autoloader
}

check_composer_platform_requirements() {
    if [ "$RUN_COMPOSER_INSTALL" != "1" ]; then
        return
    fi

    [ -f "$APP_DIR/composer.lock" ] || return

    command_exists "$COMPOSER_BIN" || fail "Composer tidak ditemukan: $COMPOSER_BIN"

    if run_in_app_as_installer_user "$COMPOSER_BIN" check-platform-reqs --lock --no-dev >/dev/null 2>&1; then
        return
    fi

    fail "Platform PHP untuk user installer tidak cocok dengan composer.lock. Jalankan 'php -v' dan 'composer check-platform-reqs --lock --no-dev' sebagai user $(installer_exec_user). Dari log Anda, lock file saat ini membutuhkan PHP 8.4, sedangkan proses install tadi masih membaca PHP 8.3.6."
}

npm_build() {
    if [ "$RUN_NPM_BUILD" != "1" ]; then
        return
    fi

    [ -f "$APP_DIR/package.json" ] || return
    [ -f "$APP_DIR/vite.config.js" ] || return

    if [ ! -f "$APP_DIR/resources/css/app.css" ] || [ ! -f "$APP_DIR/resources/js/app.js" ]; then
        warn "Entry Vite default tidak ditemukan (resources/css/app.css atau resources/js/app.js). Melewati npm build untuk installer ini."
        return
    fi

    command_exists "$NPM_BIN" || fail "npm tidak ditemukan: $NPM_BIN"
    check_node_runtime_requirements
    run_in_app_as_installer_user "$NPM_BIN" install
    run_in_app_as_installer_user "$NPM_BIN" run build
}

ensure_app_key() {
    local app_key

    app_key="$(read_env APP_KEY)"

    if [ -n "$app_key" ]; then
        return
    fi

    run_in_app_as_installer_user "$PHP_BIN" artisan key:generate --force
}

run_artisan_runtime_setup() {
    command_exists "$PHP_BIN" || fail "PHP binary tidak ditemukan: $PHP_BIN"

    run_in_app_as_installer_user "$PHP_BIN" artisan config:clear --ansi
    ensure_app_key
    require_license_public_key

    if [ "$RUN_MIGRATE" = "1" ]; then
        run_in_app_as_installer_user "$PHP_BIN" artisan migrate --force --ansi
    fi

    run_in_app_as_installer_user "$PHP_BIN" artisan storage:link --force --ansi

    if run_in_app_as_installer_user "$PHP_BIN" artisan list --raw 2>/dev/null | grep -q '^wireguard:sync'; then
        run_in_app_as_installer_user "$PHP_BIN" artisan wireguard:sync --ansi
    else
        warn "Command wireguard:sync belum tersedia, melewati sinkronisasi WireGuard saat install."
    fi

    prompt_admin_inputs_if_needed
    confirm_install_configuration_if_needed

    if [ "$RUN_SUPER_ADMIN_SETUP" != "1" ]; then
        register_install_time_if_configured
        return
    fi

    if [ -z "$ADMIN_NAME" ] || [ -z "$ADMIN_EMAIL" ] || [ -z "$ADMIN_PASSWORD" ]; then
        warn "Data super admin belum lengkap, melewati pembuatan user awal."
        return
    fi

    run_in_app_as_installer_user "$PHP_BIN" artisan user:create-super-admin "$ADMIN_NAME" "$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --phone="$ADMIN_PHONE" --ansi

    register_install_time_if_configured
}

register_install_time_if_configured() {
    local registration_output=""

    if [ -z "$(read_env SELF_HOSTED_REGISTRY_URL)" ] || [ -z "$(read_env SELF_HOSTED_REGISTRY_TOKEN)" ]; then
        return
    fi

    if ! registration_output="$(run_in_app_as_installer_user "$PHP_BIN" artisan self-hosted:register-install --admin-name="$ADMIN_NAME" --admin-email="$ADMIN_EMAIL" --admin-phone="$ADMIN_PHONE" --ansi 2>&1)"; then
        warn "Registrasi install-time ke SaaS gagal. Instalasi lokal tetap dilanjutkan, tetapi instance ini belum tercatat otomatis di SaaS. Detail: $registration_output"
        return
    fi

    if [ -n "$registration_output" ]; then
        printf '%s\n' "$registration_output"
    fi
}

show_status() {
    normalize_sqlite_path
    local access_mode
    local access_mode_note

    access_mode="$(resolve_access_mode)"
    case "$access_mode" in
        domain-based)
            access_mode_note="Domain aktif. Mode ini cocok untuk akses publik dan HTTPS/SSL."
            ;;
        custom-url)
            access_mode_note="Custom APP_URL aktif. Pastikan host ini memang bisa diakses client."
            ;;
        *)
            access_mode_note="Fallback ke IP server. Cocok untuk LAN, VPN, atau akses internal tanpa domain."
            ;;
    esac

    printf 'Mode                 : %s\n' "$MODE"
    printf 'Access Mode          : %s\n' "$access_mode"
    printf 'Access Mode Note     : %s\n' "$access_mode_note"
    printf 'App Directory        : %s\n' "$APP_DIR"
    printf 'Env File             : %s\n' "$ENV_FILE"
    printf 'Env Exists           : %s\n' "$([ -f "$ENV_FILE" ] && printf yes || printf no)"
    printf 'Public Host          : %s\n' "$(resolve_public_host)"
    printf 'Access URL           : %s\n' "$(resolve_app_url)"
    printf 'Vendor Directory     : %s\n' "$([ -d "$APP_DIR/vendor" ] && printf yes || printf no)"
    printf 'Bootstrap Cache Dir  : %s\n' "$([ -d "$APP_DIR/bootstrap/cache" ] && printf yes || printf no)"
    printf 'License Directory    : %s\n' "$([ -d "$APP_DIR/storage/app/license" ] && printf yes || printf no)"
    printf 'WireGuard Directory  : %s\n' "$([ -d "$APP_DIR/storage/app/wireguard" ] && printf yes || printf no)"
    printf 'DB Connection        : %s\n' "$DB_CONNECTION"

    if [ "$DB_CONNECTION" = "sqlite" ]; then
        printf 'SQLite Database      : %s\n' "$DB_DATABASE"
        printf 'SQLite Exists        : %s\n' "$([ -f "$DB_DATABASE" ] && printf yes || printf no)"
    else
        printf 'Database Name        : %s\n' "$DB_DATABASE"
        printf 'Database Host        : %s\n' "$DB_HOST"
    fi

    if [ -f "$ENV_FILE" ]; then
        printf 'App URL              : %s\n' "$(read_env APP_URL)"
        printf 'License Enabled      : %s\n' "$(read_env LICENSE_SELF_HOSTED_ENABLED)"
        printf 'License Enforced     : %s\n' "$(read_env LICENSE_ENFORCE)"
        printf 'License Public Key   : %s\n' "$([ -n "$(read_env LICENSE_PUBLIC_KEY)" ] && printf set || printf missing)"
        printf 'Registry URL         : %s\n' "$(read_env SELF_HOSTED_REGISTRY_URL)"
        printf 'Registry Token       : %s\n' "$([ -n "$(read_env SELF_HOSTED_REGISTRY_TOKEN)" ] && printf set || printf missing)"
        printf 'WG Apply Command     : %s\n' "$(read_env WG_APPLY_COMMAND)"
    fi

    printf 'WG System Bootstrap  : %s\n' "$RUN_WIREGUARD_SYSTEM_BOOTSTRAP"
    printf 'System Bootstrap     : %s\n' "$RUN_SYSTEM_BOOTSTRAP"
    printf 'Nginx Site           : %s\n' "$NGINX_SITE_AVAILABLE_PATH"
    printf 'Preferred PHP        : %s\n' "$PHP_PREFERRED_VERSION"
    printf 'PHP CLI Binary       : %s\n' "$(resolve_php_cli_bin)"
    printf 'Preferred Node.js    : %s.x\n' "$NODE_PREFERRED_MAJOR"
    printf 'PHP-FPM Service      : %s\n' "$(detect_php_fpm_service)"
    printf 'Deploy Sudoers       : %s\n' "$DEPLOY_SUDOERS_PATH"
    printf 'WG Helper Path       : %s\n' "$WG_SYNC_HELPER_PATH"
    printf 'WG System Service    : %s\n' "$WG_SYSTEM_SERVICE"
    printf 'Server Health Sudoers: %s\n' "$SERVER_HEALTH_SUDOERS_PATH"
}

run_install_or_deploy() {
    require_root
    normalize_php_runtime
    ensure_deploy_user
    ensure_deploy_group_membership
    write_deploy_sudoers
    ensure_expected_app_dir
    ensure_app_layout
    ensure_runtime_directories
    copy_env_file_if_missing
    prompt_install_configuration_if_needed
    configure_environment
    prepare_sqlite_database
    prepare_app_for_deploy_user
    configure_timezone
    install_system_packages
    check_composer_platform_requirements
    composer_install
    npm_build
    write_nginx_site_config
    enable_nginx_site
    bootstrap_wireguard_system_service
    bootstrap_server_health_sudoers
    run_artisan_runtime_setup
    apply_basic_permissions
    restart_web_services

    info "Installer/deployment self-hosted selesai."
    show_status
}

main() {
    elevate_with_sudo "$@"
    parse_args "$@"

    case "$MODE" in
        install|deploy)
            run_install_or_deploy
            ;;
        status)
            normalize_php_runtime
            ensure_app_layout
            show_status
            ;;
        *)
            fail "Mode tidak didukung: $MODE"
            ;;
    esac
}

main "$@"
