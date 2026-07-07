# Rencana Pengembangan

> Daftar rencana pengembangan yang **sudah diputuskan** dan akan / sedang dikerjakan —
> lengkap dengan keputusan desain yang sudah dikunci, skema, dan langkah implementasi.
>
> Bedakan dengan [`CATATAN-FITUR.md`](CATATAN-FITUR.md): file itu adalah **backlog ide**
> ("kalau suatu saat mau, begini caranya"). File ini adalah **rencana aktif** — sudah dibahas,
> keputusan sudah diambil, tinggal eksekusi. Saat sebuah rencana selesai dikerjakan, pindahkan
> ringkasannya ke bagian "Arsip" di bawah.

**Keterangan status:**

| Simbol          | Arti                                                           |
| --------------- | -------------------------------------------------------------- |
| 📋 Direncanakan | Keputusan sudah dikunci, belum mulai koding                    |
| 💡 Ide ringan   | Sudah dibahas tapi belum dikunci penuh / menunggu rencana lain |
| 🔨 Dikerjakan   | Sedang dalam pengerjaan                                        |
| ✅ Selesai      | Sudah rampung (lihat Arsip)                                    |
| 📱 Android      | Perlu perubahan di repo Android (terpisah dari repo ini)       |

---

## Daftar Isi

1. [Modul Pelanggan (Customers)](#1-modul-pelanggan-customers) ✅ 📱 — **Selesai** (ringkasan di [Arsip](#arsip-rencana-yang-sudah-selesai))
2. [Catatan & bukti pekerjaan teknisi](#2-catatan--bukti-pekerjaan-teknisi) ✅ 📱 — **Selesai** (ringkasan di [Arsip](#arsip-rencana-yang-sudah-selesai))
3. [Dashboard analitik & filter laporan](#3-dashboard-analitik--filter-laporan) ✅ — **Selesai** (ringkasan di [Arsip](#arsip-rencana-yang-sudah-selesai))
4. [Pendaftaran pelanggan oleh teknisi saat pemasangan baru](#4-pendaftaran-pelanggan-oleh-teknisi-saat-pemasangan-baru) 💡 📱
5. [CRUD Jenis Kerusakan (damage_types)](#5-crud-jenis-kerusakan-damage_types) ✅ — **Selesai** (ringkasan di [Arsip](#arsip-rencana-yang-sudah-selesai))
6. [Metadata laporan: asal komplain & jadwal kunjungan](#6-metadata-laporan-asal-komplain--jadwal-kunjungan) 📋
7. [Peningkatan peta Monitoring & Dashboard](#7-peningkatan-peta-monitoring--dashboard) 🔨 — opsi A & C ✅ + foto bukti live ✅, B menyusul
8. [Audit log aktivitas admin](#8-audit-log-aktivitas-admin) 📋

> **Urutan eksekusi yang disarankan:** ~~#1~~ ✅ → ~~#2~~ ✅ → ~~#3~~ ✅ → #4 (terakhir, menunggu dosen).
> **#1 SUDAH SELESAI** (lihat Arsip) — fondasi `customers`/`ReportCategory` yang dirujuk #3 & #4
> kini tersedia, jadi **#3a** (filter/pecahan per kategori) & **#4** tak lagi terblokir olehnya.
> **~~#5~~ ✅ SUDAH SELESAI** (2026-06-28, lihat Arsip) — CRUD jenis gangguan + jadikan
> `damage_type_id` nullable/`nullOnDelete` (opsional untuk non-pelanggan). **#6** dulu diharapkan
> membonceng migrasi `damage_reports` di #1; karena #1 sudah rampung, #6 kini butuh **migrasi
> sendiri** (kolom `report_source`/`reported_at`/`scheduled_at`).

---

## 1. Modul Pelanggan (Customers) ✅ 📱

**Status:** ✅ **SELESAI** (web 2026-06-27, Android 2026-06-28) — diimplementasi penuh termasuk
**Fase 2 Android** (upload foto rumah dari lapangan via `POST /api/tasks/{id}/house-photos` +
detail tugas adaptif per kategori). Ringkasan as-built ada di [Arsip](#arsip-rencana-yang-sudah-selesai)
di bawah. Detail desain berikut ini dipertahankan sebagai **catatan historis** (apa yang diputuskan
& dibangun).

### Latar belakang

Hasil review dosen pembimbing: aplikasi **kurang data pelanggan** sehingga admin tidak bisa
melihat siapa pelanggan yang komplain. Saat ini
[`damage_reports`](database/migrations/2026_06_05_145704_create_damage_reports_table.php) hanya
menyimpan `customer_name` + `address` sebagai **teks bebas** yang diketik ulang tiap laporan —
pelanggan bukan entitas, jadi tak ada riwayat/kontak/identitas pelanggan.

Rencana ini menjadikan pelanggan **entitas tersendiri** (`customers`), laporan menunjuk ke
pelanggan via FK. Sekaligus menggantikan ide kecil **#8 Kontak pelanggan** di
[`CATATAN-FITUR.md`](CATATAN-FITUR.md) (nomor telepon kini jadi bagian dari tabel pelanggan).

### Keputusan yang sudah dikunci

1. **Pelanggan wajib HANYA untuk laporan kategori "Pelanggan".** Laporan punya **kategori**
   (lihat #9). Untuk kategori Pelanggan → wajib pilih pelanggan terdaftar (search-select), bukan
   teks bebas. Untuk kategori Jaringan/Pemeliharaan → tanpa pelanggan, pakai judul + lokasi.
2. **Snapshot dipertahankan.** `damage_reports` dapat kolom `customer_id` (FK), **tapi**
   `customer_name`/`address` tetap disimpan sebagai snapshot saat laporan dibuat — supaya riwayat
   & PDF tidak berubah kalau data pelanggan kelak diperbarui/pindah alamat. Konsisten dengan pola
   jaga-riwayat yang sudah ada (`nullOnDelete`).
3. **FK `nullOnDelete`.** Hapus pelanggan tidak menghapus laporan; `customer_id` jadi NULL,
   snapshot tetap tampil. Selaras prinsip "hapus data master tak menghapus arsip".
4. **Kode pelanggan → `ip_address`.** SkyNet tidak punya ID pelanggan formal, tapi tiap pelanggan
   punya IP yang di-assign di jaringan. Lebih realistis & relevan untuk aplikasi monitoring
   jaringan (saat komplain, langsung tahu IP yang bermasalah).
5. **Koordinat GPS nullable.** Data titik rumah pelanggan belum tersedia → `latitude`/`longitude`
   boleh kosong. Struktur disiapkan; bisa diisi belakangan (klik di peta) lalu otomatis muncul di
   monitoring.
6. **Migrasi data lama: auto-buat pelanggan.** Script migrasi membuat satu pelanggan untuk tiap
   kombinasi `customer_name`+`address` unik di laporan lama, lalu mengisi `customer_id`. IP/paket
   dikosongkan (data lama tak punya), admin lengkapi kemudian.
7. **Foto rumah pelanggan (beberapa foto).** Karena wilayah pelanggan = perkampungan, alamat sering
   tidak presisi. Foto rumah + patokan (gang, warna pagar) jadi alat bantu teknisi menemukan lokasi —
   melengkapi koordinat GPS yang nullable. Beberapa foto per pelanggan → tabel `customer_photos`.
8. **Pengunggah foto: bertahap.** Skema disiapkan untuk admin **maupun** teknisi (kolom
   `uploaded_by`), tapi eksekusi bertahap — **Fase 1: admin via web** (masuk modul ini);
   **Fase 2: teknisi via Android** (tinggal tambah 1 endpoint upload, tanpa ubah skema).
9. **Kategori laporan (3 jenis).** Tidak semua pekerjaan milik satu pelanggan. Laporan dapat kolom
   `category` (enum `App\Enums\ReportCategory`): **`pelanggan`** (Gangguan Pelanggan — butuh
   pelanggan), **`jaringan`** (Gangguan Jaringan/Infrastruktur — kabel utama putus, dll),
   **`pemeliharaan`** (Pemeliharaan/preventif — server, perawatan rutin). Default `pelanggan`
   (data lama semua = komplain pelanggan). Untuk non-pelanggan: isi **judul** + **lokasi/area
   terdampak** sebagai teks, `customer_id` NULL.
10. **Ekspor daftar pelanggan: PDF + Excel, ikut filter aktif.** Daftar pelanggan = data master →
    bisa diekspor **PDF** (arsip/cetak resmi) **dan Excel** (diolah admin). Ekspor mengikuti
    search & filter status yang sedang aktif (konsisten dengan ekspor Riwayat). Jalur PDF ikut pola
    `ReportExportController` + dompdf (tanpa paket baru); jalur Excel pakai paket baru
    **`maatwebsite/excel`**.

### Skema tabel `customers`

| Kolom                  | Tipe                             | Wajib           | Catatan                                      |
| ---------------------- | -------------------------------- | --------------- | -------------------------------------------- |
| `id`                   | bigint PK                        | ✅              |                                              |
| `name`                 | string                           | ✅              | Nama pelanggan                               |
| `phone`                | string                           | ✅              | No. HP / WhatsApp                            |
| `address`              | string                           | ✅              | Alamat pemasangan                            |
| `ip_address`           | string nullable                  | —               | IP yang di-assign (pengganti kode pelanggan) |
| `subscription_package` | string nullable                  | —               | Paket internet (mis. 10 Mbps)                |
| `status`               | enum `aktif`/`isolir`/`berhenti` | default `aktif` | Status langganan                             |
| `latitude`             | decimal nullable                 | —               | Titik rumah (diisi belakangan)               |
| `longitude`            | decimal nullable                 | —               | Titik rumah (diisi belakangan)               |
| `installed_at`         | date nullable                    | —               | Tanggal pasang                               |
| `timestamps`           |                                  | ✅              |                                              |

Enum status pelanggan diangkat ke `App\Enums\CustomerStatus` (ikut pola `ReportStatus`/`UserRole` —
sumber kebenaran, hindari literal).

### Foto rumah pelanggan (tabel `customer_photos`)

Alat bantu wayfinding karena alamat perkampungan tidak presisi. Beberapa foto per pelanggan
(rumah tampak depan, patokan gang/jalan, dll.).

| Kolom         | Tipe                  | Catatan                                                          |
| ------------- | --------------------- | ---------------------------------------------------------------- |
| `id`          | bigint PK             |                                                                  |
| `customer_id` | FK → `customers`      | `cascadeOnDelete` (foto = artefak pelanggan)                     |
| `uploaded_by` | FK → `users` nullable | `nullOnDelete` — siapa yang unggah (admin/teknisi); jaga riwayat |
| `path`        | string                | Path file di disk `public` (bukan blob)                          |
| `caption`     | string nullable       | Keterangan (mis. "tampak depan", "patokan gang")                 |
| `created_at`  | timestamp             | Append-only (tanpa `updated_at`)                                 |

- **Penyimpanan:** simpan **path file**, file fisik di disk `public` (`php artisan storage:link`).
  Validasi `image|max:2048`.
- **Fase 1 (web, masuk modul ini):** admin unggah/hapus foto rumah di form tambah/edit pelanggan;
  galeri foto tampil di detail pelanggan.
- **Fase 2 (Android, lanjutan):** endpoint `POST /api/customers/{id}/photos` agar teknisi bisa
  menambah foto dari lapangan. Skema sudah siap (`uploaded_by`) — tak perlu ubah tabel.
- **Android (detail tugas)** 📱: kirim daftar URL foto rumah pelanggan di `GET /api/tasks/{id}`
  supaya teknisi bisa mencocokkan rumah saat datang.

### Kategori laporan (`category`)

Enum `App\Enums\ReportCategory` (ikut pola `ReportStatus`/`UserRole` — sumber kebenaran):

| Nilai          | Label                           | Pelanggan? | Contoh                                          |
| -------------- | ------------------------------- | ---------- | ----------------------------------------------- |
| `pelanggan`    | Gangguan Pelanggan              | wajib      | Internet 1 rumah mati, kabel ke rumah putus     |
| `jaringan`     | Gangguan Jaringan/Infrastruktur | tidak      | Kabel utama putus (banyak pelanggan), ODP rusak |
| `pemeliharaan` | Pemeliharaan                    | tidak      | Cek/upgrade server, perawatan rutin perangkat   |

- **Validasi bersyarat** di form laporan:
    - `pelanggan` → `customer_id` **wajib**; snapshot nama/alamat dari pelanggan terpilih.
    - `jaringan` / `pemeliharaan` → `customer_id` NULL; **`title` wajib** + **`address` wajib**
      (dipakai sebagai lokasi/area terdampak); `customer_name` NULL.
- **Headline laporan** dipakai di tabel/PDF/Android = accessor `judul` = `customer_name ?? title`
  (satu sumber tampilan, tak peduli kategori).

### Perubahan `damage_reports`

- Tambah `category` (enum, default `pelanggan`).
- Tambah `customer_id` (FK ke `customers`, **nullable**, `nullOnDelete`).
- Tambah `title` (string nullable) — judul untuk laporan non-pelanggan.
- `customer_name` (kini nullable) + `address` **tetap ada** sebagai snapshot; untuk laporan
  non-pelanggan, `customer_name` NULL & `address` = lokasi/area terdampak.
- `scopeRiwayatSelesai` mencari ke `customer_name`/`address`/`title` (cepat, tanpa join).

### Fitur turunan: Riwayat perbaikan per pelanggan

Konsekuensi gratis dari relasi `Customer hasMany DamageReport` — tak butuh struktur data baru.

- **Di mana:** Detail Pelanggan (modal/halaman saat admin klik satu pelanggan).
- **Isi:**
    - Ringkasan: total komplain, komplain bulan ini, jenis kerusakan tersering, terakhir komplain.
    - Tabel riwayat laporan pelanggan itu (terbaru di atas): tanggal, jenis kerusakan,
      status (`<x-status-pill>`), teknisi, durasi penanganan.
- **Cakupan: semua status** (termasuk yang sedang berjalan), dibedakan lewat status-pill. Berbeda
  dari halaman Riwayat utama yang khusus "selesai".
- **Nilai untuk skripsi:** admin tak cuma tahu _siapa_ yang komplain, tapi _pola kerusakannya_ —
  bahan analisis untuk bab pembahasan (mis. pelanggan yang sering komplain kabel putus → indikasi
  instalasi bermasalah).

### Ekspor daftar pelanggan (PDF + Excel)

Daftar pelanggan = data master → bisa diekspor untuk arsip/rekap. **Mengikuti filter aktif**
(search + filter status) di halaman, sama seperti ekspor Riwayat.

- **PDF** — ikut pola yang sudah ada: **controller biasa** (bukan Volt), template di
  `resources/views/pdf/` (ber-kop, CSS inline, font DejaVu Sans), `$pdf->stream(...)` inline di tab
  baru. Tanpa dependency baru (`barryvdh/laravel-dompdf` sudah terpasang). Lihat pola
  [`ReportExportController`](app/Http/Controllers/ReportExportController.php) & konvensi "Ekspor PDF"
  di [`CLAUDE.md`](CLAUDE.md).
- **Excel** — paket baru **`maatwebsite/excel`** (PhpSpreadsheet). Buat class export
  (mis. `App\Exports\CustomersExport`) yang memakai query/scope yang sama dengan halaman + PDF
  (sumber kebenaran tunggal — filter dipusatkan di satu scope `Customer`).
- **Tombol** = `<x-dropdown>` (pola sama dengan Riwayat: `dropdown` bukan di daftar hardcoded
  `mary-*`), berisi pilihan "Ekspor PDF" & "Ekspor Excel", `rounded-full` sesuai bahasa visual.
- Kolom ekspor: nama, HP, alamat, IP, paket, status, tanggal pasang (sesuaikan; foto rumah tidak
  ikut ekspor).

### Dampak API Android 📱

[`TaskController`](app/Http/Controllers/Api/TaskController.php) (detail tugas) mengirim
`category` + `judul` + lokasi. Untuk kategori **pelanggan** tambahkan `phone` + `ip_address` +
`subscription_package` + **daftar URL foto rumah** (teknisi tahu kontak, info teknis, & bisa
mencocokkan rumah). Untuk kategori **jaringan/pemeliharaan**, field pelanggan kosong — Android
menampilkan judul + lokasi/area terdampak saja. Sisi Android perlu menangani tugas **tanpa
pelanggan** (UI detail tugas adaptif per kategori). 📱

### Rencana implementasi (langkah)

1. Migration `create_customers_table` + `create_customer_photos_table` +
   `add_customer_and_category_to_damage_reports` (kolom `customer_id` nullable, `category`,
   `title`; jadikan `customer_name` nullable).
2. Migration data: backfill pelanggan dari laporan lama + isi `customer_id`; set semua laporan
   lama `category = pelanggan`.
3. Model `Customer` (relasi `reports()`, `photos()`) + `CustomerPhoto`, enum `CustomerStatus` &
   `ReportCategory`, update relasi & `$fillable` + accessor `judul` (`customer_name ?? title`)
   [`DamageReport`](app/Models/DamageReport.php).
4. `php artisan storage:link` (untuk foto rumah) — pastikan masuk checklist deploy
   ([`CATATAN-FITUR.md`](CATATAN-FITUR.md) #11 sudah menyebutnya).
5. Halaman admin **Pelanggan** (CRUD: list + filter status + search + modal tambah/edit +
   **unggah/hapus foto rumah** + modal hapus + **detail + galeri foto + riwayat perbaikan**) —
   pakai komponen seragam yang sudah ada (`x-table-card`, `x-filter-chips`,
   `x-confirm-delete-modal`, trait `WithTableFilters`).
6. Update form laporan: tambah **pemilih kategori** yang men-toggle isi form —
   kategori `pelanggan` → search-select pelanggan (snapshot otomatis); `jaringan`/`pemeliharaan` →
   input judul + lokasi/area. Validasi bersyarat sesuai kategori.
7. Update `scopeRiwayatSelesai` (search ke `customer_name`/`address`/`title`); tampilkan kategori +
   `judul` di tabel/modal Riwayat & PDF (pakai accessor, aman untuk laporan tanpa pelanggan).
8. Update [`TaskController`](app/Http/Controllers/Api/TaskController.php): kirim `category` +
   `judul` + lokasi; untuk kategori pelanggan tambahkan `phone` + `ip_address` +
   `subscription_package` + daftar URL foto rumah. Field pelanggan **null-safe** (laporan jaringan
   tak punya pelanggan). 📱
9. **Ekspor daftar pelanggan:** `composer require maatwebsite/excel`; buat `CustomersExport`
   (Excel) + controller PDF (mis. `CustomerExportController`) + template
   `resources/views/pdf/pelanggan.blade.php` + route di grup `['auth']` + tombol `<x-dropdown>`
   (PDF/Excel) di header halaman Pelanggan. Filter dipusatkan di satu scope `Customer` (dipakai
   halaman + PDF + Excel).
10. Seeder + factory `Customer` (+ `CustomerPhoto`); sambungkan `DamageReportFactory`.
11. Update test yang menyentuh `customer_name` + tambah test modul Pelanggan (foto rumah, kategori,
    ekspor PDF/Excel).
12. Update [`CLAUDE.md`](CLAUDE.md): tabel jadi 12 (`customers` + `customer_photos`), enum baru
    (`CustomerStatus`, `ReportCategory`), paket baru `maatwebsite/excel` di tabel stack, route
    ekspor pelanggan, dokumentasi modul Pelanggan; lalu pindahkan rencana ini ke Arsip.

> **Fase 2 (lanjutan, di luar eksekusi awal):** endpoint `POST /api/customers/{id}/photos` agar
> teknisi unggah foto rumah dari Android. Skema `customer_photos.uploaded_by` sudah menampungnya —
> tinggal tambah endpoint, tanpa ubah tabel. 📱

### Catatan / risiko

- Sebaran `customer_name`/`address` ada di ~20 file, tapi mayoritas hanya **menampilkan**
  (blade/PDF/seeder/test) — perubahan logika sebenarnya kecil (scope riwayat, form laporan, API).
- Migrasi data lama harus **idempotent & aman** dijalankan di MySQL produksi (dedupe nama+alamat).
- Ide **#8 Kontak pelanggan** di [`CATATAN-FITUR.md`](CATATAN-FITUR.md) jadi usang setelah rencana
  ini — hapus/tandai saat modul selesai.

---

## 2. Catatan & bukti pekerjaan teknisi ✅ 📱

**Status:** ✅ **SELESAI** (2026-06-28) — backend **dan** Android rampung (Lapis A catatan + Lapis B
foto bukti). Ringkasan as-built ada di [Arsip](#arsip-rencana-yang-sudah-selesai) di bawah. Detail
desain berikut dipertahankan sebagai **catatan historis** (apa yang diputuskan & dibangun).

### Latar belakang

Hasil review dosen pembimbing: **sisi Android kurang informasi tentang pekerjaan yang
dilakukan**. Saat ini teknisi hanya menekan tombol transisi status
(Ditugaskan → Sedang Memperbaiki → Selesai). Work log dibuat **hanya berisi `status`** —
lihat [`TaskController::updateStatus`](app/Http/Controllers/Api/TaskController.php#L82-L86) —
sehingga "Log Aktivitas Pekerjaan" cuma deretan perubahan status **tanpa substansi apa yang
dikerjakan**. Kolom [`work_logs.description`](database/migrations/2026_06_05_145717_create_work_logs_table.php)
sudah ada & API detail tugas [sudah mengirimnya](app/Http/Controllers/Api/TaskController.php#L118),
tapi selalu kosong karena tak pernah diisi.

Rencana ini menggabungkan dua ide backlog [`CATATAN-FITUR.md`](CATATAN-FITUR.md) **#5 (foto bukti)**
& **#6 (keterangan work log)** menjadi satu rencana utuh; keduanya usang setelah modul ini selesai.

### Keputusan yang sudah dikunci

1. **Dua lapis dikerjakan keduanya:** Lapis A (catatan teks) + Lapis B (foto bukti).
2. **Pengisian opsional tapi disarankan.** TIDAK ada validasi keras yang memblokir tombol Selesai.
   Sebagai gantinya, Android memberi **dorongan halus**: bila teknisi menekan Selesai tanpa
   catatan/foto, muncul konfirmasi _"Selesaikan tanpa catatan/foto?"_ yang tetap bisa dilanjutkan.
3. **Foto: banyak per laporan** (mendukung before/after) → pakai tabel baru `report_photos`,
   bukan kolom tunggal.

### Lapis A — Catatan pekerjaan (`work_logs.description`)

Kolom sudah ada, API sudah mengirim — perubahan backend **sangat kecil**.

- **Backend:** [`TaskController::updateStatus`](app/Http/Controllers/Api/TaskController.php#L36)
  menerima `description` opsional (`nullable|string`), teruskan ke
  `WorkLog::create([... 'description' => ...])`.
- **Android** 📱: field catatan (opsional) di layar "Sedang Memperbaiki" / saat menutup tugas.
- **Tampilan:** timeline work log di Riwayat ([riwayat.blade.php](resources/views/livewire/pages/riwayat.blade.php))
  & PDF lengkap tinggal menampilkan `description` bila ada (tempatnya sudah tersedia).

### Lapis B — Foto bukti perbaikan (tabel `report_photos`)

Skema tabel `report_photos`:

| Kolom           | Tipe                  | Catatan                                                 |
| --------------- | --------------------- | ------------------------------------------------------- |
| `id`            | bigint PK             |                                                         |
| `report_id`     | FK → `damage_reports` | `cascadeOnDelete` (foto = artefak hidup laporan)        |
| `technician_id` | FK → `users` nullable | `nullOnDelete` (jaga riwayat, konsisten pola work_logs) |
| `path`          | string                | Path file di disk `public` (bukan blob)                 |
| `caption`       | string nullable       | Keterangan opsional (mis. "sebelum"/"sesudah")          |
| `created_at`    | timestamp             | Append-only (tanpa `updated_at`, ikut pola tabel log)   |

- **Penyimpanan:** simpan **path file**, file fisik di disk `public`
  (`php artisan storage:link`). Validasi `image|max:2048`, kompres di device.
- **API** 📱: endpoint terpisah `POST /api/tasks/{id}/photos` (multipart, field `photo` +
  `caption` opsional) — dipisah dari endpoint status agar mendukung banyak foto & tidak
  mengganggu transisi status. Detail tugas (`GET /api/tasks/{id}`) menyertakan daftar URL foto.
- **Web:** thumbnail di modal detail [Riwayat](resources/views/livewire/pages/riwayat.blade.php) +
  PDF lengkap (dompdf bisa render `<img>` dari path lokal/absolut).

### Rencana implementasi (langkah)

1. **Lapis A backend:** validasi `description` opsional di
   [`TaskController::updateStatus`](app/Http/Controllers/Api/TaskController.php#L36) →
   teruskan ke `WorkLog::create`. Tambah test.
2. Migration `create_report_photos_table` + model `ReportPhoto` + relasi
   `DamageReport::photos()`.
3. **Lapis B API:** endpoint `POST /api/tasks/{id}/photos` (validasi `image|max:2048`, simpan ke
   disk `public`, catat path + technician + caption). Sertakan daftar foto (URL) di
   `formatTask(detailed: true)`.
4. `php artisan storage:link` + pastikan langkah ini masuk checklist deploy
   ([`CATATAN-FITUR.md`](CATATAN-FITUR.md) #11 langkah 6 sudah menyebutnya).
5. **Web tampilan:** tampilkan `description` di timeline work log + thumbnail foto (klik →
   perbesar) di modal detail Riwayat; sertakan di PDF lengkap.
6. **Android** 📱: field catatan opsional + ambil/unggah foto dari kamera; konfirmasi halus
   "Selesaikan tanpa catatan/foto?" saat Selesai dalam keadaan kosong.
7. Update test (work log description terisi, upload foto, tampil di detail tugas) +
   [`CLAUDE.md`](CLAUDE.md) (tabel bertambah `report_photos`, endpoint API baru) + pindahkan
   rencana ini ke Arsip saat selesai.

### Dampak API Android 📱

| Method | Path                     | Perubahan                                               |
| ------ | ------------------------ | ------------------------------------------------------- |
| POST   | `/api/tasks/{id}/status` | Terima field `description` opsional (Lapis A)           |
| POST   | `/api/tasks/{id}/photos` | **Baru** — unggah 1 foto (multipart) + caption opsional |
| GET    | `/api/tasks/{id}`        | Response menyertakan daftar URL foto laporan            |

### Catatan / risiko

- **Lapis A paling murah** (kolom & relasi sudah siap) — bisa dikerjakan & dirilis lebih dulu
  bila ingin cepat menjawab kritik, foto menyusul. Tapi keputusan = kerjakan keduanya.
- Foto butuh kerja **sisi Android** (kamera + multipart upload + kompres). Pastikan
  `storage:link` aktif di server saat deploy, kalau tidak foto tak tampil.
- GPS membuktikan teknisi _ada di lokasi_; foto + catatan membuktikan _pekerjaan dikerjakan_ —
  ini paling mungkin ditanya penguji ("bagaimana admin tahu teknisi benar memperbaiki?").

---

## 3. Dashboard analitik & filter laporan ✅

**Status:** ✅ **SELESAI** (2026-06-28) — diimplementasi penuh (100 test pass). Ringkasan as-built
ada di [Arsip](#arsip-rencana-yang-sudah-selesai) di bawah. Detail desain berikut dipertahankan
sebagai **catatan historis**. Keputusan kecil yang dulu ditunda kini terkunci: **halaman Statistik
tersendiri** (bukan menumpuk di dashboard) & **grafik pakai Chart.js via CDN** (pola sama Leaflet).

### Apa

Memperkaya dashboard & riwayat dengan penyaringan dan statistik untuk pengambilan keputusan +
bahan bab pembahasan skripsi. Dua bagian:

**3a. Filter & pecahan per kategori** _(butuh Rencana #1 #9)_

- **Dashboard** — pecahan jumlah laporan **per kategori** (Pelanggan/Jaringan/Pemeliharaan),
  melengkapi stat per-status ([dashboard.blade.php](resources/views/livewire/pages/dashboard.blade.php)).
- **Riwayat** — baris **chip filter kategori** sejajar filter periode/search
  ([riwayat.blade.php](resources/views/livewire/pages/riwayat.blade.php)).

**3b. Statistik / analitik** _(bisa mandiri)_

- **Rata-rata durasi penanganan** (sudah ada `durasiPenanganan()` & `completed_at` sebagai sumber).
- **Tren komplain per bulan** (grafik batang/garis sederhana).
- **Kinerja per teknisi** — jumlah tugas ditangani & selesai (lewat `work_logs`/`task_assignments`).
- **Kerusakan tersering** — agregasi `damage_type_id` (top 5).

### Kenapa

Memberi makna "monitoring" yang sebenarnya (bukan cuma daftar) & bahan analisis kuat untuk skripsi.
Murah karena sumber datanya sudah ada (`completed_at`, `durasiPenanganan()`, relasi work log).

### Bagaimana

- **Filter kategori:** tambah parameter `$category` ke `DamageReport::scopeRiwayatSelesai`; render
  `<x-filter-chips :options="ReportCategory::options()" field="filterCategory" .../>` (komponen &
  pola filter sudah ada, trait `WithTableFilters` urus reset paginasi).
- **Stat per kategori/status:** query `count` grouped (mirip pola stat per-status yang sudah ada),
  tampil sebagai baris stat / kartu kecil.
- **Grafik:** butuh pustaka chart ringan (mis. Chart.js / ApexCharts via CDN) — **keputusan kecil
  menyusul**; bisa juga mulai dari tabel angka + bar CSS murni tanpa pustaka.
- Bisa berdiri sebagai **halaman "Laporan/Statistik" tersendiri** bila dashboard mulai padat.

### Catatan

- **Bagian 3a jangan mulai sebelum Rencana #1 selesai** — tanpa kolom `category`, tak ada yang
  difilter. Bagian 3b (durasi/tren/kinerja) bisa dikerjakan lebih dulu bila mau.
- Lingkup polish; cocok dikerjakan setelah modul inti jalan.

---

## 4. Pendaftaran pelanggan oleh teknisi saat pemasangan baru 💡 📱

**Status:** 💡 Ide ringan — **DITUNDA**. Dua syarat sebelum dieksekusi:

> 1. **Rencana #1 (Modul Pelanggan) harus selesai dulu** — fitur ini menumpang entitas `customers`.
> 2. **⚠️ Menunggu persetujuan dosen pembimbing** — pemasangan baru memperluas cakupan dari
>    "perbaikan" (lihat caveat di bawah). Jangan koding sebelum dosen setuju.
>
> Urutan: kerjakan #1 → #2 → #3 dulu, rencana ini **paling akhir**.

### Latar belakang

Teknisi tidak hanya **memperbaiki** tapi juga melakukan **pemasangan baru**, dan saat itu ia
**bertemu pelanggan langsung di lokasi** — sumber data paling akurat. Ide: teknisi mendaftarkan
data pelanggan baru dari Android saat pemasangan, sehingga **admin tidak perlu lagi input manual**.

### Keputusan (model alur)

1. **Teknisi mengisi → admin menyetujui.** Teknisi mengirim data pelanggan baru dari Android.
   Data masuk berstatus **`menunggu_verifikasi`**. Admin melihatnya di daftar Pelanggan (dengan
   penanda), lalu **Setujui** (status jadi `aktif`) atau **Tolak**. Gerbang verifikasi ini menjaga
   kualitas data — sekaligus jawaban kuat untuk pertanyaan penguji soal integritas data.
2. **Manfaat sampingan (bonus):**
    - **Koordinat GPS presisi** — teknisi fisik di lokasi → tangkap `latitude`/`longitude` akurat
      saat itu. Menutup kompromi "koordinat nullable" di Rencana #1.
    - **Foto rumah on-site** — pemicu konkret untuk **Fase 2 foto rumah** (Rencana #1 #8): teknisi
      foto rumah saat memasang.
3. **Opsional — catat sebagai job `pemasangan` yang termonitor.** Tambah kategori ke-4
   `pemasangan` ke `ReportCategory` (Rencana #1 #9) agar pemasangan ikut terlacak GPS/riwayat
   seperti perbaikan; `customer_id` terisi saat pelanggan baru disetujui. **Keputusan menyusul** —
   bisa mulai dari versi sederhana (hanya daftar pelanggan + verifikasi) tanpa job dulu.

### ⚠️ Caveat cakupan (wajib dibahas dengan dosen)

Judul skripsi = **"Monitoring Perbaikan Jaringan."** Pemasangan baru **bukan perbaikan** →
memperluas cakupan ke "pekerjaan lapangan teknisi". Bisa jadi **nilai plus** ("sistem mencakup
seluruh pekerjaan teknisi") **atau** pertanyaan jebakan penguji. **Konfirmasikan ke dosen** apakah
perluasan ini diizinkan / perlu penyesuaian framing/judul — sebelum dieksekusi.

### Dampak teknis (ringkas — detail menyusul saat dieksekusi)

- **Enum** `CustomerStatus` tambah nilai `menunggu_verifikasi`; admin approve → `aktif`.
- **`customers`** tambah jejak pembuat (mis. `submitted_by` FK → users, `nullOnDelete`) untuk tahu
  teknisi mana yang mendaftarkan.
- **API Android** 📱: endpoint baru `POST /api/customers` (teknisi kirim data pelanggan +
  koordinat + foto rumah).
  > **⚠️ Pergeseran endpoint (update 2026-06-27):** asumsi awal "reuse `POST /api/customers/{id}/photos`"
  > **tidak berlaku lagi**. Saat Fase 2 dieksekusi, endpoint upload foto rumah dibuat
  > **task-scoped** (`POST /api/tasks/{id}/house-photos`) demi otorisasi alami — teknisi hanya
  > boleh menambah foto untuk laporan yang ditugaskan padanya. Pada #4, pelanggan **baru** belum
  > punya task → endpoint task-scoped itu **tak bisa dipakai**. Jadi #4 harus menyediakan jalur
  > fotonya sendiri: entah (a) `POST /api/customers` sekalian terima foto multipart, atau (b)
  > endpoint customer-scoped terpisah (`POST /api/customers/{id}/photos`) yang dibuat khusus di #4
  > dengan cek otorisasi sendiri (mis. `submitted_by` = teknisi pengirim). Bukan blocker — hanya
  > berarti "reuse penuh" jadi "jalur baru". Fase 2 yang sudah ada melayani kasus **perbaikan**
  > (lihat Arsip #1); #4 menambah jalur untuk kasus **pemasangan/pendaftaran**.
- **Web admin:** di halaman Pelanggan, chip filter "Menunggu Verifikasi" + aksi Setujui/Tolak di
  detail pelanggan.
- Bila ambil opsi job termonitor: kategori `pemasangan` + alur task assignment seperti perbaikan.

### Catatan

- **Bergantung penuh pada Rencana #1** — tanpa entitas `customers`, tak ada yang didaftarkan.
- Mulai dari versi minimal (daftar pelanggan + verifikasi) lebih aman; job `pemasangan` termonitor
  bisa jadi peningkatan berikutnya.

---

## 6. Metadata laporan: asal komplain & jadwal kunjungan 📋

**Status:** 📋 Direncanakan — keputusan desain **dikunci 2026-06-28** (lihat di bawah), belum mulai
koding. Karena Rencana #1 sudah rampung, #6 **butuh migrasi sendiri** (tak bisa lagi membonceng
migrasi `damage_reports` di #1).

> Catatan historis: nomor #6 sempat hanya tercantum di Daftar Isi tanpa badan seksi (referensi
> menggantung sejak commit Modul Pelanggan). Seksi ini mengisinya.

### Latar belakang

Laporan saat ini menyimpan **apa** gangguannya, tapi tidak **dari mana** & **kapan** komplain datang,
juga tidak **kapan** dijadwalkan ditangani. Kolom `created_at` = waktu admin **mengetik** laporan di
web — bukan waktu pelanggan **melapor**. Akibatnya aplikasi tak bisa mengukur **kecepatan tanggap**
(response time) maupun menganalisis **saluran komplain**.

Tiga kolom metadata melengkapi laporan menjadi **siklus hidup utuh** — sumber data analitik yang kuat
untuk bab pembahasan skripsi:

```
asal komplain → reported_at (lapor) → scheduled_at (jadwal) → sedang_memperbaiki → completed_at (selesai)
```

### Keputusan yang sudah dikunci

1. **Ketiga kolom masuk semua** (`report_source` + `reported_at` + `scheduled_at`) — satu migrasi,
   saling melengkapi membentuk lifecycle laporan. Murah (kolom + field form), nilai analitik tinggi.
2. **`report_source` = enum `App\Enums\ReportSource`** (sumber kebenaran, ikut pola
   `ReportCategory`/`CustomerStatus` — **tidak di-cast**, pakai `->value`, hindari literal). Nilai awal:
   `whatsapp` → WhatsApp, `telepon` → Telepon, `datang` → Datang langsung, `lainnya` → Lainnya.
   `options()` untuk chip/select, `values()` untuk validasi `in:`. **Enum** (bukan teks bebas) supaya
   bisa diagregasi rapi di Statistik (pecahan komplain per saluran) tanpa typo/variasi ejaan.
3. **`scheduled_at` = field sederhana** (datetime nullable) — diisi admin, tampil di detail/tabel,
   bisa difilter "jadwal hari ini". **TANPA** pengingat/notifikasi FCM/view kalender (sengaja, jaga
   lingkup; bisa ditingkatkan kelak sebagai rencana terpisah).
4. **Semua kolom nullable** → data lama tetap valid (`report_source`/`reported_at`/`scheduled_at` =
   NULL, tampil "—"). Tak perlu backfill.
5. **`reported_at` default `now()`** saat buat laporan bila admin tak mengisinya — mengurangi friksi
   input; admin tinggal mengoreksi bila komplain masuk lebih awal dari saat diketik.

### Skema perubahan `damage_reports` (migrasi baru)

| Kolom           | Tipe              | Catatan                                                         |
| --------------- | ----------------- | -------------------------------------------------------------- |
| `report_source` | string nullable   | Nilai enum `ReportSource` (saluran komplain); NULL = "—"        |
| `reported_at`   | datetime nullable | Kapan komplain masuk dari pelanggan (≠ `created_at`)            |
| `scheduled_at`  | datetime nullable | Jadwal kunjungan teknisi                                        |

Enum `App\Enums\ReportSource` (`whatsapp`/`telepon`/`datang`/`lainnya`) — pola sama enum lain
(tak di-cast, `options()`/`values()`). `reported_at`/`scheduled_at` di-`cast` `datetime` di model
(ikut pola `completed_at`); `report_source` **tidak** di-cast (pola enum string).

### Tampilan & analitik

- **Form Laporan** ([laporan/index.blade.php](resources/views/livewire/pages/laporan/index.blade.php)):
  tambah 3 input opsional di modal — select asal komplain (`icon="o-chat-bubble-left-right"`),
  datetime waktu lapor (`o-clock`), datetime jadwal kunjungan (`o-calendar`). Bahasa visual:
  `rounded-full`, icon kontekstual.
- **Tabel/detail Laporan & Riwayat:** tampilkan asal komplain sebagai **pil** (komponen baru
  `<x-source-pill>` mengikuti pola `<x-category-pill>`) + jadwal kunjungan. Opsional: filter
  "jadwal hari ini" di halaman Laporan.
- **Halaman Statistik** ([statistik.blade.php](resources/views/livewire/pages/statistik.blade.php)):
  tambah **pecahan komplain per saluran** (`report_source`) + **rata-rata response time**
  (`reported_at` → `completed_at`), melengkapi rata-rata durasi penanganan yang sudah ada. Query
  DB-agnostic (jalan di MySQL & SQLite test).
- **PDF Riwayat (opsional):** kolom asal komplain di template ringkasan.

### Rencana implementasi (langkah)

1. Enum `App\Enums\ReportSource`.
2. Migrasi `add_metadata_to_damage_reports` (`report_source`, `reported_at`, `scheduled_at` — semua
   nullable).
3. Model [`DamageReport`](app/Models/DamageReport.php): `$fillable` + cast `reported_at`/`scheduled_at`
   (datetime); `report_source` tak di-cast. Accessor label source bila perlu (null-safe → "—").
4. Form Laporan: 3 field + validasi bersyarat (`report_source` `nullable|in:ReportSource::values()`;
   `reported_at`/`scheduled_at` `nullable|date`); default `reported_at = now()` bila kosong.
5. Factory/seeder: isi contoh `report_source` + `reported_at` (+ sebagian `scheduled_at`) agar
   Statistik ada datanya.
6. Tampilan: `<x-source-pill>` + jadwal di tabel/detail/Riwayat.
7. Statistik: pecahan per saluran + rata-rata response time.
8. Test: simpan metadata dari form, enum `ReportSource`, agregat saluran & response time di Statistik.
9. [`CLAUDE.md`](CLAUDE.md): enum baru `ReportSource`, kolom metadata `damage_reports`, dokumentasi
   Statistik bertambah; lalu pindahkan rencana ini ke Arsip.

### Dampak API Android 📱 (opsional, bukan blocker)

Kolom ini **diisi admin di web** — Android tak wajib berubah. Sebagai peningkatan opsional,
[`TaskController`](app/Http/Controllers/Api/TaskController.php) detail tugas bisa menyertakan
`scheduled_at` agar teknisi tahu jadwal kunjungan (dan `report_source`/`reported_at` bila berguna).
Bisa jadi fase lanjutan setelah web selesai.

### Catatan / risiko

- **`reported_at` menambah sedikit friksi input** admin — diredam dengan default `now()`.
- **Response time = nilai jual kuat saat sidang** ("seberapa cepat komplain ditanggapi") — gunakan
  `reported_at` → `completed_at` (atau → mulai memperbaiki untuk "waktu respons awal").
- **Jangan over-engineer jadwal** jadi sistem reminder/kalender — sudah diputuskan field sederhana.

---

## 7. Peningkatan peta Monitoring & Dashboard 🔨

**Status:** 🔨 Dikerjakan bertahap — **opsi A & C ✅ SELESAI** + **tambahan: foto bukti pekerjaan
LIVE di Monitoring ✅ SELESAI** (di luar 3 opsi, lihat di bawah), opsi B 💡 Ide (terikat retensi
data). Backlog idenya juga tercatat di
[`CATATAN-FITUR.md` #12](CATATAN-FITUR.md#12-peningkatan-peta-monitoring-marker-trail-gps-basi);
bagian ini = jalur aktif/as-built-nya.

### Latar belakang

Peta GPS ([monitoring.blade.php](resources/views/livewire/pages/monitoring.blade.php) &
[dashboard.blade.php](resources/views/livewire/pages/dashboard.blade.php)) memakai pin biru default
Leaflet yang **identik untuk semua teknisi** (tak terbedakan tanpa klik popup), hanya menampilkan
**satu titik terakhir** (tak ada cerita pergerakan), dan `last_update` sudah dihitung di
[`GetActiveTechnicianLocations`](app/Actions/GetActiveTechnicianLocations.php) tapi **belum dipakai**
untuk menandai data basi. Tiga peningkatan memperkuat narasi "monitoring perbaikan realtime".

Semua murni **lapisan presentasi (+ sedikit logika di Action)** — **0 perubahan Android/DB** (kecuali
opsi B yang butuh titik GPS tetap tersimpan). Data lokasi tetap dari satu sumber Action yang sama,
dipakai bersama kedua peta.

### Tiga opsi

| Opsi | Apa | Status |
| ---- | --- | ------ |
| **A. Marker custom** | Ganti pin default → badge bulat berinisial nama + warna khas, konsisten per id teknisi | ✅ Selesai |
| **C. Indikator GPS basi** | Bila titik terakhir > 5 menit, marker & sidebar diberi warna "GPS terputus" | ✅ Selesai |
| **B. Jejak/trail GPS** | `L.polyline` dari beberapa titik terakhir pergerakan teknisi saat `sedang_memperbaiki` | 💡 Ide |

### ✅ Opsi A — Marker custom (SELESAI)

Diterapkan **identik di kedua peta** (Monitoring & Dashboard) agar konsisten — dashboard menaut ke
halaman Monitoring, marker beda bentuk akan terlihat belum rapi.

- **Yang dibangun:** helper `MARKER_COLORS` (palet 8 warna) + `colorFor(id)` (pilih warna `id % 8`,
  konsisten per teknisi selama sesi) + `initialsFor(name)` (inisial kata pertama+terakhir) +
  `makeIcon(loc)` (`L.divIcon`: lingkaran berinisial + segitiga penunjuk lurus ke bawah, tip = lokasi
  tepat). `L.marker(...)` diberi `{ icon: makeIcon(loc) }`.
- **Style inline** di dalam html divIcon (di-render di pane peta, bukan dipindai Tailwind);
  `className: 'technician-marker'` meng-override default agar tak muncul kotak putih `leaflet-div-icon`.
- **Tanpa build** (JS inline di `@script`, bukan asset Vite). 0 perubahan backend/Android/DB.
- **Trade-off diterima:** JS marker terduplikasi di dua blade — konsisten dengan pola yang sudah ada
  (blok `@script` peta memang dipisah per halaman; yang dibagikan satu sumber hanya *data*-nya, yaitu
  Action PHP). Bukan bau kode baru.

### ✅ Opsi C — Indikator GPS basi (stale) (SELESAI)

**Kenapa:** GPS HP teknisi bisa mati/sinyal hilang. Tanpa penanda, admin bisa menyangka teknisi diam
di titik itu padahal datanya usang.

- **Yang dibangun:** [`GetActiveTechnicianLocations`](app/Actions/GetActiveTechnicianLocations.php)
  menambah flag `is_stale` ke payload — `true` bila titik terakhir lebih tua dari
  `STALE_AFTER_MINUTES` (5 menit), `false` bila belum ada GPS sama sekali (itu "Menunggu GPS", bukan
  basi). Ambang dijadikan konstanta beralasan (polling 10 detik → 5 menit = toleransi lapang).
- **Monitoring:** sidebar kini punya **tiga keadaan** — "GPS terputus" (warning, titik tak berdenyut),
  "GPS aktif" (success, berdenyut), "Menunggu GPS". Marker basi diberi warna abu `#94a3b8` (`STALE_COLOR`)
  via `colorFor(loc)` sehingga meredup di antara teknisi live.
- **Dashboard:** marker basi ikut abu (peta saja, tak ada sidebar status).
- **Test:** `GetActiveTechnicianLocationsTest` + 2 kasus (titik segar tak basi, titik usang ditandai
  basi & lokasi terakhir tetap tampil). Masih 0 Android/DB.

### ✅ Tambahan — Foto bukti pekerjaan LIVE di Monitoring (SELESAI, di luar 3 opsi)

> Bukan bagian dari opsi A/B/C (yang soal *marker/trail*), tapi peningkatan halaman Monitoring yang
> **membonceng data Rencana #2** ([Catatan & bukti pekerjaan teknisi](#2-catatan--bukti-pekerjaan-teknisi)).
> Dicatat di sini karena tempat fiturnya = peta Monitoring.

**Latar:** foto bukti pekerjaan (`report_photos`, dibuat di Rencana #2) sebelumnya **hanya** tampil
di halaman Riwayat — artinya admin baru bisa melihatnya setelah pekerjaan **selesai**. Ide user:
perlihatkan juga **saat `sedang_memperbaiki`** agar admin bisa pantau progres real-time. Datanya
sudah ada; ini murni soal **tata letak UI**, bukan fitur/skema baru.

**Yang dibangun (0 perubahan Android/DB):**
- [`GetActiveTechnicianLocations`](app/Actions/GetActiveTechnicianLocations.php): eager-load
  `report.photos` (dibatasi `MAX_PHOTOS = 8`, terbaru dulu — tetap **anti-N+1**) + key `photos`
  (url + caption) per teknisi di payload. Dashboard memakai Action yang sama tapi **mengabaikan**
  key `photos` (cuma meneruskan data ke peta JS) — aman, tanpa perubahan.
- [`monitoring.blade.php`](resources/views/livewire/pages/monitoring.blade.php): **strip thumbnail**
  foto terbaru di kartu sidebar tiap teknisi aktif + **modal galeri** saat diklik
  (`openPhotos($id)`). Karena halaman sudah `wire:poll.10s`, **foto yang baru diunggah teknisi di
  tengah pekerjaan otomatis muncul** — inilah inti "monitoring realtime".
- **Test:** `GetActiveTechnicianLocationsTest` + 2 kasus (foto terbaru-dulu & path → URL storage;
  teknisi tanpa foto = array kosong). **Total 111 test pass.**
- **Catatan deploy:** butuh `php artisan storage:link` aktif (foto di disk `public`); view memakai
  `asset('storage/..')` sesuai konvensi (hindari `Storage::url()` — beda host APP_URL vs serve).

### Opsi B — Jejak/trail GPS 💡 (BELUM — menunggu keputusan scope)

**Apa:** Selain marker satu titik (posisi terakhir), gambar **garis (`L.polyline`)** menghubungkan
beberapa titik terakhir tiap teknisi → terlihat **rute/pergerakan**, bukan posisi diam. Marker tetap
di ujung terbaru, ekornya jadi jejak.

**Kenapa:** pembeda terkuat untuk tema "monitoring perbaikan" — bukan cuma titik, tapi rute
pergerakan nyata teknisi. Paling "menjual" naratif saat sidang.

**Beda fundamental dari A & C:** A & C cuma mengubah **tampilan satu titik** (warna/inisial/abu).
B mengubah **bentuk data** yang ditarik — dari "1 titik per teknisi" jadi "banyak titik per
teknisi". Itu sebabnya paling berat.

**Bagaimana (garis besar):**
- [`GetActiveTechnicianLocations`](app/Actions/GetActiveTechnicianLocations.php) sekarang **sengaja**
  hanya menarik titik `MAX(recorded_at)` per pasangan (teknisi, laporan) demi hemat. Trail butuh
  kebalikannya → buat **method/Action baru** khusus trail (jangan modifikasi Action lama yang dipakai
  sidebar/dashboard, supaya payload poll-nya tetap ramping).
- View [`monitoring.blade.php`](resources/views/livewire/pages/monitoring.blade.php): render
  `L.polyline([[lat,lng],...])` per teknisi selain marker, perbarui garis tiap poll.

**⚠️ Tiga keputusan yang harus diambil dulu (sebelum koding):**
1. **Berapa titik / rentang waktu?** Semua titik sejak mulai bisa ratusan–ribuan baris. Wajib dibatasi:
   N titik terakhir **atau** titik X menit terakhir. Untuk peta *live* cukup ekor pendek (mis. 15–30
   menit terakhir).
2. **Scope: live saja, atau sekalian Riwayat?** Beririsan dengan
   [`CATATAN-FITUR.md` #2](CATATAN-FITUR.md) (trail *historis* di halaman Riwayat untuk laporan yang
   **sudah selesai**). Bedanya: **B = live** saat masih dikerjakan; **#2 = arsip** setelah selesai.
   Bisa dipisah atau dibangun satu fondasi query bersama. **Belum diputuskan.**
3. **Beban polling.** Tiap 10 detik kini kirim 1 titik/teknisi; trail kirim N titik/teknisi. Skala
   SkyNet masih kecil, tapi jangan tarik seluruh jejak.

**⚠️ Prasyarat retensi data** ([`CATATAN-FITUR.md` #1](CATATAN-FITUR.md)): trail butuh titik tetap
tersimpan. Kalau cleanup `location_logs` kelak pakai mode hapus-total, jejak hilang. Versi **live** B
relatif aman (pakai titik yang baru masuk); yang paling terdampak adalah trail **historis** #2.

**Catatan jujur (pertimbangan keputusan):** efek visual trail bergantung **adanya pergerakan** —
kalau teknisi kebanyakan diam di satu titik perbaikan, trail-nya cuma garis pendek/menumpuk. Nilai
trail paling terasa bila GPS Android benar-benar mengirim titik berkala selama **perjalanan menuju
lokasi**, bukan hanya saat sudah sampai. Cek dulu perilaku pengiriman GPS sisi Android sebelum
memutuskan ini layak dikerjakan.

### Urutan disarankan

**A → C → B** (cepat & kentara dulu; trail terakhir karena paling banyak kerja & terikat retensi
data). Polling 10 detik = full Livewire round-trip; untuk skala SkyNet biarkan apa adanya, jangan
over-engineer jadi WebSocket.

---

## 8. Audit log aktivitas admin 📋

**Status:** 📋 Direncanakan — keputusan desain **dikunci 2026-06-28** (lihat di bawah), belum mulai
koding. Mandiri (tak bergantung rencana lain), bisa dikerjakan kapan saja.

### Latar belakang

Aplikasi mencatat **pekerjaan teknisi** (`work_logs`, `location_logs`, foto bukti) tapi **tidak**
mencatat **aksi admin** di panel web. Bila sebuah laporan diubah, pelanggan dihapus, atau penugasan
teknisi dibatalkan, **tak ada jejak siapa & kapan** — padahal beberapa aksi tak terbalikkan dan FK
`nullOnDelete`/snapshot sengaja menjaga arsip. Pertanyaan penguji klasik soal **akuntabilitas &
integritas data** ("kalau data salah/hilang, siapa yang mengubah?") saat ini tak terjawab oleh sistem.

Rencana ini menambahkan **audit log menyeluruh** — bukan sekadar satu modul, melainkan **semua
perubahan data lintas seluruh entitas admin** + autentikasi — yang tampil di satu halaman "Log
Aktivitas" yang bisa dibaca, dicari, dan difilter admin. Mengangkat catatan minor di
[`CATATAN-FITUR.md` #10](CATATAN-FITUR.md) menjadi fitur utuh.

### Keputusan yang sudah dikunci

1. **Cakupan aktor = admin web + autentikasi.** Yang dicatat: semua perubahan data yang dilakukan
   admin lewat panel web (laporan, pelanggan, pengguna, jenis gangguan, foto rumah, penugasan
   teknisi) + peristiwa **login / login gagal / logout**. **Aksi teknisi via API Sanctum TIDAK
   dicatat** di sini — sudah terekam di `work_logs`/`location_logs`/foto bukti. (Mencegah audit log
   membanjir oleh ping GPS & menjaga maknanya "siapa admin mengubah apa".)
2. **Hanya perubahan data + auth, BUKAN page-view.** Membuka/melihat halaman tidak dicatat — log
   tetap ringkas & bermakna, fokus ke _apa yang berubah_. "Semua aktivitas" di sini = semua
   **perubahan** lintas entitas, bukan setiap navigasi.
3. **Pakai paket `spatie/laravel-activitylog`** (bukan tabel/observer custom). Battle-tested,
   menangkap **diff lama→baru** otomatis lewat trait `LogsActivity` per model (satu baris konfigurasi
   per model — itulah yang membuat "semua entitas" murah & tak ada yang lolos), satu tabel
   `activity_log`. Konsisten dengan pola proyek memakai paket pihak ketiga (`dompdf`, `maatwebsite/excel`,
   `kreait/firebase`).
4. **Audit log = read-only & append-only.** Halaman "Log Aktivitas" hanya menampilkan — **tanpa**
   tombol edit/hapus (integritas audit; log yang bisa diutak-atik tak ada gunanya). Tabel tak pernah
   ditulis dari UI, hanya oleh sistem.
5. **Data sensitif tak ikut tercatat.** Perubahan kolom `password`, `remember_token`, `fcm_token`
   pada `users` **dikecualikan** dari diff (`logExcept`). Audit mencatat _bahwa_ akun diubah, bukan
   isi rahasianya.

### Apa yang dicatat (lintas entitas)

| Entitas (model)             | Aksi tercatat                 | Titik picu (sudah pakai operasi level-model → event Eloquent jalan) |
| --------------------------- | ----------------------------- | ------------------------------------------------------------------- |
| `DamageReport`              | dibuat / diperbarui / dihapus | [laporan/index.blade.php](resources/views/livewire/pages/laporan/index.blade.php) `save()`/`delete()` |
| `Customer`                  | dibuat / diperbarui / dihapus | [pelanggan/index.blade.php](resources/views/livewire/pages/pelanggan/index.blade.php) |
| `User`                      | dibuat / diperbarui / dihapus | [pengguna/index.blade.php](resources/views/livewire/pages/pengguna/index.blade.php) (kecuali field rahasia) |
| `DamageType`                | dibuat / diperbarui / dihapus | [jenis-gangguan/index.blade.php](resources/views/livewire/pages/jenis-gangguan/index.blade.php) |
| `CustomerPhoto`             | ditambah / dihapus (oleh admin) | [pelanggan/detail.blade.php](resources/views/livewire/pages/pelanggan/detail.blade.php) |
| Penugasan teknisi           | ditugaskan / dibatalkan       | [SyncReportTechnicians](app/Actions/SyncReportTechnicians.php) — **lihat titik buta di bawah** |
| Autentikasi (events Laravel) | login / login gagal / logout  | `LoginForm::authenticate` (`Auth::attempt`) & `Logout` action |

> **Tiap entri menyimpan:** aktor (admin penyebab, otomatis dari user login), aksi
> (created/updated/deleted/login/…), objek (jenis model + identitasnya), ringkas perubahan
> (diff lama→baru, JSON `properties`), dan waktu. **Opsional:** IP + user-agent ke `properties`.

### ⚠️ Titik buta yang wajib ditangani

Trait `LogsActivity` menangkap **event Eloquent** (`created`/`updated`/`deleted`). Operasi
**query-builder massal melewati event** sehingga tak tertangkap otomatis:

- **Pembatalan penugasan** di [SyncReportTechnicians.php](app/Actions/SyncReportTechnicians.php#L36-L39)
  memakai `->whereIn('technician_id', $toRemove)->delete()` (bulk) — **tidak** memicu event.
  Penambahan (`TaskAssignment::create()`) memicu event, pembatalan tidak. **Solusi:** catat
  penugasan/pembatalan **secara eksplisit di Action** sebagai satu entri semantik yang terbaca
  (mis. _"Menugaskan teknisi Budi ke laporan #12"_ / _"Membatalkan penugasan teknisi Budi dari
  laporan #12"_) — lebih bermakna daripada baris `task_assignments` mentah. (Karena dicatat manual
  di Action, `TaskAssignment` **tidak** perlu trait `LogsActivity`, menghindari entri ganda.)
- Aturan umum ke depan: aksi admin baru harus pakai operasi **level-model** (atau `activity()` manual)
  agar tetap terekam.

### Membatasi ke konteks web admin

Agar aksi teknisi via API tidak ikut tercatat (keputusan #1) — mis.
`TaskController::updateStatus` yang memanggil `$report->update(...)` — **logging dinonaktifkan untuk
grup route `/api`** (mis. middleware yang menyetel `config(['activitylog.enabled' => false])` untuk
request API; mekanisme persis diverifikasi saat implementasi). Dengan begitu trait di `DamageReport`
dkk. hanya aktif pada request web admin. Penyebab (causer) diambil otomatis dari user login web.

### Skema (tabel `activity_log` dari paket)

Migrasi bawaan `spatie/laravel-activitylog` membuat tabel **`activity_log`** (jadi total tabel
**13 → 14**): kolom kunci `log_name`, `description`, `event`, `subject_type`/`subject_id` (objek),
`causer_type`/`causer_id` (aktor), `properties` (JSON diff + IP opsional), `created_at`. Tak perlu
skema buatan sendiri.

### Tampilan — halaman "Log Aktivitas"

Halaman Volt baru (admin), ikut bahasa visual & komponen seragam yang sudah ada:

- Route `Volt::route('activity-log', 'pages.aktivitas.index')->name('activity.index')` di grup
  `['auth']`; menu sidebar baru "Log Aktivitas" (`icon="o-clipboard-document-list"`).
- `<x-table-card>` read-only (tanpa aksi edit/hapus): kolom **Waktu · Aktor · Aksi · Objek ·
  Perubahan**. Aksi sebagai **pil warna** (pola `<x-status-pill>`): dibuat=success, diperbarui=info,
  dihapus=error, login=primary, login gagal=warning, logout=secondary.
- **Filter** lewat trait `WithTableFilters` + `<x-filter-chips>`: pencarian (deskripsi/aktor) +
  filter **jenis aksi** + filter **jenis objek** (laporan/pelanggan/pengguna/…) + opsional periode.
  Semua `rounded-full` sesuai bahasa visual.
- **Modal detail** menampilkan diff **lama → baru** per kolom (dari `properties`), + IP/waktu.

### Rencana implementasi (langkah)

1. `composer require spatie/laravel-activitylog`; publish config + migrasi; `php artisan migrate`
   (buat tabel `activity_log`).
2. Tambah trait `LogsActivity` + `getActivitylogOptions()` ke 5 model admin (`DamageReport`,
   `Customer`, `User`, `DamageType`, `CustomerPhoto`): `logOnlyDirty()` + `dontSubmitEmptyLogs()` +
   deskripsi Indonesia (dibuat/diperbarui/dihapus). **`User` wajib `logExcept(['password',
   'remember_token', 'fcm_token'])`.**
3. Tangani titik buta penugasan: catat assign/unassign **manual via `activity()`** di
   [SyncReportTechnicians](app/Actions/SyncReportTechnicians.php) (entri semantik terbaca). Jangan
   pasang trait di `TaskAssignment` (hindari entri ganda).
4. Listener auth: tangkap event `Login` / `Failed` / `Logout` Laravel → `activity()->log(...)`
   (login gagal catat email yang dicoba + IP). Daftarkan di provider event.
5. Nonaktifkan logging untuk grup route `/api` (middleware) → aksi teknisi tak tercatat.
6. (Opsional) Rekam **IP + user-agent** ke `properties` tiap entri (global tap / custom Activity model).
7. Halaman `pages/aktivitas/index.blade.php` + route + menu sidebar; `<x-table-card>` read-only +
   filter (`WithTableFilters`, `<x-filter-chips>`) + modal diff. **Tanpa** aksi tulis.
8. (Opsional) Retensi: `php artisan activitylog:clean` (paket menyediakannya; `delete_records_older_than_days`,
   default 365) — catat di [`CATATAN-FITUR.md`](CATATAN-FITUR.md) seperti cleanup `location_logs`,
   jadwalkan saat deploy VPS. Volume jauh lebih kecil dari GPS → tak mendesak.
9. Test `ActivityLogTest`: CRUD laporan → 1 entri (event & causer benar); **ubah password user →
   tak bocor** ke `properties`; pembatalan penugasan tercatat; login/login-gagal/logout tercatat;
   **mutasi via API teknisi TIDAK tercatat**; halaman render & **read-only** (tak ada jalur tulis).
   Tambah entri `PageRenderTest`.
10. Update [`CLAUDE.md`](CLAUDE.md): tabel **13 → 14** (`activity_log`), paket baru
    `spatie/laravel-activitylog` di tabel stack, menu "Log Aktivitas" + route, dokumentasi konvensi
    audit (read-only, admin-only, field sensitif dikecualikan, titik buta bulk-op); lalu pindahkan
    rencana ini ke Arsip.

### Dampak API Android 📱

**Nol.** Aksi teknisi via API sengaja **tidak** dicatat (keputusan #1) — tak ada perubahan kontrak
atau perilaku Android. Murni fitur web admin.

### Catatan / risiko

- **Kebocoran data sensitif** = risiko utama → `logExcept` password/token **wajib**, diuji eksplisit.
- **Append-only tumbuh** seiring waktu, tapi volume aksi admin jauh di bawah GPS — retensi opsional,
  bukan blocker.
- **Operasi massal melewati event** (titik buta penugasan di atas) — sudah dienumerasi; jaga aturan
  "aksi admin baru pakai operasi level-model atau `activity()` manual".
- **Causer kosong** untuk aksi sistem/seeder (bukan admin) — tampil "Sistem", wajar.
- **Nilai untuk skripsi:** jawaban konkret untuk pertanyaan akuntabilitas/integritas data —
  melengkapi pola `nullOnDelete`/snapshot yang sudah ada dengan **jejak siapa-kapan**.

---

## Arsip (rencana yang sudah selesai)

### ✅ 2. Catatan & bukti pekerjaan teknisi — selesai 2026-06-28

Menjawab kritik dosen "sisi Android kurang info pekerjaan" — teknisi kini bisa melampirkan
**catatan** + **foto bukti** saat bekerja, bukan cuma menekan tombol status. Dua lapis dikerjakan
keduanya; **backend dan Android rampung**. Yang terbangun:

- **Lapis A — Catatan pekerjaan (`work_logs.description`):**
  [`TaskController::updateStatus`](app/Http/Controllers/Api/TaskController.php) menerima `description`
  opsional (`nullable|string|max:1000`) → disimpan di work log transisi. Tampil di timeline Riwayat
  & PDF lengkap (tempatnya sudah tersedia) dan di API detail tugas.
- **Lapis B — Foto bukti (`report_photos`):** tabel baru (append-only, disk `public`,
  `cascadeOnDelete` ke laporan, `uploaded_by` `nullOnDelete`) + model `ReportPhoto` + relasi
  `DamageReport::photos()`. Endpoint **`POST /api/tasks/{id}/photos`** (multipart `photo` wajib
  gambar ≤5MB + `caption` opsional, di-scope kepemilikan tugas → 404 bila bukan miliknya).
  `GET /api/tasks/{id}` menyertakan `repair_photos`. Thumbnail tampil di modal detail Riwayat.
- **Fase 2 foto rumah (satu paket):** **`POST /api/tasks/{id}/house-photos`** → `customer_photos`
  (hanya kategori pelanggan, 422 bila bukan). Melengkapi Rencana #1 Fase 2.
- **Android** 📱: field catatan + ambil/unggah foto dari kamera + konfirmasi halus "Selesaikan tanpa
  catatan/foto?" — **sudah diimplementasi** di repo Android.
- **Test:** `TaskTest` (description tersimpan & tampil), `TaskPhotoTest` (unggah bukti & rumah,
  validasi gambar, scope kepemilikan, repair_photos di detail), `RiwayatCategoryTest` (modal detail
  tampilkan foto bukti).

> **Peningkatan lanjutan (di luar #2):** foto bukti kini juga tampil **LIVE saat `sedang_memperbaiki`**
> di halaman Monitoring — lihat [Rencana #7 → tambahan foto bukti live](#7-peningkatan-peta-monitoring--dashboard).
>
> Detail desain & keputusan lengkap tetap di [bagian #2 di atas](#2-catatan--bukti-pekerjaan-teknisi)
> sebagai catatan historis.

### ✅ 5. CRUD Jenis Kerusakan (damage_types) — selesai 2026-06-28

Jenis gangguan kini **data master yang dikelola admin** (bukan hanya seeder) + perbaikan semantik
model data. **107 test pass.** Yang terbangun:

- **Menu "Jenis Gangguan"** (`pages/jenis-gangguan/index.blade.php`, route `damage-types.index`):
  full CRUD + search + nama unik + kolom "Dipakai" (jumlah laporan). Pakai komponen seragam
  (`x-table-card`, trait `WithTableFilters`). Seeder diperkaya 5 → 11 jenis. Tabel `damage_types`
  dapat `updated_at` (migrasi `add_updated_at_to_damage_types`) & `DamageType` kini `timestamps`
  aktif — jejak waktu ubah tercatat (menutup catatan backlog `CATATAN-FITUR.md`).
- **Perbaikan FK (yang penting):** `damage_reports.damage_type_id` dijadikan **nullable** + FK
  `cascadeOnDelete` → **`nullOnDelete`** (migrasi `2026_06_28_000000_make_damage_type_optional...`).
  Semula hapus satu jenis akan **ikut menghapus laporan/riwayat** yang memakainya — kini laporan
  utuh, kolomnya jadi NULL (tampil "—"). Selaras prinsip "hapus data master tak menghapus arsip".
- **Jenis gangguan jadi opsional untuk non-pelanggan:** `ReportCategory::butuhJenisGangguan()`
  (true hanya `pelanggan`). Pemeliharaan/jaringan boleh tanpa jenis (pemeliharaan kerap bukan
  "kerusakan"). Label form/tabel diganti **"Jenis Gangguan/Pekerjaan"**. Semua pemakaian
  `damageType->name` dibuat null-safe (`?->name ?? '—'`).
- **Bonus presentasi kategori (menyambung #1 #9 & #3a):** kolom "Pelanggan" di tabel **Laporan &
  Riwayat** menyesatkan (judulnya "Pelanggan" tapi berisi `judul` = nama pelanggan ATAU judul
  pekerjaan). Header diganti **"Laporan"** + komponen baru **`<x-category-pill>`** (pelanggan=primary,
  jaringan=info, pemeliharaan=secondary) di tiap baris — jenis laporan kini tegas tanpa ambiguitas.

### ✅ 3. Dashboard analitik & filter laporan — selesai 2026-06-28

Memperkaya aplikasi dengan penyaringan kategori & analitik, **100 test pass**. Keputusan kecil yang
dulu ditunda dikunci: **halaman "Statistik" tersendiri** (menu sidebar baru, dashboard tetap ringkas)
+ **Chart.js via CDN** (dimuat global di `layouts.app`, pola sama Leaflet). Yang terbangun:

- **3a — Filter kategori:** `DamageReport::scopeRiwayatSelesai` dapat param ke-4 `$category` (opsional,
  backward-compatible). Halaman Riwayat dapat baris `<x-filter-chips field="filterCategory">`
  (trait `WithTableFilters` urus reset paginasi). Ekspor PDF ikut filter kategori
  (`ReportExportController` baca `category`, tampil di kop meta `kategoriLabel`; template
  `riwayat-ringkasan`/`riwayat-lengkap` dapat baris Kategori).
- **3b — Halaman Statistik** (`resources/views/livewire/pages/statistik.blade.php`, route `statistik`):
  pecahan laporan per kategori, rata-rata durasi penanganan (dihitung dari `completed_at`),
  **tren komplain 12 bulan** (Chart.js line; canvas di `wire:ignore`, init via `@script`),
  kinerja per teknisi (`withCount` task_assignments ditangani vs laporan selesai), kerusakan
  tersering top 5 (`DamageType::withCount('damageReports')`). Semua query DB-agnostic (jalan di
  MySQL & SQLite test). Menu sidebar "Statistik" + entri di `PageRenderTest`; `StatistikTest` baru.

### ✅ 1. Modul Pelanggan (Customers) — selesai 2026-06-27

Pelanggan kini jadi **entitas tersendiri** (`customers`) + laporan punya **kategori**
(`ReportCategory`: pelanggan/jaringan/pemeliharaan). Dikerjakan bertahap (batch 1–6c), **84 test
pass**. Yang terbangun:

- **Data layer:** tabel `customers` & `customer_photos`; `damage_reports` dapat `category` (default
  `pelanggan`), `customer_id` (FK `nullOnDelete`), `title`; `customer_name` jadi nullable (tetap jadi
  **snapshot**). Enum `CustomerStatus` & `ReportCategory` (sumber kebenaran, tak di-cast). Model
  `Customer`/`CustomerPhoto`; `DamageReport` dapat relasi `customer()` + accessor `judul`
  (`customer_name ?? title`).
- **Migrasi data lama:** `backfill_customers_from_damage_reports` (idempotent: dedup nama+alamat →
  pelanggan, isi `customer_id`; `phone` di-backfill kosong → admin lengkapi).
- **Halaman Pelanggan:** CRUD + filter status + search + **detail** (galeri foto rumah upload/hapus
  ke disk `public`, ringkasan komplain, riwayat perbaikan per pelanggan).
- **Form Laporan:** pemilih kategori (toggle) + search-select pelanggan (`x-choices-offline`) +
  validasi bersyarat (`butuhPelanggan()`) + snapshot otomatis.
- **Riwayat & PDF:** tampil `judul` + kategori (template `riwayat-*` + `pelanggan`); layout PDF
  pakai `@yield('meta')`.
- **API Android:** `TaskController` kirim `category` + `headline` + kontak pelanggan +
  `house_photos` (null-safe non-pelanggan); kontrak di `CLAUDE_ANDROID.md`.
- **Ekspor pelanggan:** PDF (dompdf) + **Excel** (`maatwebsite/excel`), ikut filter via scope
  tunggal `Customer::filtered`.

**Fase 2 — upload foto rumah dari Android — ✅ SELESAI (backend 2026-06-27, Android 2026-06-28):**
- Dibuat sebagai **`POST /api/tasks/{id}/house-photos`** (task-scoped, BUKAN `/customers/{id}/photos`
  mentah — otorisasi alami: teknisi hanya boleh menambah foto untuk laporan yang ditugaskan padanya;
  hanya kategori pelanggan, non-pelanggan → 422). Multipart `photo`+`caption` → `customer_photos`,
  `uploaded_by` dari token. Dikerjakan satu paket dengan foto bukti pekerjaan (Rencana #2).
- **UI Android sudah jadi** (tombol kamera kirim ke endpoint itu + detail tugas adaptif per kategori
  menampilkan foto rumah & kontak pelanggan). Kontrak di `CLAUDE_ANDROID.md`.
- **Catatan untuk Rencana #4:** endpoint task-scoped ini **tidak** otomatis melayani pendaftaran
  pelanggan baru #4 (pelanggan baru belum punya task) — lihat pergeseran endpoint di [bagian #4](#4-pendaftaran-pelanggan-oleh-teknisi-saat-pemasangan-baru).

> Detail desain & keputusan lengkap tetap tersimpan di [bagian #1 di atas](#1-modul-pelanggan-customers)
> sebagai catatan historis.
