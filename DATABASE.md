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

## Tabel: customers *(SoftDeletes)*
- `id` (bigint unsigned, PK, auto-increment)
- `customer_code` (varchar(20), Unique) - Nomor pelanggan unik auto-generate (format: `SKY-0001`)
- `name` (varchar)
- `phone` (varchar)
- `address` (varchar)
- `ip_address` (varchar, nullable) - IP perangkat pelanggan (opsional)
- `ip_pool_id` (bigint unsigned, FK → `ip_pools.id`, nullable, nullOnDelete) - Alokasi master data IP Pool
- `internet_package_id` (bigint unsigned, FK → `internet_packages.id`, nullable, nullOnDelete) - Paket internet pelanggan; NULL jika paket dihapus atau belum diisi
- `status` (enum: `aktif`|`isolir`|`berhenti`, default `aktif`)
- `latitude` (decimal(10,8), nullable) - Koordinat rumah pelanggan
- `longitude` (decimal(11,8), nullable)
- `installed_at` (date, nullable) - Tanggal instalasi
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)
- `deleted_at` (timestamp, nullable) - Soft delete: diisi saat pelanggan "dihapus" (data tetap di DB, bisa dipulihkan)

> **Catatan**: Hapus pelanggan = soft delete (kolom `deleted_at` diisi, baris tetap ada).
> Admin bisa memulihkan (restore) dari tampilan "Pelanggan Terhapus", atau menghapus
> permanen (force delete) — saat itu FK `nullOnDelete` di `damage_reports.customer_id`
> bekerja (customer_id → NULL, snapshot `customer_name`/`address` tetap utuh).

---

## Tabel: internet_packages
- `id` (bigint unsigned, PK, auto-increment)
- `name` (varchar, Unique) - Nama paket (mis. "20 Mbps")
- `speed_mbps` (unsigned integer) - Kecepatan nominal dalam Mbps
- `price` (unsigned integer, nullable) - Harga bulanan dalam Rupiah
- `description` (varchar, nullable) - Keterangan tambahan
- `created_at` (timestamp, default CURRENT)
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

## Tabel: ip_pools
- `id` (bigint unsigned, PK, auto-increment)
- `ip_address` (varchar(45), Unique) - Alamat IPv4
- `segment` (varchar(50), nullable) - Segmen jaringan / area (mis. "Cluster Cibitung", "192.168.10.0/24")
- `status` (enum: `tersedia`|`terpakai`|`reserved`, default `tersedia`) - Status alokasi IP
- `customer_id` (bigint unsigned, FK → `customers.id`, nullable, nullOnDelete) - Pelanggan pemegang IP (NULL jika tersedia/reserved)
- `notes` (varchar(255), nullable) - Catatan perangkat (mis. "Gateway MikroTik")
- `created_at` (timestamp, nullable)
- `updated_at` (timestamp, nullable)

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
- `customer_ip` (varchar, nullable) - Snapshot IP pelanggan saat laporan dibuat (audit trail riwayat koneksi)
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
- `type` (varchar, nullable) - Jenis notifikasi (mis. `task_assigned`, `task_in_progress`, `task_completed`)
- `related_id` (bigint unsigned, nullable) - ID entitas terkait (biasanya `damage_reports.id`) untuk fitur deep-link
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

## Tabel: activity_log (Audit Trail)
- `id` (bigint unsigned, PK, auto-increment)
- `log_name` (varchar, nullable, Index) - Kategori/modul entitas (mis. `laporan`, `pelanggan`, `pengguna`, `paket-internet`, `ip-pool`)
- `description` (text) - Deskripsi aktivitas audit (mis. "Data pelanggan diperbarui", "Menugaskan teknisi: Budi")
- `subject_type` (varchar, nullable) - Morph type data yang diubah (mis. `App\Models\Customer`, `App\Models\DamageReport`)
- `subject_id` (bigint unsigned, nullable) - Morph ID data yang diubah
- `event` (varchar, nullable) - Jenis tindakan (mis. `created`, `updated`, `deleted`)
- `causer_type` (varchar, nullable) - Morph type user pelaku perubahan (`App\Models\User`)
- `causer_id` (bigint unsigned, nullable) - Morph ID user pelaku perubahan (NULL jika dijalankan oleh sistem/tamu)
- `properties` (json, nullable) - Detail perubahan nilai atribut (JSON memuat perbandingan `old` vs `attributes`)
- `batch_uuid` (uuid, nullable) - Identifier grup batch log jika dieksekusi bersamaan
- `created_at` (timestamp, nullable) - Waktu pencatatan aktivitas
- `updated_at` (timestamp, nullable)
- **Index**:
  - `log_name`
  - `(subject_type, subject_id)` — morph index
  - `(causer_type, causer_id)` — morph index

> **Catatan Pembersihan Otomatis (Retention)**: Log dibersihkan secara otomatis via scheduler harian (`Schedule::command('activitylog:clean --force')->daily()` pada `routes/console.php`). Batas usia simpan default adalah 365 hari dan dapat disesuaikan melalui variabel `ACTIVITY_LOGGER_RETENTION_DAYS` pada file `.env`.

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
