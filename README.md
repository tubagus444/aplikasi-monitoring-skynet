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
Penjelasan cara kerja end-to-end ada di [BELAJAR.md](BELAJAR.md).

---

## 🚀 Instalasi & Menjalankan (Lokal / Laragon)

### Prasyarat
- PHP **8.3+**, Composer
- Node.js 20+ & npm
- MySQL (disarankan via **Laragon**)

### Langkah

```bash
# 1. Install dependency PHP
composer install

# 2. Salin file environment lalu generate APP_KEY
cp .env.example .env        # Windows (PowerShell): Copy-Item .env.example .env
php artisan key:generate
```

`.env.example` sudah memakai konfigurasi proyek yang benar (**MySQL** `aplikasi_monitoring`
+ `SESSION_DRIVER=file`), jadi setelah disalin biasanya **tidak perlu** diubah. Sesuaikan hanya
bila berbeda dari default:

```dotenv
DB_USERNAME=root
DB_PASSWORD=            # isi bila MySQL Anda memakai password

# Wajib untuk push notification Android (path ke service account JSON Firebase).
# File JSON JANGAN di-commit (sudah ada di .gitignore). Boleh dikosongkan saat dev.
FIREBASE_CREDENTIALS=
```

```bash
# 3. Siapkan database "aplikasi_monitoring" + isi data contoh (akun demo, jenis kerusakan, dll.)
#    Jika database belum dibuat, perintah ini akan menawarkan membuatkannya otomatis (jawab "yes").
php artisan migrate
#    Alternatif: buat manual lebih dulu via HeidiSQL / Laragon, lalu jalankan perintah di bawah.
php artisan migrate --seed

# 4. Install dependency frontend
npm install
```

**5. Jalankan aplikasi.** Cara termudah — satu perintah menjalankan semua service
(server + queue + Vite):

```bash
composer dev
```

> ⚠️ `composer dev` butuh `composer` ada di PATH. Bila terminal Anda tidak mengenali `composer`
> (mis. Git Bash / terminal VS Code), jalankan dari **Terminal Laragon**, atau pakai perintah
> setara tanpa composer:
> ```bash
> npx concurrently "php artisan serve" "php artisan queue:listen --tries=1" "npm run dev"
> ```
> Alternatif paling sederhana (cukup untuk lihat UI & login, tanpa queue worker):
> jalankan `npm run dev` dan `php artisan serve` di dua terminal terpisah.

> 🪟 **Catatan Windows:** log viewer `php artisan pail` sengaja **tidak** disertakan karena butuh
> ekstensi `pcntl` yang tidak tersedia di Windows. Untuk melihat log, baca langsung
> `storage/logs/laravel.log`.

> 💡 Saat **development** pakai `npm run dev` (Vite hot-reload, sudah termasuk di atas) — **bukan**
> `npm run build`. `npm run build` hanya untuk **produksi** (mengompilasi aset jadi file statis).

Buka **http://localhost:8000** lalu login sebagai admin (lihat akun demo di bawah).

> **Firebase opsional saat dev:** bila `FIREBASE_CREDENTIALS` belum diisi, pengiriman FCM gagal
> diam-diam (`try/catch` + log) dan **tidak** menghentikan alur — notifikasi tetap tersimpan di DB.

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
| [BELAJAR.md](BELAJAR.md) | Panduan belajar — penjelasan *cara kerja* aplikasi dari ujung ke ujung |
| [CLAUDE_ANDROID.md](CLAUDE_ANDROID.md) | **Kontrak API** backend untuk klien Android (request/response tiap endpoint) |
| [CATATAN-FITUR.md](CATATAN-FITUR.md) | Backlog ide pengembangan & rencana deploy (shared hosting / VPS) |

---

## 🗄️ Struktur Database (ringkas)

10 tabel utama. Inti alurnya: `damage_reports` (laporan) ⇄ `task_assignments` (penugasan
teknisi) ⇄ `users`; jejak pekerjaan di `work_logs`, jejak GPS di `location_logs`, push di
`notifications`. Detail relasi & aturan foreign key ada di [BELAJAR.md](BELAJAR.md) Bab 3.

| Tabel | Keterangan |
|---|---|
| `users` | Admin & teknisi |
| `damage_types` | Jenis kerusakan jaringan |
| `damage_reports` | Laporan kerusakan dari admin |
| `task_assignments` | Penugasan teknisi ke laporan |
| `work_logs` | Log aktivitas / transisi status pekerjaan |
| `location_logs` | Koordinat GPS teknisi (realtime) |
| `notifications` | Notifikasi untuk teknisi |
| `personal_access_tokens` | Token Sanctum (Android) |
| `cache`, `jobs` | Cache & antrian Laravel |

---

## 📌 Catatan

- Gunakan **`composer dev`** (bukan sekadar `php artisan serve`) agar Vite, queue, dan log
  ikut berjalan.
- Auth web **sengaja admin-only**: tidak ada halaman registrasi / reset password / verifikasi
  email. Akun dikelola lewat menu **Pengguna**.
- Proyek ini dibangun untuk keperluan **skripsi** (studi kasus SkyNet RT/RW Net), berbasis
  kerangka Laravel yang berlisensi [MIT](https://opensource.org/licenses/MIT).
