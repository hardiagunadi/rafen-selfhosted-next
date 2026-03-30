# SaaS Tenant Parity Checklist

> Status strategi: checklist ini tetap berguna sebagai alat audit parity, tetapi bukan lagi strategi implementasi utama. Untuk jalur yang lebih cepat dan stabil, gunakan blueprint rebase di [docs/self-hosted-rebase-blueprint.md](/var/www/rafen-selfhosted/docs/self-hosted-rebase-blueprint.md).

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

## Gap Parity Yang Terdeteksi

Berikut gap awal dari perbandingan route tenant SaaS terhadap self-hosted. Ini belum berarti semua harus dibawa apa adanya, tetapi default-nya adalah dibawa kecuali ada alasan eksplisit untuk tidak ikut.

### Dashboard

- `dashboard.api`
- `dashboard.api.data`
- `dashboard.api.menu`
- `dashboard.api.traffic`
- aksi dashboard untuk hotspot aktif, PPP aktif, PPP secret, PPPoE server, dan IP binding

### PPP Users

- `ppp-users.create`
- `ppp-users.edit`
- `ppp-users.show`
- `ppp-users.datatable`
- `ppp-users.autocomplete`
- `ppp-users.bulk-destroy`
- `ppp-users.disconnect`
- `ppp-users.toggle-status`
- `ppp-users.add-invoice`
- `ppp-users.invoice-datatable`
- `ppp-users.nota-aktivasi`

### Hotspot Users

- `hotspot-users.create`
- `hotspot-users.edit`
- `hotspot-users.show`
- `hotspot-users.datatable`
- `hotspot-users.autocomplete`
- `hotspot-users.bulk-destroy`
- `hotspot-users.toggle-status`
- `hotspot-users.renew`

### Invoice & Payment

- `invoices.datatable`
- `invoices.nota`
- `invoices.nota-bulk`
- `invoices.print`
- `invoices.send-wa`
- pending payment dan manual confirmation flow
- create/store payment untuk invoice
- payment status check

### CPE / GenieACS

- `cpe.datatable`
- `cpe.show`
- `cpe.info`
- `cpe.search-ppp-users`
- `cpe.bulk-auto-link`
- `cpe.unlinked*`
- `cpe.update-mac`
- `cpe.wan-*`
- `cpe.wifi-by-index`
- trafik dan riwayat yang lebih detail

### ODP

- `odps.create`
- `odps.edit`
- `odps.show`
- `odps.datatable`
- `odps.autocomplete`

### Outage

- `outages.create`
- `outages.edit`
- `outages.datatable`
- `outages.assign`
- `outages.blast`
- `outages.test-blast`
- `outages.affected-users`
- `outages.affected-users-preview`

### Logs

- `logs.activity.data`
- `logs.login*`
- `logs.radius-auth*`
- `logs.bg-process*`
- `logs.genieacs*`
- `logs.wa-blast*`
- `logs.wa-pengiriman*`

### Tools

- export users
- import tools
- reset database
- reset report
- usage monitoring

### Portal Pelanggan

- `portal.dashboard`
- `portal.invoices`
- `portal.account`
- `portal.change-password`
- `portal.traffic`
- `portal.wifi.update`
- `portal.push.subscribe`
- `portal.push.unsubscribe`
- `portal.tickets.store`

## Fitur Yang Perlu Keputusan Eksplisit

Jika fitur-fitur ini tidak ikut ke self-hosted, perlu dicatat sebagai pengecualian resmi:

- integrasi payment callback pihak ketiga
- public payment page tertentu
- branding preview
- beberapa webhook publik SaaS
- fitur yang bergantung pada model multi-tenant SaaS

## Langkah Implementasi Yang Disarankan

1. Samakan shell UX:
   menu, grouping, treeview, active state, breadcrumbs, judul halaman.
2. Samakan flow CRUD utama:
   PPP, Hotspot, Invoice, Payment, ODP, Outage, CPE.
3. Samakan fitur pendukung operasional:
   datatable, autocomplete, bulk action, nota/print, WA helper.
4. Samakan portal pelanggan.
5. Tandai fitur yang sengaja dikecualikan beserta alasan teknis atau bisnisnya.
