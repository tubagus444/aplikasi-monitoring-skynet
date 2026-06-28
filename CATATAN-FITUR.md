# Catatan Fitur & Ide Pengembangan

> Daftar ide/fitur yang **bisa** dikerjakan nanti tapi sengaja ditunda atau belum dibuat.
> Ini bukan to-do wajib — anggap sebagai catatan "kalau suatu saat mau dikembangkan,
> begini caranya & apa pertimbangannya." Setiap entri punya **Apa / Kenapa / Bagaimana / Catatan**.

**Keterangan status:**

| Simbol | Arti |
|---|---|
| 🟡 Ditunda | Sudah dibahas, sengaja ditunda — tinggal dikerjakan kalau mau |
| 💡 Ide | Belum dibahas detail, baru gagasan |
| 📱 Android | Perlu perubahan di repo Android (terpisah dari repo ini) |

---

## Daftar Isi

1. [Pembersihan otomatis `location_logs` (retensi data)](#1-pembersihan-otomatis-location_logs-retensi-data) 🟡
2. [Tampilkan jejak rute GPS di Riwayat](#2-tampilkan-jejak-rute-gps-di-riwayat) 💡
3. [Migrasi peta ke Google Maps SDK](#3-migrasi-peta-ke-google-maps-sdk) 🟡
4. [Standarisasi penamaan folder dan route Volt](#4-standarisasi-penamaan-folder-dan-route-volt) 🟡
5. [Foto bukti perbaikan](#5-foto-bukti-perbaikan) 🟡 📱
6. [Keterangan (`description`) pada work log](#6-keterangan-description-pada-work-log) 🟡 📱
7. [UNIQUE constraint `task_assignments (report_id, technician_id)`](#7-unique-constraint-task_assignments-report_id-technician_id) 🟡
8. [Kontak pelanggan (nomor telepon)](#8-kontak-pelanggan-nomor-telepon) ✅ USANG (jadi `customers.phone` di Rencana #1)
9. [Masa berlaku token Android (Sanctum expiration)](#9-masa-berlaku-token-android-sanctum-expiration) 🟡
10. [Catatan kecil lainnya](#10-catatan-kecil-lainnya) 💡
11. [Rencana deploy ke hosting (shared & VPS)](#11-rencana-deploy-ke-hosting-shared--vps) 🟡 📱
12. [Peningkatan peta Monitoring (marker, trail, GPS basi)](#12-peningkatan-peta-monitoring-marker-trail-gps-basi) 💡
13. [Sudah dikerjakan (arsip)](#13-sudah-dikerjakan-arsip)

---

## 1. Pembersihan otomatis `location_logs` (retensi data) 🟡

**Apa:** Tabel `location_logs` bersifat *append-only* — tiap titik GPS yang dikirim teknisi
jadi satu baris permanen, dan tidak pernah ada yang menghapus. Fitur ini membersihkan titik-titik
GPS milik laporan yang sudah `selesai`.

**Kenapa:** Setelah laporan `selesai`, baris-baris GPS-nya **tidak pernah dibaca lagi** — kedua
pembacanya ([monitoring.blade.php](resources/views/livewire/pages/monitoring.blade.php) dan
[dashboard.blade.php](resources/views/livewire/pages/dashboard.blade.php)) memfilter ketat
`status = 'sedang_memperbaiki'`. Halaman Riwayat tidak menyentuh tabel ini. Jadi data jadi beban
tabel yang tumbuh tanpa batas (kira-kira ~120 baris/perbaikan → ratusan ribu baris/tahun).

> **Catatan kepentingan:** untuk skala SkyNet RT/RW Net ini **belum mendesak**. Index komposit
> yang sudah dipasang (lihat bagian Arsip) menjaga query tetap cepat walau tabel besar. Ini lebih
> ke kebersihan data daripada kebutuhan performa.

**Bagaimana — 3 pilihan pendekatan:**

| Pendekatan | Cara kerja | Trade-off |
|---|---|---|
| **A. Hapus total** | Hapus semua titik milik laporan `selesai` yang sudah lewat masa tenggang (mis. > 30 hari) | Paling simpel & hemat. Jejak rute hilang permanen |
| **B. Sisakan titik terakhir** | Hapus titik tengah, simpan 1 titik terakhir per laporan | Tetap tahu "teknisi terakhir di mana", buang ~99% baris |
| **C. Arsip** | Pindahkan ke tabel arsip / ekspor sebelum hapus | Paling aman, paling rumit — overkill untuk skala ini |

**Mekanisme implementasi (sama untuk semua pendekatan):**

1. Buat Artisan command:
   ```bash
   php artisan make:command CleanupLocationLogs
   ```
   Inti logikanya (pendekatan A dengan masa tenggang 30 hari):
   ```php
   LocationLog::whereHas('report', fn ($q) => $q->where('status', 'selesai'))
       ->where('recorded_at', '<', now()->subDays(30))
       ->delete();
   ```
2. Jadwalkan harian di `routes/console.php`:
   ```php
   use Illuminate\Support\Facades\Schedule;
   Schedule::command('locations:cleanup')->daily();
   ```
3. **Pemicu scheduler** — ini bagian yang sering terlewat:
   - Di server Linux: cron `* * * * * php artisan schedule:run`.
   - Di **Laragon/Windows (dev)**: perlu Task Scheduler, dan biasanya tidak aktif. Untuk dev
     cukup jalankan **manual** sesekali: `php artisan locations:cleanup`. Pasang scheduler
     beneran nanti saat sudah deploy ke server.

**Catatan:** kalau nanti mau bikin fitur #2 (jejak rute di Riwayat), **jangan pakai pendekatan A
tanpa tenggang** — pilih **B** atau perpanjang masa tenggang, supaya titik-titiknya masih ada
saat mau digambar jadi rute.

---

## 2. Tampilkan jejak rute GPS di Riwayat 💡

**Apa:** Di halaman/detail Riwayat, gambar **garis rute** (polyline) dari seluruh titik GPS yang
dilewati teknisi selama mengerjakan satu laporan — bukan cuma satu titik terakhir seperti di peta
Monitoring.

**Kenapa:** Memberi bukti/jejak audit "teknisi benar-benar datang ke lokasi dan rutenya seperti
apa." Saat ini relasi `DamageReport::locationLogs()` di
[app/Models/DamageReport.php](app/Models/DamageReport.php) **sudah ada tapi belum dipakai
sama sekali** — ini hook alami untuk fitur tersebut.

**Bagaimana:**
1. Di komponen detail riwayat, ambil titik berurutan:
   ```php
   $report->locationLogs()->orderBy('recorded_at')->get(['latitude', 'longitude']);
   ```
2. Render peta Leaflet (ikuti pola wajib `wire:ignore` + `@script` di
   [CLAUDE.md](CLAUDE.md) bagian "Leaflet.js + Livewire").
3. Gambar rute dengan `L.polyline([...koordinat]).addTo(map)` lalu `map.fitBounds(...)`.

**Catatan:** **Bergantung pada fitur #1.** Kalau titik GPS sudah dihapus pakai pendekatan A,
rute tidak bisa digambar. Jadi kalau fitur ini mau dibuat, retensi data harus pakai pendekatan B
(sisakan titik) atau masa tenggang yang panjang. **Beririsan dengan fitur #12 opsi B** (trail GPS
di peta Monitoring) — bedanya: ini trail *historis* di Riwayat (laporan sudah selesai), fitur #12
trail *live* saat laporan masih dikerjakan. Keduanya berbagi sumber data `location_logs`.

---

## 3. Migrasi peta ke Google Maps SDK 🟡

**Apa:** Mengganti pustaka peta dari **Leaflet.js + OpenStreetMap** ke **Google Maps JavaScript
API**, kalau suatu saat dituntut keadaan (mis. permintaan klien, kebutuhan tampilan/fitur khas
Google). Bukan rencana wajib — catatan kesiapan saja.

**Kenapa bisa:** Peta di aplikasi ini cuma **lapisan presentasi**. Data lokasi yang disimpan &
dikirim hanyalah koordinat `latitude`/`longitude` (WGS84) — lihat
[LocationController.php](app/Http/Controllers/Api/LocationController.php),
[LocationLog.php](app/Models/LocationLog.php), dan endpoint `/dashboard/map-data`. Format koordinat
ini **identik** untuk Leaflet maupun Google Maps. Konsekuensinya:

- **Backend / API / database / app Android → 0 perubahan.** Teknisi tetap kirim lat-lng yang sama.
- Yang berubah **hanya cara menggambar titik di layar.**

**Bagaimana — hanya menyentuh 3 file view:**

| File | Perubahan |
|---|---|
| [layouts/app.blade.php](resources/views/layouts/app.blade.php) | Ganti CDN Leaflet (`<link>`+`<script>` unpkg) → loader Google Maps JS API + API key |
| [monitoring.blade.php](resources/views/livewire/pages/monitoring.blade.php) | Terjemahkan blok `@script` (init peta, marker, fitBounds, event `focus-technician`) |
| [dashboard.blade.php](resources/views/livewire/pages/dashboard.blade.php) | Sama, blok peta-nya |

Penerjemahan API hampir 1:1:

| Leaflet (sekarang) | Google Maps SDK |
|---|---|
| `L.map(el).setView([lat,lng], 12)` | `new google.maps.Map(el, { center, zoom })` |
| `L.tileLayer('...osm...')` | otomatis (tile Google sendiri) |
| `L.marker([lat,lng])` | `new google.maps.marker.AdvancedMarkerElement({ position })` |
| `map.fitBounds(...)` | `map.fitBounds(new google.maps.LatLngBounds(...))` |
| `map.setView(latlng, 17)` | `map.setCenter(latlng); map.setZoom(17)` |

**Yang TIDAK berubah (penting):** pola integrasi Livewire ↔ peta tetap sama persis — `wire:ignore`
pada container, init via `@script` (bukan inline `<script>`), `wire:poll.10s="loadLocations"` +
`$wire.on('locations-updated')`, dan event `focus-technician`. Semua mekanisme realtime/polling itu
independen dari pustaka peta; tinggal isi callback-nya dengan API Google.

**Catatan:**
- **Biaya:** Leaflet+OSM sekarang **gratis tanpa API key**. Google Maps **wajib API key + akun
  billing (kartu kredit terdaftar)**, walau ada free tier bulanan. Untuk skala internal admin SkyNet
  (trafik rendah) free tier **sangat cukup** — Google menagih per **map load** (saat
  `new google.maps.Map()`), sedangkan polling 10 detik yang cuma menggerakkan marker **tidak**
  dihitung map load. Risiko biaya baru muncul kalau menambah fitur Geocoding/Directions/Places
  (aplikasi ini tidak memakainya).
- **Wajib dipertahankan:** ekuivalen guard anti double-init (`if (el._leaflet_id) return` di Leaflet)
  harus tetap ada agar peta tidak di-init ulang tiap poll — kalau lalai, map load (dan biaya)
  membengkak.
- **Key jangan di-commit:** batasi API key per domain/referrer dan simpan di `.env` (ikuti precedent
  `FIREBASE_CREDENTIALS`).

Penghalang utama migrasi ini **bukan teknis** (cuma ~3 file view, backend nol perubahan), melainkan
keputusan **billing Google vs OSM yang gratis**.

---

## 4. Standarisasi penamaan folder dan route Volt 🟡

**Apa:** Menyelaraskan penamaan halaman admin yang sekarang campur. Tiap halaman punya 3 lapis nama
(URL · file Volt · route name) yang idealnya konsisten, tapi `laporan`↔`reports`, `pengguna`↔`users`,
`riwayat`↔`history` campur Indonesia/Inggris, dan sebagian file pakai subfolder+`index` sementara
sebagian datar.

| Konsep | URL | File Volt | Route name | Konsisten? |
|---|---|---|---|---|
| Dashboard | `/dashboard` | `dashboard.blade.php` | `dashboard` | ✅ |
| Laporan | `/reports` | `laporan/index.blade.php` | `reports.index` | ❌ |
| Monitoring | `/monitoring` | `monitoring.blade.php` | `monitoring` | ✅ |
| Riwayat | `/history` | `riwayat.blade.php` | `history` | ❌ |
| Pengguna | `/users` | `pengguna/index.blade.php` | `users.index` | ❌ |
| Pelanggan | `/customers` | `pelanggan/index.blade.php` | `customers.index` | ❌ |
| Statistik | `/statistik` | `statistik.blade.php` | `statistik` | ✅ |
| Jenis Gangguan | `/damage-types` | `jenis-gangguan/index.blade.php` | `damage-types.index` | ❌ |

> Tabel di atas sudah memasukkan halaman yang ditambah belakangan (Pelanggan, Statistik, Jenis
> Gangguan) — pola campurnya **makin banyak** (kini 5 dari 8 halaman tak konsisten), jadi kalau
> mau dirapikan, ruang lingkup `git mv`-nya lebih besar dari estimasi awal entri ini.

Dua masalah terpisah:
- **A. Bahasa campur** — file ID (`laporan`) vs route EN (`reports`). Harus hafal pemetaan untuk tahu
  "route `reports` itu file mana".
- **B. Struktur folder campur** — `laporan/index.blade.php` & `pengguna/index.blade.php` pakai
  subfolder+`index`, sedangkan `monitoring`/`riwayat`/`dashboard` datar.

**Kenapa (dan kenapa ditunda):** Murni kerapian internal — **nol** perubahan fungsional, **nol** untuk
user. Yang sekarang berfungsi sempurna. Karena `Volt::route('url', 'komponen')->name('nama')` memisah
ketiga lapis, mismatch ini **tidak menimbulkan bug** — cuma "tax" kognitif kecil saat memetakan
route→file. Manfaatnya kecil dibanding risiko menggerakkan file, makanya ditunda (bukan masalah,
hanya estetika).

**Bagaimana — rekomendasi: Inggris + ratakan, URL & route name dipertahankan.**

Dua keputusan:
1. **Arah bahasa → Inggris**: rename file `laporan`→`reports`, `pengguna`→`users`, `riwayat`→`history`.
   URL sudah Inggris, jadi **URL tidak berubah** — hanya nama file internal. (Alternatif arah Indonesia
   mengubah URL user-visible `/reports`→`/laporan` dst. — lebih berisik.)
2. **Ratakan folder**: buang subfolder+`index`, mis. `laporan/index.blade.php` → `reports.blade.php`.

Langkah konkret (blast radius kecil):
1. `git mv` 3 file (folder `laporan/` & `pengguna/` jadi kosong → hilang sendiri):
   - `livewire/pages/laporan/index.blade.php` → `livewire/pages/reports.blade.php`
   - `livewire/pages/pengguna/index.blade.php` → `livewire/pages/users.blade.php`
   - `livewire/pages/riwayat.blade.php` → `livewire/pages/history.blade.php`
2. Update 3 argumen komponen di [routes/web.php](routes/web.php) (biarkan URL & `->name(...)` apa adanya):
   - `'pages.laporan.index'` → `'pages.reports'`
   - `'pages.pengguna.index'` → `'pages.users'`
   - `'pages.riwayat'` → `'pages.history'`
3. Update 1 string di [tests/Feature/Web/LaporanFormTest.php](tests/Feature/Web/LaporanFormTest.php):
   `Volt::test('pages.laporan.index')` → `'pages.reports'`.
4. `php artisan view:clear` lalu `php artisan test` — `PageRenderTest` membuktikan tiap URL tetap 200.

**Yang TIDAK perlu disentuh** (kunci agar aman): route name (`reports.index`, `history`, `users.index`)
& URL tetap → semua `route('reports.index')` dst. di [navigation](resources/views/livewire/layout/navigation.blade.php),
[dashboard](resources/views/livewire/pages/dashboard.blade.php), [riwayat](resources/views/livewire/pages/riwayat.blade.php)
**tetap jalan tanpa diubah**.

**Catatan:**
- **Prioritas terendah / boleh skip selamanya.** Tidak ada "bau kode" serius; estetika belaka.
- Risiko utama: rename file Volt + **cache view** — `php artisan view:clear` **wajib** setelah rename,
  kalau tidak Volt bisa memuat path lama. Test menangkap referensi yang putus.
- Kalau mau konsistensi penuh, route name `reports.index`/`users.index` bisa disederhanakan jadi
  `reports`/`users` (selaras `monitoring`/`history` yang tanpa `.index`) — tapi itu menambah update ke
  `route()` di navigation & dashboard, jadi blast radius lebih besar. Tidak wajib.

---

## 5. Foto bukti perbaikan ✅ backend SELESAI — sisa UI Android 📱

> **Backend sudah jadi (2026-06).** Diimplementasi pakai **opsi B** (tabel `report_photos`, bukan
> kolom di `work_logs`) karena lebih fleksibel (banyak foto sebelum/sesudah per laporan). Endpoint
> `POST /api/tasks/{id}/photos` (multipart `photo` ≤5MB + `caption` opsional, di-scope kepemilikan
> tugas) sudah ada, foto tampil di modal detail [Riwayat](resources/views/livewire/pages/riwayat.blade.php)
> sebagai `repair_photos`, dan dikirim ke Android via detail tugas. Test: `TaskPhotoTest`. **Sisa
> hanya UI Android** (kamera + unggah). Lihat memory `catatan-bukti-pekerjaan-progres`.

**Apa (sudah tercapai):** Teknisi mengunggah foto bukti (sebelum/sesudah) perbaikan dari Android;
admin melihatnya di detail Riwayat. Menjawab pertanyaan akuntabilitas "bagaimana admin tahu teknisi
benar memperbaiki, bukan asal klik selesai?" — GPS hanya membuktikan teknisi **berada di lokasi**,
foto membuktikan **pekerjaan dikerjakan**.

**Yang tersisa (Android):** layar kamera/galeri + multipart upload ke `POST /api/tasks/{id}/photos`,
kompres di device sebelum kirim. Validasi server sudah ada (`image|max:5120`).

**Catatan lanjutan (belum dikerjakan, opsional):** sertakan foto di **PDF riwayat lengkap** (dompdf
bisa render `<img>` dari path lokal) — saat ini foto hanya tampil di modal web, belum di PDF.

---

## 6. Keterangan (`description`) pada work log ✅ backend SELESAI — sisa UI Android 📱

> **Backend sudah jadi (2026-06).** `POST /api/tasks/{id}/status` kini menerima `description`
> opsional (`max:1000`, lihat [TaskController](app/Http/Controllers/Api/TaskController.php#L49)),
> disimpan di work log transisi, dan tampil di timeline Riwayat + dikirim balik via API detail tugas.
> Test: `TaskTest` menguji catatan tersimpan & tampil. **Sisa hanya field catatan di UI Android.**

**Apa (sudah tercapai):** Kolom `work_logs.description` yang dulu tak pernah diisi kini terisi
catatan pekerjaan dari teknisi, sehingga "Log Aktivitas Pekerjaan" tidak lagi kosong.

**Yang tersisa (Android):** tambah field catatan (opsional) di layar "Sedang Memperbaiki" / saat
menutup tugas, lalu kirim sebagai field `description` saat update status.

---

## 7. UNIQUE constraint `task_assignments (report_id, technician_id)` 🟡

**Apa:** Menambah composite unique index `(report_id, technician_id)` pada `task_assignments`.

**Kenapa:** Tabel junction many-to-many ini **tidak punya jaminan unik di level database** —
pencegahan penugasan ganda hanya ada di logika aplikasi
([SyncReportTechnicians](app/Actions/SyncReportTechnicians.php#L31-L32) via `array_diff`). Bila ada
jalur lain (seeder, raw insert, bug) yang menambah baris, duplikat bisa lolos. Penguji yang fokus
basis data bisa menanyakannya, dan "dijaga kode" adalah jawaban lemah untuk integritas data.

**Bagaimana:**
```php
Schema::table('task_assignments', function (Blueprint $table) {
    $table->unique(['report_id', 'technician_id'], 'task_assignments_report_tech_unique');
});
```
- **Dedupe dulu** baris duplikat yang mungkin sudah ada sebelum menambah index (kalau ada duplikat,
  pembuatan index gagal).
- Tambah test regresi: assign teknisi yang sama dua kali → DB menolak / app tetap idempotent.

**Catatan:** Tidak bertabrakan dengan `SyncReportTechnicians` (yang memang sudah mencegah duplikat) —
index ini **sabuk pengaman level DB**. Murah dan menambah poin "integritas referensial" saat sidang.

---

## 8. Kontak pelanggan (nomor telepon) ✅ USANG — sudah terpenuhi

> **Tidak relevan lagi.** Digantikan sepenuhnya oleh **Rencana #1 Modul Pelanggan** (selesai
> 2026-06-27, lihat Arsip di [RENCANA-PENGEMBANGAN.md](RENCANA-PENGEMBANGAN.md)). Nomor telepon kini
> jadi kolom `customers.phone` (bukan `damage_reports.customer_phone`), tampil di halaman/detail
> Pelanggan & PDF, dan dikirim ke Android via `TaskController::formatTask` (field `phone`, untuk
> kategori pelanggan). Tidak perlu dikerjakan lagi.

~~**Apa:** Menambah kolom `customer_phone` (nullable) pada `damage_reports`.~~ (ide awal, kini usang)

---

## 9. Masa berlaku token Android (Sanctum expiration) 🟡

**Apa:** Menyetel masa berlaku token Sanctum untuk app Android.

**Kenapa:** Default Sanctum: token **tidak kedaluwarsa** — berlaku selamanya sampai logout
([AuthController](app/Http/Controllers/Api/AuthController.php#L29) membuat token tanpa expiry). Kalau
HP teknisi hilang/dicuri, token tetap valid. Pertanyaan keamanan klasik penguji.

**Bagaimana:**
- `config/sanctum.php` → `'expiration' => 60 * 24 * 30` (mis. 30 hari, dalam menit). Token lewat masa
  berlaku otomatis ditolak.
- Komplemen: layar admin untuk me-revoke token (`$user->tokens()->delete()`) bila perangkat hilang.

**Catatan:** Trade-off — teknisi harus login ulang saat token kedaluwarsa; pilih durasi yang nyaman
(mingguan/bulanan). Untuk skripsi cukup setel `expiration` + sebut di "Saran Pengembangan";
manajemen perangkat penuh adalah overkill.

---

## 10. Catatan kecil lainnya 💡

Temuan minor dari review kesiapan sidang — sebagian **bukan perubahan**, melainkan hal yang cukup
**disiapkan jawabannya**. Dikumpulkan di sini agar tidak hilang konteks.

- **Rate limit `POST /api/location`** — endpoint GPS belum di-throttle (beda dengan login yang
  `throttle:5,1` di [routes/api.php](routes/api.php#L10)). Teknisi nakal / bug bisa membanjiri.
  Tambah `->middleware('throttle:...')` bila perlu. Minor.
- **Notifikasi tidak terhubung ke laporan** — tabel `notifications` hanya `title`/`body` teks, tanpa
  `report_id`/`type`
  ([migrasi notifications](database/migrations/2026_06_05_145737_create_notifications_table.php)). Tap
  notifikasi di Android tak bisa *deep-link* ke tugas terkait. Tambah kolom `report_id` (nullable) +
  `type` bila ingin navigasi langsung.
- ~~**`damage_types` tanpa `updated_at`**~~ ✅ **RESOLVED (2026-06-28)** — bersamaan dengan menu CRUD
  Jenis Gangguan (Rencana #5), kolom `updated_at` ditambahkan (migrasi `add_updated_at_to_damage_types`,
  nullable) & `DamageType` kini `timestamps` aktif (hapus `$timestamps = false`). Jejak waktu ubah
  tercatat saat admin mengedit jenis.
- **`fcm_token` tunggal per user** — login di 2 perangkat → token lama tertimpa, hanya perangkat
  terakhir yang menerima push. Acceptable untuk pola "1 teknisi 1 HP"; catat saja sebagai batasan.
- **`enum` vs tabel referensi (status/role)** — bukan perubahan, tapi **siapkan jawaban**: jenis
  gangguan = data yang dikelola admin (**tabel CRUD — kini benar-benar ada**, Rencana #5), sedangkan
  status/role = aturan bisnis yang tetap (enum). Menambah nilai enum perlu migration `ALTER` —
  konsekuensi yang disengaja.
- **Timestamp append-only tak seragam** — tabel log (`work_logs.logged_at`,
  `location_logs.recorded_at`, `notifications.created_at`, `task_assignments.assigned_at`) sengaja
  tanpa `updated_at` karena barisnya immutable. **Defensible**; siapkan jawabannya saat membahas ERD.

---

## 11. Rencana deploy ke hosting (shared & VPS) 🟡 📱

**Apa:** Rencana men-deploy aplikasi (web admin + API Android) agar bisa diakses online. Saat ini
hanya berjalan lokal via Laragon. Dicatat untuk **dua jalur** — shared hosting (cPanel) & VPS —
supaya fleksibel mengikuti kebijakan dosen / anggaran.

**Kenapa:** Untuk **demo sidang** (penguji bisa mengakses web admin & app Android konek ke API lewat
HTTPS). Bukan produksi permanen SkyNet, jadi hardening secukupnya. Firebase FCM **sudah aktif &
teruji** di Android — sisi server tinggal memakai service account JSON yang sama.

### Kabar baik — aplikasi ini relatif ramah deploy

- **Timezone sudah `Asia/Jakarta`** ([config/app.php](config/app.php)) → waktu selesai/durasi/GPS
  benar tanpa setup tambahan.
- **FCM dikirim sinkron**, bukan via queue ([NotificationObserver](app/Observers/NotificationObserver.php#L29))
  → **tidak wajib menjalankan queue worker**. (Trade-off: aksi pemicu notif menunggu sebentar
  round-trip FCM; gagal pun sudah di-`try/catch`.)
- **Peta OSM tanpa API key** + **API Android pakai token, bukan cookie** → tak ada ribet CORS.
- **Belum ada cron wajib** (cleanup `location_logs` / fitur #1 belum dibuat).

### ⚠️ Checklist `.env` produksi — yang wajib disesuaikan dari nilai dev

`.env.example` sekarang **sudah memuat konfigurasi proyek yang benar** (`DB_CONNECTION=mysql`,
`SESSION_DRIVER=file`, ada `FIREBASE_CREDENTIALS`) — jadi `sessions`/sqlite tidak lagi jadi ranjau.
Sisa yang **wajib diubah** saat naik ke produksi (jangan pakai nilai dev apa adanya):

| # | Nilai dev (`.env.example`) | Wajib jadi (produksi) | Akibat kalau lalai |
|---|---|---|---|
| 1 | `APP_ENV=local`, `APP_DEBUG=true` | `production` / **`false`** | Halaman error membocorkan kredensial & query |
| 2 | `APP_URL=http://localhost` | URL HTTPS domain produksi | Link absolut / aset salah arah |
| 3 | DB lokal (`root`, tanpa password) | Kredensial DB server produksi | App tak konek DB produksi |
| 4 | `FIREBASE_CREDENTIALS=` (kosong) | Path ke JSON service account di server | Push FCM mati |
| 5 | — (document root) | Docroot → folder **`public/`** | `.env` & seluruh source code terekspos publik |

JSON Firebase: **jangan di webroot, jangan di-commit git** (sudah di `.gitignore`).

> Catatan: poin #1 (`SESSION_DRIVER=file`) & #2 (`mysql`) yang dulu jadi "ranjau" sudah hilang sejak
> `.env.example` diselaraskan dengan setup proyek — kini cukup salin `.env.example` lalu sesuaikan
> tabel di atas.

### Langkah umum (berlaku di kedua jalur)

1. Siapkan **domain/subdomain + SSL** (Android modern memblokir HTTP cleartext → HTTPS wajib).
2. Upload kode (Git / FTP), arahkan **document root ke `public/`**.
3. `composer install --no-dev --optimize-autoloader`
4. Asset: `npm run build` (di shared hosting tanpa Node → build lokal lalu upload `public/build`).
5. `.env` produksi (lihat checklist penyesuaian di atas) → `php artisan key:generate`.
6. `php artisan migrate --force` → buat/seed **admin pertama** → `config:cache route:cache view:cache`
   → `php artisan storage:link` (penting bila fitur foto bukti #5 dibuat).
7. **Android**: ubah **base URL** ke `https://domain-mu/...`, rebuild APK. Firebase tak berubah
   (project & `google-services.json` sama).
8. Uji hari-H: login admin → buat laporan → assign teknisi (cek push FCM masuk HP) → update status
   dari Android → GPS muncul di Monitoring.

### Jalur A — Shared hosting / cPanel (paling cepat & murah)

**Cocok bila:** sekadar butuh online untuk demo, anggaran minim, tak mau urus ops server.

- Provider (Niagahoster / Hostinger / Rumahweb / Jagoan Hosting sejenis) — **wajib cek 4 hal**:
  PHP 8.3, **akses SSH/Terminal** (untuk `artisan migrate`), **SSL gratis**, MySQL.
- Dapat **subdomain + SSL gratis** dari provider → tak perlu beli domain untuk demo.
- Kendala khas: docroot harus diarahkan ke `public/` (atur via menu Domains/Addon), build asset
  dilakukan lokal, migrasi lewat SSH/Terminal cPanel.
- **Plan B gratis:** laptop + **Cloudflare Tunnel** (URL HTTPS gratis). Hanya cadangan — bergantung
  laptop & internet nyala saat sidang.

### Jalur B — VPS (lebih kuat & "nyata", cocok bila jadi kebijakan dosen)

**Cocok bila:** ingin kontrol penuh, ingin mengaktifkan cron/queue, atau diarahkan dosen.

**Keunggulan untuk app ini:**
- Docroot bersih (Nginx `root` → `/public`) → **ranjau #5 hilang**.
- Ada Node + Composer di server → deploy cukup `git pull` + `npm run build` (tak perlu upload
  `public/build` manual).
- **Cron sungguhan** → fitur **#1 (cleanup `location_logs`)** jadi benar-benar bisa dijalankan
  (`* * * * * php artisan schedule:run`).
- Bisa pasang **Supervisor queue worker** → opsional pindahkan FCM ke queue agar aksi admin instan.
- Lebih berbobot di laporan implementasi (BAB 4).

**Konsekuensi:**
- Setup manual Nginx + PHP-FPM + MySQL + SSL (+ Supervisor) — ada kurva belajar.
- **Keamanan server jadi tanggung jawab sendiri**: SSH pakai key (bukan password), firewall (`ufw`),
  update rutin.
- **Wajib domain sendiri**: Let's Encrypt **tak menerbitkan SSL untuk IP telanjang**. Domain murah
  (~Rp15–50rb/th) + opsional **Cloudflare gratis** di depan (SSL + proteksi dasar).
- Set **timezone OS `Asia/Jakarta`** (app-level sudah benar, ini untuk konsistensi log).

**Peredam beban setup:** pakai panel manajemen — **Ploi** (ada paket gratis), **Laravel Forge**
(berbayar, paling mulus), atau **RunCloud**. Memprovisikan Nginx + PHP 8.3 + MySQL + SSL + Supervisor
+ cron otomatis lewat web panel — kekuatan VPS tanpa derita konfigurasi manual.

### Perbandingan singkat

| Aspek | Shared hosting | VPS |
|---|---|---|
| Biaya | Termurah (subdomain+SSL gratis) | VPS + domain (+ panel opsional) |
| Setup | Paling mudah | Lebih rumit (atau pakai panel) |
| Domain | Tak wajib (pakai subdomain provider) | **Wajib** (untuk SSL) |
| Queue worker / cron | Sulit/terbatas | **Bisa** (buka fitur #1) |
| Keamanan server | Diurus provider | Tanggung jawab sendiri |
| Kesan di laporan | Standar | Lebih "nyata"/berbobot |

### Catatan / keputusan yang masih terbuka

- **Admin pertama** ✅ **sudah** dibuat lewat seeder ([UserSeeder.php](database/seeders/UserSeeder.php),
  `admin@skynet.test`) — otomatis saat `migrate --seed`, tak perlu `tinker` manual. Ganti
  password/email default ini setelah deploy.
- **Akses penguji setelah sidang?** Bila perlu tetap online, **hindari Plan B (tunnel)** — pakai
  hosting yang nyala terus.
- **Hardening proporsional**: karena demo, cukup `APP_DEBUG=false` + HTTPS + docroot benar. Backup
  DB / monitoring / retensi data **belum perlu** (itu ranah produksi SkyNet bila kelak dipakai nyata).

---

## 12. Peningkatan peta Monitoring (marker, trail, GPS basi) 💡

**Apa:** Tiga peningkatan tampilan/akurasi peta [Monitoring](resources/views/livewire/pages/monitoring.blade.php)
yang fondasinya sudah ada (polling tak reset view, marker diff, anti N+1 lewat
[GetActiveTechnicianLocations](app/Actions/GetActiveTechnicianLocations.php)) tapi bisa dibuat
lebih informatif:

| # | Ide | Berdampak vs effort |
|---|---|---|
| **A. Marker custom** | Ganti pin biru default Leaflet → `L.divIcon` berisi inisial/nama teknisi + warna khas, jadi "siapa di mana" terbaca tanpa klik popup | Paling cepat, paling kentara untuk demo |
| **B. Jejak/trail GPS** | Gambar `L.polyline` dari beberapa titik terakhir pergerakan teknisi selama sesi `sedang_memperbaiki` — bukan cuma 1 titik terakhir | Paling "menjual" naratif monitoring; datanya sudah penuh di `location_logs` |
| **C. Indikator GPS basi (stale)** | Bila titik terakhir > X menit, marker & baris sidebar diberi warna abu/oranye "GPS terputus" | Penting: admin tahu data usang vs teknisi benar-benar diam |

**Kenapa:** Saat ini semua teknisi tampil sebagai pin biru identik (tak terbedakan), peta hanya
menampilkan satu titik terakhir (tak ada cerita pergerakan), dan `last_update` sudah dihitung di
Action tapi belum dipakai untuk menandai data basi. Ketiganya memperkuat narasi "monitoring
perbaikan realtime" yang paling mungkin disorot saat sidang.

**Bagaimana:**
- **A** — di blok `@script`, ganti `L.marker([lat,lng])` jadi `L.marker([lat,lng], { icon: L.divIcon({...}) })`.
  Cukup ubah view; data dari Action tak berubah.
- **B** — perluas Action (atau method baru) untuk menarik N titik terakhir per teknisi (bukan hanya
  `MAX(recorded_at)` tunggal), lalu render `L.polyline`. **Bergantung pada fitur #1** (retensi data):
  jangan pakai pendekatan hapus-total tanpa tenggang, atau titiknya keburu hilang. Beririsan dengan
  **fitur #2** (trail di Riwayat) — bedanya ini trail *live* saat masih dikerjakan.
- **C** — di Action sudah ada `recorded_at`; tambahkan flag `is_stale` (mis. `recorded_at < now()->subMinutes(5)`)
  ke payload, lalu warnai marker/sidebar berdasarkan flag itu di view.

**Catatan:** Murni lapisan presentasi + sedikit logika di Action — **0 perubahan Android/DB**
(kecuali B yang butuh titik GPS tetap tersimpan). Rekomendasi urutan: **A → C → B** (cepat dulu,
trail terakhir karena paling banyak kerja & terikat retensi data). Polling 10 detik = full Livewire
round-trip; untuk skala SkyNet biarkan apa adanya, jangan over-engineer jadi WebSocket.

---

## 13. Sudah dikerjakan (arsip)

Catatan ringkas hal yang sudah selesai, biar konteksnya tidak hilang.

- **Optimasi baterai GPS (sisi Android)** 📱 — strategi pengambilan & pengiriman GPS saat status
  `sedang_memperbaiki` sudah disetel hemat baterai di repo Android (a.l. `FusedLocationProviderClient`,
  `setSmallestDisplacement`, priority `BALANCED_POWER_ACCURACY`, interval & foreground service yang
  berhenti total saat `done`). Faktor utama boros/tidaknya baterai ada di sisi HP ini, bukan server. ✅
- **Index komposit `location_logs`** — `(technician_id, report_id, recorded_at)` via
  [migrasi 2026_06_10](database/migrations/2026_06_10_000000_add_index_to_location_logs_table.php).
  Mempercepat query "titik terbaru per teknisi" yang dipanggil tiap 10 detik. ✅
- **`recorded_at` dari device** — `POST /api/location` kini menerima `recorded_at` opsional
  (waktu GPS diambil di HP, lebih akurat dari waktu sampai server), dengan guard menolak jam
  device yang melenceng ke masa depan. Backward-compatible. ✅
  - *Follow-up tersisa:* pastikan sisi Android benar-benar **mengirim** field `recorded_at` ini saat
    POST lokasi (terpisah dari optimasi baterai di atas). Kalau belum dikirim pun tidak error —
    server otomatis pakai waktu terima sebagai fallback.
