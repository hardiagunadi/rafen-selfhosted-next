# Self-Hosted Rebase Blueprint

## Ringkasan

Porting SaaS ke self-hosted secara manual per modul terbukti lambat, mahal, dan hasil UI/UX-nya tetap mudah melenceng dari tenant SaaS. Untuk target:

- UI/UX self-hosted sama dengan tenant SaaS
- flow operasional tetap familiar untuk pelanggan yang pindah dari SaaS
- parity fitur cepat tercapai

strategi yang lebih efektif adalah:

1. jadikan repo tenant SaaS sebagai baseline self-hosted baru
2. sederhanakan multi-tenant menjadi single-tenant
3. pertahankan layer lisensi, installer, dan provisioning self-hosted
4. matikan hanya fitur yang memang khusus SaaS

Dokumen ini menggantikan pendekatan "porting incremental modul demi modul" sebagai strategi utama.

## Kenapa Rebase Lebih Masuk Akal

Masalah terbesar repo self-hosted saat ini bukan sekadar fitur kurang, tetapi divergensi arsitektur:

- struktur route berbeda dari tenant SaaS
- layout, shell halaman, dan pattern CRUD sudah telanjur tidak seirama
- setiap parity butuh rekonstruksi controller, view, route, dan behavior
- hasilnya tetap berisiko berbeda dari ekspektasi user SaaS

Kalau source of truth operasional memang tenant SaaS, maka basis self-hosted juga harus lahir dari codebase tenant SaaS.

## Fakta Penting Dari Codebase SaaS

Repo SaaS sudah memiliki fondasi khusus self-hosted yang bisa dipakai langsung:

- `LICENSE_SELF_HOSTED_ENABLED` di [`/var/www/rafen/routes/web.php`](/var/www/rafen/routes/web.php)
- cluster route lisensi di [`/var/www/rafen/routes/self_hosted_license.php`](/var/www/rafen/routes/self_hosted_license.php)
- controller lisensi di [`/var/www/rafen/app/Http/Controllers/SuperAdminLicenseController.php`](/var/www/rafen/app/Http/Controllers/SuperAdminLicenseController.php)
- manifest extraction di [`/var/www/rafen/app/Services/SelfHostedExtractionManifestService.php`](/var/www/rafen/app/Services/SelfHostedExtractionManifestService.php)
- runbook cutover di [`/var/www/rafen/app/Services/SelfHostedCutoverPlanService.php`](/var/www/rafen/app/Services/SelfHostedCutoverPlanService.php)
- command staging di [`/var/www/rafen/app/Console/Commands/StageSelfHostedExtraction.php`](/var/www/rafen/app/Console/Commands/StageSelfHostedExtraction.php)
- command candidate repo di [`/var/www/rafen/app/Console/Commands/MaterializeSelfHostedRepository.php`](/var/www/rafen/app/Console/Commands/MaterializeSelfHostedRepository.php)

Kesimpulannya: repo SaaS sudah lebih siap untuk dijadikan sumber self-hosted baru daripada repo self-hosted lama dipaksa mengejar parity.

## Target Arsitektur

Target yang disarankan:

- `rafen-saas`: tetap jadi source of truth untuk tenant SaaS
- `rafen-selfhosted-next`: repo baru berbasis tenant SaaS
- `rafen-selfhosted` saat ini: dibekukan sebagai referensi sementara, bukan basis parity jangka panjang

Self-hosted baru sebaiknya:

- memakai controller, route, view, dan workflow tenant SaaS semaksimal mungkin
- tetap menyimpan model `owner_id` dan helper seperti `effectiveOwnerId()` untuk menghindari refactor besar
- menjalankan aplikasi sebagai single-tenant dengan satu owner aktif
- memakai lisensi sistem untuk menggantikan billing/subscription SaaS

## Prinsip Migrasi

### 1. Pertahankan kode tenant SaaS utuh selama mungkin

Jangan buru-buru menghapus pola multi-tenant dari level model/controller kalau itu hanya membuat diff membesar.

Yang lebih aman:

- tetap biarkan banyak model memakai `owner_id`
- tetap biarkan `User::effectiveOwnerId()` ada
- self-hosted cukup memastikan semua data mengarah ke owner tunggal

### 2. Ubah perilaku, bukan struktur, saat belum perlu

Contoh:

- `TenantMiddleware` untuk self-hosted bisa menjadi no-op atau selalu allow
- subdomain resolver bisa dimatikan tanpa membuang semua portal route
- fitur subscription bisa disembunyikan dari UI dan route, tanpa membongkar relasi model seluruhnya di fase awal

### 3. Layer khusus self-hosted harus tipis

Yang sebaiknya spesifik self-hosted:

- lisensi
- installer
- provisioning server
- bootstrap initial super admin
- penguncian fitur berdasarkan lisensi
- mekanisme update notice self-hosted

## Yang Dicopy Dari SaaS Sebagai Baseline

Prioritas copy dari repo SaaS:

- root Laravel skeleton:
  - `artisan`
  - `composer.json`
  - `composer.lock`
  - `package.json`
  - `package-lock.json`
  - `vite.config.js`
  - `config/`
  - `public/`
- app runtime tenant:
  - `app/Http/Controllers/`
  - `app/Models/`
  - `app/Services/`
  - `app/Jobs/`
  - `app/Console/Commands/`
  - `app/Http/Middleware/`
- tenant UI:
  - `resources/views/`
- tenant routes:
  - `routes/web.php`
  - `routes/console.php`

Intinya: baseline harus lahir dari SaaS terlebih dahulu, baru diberi penyesuaian self-hosted.

## Yang Dipertahankan / Ditarik Untuk Self-Hosted

Cluster self-hosted yang wajib ada di repo hasil rebase:

- installer server:
  - [`/var/www/rafen-selfhosted/install-selfhosted.sh`](/var/www/rafen-selfhosted/install-selfhosted.sh)
- lisensi sistem:
  - [`/var/www/rafen/app/Services/SystemLicenseService.php`](/var/www/rafen/app/Services/SystemLicenseService.php)
  - [`/var/www/rafen/app/Services/FeatureGateService.php`](/var/www/rafen/app/Services/FeatureGateService.php)
  - [`/var/www/rafen/app/Services/LicenseActivationRequestService.php`](/var/www/rafen/app/Services/LicenseActivationRequestService.php)
  - [`/var/www/rafen/app/Services/LicenseFingerprintService.php`](/var/www/rafen/app/Services/LicenseFingerprintService.php)
  - [`/var/www/rafen/app/Services/LicenseSignatureService.php`](/var/www/rafen/app/Services/LicenseSignatureService.php)
  - [`/var/www/rafen/app/Services/SelfHostedLicenseViewDataService.php`](/var/www/rafen/app/Services/SelfHostedLicenseViewDataService.php)
- middleware dan routes lisensi:
  - [`/var/www/rafen/app/Http/Middleware/EnsureValidSystemLicense.php`](/var/www/rafen/app/Http/Middleware/EnsureValidSystemLicense.php)
  - [`/var/www/rafen/app/Http/Middleware/EnsureSystemFeatureEnabled.php`](/var/www/rafen/app/Http/Middleware/EnsureSystemFeatureEnabled.php)
  - [`/var/www/rafen/routes/self_hosted_license.php`](/var/www/rafen/routes/self_hosted_license.php)
- model dan migration lisensi:
  - [`/var/www/rafen/app/Models/SystemLicense.php`](/var/www/rafen/app/Models/SystemLicense.php)
  - migration `system_licenses`
- command operasional lisensi:
  - `license:status`
  - `license:refresh`
  - `license:activation-request`

Daftar portabel resminya sudah disediakan oleh manifest SaaS.

## Yang Dimatikan Atau Dikeluarkan Dari Self-Hosted

Fitur berikut sebaiknya tidak menjadi bagian default self-hosted:

- registrasi tenant publik
- pembelian paket langganan tenant
- subscription billing SaaS
- wallet tenant
- withdrawal tenant
- manajemen tenant oleh super admin SaaS
- impersonation tenant
- cleanup expired trial tenant
- reminder subscription SaaS
- public payment page khusus subscription SaaS

Contoh file/area yang perlu dikeluarkan atau disembunyikan:

- `SubscriptionController`
- `SubscriptionPlanController`
- `TenantWalletController`
- `WithdrawalController`
- bagian tenant management di `SuperAdminController`
- route `subscription/*`
- route `wallet/*`
- route `super-admin/tenants/*`

Catatan:

- webhook WA tenant tidak otomatis dibuang, karena sebagian tetap relevan untuk self-hosted.
- payment gateway invoice pelanggan juga tidak otomatis dibuang; putuskan berdasarkan scope bisnis self-hosted.

## Multi-Tenant Menjadi Single-Tenant

Pendekatan yang disarankan:

- buat satu owner utama saat install pertama
- semua staff lokal menjadi `parent_id = owner_id` bila perlu
- semua data operasional tetap memakai `owner_id` owner tersebut
- `TenantMiddleware` cukup memastikan user valid, tanpa logic subscription SaaS
- `ResolveTenantFromSubdomain` bisa dimatikan jika deployment self-hosted tidak memakai subdomain tenant

Jangan lakukan refactor besar untuk menghapus `owner_id` di fase awal. Itu tidak memberi nilai parity, hanya menambah risiko.

## Workflow Cutover Yang Disarankan

### Fase 0: Bekukan Porting Incremental

- hentikan parity manual di repo self-hosted saat ini sebagai jalur utama
- jangan habiskan waktu untuk polish UI repo lama jika baseline akan diganti

### Fase 1: Bentuk Candidate Repo Dari SaaS

Jalankan di repo SaaS:

```bash
php artisan self-hosted:manifest
php artisan self-hosted:cutover-plan
php artisan self-hosted:materialize-repo /tmp/rafen-selfhosted-next --force
php artisan self-hosted:stage /tmp/rafen-selfhosted-stage --force
```

Hasilnya:

- candidate repo skeleton
- bundle `portable/`
- `references/` untuk touchpoint manual
- manifest JSON yang menjelaskan cluster self-hosted

### Fase 2: Overlay Baseline Tenant SaaS

Tujuan fase ini:

- candidate repo memakai seluruh shell aplikasi tenant SaaS
- fitur self-hosted menempel di atas baseline itu

Yang dilakukan:

- mulai dari candidate repo hasil materialize
- overlay kode tenant SaaS yang memang ingin dibawa utuh
- merge cluster lisensi self-hosted dari staging manifest

### Fase 3: Patch Single-Tenant Adapter

Patch minimum:

- sederhanakan `TenantMiddleware`
- matikan route subscription/tenant commerce
- sembunyikan menu SaaS-only
- set seed owner tunggal
- arahkan login default ke dashboard self-hosted

### Fase 4: Bawa Installer Dan Provisioning

Komponen dari repo self-hosted lama yang dibawa:

- `install-selfhosted.sh`
- dokumentasi provisioning
- validasi PHP 8.4 / Node 22
- create user `deploy`
- ownership dan flow deploy production

### Fase 5: Smoke Test

Minimal yang harus lolos:

- login admin
- dashboard
- PPP users
- Hotspot users
- invoice & payment
- outage
- ODP / CPE / OLT
- portal pelanggan
- upload lisensi
- `license:status`

## Urutan Kerja Paling Cepat

Kalau fokusnya kecepatan, urutan paling efektif adalah:

1. buat repo candidate dari SaaS
2. pasang cluster lisensi self-hosted dari manifest SaaS
3. nonaktifkan fitur SaaS-only
4. sambungkan installer self-hosted
5. lakukan smoke test
6. baru setelah itu rapikan detail branding atau edge-case

Jangan mulai dari:

- menyalin satu controller
- menyalin satu halaman
- memoles sidebar repo lama

Itu membuat parity lambat dan mahal.

## Keputusan Teknis Yang Saya Rekomendasikan

- Repo self-hosted lama jangan dijadikan basis akhir.
- Baseline baru harus berasal dari repo SaaS tenant.
- Multi-tenant tidak perlu dihapus total di fase awal.
- Lisensi sistem menjadi pengganti billing SaaS, bukan patch tambahan belakangan.
- Manual parity checklist tetap berguna, tetapi hanya sebagai alat verifikasi setelah rebase, bukan strategi implementasi utama.

## Deliverable Yang Sebaiknya Dibuat Berikutnya

Setelah blueprint ini disetujui, langkah paling bernilai adalah:

1. buat branch atau direktori candidate `self-hosted-next` dari repo SaaS
2. jalankan command `self-hosted:materialize-repo` dan `self-hosted:stage`
3. buat daftar final:
   - `copy as-is`
   - `disable in self-hosted`
   - `keep from current self-hosted`
4. baru mulai coding di baseline baru

## Status Dokumen

Status blueprint ini:

- direkomendasikan sebagai jalur utama migrasi
- parity checklist lama tetap dipakai sebagai alat audit hasil akhir
- porting incremental pada repo self-hosted saat ini dianggap jalur sementara, bukan target final
