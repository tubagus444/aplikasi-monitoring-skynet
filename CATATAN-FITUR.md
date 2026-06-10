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
2. [Optimasi baterai GPS](#2-optimasi-baterai-gps) 📱
3. [Tampilkan jejak rute GPS di Riwayat](#3-tampilkan-jejak-rute-gps-di-riwayat) 💡
4. [Sudah dikerjakan (arsip)](#4-sudah-dikerjakan-arsip)

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

**Catatan:** kalau nanti mau bikin fitur #3 (jejak rute di Riwayat), **jangan pakai pendekatan A
tanpa tenggang** — pilih **B** atau perpanjang masa tenggang, supaya titik-titiknya masih ada
saat mau digambar jadi rute.

---

## 2. Optimasi baterai GPS 📱

**Apa:** Pengaturan strategi pengambilan & pengiriman GPS di app Android teknisi supaya hemat
baterai tapi tetap akurat. **Faktor utama boros/tidaknya baterai ada di sini**, bukan di server
Laravel (server cuma menerima apa pun yang dikirim HP).

**Kenapa:** Tanpa strategi yang tepat, HP teknisi bisa cepat habis baterai saat status
`sedang_memperbaiki` (GPS aktif). Yang diincar: posisi tetap akurat saat teknisi bergerak,
tapi hemat saat ia diam bekerja di satu titik.

**Bagaimana (semua di kode Kotlin repo Android):**

| Parameter | Rekomendasi | Kenapa |
|---|---|---|
| Provider | `FusedLocationProviderClient` (Play Services) | Gabung GPS+WiFi+cell+sensor, jauh lebih hemat dari `LocationManager` mentah |
| `setSmallestDisplacement` | **10–25 meter** | **Kunci hemat.** Teknisi yang diam (sedang memperbaiki) tidak memicu update terus-menerus |
| Priority | `PRIORITY_BALANCED_POWER_ACCURACY` (~100 m) | Cukup untuk lihat posisi di peta. `HIGH_ACCURACY` = chip GPS penuh = paling boros |
| Interval | `interval` 15–20 dtk, `fastestInterval` 10 dtk | Tiap kirim menyalakan radio seluler (*tail energy* ~20 dtk). Jangan kirim tiap 5 dtk |
| Foreground service | Wajib (Android 8+), **stop total saat `done`** | Pastikan benar-benar berhenti saat `selesai`, jangan cuma pause |

**Catatan:** Server **sudah siap** menerima field `recorded_at` (waktu GPS diambil di device)
di `POST /api/location` — lihat [LocationController.php](app/Http/Controllers/Api/LocationController.php).
Tinggal sisi Android yang menyertakannya, mis.:
```json
{ "report_id": 12, "latitude": -6.37, "longitude": 107.16, "recorded_at": "2026-06-10T14:05:30+07:00" }
```
Kalau belum dikirim pun tidak error — server otomatis pakai waktu terima sebagai fallback.

---

## 3. Tampilkan jejak rute GPS di Riwayat 💡

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

## 4. Sudah dikerjakan (arsip)

Catatan ringkas hal yang sudah selesai, biar konteksnya tidak hilang.

- **Index komposit `location_logs`** — `(technician_id, report_id, recorded_at)` via
  [migrasi 2026_06_10](database/migrations/2026_06_10_000000_add_index_to_location_logs_table.php).
  Mempercepat query "titik terbaru per teknisi" yang dipanggil tiap 10 detik. ✅
- **`recorded_at` dari device** — `POST /api/location` kini menerima `recorded_at` opsional
  (waktu GPS diambil di HP, lebih akurat dari waktu sampai server), dengan guard menolak jam
  device yang melenceng ke masa depan. Backward-compatible. ✅
  - *Follow-up tersisa:* sisi Android perlu mulai mengirim field ini (lihat fitur #2).
