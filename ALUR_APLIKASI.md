# Alur Aplikasi: Monitoring Perbaikan Jaringan

> Dokumen ini dibuat untuk membantu kamu memahami **bagaimana aplikasimu sendiri bekerja**,
> dari sudut pandang "ini kodenya, ini yang terjadi di balik layar."
>
> Cara membaca yang disarankan: baca Bab 1–4 dulu (gambaran besar + alur), lalu loncat ke
> bab teknis (5+) sesuai bagian yang sedang ingin kamu kerjakan. Jangan dihafal — ikuti
> satu alur dari ujung ke ujung sambil membuka file yang disebut.

---

## Daftar Isi

1. [Gambaran Besar](#1-gambaran-besar)
2. [Siapa yang Menggunakan Aplikasi Ini?](#2-siapa-yang-menggunakan-aplikasi-ini)
3. [Database: Pondasi Segalanya](#3-database-pondasi-segalanya)
4. [Alur Kerja Utama (End-to-End)](#4-alur-kerja-utama-end-to-end)
5. [Bagaimana Laravel Bekerja di Sini](#5-bagaimana-laravel-bekerja-di-sini)
6. [Livewire Volt: Halaman Interaktif Tanpa Reload](#6-livewire-volt-halaman-interaktif-tanpa-reload)
7. [Enum: Sumber Kebenaran Status & Role](#7-enum-sumber-kebenaran-status--role)
8. [Action Class: Logika Bisnis yang Dipakai Bersama](#8-action-class-logika-bisnis-yang-dipakai-bersama)
9. [API untuk Android: Cara Kerja Sanctum Token](#9-api-untuk-android-cara-kerja-sanctum-token)
10. [Update Status Tugas: Idempotent & Anti-Balapan](#10-update-status-tugas-idempotent--anti-balapan)
11. [GPS Realtime: Dari Android ke Peta Web](#11-gps-realtime-dari-android-ke-peta-web)
12. [Push Notification: Cara Kerja NotificationObserver](#12-push-notification-cara-kerja-notificationobserver)
13. [`completed_at`: Mengapa Bukan `updated_at`?](#13-completed_at-mengapa-bukan-updated_at)
14. [Hapus Pengguna Tanpa Menghapus Riwayat](#14-hapus-pengguna-tanpa-menghapus-riwayat)
15. [Ekspor PDF: Mengapa Pakai Controller Biasa?](#15-ekspor-pdf-mengapa-pakai-controller-biasa)
16. [Peta Leaflet.js: Mengapa Ada `wire:ignore`?](#16-peta-leafletjs-mengapa-ada-wireignore)
17. [Komponen & Trait Reusable Panel Admin](#17-komponen--trait-reusable-panel-admin)
18. [Tailwind v4 + DaisyUI: Konfigurasi CSS-First](#18-tailwind-v4--daisyui-konfigurasi-css-first)
19. [Ringkasan Alur Per Fitur](#19-ringkasan-alur-per-fitur)
20. [Struktur Direktori Penting](#20-struktur-direktori-penting)
21. [Konfigurasi Lingkungan (.env)](#21-konfigurasi-lingkungan-env)
22. [Tips Belajar Selanjutnya](#22-tips-belajar-selanjutnya)

---

## 1. Gambaran Besar

Aplikasi ini punya **dua sisi**:

```
┌─────────────────────────────┐      ┌──────────────────────────────┐
│       WEB (Admin)           │      │       ANDROID (Teknisi)      │
│  Browser → Laravel + Livewire│      │  Kotlin App → REST API       │
│                             │      │                               │
│  - Buat laporan kerusakan   │      │  - Lihat tugas               │
│  - Tugaskan teknisi         │◄────►│  - Update status             │
│  - Monitor GPS realtime     │      │  - Kirim koordinat GPS       │
│  - Lihat riwayat + PDF      │      │  - Terima push notification  │
└─────────────────────────────┘      └──────────────────────────────┘
                │                                    │
                └──────────── MySQL Database ─────────┘
```

Keduanya mengakses **database yang sama**. Bedanya hanya cara membuktikan "siapa kamu":
- **Web (admin)** pakai **session** (cookie di browser, dibuat saat login form).
- **Android (teknisi)** pakai **token Sanctum** (string rahasia yang dikirim di setiap request).

Filosofi penting yang berulang di banyak tempat di kode ini: **satu sumber kebenaran**
(*single source of truth*). Nilai status ditulis di satu enum, logika penugasan ditulis di
satu Action class, query riwayat ditulis di satu scope. Jadi kalau ada perubahan, kamu cukup
mengubahnya di satu tempat, dan semua pemakainya ikut benar.

---

## 2. Siapa yang Menggunakan Aplikasi Ini?

Ada dua role di tabel `users`, ditulis di enum [app/Enums/UserRole.php](app/Enums/UserRole.php):

| Role | Akses | Cara Login |
|---|---|---|
| `admin` | Web saja | Session (form login biasa) |
| `teknisi` | Android saja | Token Sanctum via API |

**Cara cek di kode:**

Di [app/Models/User.php](app/Models/User.php), ada dua helper method. Perhatikan keduanya
membandingkan dengan **enum**, bukan string mentah:

```php
public function isAdmin(): bool   { return $this->role === UserRole::Admin->value; }
public function isTeknisi(): bool { return $this->role === UserRole::Teknisi->value; }
```

Di [app/Http/Middleware/RoleMiddleware.php](app/Http/Middleware/RoleMiddleware.php), middleware ini
mencegah teknisi membuka halaman web (dan sebaliknya).

> **Catatan auth web sengaja admin-only.** TIDAK ada halaman register / lupa password / verifikasi
> email / profil — semua scaffolding bawaan Laravel Breeze dihapus karena ini app internal. Admin
> mengganti password sendiri lewat menu **Pengguna** (boleh edit akun sendiri; yang dilarang hanya
> menghapus diri sendiri). Karena tidak ada verifikasi email, grup route web cukup `['auth']`
> (TANPA `verified`).

---

## 3. Database: Pondasi Segalanya

Ini adalah 10 tabel dan hubungannya. Pahami ini dulu sebelum baca kode lain.

```
users
 ├── damage_reports (created_by → users.id)   ← saat user dihapus: created_by jadi NULL
 │    ├── task_assignments (report_id, technician_id)   ← ikut terhapus bila report/user dihapus
 │    ├── work_logs        (report_id, technician_id)   ← technician_id jadi NULL bila user dihapus
 │    └── location_logs    (report_id, technician_id)   ← ikut terhapus bila report/user dihapus
 └── notifications (user_id → users.id)

damage_types
 └── damage_reports (damage_type_id → damage_types.id)
```

**Tabel kunci yang perlu dimengerti:**

### `damage_reports` — Inti dari semuanya
Setiap laporan kerusakan dari pelanggan. Punya kolom `status` yang berubah sepanjang alur:

```
ditugaskan → sedang_memperbaiki → selesai
```

> **Perhatikan:** laporan dibuat **langsung berstatus `ditugaskan`** (lihat default kolom di
> migrasi dan `save()` di halaman Laporan). Tidak ada status "null/belum ditugaskan" — saat admin
> membuat laporan, ia sekaligus memilih teknisi pada modal yang sama.

Kolom penting lain: `completed_at` (waktu selesai sebenarnya — lihat [Bab 13](#13-completed_at-mengapa-bukan-updated_at)),
dan `created_by` (admin pembuat; bisa NULL bila admin dihapus).

### `task_assignments` — Penghubung laporan & teknisi
Tabel ini menjawab: *"Teknisi mana yang mengerjakan laporan mana?"*
Satu laporan bisa punya banyak teknisi (1 laporan : banyak assignment). Ini "penanda hidup",
bukan arsip — karena itu ia ikut terhapus (cascade) bila laporan atau teknisinya dihapus.

### `work_logs` — Rekam jejak perubahan status
Setiap kali status berubah (`ditugaskan` → `sedang_memperbaiki` → `selesai`),
satu baris ditambahkan ke tabel ini. Inilah yang muncul sebagai "timeline" di detail tugas Android
dan di PDF riwayat lengkap. Ini **arsip**, jadi `technician_id`-nya jadi NULL (bukan ikut terhapus)
bila teknisinya dihapus.

### `location_logs` — Koordinat GPS teknisi
Setiap kali Android teknisi kirim GPS, koordinatnya disimpan di sini.
Peta di web membaca baris **terbaru per teknisi** dari tabel ini (lihat [Bab 11](#11-gps-realtime-dari-android-ke-peta-web)).

---

## 4. Alur Kerja Utama (End-to-End)

Ini adalah skenario lengkap dari awal sampai akhir:

### Langkah 1: Admin buat laporan + pilih teknisi (dalam satu langkah)
- Admin buka `/reports` → klik "Tambah Laporan"
- Isi: nama pelanggan, alamat, jenis kerusakan, catatan, **dan pilih satu/lebih teknisi**
- Saat "Simpan", di dalam **satu `DB::transaction`** ([laporan/index.blade.php](resources/views/livewire/pages/laporan/index.blade.php), method `save()`):
  1. Baris baru dibuat di `damage_reports` dengan `status = 'ditugaskan'`
  2. Action `SyncReportTechnicians` membuat baris di `task_assignments` (satu per teknisi)
  3. Action yang sama membuat baris di `notifications` untuk tiap teknisi baru
  4. **Secara otomatis** `NotificationObserver` mengirim push notification FCM ke HP teknisi

  > Mengapa satu transaksi? Supaya tidak ada "laporan setengah jadi" — kalau pembuatan
  > penugasan gagal di tengah, pembuatan laporannya ikut dibatalkan (rollback).

### Langkah 2: Teknisi terima notifikasi & mulai kerja
- Android teknisi dapat push notification: "Tugas Baru Ditugaskan"
- Teknisi buka app → login → lihat daftar tugas
- Klik "Mulai Perbaiki" → Android kirim `POST /api/tasks/{id}/status` dengan `status: "in_progress"`
- Server ubah `damage_reports.status` → `'sedang_memperbaiki'`, lalu catat di `work_logs`

### Langkah 3: GPS aktif selama pengerjaan
- Saat status `sedang_memperbaiki`, Android otomatis kirim GPS tiap beberapa detik
- `POST /api/location` → koordinat disimpan ke `location_logs`
- Web admin yang membuka halaman Monitoring (atau dashboard) melihat posisi teknisi di peta

### Langkah 4: Teknisi selesai
- Teknisi klik "Tandai Selesai" → `POST /api/tasks/{id}/status` dengan `status: "done"`
- Server ubah `damage_reports.status` → `'selesai'` **dan isi `completed_at = now()`**
- GPS berhenti (Android tidak kirim lagi karena status sudah bukan `sedang_memperbaiki`;
  server pun akan menolak GPS untuk laporan non-aktif)

### Langkah 5: Admin lihat riwayat
- Laporan masuk ke halaman `/history`
- Admin bisa filter per minggu/bulan, cari nama pelanggan/alamat
- Bisa ekspor ke PDF (mode ringkasan / lengkap)

---

## 5. Bagaimana Laravel Bekerja di Sini

### Route Web (`routes/web.php`)

```php
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'));

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('reports',   'pages.laporan.index')->name('reports.index');
    Volt::route('monitoring','pages.monitoring')->name('monitoring');
    Volt::route('history',   'pages.riwayat')->name('history');
    Route::get('history/export', [ReportExportController::class, 'riwayat'])->name('history.export');
    Volt::route('users',     'pages.pengguna.index')->name('users.index');
});
```

Dua hal penting di sini:

1. **`Volt::route(...)` ≠ `Route::get(...)` biasa.** Ia mendaftarkan halaman Livewire Volt yang
   **sekaligus mengurus tampilan DAN logika** dalam satu file `.blade.php`. Aksi CRUD
   (simpan/edit/hapus/assign) BUKAN route HTTP REST terpisah — semuanya **Livewire action di
   dalam component** (lihat [Bab 6](#6-livewire-volt-halaman-interaktif-tanpa-reload)).
   Satu-satunya route HTTP "biasa" di sini adalah `history/export` (ekspor PDF, lihat [Bab 15](#15-ekspor-pdf-mengapa-pakai-controller-biasa)).

2. **Middleware `['auth']`** (tanpa `verified`) artinya: hanya user yang sudah login yang bisa
   akses; kalau belum login, otomatis diarahkan ke `/login`.

### Route API (`routes/api.php`)

```php
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tasks',              [TaskController::class, 'index']);
    Route::post('/tasks/{id}/status', [TaskController::class, 'updateStatus']);
    Route::post('/location',          [LocationController::class, 'store']);
    // ...dst
});
```

- `auth:sanctum` artinya: Android harus kirim header `Authorization: Bearer {token}` di setiap
  request. Tanpa token, server balas `401 Unauthorized`.
- `throttle:5,1` di endpoint login = maksimal 5 percobaan per menit per IP, untuk mencegah
  brute-force password.

---

## 6. Livewire Volt: Halaman Interaktif Tanpa Reload

Ini yang membuat halaman web terasa seperti aplikasi (tidak full reload saat aksi).

Contoh pola dasarnya:

```php
<?php
// ===== BAGIAN PHP — logika server =====
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {

    public string $search = '';        // ← property: nilainya "hidup" antara browser & server

    #[Computed]                         // ← di-cache selama 1 request, dipanggil seperti property
    public function totalLaporan(): int
    {
        return DamageReport::count();   // ← query ke database
    }

    public function hapus(int $id): void   // ← Livewire action, dipicu dari wire:click
    {
        DamageReport::findOrFail($id)->delete();
        unset($this->reports);          // ← buang cache computed agar dihitung ulang
    }
}; ?>

{{-- ===== BAGIAN HTML — tampilan ===== --}}
<div>
    {{ $this->totalLaporan }}                {{-- panggil computed di atas --}}
    <input wire:model.live="search">         {{-- ketik → server tahu --}}
    <button wire:click="hapus(5)">Hapus</button>
</div>
```

**Cara kerjanya:**
1. Saat halaman dibuka, Livewire kirim satu request → server render HTML penuh → kirim ke browser.
2. Saat user klik tombol (`wire:click`) atau mengetik (`wire:model.live`), Livewire kirim **AJAX
   request** ke server.
3. Server menjalankan ulang component, hitung ulang, kirim balik HTML baru.
4. Livewire membandingkan HTML lama vs baru (*DOM diffing*) dan hanya mengganti bagian yang berbeda
   — **tanpa reload penuh**.

**Konsep yang harus kamu kenali:**
- **`#[Computed]`** = method yang hasilnya di-cache selama satu request. Untuk memaksa hitung
  ulang (mis. setelah hapus data), panggil `unset($this->namaMethod)`.
- **`wire:model`** = mengikat input HTML ke property PHP.
- **`wire:click="method"`** = memanggil method PHP saat diklik.
- **`wire:poll.10s="method"`** = panggil method tiap 10 detik otomatis (dipakai di Monitoring).

> File halaman ada di `resources/views/livewire/pages/`. Layout pembungkus (sidebar + topbar)
> ada di `layouts.app`.

---

## 7. Enum: Sumber Kebenaran Status & Role

Ini perubahan penting dibanding versi awal aplikasi: nilai status & role **tidak lagi ditulis
sebagai string mentah** (`'selesai'`, `'admin'`) yang tersebar di mana-mana. Sekarang ada dua enum.

### `App\Enums\ReportStatus`

[app/Enums/ReportStatus.php](app/Enums/ReportStatus.php) adalah **satu-satunya tempat** nilai status
laporan didefinisikan:

```php
enum ReportStatus: string
{
    case Ditugaskan       = 'ditugaskan';
    case SedangMemperbaiki = 'sedang_memperbaiki';
    case Selesai          = 'selesai';

    public function label(): string { /* "Ditugaskan", "Sedang Memperbaiki", ... */ }
    public static function options(): array { /* value => label, untuk chip filter */ }
}
```

**Mengapa pakai enum?** Kalau kamu salah ketik `'seleai'` sebagai string mentah, PHP tidak protes
— jadi bug senyap. Tapi `ReportStatus::Seleai` langsung error saat kode dibaca. Aturannya:

> Di query & logika bisnis, **selalu** pakai `ReportStatus::Selesai->value`, jangan tulis
> `'selesai'`. Pengecualian: blok `match()` warna/label di Blade masih literal, karena kelas
> Tailwind WAJIB ada di view agar terdeteksi scanner (sudah diekstrak ke `<x-status-pill>`).

Menariknya, enum ini juga **memegang tabel terjemahan status Android** (lihat bab berikutnya):
`apiTransitions()`, `apiActions()`, `transitionForApiAction()`.

### `App\Enums\UserRole`

Pola sama untuk peran pengguna: `UserRole::Admin->value` (`'admin'`), `UserRole::Teknisi->value`
(`'teknisi'`). Dipakai di `isAdmin()`/`isTeknisi()`, validasi form, dan chip filter Pengguna.

---

## 8. Action Class: Logika Bisnis yang Dipakai Bersama

Kalau ada logika bisnis yang **kompleks atau dipakai di lebih dari satu tempat**, ia dikeluarkan
dari component ke kelas khusus di `app/Actions/`. Ini menjaga component tetap ramping dan
memastikan logikanya satu sumber. Ada dua Action di aplikasi ini.

### `SyncReportTechnicians` — sinkron penugasan teknisi

[app/Actions/SyncReportTechnicians.php](app/Actions/SyncReportTechnicians.php). Dipanggil saat
**buat & edit** laporan. Sifatnya **diff-based**:

```php
$toRemove = array_diff($existingIds, $technicianIds);  // teknisi yang dilepas
$toAdd    = array_diff($technicianIds, $existingIds);  // teknisi yang baru
```

- Hanya penugasan yang **dilepas** yang dihapus, hanya yang **baru** yang dibuat.
- Penugasan lama dibiarkan utuh → `assigned_at`-nya tidak ter-reset saat laporan diedit.
- Notifikasi hanya dikirim ke teknisi **baru** (yang lama tidak di-spam ulang).
- Pada alur "buat", belum ada teknisi lama, jadi semua pilihan dianggap baru.

Dipakai begini di `save()` (di dalam `DB::transaction`): `(new SyncReportTechnicians)($report, $this->selectedTechnicians);`

### `GetActiveTechnicianLocations` — lokasi GPS teknisi aktif

[app/Actions/GetActiveTechnicianLocations.php](app/Actions/GetActiveTechnicianLocations.php).
Dipakai bersama oleh **Monitoring** (poll 10 detik) & **peta dashboard**. Tugasnya: ambil titik
GPS **terakhir** tiap teknisi yang sedang `sedang_memperbaiki`.

Bagian yang penting untuk dipahami (soal performa):

> Tabel `location_logs` bisa membengkak ribuan baris selama satu sesi kerja, tapi peta cuma butuh
> **satu titik terbaru per teknisi**. Action ini memakai subquery `recorded_at = MAX(recorded_at)`
> per pasangan (teknisi, laporan), jadi yang ditarik ke memori hanya ~1 baris per teknisi — bukan
> seluruh jejak. Tetap **satu query** (anti N+1: jumlah query tetap konstan walau teknisi bertambah).

---

## 9. API untuk Android: Cara Kerja Sanctum Token

### Login

```
Android kirim:                         Server balas:
POST /api/auth/login                   { "token": "1|abc123...",
{ "email": "...", "password": "..." }    "user": { ... } }
```

Lihat di [app/Http/Controllers/Api/AuthController.php](app/Http/Controllers/Api/AuthController.php):

```php
$token = $user->createToken('android')->plainTextToken;
```

Token ini disimpan di tabel `personal_access_tokens`. Android harus simpan token ini di storage
lokal dan kirim di header `Authorization: Bearer ...` pada setiap request selanjutnya.

### Request Berikutnya

```
GET /api/tasks
Header: Authorization: Bearer 1|abc123...

Server:
- Sanctum cek token di tabel personal_access_tokens
- Temukan user pemilik token → $request->user()
- Jalankan query: TaskAssignment milik user tersebut
```

### Pemetaan Status (Ini Hidden Contract Penting!)

Android dan database pakai nama berbeda untuk status yang sama:

| Android kirim | Yang disimpan di DB |
|---|---|
| `in_progress` | `sedang_memperbaiki` |
| `done` | `selesai` |
| — | `ditugaskan` (hanya dari web admin) |

**Di mana terjemahan ini hidup?** Bukan di controller, melainkan di enum `ReportStatus`
(`apiTransitions()`). `TaskController` cuma **mengorkestrasi** — ia memanggil
`ReportStatus::transitionForApiAction($request->status)` untuk mendapat pasangan `from`/`to`:

```php
// di ReportStatus.php
'in_progress' => ['from' => self::Ditugaskan,        'to' => self::SedangMemperbaiki],
'done'        => ['from' => self::SedangMemperbaiki, 'to' => self::Selesai],
```

Transisi **searah**: `ditugaskan` → `in_progress` → `done`. Loncat langsung dari `ditugaskan` ke
`done` ditolak `422`. Bagaimana penolakan & race condition ditangani dijelaskan di bab berikutnya.

---

## 10. Update Status Tugas: Idempotent & Anti-Balapan

Bab ini menjelaskan satu method yang halus tapi penting: `TaskController::updateStatus`
([app/Http/Controllers/Api/TaskController.php](app/Http/Controllers/Api/TaskController.php)).

**Konsep kunci: status itu milik bersama (level laporan), bukan per-teknisi.**
Satu laporan bisa ditugaskan ke beberapa teknisi. Status menggambarkan keadaan PEKERJAAN, bukan
tiap individu. Konsekuensinya muncul dua masalah yang harus ditangani:

### Masalah 1: dua teknisi menekan tombol yang sama
Bila teknisi A sudah memindahkan laporan ke `sedang_memperbaiki`, lalu teknisi B menekan
"Mulai Perbaiki" juga, B **tidak** ditolak — permintaannya dianggap sukses (idempotent), tanpa
membuat work log ganda:

```php
if ($report->status === $transition['to']->value) {
    return response()->json(['message' => 'Status sudah sesuai', 'status' => $report->status]);
}
```

### Masalah 2: keduanya menekan nyaris bersamaan (race condition)
Kalau cuma mengandalkan pengecekan di atas, dua request paralel bisa lolos berbarengan dan membuat
work log ganda. Maka pengecekan + penulisan dibungkus `DB::transaction` dengan **`lockForUpdate()`**
pada baris laporan:

```php
return DB::transaction(function () use (...) {
    $report = $assignment->report()->lockForUpdate()->first();  // kunci baris ini
    // ...cek status, lalu update + WorkLog::create dalam kunci yang sama
});
```

`lockForUpdate` membuat request kedua menunggu sampai yang pertama selesai (no-op di SQLite saat
test, tapi query tetap jalan).

### Loncat status tetap ditolak
```php
if ($report->status !== $transition['from']->value) {
    return response()->json(['message' => 'Perubahan status tidak valid ...'], 422);
}
```

### Saat selesai, `completed_at` diisi
```php
if ($transition['to'] === ReportStatus::Selesai) {
    $updates['completed_at'] = now();
}
```
Kenapa ini penting? Lihat [Bab 13](#13-completed_at-mengapa-bukan-updated_at).

---

## 11. GPS Realtime: Dari Android ke Peta Web

Ini fitur yang paling "ajaib" tapi sebenarnya sederhana: **tidak ada WebSocket / realtime sejati**,
hanya polling biasa.

### Alur Data GPS

```
[Android]                    [Server]                       [Web Admin]
   │ POST /api/location         │                                │
   │ { lat, lng, report_id }    │                                │
   ├──────────────────────────► │ INSERT location_logs           │
   │   (tiap beberapa detik)    │                                │
   │                            │            (tiap 10 detik)     │
   │                            │◄───────────────────────────────┤ wire:poll.10s
   │                            │ GetActiveTechnicianLocations:   │
   │                            │ ambil titik terakhir per teknisi│
   │                            ├───────────────────────────────►│ dispatch event →
   │                            │                                │ JS update marker peta
```

**Android** kirim GPS → **disimpan ke DB** → **web polling tiap 10 detik** → **baca titik terbaru
per teknisi** → **update marker peta** (tanpa reset zoom/posisi peta).

### Validasi di Server

Di [app/Http/Controllers/Api/LocationController.php](app/Http/Controllers/Api/LocationController.php),
GPS hanya diterima kalau laporan itu **milik teknisi ini** DAN sedang **`sedang_memperbaiki`**:

```php
$assigned = TaskAssignment::where('technician_id', $request->user()->id)
    ->where('report_id', $request->report_id)
    ->whereHas('report', fn($q) => $q->where('status', ReportStatus::SedangMemperbaiki->value))
    ->exists();

if (! $assigned) {
    return response()->json(['message' => '...'], 403);
}
```

### Detail halus: `recorded_at`

Android boleh mengirim `recorded_at` (waktu GPS diambil di HP), karena lebih akurat daripada waktu
sampai server (bisa tertunda saat sinyal lemah). Tapi jam HP yang melenceng ke **masa depan**
diabaikan agar label "last_update" tidak janggal:

```php
$recordedAt = $request->filled('recorded_at') ? Carbon::parse($request->recorded_at) : now();
if ($recordedAt->isFuture()) { $recordedAt = now(); }
```

---

## 12. Push Notification: Cara Kerja NotificationObserver

Ini salah satu bagian paling elegan di kode ini — pemakaian **Observer pattern**.

Di [app/Observers/NotificationObserver.php](app/Observers/NotificationObserver.php):

```php
public function created(Notification $notification): void
{
    // Dipanggil OTOMATIS setiap kali ada Notification::create(...)
    $fcmToken = $notification->user()->value('fcm_token');  // ambil hanya kolom token
    if (! $fcmToken) return;

    try {
        $message = CloudMessage::new()
            ->withToken($fcmToken)
            ->withNotification(FcmNotification::create($notification->title, $notification->body));
        app(Messaging::class)->send($message);
    } catch (\Throwable $e) {
        Log::warning('FCM send failed', [...]);   // gagal kirim FCM TIDAK menggagalkan alur utama
    }
}
```

**Observer** = "pendengar" yang didaftarkan pada sebuah model. Di sini: setiap kali ada baris baru
di tabel `notifications`, method `created()` di atas otomatis jalan.

**Artinya bagimu sebagai pengembang:** kamu **tidak perlu** ingat memanggil FCM secara manual di
mana-mana. Cukup `Notification::create([...])` → FCM otomatis terkirim. Inilah kenapa
`SyncReportTechnicians` cukup membuat baris notifikasi, tanpa menyentuh Firebase sama sekali.

Dua hal kecil yang sengaja dirancang:
- Token diambil via `->user()->value('fcm_token')` — hanya menarik satu kolom, tidak menghidrasi
  model User penuh.
- Kegagalan FCM dibungkus `try/catch` + `Log::warning` → kalau Firebase down, notifikasi DB tetap
  tersimpan dan alur penugasan tidak ikut gagal.

Observer ini didaftarkan di [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php).

---

## 13. `completed_at`: Mengapa Bukan `updated_at`?

Pertanyaan wajar: untuk tahu kapan laporan selesai, kenapa tidak pakai `updated_at` saja?

**Masalahnya:** `updated_at` berubah **setiap kali** baris laporan disentuh — termasuk kalau admin
mengedit catatan laporan tiga hari setelah selesai. Kalau riwayat & durasi dihitung dari
`updated_at`, angkanya akan melenceng begitu laporan diedit lagi.

**Solusinya:** kolom khusus `completed_at` yang diisi **sekali**, tepat saat status menjadi
`selesai` (lihat [Bab 10](#10-update-status-tugas-idempotent--anti-balapan)). Ia jadi **sumber
kebenaran waktu selesai** dan kebal terhadap edit berikutnya.

Di [app/Models/DamageReport.php](app/Models/DamageReport.php), semua tampilan waktu/durasi
dipusatkan ke satu sumber:

```php
// Accessor: waktu selesai efektif (fallback ke updated_at untuk data lama)
protected function waktuSelesai(): Attribute {
    return Attribute::get(fn () => $this->completed_at ?? $this->updated_at);
}

// Durasi penanganan (dibuat → selesai), dipakai tabel/modal riwayat & PDF
public function durasiPenanganan(bool $singkat = false): string { /* ... */ }

// Filter riwayat (dipakai halaman Volt & ekspor PDF) — pakai completed_at, bukan updated_at
public function scopeRiwayatSelesai(Builder $q, ?string $search, ?string $period): Builder { /* ... */ }
```

> Aturan: untuk **waktu/durasi/filter "selesai"**, selalu pakai `completed_at` (atau accessor
> `waktu_selesai` / method `durasiPenanganan()`), **jangan** `updated_at`.

---

## 14. Hapus Pengguna Tanpa Menghapus Riwayat

Dulu semua foreign key ke `users` memakai `cascadeOnDelete` — artinya menghapus seorang admin/teknisi
ikut **menghapus** laporan & work log yang ia buat. Itu berbahaya untuk data skripsi/arsip.

Diperbaiki di migrasi [2026_06_12_000001_preserve_history_on_user_delete.php](database/migrations/2026_06_12_000001_preserve_history_on_user_delete.php).
Aturan barunya:

| Kolom | Perilaku saat user dihapus | Alasan |
|---|---|---|
| `damage_reports.created_by` | **nullOnDelete** → jadi NULL | Laporan adalah arsip; tetap disimpan, pembuat ditampilkan "—" |
| `work_logs.technician_id` | **nullOnDelete** → jadi NULL | Timeline adalah arsip; tetap disimpan, teknisi "Teknisi dihapus" |
| `task_assignments.*` | tetap **cascade** (ikut terhapus) | Penanda hidup "siapa kerjakan apa sekarang", bukan arsip |
| `location_logs.*` | tetap **cascade** (ikut terhapus) | Jejak GPS hanya relevan saat tugas aktif |

Konsekuensi di UI: kolom yang jadi NULL ditampilkan sebagai "—" atau "Teknisi dihapus", bukan error.

> Catatan kecil terkait: admin **boleh** menghapus user lain, tapi **tidak boleh menghapus dirinya
> sendiri** — dijaga di method `deleteUser` (ada test `PenggunaDeletionGuardTest` yang memastikan
> larangan ini tetap berlaku walau guard front-end dilewati).

---

## 15. Ekspor PDF: Mengapa Pakai Controller Biasa?

Halaman lain pakai Livewire Volt, tapi ekspor PDF pakai **controller biasa**. Mengapa?

Livewire bekerja dengan mengirim potongan HTML untuk meng-update DOM. Itu cocok untuk halaman
interaktif, tapi **tidak bisa mengirim file** (PDF). Maka ekspor memakai
[app/Http/Controllers/ReportExportController.php](app/Http/Controllers/ReportExportController.php):

```php
\Carbon\Carbon::setLocale('id');                       // nama bulan/hari Bahasa Indonesia
$reports = DamageReport::riwayatSelesai($search, $period)->get();  // SCOPE yang sama dgn halaman Volt
$pdf = Pdf::loadView('pdf.riwayat-ringkasan', [...]);
return $pdf->stream('riwayat.pdf');                    // tampil inline di tab baru
```

Poin penting:
- Tombol ekspor dibuat sebagai `<a href="/history/export?mode=..." target="_blank">` biasa
  (membuka tab baru), **bukan** `wire:click`.
- Filter dipusatkan di **scope `DamageReport::scopeRiwayatSelesai()`** → halaman Volt & PDF memakai
  query yang sama (satu sumber kebenaran), jadi isi PDF persis mengikuti filter yang sedang aktif.
- Template ada di `resources/views/pdf/`. dompdf **tidak membaca Tailwind** → semua CSS ditulis
  **inline** di `<style>` template, dengan font `DejaVu Sans` (aman untuk karakter Indonesia & dash `– —`).

---

## 16. Peta Leaflet.js: Mengapa Ada `wire:ignore`?

Di setiap halaman yang punya peta, ada ini:

```html
<div wire:ignore>
    <div id="my-map" style="height:400px;"></div>
</div>
```

**Masalahnya:** Livewire bekerja dengan DOM diffing ("bandingkan HTML lama & baru, ganti yang
berbeda"). Leaflet.js juga mengelola DOM peta itu sendiri (menambah layer, marker, dll). Kalau
keduanya mengelola DOM yang sama → **konflik**: setiap `wire:poll` akan menghapus peta yang sudah
dirender Leaflet.

`wire:ignore` berarti: *"Livewire, jangan sentuh div ini — biarkan JavaScript yang mengelolanya."*

**Pola `@script` (bukan `<script>` biasa):**

```blade
@script
<script>
    const el = document.getElementById('my-map');
    if (!el || el._leaflet_id) return;   // cegah inisialisasi ganda
    const map = L.map(el).setView([...], 12);

    $wire.on('locations-updated', ({ locations }) => {   // dengar event dari server
        updateMarkers(locations);                        // update marker tanpa reset view
    });
</script>
@endscript
```

`@script` dijamin jalan setelah component siap dan **tetap jalan** saat navigasi `wire:navigate`
(berbeda dari `<script>` biasa yang bisa tidak ter-eksekusi saat navigasi SPA Livewire). Ia juga
punya akses ke `$wire` untuk mendengar event yang di-dispatch dari PHP.

Detail alur di `monitoring.blade.php`: flag `viewInitialized` membuat `fitBounds` hanya dipanggil
**sekali** saat load pertama, bukan tiap poll — supaya peta tidak "melompat" tiap 10 detik.

---

## 17. Komponen & Trait Reusable Panel Admin

Agar halaman Laporan/Pengguna/Riwayat tidak mengulang markup yang sama, ada komponen Blade & trait
siap pakai. Kalau membuat fitur baru, **pakai ini, jangan menyalin markup manual.**

### Komponen Blade (`resources/views/components/`)

| Komponen | Guna |
|---|---|
| `<x-status-pill :status="$report->status" />` | Pil status berwarna (+ atribut `pulse` untuk titik berdenyut). Ganti `match()` warna manual. |
| `<x-table-card :rows="..." empty-icon="..." empty-text="...">` | Kerangka card + tabel + empty-state + paginasi. Isi `<x-slot:head>` dengan `<th>` dan slot default dengan baris `<tr>`. |
| `<x-filter-chips :options="..." field="filterStatus" :selected="$filterStatus" />` | Grup pil filter. `options` = array value=>label (mis. `ReportStatus::options()`). |
| `<x-confirm-delete-modal title="..." noun="..." :name="$deletingName" action="deleteX" />` | Modal konfirmasi hapus; mengandalkan properti `showDeleteModal` & method `deleteX` di induk. |

### Trait `WithTableFilters` (`app/Livewire/Concerns/`)

Dulu tiap halaman tabel mengulang `updatedSearch()` / `updatedFilterX()` untuk mereset paginasi.
Sekarang cukup pakai trait [WithTableFilters](app/Livewire/Concerns/WithTableFilters.php):

```php
use App\Livewire\Concerns\WithTableFilters;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination, WithTableFilters;

    protected function tableComputed(): string { return 'reports'; }  // nama #[Computed] tabel
};
```

Cara kerjanya: hook generik `updated()` mendeteksi properti `search` atau yang berawalan `filter`
berubah → otomatis `resetPage()` (balik ke halaman 1) + unset computed tabel (hitung ulang).
**Syarat penamaan:** properti filter harus bernama `search` atau berprefix `filter`
(`filterStatus`, `filterRole`, `filterPeriod`) — patuhi ini saat menambah filter baru.

---

## 18. Tailwind v4 + DaisyUI: Konfigurasi CSS-First

Penting kalau kamu menyentuh tampilan: **tidak ada lagi** `tailwind.config.js` maupun
`postcss.config.js`. Sejak migrasi ke Tailwind v4, semua konfigurasi ada di
`resources/css/app.css` (gaya *CSS-first*):

- `@import "tailwindcss"` (menggantikan `@tailwind base/components/utilities` gaya v3)
- `@plugin "daisyui"` + tema `skynet` via `@plugin "daisyui/theme" { ... }`
- `@source "..."` mendaftarkan folder vendor (Mary UI) agar kelasnya terpindai

**Perubahan sintaks yang gampang bikin bingung (jangan pakai pola v3 lama):**

```
❌ !mb-6   !p-0      (prefix "!important" gaya v3 — DIABAIKAN di v4)
✅ mb-6!   p-0!      (suffix "!important" gaya v4)
```

- **Opacity modifier sekarang berfungsi**: `bg-primary/10`, `text-base-content/50` valid. Tapi tetap
  tulis kelas warna sebagai **literal lengkap** (jangan rangkai `bg-{{ $c }}/10`) agar scanner
  Tailwind mendeteksinya.
- Komponen UI memakai **Mary UI**. Hati-hati prefix: sebagian komponen pakai `x-mary-*`
  (mis. `x-mary-button`, `x-mary-card`, `x-mary-modal`), sebagian cukup `x-*` (mis. `x-select`,
  `x-table`, `x-toast`). Lihat daftar lengkap di `CLAUDE.md` — memakai prefix yang salah memicu
  error "Unable to locate component".

---

## 19. Ringkasan Alur Per Fitur

### Dashboard
```
Buka /dashboard
  → Livewire hitung: count per status laporan, jumlah teknisi, dll → render stat cards
  → GetActiveTechnicianLocations → titik GPS terakhir tiap teknisi aktif
  → @script inisialisasi Leaflet + pasang marker
  → Tombol refresh → dispatch event 'map-data-refreshed' → $wire.on(...) update marker
```

### Manajemen Laporan
```
Buka /reports
  → daftar damage_reports + pagination (computed 'reports')
  → search/filter (trait WithTableFilters) → resetPage + re-query (AJAX, tanpa reload)
  → "Tambah/Edit" → modal; pilih teknisi → Simpan (save)
  → DB::transaction { create/update report (status='ditugaskan') + SyncReportTechnicians }
  → FCM ke teknisi baru dikirim otomatis via NotificationObserver
```

### Monitoring GPS
```
Buka /monitoring
  → render sidebar daftar teknisi aktif + peta Leaflet (wire:ignore)
  → wire:poll.10s="loadLocations":
      → GetActiveTechnicianLocations (1 query, titik terakhir per teknisi)
      → dispatch 'locations-updated' → JS update marker (fitBounds hanya sekali)
  → klik teknisi di sidebar → zoom ke marker-nya
```

### Riwayat + Ekspor PDF
```
Buka /history
  → DamageReport::riwayatSelesai($search, $period) (status=selesai, urut completed_at)
  → filter minggu/bulan & search → re-query
  → "Ekspor PDF" = <a href="/history/export?..." target="_blank">
      → ReportExportController@riwayat → scope yang sama → Pdf::stream (tab baru)
```

### API Update Status (Android)
```
POST /api/tasks/{id}/status { status: "in_progress" }
  → Sanctum validasi token → $request->user()
  → cari TaskAssignment milik user ini
  → ReportStatus::transitionForApiAction('in_progress') → from=ditugaskan, to=sedang_memperbaiki
  → DB::transaction + lockForUpdate baris laporan:
      - kalau sudah di status tujuan → 200 idempotent (tanpa work log ganda)
      - kalau status sekarang ≠ from → 422 (loncat status ditolak)
      - else: update status (+ completed_at bila 'done') + WorkLog::create
  → balas JSON { message, status }
```

---

## 20. Struktur Direktori Penting

Untuk memudahkan navigasi, berikut adalah struktur folder utama dalam proyek ini:

- `app/Actions/`: Berisi logika bisnis kompleks (seperti sinkronisasi teknisi).
- `app/Enums/`: Kumpulan enum yang menjadi *single source of truth* (`ReportStatus`, `UserRole`).
- `app/Http/Controllers/`: Controller tradisional dan API Controller (Sanctum).
- `app/Livewire/` atau `resources/views/livewire/pages/`: Tempat komponen Livewire Volt dan halaman interaktif.
- `app/Models/`: Model database (`User`, `DamageReport`, dll.).
- `app/Observers/`: Observer seperti `NotificationObserver` untuk push notifikasi otomatis.
- `routes/`: File rute (web, api, console).
- `resources/views/`: Berisi layout utama, komponen blade UI, template PDF, dan file Volt.

---

## 21. Konfigurasi Lingkungan (.env)

Agar aplikasi dapat berjalan sepenuhnya (terutama fitur Android & Notifikasi), pastikan `.env` terkonfigurasi:

- **Database**: Pastikan kredensial DB sesuai (`DB_CONNECTION=mysql`, `DB_DATABASE=...`).
- **Sanctum**: `SANCTUM_STATEFUL_DOMAINS` perlu diatur jika menggunakan otentikasi SPA, walau di sini lebih dominan Token via API.
- **Firebase/FCM**: Jika push notifikasi diaktifkan, diperlukan kredensial Firebase Cloud Messaging (seringkali melalui variabel seperti `FCM_SERVER_KEY` atau file JSON service account yang harus ditunjuk dari `.env`).

---

## 22. Tips Belajar Selanjutnya

1. **Baca migrations** di `database/migrations/` — file ini menjelaskan struktur tabel persis
   seperti dibuat (kolom, tipe, default, dan aturan foreign key seperti `nullOnDelete`/`cascade`).

2. **Baca enum dulu sebelum logika.** `ReportStatus` & `UserRole` adalah "kamus" aplikasi —
   memahaminya membuat kode lain langsung masuk akal.

3. **Ikuti satu alur dari ujung ke ujung.** Misalnya: buka `laporan/index.blade.php`, temukan
   method `save()`, ikuti ke `SyncReportTechnicians`, lalu ke `NotificationObserver`. Jangan baca
   semua file sekaligus.

4. **Gunakan `php artisan tinker`** untuk bereksperimen dengan model langsung di terminal:
   ```php
   DamageReport::first();                              // lihat satu laporan
   DamageReport::with('taskAssignments')->first();     // lihat dengan relasinya
   User::where('role', \App\Enums\UserRole::Teknisi->value)->get();  // semua teknisi
   (new \App\Actions\GetActiveTechnicianLocations)();  // lihat output lokasi teknisi aktif
   ```

5. **Baca test** di `tests/Feature/` — test case menjelaskan "apa yang seharusnya terjadi" dalam
   bahasa yang cukup mudah dibaca. Contoh berguna: `CompletedAtTest` (waktu selesai),
   `UserDeletionPreservesHistoryTest` (hapus user → riwayat utuh), `TaskTest` (transisi status API),
   `FilterScopingTest` (filter tabel tidak bocor).

6. **Saat menambah fitur tampilan**, ingat dua aturan utama: pakai **komponen/trait reusable**
   (Bab 17) dan ikuti **sintaks Tailwind v4** (Bab 18) — keduanya sumber bug paling umum kalau
   keliru.
