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
5. [Sudah dikerjakan (arsip)](#5-sudah-dikerjakan-arsip)

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
(sisakan titik) atau masa tenggang yang panjang.

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

## 5. Sudah dikerjakan (arsip)

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
