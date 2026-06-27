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
      "is_read": false,
      "created_at": "2025-06-01T08:00:00+07:00"
    }
  ]
}
```

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

- Backend mengirim pesan **notification-only** (tanpa blok `data`):
  ```json
  { "notification": { "title": "Tugas Baru", "body": "Anda ditugaskan ke ..." } }
  ```
- Konsekuensinya, tap notifikasi **tidak** bisa deep-link ke tugas spesifik (tak ada `report_id`
  di payload). Ini disengaja.

> **Bila kelak ingin deep-link** ("tap notif → buka Detail Tugas"), perubahan **sisi backend**
> yang diperlukan: tambah `->withData(['report_id' => ..., 'type' => 'task_assigned'])` di
> `NotificationObserver`. Tabel `notifications` saat ini hanya menyimpan `user_id, title, body,
> is_read`, jadi sumber `report_id` perlu didesain dulu (lihat juga [CATATAN-FITUR.md](CATATAN-FITUR.md)).

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
