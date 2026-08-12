# Aplikasi Monitoring Perbaikan Jaringan — SkyNet RT/RW Net

Aplikasi **web admin + Android** untuk memonitor proses perbaikan gangguan jaringan internet
pada layanan **SkyNet RT/RW Net** (Kab. Bekasi). Admin mengelola laporan kerusakan dan menugaskan
teknisi dari panel web; teknisi menerima tugas, memperbarui status, dan mengirim lokasi GPS-nya
secara realtime dari aplikasi Android.

> Studi kasus skripsi · Metode pengembangan **RAD** (Rapid Application Development).
> Repositori ini adalah **backend + panel web admin**. Aplikasi Android teknisi berada di repo
> terpisah (lihat [CLAUDE_ANDROID.md](CLAUDE_ANDROID.md) untuk kontrak API & spesifikasinya).

---

## ✨ Fitur Utama

### Panel Web Admin
- **Dashboard** — ringkasan laporan per status + peta posisi teknisi aktif.
- **Manajemen Laporan** — CRUD laporan kerusakan, filter & pencarian, penugasan multi-teknisi.
- **Monitoring GPS** — peta Leaflet.js realtime (polling 10 detik), fokus ke teknisi tertentu.
- **Riwayat** — laporan selesai, filter periode, detail timeline, **ekspor PDF** (ringkasan & lengkap).
- **Manajemen Pengguna** — CRUD admin & teknisi.

### Aplikasi Android Teknisi (repo terpisah)
- Login via token (Sanctum), lihat & perbarui status tugas.
- GPS otomatis aktif selama status **"Sedang Memperbaiki"**, berhenti saat **"Selesai"**.
- Push notification (Firebase FCM) saat menerima tugas baru.

### Alur Status
```
Ditugaskan ──► Sedang Memperbaiki (GPS aktif) ──► Selesai (GPS berhenti)
```

---

## 🧱 Stack Teknologi

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13 (PHP ^8.3) |
| UI Realtime | Livewire v3 + Volt |
| Komponen UI | Mary UI (`robsontenorio/mary`) |
| CSS | Tailwind CSS v4 (CSS-first) + DaisyUI v5 |
| Build | Vite v8 |
| Peta | Leaflet.js + OpenStreetMap |
| Auth API | Laravel Sanctum (token, untuk Android) |
| Push Notification | Firebase FCM (`kreait/laravel-firebase`) |
| Ekspor PDF | `barryvdh/laravel-dompdf` |
| Database | MySQL |
| Mobile | Android (Kotlin · Jetpack Compose) — repo terpisah |
| Dev Environment | Laragon (Windows) |

---

## 🏗️ Arsitektur Singkat

```
┌─────────────────────────────┐        ┌──────────────────────────────┐
│         WEB (Admin)         │        │       ANDROID (Teknisi)      │
│   Browser → Livewire/Volt   │        │   Kotlin App → REST API      │
│                             │◄──────►│                              │
│  Session (cookie)           │        │  Token Sanctum (Bearer)      │
└──────────────┬──────────────┘        └───────────────┬──────────────┘
               │                                        │
               └──────────────► MySQL ◄─────────────────┘
```

Kedua sisi mengakses database yang sama; perbedaannya hanya pada cara autentikasi.
Penjelasan cara kerja end-to-end ada di [ALUR_APLIKASI.md](ALUR_APLIKASI.md).

---

## 🚀 Instalasi & Menjalankan (Lokal / Laragon)

### 1. Prasyarat Sistem
Pastikan sistem Anda sudah terinstal perangkat lunak berikut:
- **PHP 8.3+** dan **Composer**
- **Node.js 20+** dan **npm**
- **MySQL** (Sangat disarankan menggunakan **Laragon** untuk lingkungan Windows)

### 2. Langkah Instalasi

**Clone & Install Dependencies**
```bash
# Clone repositori (jika belum)
# git clone <url-repo> && cd Aplikasi-Monitoring

# Install dependency PHP (Backend)
composer install

# Install dependency Node.js (Frontend)
npm install
```

**Konfigurasi Environment**
```bash
# Salin file konfigurasi (Windows PowerShell: Copy-Item .env.example .env)
cp .env.example .env

# Generate APP_KEY
php artisan key:generate
```

> 💡 File `.env.example` sudah dikonfigurasi untuk database `aplikasi_monitoring` dengan `SESSION_DRIVER=file`. Anda hanya perlu menyesuaikan `DB_USERNAME` dan `DB_PASSWORD` jika berbeda dari bawaan Laragon (biasanya root / dikosongkan).
> 
> 🔑 **Konfigurasi Firebase (Opsional untuk Dev):** Isi `FIREBASE_CREDENTIALS` di file `.env` dengan path ke file JSON service account untuk mengaktifkan notifikasi push ke Android. Kosongkan saja jika belum perlu (error FCM akan ditangkap otomatis).

**Setup Database**
Pastikan service MySQL di Laragon sudah berjalan.
```bash
# Jalankan migrasi dan isi data awal (seeder)
# Jika database "aplikasi_monitoring" belum ada, akan ditawarkan untuk dibuat otomatis
php artisan migrate --seed
```

### 3. Menjalankan Aplikasi

Cara paling praktis untuk menjalankan aplikasi beserta semua servicenya (Web Server, Vite HMR, dan Queue Worker) adalah dengan satu perintah:

```bash
composer dev
```

> 📱 **Pengujian dengan HP Fisik (Android):** Jika Anda ingin menghubungkan aplikasi Android di HP fisik (bukan emulator) ke backend lokal ini, gunakan perintah berikut:
> ```bash
> composer dev:mobile
> ```
> Perintah ini akan menjalankan server di `--host=0.0.0.0` sehingga dapat diakses oleh jaringan lokal.
> **Langkah Menyambungkan:**
> 1. Cek IP lokal komputer/laptop Anda (misal `192.168.0.105`).
> 2. Di HP fisik, buka browser dan akses `http://192.168.0.105:8000`. Jika halaman web tampil, berarti koneksi berhasil (tidak diblokir firewall).
> 3. Buka aplikasi Android, pada **halaman Login**, buka menu **Konfigurasi API** dan masukkan URL tersebut (contoh: `http://192.168.0.105:8000/api/`).

Jika perintah `composer` di atas tidak berjalan (misal `composer` tidak ada di PATH terminal Anda), Anda bisa menjalankannya secara manual di terminal/tab yang terpisah:
1. **Web Server:** `php artisan serve` *(Gunakan `php artisan serve --host=0.0.0.0 --port=8000` jika untuk pengujian HP fisik)*
2. **Queue (Notifikasi):** `php artisan queue:listen --tries=1`
3. **Vite (Frontend):** `npm run dev`

Atau jika ingin satu perintah menggunakan `npx concurrently`:
```bash
# Default (Lokal saja)
npx concurrently "php artisan serve" "php artisan queue:listen --tries=1" "npm run dev"

# Untuk Pengujian HP Fisik
npx concurrently "php artisan serve --host=0.0.0.0 --port=8000" "php artisan queue:listen --tries=1" "npm run dev"
```

### 4. Akses Aplikasi
- Buka browser dan akses **http://localhost:8000**
- Login menggunakan akun Admin yang tersedia di bagian [Akun Demo](#-akun-demo-dari-seeder) di bawah.

> 🪟 **Catatan untuk pengguna Windows:** Perintah `php artisan pail` (log viewer) tidak dapat digunakan karena membutuhkan ekstensi `pcntl`. Silakan cek error log secara langsung di `storage/logs/laravel.log`.

---

## 🔑 Akun Demo (dari seeder)

| Peran | Email | Password | Akses |
|---|---|---|---|
| Admin | `admin@skynet.test` | `password` | Web admin |
| Teknisi | `teknisi1@skynet.test` | `password` | Android (API) |
| Teknisi | `teknisi2@skynet.test` | `password` | Android (API) |
| Teknisi | `teknisi3@skynet.test` | `password` | Android (API) |

> Login **web hanya untuk admin**; akun teknisi hanya bisa masuk lewat API Android.

---

## 🧪 Testing

```bash
php artisan test          # atau: composer test
```

Cakupan test ada di `tests/Feature/Api/` (endpoint Android) dan `tests/Feature/Web/`
(halaman & logika admin).

---

## 📚 Dokumentasi Lain

| Dokumen | Isi |
|---|---|
| [CLAUDE.md](CLAUDE.md) | Acuan teknis lengkap: struktur, routes, konvensi kode & UI, pola Volt/Leaflet/PDF |
| [DATABASE.md](DATABASE.md) | **Skema database** lengkap per kolom: tipe data, PK/FK, constraint, logika bisnis |
| [ALUR_APLIKASI.md](ALUR_APLIKASI.md) | Panduan belajar — penjelasan *cara kerja* aplikasi dari ujung ke ujung |
| [CLAUDE_ANDROID.md](CLAUDE_ANDROID.md) | **Kontrak API** backend untuk klien Android (request/response tiap endpoint) |
| [CATATAN-FITUR.md](CATATAN-FITUR.md) | Backlog ide pengembangan & rencana deploy (shared hosting / VPS) |

---

## 🗄️ Struktur Database (ringkas)

16 tabel. Inti alurnya: `damage_reports` (laporan) ⇄ `task_assignments` (penugasan
teknisi) ⇄ `users`; jejak pekerjaan di `work_logs`, jejak GPS di `location_logs`, push di
`notifications`. **Dokumentasi lengkap per kolom** (tipe data, FK, constraint, logika bisnis) ada di [DATABASE.md](DATABASE.md).

| Tabel | Keterangan |
|---|---|
| `users` | Admin & teknisi |
| `customers` | Pelanggan (entitas tersendiri) |
| `customer_photos` | Foto rumah pelanggan (wayfinding) |
| `damage_types` | Jenis gangguan/pekerjaan (master data) |
| `damage_reports` | Laporan kerusakan dari admin |
| `report_photos` | Foto bukti pekerjaan teknisi |
| `task_assignments` | Penugasan teknisi ke laporan |
| `work_logs` | Log aktivitas / transisi status pekerjaan |
| `location_logs` | Koordinat GPS teknisi (realtime) |
| `notifications` | Notifikasi untuk teknisi |
| `personal_access_tokens` | Token Sanctum (Android) |
| `cache`, `cache_locks` | Cache & cache locks Laravel |
| `jobs`, `job_batches`, `failed_jobs` | Antrian Laravel |

---

## 📌 Catatan

- Gunakan **`composer dev`** (bukan sekadar `php artisan serve`) agar Vite, queue, dan log
  ikut berjalan.
- Auth web **sengaja admin-only**: tidak ada halaman registrasi / reset password / verifikasi
  email. Akun dikelola lewat menu **Pengguna**.
- Proyek ini dibangun untuk keperluan **skripsi** (studi kasus SkyNet RT/RW Net), berbasis
  kerangka Laravel yang berlisensi [MIT](https://opensource.org/licenses/MIT).
