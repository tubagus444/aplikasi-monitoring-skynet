# Aplikasi Monitoring Teknisi — SkyNet RT/RW Net (Android)

Aplikasi Android untuk teknisi lapangan SkyNet RT/RW Net, Kab. Bekasi. Digunakan untuk
menerima tugas perbaikan jaringan, update status pengerjaan, dan mengirim lokasi GPS
secara realtime ke admin.

Proyek ini adalah **klien mobile** dari backend Laravel di repo `Aplikasi-Monitoring`.
Studi kasus skripsi, metode RAD.

## Stack Teknologi

| Layer | Teknologi |
|---|---|
| Bahasa | Kotlin |
| UI | Jetpack Compose |
| Arsitektur | MVVM (ViewModel + StateFlow) |
| Networking | Retrofit 2 + OkHttp 3 |
| Dependency Injection | Hilt |
| Penyimpanan Token | DataStore Preferences |
| Push Notifikasi | Firebase Cloud Messaging (FCM) |
| GPS | FusedLocationProviderClient (Foreground Service) |
| Navigasi | Navigation Compose |
| Image Loading | Coil |

## Perintah Umum

```bash
# Build debug APK
./gradlew assembleDebug

# Run unit test
./gradlew test

# Run instrumented test
./gradlew connectedAndroidTest

# Lint
./gradlew lint
```

## Konfigurasi Environment

### Base URL API

Backend Laravel berjalan di `http://localhost:8000` (Laragon, Windows). Android tidak bisa
akses `localhost` langsung — gunakan:

```kotlin
// app/src/main/java/.../data/api/ApiService.kt atau Constants.kt
const val BASE_URL = "http://10.0.2.2:8000/api/"   // emulator Android
// const val BASE_URL = "http://192.168.x.x:8000/api/"  // device fisik (ganti IP)
```

### Firebase

- Download `google-services.json` dari Firebase Console (project yang sama dengan backend)
- Letakkan di folder `app/`
- `google-services.json` **jangan di-commit** — tambahkan ke `.gitignore`

## Struktur Package

```
app/src/main/java/com/skynet/monitoring/
├── di/
│   ├── NetworkModule.kt         # Retrofit, OkHttp, ApiService, AuthInterceptor
│   └── RepositoryModule.kt      # Binding interface → implementasi repository
├── data/
│   ├── api/
│   │   ├── ApiService.kt        # Semua endpoint Retrofit (interface)
│   │   └── model/               # Data class request & response
│   │       ├── AuthModels.kt
│   │       ├── TaskModels.kt
│   │       ├── LocationModels.kt
│   │       └── NotificationModels.kt
│   ├── repository/
│   │   ├── AuthRepository.kt
│   │   ├── TaskRepository.kt
│   │   ├── LocationRepository.kt
│   │   └── NotificationRepository.kt
│   └── local/
│       └── UserPreferences.kt   # DataStore: simpan token + data user
├── ui/
│   ├── navigation/
│   │   └── AppNavGraph.kt       # NavHost + definisi semua route
│   ├── screens/
│   │   ├── auth/
│   │   │   ├── LoginScreen.kt
│   │   │   └── LoginViewModel.kt
│   │   ├── tasks/
│   │   │   ├── TaskListScreen.kt
│   │   │   ├── TaskListViewModel.kt
│   │   │   ├── TaskDetailScreen.kt
│   │   │   └── TaskDetailViewModel.kt
│   │   ├── working/
│   │   │   ├── WorkingScreen.kt
│   │   │   └── WorkingViewModel.kt
│   │   ├── notifications/
│   │   │   ├── NotificationScreen.kt
│   │   │   └── NotificationViewModel.kt
│   │   └── profile/
│   │       ├── ProfileScreen.kt
│   │       └── ProfileViewModel.kt
│   ├── components/              # Composable reusable: StatusBadge, TaskCard, dll.
│   └── theme/
│       ├── Theme.kt
│       ├── Color.kt
│       └── Type.kt
└── service/
    └── LocationService.kt       # Foreground Service pengiriman GPS berkala
```

## Kontrak API Backend

Base URL: `/api/` — semua request kecuali login wajib menyertakan header:
```
Authorization: Bearer {token}
```

---

### Autentikasi

**Login**
```
POST /api/auth/login
Body: { "email": "string", "password": "string" }

Response 200:
{
  "token": "1|xxxxxxxxxxx",
  "user": { "id": 1, "name": "Budi", "email": "budi@skynet.id", "role": "teknisi" }
}

Response 401: { "message": "Email atau password salah" }
Response 422: { "message": "...", "errors": { "email": ["..."] } }   // validasi gagal (field kosong/format salah)
```

> Login hanya bisa dengan akun ber-role `teknisi`. Akun admin tidak bisa masuk via API.
> **Tidak ada endpoint registrasi** — akun teknisi dibuat admin lewat web. App TIDAK punya
> layar daftar/sign-up; jangan men-scaffold-nya.

**Logout**
```
POST /api/auth/logout

Response 200: { "message": "Logout berhasil" }
```

> `logout` hanya menghapus token di sisi server. App **tetap wajib** membersihkan token +
> user dari DataStore secara lokal walau request gagal (mis. offline), lalu arahkan ke Login.

**Update FCM Token**
```
PUT /api/auth/fcm-token
Body: { "fcm_token": "string" }

Response 200: { "message": "FCM token diperbarui" }
```

---

### Tugas

**Daftar Tugas** — hanya tugas milik teknisi yang login, status aktif saja
```
GET /api/tasks

Response 200:
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

`status` hanya akan berisi `"ditugaskan"` atau `"sedang_memperbaiki"` di endpoint ini.
Tugas yang sudah `"selesai"` tidak muncul.

> **`address` adalah teks biasa, BUKAN koordinat.** Tabel `damage_reports` tidak menyimpan
> lat/lng pelanggan — jadi app TIDAK bisa menampilkan pin pelanggan di peta atau fitur
> "navigasi ke lokasi". GPS yang dikirim app hanya posisi teknisi (untuk dipantau admin).
> Tampilkan `address` sebagai teks saja (atau buka di Google Maps via intent `geo:0,0?q=<address>`).

**Detail Tugas** — sama dengan list, ditambah `work_logs`
```
GET /api/tasks/{id}

Response 200:
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

**Update Status Tugas**
```
POST /api/tasks/{id}/status
Body: { "status": "in_progress" }    // atau "done"

Response 200: { "message": "Status diperbarui", "status": "sedang_memperbaiki" }
Response 422: { "message": "Perubahan status tidak valid dari status saat ini" }
```

> **Penting — Status Mapping (hidden contract):**
>
> | Android kirim  | Database menyimpan    |
> |----------------|-----------------------|
> | `in_progress`  | `sedang_memperbaiki`  |
> | `done`         | `selesai`             |
> | —              | `ditugaskan`          |
>
> Transisi **hanya searah**: `ditugaskan` → `in_progress` → `done`.
> Loncat atau mundur akan dibalas `422`.

---

### GPS

**Kirim Koordinat Lokasi**
```
POST /api/location
Body: { "report_id": 5, "latitude": -6.2631, "longitude": 107.0059 }

Response 200: { "message": "Lokasi dicatat" }
Response 403: { "message": "Tidak dapat mengirim lokasi — laporan tidak aktif atau bukan tugas Anda" }
```

Backend hanya menerima koordinat jika status laporan `sedang_memperbaiki` dan `report_id`
memang milik teknisi yang sedang login. Gunakan `report_id` (bukan `task id`) saat kirim.

---

### Notifikasi

**Daftar Notifikasi**
```
GET /api/notifications

Response 200:
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

**Tandai Sudah Dibaca**
```
PUT /api/notifications/{id}/read

Response 200: { "message": "Notifikasi ditandai sudah dibaca" }
```

> Tidak ada endpoint "jumlah belum dibaca". Hitung badge sendiri di client dari list:
> `data.count { !it.isRead }`.

---

## Layar & Alur Navigasi

```
[Login]
   ↓
[Daftar Tugas] ←——— Bottom Nav ———→ [Notifikasi]
      ↓                                   |
[Detail Tugas]           [Profil] ←———————
      ↓
[Sedang Memperbaiki]  ← GPS Foreground Service aktif di sini
```

| # | Layar | Route | Keterangan |
|---|---|---|---|
| 1 | Login | `login` | Form email + password. Simpan token ke DataStore. Kirim FCM token setelah login berhasil. |
| 2 | Daftar Tugas | `tasks` | List tugas aktif. Tab utama bottom navigation. Pull-to-refresh. |
| 3 | Detail Tugas | `tasks/{id}` | Info lengkap, timeline work logs, tombol "Mulai Memperbaiki" / "Selesai". |
| 4 | Sedang Memperbaiki | `tasks/{id}/working` | Layar aktif saat GPS berjalan. Tampilkan nama pelanggan, alamat, timer durasi, status live. Tombol "Tandai Selesai". |
| 5 | Notifikasi | `notifications` | Daftar notifikasi terbaru, indikator belum-baca. Tap → tandai dibaca. |
| 6 | Profil | `profile` | Nama + email user. Tombol logout. |

### Logika Tombol di Detail Tugas

- Status `ditugaskan` → tampilkan tombol **"Mulai Memperbaiki"** → kirim `in_progress` → start `LocationService` → navigasi ke `working`
- Status `sedang_memperbaiki` → tampilkan tombol **"Tandai Selesai"** di layar Working → kirim `done` → stop `LocationService` → kembali ke Daftar Tugas

## GPS Foreground Service

`LocationService` berjalan sepanjang teknisi berstatus `sedang_memperbaiki`:

- **Start**: dipanggil dari `WorkingViewModel` setelah status berhasil diubah ke `in_progress`
- **Interval**: kirim lokasi setiap **15 detik** via `FusedLocationProviderClient`
  (web admin polling peta tiap 10 detik — 15s adalah kompromi antara realtime & hemat baterai)
- **Payload**: POST ke `/api/location` dengan `report_id` tugas aktif
- **Stop**: dipanggil dari `WorkingViewModel` setelah status berhasil diubah ke `done`
- **Persistent notification** wajib ditampilkan selama service aktif (requirement Android 8+):
  contoh teks: "SkyNet — GPS aktif • Sedang memperbaiki"

Permission yang wajib dideklarasikan di `AndroidManifest.xml`:
```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE" />
<uses-permission android:name="android.permission.FOREGROUND_SERVICE_LOCATION" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

Runtime permission harus diminta sebelum memulai service: `ACCESS_FINE_LOCATION` (semua versi)
dan `POST_NOTIFICATIONS` (Android 13+).

## Firebase FCM

**Registrasi token:**
- `FirebaseMessaging.getInstance().token` untuk mendapatkan token perangkat
- Kirim token ke backend via `PUT /api/auth/fcm-token` **segera setelah login berhasil**
- Override `onNewToken` di `FirebaseMessagingService` → kirim ulang token ke backend jika sudah login

**Struktur pesan (baseline — yang dikirim backend saat ini):**

Backend (`NotificationObserver`) mengirim pesan **notification-only** (tanpa blok `data`):
```json
{ "notification": { "title": "Tugas Baru", "body": "Anda ditugaskan ke ..." } }
```

Perilaku `onMessageReceived` berbeda tergantung state app — ini bawaan FCM, tidak bisa diubah:

| State App | `onMessageReceived` dipanggil? | Yang terjadi |
|---|---|---|
| **Foreground** | ✅ Ya | App tampilkan notif sendiri via `NotificationManager` (baca `title`/`body`) |
| **Background / killed** | ❌ Tidak | Sistem tampilkan notif otomatis dari blok `notification` |

**Aturan implementasi (baseline):**
- Tap notif cukup membuka app di halaman awal (Daftar Tugas). **Tidak ada navigasi ke tugas
  spesifik** — ini disengaja (lihat catatan deep-link di bawah).
- Daftar notifikasi lengkap tetap bisa dilihat kapan saja di layar Notifikasi (`GET /api/notifications`).
- Yang wajib jalan: notif **muncul** saat ada tugas baru. Itu sudah memenuhi kebutuhan inti.

> ### (Opsional, fase akhir) Deep-link dari notifikasi
> Fitur "tap notif → buka langsung Detail Tugas" sengaja **ditunda** agar tidak menambah
> risiko bug sebelum fitur inti stabil. **Jangan dikerjakan** kecuali semua layar inti + GPS +
> alur status sudah berfungsi penuh. Kalau nanti mau diaktifkan, perlu DUA perubahan:
> 1. **Backend**: tambah `->withData(['report_id' => ..., 'type' => 'task_assigned'])` di
>    `NotificationObserver` (perlu desain dari mana `report_id` diambil — tabel `notifications`
>    saat ini hanya simpan `user_id, title, body, is_read`).
> 2. **Android**: baca `report_id` (FCM `data` selalu string → parse ke Int), navigasi ke
>    `tasks/{report_id}`. Foreground ambil dari `remoteMessage.data`; background ambil dari
>    `intent.extras` di launcher Activity.

## DataStore — UserPreferences

Simpan hanya dua key:

```kotlin
val TOKEN_KEY = stringPreferencesKey("auth_token")
val USER_KEY  = stringPreferencesKey("user_json")  // JSON dari object User login
```

- `token == null` → arahkan ke layar Login
- Baca sebagai `Flow` agar reaktif; collect di ViewModel
- Hapus keduanya saat logout

## OkHttp AuthInterceptor

Pasang interceptor untuk menambahkan token otomatis ke setiap request:

```kotlin
class AuthInterceptor @Inject constructor(
    private val userPreferences: UserPreferences
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = runBlocking { userPreferences.getToken() }
        val request = chain.request().newBuilder()
            .addHeader("Authorization", "Bearer $token")
            .addHeader("Accept", "application/json")
            .build()
        return chain.proceed(request)
    }
}
```

## Penanganan Error API

Semua response error dari backend selalu memiliki field `message`. Tampilkan ke user via
Snackbar. HTTP status yang relevan:

| Status | Arti | Tindakan |
|---|---|---|
| `401` | Token tidak valid / kadaluarsa | Hapus token, paksa ke layar Login |
| `403` | Aksi tidak diizinkan (mis. kirim lokasi saat laporan tidak aktif) | Tampilkan `message` |
| `422` (bisnis) | Transisi status tidak valid | Tampilkan `message` dari response |
| `422` (validasi) | Field salah/kosong — ada objek `errors` | Tampilkan pesan dari `errors`, atau `message` |
| `404` | Resource tidak ada **ATAU** tugas bukan milik teknisi ini | Tampilkan pesan error / kembali ke list |
| Network error | Tidak ada koneksi | Tampilkan "Periksa koneksi internet Anda" |

> Catatan: endpoint task & notifikasi memakai `findOrFail` yang di-scope ke `technician_id`
> pemilik. Meminta tugas milik teknisi lain membalas **404** (bukan 403) — perlakukan sebagai
> "tugas tidak ditemukan".

## Konvensi Kode

- Satu ViewModel per layar — jangan share ViewModel antar layar kecuali data benar-benar sama
- UI state dimodelkan sebagai `sealed class UiState<T>`:
  `Loading`, `Success(data: T)`, `Error(message: String)`
- Repository mengembalikan `Result<T>` — wrap semua network call dengan `try/catch`
- Network call di `Dispatchers.IO`, collect di ViewModel dengan `viewModelScope`
- Inject `Context` via `@ApplicationContext` dari Hilt — jangan pegang referensi Activity
- Naming: `*Screen.kt` (Composable root), `*ViewModel.kt`, `*Repository.kt`
- Hindari logic di Composable — semua logic di ViewModel
