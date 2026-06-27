# Aplikasi Monitoring Perbaikan Jaringan — SkyNet RT/RW Net

Aplikasi web admin + Android untuk monitoring perbaikan jaringan. Studi kasus skripsi, klien SkyNet RT/RW Net, Kab. Bekasi. Metode pengembangan: RAD.

## Stack Teknologi

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 (PHP ^8.3) |
| Realtime UI | Livewire v3 + Volt |
| UI Components | Mary UI (`robsontenorio/mary`) |
| CSS | Tailwind CSS v4 (CSS-first config) + DaisyUI v5 |
| Build Tool | Vite v8 |
| Peta | Leaflet.js + OpenStreetMap |
| Auth API | Laravel Sanctum (token-based, untuk Android) |
| Push Notification | Firebase FCM (`kreait/laravel-firebase v7`) |
| Ekspor PDF | `barryvdh/laravel-dompdf` (dompdf) |
| Ekspor Excel | `maatwebsite/excel` (PhpSpreadsheet) |
| Database | MySQL via Laragon |
| Mobile | Android (Kotlin) — repo terpisah |
| Dev Environment | Laragon (Windows) |

## Perintah Umum

```bash
# Jalankan semua service sekaligus (Laravel + Queue + Vite)
# Catatan: pail (log viewer) dilepas dari script — butuh ext pcntl yang tak ada di Windows
composer dev

# Setup awal project
composer setup

# Jalankan test (gunakan php artisan test jika composer tidak ada di PATH)
composer test
php artisan test

# Build assets
npm run build

# Dev assets saja
npm run dev
```

## Database

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aplikasi_monitoring
DB_USERNAME=root
DB_PASSWORD=
```

### Tabel (12 total)

| Tabel | Keterangan |
|---|---|
| `users` | Admin dan teknisi |
| `customers` | Pelanggan (entitas tersendiri); laporan kategori "pelanggan" menunjuk ke sini via FK |
| `customer_photos` | Foto rumah pelanggan (wayfinding); path file di disk `public`, append-only |
| `damage_types` | Jenis kerusakan jaringan |
| `damage_reports` | Laporan kerusakan dari admin (punya `category` + `customer_id` nullable + `title`) |
| `task_assignments` | Penugasan teknisi ke laporan |
| `work_logs` | Log aktivitas pekerjaan teknisi |
| `location_logs` | Log koordinat GPS teknisi (realtime) |
| `notifications` | Notifikasi untuk teknisi |
| `personal_access_tokens` | Token Sanctum untuk Android |
| `cache` | Laravel cache |
| `jobs` | Laravel queue jobs |

## Struktur Aplikasi

### Aktor & Alur

**Admin (Web)**
- Kelola laporan kerusakan
- Tugaskan teknisi ke laporan
- Monitor GPS teknisi secara realtime (Leaflet.js)
- Lihat riwayat perbaikan
- Manajemen user

**Teknisi (Android)**
- Login via API (Sanctum token)
- Lihat dan update status tugas
- GPS otomatis aktif saat status "Sedang Memperbaiki"

### Alur Status Tugas

```
Ditugaskan → Sedang Memperbaiki (GPS aktif) → Selesai (GPS berhenti)
```

### Halaman Web Admin (sudah diimplementasi)

1. Dashboard — stat cards data nyata + laporan terbaru
2. Manajemen Laporan — full CRUD + filter + search + assign teknisi + **pemilih kategori** (pelanggan vs jaringan/pemeliharaan)
3. Monitoring GPS — Leaflet.js realtime, polling 10 detik
4. Riwayat — laporan selesai + filter periode + detail modal + **ekspor PDF** (mode ringkasan & lengkap)
5. Pengguna — full CRUD admin & teknisi
6. Pelanggan — full CRUD + filter status + search + **detail** (galeri foto rumah + ringkasan + riwayat perbaikan per pelanggan) + **ekspor PDF & Excel**

### Layar Android Teknisi (direncanakan 6 layar)

1. Login
2. Daftar Tugas
3. Detail Tugas
4. Sedang Memperbaiki
5. Notifikasi
6. Profil

## Struktur File Kunci

```
app/
  Actions/
    SyncReportTechnicians.php       # Sync penugasan teknisi (diff-based) + notifikasi; dipanggil save()
                                    # laporan di dalam DB::transaction (simpan + sync atomik, anti setengah-jadi)
    GetActiveTechnicianLocations.php # Lokasi GPS terakhir teknisi aktif (1 query, anti N+1); dipakai monitoring & dashboard
  Http/Controllers/Api/ # AuthController, TaskController (detail tugas kirim category +
                        # headline + kontak pelanggan + house_photos, null-safe non-pelanggan),
                        # LocationController, NotificationController
  Http/Controllers/     # ReportExportController (ekspor PDF riwayat — web, bukan Volt),
                        # CustomerExportController (ekspor PDF + Excel pelanggan, ikut filter scope)
  Exports/              # CustomersExport (maatwebsite/excel: FromQuery+WithHeadings+WithMapping
                        # +ShouldAutoSize) — pakai scope Customer::filtered (sama dgn halaman+PDF)
  Models/               # DamageReport, DamageType, TaskAssignment,
                        # WorkLog, LocationLog, Notification, User, Customer, CustomerPhoto
                        # Customer: scope filtered($search,$status) = sumber tunggal filter
                        # (halaman Pelanggan + ekspor PDF/Excel); relasi reports()/photos().
                        # CustomerPhoto: append-only (created_at saja), path di disk public.
                        # DamageReport: category (enum ReportCategory) + customer_id (FK nullOnDelete)
                        # + title; accessor $report->judul = customer_name ?? title (headline tabel/
                        # PDF/API, tak peduli kategori); snapshot customer_name/address tetap disimpan;
                        # DamageReport: scope riwayatSelesai($search,$period);
                        # kolom completed_at = sumber kebenaran WAKTU SELESAI
                        # (jangan pakai updated_at untuk waktu/durasi/filter selesai);
                        # $report->waktu_selesai (accessor) & ->durasiPenanganan($singkat)
                        # = satu sumber tampilan waktu & durasi (riwayat + PDF)
                        # FK ke users: created_by (DamageReport) & technician_id (WorkLog)
                        # = nullOnDelete → hapus pengguna TIDAK menghapus riwayat; kolomnya
                        # jadi NULL (tampil "—"/"Teknisi dihapus"). task_assignments &
                        # location_logs sengaja TETAP cascade (penanda live, bukan arsip)
  Enums/
    ReportStatus.php    # Sumber kebenaran status laporan (dipakai PHP & query, hindari literal).
                        # Juga memegang tabel transisi API Android: apiTransitions() (peta
                        # in_progress/done → from/to), apiActions() (kosakata valid untuk in:),
                        # transitionForApiAction() — TaskController cuma orkestrator, bukan pemilik tabel
    UserRole.php        # Sumber kebenaran peran pengguna (admin/teknisi); pakai
                        # UserRole::X->value, hindari literal 'admin'/'teknisi'. Kolom
                        # users.role TIDAK di-cast (sama pola dengan status). options()
                        # untuk chip filter, values() untuk aturan validasi in:
    CustomerStatus.php  # Sumber kebenaran status langganan (aktif/isolir/berhenti); pola sama
                        # dgn UserRole (tidak di-cast, options()/values())
    ReportCategory.php  # Sumber kebenaran kategori laporan (pelanggan/jaringan/pemeliharaan);
                        # butuhPelanggan() = apakah kategori wajib customer_id (validasi form)
  Observers/
    NotificationObserver.php  # Auto-kirim FCM setiap Notification::create()
routes/
  web.php               # Volt routes (admin panel) + history/export + customers/export/{pdf,excel}
                        # + redirect `/`. Route ekspor pelanggan didaftar SEBELUM customers/{customer}
  api.php               # 9 API endpoints (Android via Sanctum); /auth/login throttle:5,1
resources/views/livewire/pages/
  dashboard.blade.php
  laporan/index.blade.php   # form punya pemilih kategori + search-select pelanggan (x-choices-offline)
  monitoring.blade.php
  riwayat.blade.php
  pengguna/index.blade.php
  pelanggan/index.blade.php  # CRUD pelanggan + filter status + search + ekspor PDF/Excel
  pelanggan/detail.blade.php # galeri foto rumah (upload/hapus) + ringkasan + riwayat perbaikan
resources/views/components/ # Komponen Blade reusable panel admin: status-pill,
                        # table-card, filter-chips, confirm-delete-modal (lihat "Bahasa Visual")
resources/views/pdf/    # Template dompdf: layout (kop + footer + @yield('meta')),
                        # riwayat-ringkasan, riwayat-lengkap, pelanggan
                        # (CSS inline, font DejaVu Sans; tiap template isi @section('meta') sendiri)
database/
  migrations/           # Semua migrasi tabel (+ backfill_customers_from_damage_reports = data lama)
  seeders/              # Seeder untuk semua tabel utama (+ CustomerSeeder)
  factories/            # UserFactory, DamageTypeFactory, DamageReportFactory (sadar kategori),
                        # CustomerFactory, CustomerPhotoFactory
config/
  firebase.php          # Konfigurasi kreait/laravel-firebase
tests/
  Feature/Api/          # AuthTest, TaskTest, LocationTest, NotificationApiTest
  Feature/Web/          # PageRenderTest (smoke halaman admin), StatusPillTest,
                        # SyncReportTechniciansTest,
                        # LaporanFormTest (integrasi save + atomicity rollback + validasi teknisi + nama hapus),
                        # FilterScopingTest (regresi search+filter status/role tidak bocor),
                        # GetActiveTechnicianLocationsTest (lokasi teknisi aktif, anti N+1),
                        # CompletedAtTest (completed_at + durasiPenanganan terpusat + modal),
                        # UserDeletionPreservesHistoryTest (hapus user → riwayat utuh, FK null),
                        # PenggunaDeletionGuardTest (deleteUser tolak hapus diri sendiri walau lewati confirmDelete),
                        # TableFiltersResetTest (trait WithTableFilters: ubah search/filter reset paginasi ke hal. 1),
                        # PelangganCrudTest (CRUD + opsional→null + hapus jaga laporan),
                        # PelangganDetailTest (render + upload/hapus foto + ringkasan stats),
                        # PelangganExportTest (ekspor PDF/Excel ikut scope filtered),
                        # RiwayatCategoryTest (judul/kategori di Riwayat + ekspor PDF non-pelanggan)
                        # TaskTest juga menguji catatan pekerjaan (description) tersimpan & tampil di detail
                        # (87 test cases, semua pass — 33 API + 54 Web)
```

## Routes & Endpoint

> Semua endpoint sudah diimplementasi.

### Web Admin — session-based auth

> Halaman admin = Volt component; aksi CRUD (simpan/edit/hapus/assign) dijalankan sebagai
> **Livewire action di dalam component**, bukan route HTTP REST terpisah. Tabel di bawah
> mendeskripsikan operasi secara konseptual; route HTTP riil yang terdaftar hanyalah halaman
> Volt + `history/export` + autentikasi (lihat `routes/web.php`).

**Autentikasi**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/login` | Halaman form login (admin-only) |
| POST | `/login` | Proses login; guard di `login.blade.php` menolak non-admin |
| — | (sidebar) | Logout = Livewire action (`App\Livewire\Actions\Logout`), bukan route HTTP |

> **Auth web sengaja admin-only.** TIDAK ada register / lupa-reset password / verifikasi email /
> halaman profil — semua scaffolding Breeze itu dihapus (app internal). Ganti password sendiri
> lewat menu **Pengguna** (admin boleh edit akunnya sendiri; yang dilarang hanya hapus diri sendiri).
> `/` redirect ke `dashboard` (sudah login) atau `login`. `User` TIDAK `MustVerifyEmail` → grup
> route web cukup `['auth']` (tanpa `verified`).

**Dashboard & Monitoring**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/dashboard` | Ringkasan laporan per status + peta teknisi aktif |

> Posisi teknisi di peta dashboard di-refresh lewat **Livewire event** `map-data-refreshed`
> (dispatch dari component, ditangkap `$wire.on(...)` di `@script`) — sama polanya dengan
> monitoring, BUKAN endpoint HTTP/JSON terpisah.

**Laporan Gangguan**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/reports` | Daftar semua laporan |
| GET | `/reports/create` | Form buat laporan |
| POST | `/reports` | Simpan laporan baru |
| GET | `/reports/{id}` | Detail + riwayat status + peta teknisi |
| GET | `/reports/{id}/edit` | Form edit laporan |
| PUT | `/reports/{id}` | Simpan perubahan laporan |
| DELETE | `/reports/{id}` | Hapus laporan |

**Penugasan Teknisi**
| Method | Path | Keterangan |
|---|---|---|
| POST | `/reports/{id}/assign` | Tugaskan 1 atau lebih teknisi |
| DELETE | `/reports/{id}/assign/{user_id}` | Batalkan penugasan teknisi |

**Manajemen Pengguna**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/users` | Daftar semua pengguna |
| POST | `/users` | Tambah admin atau teknisi baru |
| PUT | `/users/{id}` | Edit data pengguna |
| DELETE | `/users/{id}` | Hapus pengguna |

**Manajemen Pelanggan**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/customers` | Daftar pelanggan + filter status + search (aksi CRUD = Livewire action) |
| GET | `/customers/{customer}` | Detail pelanggan: galeri foto rumah (upload/hapus) + ringkasan + riwayat perbaikan |

**Ekspor** _(route HTTP riil)_
| Method | Path | Keterangan |
|---|---|---|
| GET | `/history` | Daftar laporan selesai + filter periode + detail modal |
| GET | `/history/export` | Ekspor PDF riwayat. Query: `mode` (`ringkasan`\|`lengkap`), `search`, `period` (`minggu`\|`bulan`). Mengikuti filter aktif, dibuka inline di tab baru |
| GET | `/customers/export/pdf` | Ekspor PDF daftar pelanggan. Query: `search`, `status`. Ikut filter aktif (scope `Customer::filtered`), inline tab baru |
| GET | `/customers/export/excel` | Ekspor Excel (.xlsx) daftar pelanggan. Query sama dgn PDF; unduh file |

---

### API Android — token-based auth via Sanctum (prefix `/api`)

**Autentikasi**
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/auth/login` | — | Login teknisi, return Sanctum token |
| POST | `/api/auth/logout` | token | Hapus token |
| PUT | `/api/auth/fcm-token` | token | Update FCM token untuk push notification |

**Tugas Teknisi**
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | `/api/tasks` | token | Daftar tugas milik teknisi yang login. Tiap item: `category` + `headline` + kontak pelanggan (`phone`/`ip_address`/`subscription_package`, null untuk non-pelanggan) |
| GET | `/api/tasks/{id}` | token | Detail tugas + riwayat status + `house_photos` (URL foto rumah; `[]` non-pelanggan). Kontrak lengkap di `CLAUDE_ANDROID.md` |
| POST | `/api/tasks/{id}/status` | token | Update status: `in_progress` atau `done`. Opsional `description` (catatan pekerjaan, `max:1000`) → disimpan di work log transisi, tampil di timeline Riwayat & API detail |

**GPS Tracking**
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/location` | token | Kirim koordinat GPS (berkala saat `in_progress`). Body: `report_id`, `latitude`, `longitude`, + opsional `recorded_at` (ISO8601, waktu GPS diambil di device; default = waktu terima server, jam masa depan diabaikan) |

**Notifikasi**
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | `/api/notifications` | token | Daftar notifikasi teknisi |
| PUT | `/api/notifications/{id}/read` | token | Tandai notifikasi sudah dibaca |

---

## Konvensi Kode

- Komponen UI menggunakan Mary UI — lihat dokumentasi di `robsontenorio/mary`
- FCM push notification dikirim otomatis via `NotificationObserver` setiap kali `Notification::create()` dipanggil — tidak perlu memanggil FCM manual di tempat lain
- `FIREBASE_CREDENTIALS` di `.env` wajib diisi path ke service account JSON Firebase (file JSON tidak boleh di-commit ke git, sudah ada di `.gitignore`)
- Halaman interaktif dibuat sebagai Livewire Volt component (bukan controller biasa)
- Response non-interaktif (ekspor PDF / download file) memakai **controller biasa** di
  `app/Http/Controllers/` (mis. `ReportExportController`), BUKAN Volt — Volt khusus halaman
  interaktif. Lihat subbagian "Ekspor PDF (dompdf)" di bawah
- Logika bisnis kompleks/berulang dikeluarkan ke **Action class** di `app/Actions/`
  (mis. `SyncReportTechnicians` — sync penugasan + notifikasi, dipakai bersama alur buat
  & edit laporan; `GetActiveTechnicianLocations` — lokasi GPS teknisi aktif, dipakai bersama
  monitoring & dashboard), bukan ditanam di dalam method Volt component
- API untuk Android menggunakan prefix `/api` dengan auth `sanctum`
- **Laporan punya kategori** (`ReportCategory`): kategori `pelanggan` wajib `customer_id` (pilih
  pelanggan terdaftar lewat `x-choices-offline`; `customer_name`/`address` disimpan sebagai
  **snapshot** saat simpan), kategori `jaringan`/`pemeliharaan` pakai `title` + `address` (lokasi),
  `customer_id`/`customer_name` NULL. Validasi bersyarat lewat `ReportCategory::butuhPelanggan()`.
  Tampilan headline mana pun kategorinya pakai accessor `$report->judul` (`customer_name ?? title`).
  Hapus pelanggan TIDAK menghapus laporan (FK `nullOnDelete`) — snapshot tetap utuh
- **Foto rumah pelanggan** disimpan di disk `public` (`storage:link` wajib aktif); upload/hapus di
  detail pelanggan (Fase 1 web). Skema `customer_photos.uploaded_by` sudah siap untuk upload dari
  Android (Fase 2) tanpa ubah tabel
- Role middleware ada di `app/Http/Middleware/RoleMiddleware.php`
- Gunakan `composer dev` untuk menjalankan semua service, bukan `php artisan serve` saja
- `SESSION_DRIVER=file` (bukan database — tabel sessions tidak dibuat)

### Prefix Komponen Mary UI

Mary UI punya dua kelompok prefix. **Jangan pakai `x-mary-*` untuk komponen yang tidak ada di daftar hardcoded.**

**Hardcoded `x-mary-*` (gunakan prefix ini):**
```
x-mary-button   x-mary-card     x-mary-icon     x-mary-input
x-mary-modal    x-mary-menu     x-mary-menu-item x-mary-header
x-mary-pagination  x-mary-popover  x-mary-list-item
```

**Tanpa prefix — cukup `x-*` (prefix kosong dari config):**
```
x-select    x-textarea  x-stat      x-badge
x-nav       x-main      x-table     x-tabs      x-tab
x-avatar    x-alert     x-checkbox  x-toggle    x-radio
x-toast
```
> Catatan: `toast` TIDAK ada di daftar hardcoded `mary-*`, jadi tag-nya `x-toast`
> (bukan `x-mary-toast` — itu memicu error "Unable to locate component"). Pasang
> `<x-toast />` sekali di `layouts.app`, lalu panggil `$this->success('...')` /
> `error()` / `warning()` dari komponen yang memakai trait `Mary\Traits\Toast`.

### Konfigurasi CSS-First (Tailwind v4 + DaisyUI v5)

Sejak migrasi ke Tailwind v4, **tidak ada lagi** `tailwind.config.js` maupun `postcss.config.js`.
Semua konfigurasi ada di `resources/css/app.css` (CSS-first):

- `@import "tailwindcss"` menggantikan `@tailwind base/components/utilities`
- `@source "..."` mendaftarkan sumber kelas di folder vendor (Mary UI + paginasi Laravel);
  `resources/views` terdeteksi otomatis
- Plugin via `@plugin` (`@plugin "@tailwindcss/forms"`, `@plugin "daisyui"`)
- Tema `skynet` & `skynet-dark` via `@plugin "daisyui/theme" { ... }` — terdaftar sebagai
  tema DaisyUI yang sesungguhnya, bukan lagi CSS-var manual di `html[data-theme]`
- Font default via `@theme { --font-sans: ... }`
- Build memakai `@tailwindcss/vite` di `vite.config.js` (bukan PostCSS)

**Opacity modifier kini BERFUNGSI** (beda dengan setup v3 lama). Karena warna tema terdaftar
sebagai tema DaisyUI, modifier opacity ter-generate normal via `color-mix`:

```
✅ bg-primary/10   border-info/30   text-success/50   text-base-content/50
✅ bg-primary      text-primary-content    bg-base-200
```

Tetap tulis kelas warna sebagai **literal lengkap** (jangan dirangkai `bg-{{ $c }}/10`)
agar terdeteksi scanner Tailwind.

**Perubahan sintaks penting di v4 (jangan pakai pola v3 lama):**

```
❌ !mb-6      !p-0      !w-7              (prefix !important v3 — TIDAK berlaku di v4, kelas diabaikan)
✅ mb-6!      p-0!      w-7!              (suffix !important v4)
✅ badge-soft badge-info                  (varian "soft" DaisyUI 5 — pil bertint lembut untuk status)
✅ no-scrollbar                           (@utility custom di app.css: scroll jalan, bar disembunyikan)
```

Komponen `<x-avatar>` placeholder DaisyUI 5 memakai kelas `avatar avatar-placeholder`
(bukan `avatar placeholder` gaya v4 lama). Forms plugin dipasang `strategy: class` supaya
tidak me-reset `<input>` global — styling form sepenuhnya dipegang DaisyUI (`.input`).

**Focus state form di-override** (di `@layer utilities` pada `app.css`). DaisyUI default
men-set `--input-color: base-content` (≈ hitam) lalu `outline: 2px solid + offset 2px` pada
`.input/.select/.textarea` saat fokus → kotak hitam kasar. Override mengganti jadi ring
`primary` lembut (`--input-color: var(--color-primary)` + `outline` via
`color-mix(in oklab, var(--color-primary) 35%, transparent)`, `outline-offset: 1px`).
Ditaruh di layer `utilities` agar menang atas aturan `.input:focus` milik DaisyUI (layer
`components`). Berlaku global ke semua field — jangan kembalikan ke default DaisyUI.

### Bahasa Visual / Konvensi UI Panel Admin

Semua halaman admin memakai konvensi tampilan seragam — ikuti saat membuat komponen baru:

- **Semua tombol & search box** = pil → tambahkan `rounded-full`, TANPA kecuali: tombol
  header, chip filter, search box, tombol ikon aksi tabel (`btn-xs`), dan tombol footer
  modal (Batal/Simpan/Hapus/Tutup) (mis. `class="input-sm w-56 rounded-full"`,
  `class="btn-primary btn-sm rounded-full"`, `class="btn-ghost btn-xs rounded-full"`)
- **Card** = `rounded-2xl` (stat card div & `<x-mary-card class="rounded-2xl">`).
  Kartu/kontainer di DALAM card atau modal (kartu pilihan, chip ringkas) = `rounded-xl`
- **Indikator status / role / hitungan** = pil soft + titik warna (dot+pill), BUKAN `<x-badge>`:

```blade
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-info/15 text-info">
    <span class="w-1.5 h-1.5 rounded-full bg-info"></span> Memperbaiki
</span>
```

  Pemetaan warna status: `ditugaskan`=warning, `sedang_memperbaiki`=info, `selesai`=success.
  Role: admin=primary, teknisi=secondary. Indikator "hidup" (Live, GPS aktif) tambahkan
  `animate-pulse` pada titiknya. Tulis kelas warna sebagai literal lengkap (`bg-info/15`,
  `text-info`) di dalam `match()` agar terdeteksi scanner.
  **Status laporan punya komponen siap pakai**: `<x-status-pill :status="$report->status" />`
  (+ atribut `pulse` untuk titik berdenyut) — pakai itu, jangan menyalin `match()`. Pola dot+pill
  manual di atas tetap untuk indikator lain (role, hitungan) atau label kontekstual (mis. timeline
  work-log di riwayat yang memakai "Mulai Memperbaiki").
- Wrapper tabel selalu `overflow-x-auto no-scrollbar`.
- **Kerangka tabel punya komponen siap pakai**: `<x-table-card :rows="$this->reports"
  empty-icon="o-document-text" empty-text="...">` membungkus card `rounded-2xl` + empty-state +
  wrapper `overflow-x-auto no-scrollbar` + `<table>`/`<thead>` berstyle + `<x-mary-pagination>`.
  Isi `<x-slot:head>` dengan daftar `<th>` (dibungkus `<tr>` berstyle di dalam komponen) dan slot
  default dengan baris `<tr>` (mis. `@foreach`, tanpa `<tbody>`). Dipakai bersama halaman
  Laporan/Pengguna/Riwayat — jangan menyalin markup card+tabel manual.
- **Chip filter & modal hapus punya komponen siap pakai** (hilangkan duplikasi antar halaman
  Laporan/Pengguna/Riwayat): `<x-filter-chips :options="..." field="filterStatus" :selected="$filterStatus" />`
  (grup pil filter; `options` = array value=>label, mis. `ReportStatus::options()`; `field` = nama
  properti Livewire yang di-set saat klik) dan `<x-confirm-delete-modal title="Hapus X" noun="x"
  :name="$deletingName" action="deleteX" />` (modal konfirmasi; mengandalkan properti `showDeleteModal`
  & method `deleteX` di komponen induk). Pakai itu, jangan menyalin markup chip/modal manual.
- **Pilihan radio/checkbox bentuk kartu** (mis. pemilih teknisi di modal laporan, role di
  modal pengguna): bungkus `<input>` dalam `<label>`, highlight via `has-checked:` (CSS murni,
  instan tanpa round-trip Livewire) — `class="... border border-base-300 has-checked:border-primary
  has-checked:bg-primary/5"`. Pakai `has-checked:` (kanonik v4), BUKAN `has-[:checked]:`.
- **Input di modal** beri `icon="o-..."` kontekstual (nama=`o-user`, alamat=`o-map-pin`,
  email=`o-envelope`, password=`o-lock-closed`, dll.) agar mudah dipindai.

### Pola Volt Component (Livewire Volt)

Semua halaman admin menggunakan pola ini:

```php
<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    // properties & methods
}; ?>

<div>
    <x-mary-header title="..." separator class="mb-6!">
        <x-slot:actions>...</x-slot:actions>
    </x-mary-header>
    <!-- konten halaman -->
</div>
```

- Route didaftarkan via `Volt::route()` di `routes/web.php`
- View disimpan di `resources/views/livewire/pages/`
- Layout wrapper: `layouts.app` (sidebar + topbar mobile)
- Computed properties pakai attribute `#[Computed]` + `unset($this->propertyName)` untuk invalidasi cache
- **Halaman tabel + filter pakai trait `App\Livewire\Concerns\WithTableFilters`** (gantikan
  boilerplate `updatedSearch()`/`updatedFilterX()` yang dulu diulang di Laporan/Pengguna/Riwayat).
  Trait memakai hook generik `updated()`: tiap properti `search` atau berawalan `filter` berubah →
  `resetPage()` + unset computed tabel. Komponen wajib pakai `WithPagination` & mendeklarasikan
  `tableComputed()` (nama #[Computed] tabel, mis. `return 'reports';`). Konvensi nama filter
  (`search` + prefix `filter`) sudah seragam — patuhi saat menambah filter baru

### Ekspor PDF (barryvdh/laravel-dompdf) & Excel (maatwebsite/excel)

Halaman Riwayat (PDF) & Pelanggan (PDF + Excel) bisa diekspor. Pola wajib saat menambah ekspor:

- Endpoint = **controller biasa** (`ReportExportController@riwayat` route `history/export`;
  `CustomerExportController@pdf|@excel` route `customers/export/{pdf,excel}` — semua di grup
  `['auth']`), BUKAN Volt — Volt tidak cocok untuk response file.
- Template di `resources/views/pdf/`: `layout.blade.php` (kop teks + footer nomor halaman via
  `counter(page)/counter(pages)`; blok meta filter = `@yield('meta')` → **tiap template wajib isi
  `@section('meta')` sendiri**), `riwayat-ringkasan.blade.php` (A4 landscape), `riwayat-lengkap.blade.php`
  (A4 portrait + timeline work logs, `page-break-inside: avoid`), `pelanggan.blade.php` (A4 landscape).
- dompdf **tidak membaca Tailwind/asset Vite** → semua CSS ditulis **inline** di `<style>` template.
  Pakai font `DejaVu Sans` (bawaan dompdf; aman untuk karakter Indonesia & en/em dash `– —`).
- Set `\Carbon\Carbon::setLocale('id')` di controller sebelum `translatedFormat(...)` supaya nama
  bulan/hari berbahasa Indonesia — locale app default `en`.
- `$pdf->stream(...)` = tampil inline di tab baru (tombol pakai `target="_blank"`); pakai
  `download()` bila ingin paksa unduh.
- **Excel** = class di `app/Exports/` (mis. `CustomersExport`) implement `FromQuery` + `WithHeadings`
  + `WithMapping` (+`ShouldAutoSize`), dikembalikan via `Excel::download(new ..., 'nama.xlsx')`. Butuh
  ext `zip`/`gd` (ada di Laragon). Test pakai `Excel::fake()` + `Excel::assertDownloaded(...)`.
- Filter **dipusatkan di satu scope** agar halaman Volt + PDF + Excel memakai query identik (sumber
  kebenaran tunggal): `DamageReport::scopeRiwayatSelesai($search,$period)` (riwayat),
  `Customer::scopeFiltered($search,$status)` (pelanggan).
- Tombol pakai `<x-dropdown>` (BUKAN `x-mary-dropdown` — `dropdown` tak ada di daftar hardcoded
  `mary-*`); mode Lengkap riwayat memunculkan `confirm()` JS bila hasil > 30 laporan.

### Status Mapping API Android ↔ Database

Android mengirim nilai berbeda dari yang disimpan di DB — ini hidden contract:

| Android kirim | DB menyimpan |
|---|---|
| `in_progress` | `sedang_memperbaiki` |
| `done` | `selesai` |
| — | `ditugaskan` (hanya dari web admin) |

Transisi hanya boleh searah: `ditugaskan` → `in_progress` → `done`. Loncat tidak diizinkan.

**Status milik bersama (level laporan), bukan per-teknisi.** Satu laporan bisa
ditugaskan ke beberapa teknisi; status menggambarkan keadaan PEKERJAAN, bukan tiap
individu. Karena itu `TaskController::updateStatus` bersifat **idempotent**: bila
laporan sudah berada di status tujuan (teknisi lain di tim sudah memindahkannya lebih
dulu), permintaan dianggap sukses (200) tanpa transisi ulang & tanpa work log ganda —
bukan ditolak 422. Konsekuensi yang disengaja: teknisi mana pun yang ditugaskan boleh
memulai/menutup pekerjaan untuk seluruh tim. (Loncat status tetap ditolak 422.)

Karena status milik bersama, `updateStatus` membungkus pengecekan + penulisan work log
dalam `DB::transaction` dengan `lockForUpdate()` pada baris laporan — dua teknisi yang
menekan tombol nyaris bersamaan tidak akan menghasilkan work log ganda (race terkunci di
baris, bukan hanya ditebak idempotent). `lockForUpdate` no-op di SQLite (test) tapi query
tetap jalan.

**Sumber kebenaran nilai status DB = enum `App\Enums\ReportStatus`** (`Ditugaskan`,
`SedangMemperbaiki`, `Selesai`). Di query & logika bisnis pakai `ReportStatus::X->value`, JANGAN
tulis string literal (typo jadi bug senyap). Pemetaan Android `in_progress`/`done` → transisi enum
juga tinggal di `ReportStatus` (`apiTransitions()`/`transitionForApiAction()`/`apiActions()`);
`TaskController` hanya memanggilnya sebagai orkestrator, bukan menyimpan tabel transisi sebagai
literal. Pengecualian: blok `match()` warna/label status di blade masih literal karena kelas
Tailwind wajib berada di view (aturan scanner) — sudah diekstrak ke komponen `<x-status-pill>`.

### Leaflet.js + Livewire — Pola Wajib

Leaflet memanipulasi DOM langsung. Ada dua aturan wajib agar tidak konflik dengan Livewire:

**1. `wire:ignore` pada container peta**
```html
<div wire:ignore>
    <div id="my-map" style="height:400px;"></div>
</div>
```
Tanpa ini, `wire:poll` atau re-render apapun akan menghapus peta karena Livewire morphdom mengganti DOM.

**2. `@script` untuk inisialisasi, bukan inline `<script>`**
```blade
@script
<script>
    const el = document.getElementById('my-map');
    if (!el || el._leaflet_id) return; // cegah double-init
    const map = L.map(el).setView([...], 12);
    // ...
    $wire.on('event-name', ({ data }) => { /* update marker */ });
</script>
@endscript
```
Inline `<script>` biasa tidak jalan saat `wire:navigate`. `@script` dijamin jalan setelah component mount, dan punya akses ke `$wire`.

**Alur data di monitoring.blade.php:**
- `wire:poll.10s="loadLocations"` → Livewire re-render sidebar + `$this->dispatch('locations-updated', locations: $result)`
- `$wire.on('locations-updated')` di `@script` → update marker tanpa reset view
- `viewInitialized` flag → `fitBounds` hanya sekali saat load pertama, bukan tiap poll
- Klik sidebar → Alpine `$dispatch('focus-technician', {id})` → `document.addEventListener` di `@script` zoom ke marker
