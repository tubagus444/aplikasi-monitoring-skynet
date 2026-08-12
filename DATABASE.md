# Dokumentasi Skema Database

## Tabel: users
- `id` (bigint unsigned, PK, auto-increment)
- `name` (varchar)
- `email` (varchar, Unique)
- `email_verified_at` (timestamp, nullable)
- `password` (varchar)
- `role` (enum: `admin`|`teknisi`, default `teknisi`) - Menentukan hak akses pengguna
- `fcm_token` (varchar, nullable) - Token Firebase Cloud Messaging untuk push notification
- `remember_token` (varchar(100), nullable)
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

---

## Tabel: customers
- `id` (bigint unsigned, PK, auto-increment)
- `name` (varchar)
- `phone` (varchar)
- `address` (varchar)
- `ip_address` (varchar, nullable) - IP pelanggan sebagai pengganti kode pelanggan (identitas SkyNet)
- `subscription_package` (varchar, nullable) - Paket langganan
- `status` (enum: `aktif`|`isolir`|`berhenti`, default `aktif`)
- `latitude` (decimal(10,8), nullable) - Koordinat rumah pelanggan
- `longitude` (decimal(11,8), nullable)
- `installed_at` (date, nullable) - Tanggal instalasi
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

---

## Tabel: customer_photos
- `id` (bigint unsigned, PK, auto-increment)
- `customer_id` (bigint unsigned, FK → `customers.id`, cascadeOnDelete) - Foto milik pelanggan ini
- `uploaded_by` (bigint unsigned, FK → `users.id`, nullable, nullOnDelete) - User yang mengunggah; NULL jika user dihapus
- `path` (varchar) - Path file foto di disk `public`
- `caption` (varchar, nullable)
- `created_at` (timestamp, default CURRENT) - Append-only, tanpa updated_at

---

## Tabel: damage_types
- `id` (bigint unsigned, PK, auto-increment)
- `name` (varchar) - Nama jenis gangguan
- `description` (varchar, nullable)
- `created_at` (timestamp, default CURRENT)
- `updated_at` (timestamp, nullable)

---

## Tabel: damage_reports
- `id` (bigint unsigned, PK, auto-increment)
- `created_by` (bigint unsigned, FK → `users.id`, nullable, nullOnDelete) - Admin/user pembuat laporan; NULL jika user dihapus (riwayat tetap ada)
- `damage_type_id` (bigint unsigned, FK → `damage_types.id`, nullable, nullOnDelete) - Jenis gangguan; nullable untuk kategori non-pelanggan; NULL jika jenis dihapus
- `category` (enum: `pelanggan`|`jaringan`|`pemeliharaan`, default `pelanggan`) - Kategori laporan
- `customer_id` (bigint unsigned, FK → `customers.id`, nullable, nullOnDelete) - Pelanggan terkait (hanya untuk kategori `pelanggan`)
- `title` (varchar, nullable) - Judul laporan untuk kategori non-pelanggan
- `customer_name` (varchar, nullable) - Snapshot nama pelanggan saat laporan dibuat (tidak berubah walau data pelanggan diperbarui)
- `address` (varchar) - Snapshot alamat
- `notes` (text, nullable) - Catatan tambahan
- `status` (enum: `ditugaskan`|`sedang_memperbaiki`|`selesai`, default `ditugaskan`)
- `completed_at` (timestamp, nullable) - Waktu selesai sebenarnya; di-set saat transisi status ke `selesai`
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

---

## Tabel: task_assignments
- `id` (bigint unsigned, PK, auto-increment)
- `report_id` (bigint unsigned, FK → `damage_reports.id`, cascadeOnDelete) - Laporan yang ditugaskan
- `technician_id` (bigint unsigned, FK → `users.id`, cascadeOnDelete) - Teknisi yang menerima tugas
- `assigned_at` (timestamp, default CURRENT)

---

## Tabel: work_logs
- `id` (bigint unsigned, PK, auto-increment)
- `report_id` (bigint unsigned, FK → `damage_reports.id`, cascadeOnDelete) - Laporan terkait
- `technician_id` (bigint unsigned, FK → `users.id`, nullable, nullOnDelete) - Teknisi pelaksana; NULL jika user dihapus (riwayat tetap ada)
- `status` (enum: `ditugaskan`|`sedang_memperbaiki`|`selesai`) - Status saat log dicatat
- `description` (text, nullable)
- `logged_at` (timestamp, default CURRENT)

---

## Tabel: location_logs
- `id` (bigint unsigned, PK, auto-increment)
- `technician_id` (bigint unsigned, FK → `users.id`, cascadeOnDelete) - Teknisi yang dilacak
- `report_id` (bigint unsigned, FK → `damage_reports.id`, cascadeOnDelete) - Laporan konteks pelacakan
- `latitude` (decimal(10,8))
- `longitude` (decimal(11,8))
- `recorded_at` (timestamp, default CURRENT)
- **Index**: `(technician_id, report_id, recorded_at)` — komposit untuk optimasi query titik terbaru per teknisi+report

---

## Tabel: report_photos
- `id` (bigint unsigned, PK, auto-increment)
- `report_id` (bigint unsigned, FK → `damage_reports.id`, cascadeOnDelete) - Foto bukti pekerjaan untuk laporan ini
- `uploaded_by` (bigint unsigned, FK → `users.id`, nullable, nullOnDelete) - Teknisi pengunggah; NULL jika user dihapus
- `path` (varchar) - Path file foto di disk `public`
- `caption` (varchar, nullable)
- `created_at` (timestamp, default CURRENT) - Append-only, tanpa updated_at

---

## Tabel: notifications
- `id` (bigint unsigned, PK, auto-increment)
- `user_id` (bigint unsigned, FK → `users.id`, cascadeOnDelete) - Penerima notifikasi
- `title` (varchar)
- `body` (text)
- `is_read` (boolean, default `false`)
- `created_at` (timestamp, default CURRENT)

---

## Tabel: personal_access_tokens
- `id` (bigint unsigned, PK, auto-increment)
- `tokenable_type` (varchar) - Morph type (polimorfik, biasanya `App\Models\User`)
- `tokenable_id` (bigint unsigned) - Morph ID
- `name` (text) - Label token
- `token` (varchar(64), Unique) - Hash token
- `abilities` (text, nullable) - Daftar kemampuan token (JSON)
- `last_used_at` (timestamp, nullable)
- `expires_at` (timestamp, nullable, Index)
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)
- **Index**: `(tokenable_type, tokenable_id)` — morph index

---

## Tabel: cache (Laravel Framework)
- `key` (varchar, PK)
- `value` (mediumtext)
- `expiration` (bigint, Index)

## Tabel: cache_locks (Laravel Framework)
- `key` (varchar, PK)
- `owner` (varchar)
- `expiration` (bigint, Index)

---

## Tabel: jobs (Laravel Framework)
- `id` (bigint unsigned, PK, auto-increment)
- `queue` (varchar, Index)
- `payload` (longtext)
- `attempts` (smallint unsigned)
- `reserved_at` (int unsigned, nullable)
- `available_at` (int unsigned)
- `created_at` (int unsigned)

## Tabel: job_batches (Laravel Framework)
- `id` (varchar, PK)
- `name` (varchar)
- `total_jobs` (int)
- `pending_jobs` (int)
- `failed_jobs` (int)
- `failed_job_ids` (longtext)
- `options` (mediumtext, nullable)
- `cancelled_at` (int, nullable)
- `created_at` (int)
- `finished_at` (int, nullable)

## Tabel: failed_jobs (Laravel Framework)
- `id` (bigint unsigned, PK, auto-increment)
- `uuid` (varchar, Unique)
- `connection` (varchar)
- `queue` (varchar)
- `payload` (longtext)
- `exception` (longtext)
- `failed_at` (timestamp, default CURRENT)
- **Index**: `(connection, queue, failed_at)`
