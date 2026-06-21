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
      "customer": "Pak Ahmad",
      "address": "Jl. Mawar No.3, Cikarang",
      "damage_type": "Kabel Putus",
      "notes": "Sinyal hilang total sejak kemarin",
      "assigned_at": "2025-06-01T08:00:00+07:00"
    }
  ]
}
```
- `status` di endpoint ini hanya `"ditugaskan"` atau `"sedang_memperbaiki"`. Tugas `"selesai"`
  tidak muncul.
- **`address` adalah teks biasa, BUKAN koordinat.** `damage_reports` tidak menyimpan lat/lng
  pelanggan — tidak ada pin pelanggan / navigasi-ke-lokasi. GPS yang dikirim klien hanya posisi
  teknisi (untuk dipantau admin).

### Detail Tugas — sama dengan list + `work_logs`
```
GET /api/tasks/{id}

200:
{
  "data": {
    "id": 1,
    "report_id": 5,
    "status": "ditugaskan",
    "customer": "Pak Ahmad",
    "address": "Jl. Mawar No.3, Cikarang",
    "damage_type": "Kabel Putus",
    "notes": "Sinyal hilang total",
    "assigned_at": "2025-06-01T08:00:00+07:00",
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

### Update Status Tugas
```
POST /api/tasks/{id}/status
Body: { "status": "in_progress" }    // atau "done"

200: { "message": "Status diperbarui", "status": "sedang_memperbaiki" }
422: { "message": "Perubahan status tidak valid dari status saat ini" }
```

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
