# Self-Hosted Update System Design

## Tujuan

Membuat sistem update aplikasi `Rafen Self-Hosted` yang:

- aman untuk production
- jelas source of truth versinya
- tidak bergantung pada branch Git yang bergerak bebas
- tetap kompatibel dengan flow lisensi dan registrasi ke SaaS yang sudah ada
- bisa diadopsi bertahap tanpa memecah installer dan toolkit saat ini

## Rekomendasi Inti

Source of truth update harus berasal dari **release manifest repo self-hosted di GitHub**, bukan dari:

- verifikasi versi via SaaS sebagai sumber utama
- status `git pull` terhadap `origin/main`

Peran yang disarankan:

- **GitHub release manifest**: menentukan versi terbaru yang valid untuk diinstall
- **SaaS**: control plane untuk registry, lisensi, policy, notifikasi, dan visibilitas status instance
- **Self-hosted instance**: executor yang melakukan check, backup, apply, verify, dan rollback

## Kenapa Bukan SaaS-Verified Update Sebagai Jalur Utama

Konsep verifikasi dari SaaS masih relevan, tetapi hanya sebagai lapisan policy dan observability.

Alasannya:

- SaaS saat ini sudah dipakai untuk registrasi install-time, token per-instance, dan license upgrade request
- codebase belum menunjukkan adanya update engine yang benar-benar menjadikan SaaS sebagai otoritas versi runtime
- kalau SaaS dijadikan sumber versi utama, repo self-hosted menjadi tergantung pada state database/platform, padahal artifact code final tetap berasal dari repo Git
- self-hosted harus tetap bisa diupdate walau SaaS sedang unavailable, selama release artifact sudah ada

Kesimpulan:

- **SaaS tetap dipakai**
- tetapi **bukan** sebagai sumber versi final yang harus dipercaya instance untuk memutuskan isi update

## Kenapa Bukan `git pull origin/main` Sebagai Jalur Utama

`git pull` ke branch seperti `main` terlalu longgar untuk production karena:

- branch tip belum tentu release yang sudah disahkan
- tidak ada metadata migration risk, maintenance note, checksum, atau rollback guidance
- sulit membedakan hotfix, draft work, dan release resmi
- rentan kalau instance memiliki local modification atau branch tracking yang tidak konsisten

`git pull` tetap boleh dipakai untuk maintainer internal, tetapi hanya sebagai mode teknis rendah-level, bukan sebagai sistem update resmi yang terlihat di UI.

## Arsitektur Target

### 1. Release Artifact

Setiap rilis self-hosted harus menghasilkan:

- Git tag, misalnya `v2026.04.03-main.1`
- release manifest JSON
- release notes URL
- optional portable bundle / candidate artifact

Manifest ini menjadi dasar check update.

### 2. Local Runtime State

Instance self-hosted menyimpan status lokal:

- versi aplikasi saat ini
- commit yang terpasang
- channel update
- waktu check terakhir
- hasil check terakhir
- status apply terakhir
- rollback target terakhir
- status heartbeat terakhir ke SaaS
- waktu heartbeat terakhir dan `status_id` monitoring dari SaaS
- waktu heartbeat sukses terakhir untuk deteksi sync stale

### 3. SaaS Control Plane

SaaS menerima heartbeat/status berkala dari instance untuk:

- melihat versi yang dipakai tenant
- melihat apakah tenant tertinggal
- menentukan tenant eligible atau blocked untuk update tertentu
- mengirim notice operasional

Namun keputusan teknis "versi terbaru apa yang boleh dipasang" tetap dibaca instance dari release manifest.

## Release Manifest

File rekomendasi:

- `https://github.com/hardiagunadi/rafen-selfhosted-next/releases/download/<tag>/release-manifest.json`

Schema minimum:

```json
{
  "schema": "rafen-self-hosted-release:v1",
  "channel": "stable",
  "version": "2026.04.03-main.1",
  "tag": "v2026.04.03-main.1",
  "commit": "bee6dfb",
  "published_at": "2026-04-03T18:00:00+07:00",
  "release_notes_url": "https://github.com/hardiagunadi/rafen-selfhosted-next/releases/tag/v2026.04.03-main.1",
  "severity": "normal",
  "requires_maintenance": true,
  "requires_backup": true,
  "requires_migration": true,
  "minimum_supported_from": "2026.03.20-main.1",
  "php_version": "8.4",
  "node_major": 22,
  "package": {
    "type": "git-tag",
    "repository": "git@github.com:hardiagunadi/rafen-selfhosted-next.git",
    "ref": "v2026.04.03-main.1"
  },
  "checksums": {
    "manifest_sha256": "<sha256>"
  },
  "post_update": {
    "artisan": [
      "php artisan migrate --force",
      "php artisan optimize:clear",
      "php artisan config:cache",
      "php artisan route:cache",
      "php artisan view:cache"
    ]
  }
}
```

Field yang wajib dipakai sejak awal:

- `schema`
- `channel`
- `version`
- `tag`
- `commit`
- `published_at`
- `release_notes_url`
- `requires_maintenance`
- `requires_backup`
- `requires_migration`
- `package.repository`
- `package.ref`

## Strategi Versi

Versi current pada instance jangan hanya mengandalkan `APP_VERSION`.

Target state:

- `APP_VERSION` tetap dipakai untuk UI cepat
- tambahkan pencatatan `CURRENT_COMMIT`
- saat deploy/update berhasil, simpan snapshot versi terpasang ke storage atau database

Konfigurasi yang disarankan:

- `APP_VERSION`
- `APP_COMMIT`
- `SELF_HOSTED_UPDATE_CHANNEL=stable`
- `SELF_HOSTED_HEARTBEAT_STALE_AFTER_MINUTES=60`
- `SELF_HOSTED_UPDATE_MANIFEST_URL=...`
- `SELF_HOSTED_UPDATE_REPOSITORY=git@github.com:hardiagunadi/rafen-selfhosted-next.git`
- `SELF_HOSTED_UPDATE_WORKDIR=/var/www/rafen-selfhosted-next`
- `SELF_HOSTED_HEARTBEAT_STALE_AFTER_MINUTES=60`

## Model Data Lokal

Tambahkan dua tabel.

### `self_hosted_update_states`

Satu row aktif untuk state instance.

Kolom yang disarankan:

- `id`
- `channel`
- `current_version`
- `current_commit`
- `current_ref`
- `latest_version`
- `latest_commit`
- `latest_ref`
- `latest_manifest_url`
- `latest_release_notes_url`
- `update_available`
- `last_checked_at`
- `last_check_status`
- `last_check_message`
- `last_applied_at`
- `last_apply_status`
- `last_apply_message`
- `rollback_ref`
- `created_at`
- `updated_at`

### `self_hosted_update_runs`

Audit trail setiap operasi update.

Kolom yang disarankan:

- `id`
- `action`
- `target_version`
- `target_ref`
- `started_at`
- `finished_at`
- `status`
- `triggered_by_user_id`
- `output_excerpt`
- `backup_path`
- `rollback_ref`
- `metadata`
- `created_at`
- `updated_at`

## Service Yang Perlu Ditambahkan

Di repo self-hosted:

- `App\Services\SelfHostedUpdateManifestService`
  - fetch manifest
  - validasi schema
  - normalisasi payload

- `App\Services\SelfHostedUpdateStatusService`
  - baca current version/commit
  - bandingkan dengan manifest
  - simpan snapshot ke `self_hosted_update_states`

- `App\Services\SelfHostedUpdateRunnerService`
  - lock update
  - maintenance mode
  - backup
  - fetch tag/ref
  - dependency install
  - migration
  - post-update checks
  - rollback bila gagal

- `App\Services\SelfHostedUpdateHeartbeatService`
  - kirim status versi ke SaaS secara berkala
  - tidak memutus check update lokal bila SaaS gagal dihubungi

## Command Yang Disarankan

Tambahkan command baru:

- `php artisan self-hosted:update:status`
  - tampilkan current version, latest version, check result

- `php artisan self-hosted:update:check {--force}`
  - ambil manifest
  - validasi channel
  - bandingkan versi
  - simpan hasil check

- `php artisan self-hosted:update:apply {ref?} {--yes} {--skip-backup} {--dry-run}`
  - jalankan update resmi ke tag/ref tertentu

- `php artisan self-hosted:update:rollback {ref?} {--yes}`
  - rollback ke release sebelumnya

- `php artisan self-hosted:update:heartbeat`
  - kirim versi dan status update ke SaaS

## Route dan UI Yang Disarankan

Tambahkan halaman baru di area super admin self-hosted:

- `GET /super-admin/app-update`
- `POST /super-admin/app-update/check`
- `POST /super-admin/app-update/apply`
- `POST /super-admin/app-update/rollback`

Controller baru:

- `App\Http\Controllers\SuperAdminAppUpdateController`

Halaman ini lebih cocok daripada mencampur update ke:

- `Server Health`
- `Terminal`
- `Self-Hosted Toolkit`

karena update aplikasi punya lifecycle sendiri dan butuh guardrail yang lebih ketat.

### Isi halaman `App Update`

- current version
- current commit
- latest version
- severity
- release notes
- status heartbeat ke SaaS
- indikator stale jika heartbeat sukses terakhir melewati ambang waktu
- migration required / maintenance required
- hasil preflight
- tombol `Check Update`
- tombol `Apply Update`
- tombol `Rollback`
- histori update run
- status sinkronisasi heartbeat ke SaaS
- indikator `stale` bila heartbeat sukses terakhir melewati threshold

## Preflight Check Sebelum Apply

Sebelum update dijalankan, sistem wajib mengecek:

- worktree bersih
- branch/ref saat ini terdeteksi
- disk cukup
- database konek
- queue worker / scheduler / web service aktif
- backup path writable
- PHP/Node version memenuhi syarat release

Kalau salah satu gagal, update harus ditolak dengan pesan yang jelas.

## Mekanisme Apply Update

Urutan apply yang direkomendasikan:

1. ambil advisory lock
2. tulis row `self_hosted_update_runs`
3. jalankan preflight
4. aktifkan maintenance mode
5. backup database
6. simpan current ref sebagai `rollback_ref`
7. fetch tag release
8. checkout ke tag atau detached ref yang dituju
9. `composer install --no-dev --optimize-autoloader`
10. `npm ci && npm run build` bila release membutuhkan frontend rebuild
11. `php artisan migrate --force`
12. `php artisan optimize:clear`
13. cache ulang yang diperlukan
14. restart service terkait bila perlu
15. health check HTTP dan artisan
16. nonaktifkan maintenance mode
17. update state table
18. kirim heartbeat ke SaaS

## Mekanisme Rollback

Rollback tidak boleh hanya "git pull ke commit lama".

Jalur yang lebih aman:

- simpan `rollback_ref` sebelum apply
- rollback ke ref itu
- jalankan `composer install`
- clear/cache ulang
- jalankan rollback verification

Catatan:

- rollback database otomatis tidak disarankan untuk fase awal
- lebih aman rollback code + restore DB dari backup terpisah jika migration bersifat destruktif

## Integrasi Dengan SaaS

SaaS perlu menerima heartbeat ringan dari self-hosted.

Endpoint baru yang disarankan di SaaS:

- `POST /api/self-hosted/update-heartbeats`

Payload minimum:

```json
{
  "fingerprint": "sha256:...",
  "app_url": "https://billing.example.com",
  "channel": "stable",
  "current_version": "2026.04.03-main.1",
  "current_commit": "bee6dfb",
  "latest_version": "2026.04.05-main.1",
  "update_available": true,
  "last_checked_at": "2026-04-03T19:10:00+07:00",
  "last_check_status": "ok",
  "last_apply_status": "success"
}
```

Peran endpoint ini:

- dashboard tenant di SaaS bisa melihat instance tertinggal
- support/vendor tahu versi tenant saat troubleshooting
- SaaS bisa memberi policy seperti `minimum_supported_version`

Tetapi endpoint ini **tidak** menentukan artifact mana yang diinstall.

## Tempat Publish Release

Publish release terbaik dilakukan dari pipeline repo self-hosted, bukan dari instance customer.

Flow yang disarankan:

1. SaaS membentuk candidate repo seperti saat ini
2. candidate disinkronkan ke repo `rafen-selfhosted-next`
3. setelah review dan test, buat tag release resmi
4. CI membuat `release-manifest.json`
5. instance self-hosted membaca manifest itu

Ini cocok dengan arah yang sudah ada di:

- `self-hosted:materialize-repo`
- `self-hosted:stage`
- `scripts/self-hosted-sync.sh`

## Hubungan Dengan Toolkit Yang Sudah Ada

Toolkit saat ini tetap dipertahankan untuk:

- manifest
- cutover plan
- stage bundle
- import bundle
- seed workspace
- audit workspace
- materialize repo
- publish update notice

Namun update runtime aplikasi sebaiknya **tidak** ditangani oleh `SelfHostedToolkitService`.

Toolkit tetap fokus sebagai alat maintainer/release engineering.
Update runtime production sebaiknya punya service, route, permission, dan audit trail sendiri.

## Fase Implementasi

### Fase 1

Bangun read-only update visibility.

Deliverable:

- manifest fetcher
- local status table
- `update:check`
- halaman `App Update` read-only

### Fase 2

Bangun apply manual-assisted.

Deliverable:

- `update:apply`
- preflight
- backup hook
- rollback ref
- audit runs

### Fase 3

Bangun SaaS heartbeat dan policy.

Deliverable:

- heartbeat endpoint di SaaS
- tabel status versi tenant
- dashboard visibility

### Fase 4

Tambahkan hardening.

Deliverable:

- signed manifest
- checksum artifact
- health-check gating
- channel `stable` dan `candidate`

## Hardening Yang Disarankan

- manifest ditandatangani vendor
- tag release immutable
- apply memakai maintenance window warning
- lock agar hanya satu update run aktif
- semua output disanitasi dan dibatasi
- aksi update hanya untuk super admin self-hosted

## Keputusan Final

Keputusan yang direkomendasikan untuk RAFEN:

- **pakai GitHub release manifest sebagai source of truth update**
- **pakai SaaS sebagai control plane, bukan sebagai sumber versi utama**
- **jangan gunakan `git pull origin/main` sebagai sistem update resmi**
- **pisahkan update runtime dari toolkit release engineering**

## Implementasi Pertama Yang Paling Bernilai

Urutan kerja tercepat:

1. tambah manifest release formal
2. tambah `SelfHostedUpdateManifestService`
3. tambah `self_hosted_update_states`
4. tambah `self-hosted:update:check`
5. tambah halaman `App Update`
6. baru lanjut ke `update:apply`

Kalau semua itu sudah ada, RAFEN akan punya fondasi update yang:

- cukup aman untuk production
- tidak mengunci self-hosted ke ketersediaan SaaS
- tetap memberi visibilitas pusat ke vendor/operator
- selaras dengan arsitektur repo saat ini
