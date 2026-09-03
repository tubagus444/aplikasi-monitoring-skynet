# Kontrak API Backend untuk Klien Android — SkyNet RT/RW Net

Dokumen ini mendefinisikan **kontrak HTTP** antara backend Laravel (repo ini) dan aplikasi
Android teknisi. Ia hanya mencakup apa yang **dilayani & dimiliki backend**: bentuk
request/response, aturan status, dan semantik error.

> **Internal aplikasi Android** (struktur package, Jetpack Compose, Hilt, GPS foreground
> service, build/Gradle, pengujian) **TIDAK** didokumentasikan di sini — itu milik repo Android
> dan tinggal di `CLAUDE.md` repo tersebut (sumber kebenaran tunggal untuk sisi klien).
> File ini sengaja diringkas agar tidak menjadi salinan yang gampang usang.

Endpoint juga terangkum di [CLAUDE.md](CLAUDE.md) (tabel "API Android") — di sini versi
detail dengan contoh JSON.

---

## Konvensi Umum

- Base URL: **`/api/`**.
- Semua request **kecuali login** wajib menyertakan header:
  ```
  Authorization: Bearer {token}
  Accept: application/json
  ```
- Auth memakai **Laravel Sanctum** (token, bukan cookie/session).
- Semua response error selalu punya field **`message`** (tampilkan ke user). Error validasi
  juga menyertakan objek `errors`.
- Login hanya untuk akun ber-role **`teknisi`**; admin tidak bisa masuk via API. **Tidak ada
  endpoint registrasi** — akun teknisi dibuat admin lewat web.

---

## Autentikasi

### Login
```
POST /api/auth/login                       (throttle: 5 percobaan / menit / IP)
Body: { "email": "string", "password": "string" }

200:
{
  "token": "1|xxxxxxxxxxx",
  "user": { "id": 1, "name": "Budi", "email": "budi@skynet.id", "role": "teknisi" }
}

401: { "message": "Email atau password salah" }
422: { "message": "...", "errors": { "email": ["..."] } }   // field kosong/format salah
```

### Logout
```
POST /api/auth/logout
200: { "message": "Logout berhasil" }
```
> Hanya menghapus token di sisi server. Klien tetap wajib membersihkan token lokal walau
> request gagal (mis. offline).

### Update FCM Token
```
PUT /api/auth/fcm-token
Body: { "fcm_token": "string" }
200: { "message": "FCM token diperbarui" }
```

---

## Tugas

### Daftar Tugas — hanya tugas milik teknisi yang login, status aktif saja
```
GET /api/tasks

200:
{
  "data": [
    {
      "id": 1,
      "report_id": 5,
      "status": "ditugaskan",
      "category": "pelanggan",
      "headline": "Pak Ahmad",
      "customer": "Pak Ahmad",
      "address": "Jl. Mawar No.3, Cikarang",
      "damage_type": "Kabel Putus",
      "notes": "Sinyal hilang total sejak kemarin",
      "assigned_at": "2025-06-01T08:00:00+07:00",
      "phone": "081234567890",
      "ip_address": "192.168.10.5",
      "subscription_package": "20 Mbps"
    }
  ]
}
```
- `status` di endpoint ini hanya `"ditugaskan"` atau `"sedang_memperbaiki"`. Tugas `"selesai"`
  tidak muncul.
- **`category`** = `"pelanggan"` | `"jaringan"` | `"pemeliharaan"`. **UI detail tugas wajib adaptif
  per kategori** (lihat di bawah).
- **`headline`** = judul tampilan apa pun kategorinya (`customer ?? title`). Pakai ini sebagai
  judul kartu tugas. Untuk `pelanggan` = nama pelanggan; untuk `jaringan`/`pemeliharaan` = judul
  laporan.
- **Field khusus pelanggan** (`customer`, `phone`, `ip_address`, `subscription_package`):
  terisi hanya untuk `category == "pelanggan"`; untuk kategori lain **`null`**. `customer` adalah
  snapshot nama saat laporan dibuat.
- **`address`** = alamat pelanggan (kategori pelanggan) **atau** lokasi/area terdampak (kategori
  jaringan/pemeliharaan). Tetap teks biasa, **BUKAN koordinat** — tidak ada pin/navigasi pelanggan.
  GPS yang dikirim klien hanya posisi teknisi (untuk dipantau admin). Untuk membantu menemukan
  rumah, pakai **`house_photos`** di endpoint detail.

### Detail Tugas — sama dengan list + `house_photos` + `work_logs`
```
GET /api/tasks/{id}

200:
{
  "data": {
    "id": 1,
    "report_id": 5,
    "status": "ditugaskan",
    "category": "pelanggan",
    "headline": "Pak Ahmad",
    "customer": "Pak Ahmad",
    "address": "Jl. Mawar No.3, Cikarang",
    "damage_type": "Kabel Putus",
    "notes": "Sinyal hilang total",
    "assigned_at": "2025-06-01T08:00:00+07:00",
    "phone": "081234567890",
    "ip_address": "192.168.10.5",
    "subscription_package": "20 Mbps",
    "house_photos": [
      "http://host/storage/customer-photos/abc.jpg"
    ],
    "repair_photos": [
      {
        "url": "http://host/storage/report-photos/xyz.jpg",
        "caption": "Kondisi sesudah",
        "technician": "Budi",
        "uploaded_at": "2025-06-01T10:15:00+07:00"
      }
    ],
    "work_logs": [
      {
        "status": "ditugaskan",
        "technician": "Budi",
        "description": null,
        "logged_at": "2025-06-01T08:00:00+07:00"
      }
    ]
  }
}
```
- **`house_photos`** (hanya di endpoint detail): array URL absolut foto rumah pelanggan (alat bantu
  menemukan lokasi karena alamat perkampungan sering tak presisi). **Kosong `[]`** untuk kategori
  non-pelanggan atau bila pelanggan belum punya foto.
- **`repair_photos`** (hanya di endpoint detail): array foto **bukti pekerjaan** yang diunggah teknisi
  (lihat "Unggah Foto" di bawah). Tiap item `{ url, caption, technician, uploaded_at }`, urut naik
  `uploaded_at`. **Kosong `[]`** bila belum ada foto. Beda peran dengan `house_photos`: `repair_photos`
  = bukti hasil kerja (semua kategori), `house_photos` = wayfinding rumah (khusus pelanggan).

### Update Status Tugas
```
POST /api/tasks/{id}/status
Body: { "status": "in_progress" }                               // atau "done"
Body: { "status": "done", "description": "Ganti konektor RJ45" } // + catatan pekerjaan (opsional)

200: { "message": "Status diperbarui", "status": "sedang_memperbaiki" }
422: { "message": "Perubahan status tidak valid dari status saat ini" }
```

- **`description`** (opsional, `max:1000`): catatan/narasi pekerjaan teknisi untuk transisi ini.
  Tersimpan di work log transisi → muncul kembali di `work_logs[].description` pada endpoint detail
  & di timeline Riwayat web admin. Field lama yang sudah ada (dulu selalu `null`) — kini bisa diisi.
  Paling relevan dikirim bersama `"done"` (rangkuman apa yang dikerjakan), tapi boleh di `in_progress`
  juga. Jika dikirim pada request **idempotent** (status sudah sesuai, tak ada transisi), catatan
  **tidak tersimpan** karena tak ada work log baru untuk dilekati.

> **Status Mapping (hidden contract):**
>
> | Android kirim | Database menyimpan |
> |---|---|
> | `in_progress` | `sedang_memperbaiki` |
> | `done` | `selesai` |
> | — | `ditugaskan` (hanya dari web admin) |
>
> Transisi **searah**: `ditugaskan` → `in_progress` → `done`. Loncat/mundur dibalas `422`.
>
> **Idempotent:** status itu **milik laporan, bukan per-teknisi**. Bila laporan sudah di status
> tujuan (teknisi lain di tim sudah memindahkannya), request dianggap **sukses `200`** tanpa
> membuat work log ganda — bukan ditolak. Klien boleh memperlakukan 200 sebagai berhasil.

### Unggah Foto

Dua endpoint upload, **keduanya `multipart/form-data`** (bukan JSON) dan **di-scope ke kepemilikan
tugas**: `{id}` = id tugas (assignment) milik teknisi yang login. Tugas milik orang lain → `404`.

```
POST /api/tasks/{id}/photos          # foto BUKTI PEKERJAAN (sebelum/sesudah) → report_photos
POST /api/tasks/{id}/house-photos    # foto RUMAH PELANGGAN (wayfinding)      → customer_photos

Form fields:
  photo    : file gambar (wajib, jpg/png/…, maks 5 MB)
  caption  : teks (opsional, maks 255)

201: { "message": "...diunggah", "data": { "id": 12, "url": "http://host/storage/...", "caption": "..." } }
422: validasi gagal (bukan gambar / >5 MB), ATAU house-photos pada tugas non-pelanggan
404: tugas bukan milik teknisi
```

- **`/photos`** berlaku untuk **semua kategori** laporan; muncul kembali di `repair_photos` pada
  endpoint detail. Boleh dipanggil berkali-kali (append — beberapa foto per laporan).
- **`/house-photos`** hanya valid untuk laporan **kategori pelanggan** (punya `customer_id`); pada
  tugas non-pelanggan dibalas `422`. Muncul kembali di `house_photos`. (Fase 2 modul Pelanggan —
  melengkapi upload dari web admin.)
- **Catatan desain:** sengaja lewat `/tasks/{id}/...` (bukan `/customers/{id}/photos` mentah) agar
  otorisasi alami — teknisi hanya bisa menambah foto untuk tugas yang ditugaskan padanya. `uploaded_by`
  terisi otomatis dari token.

---

## GPS

### Kirim Koordinat Lokasi
```
POST /api/location
Body: {
  "report_id": 5,
  "latitude": -6.2631,
  "longitude": 107.0059,
  "recorded_at": "2026-06-10T14:05:30+07:00"   // opsional
}

200: { "message": "Lokasi dicatat" }
403: { "message": "Tidak dapat mengirim lokasi — laporan tidak aktif atau bukan tugas Anda" }
```
- Backend hanya menerima koordinat jika laporan **`sedang_memperbaiki`** DAN `report_id`
  memang milik teknisi yang login. Gunakan **`report_id`** (bukan `task id`).
- **`recorded_at`** (opsional, ISO-8601 + offset zona): waktu fix GPS sebenarnya di perangkat,
  agar akurat walau pengiriman tertunda. Backward-compatible — bila tidak dikirim, server pakai
  waktu terima. Jam perangkat yang melenceng ke **masa depan diabaikan** (di-clamp ke waktu server).

---

## Notifikasi

### Daftar Notifikasi
```
GET /api/notifications

200:
{
  "data": [
    {
      "id": 1,
      "title": "Tugas Baru",
      "body": "Anda ditugaskan ke laporan Pak Ahmad di Jl. Mawar No.3",
      "type": "task_assigned",
      "related_id": 5,
      "is_read": false,
      "created_at": "2025-06-01T08:00:00+07:00"
    }
  ]
}
```

- **`type`** (nullable string): jenis notifikasi — `"task_assigned"` | `"task_in_progress"` |
  `"task_completed"`. `null` pada notifikasi lama (sebelum migrasi).
- **`related_id`** (nullable int): ID laporan (`damage_reports.id`) terkait untuk deep-link.
  `null` pada notifikasi tanpa referensi laporan atau notifikasi lama.

### Tandai Sudah Dibaca
```
PUT /api/notifications/{id}/read
200: { "message": "Notifikasi ditandai sudah dibaca" }
```
> Tidak ada endpoint "jumlah belum dibaca" — hitung badge di klien dari list (`is_read == false`).

---

## Push Notification (FCM) — sisi backend

Yang dikirim backend ditentukan oleh `NotificationObserver` (otomatis tiap `Notification::create()`).
Yang relevan untuk klien:

- Backend mengirim pesan **notification + data** (bila `type`/`related_id` ada):
  ```json
  {
    "notification": { "title": "Tugas Baru", "body": "Anda ditugaskan ke ..." },
    "data": { "type": "task_assigned", "related_id": "5" }
  }
  ```
  Bila `type`/`related_id` null (notifikasi lama), blok `data` **tidak ada** — sama seperti
  sebelumnya (notification-only). Backward-compatible.

- **Deep-link dari notifikasi** kini dimungkinkan: baca `related_id` dari blok `data` (selalu
  string di FCM → parse ke Int), cari task assignment dengan `report_id` == `related_id`, lalu
  navigasi ke `tasks/{taskId}`. Perubahan Android yang diperlukan:
  1. Tambah `type: String?` dan `relatedId: Int?` ke model `NotificationResponse`.
  2. Di `MonitoringFirebaseService.onMessageReceived`: baca `remoteMessage.data["related_id"]`,
     bangun `PendingIntent` dengan extra `related_id`.
  3. Di `MainActivity`: baca `intent.extras["related_id"]` untuk navigasi ke detail tugas.
  4. Di `NotificationScreen`: klik notifikasi dengan `relatedId` → navigasi ke detail tugas.

---

## Semantik Error

| Status | Arti | Tindakan klien yang disarankan |
|---|---|---|
| `401` | Token tidak valid / kadaluarsa | Hapus token, paksa ke layar Login |
| `403` | Aksi tidak diizinkan (mis. kirim lokasi saat laporan tidak aktif) | Tampilkan `message` |
| `422` (bisnis) | Transisi status tidak valid | Tampilkan `message` |
| `422` (validasi) | Field salah/kosong — ada objek `errors` | Tampilkan pesan dari `errors`/`message` |
| `404` | Resource tidak ada **ATAU** tugas bukan milik teknisi ini | Perlakukan sebagai "tidak ditemukan" |
| Network error | Tidak ada koneksi | Tampilkan "Periksa koneksi internet Anda" |

> Endpoint task & notifikasi memakai `findOrFail` yang **di-scope ke `technician_id` pemilik**.
> Meminta tugas milik teknisi lain dibalas **`404`** (bukan `403`).
