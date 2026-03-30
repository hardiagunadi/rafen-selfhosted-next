# Rafen Self-Hosted

Installer utama untuk fresh server ada di [install-selfhosted.sh](/var/www/rafen-selfhosted/install-selfhosted.sh).

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
  --admin-name 'Super Admin' \
  --admin-email admin@example.com \
  --admin-password 'password-kuat'
```

Kalau tanpa domain:

```bash
bash install-selfhosted.sh install \
  --license-public-key 'BASE64_PUBLIC_KEY_DARI_VENDOR' \
  --admin-name 'Super Admin' \
  --admin-email admin@example.com \
  --admin-password 'password-kuat'
```

Jika `--domain` tidak diisi, installer akan fallback ke IP utama server untuk `APP_URL` dan konfigurasi Nginx.

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
- `--skip-system-bootstrap`: lewati provisioning package sistem dan Nginx/PHP-FPM
- `--wireguard-system`: siapkan helper WireGuard level sistem

Environment variable penting:
- `DEPLOY_USER`, `DEPLOY_GROUP`, `DEPLOY_PASSWORD`: kontrol user deploy untuk instalasi dari `root`
- `PHP_PREFERRED_VERSION`: versi PHP target, default `8.4`
- `PHP_BIN`: override binary PHP secara manual bila memang diperlukan
- `PHP_FPM_SERVICE`, `PHP_FPM_SOCK`: override service/socket PHP-FPM

## Catatan Operasional

- Jalankan installer sebagai `root` untuk fresh server agar provisioning sistem bisa lengkap
- Bila dijalankan sebagai user biasa, installer akan mencoba eskalasi lewat `sudo`
- Mode tanpa domain tetap valid untuk production kecil atau instalasi internal, tetapi HTTPS publik biasanya tetap membutuhkan domain
- Untuk deploy ulang setelah server siap, gunakan mode `deploy`

## Cek Status

```bash
bash install-selfhosted.sh status
```
