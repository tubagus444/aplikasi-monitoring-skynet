# Panduan Belajar: Aplikasi Monitoring Perbaikan Jaringan

> Dokumen ini dibuat untuk membantu kamu memahami **bagaimana aplikasimu sendiri bekerja**,
> dari sudut pandang "ini kodenya, ini yang terjadi di balik layar."

---

## Daftar Isi

1. [Gambaran Besar](#1-gambaran-besar)
2. [Siapa yang Menggunakan Aplikasi Ini?](#2-siapa-yang-menggunakan-aplikasi-ini)
3. [Database: Pondasi Segalanya](#3-database-pondasi-segalanya)
4. [Alur Kerja Utama (End-to-End)](#4-alur-kerja-utama-end-to-end)
5. [Bagaimana Laravel Bekerja di Sini](#5-bagaimana-laravel-bekerja-di-sini)
6. [Livewire Volt: Halaman Interaktif Tanpa Reload](#6-livewire-volt-halaman-interaktif-tanpa-reload)
7. [API untuk Android: Cara Kerja Sanctum Token](#7-api-untuk-android-cara-kerja-sanctum-token)
8. [GPS Realtime: Dari Android ke Peta Web](#8-gps-realtime-dari-android-ke-peta-web)
9. [Push Notification: Cara Kerja NotificationObserver](#9-push-notification-cara-kerja-notificationobserver)
10. [Ekspor PDF: Mengapa Pakai Controller Biasa?](#10-ekspor-pdf-mengapa-pakai-controller-biasa)
11. [Peta Leaflet.js: Mengapa Ada `wire:ignore`?](#11-peta-leafletjs-mengapa-ada-wireignore)
12. [Ringkasan Alur Per Fitur](#12-ringkasan-alur-per-fitur)

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

Keduanya mengakses **database yang sama**. Web pakai session, Android pakai token.

---

## 2. Siapa yang Menggunakan Aplikasi Ini?

Ada dua role di tabel `users`:

| Role | Akses | Cara Login |
|---|---|---|
| `admin` | Web saja | Session (form login biasa) |
| `teknisi` | Android saja | Token Sanctum via API |

**Cara cek di kode:**

Di [app/Models/User.php](app/Models/User.php), ada dua helper method:
```php
public function isAdmin(): bool   { return $this->role === 'admin'; }
public function isTeknisi(): bool { return $this->role === 'teknisi'; }
```

Di [app/Http/Middleware/RoleMiddleware.php](app/Http/Middleware/RoleMiddleware.php), middleware ini
mencegah teknisi membuka halaman web (dan sebaliknya).

---

## 3. Database: Pondasi Segalanya

Ini adalah 10 tabel dan hubungannya. Pahami ini dulu sebelum baca kode lain.

```
users
 ├── damage_reports (created_by → users.id)
 │    ├── task_assignments (report_id → damage_reports.id, technician_id → users.id)
 │    ├── work_logs        (report_id, technician_id)
 │    └── location_logs    (report_id, technician_id)
 └── notifications (user_id → users.id)

damage_types
 └── damage_reports (damage_type_id → damage_types.id)
```

**Tabel kunci yang perlu dimengerti:**

### `damage_reports` — Inti dari semuanya
Setiap laporan kerusakan dari pelanggan. Punya kolom `status` yang berubah sepanjang alur:

```
(baru dibuat) → ditugaskan → sedang_memperbaiki → selesai
```

### `task_assignments` — Penghubung laporan & teknisi
Tabel ini menjawab: *"Teknisi mana yang mengerjakan laporan mana?"*
Satu laporan bisa punya banyak teknisi (1 laporan : banyak assignment).

### `work_logs` — Rekam jejak perubahan status
Setiap kali status berubah (`ditugaskan` → `sedang_memperbaiki` → `selesai`),
satu baris ditambahkan ke tabel ini. Inilah yang muncul sebagai "timeline" di detail tugas Android.

### `location_logs` — Koordinat GPS teknisi
Setiap kali Android teknisi kirim GPS, koordinatnya disimpan di sini.
Peta di web membaca baris **terbaru** dari tabel ini.

---

## 4. Alur Kerja Utama (End-to-End)

Ini adalah skenario lengkap dari awal sampai akhir:

### Langkah 1: Admin buat laporan
- Admin buka `/reports` → klik "Tambah Laporan"
- Isi: nama pelanggan, alamat, jenis kerusakan, catatan
- Data disimpan ke tabel `damage_reports` dengan `status = null` (belum ditugaskan)

### Langkah 2: Admin tugaskan teknisi
- Di halaman laporan, admin klik "Tugaskan Teknisi"
- Pilih satu atau lebih teknisi
- Sistem:
  1. Buat baris baru di `task_assignments` (satu per teknisi)
  2. Update `damage_reports.status` → `'ditugaskan'`
  3. Buat baris di `notifications` untuk teknisi yang dipilih
  4. **Secara otomatis** `NotificationObserver` mengirim push notification FCM ke HP teknisi

### Langkah 3: Teknisi terima notifikasi & mulai kerja
- Android teknisi dapat push notification: "Kamu dapat tugas baru"
- Teknisi buka app → login → lihat daftar tugas
- Klik "Mulai Perbaiki" → Android kirim `POST /api/tasks/{id}/status` dengan `status: "in_progress"`
- Server ubah `damage_reports.status` → `'sedang_memperbaiki'`
- Server simpan ke `work_logs`

### Langkah 4: GPS aktif selama pengerjaan
- Saat status `sedang_memperbaiki`, Android otomatis kirim GPS tiap beberapa detik
- `POST /api/location` → koordinat disimpan ke `location_logs`
- Web admin yang membuka halaman Monitoring akan melihat posisi teknisi bergerak di peta

### Langkah 5: Teknisi selesai
- Teknisi klik "Tandai Selesai" → `POST /api/tasks/{id}/status` dengan `status: "done"`
- Server ubah `damage_reports.status` → `'selesai'`
- GPS berhenti (Android tidak kirim lagi karena kondisi status sudah bukan `sedang_memperbaiki`)

### Langkah 6: Admin lihat riwayat
- Laporan masuk ke halaman `/history`
- Admin bisa filter per minggu/bulan, cari nama pelanggan
- Bisa ekspor ke PDF

---

## 5. Bagaimana Laravel Bekerja di Sini

### Route Web (`routes/web.php`)

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'pages.dashboard')->name('dashboard');
    Volt::route('reports',   'pages.laporan.index')->name('reports.index');
    // dst...
});
```

`Volt::route(...)` berbeda dari `Route::get(...)` biasa. Ini mendaftarkan halaman Livewire Volt
yang **sekaligus mengurus tampilan DAN logika** dalam satu file.

Middleware `auth` artinya: hanya user yang sudah login yang bisa akses. Kalau belum login,
otomatis diarahkan ke `/login`.

### Route API (`routes/api.php`)

```php
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    // ...
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('tasks', [TaskController::class, 'index']);
    // ...
});
```

`auth:sanctum` artinya: Android harus kirim header `Authorization: Bearer {token}` di setiap
request. Tanpa token, server balas `401 Unauthorized`.

---

## 6. Livewire Volt: Halaman Interaktif Tanpa Reload

Ini yang membuat halaman web terasa seperti aplikasi (tidak full reload saat aksi).

Contoh dari [resources/views/livewire/pages/dashboard.blade.php](resources/views/livewire/pages/dashboard.blade.php):

```php
<?php
// BAGIAN PHP — logika server
new #[Layout('layouts.app')] class extends Component {

    #[Computed]          // ← Otomatis di-cache, dipanggil seperti property
    public function totalLaporan(): int
    {
        return DamageReport::count();   // ← Query ke database
    }
}; ?>

{{-- BAGIAN HTML — tampilan --}}
<div>
    {{ $this->totalLaporan }}  {{-- ← Memanggil method di atas --}}
</div>
```

**Cara kerjanya:**
1. Saat halaman dibuka, Livewire kirim satu request ke server
2. Server jalankan query database, render HTML, kirim ke browser
3. Saat user klik tombol atau ada `wire:poll`, Livewire kirim **AJAX request** ke server
4. Server hitung ulang, kirim balik HTML baru yang berubah saja
5. Browser update hanya bagian yang berubah (tanpa reload penuh)

**`#[Computed]`** = method yang hasilnya di-cache selama satu request. Kalau dipanggil berkali-kali
dalam satu render, querynya cuma jalan sekali.

---

## 7. API untuk Android: Cara Kerja Sanctum Token

### Login

```
Android kirim:
POST /api/auth/login
{ "email": "...", "password": "..." }

Server balas:
{ "token": "1|abc123...", "user": { ... } }
```

Lihat di [app/Http/Controllers/Api/AuthController.php](app/Http/Controllers/Api/AuthController.php):

```php
$token = $user->createToken('android')->plainTextToken;
```

Token ini disimpan di tabel `personal_access_tokens`. Android harus simpan token ini di
storage lokal dan kirim di setiap request selanjutnya.

### Request Berikutnya

```
Android kirim:
GET /api/tasks
Header: Authorization: Bearer 1|abc123...

Server:
- Cek token di tabel personal_access_tokens
- Temukan user yang punya token ini
- Jalankan query: TaskAssignment milik user tersebut
```

### Pemetaan Status (Ini Hidden Contract Penting!)

Android dan database pakai nama berbeda untuk status yang sama:

| Android kirim | Yang disimpan di DB |
|---|---|
| `in_progress` | `sedang_memperbaiki` |
| `done` | `selesai` |

Lihat di [app/Http/Controllers/Api/TaskController.php](app/Http/Controllers/Api/TaskController.php):

```php
$transition = [
    'in_progress' => ['from' => 'ditugaskan',        'to' => 'sedang_memperbaiki'],
    'done'        => ['from' => 'sedang_memperbaiki', 'to' => 'selesai'],
][$request->status];

// Validasi: tidak boleh loncat status
if ($report->status !== $transition['from']) {
    return response()->json(['message' => 'Status tidak valid'], 422);
}
```

Jadi `in_progress` hanya bisa dari `ditugaskan`, dan `done` hanya bisa dari `sedang_memperbaiki`.
Loncat langsung dari `ditugaskan` ke `selesai` tidak diizinkan.

---

## 8. GPS Realtime: Dari Android ke Peta Web

Ini adalah fitur yang paling "ajaib" tapi sebenarnya sederhana:

### Alur Data GPS

```
[Android]                    [Server]                  [Web Admin]
   │                            │                           │
   │ POST /api/location         │                           │
   │ { lat, lng, report_id }    │                           │
   ├──────────────────────────► │                           │
   │                            │ INSERT location_logs      │
   │                            │                           │
   │    (setiap beberapa detik) │                           │
   │                            │   (setiap 10 detik)       │
   │                            │◄──────────────────────────┤
   │                            │ wire:poll → loadLocations │
   │                            │                           │
   │                            │ SELECT MAX(recorded_at)   │
   │                            │ dari location_logs        │
   │                            ├──────────────────────────►│
   │                            │                           │ Update marker di peta
```

**Android** kirim GPS → **disimpan ke DB** → **web polling tiap 10 detik** → **baca data terbaru** → **update marker peta**.

Tidak ada WebSocket atau realtime sejati. Ini polling sederhana tapi cukup untuk kebutuhan.

### Validasi di Server

Di [app/Http/Controllers/Api/LocationController.php](app/Http/Controllers/Api/LocationController.php):

```php
// Hanya boleh kirim GPS kalau:
// 1. Laporan itu milik teknisi ini (ada di task_assignments)
// 2. Status laporan sedang 'sedang_memperbaiki'
$assigned = TaskAssignment::where('technician_id', $request->user()->id)
    ->where('report_id', $request->report_id)
    ->whereHas('report', fn($q) => $q->where('status', 'sedang_memperbaiki'))
    ->exists();

if (! $assigned) {
    return response()->json(['message' => '...'], 403);
}
```

---

## 9. Push Notification: Cara Kerja NotificationObserver

Ini salah satu bagian yang paling elegan di kode ini.

### Observer Pattern

Di [app/Observers/NotificationObserver.php](app/Observers/NotificationObserver.php):

```php
public function created(Notification $notification): void
{
    // Dipanggil OTOMATIS setiap kali ada Notification::create(...)
    $fcmToken = $notification->user?->fcm_token;
    
    // Kirim push notification ke HP teknisi via Firebase
    app(Messaging::class)->send($message);
}
```

**Observer** adalah pola desain di mana kamu "mendaftarkan pendengar" pada suatu model.
Di sini: setiap kali ada baris baru di tabel `notifications`, kode di atas otomatis jalan.

**Artinya:** Kamu tidak perlu ingat memanggil FCM secara manual di mana-mana.
Cukup `Notification::create([...])` → FCM otomatis terkirim.

Observer ini didaftarkan di [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php).

### Alur Notifikasi Saat Penugasan

```
Admin klik "Tugaskan"
         │
         ▼
Notification::create(['user_id' => $teknisiId, 'title' => '...'])
         │
         ▼ (otomatis via Observer)
NotificationObserver::created() dipanggil
         │
         ▼
Ambil fcm_token dari tabel users
         │
         ▼
Firebase FCM kirim push notification ke HP teknisi
```

---

## 10. Ekspor PDF: Mengapa Pakai Controller Biasa?

Halaman lain pakai Livewire Volt, tapi ekspor PDF pakai controller biasa.
**Mengapa?**

Livewire bekerja dengan mengirim HTML yang di-update sebagian. Ini cocok untuk halaman interaktif.
Tapi untuk PDF, kamu perlu **mengirim file** (bukan update DOM). Livewire tidak dirancang untuk itu.

Jadi ekspor PDF memakai [app/Http/Controllers/ReportExportController.php](app/Http/Controllers/ReportExportController.php):

```php
// Controller biasa → return file response
$pdf = Pdf::loadView('pdf.riwayat-ringkasan', [...]);
return $pdf->stream('riwayat.pdf');   // Tampil di tab baru
```

Tombol ekspor di halaman Riwayat dibuat sebagai `<a href="/history/export?mode=ringkasan" target="_blank">` biasa,
bukan `wire:click`. Ini sengaja — link biasa membuka tab baru dengan file PDF.

---

## 11. Peta Leaflet.js: Mengapa Ada `wire:ignore`?

Di hampir semua halaman yang punya peta, ada ini:

```html
<div wire:ignore>
    <div id="my-map" style="height:400px;"></div>
</div>
```

**Penjelasan masalahnya:**

Livewire bekerja dengan cara "bandingkan HTML lama dan baru, update yang berbeda" (disebut DOM diffing).
Leaflet.js bekerja dengan cara "aku yang kelola DOM peta ini sendiri" (menambah layer, marker, dll.).

Kalau Livewire dan Leaflet sama-sama mengelola DOM yang sama → **konflik**. Setiap `wire:poll`
akan menghapus peta yang sudah dirender Leaflet.

`wire:ignore` berarti: "Livewire, jangan sentuh div ini. Biarkan JavaScript yang mengelolanya."

**Pola `@script` juga penting:**

```blade
@script
<script>
    // Kode di sini dijamin jalan setelah Livewire component siap
    // dan punya akses ke $wire untuk listen ke events
    $wire.on('locations-updated', ({ locations }) => {
        updateMarkers(locations);
    });
</script>
@endscript
```

`@script` berbeda dari `<script>` biasa karena dijamin re-jalan dengan benar saat navigasi
Livewire (`wire:navigate`) terjadi.

---

## 12. Ringkasan Alur Per Fitur

### Dashboard
```
Buka /dashboard
  → Livewire hitung: count(damage_reports), count(teknisi), dll.
  → Render stat cards
  → Kalau ada teknisi aktif (status=sedang_memperbaiki):
      → Query location_logs (terbaru per teknisi)
      → Render div#dashboard-map
      → @script inisialisasi Leaflet, pasang marker
  → Refresh button → $wire.on('map-data-refreshed') → update marker
```

### Manajemen Laporan
```
Buka /reports
  → Livewire load daftar damage_reports dengan pagination
  → Search/filter → Livewire re-query (AJAX, tanpa reload)
  → Klik "Tugaskan" → modal buka
  → Pilih teknisi → submit
  → Server: insert task_assignments + update status + create notification
  → FCM dikirim otomatis via Observer
```

### Monitoring GPS
```
Buka /monitoring
  → Livewire load task assignments yang sedang aktif
  → Render sidebar daftar teknisi + peta Leaflet
  → wire:poll.10s="loadLocations":
      → Livewire query location_logs terbaru
      → dispatch event 'locations-updated'
      → JavaScript update marker tanpa reset view
  → Klik teknisi di sidebar → zoom ke marker-nya
```

### API Update Status (Android)
```
POST /api/tasks/{id}/status { status: "in_progress" }
  → Sanctum validasi token → temukan user
  → Cari TaskAssignment milik user ini dengan id tersebut
  → Validasi transisi status (hanya boleh dari 'ditugaskan')
  → Update damage_reports.status = 'sedang_memperbaiki'
  → Insert work_logs (rekam jejak)
  → Balas JSON { message: "Status diperbarui", status: "sedang_memperbaiki" }
```

---

## Tips Belajar Selanjutnya

1. **Baca migrations** di `database/migrations/` — file ini menjelaskan struktur tabel persis
   seperti dibuat, lengkap dengan kolom dan tipe datanya.

2. **Baca seeders** di `database/seeders/` — ini data contoh yang dimasukkan saat setup.
   Membacanya membantu memahami "data seperti apa yang diproses aplikasi."

3. **Ikuti satu alur dari ujung ke ujung.** Misalnya: buka `laporan/index.blade.php`,
   temukan method yang jalan saat tombol "Simpan" diklik, lalu ikuti method itu sampai ke
   query database. Jangan baca semua file sekaligus.

4. **Gunakan `php artisan tinker`** untuk bereksperimen dengan model langsung di terminal:
   ```php
   DamageReport::first()                    // lihat satu laporan
   DamageReport::with('taskAssignments')->first()  // lihat dengan relasinya
   User::where('role', 'teknisi')->get()    // lihat semua teknisi
   ```

5. **Baca test** di `tests/Feature/Api/` — test case menjelaskan "apa yang seharusnya terjadi"
   dalam bahasa yang cukup mudah dibaca.
