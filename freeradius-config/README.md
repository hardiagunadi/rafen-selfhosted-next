# FreeRADIUS Config — Rafen

Folder ini berisi **seluruh file konfigurasi FreeRADIUS** yang dimodifikasi untuk Rafen.
File-file ini harus di-restore setiap kali install di server baru.

## File yang Di-backup

| File | Keterangan |
|------|------------|
| `radiusd.conf` | Auth logging aktif (`auth = yes`, `auth_badpass = yes`), include `clients.d/` |
| `clients.conf` | `require_message_authenticator = yes` untuk localhost |
| `clients.d/laravel.conf` | NAS clients dari DB (di-generate otomatis oleh Rafen) |
| `dictionary` | Atribut Mikrotik extended (nomor 31+) yang tidak ada di default FreeRADIUS |
| `mods-available/sql` | SQL module: `sql_user_name` pakai `Stripped-User-Name`, include `queries.conf` |
| `sites-available/default` | `strip_pppoe_prefix` di blok `authorize {}` |
| `policy.d/filter` | Validasi "realm harus punya titik" dinonaktifkan |
| `policy.d/strip_pppoe_prefix` | Strip prefix `pppoe-` dari User-Name |

---

## Instalasi di Server Baru

### 1. Install paket

```bash
apt update
apt install -y freeradius freeradius-mysql
```

### 2. Restore semua file config

Jalankan dari root direktori repo (`/var/www/rafen`):

```bash
cd /var/www/rafen

# radiusd.conf dan clients.conf
sudo cp freeradius-config/radiusd.conf    /etc/freeradius/3.0/radiusd.conf
sudo cp freeradius-config/clients.conf    /etc/freeradius/3.0/clients.conf
sudo chown freerad:freerad                /etc/freeradius/3.0/radiusd.conf
sudo chown freerad:freerad                /etc/freeradius/3.0/clients.conf

# dictionary (local VSA definitions — wajib agar Mikrotik-Queue-Parent-Name dikenal)
sudo cp freeradius-config/dictionary      /etc/freeradius/3.0/dictionary
sudo chown freerad:freerad                /etc/freeradius/3.0/dictionary

# SQL module
sudo cp freeradius-config/mods-available/sql /etc/freeradius/3.0/mods-available/sql
sudo chown freerad:freerad                   /etc/freeradius/3.0/mods-available/sql

# Sites
sudo cp freeradius-config/sites-available/default /etc/freeradius/3.0/sites-available/default
sudo chown freerad:freerad                         /etc/freeradius/3.0/sites-available/default

# Policies
sudo cp freeradius-config/policy.d/filter             /etc/freeradius/3.0/policy.d/filter
sudo cp freeradius-config/policy.d/strip_pppoe_prefix /etc/freeradius/3.0/policy.d/strip_pppoe_prefix
sudo chown freerad:freerad /etc/freeradius/3.0/policy.d/filter
sudo chown freerad:freerad /etc/freeradius/3.0/policy.d/strip_pppoe_prefix

# clients.d directory + placeholder (file aslinya di-generate oleh Rafen via app)
sudo mkdir -p /etc/freeradius/3.0/clients.d
sudo cp freeradius-config/clients.d/laravel.conf /etc/freeradius/3.0/clients.d/laravel.conf
sudo chown freerad:freerad /etc/freeradius/3.0/clients.d/laravel.conf
```

### 3. Aktifkan SQL module

```bash
sudo ln -sf /etc/freeradius/3.0/mods-available/sql \
            /etc/freeradius/3.0/mods-enabled/sql
```

### 4. Setup database RADIUS

Tabel RADIUS (`radcheck`, `radreply`, `radgroupcheck`, `radgroupreply`, `radacct`, `nas`)
harus ada di database `rafen`. Jika belum:

```bash
# Cari skema di salah satu lokasi ini:
ls /usr/share/doc/freeradius/examples/sql/mysql/schema.sql* 2>/dev/null
ls /usr/share/freeradius/sql/mysql/schema.sql 2>/dev/null

# Import (sesuaikan path):
zcat /usr/share/doc/freeradius/examples/sql/mysql/schema.sql.gz | mysql -u rafen -p rafen
# atau:
mysql -u rafen -p rafen < /usr/share/freeradius/sql/mysql/schema.sql
```

Pada installer self-hosted terbaru, langkah ini sudah dikerjakan otomatis:
- installer memakai MariaDB tunggal yang sama untuk app + queue + WA gateway + FreeRADIUS
- schema FreeRADIUS diimport otomatis jika tabel inti belum ada
- file `mods-available/sql` dirender ulang mengikuti `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dari `.env`

### 5. Sudoers

```bash
sudo visudo -f /etc/sudoers.d/rafen-freeradius
```

Isi file:
```
# FreeRADIUS — izinkan www-data restart/reload
Defaults:www-data !requiretty
www-data ALL=NOPASSWD:/bin/systemctl reload freeradius,/bin/systemctl restart freeradius

# FreeRADIUS dictionary — izinkan deploy (artisan) copy dictionary baru
deploy ALL=NOPASSWD:/bin/cp /var/www/rafen/freeradius-config/dictionary /etc/freeradius/3.0/dictionary
```

### 6. Validasi config & start

```bash
# Test syntax config (tidak start server)
sudo freeradius -C

# Jika OK:
sudo systemctl enable freeradius
sudo systemctl restart freeradius
sudo systemctl status freeradius
```

### 7. Sync MikroTik clients dari Rafen

Setelah app berjalan, generate `clients.d/laravel.conf` dari database:

```bash
php artisan radius:sync-clients
```

### 8. Cek dictionary atribut

Pastikan semua atribut RADIUS di database sudah terdaftar di dictionary:

```bash
php artisan radius:check-dictionary --fix
```

---

## Catatan Penting

### Dictionary (`/etc/freeradius/3.0/dictionary`)
FreeRADIUS 3 default hanya mendefinisikan atribut Mikrotik nomor 1–30. Rafen menggunakan
atribut tambahan (e.g. `Mikrotik-Queue-Parent-Name` = nomor 31). Tanpa file ini,
autentikasi PPPoE gagal dengan error:
```
sql: Failed to create the pair: Unknown attribute "Mikrotik-Queue-Parent-Name"
```
Command `radius:check-dictionary --fix` berjalan otomatis tiap hari pukul 06:00 untuk
mendeteksi dan mendaftarkan atribut baru, lalu me-restart FreeRADIUS.

### `clients.d/laravel.conf`
File ini di-generate ulang oleh Rafen setiap kali koneksi MikroTik ditambah/dihapus.
File backup di sini hanya sebagai placeholder agar `clients.d/` tidak kosong saat pertama install.
Setelah `php artisan radius:sync-clients` dijalankan, isinya akan diganti dengan NAS aktual.

### FreeRADIUS `reload` vs `restart`
FreeRADIUS 3 HUP (`reload`) **tidak** memuat ulang `clients.d/`. Selalu gunakan `restart`.
Sudah dikonfigurasi di `.env`:
```
RADIUS_RELOAD_COMMAND="sudo systemctl restart freeradius"
RADIUS_RESTART_COMMAND="sudo systemctl restart freeradius"
```

### `realm suffix` — strip = no
`mods-available/suffix` dibiarkan default (`strip = no`) agar username `user@domain`
disimpan utuh ke DB. Username tanpa TLD seperti `user@1189` juga diterima
(policy `filter` sudah dimodifikasi).

### Ownership file FreeRADIUS
Semua file di `/etc/freeradius/3.0/` harus milik `freerad:freerad`. Jika lupa `chown`,
FreeRADIUS bisa gagal start atau tidak load file tertentu secara diam-diam.
