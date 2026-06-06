# Aplikasi Monitoring Perbaikan Jaringan — SkyNet RT/RW Net

Aplikasi web admin + Android untuk monitoring perbaikan jaringan. Studi kasus skripsi, klien SkyNet RT/RW Net, Kab. Bekasi. Metode pengembangan: RAD.

## Stack Teknologi

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 (PHP ^8.3) |
| Realtime UI | Livewire v3 + Volt |
| UI Components | Mary UI (`robsontenorio/mary`) |
| CSS | Tailwind CSS v3 + DaisyUI v5 |
| Build Tool | Vite v8 |
| Peta | Leaflet.js + OpenStreetMap |
| Auth API | Laravel Sanctum (token-based, untuk Android) |
| Database | MySQL via Laragon |
| Mobile | Android (Kotlin) — repo terpisah |
| Dev Environment | Laragon (Windows) |

## Perintah Umum

```bash
# Jalankan semua service sekaligus (Laravel + Queue + Pail + Vite)
composer dev

# Setup awal project
composer setup

# Jalankan test
composer test

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

### Tabel (10 total)

| Tabel | Keterangan |
|---|---|
| `users` | Admin dan teknisi |
| `damage_types` | Jenis kerusakan jaringan |
| `damage_reports` | Laporan kerusakan dari admin |
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
2. Manajemen Laporan — full CRUD + filter + search + assign teknisi
3. Monitoring GPS — Leaflet.js realtime, polling 10 detik
4. Riwayat — laporan selesai + filter periode + detail modal
5. Pengguna — full CRUD admin & teknisi

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
  Http/Controllers/Api/ # AuthController, TaskController,
                        # LocationController, NotificationController
  Models/               # DamageReport, DamageType, TaskAssignment,
                        # WorkLog, LocationLog, Notification, User
routes/
  web.php               # Volt routes (admin panel)
  api.php               # 8 API endpoints (Android via Sanctum)
resources/views/livewire/pages/
  dashboard.blade.php
  laporan/index.blade.php
  monitoring.blade.php
  riwayat.blade.php
  pengguna/index.blade.php
database/
  migrations/           # Semua migrasi tabel
  seeders/              # Seeder untuk semua tabel utama
```

## Routes & Endpoint

> Semua endpoint sudah diimplementasi.

### Web Admin — session-based auth (18 routes)

**Autentikasi**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/login` | Halaman form login |
| POST | `/login` | Proses login, buat session |
| POST | `/logout` | Hapus session |

**Dashboard & Monitoring**
| Method | Path | Keterangan |
|---|---|---|
| GET | `/dashboard` | Ringkasan laporan per status + peta teknisi aktif |
| GET | `/dashboard/map-data` | JSON posisi teknisi aktif (dipanggil JS) |

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
| GET | `/api/tasks` | token | Daftar tugas milik teknisi yang login |
| GET | `/api/tasks/{id}` | token | Detail tugas + info pelanggan + riwayat status |
| POST | `/api/tasks/{id}/status` | token | Update status: `in_progress` atau `done` |

**GPS Tracking**
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| POST | `/api/location` | token | Kirim koordinat GPS (berkala saat `in_progress`) |

**Notifikasi**
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | `/api/notifications` | token | Daftar notifikasi teknisi |
| PUT | `/api/notifications/{id}/read` | token | Tandai notifikasi sudah dibaca |

---

## Konvensi Kode

- Komponen UI menggunakan Mary UI — lihat dokumentasi di `robsontenorio/mary`
- Halaman interaktif dibuat sebagai Livewire Volt component (bukan controller biasa)
- API untuk Android menggunakan prefix `/api` dengan auth `sanctum`
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
```

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
    <x-mary-header title="..." separator class="!mb-6">
        <x-slot:actions>...</x-slot:actions>
    </x-mary-header>
    <!-- konten halaman -->
</div>
```

- Route didaftarkan via `Volt::route()` di `routes/web.php`
- View disimpan di `resources/views/livewire/pages/`
- Layout wrapper: `layouts.app` (sidebar + topbar mobile)
- Computed properties pakai attribute `#[Computed]` + `unset($this->propertyName)` untuk invalidasi cache

### Status Mapping API Android ↔ Database

Android mengirim nilai berbeda dari yang disimpan di DB — ini hidden contract:

| Android kirim | DB menyimpan |
|---|---|
| `in_progress` | `sedang_memperbaiki` |
| `done` | `selesai` |
| — | `ditugaskan` (hanya dari web admin) |

Transisi hanya boleh searah: `ditugaskan` → `in_progress` → `done`. Loncat tidak diizinkan.

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
