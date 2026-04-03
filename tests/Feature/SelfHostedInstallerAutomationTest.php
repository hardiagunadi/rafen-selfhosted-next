<?php

use Illuminate\Support\Facades\File;

it('ships self-hosted installer automation for single MariaDB and radius bootstrap', function () {
    $scriptPath = base_path('install-selfhosted.sh');
    $scriptSource = File::get($scriptPath);

    expect($scriptSource)
        ->toContain('DB_CONNECTION="${DB_CONNECTION:-mariadb}"')
        ->toContain('DB_DATABASE="${DB_DATABASE:-rafen_selfhosted}"')
        ->toContain('DB_USERNAME="${DB_USERNAME:-rafen}"')
        ->toContain('set_env QUEUE_CONNECTION "database"')
        ->toContain('verify_php_database_extensions()')
        ->toContain('foreach ([\'pdo_mysql\', \'mysqli\'] as \$ext)')
        ->toContain('run_command "$APT_GET_BIN" install -y mariadb-server mariadb-client')
        ->toContain('RUN_GENIEACS_BOOTSTRAP="${RUN_GENIEACS_BOOTSTRAP:-1}"')
        ->toContain('MONGODB_MAJOR="${MONGODB_MAJOR:-8.0}"')
        ->toContain('GENIEACS_VERSION="${GENIEACS_VERSION:-1.2.14+260313cc72}"')
        ->toContain('add_mongodb_apt_repository()')
        ->toContain('run_command "$APT_GET_BIN" install -y mongodb-org mongodb-database-tools')
        ->toContain('run_command "$NPM_BIN" install -g "genieacs@${GENIEACS_VERSION}"')
        ->toContain('write_genieacs_env_file()')
        ->toContain('write_genieacs_systemd_units()')
        ->toContain('GENIEACS_MONGODB_CONNECTION_URL=${mongo_url}')
        ->toContain('ExecStart=${cwmp_bin}')
        ->toContain('ExecStart=${nbi_bin}')
        ->toContain('enable --now mongod.service')
        ->toContain('enable --now genieacs-cwmp.service')
        ->toContain('enable --now genieacs-fs.service')
        ->toContain('enable --now genieacs-nbi.service')
        ->toContain('enable --now genieacs-ui.service')
        ->toContain('create_radius_schema_if_missing()')
        ->toContain('find_freeradius_schema_sql()')
        ->toContain('render_freeradius_sql_module()')
        ->toContain('create_radius_schema_if_missing')
        ->toContain('render_freeradius_sql_module "$fr_dir/mods-available/sql"')
        ->toContain('ensure_wa_gateway_dist_artifacts "$wa_path"')
        ->toContain('if [ ! -f "$wa_path/package.json" ] || [ ! -f "$gateway_script" ]; then')
        ->toContain('if [ ! -f "$wa_dist_entry" ]; then')
        ->toContain('Artifact wa-multi-session/dist belum ada. Mengambil runtime package ${npm_spec} dari npm.')
        ->toContain('"$node_bin" -e "const pkg = require(process.argv[1]); process.stdout.write(String(pkg.name || \'\'))"')
        ->toContain('"$node_bin" -e "const pkg = require(process.argv[1]); process.stdout.write(String(pkg.version || \'\'))"')
        ->toContain('npm pack $(shell_quote "$npm_spec") >/dev/null')
        ->toContain('pm2 jlist | node -e')
        ->toContain('wa-multi-session gagal online via PM2')
        ->toContain("table_name IN ('radcheck','radreply','radgroupcheck','radgroupreply','radacct','radpostauth','radusergroup','radippool','nas')")
        ->toContain('Schema FreeRADIUS siap dipakai di database ${DB_DATABASE}.')
        ->toContain('Installer self-hosted tidak lagi mendukung sqlite sebagai database utama');
});

it('ships a sanitized freeradius sql template for self-hosted bundle', function () {
    $sqlTemplate = File::get(base_path('freeradius-config/mods-available/sql'));

    expect($sqlTemplate)
        ->toContain('password = "__SET_VIA_INSTALLER__"')
        ->toContain('radius_db = "rafen_selfhosted"')
        ->not->toContain('1ZpFklkkARjJohMbcSy1yQMP');
});
