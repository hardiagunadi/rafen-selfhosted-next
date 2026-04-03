# Rafen Self-Hosted

Installer utama untuk fresh server ada di [install-selfhosted.sh](/var/www/rafen-selfhosted-next/install-selfhosted.sh).

## Pola Instalasi Production

Untuk production, installer memakai pembagian role berikut:
- `root` untuk provisioning server, package, Nginx, PHP-FPM, service, dan file sistem
- `deploy` untuk proses deploy aplikasi seperti `composer install`, `npm build`, dan `php artisan`
- `www-data` untuk runtime web server/PHP-FPM

Model ini dipakai agar file hasil deploy tidak dimiliki `root`, tetapi service web tetap berjalan dengan user runtime yang terpisah.

## Fresh Server

Installer akan:
- meminta eskalasi `sudo` bila dijalankan dari user biasa
- membuat user `deploy` bila installer dijalankan dari `root`, termasuk prompt password dan konfirmasi password
- menyiapkan `.env` dan direktori runtime
- memastikan runtime aplikasi memakai PHP `8.4.x`
- mencoba menambahkan repository PHP otomatis bila `php8.4` belum tersedia di repository default
- menginstal dependency sistem dasar dan dependency aplikasi
- mengonfigurasi Nginx dan PHP-FPM
- menjalankan migrate, `storage:link`, dan bootstrap runtime aplikasi
- membuat super admin awal bila data admin diberikan

Contoh pakai untuk fresh server:

```bash
bash install-selfhosted.sh install \
  --domain billing.example.com \
  --license-public-key 'BASE64_PUBLIC_KEY_DARI_VENDOR' \
  --registry-url 'https://saas.example.com/api/self-hosted/install-registrations' \
  --registry-token 'TOKEN_BOOTSTRAP_DARI_SAAS' \
  --admin-name 'Super Admin' \
  --admin-email admin@example.com \
  --admin-phone '081234567890' \
  --admin-password 'password-kuat'
```

Catatan sinkronisasi install-time:
- `--registry-url` dan `--registry-token` mengaktifkan registrasi install-time ke SaaS
- `--admin-phone` tetap didukung di CLI dan ikut dikirim dalam payload registrasi
- installer akan mengirim metadata tambahan otomatis: `app_name`, `app_url`, `app_env`, `generated_at`, `server_name`, `fingerprint`, `current_license_status`, `current_license_id`, dan `access_mode`
- bila respons SaaS mengembalikan `registry_token` baru, token itu akan disimpan otomatis ke `.env` sebagai token instance ini

Jika `--domain` tidak diisi, installer akan fallback ke IP utama server untuk `APP_URL` dan konfigurasi Nginx.

Untuk mode interaktif:
- installer akan menyalin `.env.example` menjadi `.env` bila file belum ada
- installer akan meminta `LICENSE_PUBLIC_KEY`
- installer akan menanyakan apakah sinkronisasi install-time ke SaaS ingin diaktifkan
- jika sinkronisasi diaktifkan, installer akan meminta `SELF_HOSTED_REGISTRY_URL`, `SELF_HOSTED_REGISTRY_TOKEN`, `nama`, `email`, dan `nomor WhatsApp` admin
- sebelum lanjut, installer akan menampilkan ringkasan konfigurasi dan meminta konfirmasi akhir

## Mode Akses

Installer mendukung tiga mode akses:
- `domain-based`: aktif bila `--domain` diisi, cocok untuk akses publik dan HTTPS/SSL
- `ip-based`: default bila domain tidak diisi, cocok untuk LAN, VPN, atau akses internal
- `custom-url`: aktif bila `--app-url` diisi manual

Mode aktif bisa dicek lewat perintah `status`.

## Issue Lisensi Tanpa Domain

Flow lisensi self-hosted mendukung instalasi tanpa domain final.

Praktik yang disarankan:
- install server lebih dulu dalam mode `ip-based`
- unduh activation request dari halaman lisensi self-hosted
- issue lisensi dari panel SaaS dengan mode `Fingerprint Only` atau `IP-Based`
- upload file lisensi ke self-hosted
- bila domain final baru tersedia belakangan, domain bisa dicatat pada siklus issue lisensi berikutnya tanpa mengubah pola instalasi awal

Activation request self-hosted sekarang menyertakan konteks berikut agar vendor lebih mudah menerbitkan lisensi:
- `APP_URL`
- host dari `APP_URL`
- `server_name`
- fingerprint server
- rekomendasi mode akses awal

Di halaman lisensi self-hosted tersedia helper operasional:
- `Copy Activation Summary` untuk kirim ringkasan cepat ke vendor
- preview `Activation Request JSON`
- `Copy Activation Request JSON` untuk copy payload penuh tanpa harus unduh file dulu

## Requirement Runtime

- PHP CLI dan PHP-FPM diprioritaskan ke `8.4.x`
- installer akan memilih `php8.4` bila tersedia
- installer akan memilih service `php8.4-fpm` dan socket `php8.4-fpm.sock` bila tersedia
- bila repository default belum menyediakan `php8.4`, installer akan mencoba:
- Ubuntu: `ppa:ondrej/php`
- Debian: `packages.sury.org/php`

Jika repository tambahan tetap tidak menyediakan `php8.4`, installer akan berhenti dengan pesan error yang jelas.

## Opsi Penting

- `--domain <host>`: set domain/host publik
- `--app-url <url>`: override `APP_URL` penuh
- `--license-public-key <key>`: public key verifikasi lisensi, wajib diisi
- `--registry-url <url>`: endpoint API registrasi install-time ke SaaS, biasanya `/api/self-hosted/install-registrations`
- `--registry-token <token>`: bearer token bootstrap untuk registrasi install-time ke SaaS
- `--admin-name <name>`: nama super admin awal
- `--admin-email <email>`: email super admin awal
- `--admin-phone <phone>`: nomor WhatsApp super admin awal, juga ikut dikirim saat sinkronisasi install-time ke SaaS
- `--admin-password <value>`: password super admin awal
- `--db-connection <driver>`: koneksi database, `sqlite` atau `mysql`
- `--db-host <host>`: host database untuk mode non-sqlite
- `--db-port <port>`: port database untuk mode non-sqlite
- `--db-name <name|path>`: nama database atau path file sqlite
- `--db-user <user>`: username database untuk mode non-sqlite
- `--db-password <value>`: password database untuk mode non-sqlite
- `--skip-system-bootstrap`: lewati provisioning package sistem dan Nginx/PHP-FPM
- `--skip-composer-install`: lewati `composer install`
- `--skip-npm-build`: lewati `npm install` dan build frontend
- `--skip-migrate`: lewati `php artisan migrate --force`
- `--skip-super-admin`: lewati pembuatan super admin awal
- WireGuard bootstrap level sistem aktif secara default
- `--skip-wireguard-system`: nonaktifkan bootstrap WireGuard level sistem bila diperlukan
- `--wireguard-system`: force enable bootstrap WireGuard level sistem (opsional, untuk kompatibilitas)

Environment variable penting:
- `DEPLOY_USER`, `DEPLOY_GROUP`, `DEPLOY_PASSWORD`: kontrol user deploy untuk instalasi dari `root`
- `PHP_PREFERRED_VERSION`: versi PHP target, default `8.4`
- `PHP_BIN`: override binary PHP secara manual bila memang diperlukan
- `PHP_FPM_SERVICE`, `PHP_FPM_SOCK`: override service/socket PHP-FPM
- `LICENSE_PUBLIC_KEY_VALUE`: isi public key lisensi tanpa prompt interaktif
- `SELF_HOSTED_REGISTRY_URL_VALUE`, `SELF_HOSTED_REGISTRY_TOKEN_VALUE`: isi endpoint dan token bootstrap registrasi ke SaaS tanpa prompt interaktif
- `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PHONE`, `ADMIN_PASSWORD`: isi data super admin awal via environment
- `APP_DOMAIN`, `APP_URL_OVERRIDE`: override domain atau `APP_URL` via environment
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: override database via environment

## Sinkronisasi ke SaaS

Yang dibutuhkan di sisi self-hosted:
- `SELF_HOSTED_REGISTRY_URL`: endpoint SaaS, biasanya `https://domain-saas/api/self-hosted/install-registrations`
- `SELF_HOSTED_REGISTRY_TOKEN`: token bootstrap dari SaaS

Yang dibutuhkan di sisi SaaS:
- token bootstrap global untuk menerima registrasi awal
- setelah tenant self-hosted terdaftar, SaaS akan membuat token per-instance untuk tenant tersebut

Perilaku token saat ini:
- token global SaaS dipakai untuk bootstrap registrasi pertama instance baru
- setelah registrasi sukses, SaaS mengembalikan `registry_token` khusus instance
- self-hosted installer/command akan menyimpan token baru itu otomatis ke `.env`
- setelah tenant punya token per-instance, endpoint SaaS hanya menerima token milik tenant tersebut untuk fingerprint yang sama

Payload yang dikirim self-hosted saat registrasi install-time saat ini mencakup:
- `app_name`, `app_url`, `app_env`, `generated_at`, `server_name`
- `fingerprint`, `current_license_status`, `current_license_id`, `access_mode`
- `admin_name`, `admin_email`, `admin_phone`

Artinya, generator token global di halaman `Public Key Lisensi` SaaS dipakai untuk bootstrap install baru, sedangkan rotasi token harian per tenant dilakukan dari halaman detail tenant self-hosted di SaaS.

## Catatan Operasional

- Jalankan installer sebagai `root` untuk fresh server agar provisioning sistem bisa lengkap
- Bila dijalankan sebagai user biasa, installer akan mencoba eskalasi lewat `sudo`
- Mode tanpa domain tetap valid untuk production kecil atau instalasi internal, tetapi HTTPS publik biasanya tetap membutuhkan domain
- Untuk deploy ulang setelah server siap, gunakan mode `deploy`

### Langkah Tambahan untuk Server Lama

Jika server self-hosted sudah terpasang sebelum fitur `Server Health` terbaru ini masuk, jalankan sekali:

```bash
sudo bash install-selfhosted.sh deploy
```

Langkah ini diperlukan agar installer menulis file sudoers `Server Health` via `visudo`, sehingga aksi seperti `Start Permanen`, `Restart`, dan `Clear RAM` dari halaman `/super-admin/server-health` tidak lagi meminta password `sudo`.

Jika muncul error `deploy is not in the sudoers file`, berarti server lama belum sempat mendapat provisioning sudo untuk user `deploy`. Login sebagai `root` sekali lalu jalankan:

```bash
bash install-selfhosted.sh deploy
```

Deploy terbaru akan menulis sudoers untuk user `deploy`, jadi deploy berikutnya sudah bisa memakai:

```bash
sudo bash install-selfhosted.sh deploy
```

Setelah command di atas selesai:
- tombol aksi service di `Server Health` bisa menjalankan `systemctl restart` dan `systemctl enable --now` tanpa prompt password
- aktivasi lisensi yang berhasil bisa otomatis menyalakan service berlisensi yang masih down
- server lama ikut mendapatkan provisioning yang sama dengan instalasi baru

## Cek Status

```bash
bash install-selfhosted.sh status
```
