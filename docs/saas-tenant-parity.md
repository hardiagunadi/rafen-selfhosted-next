# SaaS Tenant Parity Checklist

> Status strategi: checklist ini tetap berguna sebagai alat audit parity, tetapi bukan lagi strategi implementasi utama. Untuk jalur yang lebih cepat dan stabil, gunakan blueprint rebase di [docs/self-hosted-rebase-blueprint.md](/var/www/rafen-selfhosted-next/docs/self-hosted-rebase-blueprint.md).

Target self-hosted:

- Pengalaman operasional harus mengikuti tenant pada repo SaaS semaksimal mungkin.
- Perbedaan hanya boleh terjadi pada fitur yang memang sengaja tidak dibawa ke self-hosted.
- Jika sebuah fitur SaaS tidak dibawa, alasannya harus eksplisit.

## Prinsip

- Menu, grouping, istilah, dan urutan navigasi mengikuti tenant SaaS.
- Pola halaman mengikuti tenant SaaS:
  - judul halaman
  - subjudul/deskripsi
  - card layout
  - form layout
  - aksi tabel
  - create/edit/show flow
- Route tambahan pendukung UX SaaS juga perlu dibawa jika mempengaruhi operasional:
  - `datatable`
  - `autocomplete`
  - `show`
  - `edit`
  - `bulk-*`
  - aksi lanjutan seperti `disconnect`, `toggle-status`, `send-wa`, dan sejenisnya

## Kategori Keputusan

Supaya audit parity tidak rancu, setiap perubahan SaaS sebaiknya selalu diberi salah satu label berikut:

- `Bawa ke Self-Hosted`: fitur bisa dibawa hampir utuh karena tidak bergantung pada model SaaS multi-tenant atau control plane pusat
- `Adaptasi untuk Self-Hosted`: ide dan UX-nya relevan, tetapi implementasinya harus disesuaikan dengan arsitektur self-hosted
- `Khusus SaaS`: fitur tetap tinggal di SaaS karena concern-nya memang milik operator platform pusat, bukan instance self-hosted tunggal

Aturan praktis saat menilai perubahan:

- jika fitur menyentuh operator harian instance, customer flow, tiket, billing lokal, atau tool admin lokal, default-nya `Bawa ke Self-Hosted`
- jika fitur menyentuh observability, update, atau monitoring lintas instance tetapi masih punya manfaat lokal, default-nya `Adaptasi untuk Self-Hosted`
- jika fitur menyentuh tenant index lintas customer, orphan management, billing platform, atau policy yang hanya masuk akal dari pusat, default-nya `Khusus SaaS`

## Rencana Perubahan Terbaru Dari SaaS

Snapshot ini disusun dari commit SaaS terbaru yang masih relevan untuk self-hosted per `2026-04-21`. Fokusnya bukan menyalin semua perubahan SaaS, tetapi membawa perubahan yang:

- langsung meningkatkan UX/operator flow self-hosted
- sudah punya touchpoint setara di repo self-hosted
- tidak bergantung pada control plane multi-instance milik SaaS

### Update Implementasi Terbaru Di Self-Hosted

Status per `2026-04-21`:

- `Bawa ke Self-Hosted`: `voucher send-wa` dari daftar voucher sudah masuk
- `Bawa ke Self-Hosted`: tenant setting sudah mendukung pemilihan provider `local` vs `ycloud`
- `Bawa ke Self-Hosted`: pengiriman WhatsApp invoice dan voucher via `YCloud` sudah masuk
- `Adaptasi untuk Self-Hosted`: inbox `WA Chat` sekarang sudah mengenali conversation `provider=local|ycloud`
- `Adaptasi untuk Self-Hosted`: webhook inbound `YCloud` sudah membuat `wa_conversations` dan `wa_chat_messages` sendiri tanpa mencampur flow gateway lokal
- `Adaptasi untuk Self-Hosted`: reply manual dari `WA Chat` sekarang sudah bisa mengirim lewat `YCloud`, termasuk reply gambar
- `Adaptasi untuk Self-Hosted`: jalur gateway lokal tetap dipertahankan sebagai provider terpisah, jadi parity tidak menghapus flow self-hosted yang sudah stabil

Catatan batas implementasi saat ini:

- `Khusus SaaS / Belum Dibawa`: orchestration channel manager penuh milik SaaS belum dipindahkan, karena di SaaS ia juga mengikat policy cost, audit logger, dan routing hybrid
- `Adaptasi untuk Self-Hosted`: inbound media hydration/download ala `YCloudInboundMediaService` SaaS belum dibawa penuh; self-hosted saat ini fokus pada sinkronisasi pesan, status, dan reply operasional
- `Adaptasi untuk Self-Hosted`: auto-reply bot untuk inbound `YCloud` belum disamakan penuh dengan SaaS karena flow bot self-hosted saat ini masih terikat ke jalur gateway lokal lama

### Prioritas 1: Stabilkan modal tiket manual

Label keputusan:

- `Bawa ke Self-Hosted`

Sumber SaaS:

- `f015d0b` pada `2026-04-21` (`fix tiket`)

Kenapa relevan:

- self-hosted sudah memiliki halaman tiket WhatsApp dengan modal create ticket yang struktur dan script-nya masih mengikuti pola lama
- patch SaaS memperbaiki konflik `Bootstrap modal` dan `Select2`, terutama saat operator memilih pelanggan di dalam modal

Target implementasi self-hosted:

- `resources/views/wa-chat/tickets.blade.php`
- `tests/Feature/WaTicketTest.php`

Ruang lingkup yang disarankan:

- ubah modal menjadi `data-backdrop="static"` dan `data-keyboard="false"`
- tandai select pelanggan dengan `data-native-select="true"`
- inisialisasi `Select2` saat modal benar-benar terbuka, bukan hanya saat halaman dimuat
- hentikan propagasi event mouse/touch di area dropdown agar modal tidak ikut tertutup
- tambah test yang memastikan markup dan behavior defensif modal tetap ada

Hasil yang diharapkan:

- operator bisa membuat tiket manual tanpa modal tertutup sendiri saat memilih pelanggan
- parity UX tiket dengan SaaS kembali sejajar

### Prioritas 1: Bawa aksi kirim kode voucher ke WhatsApp

Label keputusan:

- `Bawa ke Self-Hosted`

Sumber SaaS:

- `1ae70bb` pada `2026-04-21` (`add send vc to wa`)

Kenapa relevan:

- self-hosted sudah punya modul voucher, invoice `send-wa`, dan pondasi integrasi WhatsApp
- gap parity saat ini ada di voucher: operator belum bisa mengirim kode voucher langsung ke WhatsApp dari daftar voucher

Target implementasi self-hosted:

- `app/Http/Controllers/VoucherController.php`
- `app/Http/Requests/SendVoucherWaRequest.php`
- `resources/views/vouchers/index.blade.php`
- `routes/web.php`
- `tests/Feature/VoucherSendWaTest.php`

Ruang lingkup yang disarankan:

- tambah endpoint `vouchers.send-wa`
- tampilkan tombol aksi WhatsApp hanya untuk voucher `unused`
- pakai provider yang benar-benar tersedia di repo self-hosted saat ini
- untuk kondisi codebase sekarang, jalur yang aman adalah reuse `WaGatewayService` yang sudah dipakai invoice dan tiket
- catat audit ke log aktivitas / log WA seperti flow `invoice send-wa`
- validasi role dan isolasi `owner_id` mengikuti pola SaaS

Hasil yang diharapkan:

- distribusi voucher jauh lebih cepat untuk operator lapangan dan CS
- reuse stack WhatsApp yang sudah ada, jadi effort implementasinya relatif rendah

### Prioritas 2: Rapikan dashboard agar mengikuti SaaS terbaru

Label keputusan:

- `Bawa ke Self-Hosted`

Sumber SaaS:

- `4e73074` pada `2026-04-21` (`fix dashboard`)

Kenapa relevan:

- self-hosted masih menampilkan panel `Informasi Layanan` dan logic `serviceInfo` lama
- SaaS terbaru sudah menyederhanakan dashboard dan menghapus panel tersebut untuk semua role

Target implementasi self-hosted:

- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php`
- `tests/Feature/FinanceAccessTest.php`

Ruang lingkup yang disarankan:

- hapus dependensi `serviceInfo` dari controller dan view
- sinkronkan test agar memastikan panel `Informasi Layanan` memang tidak tampil lagi, termasuk untuk administrator
- pertahankan statistik inti yang masih relevan untuk operasi self-hosted

Hasil yang diharapkan:

- dashboard lebih konsisten dengan SaaS
- beban visual berkurang dan area fokus operator menjadi lebih jelas

### Prioritas 3: Ambil pola notifikasi heartbeat yang relevan, jangan port halaman SaaS mentah-mentah

Label keputusan:

- `Adaptasi untuk Self-Hosted`

Sumber SaaS:

- `91f045f` pada `2026-04-21` (`fix notif dan monitoring SH`)

Kenapa hanya sebagian relevan:

- commit SaaS ini berfokus pada monitor banyak instance self-hosted dari sisi control plane SaaS
- self-hosted tidak perlu membawa halaman multi-instance, orphan cleanup, atau daftar tenant yang memang khusus SaaS
- yang tetap relevan adalah pola ringkasan status heartbeat, stale indicator, dan notifikasi operasional yang lebih tegas

Target implementasi self-hosted:

- `app/Http/Controllers/SuperAdminAppUpdateController.php`
- `app/Services/SelfHostedHeartbeatService.php`
- `resources/views/super-admin/settings/app-update.blade.php`
- `resources/views/layouts/admin.blade.php`
- `tests/Feature/SuperAdminAppUpdateFeatureTest.php`

Ruang lingkup yang disarankan:

- sinkronkan wording status heartbeat dan indikator stale agar operator self-hosted cepat tahu kapan sinkronisasi terakhir berhasil
- tampilkan summary update / heartbeat yang lebih mudah dipindai di halaman update aplikasi
- bila perlu, tambahkan ringkasan notifikasi ringan di layout admin self-hosted, tetapi jangan membawa tabel monitor multi-instance milik SaaS

Hasil yang diharapkan:

- self-hosted mendapat manfaat observability yang sama tanpa membawa kompleksitas control plane SaaS

### Yang Tidak Perlu Dipaksa Ikut

Label keputusan:

- `Khusus SaaS`

- halaman `Super Admin -> Self-Hosted Instances` di SaaS
- penghapusan orphan instance dari sisi SaaS
- notifikasi yang bergantung pada daftar tenant atau fingerprint lintas instance

Alasannya:

- fitur-fitur ini adalah concern operator SaaS, bukan operator instance self-hosted tunggal

### Urutan Eksekusi Yang Disarankan

1. tiket manual modal hardening
2. voucher `send-wa`
3. dashboard cleanup
4. adopsi wording dan summary heartbeat yang relevan

Urutan ini dipilih karena:

- dua item pertama punya dampak operasional langsung
- dashboard cleanup rendah risiko
- heartbeat summary lebih aman dikerjakan setelah parity UI utama tidak mengganggu flow harian

## Sudah Diakomodasi

- Dashboard admin self-hosted
- Session PPPoE/Hotspot
- Pelanggan PPP/Hotspot
- Voucher
- Peta pelanggan
- ODP
- MikroTik
- OLT
- CPE
- Profile/paket
- Invoice/pembayaran
- FreeRADIUS
- GenieACS
- WireGuard
- WhatsApp, WA Blast, WA Chat, tiket WA
- Gangguan
- Shift
- Pengguna
- Profil sistem
- Lisensi sistem
- Log aktivitas
- Tools sistem
- Terminal
- Bantuan

## Audit Parity Saat Ini

Bagian ini menggantikan daftar `gap` lama yang murni berbasis selisih route. Setelah dicek ulang terhadap `routes/web.php` self-hosted, banyak item di daftar lama ternyata sudah ada dan tidak layak lagi ditulis sebagai gap aktif.

Cara membaca status audit:

- `Parity route sudah ada`: route dan cluster fitur inti sudah aktif di self-hosted, jadi fokus berikutnya adalah behavior, UX, atau edge case
- `Gap perilaku/UX tersisa`: route dasarnya ada, tetapi masih ada tindakan lanjutan, wording, tampilan, atau flow SaaS yang belum sepenuhnya sejajar
- `Pengecualian resmi`: fitur memang tetap tinggal di SaaS atau wajib diadaptasi karena konteks self-hosted berbeda

### Dashboard

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `dashboard.api`, `dashboard.api.data`, `dashboard.api.menu`, `dashboard.api.traffic`
- aksi dashboard untuk hotspot aktif, PPP aktif, PPP secret, PPPoE server, dan IP binding

Gap perilaku/UX tersisa:

- penyamaan wording, layout, dan kartu ringkasan bila SaaS membawa konteks tenant platform yang tidak relevan untuk instance tunggal
- review kecil berkala pada dashboard agar perubahan SaaS yang murni UX tidak tertinggal

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk flow dashboard inti
- `Adaptasi untuk Self-Hosted` untuk copywriting atau kartu yang bersifat control plane

### PPP Users

Status audit:

- `Parity route sudah ada` untuk CRUD dan aksi utama

Route parity yang sudah tervalidasi:

- `ppp-users.create`, `ppp-users.edit`, `ppp-users.show`
- `ppp-users.datatable`, `ppp-users.autocomplete`
- `ppp-users.bulk-destroy`, `ppp-users.disconnect`, `ppp-users.toggle-status`
- `ppp-users.add-invoice`, `ppp-users.invoice-datatable`, `ppp-users.nota-aktivasi`

Gap perilaku/UX tersisa:

- evaluasi apakah `nota-layanan` dari SaaS perlu dibawa sebagai `Adaptasi untuk Self-Hosted`
- polishing pada layar detail dan aksi turunan bila ada commit SaaS baru yang memperjelas flow teknisi atau CS

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk operasional PPP inti
- `Adaptasi untuk Self-Hosted` untuk service note / `nota-layanan` bila memang masih relevan di produk self-hosted

### Hotspot Users

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `hotspot-users.create`, `hotspot-users.edit`, `hotspot-users.show`
- `hotspot-users.datatable`, `hotspot-users.autocomplete`
- `hotspot-users.bulk-destroy`, `hotspot-users.toggle-status`, `hotspot-users.renew`

Gap perilaku/UX tersisa:

- sinkronisasi UX dan edge case jika ada perbedaan modul hotspot aktif/nonaktif atau kebijakan lisensi modul

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk flow hotspot inti
- `Adaptasi untuk Self-Hosted` hanya jika ada ketergantungan modul yang memang berbeda

### Invoice & Payment

Status audit:

- `Parity route sudah ada` untuk invoice dan pembayaran pelanggan lokal
- `Pengecualian resmi` untuk subscription billing milik SaaS

Route parity yang sudah tervalidasi:

- `invoices.datatable`, `invoices.nota`, `invoices.nota-bulk`, `invoices.print`, `invoices.send-wa`
- pending payment, manual confirmation, create/store payment untuk invoice, dan payment status check
- portal bayar pelanggan invoice

Gap perilaku/UX tersisa:

- payment callback publik tetap relevan, tetapi perlu ditinjau per provider yang benar-benar dipakai pada deploy self-hosted

Pengecualian resmi:

- subscription payment callback dan public subscription payment page tetap `Khusus SaaS`

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk invoice/payment lokal
- `Adaptasi untuk Self-Hosted` untuk callback payment provider
- `Khusus SaaS` untuk billing subscription platform

### CPE / GenieACS

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `cpe.datatable`, `cpe.show`, `cpe.info`, `cpe.search-ppp-users`
- `cpe.bulk-auto-link`, `cpe.unlinked*`
- `cpe.update-mac`, `cpe.wan-*`, `cpe.wifi-by-index`
- trafik dan riwayat detail CPE

Gap perilaku/UX tersisa:

- penyamaan data detail, alarm, dan UX inspeksi bila SaaS menambah helper operasional baru
- penyesuaian ketika fitur ikut bergantung pada modul OLT atau GenieACS yang bisa dimatikan lewat lisensi sistem

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk cluster CPE inti
- `Adaptasi untuk Self-Hosted` untuk bagian yang dikontrol fitur lisensi

### ODP

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `odps.create`, `odps.edit`, `odps.show`, `odps.datatable`, `odps.autocomplete`

Gap perilaku/UX tersisa:

- konsistensi form, show page, dan autocomplete bila SaaS melakukan penyederhanaan layout atau validasi

Keputusan saat ini:

- `Bawa ke Self-Hosted`

### Outage

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `outages.create`, `outages.edit`, `outages.datatable`, `outages.assign`
- `outages.blast`, `outages.test-blast`
- `outages.affected-users`, `outages.affected-users-preview`
- publik status outage dan broadcast pelanggan

Gap perilaku/UX tersisa:

- template blast, preview affected users, dan pembatasan kapasitas gateway bila perilaku SaaS berubah

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk flow outage inti
- `Adaptasi untuk Self-Hosted` untuk blast dan segmentasi bila tergantung kapasitas gateway lokal

### Logs

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `logs.activity.data`
- `logs.login*`, `logs.radius-auth*`, `logs.bg-process*`
- `logs.genieacs*`, `logs.wa-blast*`, `logs.wa-pengiriman*`

Gap perilaku/UX tersisa:

- summary atau export tambahan untuk log WA, alarm OLT, atau view agregasi yang benar-benar bernilai untuk operator instance lokal

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk log operasional tenant
- `Adaptasi untuk Self-Hosted` untuk summary tambahan yang belum tentu wajib

### Tools

Status audit:

- `Parity route sudah ada`, tetapi banyak item perlu audit perilaku yang lebih hati-hati

Route parity yang sudah tervalidasi:

- export users, export transactions, import tools, usage monitoring
- backup dan restore
- reset report
- reset database

Gap perilaku/UX tersisa:

- pembatasan izin, guardrail, dan copy konfirmasi untuk aksi yang menyentuh data server customer langsung
- audit ulang tool destruktif agar tidak diasumsikan aman hanya karena route-nya sudah ada

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk export/import dan usage monitoring
- `Adaptasi untuk Self-Hosted` untuk backup/restore, reset report, dan terutama reset database

### Portal Pelanggan

Status audit:

- `Parity route sudah ada`

Route parity yang sudah tervalidasi:

- `portal.dashboard`, `portal.invoices`, `portal.account`, `portal.change-password`
- `portal.traffic`, `portal.wifi.update`
- `portal.push.subscribe`, `portal.push.unsubscribe`
- `portal.tickets.store`

Gap perilaku/UX tersisa:

- branding portal dan mekanisme identifikasi tenant/subdomain bila pola domain self-hosted berbeda dari SaaS

Keputusan saat ini:

- `Bawa ke Self-Hosted` untuk flow portal inti
- `Adaptasi untuk Self-Hosted` untuk branding dan identifikasi domain

## Fitur Yang Perlu Keputusan Eksplisit

Jika fitur-fitur ini tidak ikut ke self-hosted, perlu dicatat sebagai pengecualian resmi:

Label default:

- `Khusus SaaS` atau `Adaptasi untuk Self-Hosted`, tergantung dependensi aktualnya

- integrasi payment callback pihak ketiga
- public payment page tertentu
- branding preview
- beberapa webhook publik SaaS
- fitur yang bergantung pada model multi-tenant SaaS

Audit keputusan saat ini:

- `Khusus SaaS`:
  subscription billing SaaS, tenant wallet, withdrawal tenant, dan halaman pembayaran subscription publik
- `Adaptasi untuk Self-Hosted`:
  payment callback pihak ketiga untuk invoice pelanggan, karena masih relevan tetapi perlu dinilai per provider
- `Adaptasi untuk Self-Hosted`:
  branding preview, bila yang diuji adalah branding lokal instance; bila preview terkait katalog tenant SaaS, tetap `Khusus SaaS`
- `Khusus SaaS`:
  webhook atau endpoint yang butuh konteks tenant platform pusat, bukan tenant lokal instance

## Langkah Implementasi Yang Disarankan

1. Samakan shell UX:
   menu, grouping, treeview, active state, breadcrumbs, judul halaman.
2. Samakan flow CRUD utama:
   PPP, Hotspot, Invoice, Payment, ODP, Outage, CPE.
3. Samakan fitur pendukung operasional:
   datatable, autocomplete, bulk action, nota/print, WA helper.
4. Samakan portal pelanggan.
5. Tandai fitur yang sengaja dikecualikan beserta alasan teknis atau bisnisnya.

## Catatan Audit

Setiap kali ada commit baru dari SaaS, jangan langsung tulis "port ke self-hosted".
Tulis dulu keputusan eksplisitnya dalam format singkat berikut:

- `Keputusan`: Bawa ke Self-Hosted / Adaptasi untuk Self-Hosted / Khusus SaaS
- `Alasan`: kenapa keputusan itu diambil
- `Touchpoint`: file atau modul self-hosted yang terdampak
- `Batasan`: bagian mana dari commit SaaS yang tidak ikut
