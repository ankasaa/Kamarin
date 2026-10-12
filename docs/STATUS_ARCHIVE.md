# STATUS Kamarin - Arsip

Entri tugas yang sudah selesai, dipindahkan dari `docs/STATUS.md` (batch pertama 2026-10-11; batch kedua 2026-10-12: entri T-005, T-005b, dan review mandiri T-005). File hidup `docs/STATUS.md` hanya memuat ringkasan satu baris per tugas selesai plus entri tugas terbaru.

---

## Pertanyaan terbuka dari agent

### 2026-10-11, T-005 — Satu keputusan skema (SELESAI - sudah dijawab user)

1. **`booking_nights.room_id` bisa menyimpang dari `booking_items.room_id` (SELESAI 2026-10-11).** Fakta: `booking_nights.room_id` dan `booking_items.room_id` sama-sama foreign key lepas ke `rooms(id)`; tidak ada penghubung di database yang memaksa keduanya bernilai sama untuk satu rantai malam-item. Tiga opsi pencegahan di level database:
   - **Opsi A — foreign key gabungan:** tambah unique `(id, room_id)` di `booking_items`, lalu ganti FK `booking_nights.booking_item_id` menjadi FK gabungan `(booking_item_id, room_id) REFERENCES booking_items(id, room_id)`. Malam dengan room_id yang tidak sama dengan item induknya langsung ditolak database; biaya satu unique index tambahan + perubahan definisi FK. Paling murah dan tetap deklaratif.
   - **Opsi B — trigger:** bekerja, tapi logika pindah ke luar deklarasi skema, sulit terlihat saat membaca ERD/migration, dan perlu pemeliharaan sendiri.
   - **Opsi C — CHECK dengan subquery:** tidak bisa di PostgreSQL (CHECK tidak boleh membaca tabel lain). Dicatat agar keputusan tidak mengulang jalur ini.

   **Keputusan user: opsi A**, dengan `ON UPDATE CASCADE` dan `ON DELETE NO ACTION`, dikerjakan langsung di **T-005b** (migration `2026_10_11_000021`, commit `bd658bd`). Sudah diimplementasi dan dibuktikan tes: malam yang room_id-nya menyimpang ditolak, pindah kamar memindahkan malam lewat cascade, dan cascade yang mendarat di kamar terisi ditolak unique parsial. Rincian di entri log T-005, bagian "T-005b".

### 2026-10-12, Review T-005 — Smart App Control memblokir driver PHP (SELESAI - sudah dijawab user)

1. **Fakta.** Sejak 2026-10-12 sekitar 09:00, Smart App Control (SAC) Windows berada dalam mode **enforce** (`HKLM:\SYSTEM\CurrentControlSet\Control\CI\Policy\VerifiedAndReputablePolicyState = 1`) dan memblokir `php_pdo_pgsql.dll` (juga `php_pdo_sqlite.dll`, `php_sqlite3.dll`, `php_zip.dll`) lewat event Code Integrity 3033/3077. Akibatnya semua `php artisan` / Pest yang butuh PostgreSQL gagal dengan `could not find driver`. Modul native Vite di proyek ini (`node_modules/@voidzero-dev/vite-plus-win32-x64-msvc/vite-plus.win32-x64-msvc.node`) juga kena blokir yang sama — build frontend ikut terdampak. DLL tidak berubah sejak 2026-10-06 dan pada 2026-10-11 semua perintah ini masih lulus; perubahannya ada di kebijakan OS, bukan di proyek. Sumber: Microsoft Learn, "Smart App Control overview" (https://learn.microsoft.com/en-us/windows/apps/develop/smart-app-control/overview) — "In enforcement mode ... Apps cannot be run unless they are recognized by Microsoft's app intelligence services, or they are signed with a trusted certificate"; "unknown, unsigned code are blocked by default".
2. **Opsi (keputusan keamanan mesin — agent tidak mengambil inisiatif apa pun):**
   - **A — Nonaktifkan SAC** lewat Settings > Windows Security > App and Browser Control (pilihan "Off"). Catatan Microsoft: mode diperiksa di halaman itu; setelah mati, keputusan menyalakan lagi sepenuhnya di tangan pengguna. Setelah nonaktif: jalankan ulang fokus (4) + seluruh suite, lalu entri review diperbarui.
   - **B — Pertahankan SAC.** Verifikasi berbasis PHP di mesin ini tetap terblokir. Fokus (4) memakai bukti historis 2026-10-11 (siklus identik pada commit sama, EXIT=0, tercatat di entri T-005b) dan rencana dijalankan ulang di mesin/CI lain.
   - **C — PHP sementara di container Docker.** Perubahan lingkungan (keputusan Opsi D: PHP lokal) → butuh persetujuan eksplisit dan verifikasi kompatibilitas lebih dulu (AGENTS aturan 11); agent tidak melakukannya tanpa persetujuan.
3. **Jika mesin ini dikelola organisasi**, kebijakan mungkin tidak boleh diubah — konfirmasi dulu sebelum memilih opsi A.

**Resolusi 2026-10-12: user memilih menonaktifkan SAC sendiri** lewat Pengaturan Windows (state registry menjadi `0`; `extension_loaded('pdo_pgsql')` = `true`) dan meminta agent menjalankan ulang. Jalanan ulang lulus semua: fokus (4) `ROLLBACK_EXIT=0` + `MIGRATE_EXIT=0`, `SchemaConformityTest` 8/8, seluruh suite 57/57. Blokir Vite ikut lepas — build frontend layak diverifikasi user saat dipakai berikutnya.

### 2026-10-11, T-004 — Satu pertanyaan skema (SELESAI - sudah dijawab user)

1. **`housekeeping_statuses.notes` (SELESAI 2026-10-11):** Pertanyaan awal: kolom ini jadi NOT NULL karena di ERD tidak ditandai nullable. **Keputusan user: dibuat nullable.** ERD v2.1 direvisi user pada hari yang sama (penanda nullable dirapikan untuk 9 kolom master, `properties.pending_expiry_hours` default menjadi 3) dan migration T-004 mengikuti. Tes kesesuaian skema tidak dilonggarkan — justru dikencangkan (default dibaca dari ERD; unique `room_id` 1:1 ikut diperiksa, ketika itu membuka bug index yang tidak pernah terbentuk). Rincian di entri log T-004, bagian "Amandemen 2026-10-11".

### 2026-10-11, T-002 — Tiga pertanyaan lanjutan (SELESAI - sudah dijawab user)

1. **`laravel/chisel` dihapus (SELESAI 2026-10-11):** Sesuai persetujuan dan keputusan user, telah dijalankan `composer remove laravel/chisel`. Dependensi bersih, seluruh tes dan build tetap lulus. Paket `laravel/sail` tetap dipertahankan di dev dependencies.
2. **`npm run types:check` (DIABAIKAN sampai T-015):** Keputusan user: 3 error pre-existing di file generated wayfinder diabaikan sementara sampai tugas CI (T-015).
3. **Format dokumen `docs/` (SELESAI):** Keputusan user: dokumen di folder `docs/` tidak diformat otomatis oleh `npm run check:fix` agar integritas format dokumen sumber tetap terjaga (sesuai aturan di `AGENTS.md`).

### 2026-10-11, T-003 — Dua catatan lanjutan (tidak memblokir)

1. **CI GitHub — SELESAI di T-002 (2026-10-11).** Sesuai instruksi user, `.github/workflows/tests.yml` diubah menjadi `workflow_dispatch` saja (tanpa menambah service baru); push/pull_request trigger dibuang. Catatan jujur: workflow manual tetap akan gagal di `composer setup`/`types:check` karena tidak ada PostgreSQL di runner dan ada error TypeScript pre-existing (lihat pertanyaan T-002 nomor 2).
2. **Isi database development.** Saat bukti pengaman T-003 dijalankan, `kamarin` holds 10 tabel framework sisa verifikasi T-001 (users=0, sessions=2) — bukan kosong murni, seperti yang diasumsikan instruksi. Tidak berbahaya (guard membuktikan tabel tidak tersentuh). Kalau ingin benar-benar kosong: `php artisan migrate:rollback` atau `docker compose down -v` (menghapus kedua database dan volume). Rekomendasi agent: biarkan.

### 2026-10-09, T-001 — Hasil pemeriksaan prasyarat dan keputusan yang dibutuhkan

Dokumen yang dirujuk: `docs/arsitektur_sistem.md` bagian 5 dan 6, Laravel 13 Installation
(https://laravel.com/docs/13.x/installation) dan Sail (https://laravel.com/docs/13.x/sail).

Hasil pemeriksaan (tanpa instalasi apa pun):

| Prasyarat | Hasil |
| --- | --- |
| Sistem operasi | Windows 11 Home Single Language 10.0.26200 x64 (PowerShell 5.1) |
| Docker | Docker Desktop terpasang (user-level). Awalnya **tidak berjalan**; agent menjalankan manual → client 29.8.1, daemon naik, Compose v5.5.1 |
| WSL2 | Tersedia: Ubuntu 26.04.1, WSL2. Integrasi Docker berfungsi (`docker` di WSL terhubung ke daemon 29.8.1) |
| PHP | 8.3.35 (Laragon, Windows). Memenuhi `laravel/framework` 13.x (`php ^8.3`). Tidak ada PHP di WSL |
| Composer | 2.10.3 (Windows). Tidak ada di WSL |
| Node / npm | v24.21.0 / 11.19.0 (Windows). Tidak ada di WSL. Sail default Node 24 |
| Laravel installer | **Belum terpasang** (`laravel` tidak dikenal) |
| Lokasi repo | `C:\Users\andikaa\Desktop\Kamarin` = drive Windows (NTFS), bukan filesystem WSL. Di WSL terbaca sebagai `/mnt/c/Users/andikaa/Desktop/Kamarin` |

Perintah resmi dari dokumentasi Laravel 13: prasyarat Installation = PHP, Composer, dan Laravel
installer (plus Node/NPM), aplikasi dibuat dengan `laravel new example-app`; Sail dipasang dengan
`composer require laravel/sail --dev`, dikonfigurasi `php artisan sail:install`, dijalankan
`./vendor/bin/sail up`, diakses lewat `http://localhost`. Sail resmi didukung di Windows **lewat
WSL2**. Sail default: PHP 8.5, Node 24.

**Pertanyaan 1 — Cara membuat proyek (Laravel installer belum terpasang, langkah 1 melarang instalasi):**

- Opsi A (rekomendasi): izinkan instalasi `composer global require laravel/installer` lalu `laravel new` di folder sementara, pindahkan isinya ke root repo. Sesuai dokumentasi resmi Installation. Catatan: direktori bin Composer global (`C:\Users\andikaa\AppData\Roaming\Composer\vendor\bin`) belum ada di PATH, jadi akan dipanggil lewat path penuh.
- Opsi B: tanpa installer, pakai `composer create-project laravel/laravel <folder-sementara>`. Ini perintah Composer umum untuk skeleton resmi `laravel/laravel`, tetapi **tidak lagi tercantum** di halaman Installation Laravel 13, jadi kurang "terverifikasi dari dokumentasi resmi".

**Pertanyaan 2 — Repo di drive Windows sementara Sail hanya bisa dijalankan dari WSL:**

Skrip `sail` berbasis shell sh, jadi perintah Sail dijalankan dari WSL (misalnya
`wsl -e sh -c "cd /mnt/c/Users/andikaa/Desktop/Kamarin && ./vendor/bin/sail up -d"`), bukan dari
PowerShell. Konsekuensi bekerja di `/mnt/c`:

- Opsi A (rekomendasi): tetap di lokasi repo sekarang, jalankan Sail dari WSL. Tanpa perpindahan file, `docs/` dan `AGENTS.md` aman. Cocok untuk T-001 (setup backend). Performa I/O lebih lambat dari filesystem WSL, dan **file-watching Vite (HMR) berisiko tidak andal** saat T-002 karena perubahan file Windows tidak selalu terdeteksi di dalam kontainer (solusinya bisa polling, atau jalankan dev server dari Windows).
- Opsi B: pindahkan seluruh repo ke filesystem WSL (misalnya `~/kamarin`). I/O dan HMR paling cepat, tetapi lokasi repo berubah dari yang sudah ditetapkan (`C:\Users\andikaa\Desktop\Kamarin`) dan akses editor Windows/git harus menyesuaikan.
- Opsi C: tidak memakai Sail, memakai PHP lokal + PostgreSQL lokal. Bertentangan dengan `arsitektur_sistem.md` bagian 5 (Sail untuk development) dan butuh instalasi manual.

**Pertanyaan 3 — Docker Desktop tidak auto-start:** tiap sesi mesin harus menjalankan Docker Desktop dulu. Boleh disetel autostart, atau dibiarkan manual (agent bisa menjalankan sendiri saat tugas mulai).

**Pertanyaan 4 — Timezone `.env.example`:** tugas menulis `[zona waktu]` sebagai placeholder. Usulan: `Asia/Jakarta`. Konfirmasi.

Rekomendasi agent: **Pertanyaan 1 = A, Pertanyaan 2 = A, Pertanyaan 3 = biarkan manual (agent menjalankan), Pertanyaan 4 = Asia/Jakarta.** Menunggu jawaban sebelum melanjutkan ke Langkah 2.

**Status: sudah dijawab user (2026-10-09).** Keputusan: Pertanyaan 1 = Opsi A (pasang `laravel/installer`, buat di folder sementara, pindah ke root). Pertanyaan 2 = **Opsi D baru** (bukan A): PHP, Composer, Node lokal di Windows; hanya PostgreSQL di Docker (compose, database dev dan tes terpisah); repo tetap di lokasi sekarang; syarat `pdo_pgsql` aktif. Pertanyaan 3 = manual, agent boleh menjalankan. Pertanyaan 4 = **Asia/Makassar (fix)**, menggantikan usulan Asia/Jakarta. AGENTS.md diperbarui user (bagian Lingkungan pengembangan, aturan 13) dan `docs/arsitektur_sistem.md` bagian 5-6 direvisi sesuai keputusan ini. Catatan: baris zona waktu di AGENTS.md masih "Asia/Jakarta (sementara)" — perlu diperbarui user ke Asia/Makassar.

---

## Log tugas (arsip)

### 2026-10-12, T-006 — Urutan T-007 (seeder) vs T-008 (AvailabilityService) (SELESAI - sudah dijawab user)

**Keputusan user 2026-10-12: Opsi B — T-008 (AvailabilityService + tes AV) dikerjakan lebih dulu, T-007 (seeder) ditunda** sesuai target laporan "setelah T-008". Konsekuensi teknis: cabang `task/T-008-availability-service` dibuat menumpuk dari tip `task/T-006-models` karena AvailabilityService membutuhkan enum, model, dan factory hasil T-006 yang belum di-merge; setelah T-006 di-merge user, T-008 tinggal melanjutkan linier di atasnya. Alasan Opsi B: T-008 adalah inti verifikasi BR-01 dan sudah dijanjikan untuk review lebih dulu; seeder hanya menyentuh `database/seeders/` (data dummy), tidak mengubah skema atau service, sehingga aman dikerjakan setelahnya.

---

### T-006 Enum, model, relasi, dan factory (2026-10-12)

- Cabang: `task/T-006-models`, dari `main` `52a5d6c` (hasil merge T-005+T-005b oleh user). **`main` tidak disentuh** (instruksi user); commit pertama cabang = arsip `docs/STATUS.md` (`343e842 docs: archive completed task entries`), terpisah dari commit T-006.
- Status: selesai
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (aturan 2 untuk nilai enum, aturan 3 untuk logika di service), baris T-006 di `docs/tasks/BACKLOG_FASE4_5.md`, `docs/erd_v2_1.mermaid` (dibaca ulang per blok, 19 tabel), BR-01 (semantik "malam aktif" untuk `holdsNights()`), BR-10 (definisi "kamar terjual" untuk `countsAsSold()`).
- Yang dikerjakan:
  - **7 enum backed string** di `app/Enums/`: `BookingStatus` (7 nilai), `PaymentOption` (3), `PaymentType` (3), `PaymentStatus` (4), `PaymentMethod` (4), `RoomStatus` (3), `HousekeepingStatus` (3) — nilai persis daftar aturan 2, dijaga tes. `BookingStatus` menambah dua metode murni: `holdsNights()` (true: pending, confirmed, checked_in, checked_out — BR-01: malam aktif memblokir kamar) dan `countsAsSold()` (true: confirmed, checked_in, checked_out saja — BR-10: pending tidak dihitung terjual). **Tanpa matriks transisi** (itu T-011).
  - **18 model baru + `Role` dan `User` dilengkapi relasi** — 19 tabel ERD semuanya punya model. Relasi mengikuti FK di ERD: `User::role/property`, `Property::users/roomTypes/rooms/rates`, `RoomType::property/rooms/roomTypeAmenities/amenities(rakit)/rates`, `Room::property/roomType/housekeepingStatus(hhasOne)/roomBlocks/bookingItems/bookingNights`, `HousekeepingStatus::room/updatedBy`, `RoomBlock::room/createdBy`, `Booking::property/guest/createdBy/bookingSource/bookingItems/bookingCharges/payments/bookingStatusLogs/bookingChangeLogs` (+ SoftDeletes), `BookingItem::booking/room/bookingNights`, `BookingNight::bookingItem/room/rate`, `BookingCharge::booking`, `Payment::booking/recordedBy`, `BookingStatusLog::booking/changedBy`, `BookingChangeLog::booking/changedBy`, `Rate::property/roomType/bookingNights`, `Guest::bookings` (+ SoftDeletes), `BookingSource::bookings`, `Amenity::roomTypes`, `RoomTypeAmenity::roomType/amenity`.
  - **Isi model hanya struktur**: `#[Fillable(...)]` eksplisit per kolom (atribut `Illuminate\Database\Eloquent\Attributes\Fillable` gaya Laravel 13 yang sudah dipakai `User`/`Role` di repo ini — memenuhi "eksplisit, tanpa `$guarded = []`"), docblock `@property`, relasi, dan `casts()`. Tanpa logika bisnis.
  - **Cast**: 6 kolom uang (`bookings.total_amount`, `booking_items.subtotal`, `booking_nights.price`, `booking_charges.amount`, `payments.amount`, `rates.price`) → `integer` (numeric(12,0) kembali sebagai string dari PostgreSQL); semua kolom date → `'date:Y-m-d'` eksplisit (bookings check-in/out, booking_nights.stay_date, payments.payment_date, rates start/end, room_blocks start/end); `rates.days_of_week` → `array` (jsonb ISO 1-7); kolom enum → enum yang sesuai (`bookings.status/payment_option`, `rooms.status`, `housekeeping_statuses.status`, `payments.type/method/status`, dan `booking_status_logs.old_status/new_status`); kolom time kebijakan (`properties.check_in_time`, `check_out_time`, `no_show_cutoff_time`) → `string`.
  - **19 factory** (17 baru; `RoleFactory`/`UserFactory` sudah ada dan tidak perlu diubah). Semua factory memakai FK yang menunjuk tabel yang benar; `RoomFactory` dan `RateFactory` membuat room_type/tipe di property yang sama lewat closure atribut; `BookingNightFactory` menurunkan `room_id` dari item induknya (syarat FK gabungan). `BookingFactory`: 7 state per status (`pending()`, `confirmed()`, `checkedIn()`, `checkedOut()`, `cancelled()`, `expired()`, `noShow()`) + **helper `createWithNights(Room $room, int $pricePerNight = 300000, array $attributes = [])`** yang membuat booking + 1 item + malam-malam yang konsisten: room_id item == room_id setiap malam, jumlah malam = `diffInDays(check_in, check_out)` (hari check-out bukan malam), `is_active` = `$booking->status->holdsNights()`, subtotal item dan total booking = Σ harga malam.
- Langkah tes: tes ditulis bersamaan dengan implementasi (fase merah terpisah tidak mungkin — model/factory belum ada saat tes dibuat; dicatat jujur). Run pertama suite penuh:

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":117,"passed":117,"assertions":1004,"duration_ms":13674,"risky":19}
  SUITE_EXIT=0
  ```

  19 tes "risky" = dataset 19 factory yang hanya memanggil `create()` tanpa assertion (PHPUnit menandai tes tanpa assert). Diperbaiki dengan `expect($model->exists)->toBeTrue()` — bukan melonggarkan, justru menambah assertion. Formatter lalu dijalankan:

  ```
  PS> composer run lint
  {"tool":"pint","result":"fixed","files":[11 file: app/Models/Amenity, BookingSource, HousekeepingStatus, Property, Room, RoomType, RoomTypeAmenity, User (fully_qualified_strict_types / ordered_imports), database/factories/HousekeepingStatusFactory (ordered_imports), tests/Feature/Models/FactoryValidityTest + ModelRelationsTest (fully_qualified_strict_types, ordered_imports)]}
  PINT_EXIT=0
  PS> composer run lint:check
  {"tool":"pint","result":"passed"}
  LINTCHECK_EXIT=0
  ```

  Seluruh suite ulang setelah formatter + perbaikan assertion:

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":117,"passed":117,"assertions":1023,"duration_ms":13442}
  SUITE_EXIT=0
  ```

  Fokus tes baru saja (60 tes):

  ```
  PS> vendor\bin\pest tests\Feature\Models tests\Unit\Enums
  {"tool":"pest","result":"passed","tests":60,"passed":60,"assertions":154,"duration_ms":6599}
  FOCUS_EXIT=0
  ```

- Tes wajib tugas terpenuhi (dibuktikan assertion, bukan klaim):
  1. **Serialisasi tanggal di Asia/Makassar:** tes guard `config('app.timezone')` + `now()->timezoneName` = `Asia/Makassar`, lalu `booking_nights.stay_date` 2026-10-12 dan `bookings.check_in_date`/`check_out_date` 2026-10-12/13 wajib muncul di JSON persis `"2026-10-12"`/`"2026-10-13"` dan **tidak** memuat `2026-10-11` (pergeseran sehari = bug klasik midnight UTC+8).
  2. **Tiap relasi memuat data benar:** 7 tes di `ModelRelationsTest` membangun graf nyata lewat factory lalu memeriksa tiap relasi (termasuk relasi rakit amenity, `hasOne` housekeeping, relasi FK gabungan malam-item, dan kolom `*_user_id` yang dinamai `createdBy`/`updatedBy`/`recordedBy`/`changedBy`).
  3. **Tiap factory lolos semua CHECK dan FK PostgreSQL:** dataset 19 factory — insert sukses = FK terpenuhi dan CHECK (guest_count, uang non-negatif, end_date > start_date, dll.) lulus. Termasuk pembuktian `booking_charges.amount` negatif (diskon) tetap diizinkan.
  4. **Helper `createWithNights`:** malam 2026-11-10..12 (3 malam) untuk check-in 10 check-out 13, room_id seragam, `is_active` true untuk pending dan false untuk cancelled/expired/no_show, subtotal = total = 3 × 250.000.
  5. **Soft delete:** `Booking` dan `Guest` hilang dari query normal tapi ada via `withTrashed()`; `Rate` (tanpa trait) terhapus permanen — membuktikan trait hanya dipasang di dua model.
  6. **Zona waktu kompatibilitas:** `freezeNow('2026-06-15')` + cast `date:Y-m-d` tetap menghasilkan "2026-06-15" (helper T-003 tidak rusak).
- Tes: 117 lulus, 0 gagal, 0 risky (1023 assert, Pest 4.x, PostgreSQL 18). 60 = tes baru (16 enum + 3 serialisasi + 7 relasi + 34 factory/cast/softdelete); 57 lama tidak tersentuh dan tetap lulus.
- Formatter: Pint `lint` memperbaiki 11 file (hanya import/FQCN), `lint:check` lulus.
- File kunci: `app/Enums/BookingStatus.php`, `app/Enums/PaymentOption.php`, `app/Enums/PaymentType.php`, `app/Enums/PaymentStatus.php`, `app/Enums/PaymentMethod.php`, `app/Enums/RoomStatus.php`, `app/Enums/HousekeepingStatus.php`, `app/Models/` (19 file), `database/factories/` (19 file), `tests/Unit/Enums/BookingStatusTest.php`, `tests/Feature/Models/DateSerializationTest.php`, `tests/Feature/Models/ModelRelationsTest.php`, `tests/Feature/Models/FactoryValidityTest.php`.
- Penyimpangan dari dokumen sumber: tidak ada terhadap ERD/aturan 2/backlog. Dua catatan keputusan: (1) `$fillable` diwujudkan sebagai atribut `#[Fillable]` (pola bawaan starter kit Laravel 13 di repo ini, sudah dipakai `User`/`Role`) bukan properti `$fillable` — tetap eksplisit per kolom, tanpa `$guarded = []`; (2) `holdsNights()`/`countsAsSold()` ditempatkan di enum (bukan `app/Services`) karena instruksi eksplisit user memintanya di enum; tetap metode murni tanpa state, tanpa akses database.
- Perintah kunci dan sumber yang dipakai:
  - Nilai enum dan semantik: `AGENTS.md` aturan 2, ERD v2.1 (daftar nilai di penanda kolom), BR-01 ("Malam aktif", "booking baru untuk kamar dan malam yang sama harus ditolak"), BR-10 ("Kamar terjual: Jumlah malam aktif milik booking confirmed, checked_in, checked_out ... Pending tidak dihitung")
  - Closure atribut factory (`room_type_id`, `room_id` menurunkan nilai dari FK yang sudah terisi sebelumnya dalam array definition) — perilaku `Factory::expandAttributes` Laravel, **diverifikasi lewat tes** (factory insert lolos FK gabungan `booking_nights(booking_item_id, room_id)` yang menolak penyimpangan apa pun)
  - Cast `date:Y-m-d` — Laravel 13 casting (laravel.com/docs/13.x/eloquent-mutators); perilaku serialisasi tanpa pergeseran diverifikasi langsung oleh `DateSerializationTest` di zona Asia/Makassar
  - Cast enum backed string (`Enum::class` di `casts()`) — Laravel enum casting (laravel.com/docs/13.x/eloquent-mutators bagian "Enum Casting"); semua kolom enum diuji `toBeInstanceOf`
- Penjelasan untuk sidang (bagaimana dan kenapa): T-006 memindahkan seluruh "kata" domain aplikasi ke tempat yang terpusat: tujuh enum backed string memastikan status booking, opsi pembayaran, dan status kamar/kebersihan hanya bisa bernilai persis seperti yang diizinkan aturan 2 — nilai lain mustahil ditulis karena PHP menolaknya, dan tes menjaga agar daftar nilai tidak bergeser diam-diam dari dokumen. Dua metode murni di `BookingStatus` (`holdsNights()` dan `countsAsSold()`) menempatkan dua pertanyaan bisnis yang paling sering ditanya di satu tempat: status apa yang masih menginap kamar (BR-01) dan status apa yang dihitung terjual (BR-10), tanpa menyentuh logika transisi yang menjadi T-011. Cast `date:Y-m-d` eksplisit adalah pertahanan terhadap bug tanggal paling mahal di aplikasi menginap: tanggal 2026-10-12 yang diserialisasi sebagai instan tengah malam UTC+8 akan tampil sebagai 2026-10-11 di JSON — tes membuktikan hal itu tidak terjadi, lengkap dengan guard bahwa aplikasi benar-benar berjalan di Asia/Makassar. Factory `createWithNights()` bukan sekadar pemercepat tes: ia membangun graf booking-item-malam yang konsisten menurut aturan (room_id sama, jumlah malam = selisih tanggal, `is_active` mengikuti `holdsNights()`), sehingga setiap tes di tugas berikutnya (AvailabilityService, T-008) bisa langsung memakai data yang sah secara database — termasuk FK gabungan yang menolak penyimpangan room_id. Seluruh isi model dibatasi hanya struktur (fillable, cast, relasi); tidak ada satu pun keputusan bisnis di model, sesuai aturan 3 bahwa logika bisnis hidup di `app/Services`.
- Reviewer: agent Reviewer terpisah (2026-10-12) — **LULUS**, tanpa ketidaksesuaian kolom/nilai terhadap ERD, tanpa logika bisnis di model. Dua temuan minor diperbaiki di cabang ini (commit `6112b76`): `Property::bookings()` yang hilang (DoD "relasi sesuai ERD") dan `User` `#[Fillable]` yang tidak memuat `role_id`/`property_id`/`is_active` (risiko `User::create()` diam-diam menjatuhkan FK). Reviewer juga mencatat `booking_code` factory belum memenuhi BR-14 — diakui sebagai tugas T-010 (BookingService), bukan cacat T-006.

### T-008 AvailabilityService dan tes AV-01..AV-13 (2026-10-12)

- Cabang: `task/T-008-availability-service`, **menumpuk dari tip `task/T-006-models` (`b39c8d1`)** — bukan dari `main` — karena AvailabilityService membutuhkan enum, model, dan factory hasil T-006 yang belum di-merge. Keputusan mencatatkan diri di "Pertanyaan terbuka" (urutan T-007 vs T-008, dijawab user). `main` tidak disentuh.
- Status: selesai, **menunggu review user** (backlog menandai T-008 wajib review).
- Rujukan yang dibaca sebelum mulai: baris T-008 di `docs/tasks/BACKLOG_FASE4_5.md` (DoD: "Semua tes AV lulus. Turnover hari yang sama diizinkan, blokir dan booking aktif menghalangi, housekeeping tidak memengaruhi tanggal mendatang"), BR-01 (poin 1 sampai 6), BR-07 (poin 1-2, 5), BR-11 tidak ikut (harga = tugas lain), kasus uji AV-01..AV-13 di `docs/rencana_pengujian.md` bagian 4.1, US-13 (anti double booking), US-08 (blokir tanggal), US-05 (status kamar), tabel ERD terkait (`rooms`, `room_blocks`, `booking_nights`, `housekeeping_statuses`).
- **Keputusan interpretasi (dijelaskan, bukan ditebak):**
  1. **Rentang `room_blocks` = `[start_date, end_date)`** (blokir malam `start` sampai `end-1`), gaya check-out seperti penginapan lain. Dasar: CHECK database `room_blocks_end_after_start` melarang `end_date = start_date`, jadi bentuk "blokir satu hari" dengan rentang inklusif mustahil; konvensi ini juga sejajar BR-01.3 (turnover hari sama). Jika user menghendaki inklusif, CHECK harus diubah lebih dulu — dicatat agar keputusan ini terlihat saat review.
  2. **"Masa depan" di BR-07.2/AV-13 = `stay_date >= today`** — malam hari ini dihitung (kamar masih terisi tamu malam ini), bukan besok.
  3. **Nama role `owner`/`manager`** dibaca dari `users.role->name` lewat konstanta privat; nama canonical role ditetapkan seeder T-007 (belum ada di repo). Bukan hardcode kebijakan properti (aturan 6 tidak dilanggar — ini identitas role, bukan angka kebijakan).
- Langkah 1 — **tes ditulis lebih dulu** dari kasus uji (`tests/Feature/Services/AvailabilityServiceTest.php`, 17 tes: AV-01..AV-13 termasuk dataset AV-06 tiga status dan AV-10 dua bentuk tanggal). Bukti merah dijalankan sebelum implementasi:

  ```
  PS> vendor\bin\pest tests\Feature\Services\AvailabilityServiceTest.php
  {"tool":"pest","result":"failed","tests":17,"passed":0,"assertions":0,"duration_ms":3785,"errors":17,
   "error_details":["...Target class [App\\Services\\AvailabilityService] does not exist... (17 error_details dengan pesan sama)"]}
  RED_EXIT=2
  ```

- Yang dikerjakan — dua file:
  - `app/Exceptions/AvailabilityException.php` — exception domain; pesan selalu menyebut kamar dan aturan yang menolak.
  - `app/Services/AvailabilityService.php` — **lapis 1 dari 3 lapis anti double booking (BR-01.5)**; lapis 2 (kunci baris + cek ulang di transaksi) = BookingService T-009, lapis 3 (unique parsial) = database. Empat metode publik:
    - `availableRooms(property, checkIn, checkOut, ?guestCount, ?excludeBookingId)`: kamar properti berstatus `active` tanpa malam aktif dan tanpa blokir yang menabrak rentang; opsional filter kapasitas kamar; **`housekeeping_statuses` sengaja tidak ikut query apa pun** (BR-07.5). Urutan `id` supaya deterministik.
    - `assertValidStay(property, checkIn, checkOut, guestCount, rooms, ?user, ?reason, ?excludeBookingId)`: (a) `check_out_date > check_in_date` (AV-10 — lapis validasi sebelum CHECK database), (b) check-in lampau hanya owner/manager dengan alasan tidak kosong (AV-11/BR-01.4), (c) minimal satu kamar, semua kamar milik properti dan `active` (AV-05), (d) `guest_count <=` total kapasitas tipe kamar terpilih (AV-09/BR-01.2), (e) setiap kamar bebas malam aktif (AV-02, AV-06, AV-07) dan bebas blokir (AV-04).
    - `assertRoomCanBeBlocked(room, start, end)`: menolak blokir yang menabrak malam aktif (AV-12/BR-01.6 — index parsial hanya menjaga booking vs booking); malam tidak aktif tidak menghalangi.
    - `assertRoomStatusChangeAllowed(room, newStatus)`: `active` selalu boleh; `maintenance`/`inactive` ditolak selama kamar punya malam aktif `stay_date >= today` (AV-13/BR-07.2).
  - Pertanyaan rentang malam: `stay_date >= check_in` dan `< check_out` (hari check-out bukan malam, aturan 7) dipakai konsisten di semua query; `excludeBookingId` disiapkan untuk tugas CH berikutnya (booking yang diedit tidak boleh menghalangi dirinya sendiri) — belum dipakai tes AV, dicatat jujur.
- Iterasi merah → hijau menemukan 3 hal, semuanya diperbaiki tanpa melonggarkan tes:
  1. Tipe kembalian helper salah: `pluck()` pada query builder menghasilkan `Illuminate\Support\Collection`, bukan `Eloquent\Collection` — 11 tes error dengan pesan return type. Diperbaiki dengan tipe yang benar (alias `SupportCollection`), bukan membuang tipe.
  2. Setelah hijau, 11 tes ditandai **risky** oleh Pest 4: `->throwsNoExceptions()` pada Pest versi ini menandai tes sebagai tidak melakukan assertion. Anotasi dihapus dari 9 deklarasi tes — setiap tes tetap punya assertion `expect()` miliknya sendiri ( AV-12b ditambahi `expect(...)->not->toThrow(...)`), jadi tidak ada assertion yang dilepas.
  3. Pint menemukan satu import tak terpakai (`Property`) sisa refactor — dibersihkan.
- Hasil akhir (dijalankan dua kali: saat hijau pertama dan sesudah formatter, EXIT=0 keduanya):

  ```
  PS> vendor\bin\pest tests\Feature\Services\AvailabilityServiceTest.php
  {"tool":"pest","result":"passed","tests":17,"passed":17,"assertions":45,"duration_ms":3405}
  FOCUS_EXIT=0
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":134,"passed":134,"assertions":1068,"duration_ms":13041}
  SUITE_EXIT=0
  PS> composer run lint
  {"tool":"pint","result":"fixed","files":["tests/Feature/Services/AvailabilityServiceTest.php (no_unused_imports)"]}
  PINT_EXIT=0
  PS> composer run lint:check
  {"tool":"pint","result":"passed"}
  LINTCHECK_EXIT=0
  PS> php artisan test   (sesudah formatter)
  {"tool":"pest","result":"passed","tests":134,"passed":134,"assertions":1068,"duration_ms":18480}
  SUITE_EXIT=0
  ```

  134 = 117 tes (T-006 dan sebelumnya) + 17 tes AV. **0 risky, 0 gagal.**
- Pemetaan kasus uji → tes (semua lulus): AV-01 tes "offers an active room"; AV-02 "rejects a request that partially overlaps active nights"; AV-03 "allows same-day turnover"; AV-04 "hides a room when a block covers one night" (+ turnover setelah blokir); AV-05 "excludes maintenance and inactive rooms"; AV-06 dataset cancelled/expired/no_show "do not block"; AV-07 "a pending booking blocks the room"; AV-08 "a dirty room stays available for future dates"; AV-09 "rejects a guest count above the total capacity" (+ filter kapasitas `availableRooms`); AV-10 dataset "same day"/"backwards"; AV-11 "guards past check-in dates by role and reason" (resepsionis ditolak, anonim ditolak, manager tanpa alasan ditolak, manager+owner dengan alasan lolos); AV-12 "rejects a block that overlaps active nights" + "allows a block when the overlapping nights are inactive"; AV-13 "rejects maintenance and inactive while future active nights exist" (termasuk: set ke active selalu boleh, dan malam lampau tidak menghalangi).
- Tes: 134 lulus, 0 gagal, 0 risky (1068 assert, Pest 4.x, PostgreSQL 18). Tes AV: 17 (45 assert).
- File kunci: `app/Services/AvailabilityService.php`, `app/Exceptions/AvailabilityException.php`, `tests/Feature/Services/AvailabilityServiceTest.php`.
- Penyimpangan dari dokumen sumber: tidak ada terhadap BR-01/BR-07/AV. Satu hal untuk diperiksa reviewer: konvensi `[start, end)` untuk `room_blocks` diturunkan dari CHECK database (butir keputusan 1 di atas) karena tidak ada dokumen yang menyatakannya eksplisit — jika user menghendaki lain, cukup ubah pembanding `end_date >` menjadi `>=` di dua tempat dan perbarui tes.
- Perintah kunci dan sumber yang dipakai:
  - Aturan: BR-01 poin 1-6 (ketersediaan, turnover, validasi, tiga lapis), BR-07 poin 1/2/5 (status kamar, penolakan maintenance, housekeeping bebas), AV-01..AV-13, US-08/US-05/US-13 — semuanya dari `docs/business_rules_kamarin.md` dan `docs/rencana_pengujian.md`
  - Setengah rentang terbuka `[from, to)` vs malam `stay_date`: standar interval reservasi; konsistensi antara query malam dan query blokir dibuktikan tes AV-03/AV-04 (turnover dengan dan tanpa blokir)
  - `today()` Laravel dengan pembeku waktu T-003 (`freezeNow`) — diverifikasi AV-11/AV-13 yang memakai "hari ini" deterministik
- Penjelasan untuk sidang (bagaimana dan kenapa): AvailabilityService adalah satu-satunya tempat aplikasi menjawab "apakah kamar ini bebas pada tanggal ini", sehingga pertanyaan yang sama tidak dijawab berbeda oleh halaman publik, dashboard, dan pembuatan booking. Ia menegakkan BR-01 dan BR-07 di lapis aplikasi pertama: kamar harus aktif, malam aktif menghalangi, blokir menghalangi, turnover hari sama diizinkan, kapasitas dan validasi tanggal diperiksa sebelum baris booking disentuh — sementara lapis kedua (kunci baris) dan ketiga (unique parsial di database) tetap menjadi jaring pengaman yang membuktikan klaim ini di level server. Kasus yang paling mudah salah tangkap dipecahkan dengan penalaran yang bisa ditunjuk: rentang blokir memakai konvensi `[start, end)` karena CHECK database melarang blokir satu hari dengan bentuk lain, dan housekeeping sengaja tidak muncul di satu pun query karena BR-07.5 menyatakan status kebersihan tidak memengaruhi tanggal mendatang. Setiap penolakan memakai exception domain dengan pesan yang menyebut kamar dan aturan, sehingga staf dan tamu bisa menerima penjelasan yang jelas (menuju US-13.2). Kode berjalan merah dulu (17 error karena service belum ada), lalu hijau; temuan selama iterasi (tipe kembalian collection, perilaku `throwsNoExceptions()` di Pest 4) diperbaiki dengan menambah ketepatan kode dan assertion, bukan dengan melonggarkan tes.
- Reviewer: agent Reviewer terpisah (2026-10-12) — **LULUS**; AV-01..AV-13 semua terpetakan penuh, tidak ada kasus uji terlewat, BR-01/BR-07 terpenuhi dan dibuktikan assertion. Tiga titik keputusan dinilai sah: konvensi `[start, end)` (diturunkan dari CHECK database — reviewer setuju tapi sarankan dikunci tertulis; user memilih **cukup di STATUS ini**, tidak di business_rules), interpretasi `stay_date >= today` (setuju), nama role konstanta privat (jembatan sampai T-007). Empat temuan minor **sudah diperbaiki di cabang ini**: (1) dedupe kamar di `assertValidStay` (kapasitas tidak tergandakan), (2) tes `excludeBookingId` (sebelumnya "belum terbukti"), (3) tes batas "tepat hari ini" AV-13, (4) skenario AV-13 dirapikan dari `checkedOut` menjadi `confirmed` agar realistis. Tes AV kini 20/20. Sisa catatan tanpa perbaikan (disengaja): semantik `availableRooms($guestCount)` per-kamar vs `assertValidStay` per-total dikunci ulang saat booking engine publik (US-26/US-27); klausul `minimum_nights` BR-01.2 memang tugas PricingService (T-009).

### T-009 PricingService dan tes PR-01..PR-11 (2026-10-12)

- Cabang: `task/T-009-pricing-service`, dari tip `main` (`743d8eb`). **Sudah di-merge fast-forward ke `main` dan di-push** (`main` = `origin/main` = `99699ff`, merge `--ff-only` EXIT=0).
- Status: selesai, **review LULUS oleh agent Reviewer terpisah** (backlog menandai T-009 wajib review). Semua temuan reviewer MINOR, tanpa hard-rule violation; PR-01..PR-11 terpetakan penuh. Suite verifikasi ulang di `main`: **149/149, 1119 assert**, `lint:check` lulus.
- Rujukan yang dibaca sebelum mulai: baris T-009 di `docs/tasks/BACKLOG_FASE4_5.md` (DoD: "Semua tes PR lulus. Harga per malam, priority, `days_of_week` ISO, minimum_nights dari tarif malam check-in, harga terkunci"), BR-11 poin 1-8, BR-12 poin 1-2, K1 (minimum_nights dari tarif malam check-in, owner/manager boleh override dengan alasan), K2 (dua tarif tumpuk priority sama ditolak; fallback id terbesar menang), kasus uji PR-01..PR-11 di `docs/rencana_pengujian.md` bagian 4.2, ERD `rates`/`booking_items`/`booking_nights`/`booking_charges`, US-19 (biaya tambahan/diskon).
- **Keputusan teknis (diverifikasi, bukan ditebak):**
  1. **ISO weekday memakai `format('N')`**, bukan `Carbon::isoDayOfWeek()` — versi Carbon di repo ini **tidak punya** `isoDayOfWeek` (diverifikasi: memanggilnya melempar `UnknownMethodException`); `format('N')` menghasilkan 1=Senin..7=Minggu persis BR-11.5. Hari tes: 2026-07-09=Kamis(4), 07-10=Jumat(5), 07-11=Sabtu(6).
  2. **Kandidat tarif** (BR-11.2): `(is_default ATAU start_date <= malam <= end_date) DAN (days_of_week kosong ATAU memuat hari ISO malam itu)`. Tanggal dibanding sebagai string `Y-m-d` (aman dari zona waktu karena cast `date:Y-m-d`).
  3. **Pemenang** (BR-11.3/K2): `priority` tertinggi; seri → `id` terbesar. Diimplementasi dengan comparator `[priority, id]` descending, bukan mengandalkan urutan query.
  4. **`minimum_nights`** dibaca dari tarif pemenang **malam check-in** (BR-11.8/K1); override hanya owner/manager dengan alasan tidak kosong.
  5. **Konflik tarif** (PR-05/K2): ditolak bila ada tarif `room_type` sama + `priority` sama yang **bertumpuk tanggal DAN hari**. Bertumpuk hari = salah satu `days_of_week` kosong atau irisan tak kosong; bertumpuk tanggal = `is_default` (selalu aktif) atau rentang beririsan; non-default tanpa rentang dianggap tidak aktif. Metode `assertNoConflictingRate` dipanggil saat menyimpan tarif (validasi di lapis servis).
  6. **Total** (BR-12): `subtotal = Σ booking_nights.price (is_active)`, `charges = Σ booking_charges.amount`, `total_amount = subtotal + charges`. Sumber kebenaran tetap `booking_nights` + `booking_charges`; `recalculateTotal` menyimpan ulang `bookings.total_amount`. Malam non-aktif (cancelled/expired/no-show) tidak dihitung.
  7. **Diskon** = `booking_charges.amount` **negatif**, hanya owner/manager (BR-12.1/US-19); `addCharge` menolak resepsionis/housekeeping mencatat diskon, lalu menghitung ulang total.
- Yang dikerjakan — empat file:
  - `app/Exceptions/PricingException.php` — exception domain; pesan menyebut aturan (BR-11.4/11.8/11.3/12.1) yang menolak.
  - `app/Services/PricingService.php` — 10 metode publik: `nightlyRateFor()` (pilih tarif pemenang, null jika tak ada), `pricePerNight()` (harga integer, lempar jika tak ada tarif = BR-11.4/PR-06), `quoteStay()` (harga per malam check-in..check-out, hari check-out bukan malam, mengembalikan `stay_date`+`price`+`rate_id` untuk dikunci T-010), `minimumNightsForCheckIn()`, `assertMinimumNightsSatisfied()` (PR-07/K1), `assertHasDefaultRate()` (PR-06/BR-11.4), `assertNoConflictingRate()` (PR-05/K2), `calculateTotals()` (BR-12/PR-10), `recalculateTotal()`, `addCharge()` (PR-11/BR-12.1). Helper privat `ratesOverlap`/`daysOverlap`/`datesOverlap`/`toDay`.
  - `database/factories/RateFactory.php` — state **`defaultRate()`** ditambahkan (catatan reviewer T-008: RateFactory perlu state tarif default): `is_default` true, tanpa rentang/tanpa hari, priority 0, minimum 1.
  - `app/Models/Booking.php` — relasi **`bookingNights()` `hasManyThrough`** (jalur ERD `BOOKINGS → BOOKING_ITEMS → BOOKING_NIGHTS`) untuk BR-12 membaca semua malam aktif; ditambah `use ...HasManyThrough`.
- Pemetaan kasus uji → tes (`tests/Feature/Services/PricingServiceTest.php`, 11 tes, semua lulus): PR-01 "prices every night from the default rate"; PR-02 "applies the weekend rate to ISO Friday and Saturday nights"; PR-03 "highest priority wins when weekend overlaps high season"; PR-04 "mixes prices per night across a season boundary" (total = jumlah malam); PR-05 "rejects overlapping rates that share a priority" (+ tarif priority berbeda/tak bertumpuk diterima); PR-06 "rejects an active room type that has no default rate" (+ setelah tarif default dibuat lolos); PR-07 "enforces minimum nights from the check-in night rate with override" (resepsionis ditolak, manager/owner dengan alasan lolos, malam cukup tanpa override); PR-08 "keeps the locked night price after the rate changes" (harga disalin ke `booking_nights`, bukan relasi hidup); PR-09 "returns whole-integer rupiah for prices and totals"; PR-10 "computes subtotal from active nights and total with charges" (malam non-aktif tak dihitung); PR-11 "allows positive charges and owner/manager discounts only" (diskon resepsionis ditolak, diskon manager menurunkan total).
- **Kejujuran proses (merah tidak bersih):** tes PR-01..PR-11 **ditulis lebih dulu** dari kasus uji, tapi red-proof TIDAK sebersih T-008: `PricingService` ditulis berdekatan dengan tes, sehingga run pertama gagal bukan karena "service belum ada" melainkan karena (a) 11 error pola factory statis `RoomTypeFactory::create()` / `RateFactory::create()` — Laravel mewajibkan `RoomType::factory()->create()`, sudah diperbaiki ke pola `Model::factory()`, dan (b) typo kutip string di `PricingService` baris 206 (buka `"` tutup `'`) sehingga `php -l` melaporkan syntax error. Keduanya diperbaiki; **tidak ada assertion yang dilonggarkan**. Catatan ini ditulis agar review memahami alur sebenarnya.
- Hasil akhir (dijalankan sesudah formatter, EXIT=0):

  ```
  PS> vendor\bin\pest tests\Feature\Services\PricingServiceTest.php
  {"tool":"pest","result":"passed","tests":11,"passed":11,"assertions":35,"duration_ms":20492}
  FOCUS_EXIT=0
  PS> composer run lint   (membersihkan spasi operator !, import, phpdoc, unused import di tes)
  PINT_EXIT=0
  PS> composer run lint:check
  {"tool":"pint","result":"passed"}
  LINTCHECK_EXIT=0
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":149,"passed":149,"assertions":1119,"duration_ms":191511}
  SUITE_EXIT=0
  ```

  149 = 138 tes (T-006, T-008, dan sebelumnya) + 11 tes PR. **0 risky, 0 gagal.** PostgreSQL `kamarin_test`.
- File kunci: `app/Services/PricingService.php`, `app/Exceptions/PricingException.php`, `tests/Feature/Services/PricingServiceTest.php`, `database/factories/RateFactory.php`, `app/Models/Booking.php` (relasi `bookingNights`).
- Penyimpangan dari dokumen sumber: tidak ada terhadap BR-11/BR-12/K1/K2/PR. Untuk review: (1) `assertHasDefaultRate` saat ini hanya mengecek **keberadaan** tarif default — belum menggabung pemeriksaan `is_active` room type (PR-06 menyebut "tipe kamar aktif"; validasi gabungan bisa ditambah di T-010 saat room type/tarif disimpan), (2) `quoteStay` mengembalikan `rate_id` untuk penguncian BR-11.7 tapi penulisan baris `booking_nights` sendiri adalah tugas BookingService T-010.
- Penjelasan untuk sidang (bagaimana dan kenapa): PricingService adalah satu-satunya tempat aplikasi menjawab "berapa harga kamar ini untuk malam ini", sehingga satu malam dihitung satu kali dari satu sumber tarif dan tidak bisa berbeda antara halaman publik, dashboard, dan pembuatan booking. Ia menegakkan BR-11: setiap malam memilih satu tarif pemenang (kandidat = default atau rentang tanggal yang mencakup malam itu, dan hari yang cocok; pemenang = priority tertinggi, seri id terbesar), dan `minimum_nights` dibaca dari tarif malam check-in sehingga kebijakan menginap minimal mengikuti tarif yang berlaku, bukan angka hardcode. Harga lalu **dikunci** sebagai salinan di `booking_nights.price` (BR-11.7/PR-08) sehingga mengubah tarif sesudah booking tidak mengubah harga booking lama — inilah alasan `base_price` dihapus di ERD v2.1: harga hanya punya satu sumber. BR-12 menghitung total dari dua sumber kebenaran (malam aktif + biaya tambahan) dan menyimpannya ke `bookings.total_amount`, dengan diskon sebagai baris negatif yang hanya boleh dicatat owner/manager. Kasus yang paling mudah salah — tarif bertumpuk dengan priority sama — dicegah di lapis servis saat tarif disimpan (K2), bukan hanya mengandalkan pemenang. Kode ditulis merah-dulu dari PR-01..PR-11; kejujuran bahwa red-proof tidak sebersih T-008 (kesalahan pola factory dan typo string, bukan karena service hilang) dicatat di butir "merah tidak bersih" di atas.
- Reviewer: agent Reviewer terpisah (2026-10-12) — **LULUS**, semua temuan MINOR, tanpa pelanggaran aturan keras (skema tidak diubah, logika bisnis di `app/Services`, uang integer rupiah, tes PostgreSQL `kamarin_test`). **PR-01..PR-11 semua terpetakan penuh dan terbukti assertion benar** (bukan tes kosong — reviewer menelusuri bahwa tes tidak bisa lulus dengan stub: PR-03 menuntut 500000 bukan default, PR-10 mengecualikan malam inactive 999999, PR-11 menuntut guard role). Enam titik yang Builder tandai dikonfirmasi: `assertHasDefaultRate` (cek keberadaan memadai untuk PR-06, lebih ketat dari BR-11.4; gabung `is_active` bisa di T-010); ISO weekday `format('N')` benar + tanggal tes akurat (reviewer verifikasi independen 07-09=Thu, 07-10=Fri, 07-11=Sabtu); PR-08 tidak vacuous (membuktikan `price` kolom salinan, bukan relasi hidup); merah tidak bersih = catatan jujur bukan cacat; tie-break K2 benar (comparator `[priority,id]` descending); `assertNoConflictingRate` semantik overlap benar di semua kasus tepi. Penilaian sidang: user bisa mempertahankan kode; dua hal untuk disiapkan jawabannya — bukti end-to-end penguncian harga ada di T-010 (BookingService), dan tie-break id = fallback defensif data rusak (tarif seri sudah ditolak saat simpan, jadi tidak diuji langsung).

### T-010 BookingService dan tes BK-01..BK-08 (2026-10-12)

- Cabang: `task/T-010-booking-service`, dari tip `main` (`be191f3`). **Sudah di-merge fast-forward ke `main` dan di-push** (`main` = `origin/main` = `b81ed5c`, merge `--ff-only` EXIT=0, push `be191f3..b81ed5c` EXIT=0). Verifikasi ulang di `main`: **159/159, 1162 assert**, `lint:check` lulus.
- Status: selesai, **review LULUS oleh dua agent Reviewer terpisah** (review awal + review ulang perbaikan minor; backlog menandai T-010 wajib review). Tes fokus BK: **10/10, 43 assert**; seluruh suite: **159/159, 1162 assert**, `lint:check` lulus, PostgreSQL `kamarin_test`.
- Rujukan yang dibaca sebelum mulai: baris T-010 di `docs/tasks/BACKLOG_FASE4_5.md` (DoD: "Semua tes BK lulus. Termasuk pengujian konkurensi atau, jika tidak bisa diotomatisasi, tes penanganan pelanggaran unique index dengan pesan yang benar (dicatat jujur di STATUS.md)"), BR-01 poin 1-6 (khususnya poin 5: tiga lapis), BR-13 + K5 (expiry), BR-14 (kode booking), BR-09 (sumber wajib), kasus uji BK-01..BK-08 di `docs/rencana_pengujian.md` bagian 4.3, ERD `bookings`/`booking_items`/`booking_nights`/`booking_status_logs`/`guests`/`booking_sources`, US-12/US-13/US-21.
- **Keputusan teknis (diverifikasi, bukan ditebak):**
  1. **API `createBooking(Property, Guest, ?BookingSource, PaymentOption, guestCount, checkIn, checkOut, rooms, ?actor, ?reason)`** — `Guest` eksplisit karena `bookings.guest_id` NOT NULL di ERD; logika cari-orang-tamu dipisah ke `findOrCreateGuest()` agar BK-07 diuji terpisah dan `createBooking` fokus ke mekanik booking.
  2. **Website vs staf dibedakan dari `?actor`**, bukan boolean terpisah: `actor === null` → `created_by_user_id` null **dan** `expires_at = now + property.pending_expiry_hours` (BR-13/K5); `actor` terisi → `expires_at` null. Satu sumber kebenaran, tidak bisa nyeleneh.
  3. **Urutan dalam transaksi** (BR-01.5 lapis 2): `SELECT ... FOR UPDATE` pada rooms dengan `orderBy('id')` (urutan stabil, anti-deadlock) → `assertValidStay()` **diulang di bawah kunci baris** → baru insert booking, items, nights, status log → `recalculateTotal()`. Transaksi kedua untuk kamar yang sama menunggu lock, dan saat melanjutkan melihat malam yang sudah ter-commit sehingga ditolak.
  4. **Lapis 3 (unique index)**: `QueryException` dengan SQLSTATE `23505` diterjemahkan ke `AvailabilityException` berpesan jelas, bukan membocorkan error driver. Asumsi bahwa `QueryException` membawa `errorInfo` **diverifikasi di sumber framework** (`vendor/laravel/framework/src/Illuminate/Database/QueryException.php` baris 70-72: `if ($previous instanceof PDOException) { $this->errorInfo = $previous->errorInfo; }`), bukan dari ingatan.
  5. **`booking_code` BR-14**: 8 karakter dari alfabet `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` (32 karakter = huruf tanpa I/O + angka tanpa 0/1), diambil `random_int` (acak, bukan penomoran → tidak berurutan), dengan loop cek unik memakai `withTrashed()` karena index unique tetap menutupi baris soft-deleted.
  6. **Harga terkunci** (BR-11.7/PR-08): `quoteStay()` → `booking_nights.price` + `rate_id`; `is_active` diambil dari `$booking->status->holdsNights()` (pending = true, kamar terblokir).
  7. **BK-06**: parameter `?BookingSource` sengaja nullable + validasi eksplisit **sebelum** transaksi → `BookingException` yang menyebut BR-09, supaya penolakan jelas alih-alih error FK database.
- File yang dikerjakan — empat file:
  - `app/Exceptions/BookingException.php` — exception domain untuk penolakan yang bukan urusan ketersediaan/harga (BR-09).
  - `app/Services/BookingService.php` — `createBooking()` (transaksi + kunci baris + cek ulang + salinan harga + status log + kode booking) dan `findOrCreateGuest()`; helper privat `countNights`/`toDay`/`generateBookingCode`.
  - `tests/Feature/Services/BookingServiceTest.php` — 10 tes: BK-01..BK-08 + 2 tambahan (lihat bawah).
  - `app/Services/AvailabilityService.php` — **koreksi 1 kata pada komentar**: referensi "BookingService (T-009)" menjadi "(T-010)" karena T-009 adalah PricingService. Komentar saja, tidak ada perubahan kode.
- Pemetaan kasus uji → tes (semua lulus): BK-01 "creates a pending booking with a code, active nights and a status log" (status pending, kode cocok regex `[2-9A-HJ-NP-Z]{8}`, malam aktif 2 = 10+11 Juli tanpa hari check-out, total 600000, log pertama `old_status` null); BK-02 "sets expires_at for website bookings but not for staff bookings" (website `created_by` null + `expires_at` = now+7 jam, memakai nilai non-default agar hardcode ketahuan; staf `expires_at` null + `created_by` = id staf); BK-03 "rolls back the whole multi-room booking when one room conflicts" (multi-kamar, satu kamar sudah terisi → `AvailabilityException` **dan** `Booking::count()` tetap 1, `BookingItem` tetap 1, malam aktif tetap 2 = tidak ada setengah jadi); BK-04 "only one booking wins the same room and nights" (permintaan kedua untuk kamar+malam sama ditolak, jumlah booking tetap 1); BK-05 "the database rejects a second active night for the same room and date" (insert `BookingNight` aktif duplikat → `QueryException` dari index parsial); BK-06 "rejects a booking without a source" (source null → `BookingException`, 0 booking); BK-07 "finds a returning guest by email without merging automatically" (email sama → id yang sama, tidak duplikat, nama lama **tidak** ditimpa; email baru → tamu baru); BK-08 "generates distinct, non-sequential booking codes" (dua kode beda, 8 karakter, regex benar, nol kemunculan `0/O/1/I`).
- **Dua tes tambahan** di luar BK (keduanya menutup temuan/DoD):
  - `BK-05 (service): turns a unique index violation into a clear availability message` — **DoD T-010** ("tes penanganan pelanggaran unique index dengan pesan yang benar"). **Tidak memakai stub**: `assertValidStay` (lapis 1/2) berjalan asli dan lolos karena belum ada yang terisi saat itu, lalu hook `Booking::creating` menyisipkan malam bentrok di jeda antara cek-ulang dan penulisan `booking_nights` — persis jendela race yang harus ditahan lapis 3. Membuktikan 23505 index parsial → `AvailabilityException` dengan pesan persis + `previous` = `QueryException`, dan **rollback penuh** (booking rakitan hook ikut terhapus: jumlah booking 0, malam aktif 0). Tes ini otomatis terverifikasi: jika hook tidak memicu, booking justru berhasil dan `$this->fail(...)` yang menggagalkannya.
  - `PR-08: locks the quoted price into booking_nights so a later rate change never touches the booking` — **temuan review T-009** ("bukti end-to-end penguncian harga"). Membuktikan `rate_id` tercatat di tiap malam, lalu tarif digandakan → harga malam dan `total_amount` tidak bergerak.
- **Kejujuran proses (merah tidak bersih):** tes BK-01..BK-08 **ditulis lebih dulu** dari kasus uji, tapi red-proof TIDAK sebersih T-008: run pertama gagal 7 error bukan karena "service belum ada" melainkan karena salah import `RoomStatus` (ada di `App\Enums`, ditulis `App\Models`). Sesudah diperbaiki, 6 tes gagal benar dengan `Target class [App\Services\BookingService] does not exist`; **BK-05 lulus sejak awal** karena ia memang menguji index database (lapis 3) yang sudah ada dari migrasi T-005, bukan service. Kegagalan lanjutan: BK-08 lupa `freezeNow` sehingga tanggal 2026-07-10 dianggap lampau (hari ini 2026-10-12); PR-08 gagal karena agregat SQL `SUM` di PostgreSQL mengembalikan `numeric`/string — diperbaiki dengan `(int)` persis seperti yang sudah dilakukan `PricingService::calculateTotals`, **bukan** dengan melonggarkan assertion. **Tidak ada assertion yang dilonggarkan.**
- **Catatan konkurensi (jujur, sesuai DoD):** BK-04 diuji **terserialisasi**, bukan dua proses benar-benar paralel — Pest berjalan satu thread sehingga tidak bisa merace `lockForUpdate` secara nyata. Jendela race-nya sendiri disimulasikan di tes `BK-05 (service)` lewat hook `Booking::creating` yang menyisipkan malam bentrok **setelah** `assertValidStay` asli selesai, sehingga lapis 1/2 tetap berjalan penuh dan hanya lapis 3 yang diuji. (Versi awal tes ini memakai stub `assertValidStay` dan STATUS sempat menyebutnya "satu-satunya cara"; reviewer menandai itu **overstatement** dan pendekatan hook memang lebih kuat — redaksi serta implementasinya sudah diperbaiki.) Meniru dua koneksi paralel sungguhan dinilai rapuh dan di luar kebutuhan MVP (satu properti, ≤15 kamar).
- Hasil akhir, sesudah perbaikan minor (EXIT=0):

  ```
  PS> vendor\bin\pest tests\Feature\Services\BookingServiceTest.php
  {"tool":"pest","result":"passed","tests":10,"passed":10,"assertions":43,"duration_ms":3256}
  EXIT=0
  PS> vendor\bin\pest
  {"tool":"pest","result":"passed","tests":159,"passed":159,"assertions":1162,"duration_ms":32372}
  SUITE_EXIT=0
  PS> composer run lint   (membersihkan import, phpdoc, concat_space)
  PINT_EXIT=0
  PS> composer run lint:check
  {"tool":"pint","result":"passed"}
  LINT_EXIT=0
  ```

  159 = 149 tes (T-006, T-008, T-009, dan sebelumnya) + 10 tes BK/PR-08. **0 risky, 0 gagal.**
- File kunci: `app/Services/BookingService.php`, `app/Exceptions/BookingException.php`, `tests/Feature/Services/BookingServiceTest.php`, `app/Services/AvailabilityService.php` (komentar).
- Penyimpangan dari dokumen sumber: tidak ada terhadap BR-01/BR-09/BR-13/BR-14/BK-01..BK-08. Tiga hal yang sengaja **tidak** dikerjakan dan dicatat, bukan ditebak: (1) "gabung cek `is_active` di `assertHasDefaultRate`" dari review T-009 — tabel `rates` tidak punya `is_active` (lihat "Pertanyaan terbuka dari agent" no. 1), (2) temuan M6 review T-010 soal normalisasi email/pencarian telepon di `findOrCreateGuest` — di luar BK-07 dan masuk domain US-21 (pertanyaan terbuka no. 2), (3) `BookingFactory` masih memakai pola kode `BK-` + `bothify` yang belum memenuhi BR-14; kode BR-14 yang benar dihasilkan `BookingService`, sedangkan factory tetap sebagai data uji untuk tes AV/PR yang tidak memeriksa format kode — perbaikiannya menunggu keputusan user agar tidak mengubah data tes tugas lain diam-diam.
- Penjelasan untuk sidang (bagaimana dan kenapa): BookingService adalah satu-satunya tempat aplikasi membuat booking, sehingga aturan yang menyangkut uang dan kamar tidak bisa ditembus lewat jalan pintas di controller. Ia menegakkan lapis kedua BR-01: kamar dikunci dengan `FOR UPDATE` berurutan id, baru ketersediaan diperiksa ulang di bawah kunci itu — karenanya dua permintaan untuk malam yang sama tidak pernah berdua menang, dan lapis ketiga (index parsial unik) tetap menjadi jaring pengaman yang pesannya diterjemahkan menjadi penjelasan yang bisa dibaca manusia. Satu booking dibuat dalam satu transaksi, jadi jika satu kamar dari pemesanan multi-kamar bermasalah tidak ada baris setengah jadi yang tertinggal (BK-03). Harga tiap malam **disalin** dari hasil `quoteStay` beserta `rate_id`-nya, sehingga mengubah tarif hari depan tidak mengubah booking lama (BR-11.7/PR-08) — inilah bukti end-to-end yang diminta reviewer T-009. Kode booking dibuat acak 8 karakter dari alfabet tanpa 0/O/1/I agar bisa dibaca ulang di telepon tanpa salah dengar (BR-14), dan kejujuran bahwa tes paralel sungguhan tidak mungkin di satu thread dicatat di butir "Catatan konkurensi".
- Reviewer: agent Reviewer terpisah (2026-10-12) — **LULUS**, **tanpa temuan HARD**. BK-01..BK-08 + PR-08 terpetakan penuh tanpa kasus uji terlewat, aturan keras AGENTS.md tidak dilanggar (skema tak diubah, logika bisnis di `app/Services`, uang integer rupiah, kebijakan expiry dibaca dari `properties`, tanpa dependensi/rahasia). Reviewer memverifikasi sendiri: alfabet BR-14 via skrip (32 karakter unik, tanpa 0/O/1/I, dan regex tes ekuivalen dua arah dengan alfabet), handler 23505 benar-benar teruji di PostgreSQL sungguhan, serta tiga klaim STATUS saya (path `QueryException.php` baris 70-72, narasi "merah tidak bersih", dan ketiadaan kolom `is_active` di tabel `rates`) dikonfirmasi benar. Reviewer juga menilai `(int)` pada `sum('price')` **bukan** pelemahan karena konsisten dengan `PricingService::calculateTotals`, dan keputusan tidak mengubah `BookingFactory` **wajar** (grep membuktikan tidak ada seeder yang memakainya, jadi kode itu tidak pernah masuk data nyata).
- **Enam temuan MINOR — lima sudah diperbaiki di cabang ini:**
  1. **M1 (diperbaiki)** — BK-02 memakai `pending_expiry_hours = 3` yang kebetulan sama dengan default `PropertyFactory`, sehingga tes bisa lolos walau `BookingService` di-hardcode `addHours(3)` (melanggar aturan keras 6 tanpa ketahuan). Kini memakai **7**, bukan nilai default.
  2. **M2 (diperbaiki, dengan koreksi terhadap saran reviewer)** — handler 23505 juga menangkap tabrakan `bookings.booking_code` (BR-14) dan akan melaporkannya dengan pesan "room was just booked" yang menyesatkan. **Saran reviewer membedakan lewat `errorInfo[1]`, tapi verifikasi langsung di PostgreSQL ini membuktikan itu salah**: `errorInfo[1]` bernilai **`7`** (kode severity) untuk *kedua* constraint, sedangkan nama constraint ada di **`errorInfo[2]`** (di dalam pesan). Dibuktikan lewat tes probe sementara (dihapus setelah dipakai) yang memicu keduanya: `booking_nights_room_stay_active_unique` vs `bookings_booking_code_unique`. Implementasi kini mengecek `errorInfo[2]` berisi nama index malam; selain itu `QueryException` dilempar ulang apa adanya.
  3. **M3 (diperbaiki)** — STATUS menyebut stub "satu-satunya cara" mencapai handler 23505, dan reviewer menandainya **overstatement** karena hook `Booking::creating` bisa mensimulasikan jendela race tanpa mematikan lapis apa pun. Tes `BK-05 (service)` kini **memakai hook itu, tanpa stub**: `assertValidStay` berjalan asli lalu malam bentrok disisipkan di jeda cek-ulang ↔ penulisan. Redaksi STATUS juga diluruskan.
  4. **M4 (diperbaiki, dengan catatan kelemahan)** — BK-01 kini meng-assert **keunikan** kode (`Booking::where('booking_code', ...)->count() === 1`), bukan hanya formatnya, karena kasus uji menyebut "kode 8 karakter **unik**". **Jujur**: dengan hanya satu booking di database, assertion ini hampir tautologis — ia memenuhi huruf kasus uji tapi bukan bukti kuat. Bukti keunikan yang sesungguhnya ada di BK-08 (dua booking → dua kode berbeda). Jangan diklaim di sidang sebagai bukti kuat keunikan.
  5. **M5 (diperbaiki)** — BK-03 kini meng-assert **pesan** penolakan (`Room {room_number} already has active nights`) dan menolak pesan kapasitas, sehingga tes tidak bisa lolos karena alasan yang salah bila kapasitas factory berubah.
  6. **M6 (TIDAK dikerjakan, jadi pertanyaan terbuka no. 2)** — `findOrCreateGuest` mencocokkan email mentah tanpa `trim`/case-normalisasi dan `phone` hanya dipakai saat membuat, bukan mencari. Di luar BK-01..BK-08 (yang hanya menguji email) dan menyentuh domain US-21, jadi tidak ditebak diam-diam.
- **INFO I1** — ada dua tes bernama "BK-05": milik T-005 di `tests/Feature/Database/TransactionSchemaTest.php` (lebih ketat: meng-assert nama index di pesan) dan milik T-010 ini. Tes T-010 sudah membawa sufiks **"(service)"** sehingga terpetakan jelas; tes milik T-005 sengaja **tidak** diubah karena di luar ruang lingkup tugas ini.
- **INFO I5 (dicatat, di luar ruang lingkup T-010)** — `lockForUpdate` saat ini hanya ada di `BookingService`; sisi pembuatan `room_blocks` (BR-01.6, booking vs blokir) belum mengunci baris kamar, sehingga race booking-blokir secara teori masih mungkin. Layak jadi pertanyaan terbuka untuk tugas blokir berikutnya.
- **Review ulang oleh reviewer kedua (2026-10-12) — LULUS untuk merge**, semua M1..M6 dan I1 dinyatakan **TERTUTUP**, tanpa temuan HARD dan tanpa regresi (suite 159/159, `lint:check` lulus, tree bersih). Reviewer kedua tidak sekadar membaca klaim — ia **membuktikan dua perbaikan secara empiris**: (a) M1 diuji dengan meng-hardcode `now()->addHours(3)` sementara, dan BK-02 **memang merah** (`expected 2026-06-15 17:00:00, actual 13:00:00`) — membuktikan tes kini mendeteksi hardcode; kode lalu dikembalikan persis. (b) M2 diuji dengan probe yang memicu dua pelanggaran unique berbeda, dan hasilnya **mengonfirmasi Builder benar serta saran reviewer awal keliru**: `errorInfo[1]` = `7` (severity PostgreSQL, identik untuk kedua constraint), nama constraint di `errorInfo[2]`. Reviewer juga memverifikasi hook `Booking::creating` tidak bocor antar-tes (dispatcher baru per aplikasi tes) dan menegaskan assertion rollback `Booking::count()===0` valid karena baris rakitan hook berada di transaksi yang sama. Satu temuan baru **N1** (MINOR, STATUS menyimpan angka 41/1160 dan frasa "now+3 jam" dari sebelum fix) **sudah diperbaiki** bersama catatan kejujuran bahwa assertion unik BK-01 (M4) bersifat hampir tautologis dengan satu baris data. Catatan desain tanpa cacat: loop `generateBookingCode` tanpa batas percobaan (tabrakan ~1/32⁸, praktis nol) dan simulasi race di BK-05 (service) memakai transaksi yang sama, bukan kompetitor yang sudah ter-commit — keduanya dibahas jujur di butir "Catatan konkurensi".
### Review mandiri T-005 + T-005b terhadap dokumen sumber (2026-10-12)

- Cabang: `task/T-005-transaction-migrations` (tip `e58b8ac`, tanpa perubahan apa pun selama review). Rujukan: AGENTS.md, `docs/erd_v2_1.mermaid` (dibaca ulang per blok), BR-01, `docs/tasks/BACKLOG_FASE4_5.md`.
- Status: **(1) LULUS, (2) LULUS, (3) LULUS, (4) LULUS, (5) LULUS** — fokus (4) sempat terblokir Smart App Control memblokir `pdo_pgsql` (riwayat insiden lengkap di bawah); setelah user menonaktifkan SAC, siklus dijalankan ulang lulus dan seluruh suite hijau (57/57 tes, 867 assert). Tidak ada temuan cacat kode.
- Catatan peran (jujur): review ini **self-review** — agent yang sama dengan penulis kode. AGENTS.md melarang penulis kode me-review kodenya sendiri; direkomendasikan review kedua oleh agent/pengguna lain. **Tidak ada kode yang diedit** selama review (hanya file STATUS.md ini).
- Lingkungan: Docker Desktop tidak berjalan saat mulai → dinyalakan agent (engine 29.8.1), container `kamarin-postgres` dijalankan ulang. Database development `kamarin` kosong sebelum dan sesudah review (semua data uji dibuang `ROLLBACK`).
- Metode (1)-(3): satu skrip psql — satu transaksi `BEGIN` dengan `SAVEPOINT` per kasus, setiap kasus buruk diharapkan error lalu `ROLLBACK TO`, diakhiri `ROLLBACK` akhir. Keuntungan: server yang sama sekali tidak melihat data review.

#### Fokus (1) — dua malam aktif (room, tanggal) sama DITOLAK; nonaktif + aktif DIIZINKAN (LULUS)

```text
--- (1a) BK-05: dua malam AKTIF untuk (room, tanggal) sama -> harus DITOLAK database ---
INSERT 0 1
INSERT 0 1
psql:/tmp/review_t005.sql:77: ERROR:  duplicate key value violates unique constraint "booking_nights_room_stay_active_unique"
DETAIL:  Key (room_id, stay_date)=(3, 2026-06-01) already exists.

--- (1b) malam NONAKTIF + malam AKTIF untuk (room, tanggal) sama -> harus DIIZINKAN ---
INSERT 0 1
 stay_date  | is_active | room_id 
------------+-----------+---------
 2026-06-01 | t         |       3
 2026-06-01 | f         |       3
(2 rows)

--- bukti database kosong setelah ROLLBACK akhir skrip ---
 properties | rooms | bookings | booking_nights | booking_charges 
------------+-------+----------+----------------+-----------------
          0 |     0 |        0 |              0 |               0
(1 row)
```

#### Fokus (2) — room_id malam menyimpang dari item induk DITOLAK; ON UPDATE CASCADE memindahkan malam (LULUS)

```text
--- (2a) malam dengan room_id BERBEDA dari item induknya -> harus DITOLAK ---
psql:/tmp/review_t005.sql:98: ERROR:  insert or update on table "booking_nights" violates foreign key constraint "booking_nights_booking_item_id_room_id_foreign"
DETAIL:  Key (booking_item_id, room_id)=(5, 4) is not present in table "booking_items".

--- (2b) ON UPDATE CASCADE: pindahkan item ke kamar lain, malam harus ikut ---
  BEFORE update (item3 in room R2):
 booking_item_id | room_id | stay_date  
-----------------+---------+------------
               7 |       4 | 2026-06-05
               7 |       4 | 2026-06-06
(2 rows)
UPDATE 1
  AFTER update (booking_items.room_id R2 -> R1; nights must follow):
 booking_item_id | room_id | stay_date  
-----------------+---------+------------
               7 |       3 | 2026-06-05
               7 |       3 | 2026-06-06
(2 rows)
```

Catatan teknis: baris `ERROR` berasal dari `stderr` psql — di keluaran PowerShell gabungan muncul tertunda setelah blok `stdout`. Nomor baris skrip yang dicetak psql (77, 98, 146..223) membuktikan tiap error terjadi tepat di perintahnya; urutan logika skrip tidak rusak.

#### Fokus (3) — 12 CHECK kewajaran menolak nilai buruk dengan namanya sendiri (LULUS)

13 perintah buruk menutup 12 constraint (`deposit_percent` diuji di dua sisi rentang). Keluaran mentah (baris `DETAIL: Failing row contains (...)` juga tercetak di keluaran asli, tidak diduplikasi di sini demi ruang):

```text
psql:/tmp/review_t005.sql:146: ERROR:  new row for relation "bookings" violates check constraint "bookings_guest_count_positive"
psql:/tmp/review_t005.sql:155: ERROR:  new row for relation "bookings" violates check constraint "bookings_total_amount_non_negative"
psql:/tmp/review_t005.sql:161: ERROR:  new row for relation "booking_items" violates check constraint "booking_items_subtotal_non_negative"
psql:/tmp/review_t005.sql:167: ERROR:  new row for relation "booking_nights" violates check constraint "booking_nights_price_non_negative"
psql:/tmp/review_t005.sql:173: ERROR:  new row for relation "payments" violates check constraint "payments_amount_positive"
psql:/tmp/review_t005.sql:179: ERROR:  new row for relation "rates" violates check constraint "rates_price_non_negative"
psql:/tmp/review_t005.sql:185: ERROR:  new row for relation "rates" violates check constraint "rates_minimum_nights_positive"
psql:/tmp/review_t005.sql:193: ERROR:  new row for relation "rates" violates check constraint "rates_end_date_not_before_start"
psql:/tmp/review_t005.sql:199: ERROR:  new row for relation "room_types" violates check constraint "room_types_capacity_positive"
psql:/tmp/review_t005.sql:205: ERROR:  new row for relation "properties" violates check constraint "properties_deposit_percent_range"
psql:/tmp/review_t005.sql:211: ERROR:  new row for relation "properties" violates check constraint "properties_deposit_percent_range"
psql:/tmp/review_t005.sql:217: ERROR:  new row for relation "properties" violates check constraint "properties_pending_expiry_positive"
psql:/tmp/review_t005.sql:223: ERROR:  new row for relation "properties" violates check constraint "properties_free_cancel_days_non_negative"

--- (3 extra) booking_charges TANPA CHECK: diskon negatif harus DIIZINKAN ---
INSERT 0 1
```

#### Fokus (4) — migrate:rollback --step=3 lalu migrate: TERBLOKIR lingkungan (bukan cacat kode)

Percobaan hari ini gagal **sebelum menyentuh database** (tidak ada migration yang berjalan, tidak ada perubahan skema):

```text
Warning: PHP Startup: Unable to load dynamic library 'pdo_pgsql' (tried: C:/laragon/bin/php/php-8.3.35-Win32-vs16-x64/ext\pdo_pgsql (The specified module could not be found), C:/laragon/bin/php/php-8.3.35-Win32-vs16-x64/ext\php_pdo_pgsql.dll (An Application Control policy has blocked this file)) in Unknown on line 0

   Illuminate\Database\QueryException 

  could not find driver (Connection: pgsql, Host: 127.0.0.1, Port: 5432, Database: kamarin, SQL: select * from "migrations" where "batch" >= 1 order by "batch" desc, "migration" desc limit 3)

ROLLBACK_EXIT=1
MIGRATE_EXIT=1
```

Bukti sebab (semua diperiksa 2026-10-12):

```text
=== Smart App Control state (0=off,1=enforce,2=eval) ===
VerifiedAndReputablePolicyState : 1

=== event Code Integrity (Microsoft-Windows-CodeIntegrity/Operational) ===
[10/12/2026 09:53:28] EventId 3077: Code Integrity determined that a process (\Device\HarddiskVolume3\laragon\bin\php\php-8.3.35-Win32-vs16-x64\php.exe) attempted to load \Device\HarddiskVolume3\laragon\bin\php\php-8.3.35-Win32-vs16-x64\ext\php_pdo_pgsql.dll that did not meet the Enterprise signing level requirements or violated code integrity policy (Policy ID:{0283ac0f-fff1-49ae-ada1-8a933130cad6}).
[10/12/2026 09:48:23] EventId 3077: Code Integrity determined that a process (\Device\HarddiskVolume3\Program Files\nodejs\node.exe) attempted to load \Device\HarddiskVolume3\Users\andikaa\Desktop\Kamarin\node_modules\@voidzero-dev\vite-plus-win32-x64-msvc\vite-plus.win32-x64-msvc.node that did not meet the Enterprise signing level requirements or violated code integrity policy (Policy ID:{0283ac0f-fff1-49ae-ada1-8a933130cad6}).

=== ekstensi PHP yang load vs tidak ===
pdo: true; pdo_pgsql: false (juga pdo_sqlite, sqlite3, zip diblokir; curl/openssl/mbstring/dll tetap load)
=== timestamp DLL (tidak berubah sejak sebelum insiden) ===
php_pdo_pgsql.dll  43520  10/6/2026 6:14:26 AM
php_pgsql.dll     104960  10/6/2026 6:14:26 AM
```

- Interpretasi: SAC masuk mode **enforce** pada 2026-10-12 pagi dan memblokir kode tak dikenal/tidak ditandatangani. Modul native Vite di proyek ini juga kena blokir (event 09:48) — build frontend terdampak, bukan hanya PHP. Perubahan ada di kebijakan OS, bukan di proyek (kemarin 2026-10-11 siklus identik pada commit sama lulus EXIT=0 — tercatat di entri T-005b; `git log` tip sama, kode tidak berubah).
- Sumber resmi: Microsoft Learn, "Smart App Control overview" (https://learn.microsoft.com/en-us/windows/apps/develop/smart-app-control/overview): "In *enforcement mode*, Smart App Control is actively protecting your device. Apps cannot be run unless they are recognized by Microsoft's app intelligence services, or they are signed with a trusted certificate." dan "Malware, Potentially Unwanted Apps (PUA), and unknown, unsigned code are blocked by default." Mode diperiksa via Settings > Windows Security > App and Browser Control.
- Dampak: `migrate:rollback/migrate`, `SchemaConformityTest`, dan seluruh suite Pest **tidak bisa dijalankan** hari ini. Agent **tidak** mematikan/membypass kebijakan, tidak memasang PHP alternatif, tidak mengubah lingkungan — keputusan ada di user (3 opsi di "Pertanyaan terbuka").

**Resolusi (2026-10-12) — fokus (4) dijalankan ulang SETELAH user menonaktifkan SAC sendiri: LULUS.** Verifikasi sebelum ulang: registry `VerifiedAndReputablePolicyState = 0`, `php -r "var_dump(extension_loaded('pdo_pgsql'));"` → `bool(true)`. Jalanan ulang, keluaran mentah:

```text
=== rollback 3 langkah ===
 INFO Rolling back migrations. 

 2026_10_11_000021_add_composite_fk_and_reasonableness_checks .. 668.98ms DONE
 2026_10_11_000020_add_guest_search_indexes_to_guests_table .. 21.58ms DONE
 2026_10_11_000019_create_booking_change_logs_table .. 34.19ms DONE
ROLLBACK_EXIT=0
=== migrate lagi ===
 INFO Running migrations. 

 2026_10_11_000019_create_booking_change_logs_table .. 87.91ms DONE
 2026_10_11_000020_add_guest_search_indexes_to_guests_table .. 8.56ms DONE
 2026_10_11_000021_add_composite_fk_and_reasonableness_checks .. 67.51ms DONE
MIGRATE_EXIT=0
=== migrate:status SESUDAH (ringkas) ===
 2026_10_11_000017_create_payments_table .. [1] Ran 
 2026_10_11_000018_create_booking_status_logs_table .. [1] Ran 
 2026_10_11_000019_create_booking_change_logs_table .. [2] Ran 
 2026_10_11_000020_add_guest_search_indexes_to_guests_table .. [2] Ran 
 2026_10_11_000021_add_composite_fk_and_reasonableness_checks .. [2] Ran 
```

Verifikasi katalog SETELAH siklus — `down()` terbukti membalik, `up()` memasang lagi utuh:

```text
 fk_gabungan 
-------------
           1
(1 row)

 check_bernama 
---------------
            14
(1 row)

                                                               indexdef                                                               
--------------------------------------------------------------------------------------------------------------------------------------
 CREATE UNIQUE INDEX booking_nights_room_stay_active_unique ON public.booking_nights USING btree (room_id, stay_date) WHERE is_active
(1 row)
```

Jalur otomatis (yang semula terblokir) kini hijau:

```text
=== SchemaConformityTest ===
{"tool":"pest","result":"passed","tests":8,"passed":8,"assertions":738,"duration_ms":6223}
CONFORMITY_EXIT=0
=== SELURUH SUITE ===
{"tool":"pest","result":"passed","tests":57,"passed":57,"assertions":867,"duration_ms":17182}
SUITE_EXIT=0
```

Verdict (4) final: **LULUS** — `down()` terbukti dijalankan lalu `up()` memasang ulang seluruh penguatan (FK gabungan, 14 CHECK bernama, indeks parsial), dan seluruh suite 57/57 tes (867 assert) hijau di PostgreSQL `kamarin_test`. Blokir modul native Vite proyek ikut lepas dengan matinya SAC; build frontend layak diverifikasi user saat dipakai berikutnya.

#### Fokus (5) — tidak ada tabel atau kolom di luar ERD (LULUS, via psql manual)

Karena Pest terblokir, diverifikasi langsung lewat `pg_catalog`/`information_schema` vs pembacaan ulang `docs/erd_v2_1.mermaid` per blok:

- **27 tabel publik = 19 tabel ERD + 8 framework** (aturan 13: `cache`, `cache_locks`, `failed_jobs`, `job_batches`, `jobs`, `migrations`, `password_reset_tokens`, `sessions`).
- **169 kolom di 19 tabel ERD: nol kolom di luar ERD.** `users` hanya menambah `remember_token` + `email_verified_at` (pengecualian aturan 13, diperiksa di baris dump). 6 kolom uang semuanya `numeric(12,0)`: `booking_charges.amount`, `booking_items.subtotal`, `booking_nights.price`, `bookings.total_amount`, `payments.amount`, `rates.price`. Kolom tanggal memakai `date`. Soft delete `bookings.deleted_at` + `guests.deleted_at` ada.
- **29 FK** (28 ERD + FK gabungan T-005b), **14 CHECK bernama** (12 T-005b + `bookings_check_out_after_checkin` + `room_blocks_end_after_start`), **11 unique** (10 ERD + `booking_items_id_room_id_unique`).

Dump mentah `(5a)` tabel + jumlah kolom:

```text
         tabel         | jumlah_kolom 
-----------------------+--------------
 amenities             |            5
 booking_change_logs   |            9
 booking_charges       |            6
 booking_items         |            6
 booking_nights        |            9
 booking_sources       |            4
 booking_status_logs   |            8
 bookings              |           21
 cache                 |            3
 cache_locks           |            3
 failed_jobs           |            7
 guests                |           10
 housekeeping_statuses |            7
 job_batches           |           10
 jobs                  |            7
 migrations            |            3
 password_reset_tokens |            3
 payments              |           13
 properties            |           13
 rates                 |           13
 roles                 |            5
 room_blocks           |            8
 room_type_amenities   |            5
 room_types            |            8
 rooms                 |            8
 sessions              |            6
 users                 |           11
(27 rows)
```

Dump mentah `(5b)` semua FK skema public:

```text
         tabel         |                     conname                      |                                            definisi                                            
-----------------------+--------------------------------------------------+------------------------------------------------------------------------------------------------
 booking_change_logs   | booking_change_logs_booking_id_foreign           | FOREIGN KEY (booking_id) REFERENCES bookings(id)
 booking_change_logs   | booking_change_logs_changed_by_user_id_foreign   | FOREIGN KEY (changed_by_user_id) REFERENCES users(id)
 booking_charges       | booking_charges_booking_id_foreign              | FOREIGN KEY (booking_id) REFERENCES bookings(id)
 booking_items         | booking_items_booking_id_foreign                | FOREIGN KEY (booking_id) REFERENCES bookings(id)
 booking_items         | booking_items_room_id_foreign                   | FOREIGN KEY (room_id) REFERENCES rooms(id)
 booking_nights        | booking_nights_booking_item_id_room_id_foreign  | FOREIGN KEY (booking_item_id, room_id) REFERENCES booking_items(id, room_id) ON UPDATE CASCADE
 booking_nights        | booking_nights_rate_id_foreign                  | FOREIGN KEY (rate_id) REFERENCES rates(id)
 booking_nights        | booking_nights_room_id_foreign                  | FOREIGN KEY (room_id) REFERENCES rooms(id)
 booking_status_logs   | booking_status_logs_booking_id_foreign          | FOREIGN KEY (booking_id) REFERENCES bookings(id)
 booking_status_logs   | booking_status_logs_changed_by_user_id_foreign  | FOREIGN KEY (changed_by_user_id) REFERENCES users(id)
 bookings              | bookings_booking_source_id_foreign              | FOREIGN KEY (booking_source_id) REFERENCES booking_sources(id)
 bookings              | bookings_created_by_user_id_foreign             | FOREIGN KEY (created_by_user_id) REFERENCES users(id)
 bookings              | bookings_guest_id_foreign                       | FOREIGN KEY (guest_id) REFERENCES guests(id)
 bookings              | bookings_property_id_foreign                    | FOREIGN KEY (property_id) REFERENCES properties(id)
 housekeeping_statuses | housekeeping_statuses_room_id_foreign           | FOREIGN KEY (room_id) REFERENCES rooms(id)
 housekeeping_statuses | housekeeping_statuses_updated_by_user_id_foreign | FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
 payments              | payments_booking_id_foreign                     | FOREIGN KEY (booking_id) REFERENCES bookings(id)
 payments              | payments_recorded_by_user_id_foreign            | FOREIGN KEY (recorded_by_user_id) REFERENCES users(id)
 rates                 | rates_property_id_foreign                       | FOREIGN KEY (property_id) REFERENCES properties(id)
 rates                 | rates_room_type_id_foreign                      | FOREIGN KEY (room_type_id) REFERENCES room_types(id)
 room_blocks           | room_blocks_created_by_user_id_foreign          | FOREIGN KEY (created_by_user_id) REFERENCES users(id)
 room_blocks           | room_blocks_room_id_foreign                     | FOREIGN KEY (room_id) REFERENCES rooms(id)
 room_type_amenities   | room_type_amenities_amenity_id_foreign          | FOREIGN KEY (amenity_id) REFERENCES amenities(id)
 room_type_amenities   | room_type_amenities_room_type_id_foreign        | FOREIGN KEY (room_type_id) REFERENCES room_types(id)
 room_types            | room_types_property_id_foreign                  | FOREIGN KEY (property_id) REFERENCES properties(id)
 rooms                 | rooms_property_id_foreign                       | FOREIGN KEY (property_id) REFERENCES properties(id)
 rooms                 | rooms_room_type_id_foreign                      | FOREIGN KEY (room_type_id) REFERENCES room_types(id)
 users                 | users_property_id_foreign                       | FOREIGN KEY (property_id) REFERENCES properties(id)
 users                 | users_role_id_foreign                           | FOREIGN KEY (role_id) REFERENCES roles(id)
(29 rows)
```

Dump mentah `(5c)` semua CHECK bernama + unique:

```text
         tabel         |                      conname                       | contype |                                     definisi                                     
-----------------------+----------------------------------------------------+---------+----------------------------------------------------------------------------------
 booking_items         | booking_items_subtotal_non_negative                | c       | CHECK ((subtotal >= (0)::numeric))
 booking_nights        | booking_nights_price_non_negative                  | c       | CHECK ((price >= (0)::numeric))
 bookings              | bookings_check_out_after_checkin                   | c       | CHECK ((check_out_date > check_in_date))
 bookings              | bookings_guest_count_positive                      | c       | CHECK ((guest_count > 0))
 bookings              | bookings_total_amount_non_negative                 | c       | CHECK ((total_amount >= (0)::numeric))
 payments              | payments_amount_positive                           | c       | CHECK ((amount > (0)::numeric))
 properties            | properties_pending_expiry_positive                 | c       | CHECK ((pending_expiry_hours > 0))
 properties            | properties_free_cancel_days_non_negative           | c       | CHECK ((free_cancel_days >= 0))
 properties            | properties_deposit_percent_range                   | c       | CHECK (((deposit_percent >= 0) AND (deposit_percent <= 100)))
 rates                 | rates_end_date_not_before_start                    | c       | CHECK (((start_date IS NULL) OR (end_date IS NULL) OR (end_date >= start_date)))
 rates                 | rates_price_non_negative                           | c       | CHECK ((price >= (0)::numeric))
 rates                 | rates_minimum_nights_positive                      | c       | CHECK ((minimum_nights >= 1))
 room_blocks           | room_blocks_end_after_start                        | c       | CHECK ((end_date > start_date))
 room_types            | room_types_capacity_positive                       | c       | CHECK ((capacity > 0))
 amenities             | amenities_name_unique                              | u       | UNIQUE (name)
 booking_items         | booking_items_id_room_id_unique                    | u       | UNIQUE (id, room_id)
 booking_sources       | booking_sources_name_unique                        | u       | UNIQUE (name)
 bookings              | bookings_booking_code_unique                       | u       | UNIQUE (booking_code)
 failed_jobs           | failed_jobs_uuid_unique                            | u       | UNIQUE (uuid)
 housekeeping_statuses | housekeeping_statuses_room_id_unique               | u       | UNIQUE (room_id)
 roles                 | roles_name_unique                                  | u       | UNIQUE (name)
 room_type_amenities   | room_type_amenities_room_type_id_amenity_id_unique | u       | UNIQUE (room_type_id, amenity_id)
 rooms                 | rooms_property_id_room_number_unique               | u       | UNIQUE (property_id, room_number)
 users                 | users_email_unique                                 | u       | UNIQUE (email)
(24 rows)
```

Dump mentah `(5d)` 169 kolom 19 tabel ERD (dibandingkan baris per baris dengan ERD — tidak ada yang di luar; kolom framework dikeluarkan dari query):

```text
         tabel         |        kolom         |              tipe              | nullable 
-----------------------+----------------------+--------------------------------+----------
 amenities             | id                   | bigint                         | NO
 amenities             | name                 | character varying(255)         | NO
 amenities             | icon                 | character varying(255)         | YES
 amenities             | created_at           | timestamp(0) without time zone | YES
 amenities             | updated_at           | timestamp(0) without time zone | YES
 booking_change_logs   | id                   | bigint                         | NO
 booking_change_logs   | booking_id           | bigint                         | NO
 booking_change_logs   | changed_by_user_id   | bigint                         | YES
 booking_change_logs   | change_type          | character varying(255)         | NO
 booking_change_logs   | old_value            | text                           | NO
 booking_change_logs   | new_value            | text                           | NO
 booking_change_logs   | reason               | text                           | NO
 booking_change_logs   | created_at           | timestamp(0) without time zone | YES
 booking_change_logs   | updated_at           | timestamp(0) without time zone | YES
 booking_charges       | id                   | bigint                         | NO
 booking_charges       | booking_id           | bigint                         | NO
 booking_charges       | description          | character varying(255)         | NO
 booking_charges       | amount               | numeric(12,0)                  | NO
 booking_charges       | created_at           | timestamp(0) without time zone | YES
 booking_charges       | updated_at           | timestamp(0) without time zone | YES
 booking_items         | id                   | bigint                         | NO
 booking_items         | booking_id           | bigint                         | NO
 booking_items         | room_id              | bigint                         | NO
 booking_items         | subtotal             | numeric(12,0)                  | NO
 booking_items         | created_at           | timestamp(0) without time zone | YES
 booking_items         | updated_at           | timestamp(0) without time zone | YES
 booking_nights        | id                   | bigint                         | NO
 booking_nights        | booking_item_id      | bigint                         | NO
 booking_nights        | room_id              | bigint                         | NO
 booking_nights        | rate_id              | bigint                         | YES
 booking_nights        | stay_date            | date                           | NO
 booking_nights        | price                | numeric(12,0)                  | NO
 booking_nights        | is_active            | boolean                        | NO
 booking_nights        | created_at           | timestamp(0) without time zone | YES
 booking_nights        | updated_at           | timestamp(0) without time zone | YES
 booking_sources       | id                   | bigint                         | NO
 booking_sources       | name                 | character varying(255)         | NO
 booking_sources       | created_at           | timestamp(0) without time zone | YES
 booking_sources       | updated_at           | timestamp(0) without time zone | YES
 booking_status_logs   | id                   | bigint                         | NO
 booking_status_logs   | booking_id           | bigint                         | NO
 booking_status_logs   | changed_by_user_id   | bigint                         | YES
 booking_status_logs   | old_status           | character varying(255)         | YES
 booking_status_logs   | new_status           | character varying(255)         | NO
 booking_status_logs   | note                 | text                           | YES
 booking_status_logs   | created_at           | timestamp(0) without time zone | YES
 booking_status_logs   | updated_at           | timestamp(0) without time zone | YES
 bookings              | id                   | bigint                         | NO
 bookings              | property_id          | bigint                         | NO
 bookings              | guest_id             | bigint                         | NO
 bookings              | created_by_user_id   | bigint                         | YES
 bookings              | booking_source_id    | bigint                         | NO
 bookings              | booking_code         | character varying(255)         | NO
 bookings              | status               | character varying(255)         | NO
 bookings              | payment_option       | character varying(255)         | NO
 bookings              | guest_count          | integer                        | NO
 bookings              | check_in_date        | date                           | NO
 bookings              | check_out_date       | date                           | NO
 bookings              | expires_at           | timestamp(0) without time zone | YES
 bookings              | checked_in_at        | timestamp(0) without time zone | YES
 bookings              | checked_out_at       | timestamp(0) without time zone | YES
 bookings              | total_amount         | numeric(12,0)                  | NO
 bookings              | cancellation_reason  | character varying(255)         | YES
 bookings              | cancelled_at         | timestamp(0) without time zone | YES
 bookings              | notes                | text                           | YES
 bookings              | deleted_at           | timestamp(0) without time zone | YES
 bookings              | created_at           | timestamp(0) without time zone | YES
 bookings              | updated_at           | timestamp(0) without time zone | YES
 guests                | id                   | bigint                         | NO
 guests                | name                 | character varying(255)         | NO
 guests                | email                | character varying(255)         | YES
 guests                | phone                | character varying(255)         | YES
 guests                | country              | character varying(255)         | YES
 guests                | id_type              | character varying(255)         | YES
 guests                | id_number            | character varying(255)         | YES
 guests                | deleted_at           | timestamp(0) without time zone | YES
 guests                | created_at           | timestamp(0) without time zone | YES
 guests                | updated_at           | timestamp(0) without time zone | YES
 housekeeping_statuses | id                   | bigint                         | NO
 housekeeping_statuses | room_id              | bigint                         | NO
 housekeeping_statuses | updated_by_user_id   | bigint                         | YES
 housekeeping_statuses | status               | character varying(255)         | NO
 housekeeping_statuses | notes                | text                           | YES
 housekeeping_statuses | created_at           | timestamp(0) without time zone | YES
 housekeeping_statuses | updated_at           | timestamp(0) without time zone | YES
 payments              | id                   | bigint                         | NO
 payments              | booking_id           | bigint                         | NO
 payments              | recorded_by_user_id  | bigint                         | YES
 payments              | amount               | numeric(12,0)                  | NO
 payments              | type                 | character varying(255)         | NO
 payments              | method               | character varying(255)         | NO
 payments              | status               | character varying(255)         | NO
 payments              | payer_name           | character varying(255)         | YES
 payments              | payment_date         | date                           | NO
 payments              | reference_number     | character varying(255)         | YES
 payments              | proof_path           | character varying(255)         | YES
 payments              | created_at           | timestamp(0) without time zone | YES
 payments              | updated_at           | timestamp(0) without time zone | YES
 properties            | id                   | bigint                         | NO
 properties            | name                 | character varying(255)         | NO
 properties            | address              | text                           | NO
 properties            | city                 | character varying(255)         | NO
 properties            | phone                | character varying(255)         | YES
 properties            | check_in_time        | time(0) without time zone      | NO
 properties            | check_out_time       | time(0) without time zone      | NO
 properties            | deposit_percent      | smallint                       | NO
 properties            | pending_expiry_hours | smallint                       | NO
 properties            | free_cancel_days     | smallint                       | NO
 properties            | no_show_cutoff_time  | time(0) without time zone      | NO
 properties            | created_at           | timestamp(0) without time zone | YES
 properties            | updated_at           | timestamp(0) without time zone | YES
 rates                 | id                   | bigint                         | NO
 rates                 | property_id          | bigint                         | NO
 rates                 | room_type_id         | bigint                         | NO
 rates                 | name                 | character varying(255)         | NO
 rates                 | price                | numeric(12,0)                  | NO
 rates                 | start_date           | date                           | YES
 rates                 | end_date             | date                           | YES
 rates                 | days_of_week         | jsonb                          | YES
 rates                 | priority             | integer                        | NO
 rates                 | is_default           | boolean                        | NO
 rates                 | minimum_nights       | integer                        | NO
 rates                 | created_at           | timestamp(0) without time zone | YES
 rates                 | updated_at           | timestamp(0) without time zone | YES
 roles                 | id                   | bigint                         | NO
 roles                 | name                 | character varying(255)         | NO
 roles                 | description          | text                           | YES
 roles                 | created_at           | timestamp(0) without time zone | YES
 roles                 | updated_at           | timestamp(0) without time zone | YES
 room_blocks           | id                   | bigint                         | NO
 room_blocks           | room_id              | bigint                         | NO
 room_blocks           | created_by_user_id   | bigint                         | YES
 room_blocks           | start_date           | date                           | NO
 room_blocks           | end_date             | date                           | NO
 room_blocks           | reason               | character varying(255)         | NO
 room_blocks           | created_at           | timestamp(0) without time zone | YES
 room_blocks           | updated_at           | timestamp(0) without time zone | YES
 room_type_amenities   | id                   | bigint                         | NO
 room_type_amenities   | room_type_id         | bigint                         | NO
 room_type_amenities   | amenity_id           | bigint                         | NO
 room_type_amenities   | created_at           | timestamp(0) without time zone | YES
 room_type_amenities   | updated_at           | timestamp(0) without time zone | YES
 room_types            | id                   | bigint                         | NO
 room_types            | property_id          | bigint                         | NO
 room_types            | name                 | character varying(255)         | NO
 room_types            | description          | text                           | YES
 room_types            | capacity             | integer                        | NO
 room_types            | is_active            | boolean                        | NO
 room_types            | created_at           | timestamp(0) without time zone | YES
 room_types            | updated_at           | timestamp(0) without time zone | YES
 rooms                 | id                   | bigint                         | NO
 rooms                 | property_id          | bigint                         | NO
 rooms                 | room_type_id         | bigint                         | NO
 rooms                 | room_number          | character varying(255)         | NO
 rooms                 | floor                | integer                        | YES
 rooms                 | status               | character varying(255)         | NO
 rooms                 | created_at           | timestamp(0) without time zone | YES
 rooms                 | updated_at           | timestamp(0) without time zone | YES
 users                 | id                   | bigint                         | NO
 users                 | role_id              | bigint                         | NO
 users                 | property_id          | bigint                         | YES
 users                 | name                 | character varying(255)         | NO
 users                 | email                | character varying(255)         | NO
 users                 | password             | character varying(255)         | NO
 users                 | is_active            | boolean                        | NO
 users                 | email_verified_at    | timestamp(0) without time zone | YES
 users                 | remember_token       | character varying(100)         | YES
 users                 | created_at           | timestamp(0) without time zone | YES
 users                 | updated_at           | timestamp(0) without time zone | YES
(169 rows)
```

Tes otomatis `SchemaConformityTest` (8 tes, memparse ERD vs `information_schema`) terakhir hijau pada commit yang sama (`e58b8ac`, 2026-10-11: `tests:8, passed:8, assertions:738`); file tes tidak diubah dan kode tidak berubah sejak — namun saat verifikasi fokus (5) dijalankan, **jalur otomatis terblokir SAC**, sehingga pembandingnya memakai jalur manual psql seperti di atas. Setelah SAC dinonaktifkan, `SchemaConformityTest` dijalankan ulang dan tetap hijau 8/8 (lihat resolusi bagian fokus (4)) — kedua jalur sepakat: tidak ada tabel/kolom di luar ERD.

#### Rekomendasi (tidak ada perubahan wajib; semua bersumber dokumentasi)

1. **Pertahankan `NO ACTION`, jangan diganti `RESTRICT`.** Untuk kasus ini keduanya identik — keduanya menolak penghapusan baris yang dirujuk; bedanya hanya `RESTRICT` tidak boleh ditunda sedangkan `NO ACTION` bisa ditunda tapi tetap diperiksa segera (PostgreSQL Docs, "Constraints", https://www.postgresql.org/docs/current/ddl-constraints.html). Mengubah hanya menambah diff tanpa manfaat perilaku.
2. ~~**Opsional, butuh persetujuan user — perketat `SchemaConformityTest` FK jadi exact-set.**~~ **DIIMPLEMENTASIKAN 2026-10-12 (disetujui user, sebelum merge):** assertion kini dua arah — `array_diff($actual, $expected)` harus kosong, dengan `$actual` di-`array_unique` lebih dulu (FK gabungan T-005b menghasilkan 4 baris silang di `information_schema` → 30 string unik; `expected` ditambah satu representasi per kolom: `booking_nights.room_id->booking_items`). Suite **57/57 tes, 869 assert**. Commit `e9b111d test(database): assert exact foreign key set against erd`. Alasan awal: asersi lama hanya subset (expected ⊆ actual) — FK tambahan di luar ERD tidak akan ditolak tes. Sumber: AGENTS.md aturan 1 ("migration harus persis sesuai ERD v2.1").
3. **Tidak disarankan sekarang — CHECK enum di database.** `status`, `payment_option`, `type`, `method` sengaja tanpa CHECK database; aturan 2 AGENTS menempatkannya sebagai PHP enum (level aplikasi). Menambah CHECK `IN (...)` (PostgreSQL Docs, "Constraints") menduplikasi sumber kebenaran yang bisa tidak sinkron dengan enum PHP. Catat sebagai opsi masa depan saja.

Penjelasan untuk sidang (bagaimana dan kenapa): Review ini menguji pertahanan database secara manual lewat psql — bukan hanya lewat test suite — sehingga anti double booking (unique parsial), integritas rantai malam-item (FK gabungan + `ON UPDATE CASCADE`), dan 12 CHECK kewajaran terbukti langsung di server dengan pesan constraint yang bernama, sementara seluruh data uji dibuang `ROLLBACK` sehingga database development tidak berubah. Fokus (4) gagal bukan karena kode: sejak pagi Smart App Control Windows memblokir `pdo_pgsql` (bahkan modul native Vite proyek ikut kena), sehingga semua verifikasi berbasis PHP terhenti dan keputusan penanganannya ada di pengguna sebagai urusan keamanan mesin. Fokus (5) tetap tuntas lewat query katalog database yang dibandingkan baris per baris dengan ERD, sebagai pengganti sementara tes otomatis yang terblokir. Pelajaran proses: test suite hijau kemarin bukan jaminan hari ini — lingkungan bisa berubah, jadi bukti ulang harus dijalankan lagi dan kegagalan lingkungan dilaporkan terpisah dari kegagalan kode.

### T-005 Migration tahap 2 (transaksi) dan pengaman double booking di database (2026-10-11)

- Cabang: `task/T-005-transaction-migrations`. Catatan: saat tugas diminta, `main` masih di `026c9d5` (di belakang tip T-004 `5124cf1`); agent melakukan **fast-forward `main` ke `5124cf1`** lebih dulu karena instruksi menyebut "dari cabang utama setelah T-004 di-merge" — linier, tanpa merge commit, lalu membuat cabang.
- Status: selesai
- Rujukan yang dibaca sebelum mulai: AGENTS.md (aturan 1, 3, 4, 5, 10), baris T-005 di backlog, `docs/erd_v2_1.mermaid` (versi hasil revisi 2026-10-11), BR-01 (bagian 2), `docs/arsitektur_sistem.md` bagian 7, kasus uji BK-05 di `docs/rencana_pengujian.md`.
- Langkah 1 — **tes ditulis lebih dulu**, lalu dijalankan sebelum ada migration (bukti merah):

  ```
  PS> vendor\bin\pest tests/Feature/Database
  {"tool":"pest","result":"failed","tests":15,"passed":5,"assertions":391,"failed":5,"errors":5,"failures":[
    "...it_creates_every_master_table_from_the_ERD...","message":"Table [room_blocks] does not exist ...",
    "...it_enforces_the_unique_constraints...","message":"Missing unique index on [bookings.booking_code]",
    "...it_creates_the_foreign_keys...","message":"Missing foreign key [room_blocks.room_id->rooms]",
    "...TransactionSchemaTest::it_keeps_every_foreign_key_restrictive...","message":"11 is greater than or equal to 29 (gagal: 11 < 29)",
    "...TransactionSchemaTest::it_creates_the_performance_indexes...","message":"Partial unique index missing"],
    "error_details":["...BK-05: relation \"bookings\" does not exist..."] }
  ```

  (potongan; 10 gagal/berantah karena 8 tabel transaksi belum ada — BK-05 insert ke `bookings` pun langsung ditolak "relation does not exist"). Sebelum migrasi, tiga tes juga sengaja dikencangkan lagi: pesan QueryException kini wajib memuat **nama constraint** yang benar (`booking_nights_room_stay_active_unique`, `bookings_check_out_after_checkin`, `room_blocks_end_after_start`) supaya tes membuktikan constraint yang tepat yang menolak, bukan error sembarangan. Nama CHECK dibuat deterministik lewat `DB::statement` dengan `ADD CONSTRAINT ... CHECK`, bukan nama bawaan schema builder yang tidak bisa ditebak (AGENTS.md: jangan menebak).
- Yang dikerjakan — **9 migration**:
  - `000012` room_blocks: FK room + user nullable, `CHECK (end_date > start_date)` via `DB::statement` (`room_blocks_end_after_start`), indeks `(room_id, start_date, end_date)`.
  - `000013` bookings: FK property/guest/source (**`booking_source_id` NOT NULL** — revisi ERD, BK-06), `booking_code` unik, status/payment_option, tanggal check-in/out, `total_amount decimal(12,0)`, kolom nullable (expires_at, checked_in_at, checked_out_at, cancellation_reason, cancelled_at, notes), **soft delete** `deleted_at`, `CHECK (check_out_date > check_in_date)` via `DB::statement` (`bookings_check_out_after_checkin`), indeks `(property_id, check_in_date, check_out_date)` + `status`.
  - `000014` booking_items: FK booking + room, `subtotal decimal(12,0)`.
  - `000015` booking_nights: FK booking_item + room + rate nullable, `stay_date`, `price decimal(12,0)`, `is_active`, indeks `stay_date`, dan **unique index parsial via `DB::statement`** (dilarang schema builder, sesuai tugas + aturan 4):

    ```sql
    CREATE UNIQUE INDEX booking_nights_room_stay_active_unique
    ON booking_nights (room_id, stay_date)
    WHERE is_active
    ```

  - `000016` booking_charges: FK booking, description, `amount decimal(12,0)` (diskon = negatif, keputusan terkunci di STATUS).
  - `000017` payments: FK booking + user nullable, `amount decimal(12,0)`, type/method/status, `payer_name` nullable, `payment_date`, `reference_number` nullable, `proof_path` nullable ("opsional" di ERD), indeks `booking_id`.
  - `000018` booking_status_logs: FK booking + user nullable, **`old_status` nullable** ("kosong pada log pertama"), new_status, note nullable.
  - `000019` booking_change_logs: FK booking + user nullable, change_type, old_value/new_value/reason text NOT NULL (tanpa penanda nullable di ERD).
  - `000020` indeks pencarian tamu `guests.email` + `guests.phone` — melengkapi daftar indeks arsitektur bagian 7 (tabel `guests` sendiri dari T-004; bagian 7 mewajibkan indeksnya, jadi dikirim bersama tahap ini). Tanpa unique — tamu boleh berbagi email (US-07).
- Foreign key: **semuanya NO ACTION (dampaknya = restrict, tanpa cascade) tanpa satu pun pengecualian** — tidak ada alasan untuk CASCADE di skema ini; menghapus booking yang sudah punya pembayaran/log harus gagal, bukan menghapus jejak. Dibuktikan tes: `SELECT delete_rule ... GROUP BY delete_rule` menghasilkan satu grup saja `NO ACTION`, total 29 FK (11 master T-004 + 18 transaksi T-005).
- **Tes kesesuaian skema diperluas, bukan dilonggarkan**: daftar pengecualian `erdTransactionExceptions()` **dikosongkan** dan fungsi tabel kini `erdMigratedTables()` berisi 19 tabel ERD; loop pemeriksaan kolom/tipe/nullability/unique/default otomatis mencakup 8 tabel transaksi. Diperbarui juga: daftar 29 foreign key, unique `bookings.booking_code`, dan tes uang kini menelusuri **semua** kolom `decimal` dari ERD (6 kolom: total_amount, subtotal, price nights, amount charges, amount payments, price rates — semua `numeric(12,0)`, guard `toBe(6)` anti-lulus-kosong). Tes "tabel transaksi belum ada" dihapus (tujuannya tercapai).
- Temuan jujur selama iterasi: dua tes CHECK sempat gagal bukan karena constraint, tapi karena **efek samping `RefreshDatabase`** — setelah satu pelanggaran, transaksi tes membatalkan (SQLSTATE 25P02) sehingga pelanggaran kedua tak pernah sampai ke CHECK. Diperbaiki dengan membungkus tiap pelanggaran dalam savepoint `DB::transaction` (rollback ke savepoint, transaksi induk tetap hidup); bukan melonggarkan tes — kedua varian pelanggaran tetap diuji. Setelah itu Pint merapikan gaya dua file tes (import, kutip) tanpa mengubah perilaku.
- Langkah 6 — `migrate:fresh` di database development (jalur penuh, EXIT=0):

  ```
  PS> php artisan migrate:fresh --force
   Dropping all tables .. 95.39ms DONE
   INFO Preparing database.
   Creating migration table .. 15.79ms DONE
   INFO Running migrations.
   0001_01_01_000001_create_cache_table .. 30.78ms DONE
   0001_01_01_000002_create_jobs_table .. 54.22ms DONE
   2026_10_11_000001_create_roles_table .. 10.13ms DONE
   2026_10_11_000002_create_properties_table .. 9.82ms DONE
   2026_10_11_000003_create_users_table .. 44.36ms DONE
   2026_10_11_000004_create_room_types_table .. 22.27ms DONE
   2026_10_11_000005_create_rooms_table .. 13.08ms DONE
   2026_10_11_000006_create_amenities_table .. 11.21ms DONE
   2026_10_11_000007_create_room_type_amenities_table .. 20.60ms DONE
   2026_10_11_000008_create_housekeeping_statuses_table .. 22.70ms DONE
   2026_10_11_000009_create_guests_table .. 9.88ms DONE
   2026_10_11_000010_create_booking_sources_table .. 10.51ms DONE
   2026_10_11_000011_create_rates_table .. 16.51ms DONE
   2026_10_11_000012_create_room_blocks_table .. 48.27ms DONE
   2026_10_11_000013_create_bookings_table .. 75.60ms DONE
   2026_10_11_000014_create_booking_items_table .. 155.79ms DONE
   2026_10_11_000015_create_booking_nights_table .. 32.57ms DONE
   2026_10_11_000016_create_booking_charges_table .. 10.58ms DONE
   2026_10_11_000017_create_payments_table .. 21.52ms DONE
   2026_10_11_000018_create_booking_status_logs_table .. 25.17ms DONE
   2026_10_11_000019_create_booking_change_logs_table .. 16.57ms DONE
   2026_10_11_000020_add_guest_search_indexes_to_guests_table .. 7.56ms DONE
  EXIT=0
  ```

- Hasil `\d booking_nights` (perhatikan baris kedua di Indexes: unique parsial `WHERE is_active`):

  ```
                                              Table "public.booking_nights"
       Column      |              Type              | Collation | Nullable |                  Default
  -----------------+--------------------------------+-----------+----------+--------------------------------------------
   id              | bigint                         |           | not null | nextval('booking_nights_id_seq'::regclass)
   booking_item_id | bigint                         |           | not null |
   room_id         | bigint                         |           | not null |
   rate_id         | bigint                         |           |          |
   stay_date       | date                           |           | not null |
   price           | numeric(12,0)                  |           | not null |
   is_active       | boolean                        |           | not null |
   created_at      | timestamp(0) without time zone |           |          |
   updated_at      | timestamp(0) without time zone |           |          |
  Indexes:
      "booking_nights_pkey" PRIMARY KEY, btree (id)
      "booking_nights_room_stay_active_unique" UNIQUE, btree (room_id, stay_date) WHERE is_active
      "booking_nights_stay_date_index" btree (stay_date)
  Foreign-key constraints:
      "booking_nights_booking_item_id_foreign" FOREIGN KEY (booking_item_id) REFERENCES booking_items(id)
      "booking_nights_rate_id_foreign" FOREIGN KEY (rate_id) REFERENCES rates(id)
      "booking_nights_room_id_foreign" FOREIGN KEY (room_id) REFERENCES rooms(id)
  ```

- Hasil `\d bookings` (booking_source_id not null, soft delete, CHECK, tiga indeks):

  ```
                                                Table "public.bookings"
         Column        |              Type              | Collation | Nullable |               Default
  ---------------------+--------------------------------+-----------+----------+--------------------------------------
   id                  | bigint                         |           | not null | nextval('bookings_id_seq'::regclass)
   property_id         | bigint                         |           | not null |
   guest_id            | bigint                         |           | not null |
   created_by_user_id  | bigint                         |           |          |
   booking_source_id   | bigint                         |           | not null |
   booking_code        | character varying(255)         |           | not null |
   status              | character varying(255)         |           | not null |
   payment_option      | character varying(255)         |           | not null |
   guest_count         | integer                        |           | not null |
   check_in_date       | date                           |           | not null |
   check_out_date      | date                           |           | not null |
   expires_at          | timestamp(0) without time zone |           |          |
   checked_in_at       | timestamp(0) without time zone |           |          |
   checked_out_at      | timestamp(0) without time zone |           |          |
   total_amount        | numeric(12,0)                  |           | not null |
   cancellation_reason | character varying(255)         |           |          |
   cancelled_at        | timestamp(0) without time zone |           |          |
   notes               | text                           |           |          |
   deleted_at          | timestamp(0) without time zone |           |          |
   created_at          | timestamp(0) without time zone |           |          |
   updated_at          | timestamp(0) without time zone |           |          |
  Indexes:
      "bookings_pkey" PRIMARY KEY, btree (id)
      "bookings_booking_code_unique" UNIQUE CONSTRAINT, btree (booking_code)
      "bookings_property_id_check_in_date_check_out_date_index" btree (property_id, check_in_date, check_out_date)
      "bookings_status_index" btree (status)
  Check constraints:
      "bookings_check_out_after_checkin" CHECK (check_out_date > check_in_date)
  Foreign-key constraints:
      "bookings_booking_source_id_foreign" FOREIGN KEY (booking_source_id) REFERENCES booking_sources(id)
      "bookings_created_by_user_id_foreign" FOREIGN KEY (created_by_user_id) REFERENCES users(id)
      "bookings_guest_id_foreign" FOREIGN KEY (guest_id) REFERENCES guests(id)
      "bookings_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
  Referenced by:
      TABLE "booking_change_logs" CONSTRAINT "booking_change_logs_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
      TABLE "booking_charges" CONSTRAINT "booking_charges_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
      TABLE "booking_items" CONSTRAINT "booking_items_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
      TABLE "booking_status_logs" CONSTRAINT "booking_status_logs_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
      TABLE "payments" CONSTRAINT "payments_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
  ```

- Hasil `\d payments` (uang bulat, kolom opsional nullable, indeks booking_id):

  ```
                                                Table "public.payments"
         Column        |              Type              | Collation | Nullable |               Default
  ---------------------+--------------------------------+-----------+----------+--------------------------------------
   id                  | bigint                         |           | not null | nextval('payments_id_seq'::regclass)
   booking_id          | bigint                         |           | not null |
   recorded_by_user_id | bigint                         |           |          |
   amount              | numeric(12,0)                  |           | not null |
   type                | character varying(255)         |           | not null |
   method              | character varying(255)         |           | not null |
   status              | character varying(255)         |           | not null |
   payer_name          | character varying(255)         |           |          |
   payment_date        | date                           |           | not null |
   reference_number    | character varying(255)         |           |          |
   proof_path          | character varying(255)         |           |          |
   created_at          | timestamp(0) without time zone |           |          |
   updated_at          | timestamp(0) without time zone |           |          |
  Indexes:
      "payments_pkey" PRIMARY KEY, btree (id)
      "payments_booking_id_index" btree (booking_id)
  Foreign-key constraints:
      "payments_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
      "payments_recorded_by_user_id_foreign" FOREIGN KEY (recorded_by_user_id) REFERENCES users(id)
  ```

- Seluruh suite di `kamarin_test` (sekali saat hijau pertama, sekali lagi pada kode ter-commit setelah formatter):

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":39,"passed":39,"assertions":832,"duration_ms":5892}
  EXIT=0
  PS> php artisan test   (setelah Pint)
  {"tool":"pest","result":"passed","tests":39,"passed":39,"assertions":832,"duration_ms":5623}
  EXIT=0
  ```

  39 = 24 tes lama + 8 tes kesesuaian skema (diperluas ke 19 tabel) + 7 tes constraint transaksi. Kasus uji **BK-05 lulus** (insert dua malam aktif sama ditolak database); tes "dua malam nonaktif" dan "malam nonaktif + aktif" juga lulus — membuktikan partial index hanya mengatur malam aktif, sesuai BR-01 ("Malam aktif") dan BR-01 point 3 (turnover hari yang sama).
- **Analisis yang diminta — `booking_nights.room_id` vs `booking_items.room_id`:** Ya, **bisa menyimpang**. `booking_nights.room_id` hanyalah salinan yang diisi application code (service); di database keduanya adalah foreign key lepas ke `rooms(id)`, sehingga sebuah malam bisa menunjuk room X sementara item induknya menunjuk room Y tanpa ada yang komplain. ERD sendiri menyebut kolom ini "salinan, untuk constraint" — yaitu agar unique parsial bisa menjaga (room, malam) lintas booking; bukan agar tersinkron dengan induknya. Opsi pencegahan di level database: **(A) FK gabungan** `(booking_item_id, room_id) REFERENCES booking_items(id, room_id)` dengan unique `(id, room_id)` di booking_items — deklaratif, murah (satu index), langsung menolak penyimpangan; **(B) trigger** — bekerja tapi menyembunyikan logika dari pembaca skema; **(C) CHECK subquery** — mustahil di PostgreSQL. Rekomendasi: **A**, dikerjakan di migration terpisah setelah user menyetujui (tercatat di "Pertanyaan terbuka dari agent", entri T-005). Disiplin sementara: service (T-006+).
- Formatter: `composer run lint` (Pint) memperbaiki gaya 2 file tes (import + kutip), `composer run lint:check` lulus.
- Tes: 39 lulus, 0 gagal (832 assert, Pest 4.7.8, PostgreSQL 18.6)
- File kunci: `database/migrations/2026_10_11_000012_create_room_blocks_table.php`, `000013_create_bookings_table.php`, `000014_create_booking_items_table.php`, `000015_create_booking_nights_table.php`, `000016_create_booking_charges_table.php`, `000017_create_payments_table.php`, `000018_create_booking_status_logs_table.php`, `000019_create_booking_change_logs_table.php`, `000020_add_guest_search_indexes_to_guests_table.php`, `tests/Feature/Database/TransactionSchemaTest.php`, `tests/Feature/Database/SchemaConformityTest.php`
- Penyimpangan dari dokumen sumber: tidak ada. Dua hal yang perlu diketahui user: (1) penambahan indeks tamu (`guests.email`, `guests.phone`) di migration `000020` berasal dari arsitektur bagian 7 walau tabel `guests` milik T-004 — sengaja digabung agar seluruh set indeks bagian 7 terkirim bersama tahap ini; (2) CHECK dibuat dengan `DB::statement` + nama eksplisit (bukan schema builder) supaya tes bisa membuktikan constraint tertentu yang menolak — pola yang sama dengan unique parsial yang memang wajib `DB::statement`.
- Perintah kunci dan sumber dokumentasi:
  - `CREATE UNIQUE INDEX ... WHERE is_active` — PostgreSQL partial index (www.postgresql.org/docs/current/indexes-partial.html); diwajibkan tugas + aturan keras AGENTS.md nomor 4 (BR-01 lapis 3)
  - `ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)` — PostgreSQL check constraint (www.postgresql.org/docs/current/ddl-constraints.html); CHECK tidak boleh berisi subquery (referensi yang sama), dasar penolakan opsi C
  - Savepoint uji: `DB::transaction` nested di dalam transaksi `RefreshDatabase` — transaksi bertingkat PostgreSQL (www.postgresql.org/docs/current/transaction-iso.html bagian savepoint); diverifikasi lewat kegagalan nyata 25P02 saat pengembangan
  - Indeks bagian 7: `docs/arsitektur_sistem.md` bagian "7. Basis data dan performa"; daftar indeks per tabel divalidasi tes dari `pg_indexes`
- Penjelasan untuk sidang (bagaimana dan kenapa): Tahap 2 migration menutup seluruh skema ERD — 8 tabel transaksi menyusul 11 tabel master, sehingga daftar pengecualian di tes kesesuaian skema bisa dikosongkan dan satu tes kini menjamin seluruh 19 tabel, kolom, tipe, nullability, unique, dan 29 foreign key sesuai ERD. Inti tugas ini adalah membuktikan penegakan double booking di **database**, bukan di aplikasi: unique index parsial `(room_id, stay_date) WHERE is_active` ditulis dengan `DB::statement` karena schema builder tidak bisa mengekspresikan indeks parsial, lalu sebuah tes memasukkan dua malam aktif untuk kamar dan tanggal yang sama dan memastikan PostgreSQL sendiri yang menolak (BK-05), sementara tes lain memastikan malam yang sudah tidak aktif (booking dibatalkan) tidak menghalangi booking baru pada tanggal itu. CHECK tanggal dibuat dengan nama eksplisit agar tes dapat membuktikan constraint itulah yang menolak, bukan kebetulan error lain; temuan nyata selama pengujian (transaksi tes membatalkan setelah pelanggaran pertama) diselesaikan dengan savepoint, bukan dengan melepas assertion. Keputusan foreign key tanpa cascade disengaja: menghapus booking harus selalu gagal jika masih ada pembayaran atau log yang menunjuknya, karena riwayat keuangan tidak boleh terhapus diam-diam. Satu kelemahan desain yang terbuka jujur: room_id di booking_nights bisa menyimpang dari room_id item induknya; opsi pencegahan terbaik (FK gabungan) sudah dianalisis dan menunggu persetujuan.

---

#### T-005b — Penguatan skema: FK gabungan + 12 CHECK kewajaran (2026-10-11)

- Cabang: sama, `task/T-005-transaction-migrations`, dikerjakan sebagai **commit terpisah** dari T-005. **`main` tidak disentuh** (instruksi user: jangan mengubah cabang utama). Catatan jujur: aturan itu belum ada di file `AGENTS.md` saat tugas dijalankan (file terakhir diubah di `2d1233c`); agent mengikuti instruksi dari pesan chat apa adanya.
- Kronologi: instruksi pertama merujuk baris T-005b dan paket CHECK-nya, tetapi saat itu baris itu belum ada di repo — agent **berhenti dan bertanya** (AGENTS.md: "jangan menebak", "berhenti dan tanyakan"), bukan menebak isi CHECK. User menambahkan baris T-005b ke backlog (commit `0ab3a67`), lalu tugas dikerjakan.
- Keputusan user: **opsi A** FK gabungan dengan `ON UPDATE CASCADE` + `ON DELETE NO ACTION`, ditambah paket 12 CHECK kewajaran sesuai baris T-005b. Tanpa CHECK pada `booking_charges.amount` karena diskon bernilai negatif.
- Langkah 1 — tes ditulis lebih dulu (`tests/Feature/Database/SchemaHardeningTest.php`, 18 tes) dan dijalankan sebelum migration (bukti merah):

  ```
  PS> vendor\bin\pest tests/Feature/Database/SchemaHardeningTest.php
  {"tool":"pest","result":"failed","tests":18,"passed":1,"assertions":18,"failed":17,
   "failures":[
     "it_rejects_a_night_whose_room__id_differs_from_its_parent_booking_item":
       "Exception \"Illuminate\Database\QueryException\" not thrown.",
     "it_moves_the_nights_with_their_booking_item_when_room__id_changes__BR_06_pindah_kamar_":
       "Failed asserting that two arrays are identical. - 0 => 4, 1 => 4 / + 0 => 3, 1 => 3",
     "it_rejects_moving_an_item_to_a_room_already_occupied_on_one_of_its_dates":
       "Exception \"Illuminate\Database\QueryException\" not thrown.",
     "it_gives_the_composite_foreign_key_ON_UPDATE_CASCADE_and_ON_DELETE_NO_ACTION":
       "Expecting null not to be null 'Composite foreign key is missing'.",
     ...13 data set CHECK: "Exception \"Illuminate\\Database\\QueryException\" not thrown."] }
  EXIT=1
  ```

  Satu-satunya tes hijau saat merah: `keeps booking_charges.amount unrestricted` — memang belum ada CHECK apa pun di tabel itu, jadi tes itu berfungsi menjaga agar tidak ada yang menambahkannya.

- Yang dikerjakan — migration `2026_10_11_000021_add_composite_fk_and_reasonableness_checks.php`:
  1. `unique(['id', 'room_id'])` di `booking_items`.
  2. Drop FK tunggal `booking_nights_booking_item_id_foreign`, ganti lewat `DB::statement` dengan FK gabungan bernama `booking_nights_booking_item_id_room_id_foreign`: `FOREIGN KEY (booking_item_id, room_id) REFERENCES booking_items (id, room_id) ON UPDATE CASCADE ON DELETE NO ACTION`.
  3. Dua belas CHECK lewat `DB::statement` dengan **nama eksplisit** (tes membuktikan nama ini muncul di pesan penolakan): `bookings_guest_count_positive`, `bookings_total_amount_non_negative`, `booking_items_subtotal_non_negative`, `booking_nights_price_non_negative`, `payments_amount_positive`, `rates_price_non_negative`, `rates_minimum_nights_positive`, `rates_end_date_not_before_start`, `room_types_capacity_positive`, `properties_deposit_percent_range`, `properties_pending_expiry_positive`, `properties_free_cancel_days_non_negative`.
  4. `down()` membalik semuanya: 12 `DROP CONSTRAINT`, drop FK gabungan, restore FK tunggal `booking_nights_booking_item_id_foreign` (Blueprint, tanpa klausul hapus = NO ACTION), drop unique `(id, room_id)`.
- Setelah migration: **18/18 tes T-005b hijau**, lalu seluruh suite dua kali (sebelum dan sesudah formatter):

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":57,"passed":57,"assertions":867,"duration_ms":7460}
  EXIT=0
  PS> php artisan test   (setelah Pint)
  {"tool":"pest","result":"passed","tests":57,"passed":57,"assertions":867,"duration_ms":7304}
  EXIT=0
  ```

  57 = 39 tes T-005 + 18 tes T-005b. **Kunci requirement (5):** `SchemaConformityTest` tidak diubah satu karakter pun dan tetap lulus — tidak dilonggarkan; tes FK global di `TransactionSchemaTest` juga tetap lulus: jumlah FK tetap 29 (1 lama dibuang, 1 gabungan masuk) dan **semua `delete_rule` = `NO ACTION`**, termasuk FK gabungan. FK gabungan adalah satu-satunya yang `update_rule` = `CASCADE`, divalidasi tes tersendiri.
- Bukti siklus hidup migration — `down()` bekerja, di database development:

  ```
  PS> php artisan migrate:fresh --force
   2026_10_11_000019_create_booking_change_logs_table .. 16.16ms DONE
   2026_10_11_000020_add_guest_search_indexes_to_guests_table .. 8.38ms DONE
   2026_10_11_000021_add_composite_fk_and_reasonableness_checks .. 57.25ms DONE
  FRESH_EXIT=0
  PS> php artisan migrate:rollback --step=3 --force
   INFO Rolling back migrations.
   2026_10_11_000021_add_composite_fk_and_reasonableness_checks .. 57.54ms DONE
   2026_10_11_000020_add_guest_search_indexes_to_guests_table .. 7.96ms DONE
   2026_10_11_000019_create_booking_change_logs_table .. 12.81ms DONE
  ROLLBACK_EXIT=0
  PS> php artisan migrate --force
   INFO Running migrations.
   2026_10_11_000019_create_booking_change_logs_table .. 42.03ms DONE
   2026_10_11_000020_add_guest_search_indexes_to_guests_table .. 12.15ms DONE
   2026_10_11_000021_add_composite_fk_and_reasonableness_checks .. 48.94ms DONE
  MIGRATE_EXIT=0
  ```

- Hasil `\d booking_items` — unique `(id, room_id)`, CHECK subtotal, dan "Referenced by" FK gabungan dengan `ON UPDATE CASCADE`:

  ```
                                            Table "public.booking_items"
     Column   |              Type              | Collation | Nullable |                  Default
  ------------+--------------------------------+-----------+----------+-------------------------------------------
   id         | bigint                         |           | not null | nextval('booking_items_id_seq'::regclass)
   booking_id | bigint                         |           | not null |
   room_id    | bigint                         |           | not null |
   subtotal   | numeric(12,0)                  |           | not null |
   created_at | timestamp(0) without time zone |           |          |
   updated_at | timestamp(0) without time zone |           |          |
  Indexes:
      "booking_items_pkey" PRIMARY KEY, btree (id)
      "booking_items_id_room_id_unique" UNIQUE CONSTRAINT, btree (id, room_id)
  Check constraints:
      "booking_items_subtotal_non_negative" CHECK (subtotal >= 0::numeric)
  Foreign-key constraints:
      "booking_items_booking_id_foreign" FOREIGN KEY (booking_id) REFERENCES bookings(id)
      "booking_items_room_id_foreign" FOREIGN KEY (room_id) REFERENCES rooms(id)
  Referenced by:
      TABLE "booking_nights" CONSTRAINT "booking_nights_booking_item_id_room_id_foreign" FOREIGN KEY (booking_item_id, room_id) REFERENCES booking_items(id, room_id) ON UPDATE CASCADE
  ```

- Hasil `\d booking_nights` — FK gabungan tanpa klausul hapus (= NO ACTION), CHECK price, indeks parsial utuh:

  ```
                                              Table "public.booking_nights"
       Column      |              Type              | Collation | Nullable |                  Default
  -----------------+--------------------------------+-----------+----------+--------------------------------------------
   id              | bigint                         |           | not null | nextval('booking_nights_id_seq'::regclass)
   booking_item_id | bigint                         |           | not null |
   room_id         | bigint                         |           | not null |
   rate_id         | bigint                         |           |          |
   stay_date       | date                           |           | not null |
   price           | numeric(12,0)                  |           | not null |
   is_active       | boolean                        |           | not null |
   created_at      | timestamp(0) without time zone |           |          |
   updated_at      | timestamp(0) without time zone |           |          |
  Indexes:
      "booking_nights_pkey" PRIMARY KEY, btree (id)
      "booking_nights_room_stay_active_unique" UNIQUE, btree (room_id, stay_date) WHERE is_active
      "booking_nights_stay_date_index" btree (stay_date)
  Check constraints:
      "booking_nights_price_non_negative" CHECK (price >= 0::numeric)
  Foreign-key constraints:
      "booking_nights_booking_item_id_room_id_foreign" FOREIGN KEY (booking_item_id, room_id) REFERENCES booking_items(id, room_id) ON UPDATE CASCADE
      "booking_nights_rate_id_foreign" FOREIGN KEY (rate_id) REFERENCES rates(id)
      "booking_nights_room_id_foreign" FOREIGN KEY (room_id) REFERENCES rooms(id)
  ```

- Enam tes wajib terpenuhi: (1) malam dengan room_id berbeda dari item induknya ditolak `booking_nights_booking_item_id_room_id_foreign`; (2) mengubah `booking_items.room_id` memindahkan malam-malamnya (dua malam ikut ke room B, terbukti lewat SELECT); (3) pindah ke kamar yang sudah terisi pada 10 Juni ditolak `booking_nights_room_stay_active_unique`; (4) 13 kasus dataset, tiap CHECK menolak nilai buruk dengan nama constraint yang benar; (5) kesesuaian skema lulus tanpa diubah + semua FK `delete_rule` NO ACTION.
- Catatan penyimpangan: 12 CHECK tidak ada di penanda ERD (ERD hanya menandai CHECK tanggal) — ditambahkan sesuai baris T-005b, yaitu persetujuan eksplisit user; tidak ada tabel atau kolom yang berubah dan ERD tidak diedit.
- Formatter: Pint merapikan 1 file tes (FQ class + urutan import), `composer run lint:check` lulus.
- Tes: 57 lulus, 0 gagal (867 assert, Pest 4.7.8, PostgreSQL 18.6)
- File kunci: `database/migrations/2026_10_11_000021_add_composite_fk_and_reasonableness_checks.php`, `tests/Feature/Database/SchemaHardeningTest.php`
- Perintah kunci dan sumber dokumentasi:
  - FK gabungan dan `ON UPDATE CASCADE` — PostgreSQL foreign keys (www.postgresql.org/docs/current/ddl-constraints.html); larangan subquery di CHECK (referensi yang sama) tetap dasar opsi C gugur
  - `ALTER TABLE ... ADD CONSTRAINT ... CHECK` — PostgreSQL check constraints (referensi yang sama); nama eksplisit agar tes bisa membuktikan constraint yang tepat menolak
  - `migrate:rollback --step=N` — Laravel 13 database migrations (laravel.com/docs/13.x/migrations)
  - Daftar isi CHECK dan keputusan opsi A — `docs/tasks/BACKLOG_FASE4_5.md` baris T-005b (keputusan user 2026-10-11)
- Penjelasan untuk sidang (bagaimana dan kenapa): T-005b menutup satu-satunya celah desain yang diakui jujur di T-005 — malam yang room_id-nya bisa menyimpang dari item induknya. FK gabungan `(booking_item_id, room_id)` membuat penyimpangan mustahil di level database tanpa trigger, sementara `ON UPDATE CASCADE` membuat pindah kamar (BR-06) cukup satu UPDATE pada `booking_items`: malam-malamnya ikut berpindah, lalu unique parsial langsung menolak kalau kamar tujuan sudah terisi pada salah satu tanggalnya. Dua pengaman yang tadinya hanya saling melengkapi kini saling mengunci — FK menjaga "malam selalu milik item yang benar", unique parsial menjaga "satu kamar satu malam aktif". Dua belas CHECK kewajaran memindahkan validasi masuk akal (uang tidak negatif, kapasitas positif, batas DP 0–100, tanggal tarif berurutan) dari aplikasi ke database, dengan nama constraint eksplisit sehingga setiap penolakan bisa dibuktikan tes persis constraint mana yang bekerja. Semua dibalik rapi oleh `down()`, yang diuji sungguhan lewat rollback tiga langkah lalu migrate ulang.

### T-004 Migration tahap 1 (master) dan tes kesesuaian skema (2026-10-11)

- Cabang: `task/T-004-master-migrations`, dari `main` (`2d1233c`, squash-merge T-002 yang sudah memuat keputusan user: chisel dicopot, sail dipertahankan) + commit arsip `026c9d5`
- Status: selesai
- Langkah 1 — **tes kesesuaian skema ditulis lebih dulu** (`tests/Feature/Database/SchemaConformityTest.php`), lalu dijalankan sebelum ada migration (bukti merah):

  ```
  PS> vendor\bin\pest tests/Feature/Database/SchemaConformityTest.php
  {"tool":"pest","result":"failed","tests":9,"passed":3,"assertions":29,"duration_ms":10019,"failed":4,"errors":2,"failures":[
    "...it_creates_every_master_table_from_the_ERD...","message":"Expecting Collection not to be empty 'Table [roles] does not exist ...'",
    "...it_keeps_the_users_table_in_sync...","message":"'is_active' ... 'property_id' ... 'role_id' hilang dari users",
    "...it_enforces_the_unique_constraints_marked_in_the_ERD","message":"unique [roles.name] tidak ada",
    "...it_creates_the_foreign_keys_declared_in_the_ERD","message":"'users.role_id->roles' tidak ada"] }
  ```

  (potongan; 6 gagal/berantah karena tabel belum ada, 3 lulus — yaitu tes "tolak tabel/kolom di luar ERD" dan "tabel transaksi belum boleh ada", yang kebetulan lulus saat DB masih kosong). Dua bug pada kode tesnya sendiri juga ditemukan dan diperbaiki saat fase merah ini: regex parser ERD belum menangani penanda `PK`/`UK`/`FK` tanpa tanda kutip, dan `pgColumns()` salah menyetel tipe kembalian.
- Yang dikerjakan:
  - **10 migration baru**: `2026_10_11_000001` roles, `000002` properties (+ kolom kebijakan dengan default ERD), `000004` room_types, `000005` rooms (+ unique `(property_id, room_number)`), `000006` amenities, `000007` room_type_amenities (+ unique gabungan), `000008` housekeeping_statuses (+ unique `room_id` 1:1), `000009` guests (soft delete, `id_type`/`id_number` nullable), `000010` booking_sources, `000011` rates (`price decimal(12,0)`, `days_of_week jsonb`, `start_date`/`end_date` nullable).
  - **Migration `users` dimodifikasi di tempat** (diizinkan backlog, belum ada data produksi) dan **namanya diganti** dari `0001_01_01_000000_...` menjadi `2026_10_11_000003_create_users_table.php` — alasannya urutan: `role_id` (FK not null ke `roles`) dan `property_id` (FK nullable ke `properties`) mensyaratkan kedua tabel itu dimigrasi lebih dulu. `password_reset_tokens` dan `sessions` ikut pindah file bersama file users (netral, tidak bergantung apa pun). Kolom tambahan: `is_active` boolean not null. Kolom bawaan `email_verified_at`, `remember_token`, timestamps dipertahankan (diizinkan aturan 13).
  - Model `Role` + `RoleFactory` dibuat; `UserFactory` diperbarui: `role_id => Role::factory()`, `property_id => null`, `is_active => true` — tanpa ini 24 tes lama gagal semua karena `role_id` not null FK. Nama role sebenarnya menyusul di seeder T-007.
  - Catatan uang (backlog: "decimal(12,0) dengan cast integer di model"): kolom `rates.price` = `numeric(12,0)` sudah dibuat dan diverifikasi tes; **cast integer di model dilakukan di T-005+** saat model `Rate` diperkenalkan — belum ada model domain di T-004, sengaja tidak dibuat berlebihan.
- **Daftar pengecualian eksplisit** (disimpan di `erdTransactionExceptions()` dalam tes, dikosongkan di T-005): `room_blocks`, `bookings`, `booking_items`, `booking_nights`, `booking_charges`, `payments`, `booking_status_logs`, `booking_change_logs`. Tes `has not migrated the transaction tables yet` memastikan kedelapan tabel ini **belum ada** — kalau ada yang terlanjur dibuat, tes gagal.
- Iterasi merah → hijau menemukan 5 bug nyata pada migration: `roles`/`amenities`/`booking_sources` lupa `timestamps()` (padahal kepala ERD mensyaratkan created_at + updated_at di semua tabel), dan parser tes harus mengenali tiga penanda nullable ERD: `"nullable"`, `"opsional"` (guests.id_type), `"soft delete"` (guests.deleted_at).
- Langkah 6 — `migrate:fresh` di database development:

  ```
  PS> php artisan migrate:fresh --force
   Dropping all tables .. 158.99ms DONE
   INFO Running migrations.
   0001_01_01_000001_create_cache_table .. 77.86ms DONE
   0001_01_01_000002_create_jobs_table .. 106.09ms DONE
   2026_10_11_000001_create_roles_table .. 35.77ms DONE
   2026_10_11_000002_create_properties_table .. 63.29ms DONE
   2026_10_11_000003_create_users_table .. 157.81ms DONE
   2026_10_11_000004_create_room_types_table .. 65.02ms DONE
   2026_10_11_000005_create_rooms_table .. 48.30ms DONE
   2026_10_11_000006_create_amenities_table .. 21.97ms DONE
   2026_10_11_000007_create_room_type_amenities_table .. 62.69ms DONE
   2026_10_11_000008_create_housekeeping_statuses_table .. 42.39ms DONE
   2026_10_11_000009_create_guests_table .. 26.02ms DONE
  ```

  Catatan jujur: keluaran run pertama terpotong oleh perintah tampilan (`Select-Object -First`) yang mematikan pipeline di tengah, sehingga `000010` dan `000011` sempat berstatus Pending; dilanjutkan dan selesai:

  ```
  PS> php artisan migrate --force
   INFO Running migrations.
   2026_10_11_000010_create_booking_sources_table .. 47.19ms DONE
   2026_10_11_000011_create_rates_table .. 45.09ms DONE
  EXIT=0
  PS> php artisan migrate:status   (13 baris: semua "Ran", 0 "Pending")
  ```

- Hasil `\d` tabel kunci di database development (persis ERD v2.1):

  ```
  Table "public.users"
      Column       |              Type              | Collation | Nullable |              Default
  -------------------+--------------------------------+-----------+----------+-----------------------------------
   id                | bigint                         |           | not null | nextval('users_id_seq'::regclass)
   role_id           | bigint                         |           | not null |
   property_id       | bigint                         |           |          |
   name              | character varying(255)         |           | not null |
   email             | character varying(255)         |           | not null |
   password          | character varying(255)         |           | not null |
   is_active         | boolean                        |           | not null |
   email_verified_at | timestamp(0) without time zone |           |          |
   remember_token    | character varying(100)         |           |          |
   created_at        | timestamp(0) without time zone |           |          |
   updated_at        | timestamp(0) without time zone |           |          |
  Indexes:
      "users_pkey" PRIMARY KEY, btree (id)
      "users_email_unique" UNIQUE CONSTRAINT, btree (email)
  Foreign-key constraints:
      "users_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
      "users_role_id_foreign" FOREIGN KEY (role_id) REFERENCES roles(id)

  Table "public.rooms"
      Column    |              Type              | Collation | Nullable |              Default
  --------------+--------------------------------+-----------+----------+-----------------------------------
   id           | bigint                         |           | not null | nextval('rooms_id_seq'::regclass)
   property_id  | bigint                         |           | not null |
   room_type_id | bigint                         |           | not null |
   room_number  | character varying(255)         |           | not null |
   floor        | integer                        |           | not null |
   status       | character varying(255)         |           | not null |
   created_at   | timestamp(0) without time zone |           |          |
   updated_at   | timestamp(0) without time zone |           |          |
  Indexes:
      "rooms_pkey" PRIMARY KEY, btree (id)
      "rooms_property_id_room_number_unique" UNIQUE CONSTRAINT, btree (property_id, room_number)
  Foreign-key constraints:
      "rooms_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
      "rooms_room_type_id_foreign" FOREIGN KEY (room_type_id) REFERENCES room_types(id)

  Table "public.rates"
       Column     |              Type              | Collation | Nullable |              Default
  ----------------+--------------------------------+-----------+----------+-----------------------------------
   id             | bigint                         |           | not null | nextval('rates_id_seq'::regclass)
   property_id    | bigint                         |           | not null |
   room_type_id   | bigint                         |           | not null |
   name           | character varying(255)         |           | not null |
   price          | numeric(12,0)                  |           | not null |
   start_date     | date                           |           |          |
   end_date       | date                           |           |          |
   days_of_week   | jsonb                          |           |          |
   priority       | integer                        |           | not null |
   is_default     | boolean                        |           | not null |
   minimum_nights | integer                        |           | not null |
   created_at     | timestamp(0) without time zone |           |          |
   updated_at     | timestamp(0) without time zone |           |          |
  Foreign-key constraints:
      "rates_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
      "rates_room_type_id_foreign" FOREIGN KEY (room_type_id) REFERENCES room_types(id)

  Table "public.properties"
          Column        |              Type              | Collation | Nullable |                Default
  ----------------------+--------------------------------+-----------+----------+----------------------------------------
   id                   | bigint                         |           | not null | nextval('properties_id_seq'::regclass)
   name                 | character varying(255)         |           | not null |
   address              | text                           |           | not null |
   city                 | character varying(255)         |           | not null |
   phone                | character varying(255)         |           | not null |
   check_in_time        | time(0) without time zone      |           | not null | '14:00:00'::time without time zone
   check_out_time       | time(0) without time zone      |           | not null | '12:00:00'::time without time zone
   deposit_percent      | smallint                       |           | not null | '50'::smallint
   pending_expiry_hours | smallint                       |           | not null | '24'::smallint
   free_cancel_days     | smallint                       |           | not null | '2'::smallint
   no_show_cutoff_time  | time(0) without time zone      |           | not null | '22:00:00'::time without time zone
   created_at           | timestamp(0) without time zone |           |          |
   updated_at           | timestamp(0) without time zone |           |          |
  ```

- Seluruh suite di `kamarin_test` (dijalankan dua kali: saat hijau pertama dan sekali lagi pada kode ter-commit):

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":33,"passed":33,"assertions":456,"duration_ms":8291}
  EXIT=0
  ```

  33 = 24 tes lama (login, logout, reset password, settings, dashboard — semuanya tetap lulus dengan factory baru) + 9 tes kesesuaian skema.
- Formatter: `composer run lint` dan `composer run lint:check` lulus (`{"tool":"pint","result":"passed"}`), tanpa perubahan yang diperlukan.
- Tes: 33 lulus, 0 gagal (456 assert, Pest 4.7.8, PostgreSQL 18.6)
- File kunci: `tests/Feature/Database/SchemaConformityTest.php`, `database/migrations/2026_10_11_000001_create_roles_table.php` sampai `000011_create_rates_table.php`, `database/migrations/2026_10_11_000003_create_users_table.php` (rename + edit), `app/Models/Role.php`, `app/Models/User.php`, `database/factories/RoleFactory.php`, `database/factories/UserFactory.php`
- Penyimpangan dari dokumen sumber: tidak ada. Tabel transaksi yang sengaja belum dimigrasi dicatat sebagai daftar pengecualian eksplisit sesuai instruksi backlog ("dikosongkan di T-005"). Satu pertanyaan terbuka: `housekeeping_statuses.notes` NOT NULL per konvensi ERD (bagian Pertanyaan terbuka, entri T-004)
- Perintah kunci dan sumber dokumentasi:
  - Parsing ERD + `information_schema`: skrip uji Pest biasa (`DB::select`), tanpa paket tambahan; aturan keras AGENTS.md nomor 1 (ERD = sumber kebenaran skema) dan nomor 5 (uang bulat, decimal)
  - `decimal('price', 12, 0)` — Laravel Blueprint decimal precision/scale; BR bagian 1 "Mata uang: Rupiah (IDR), bilangan bulat tanpa desimal"
  - `foreignId(...)->constrained(...)` untuk FK bigint; unique gabungan `$table->unique([...])` — Laravel Schema Builder (laravel.com/docs/13.x/migrations)
- Penjelasan untuk sidang (bagaimana dan kenapa): Tahap 1 migration membangun seluruh data master (role, properti beserta kebijakannya, tipe kamar, kamar, fasilitas, housekeeping, tamu, sumber booking, tarif) persis mengikuti ERD v2.1, sementara tabel transaksi ditunda ke T-005 dan dicatat dalam daftar pengecualian yang terbukti lewat tes, bukan sekadar janji di dokumen. Yang membedakan tugas ini dari migration biasa adalah tes kesesuaian skema: file ERD di-parsing otomatis lalu dibandingkan dengan `information_schema` PostgreSQL, sehingga selisih apa pun — tabel hilang, kolom salah tipe, nullable meleset, unique/foreign key lupa — langsung menggagalkan suite dan tidak bisa terbawa ke Fase berikutnya. Migration users yang lama dimodifikasi di tempat dan namanya diganti agar urutannya benar (roles dan properties harus ada sebelum users memakai keduanya), yang dimungkinkan karena belum ada data produksi. Selama pengembangan, tes merah ini dua kali menangkap kesalahan nyata (lupa timestamps di tiga tabel; penanda nullable ERD yang beragam kata), yang membuktikan pengamannya bekerja. Karena registrasi publik sudah dibuang di T-002, satu-satunya cara membuat user di tes adalah factory — karena itu `UserFactory` kini menempelkan role lewat factory baru, dan seeder berisi nama role asli menyusul di T-007.
- **Amandemen 2026-10-11 — ERD direvisi user (nullable dirapikan, default expiry 3 jam):**
  - Perubahan ERD (commit `379f180`): 9 kolom master ditandai nullable — `roles.description`, `properties.phone`, `room_types.description`, `rooms.floor`, `amenities.icon`, `housekeeping_statuses.notes`, `guests.email` ("nullable, kosong untuk sebagian booking OTA"), `guests.phone`, `guests.country` — dan `properties.pending_expiry_hours` default 24 → **3**. ERD juga menandai ulang tabel transaksi (**catatan untuk T-005**): `bookings.booking_source_id` kini **wajib** (penanda nullable dilepas), sementara `bookings.notes`, `payments.payer_name`, `payments.reference_number`, `booking_status_logs.old_status`, dan `booking_status_logs.note` ditandai nullable. Karena tabel transaksi belum dimigrasi di T-004, perubahan ini belum berdampak ke skema — harus dipakai saat migration T-005 ditulis.
  - Migration diedit di tempat (commit `3177e2c`): `->nullable()` pada 9 kolom di atas dan `pending_expiry_hours` default menjadi 3. Belum ada data produksi, jadi tidak ada migration tambahan.
  - Tes **dikencangkan, bukan dilonggarkan** — tiga perbaikan sekaligus, dua di antaranya menangkap bug nyata:
    1. Tes default kini **ERD-driven**: parser mengekstrak marker `default X` dari file ERD lalu memeriksa setiap kolom master yang punya default di database. Angka hardcoded (`14:00`, `50`, `24`, dst.) dihapus dari tes, jadi revisi default berikutnya otomatis ikut teruji. Guard `expect($checked)->toBe(6)` mencegah tes lulus tanpa memeriksa apa pun.
    2. Unique `housekeeping_statuses.room_id` ("unique, 1:1 dengan room" di ERD) masuk daftar periksa → **membuka bug nyata pada migration asli**: `foreignId('room_id')->constrained('rooms')->unique()` tidak pernah membuat unique index, karena `constrained()` mengembalikan objek definisi FK sehingga `->unique()` setelahnya menjadi atribut percuma di FK, bukan modifier kolom. Diperbaiki menjadi `->unique()->constrained('rooms')`.
    3. Bug API Pest yang sama seperti fase awal tugas (`toContain` tidak menerima argumen pesan) muncul lagi di tes baru dan diperbaiki dengan `str_contains` + `toBeTrue(pesan)`.
  - Jalur merah → hijau terhadap ERD baru: run pertama **gagal 3 tes** (`notes` masih NOT NULL di database tes, unique `room_id` tidak ada, tes default belum cocok) — bukti pengaman bereaksi terhadap perubahan skema; setelah perbaikan, 9/9 tes skema lulus.
  - `migrate:fresh` di database development (jalur penuh, EXIT=0 — run pertama kali di tugas ini tanpa terpotong pipe tampilan):

    ```
    PS> php artisan migrate:fresh --force
     Dropping all tables .. 134.68ms DONE
     INFO Preparing database.
     Creating migration table .. 18.70ms DONE
     INFO Running migrations.
     0001_01_01_000001_create_cache_table .. 31.00ms DONE
     0001_01_01_000002_create_jobs_table .. 36.48ms DONE
     2026_10_11_000001_create_roles_table .. 11.13ms DONE
     2026_10_11_000002_create_properties_table .. 8.73ms DONE
     2026_10_11_000003_create_users_table .. 52.64ms DONE
     2026_10_11_000004_create_room_types_table .. 14.81ms DONE
     2026_10_11_000005_create_rooms_table .. 51.44ms DONE
     2026_10_11_000006_create_amenities_table .. 33.50ms DONE
     2026_10_11_000007_create_room_type_amenities_table .. 21.01ms DONE
     2026_10_11_000008_create_housekeeping_statuses_table .. 19.94ms DONE
     2026_10_11_000009_create_guests_table .. 8.12ms DONE
     2026_10_11_000010_create_booking_sources_table .. 10.11ms DONE
     2026_10_11_000011_create_rates_table .. 17.94ms DONE
    EXIT=0
    ```

  - Hasil `\d properties` (perubahan: `phone` nullable, `pending_expiry_hours` default `'3'::smallint`):

    ```
                                               Table "public.properties"
            Column        |              Type              | Collation | Nullable |                Default
    ----------------------+--------------------------------+-----------+----------+----------------------------------------
     id                   | bigint                         |           | not null | nextval('properties_id_seq'::regclass)
     name                 | character varying(255)         |           | not null |
     address              | text                           |           | not null |
     city                 | character varying(255)         |           | not null |
     phone                | character varying(255)         |           |          |
     check_in_time        | time(0) without time zone      |           | not null | '14:00:00'::time without time zone
     check_out_time       | time(0) without time zone      |           | not null | '12:00:00'::time without time zone
     deposit_percent      | smallint                       |           | not null | '50'::smallint
     pending_expiry_hours | smallint                       |           | not null | '3'::smallint
     free_cancel_days     | smallint                       |           | not null | '2'::smallint
     no_show_cutoff_time  | time(0) without time zone      |           | not null | '22:00:00'::time without time zone
     created_at           | timestamp(0) without time zone |           |          |
     updated_at           | timestamp(0) without time zone |           |          |
    Indexes:
        "properties_pkey" PRIMARY KEY, btree (id)
    Referenced by:
        TABLE "rates" CONSTRAINT "rates_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
        TABLE "room_types" CONSTRAINT "room_types_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
        TABLE "rooms" CONSTRAINT "rooms_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
        TABLE "users" CONSTRAINT "users_property_id_foreign" FOREIGN KEY (property_id) REFERENCES properties(id)
    ```

  - Hasil `\d guests` (`email`, `phone`, `country` kini nullable — hanya `name` yang wajib selain kolom sistem):

    ```
                                              Table "public.guests"
       Column   |              Type              | Collation | Nullable |              Default
    ------------+--------------------------------+-----------+----------+------------------------------------
     id         | bigint                         |           | not null | nextval('guests_id_seq'::regclass)
     name       | character varying(255)         |           | not null |
     email      | character varying(255)         |           |          |
     phone      | character varying(255)         |           |          |
     country    | character varying(255)         |           |          |
     id_type    | character varying(255)         |           |          |
     id_number  | character varying(255)         |           |          |
     deleted_at | timestamp(0) without time zone |           |          |
     created_at | timestamp(0) without time zone |           |          |
     updated_at | timestamp(0) without time zone |           |          |
    Indexes:
        "guests_pkey" PRIMARY KEY, btree (id)
    ```

  - Hasil `\d housekeeping_statuses` (`notes` nullable; unique `room_id` kini benar-benar terbentuk setelah perbaikan urutan fluent):

    ```
                                              Table "public.housekeeping_statuses"
           Column       |              Type              | Collation | Nullable |              Default
    --------------------+--------------------------------+-----------+----------+------------------------------------
     id                 | bigint                         |           | not null | nextval('housekeeping_statuses_id_seq'::regclass)
     room_id            | bigint                         |           | not null |
     updated_by_user_id | bigint                         |           |          |
     status             | character varying(255)         |           | not null |
     notes              | text                           |           |          |
     created_at         | timestamp(0) without time zone |           |          |
     updated_at         | timestamp(0) without time zone |           |          |
    Indexes:
        "housekeeping_statuses_pkey" PRIMARY KEY, btree (id)
        "housekeeping_statuses_room_id_unique" UNIQUE CONSTRAINT, btree (room_id)
    Foreign-key constraints:
        "housekeeping_statuses_room_id_foreign" FOREIGN KEY (room_id) REFERENCES rooms(id)
        "housekeeping_statuses_updated_by_user_id_foreign" FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
    ```

  - Seluruh suite di `kamarin_test` setelah amandemen:

    ```
    PS> php artisan test
    {"tool":"pest","result":"passed","tests":33,"passed":33,"assertions":458,"duration_ms":4846}
    EXIT=0
    PS> composer run lint:check
    {"tool":"pint","result":"passed"}
    EXIT=0
    ```

  - Keputusan user atas pertanyaan T-004: **`notes` dibuat nullable** (selesai; ERD dan migration selaras, tidak ada pertanyaan tersisa dari tugas ini).

### T-002 Pruning autentikasi starter kit (2026-10-11)

- Cabang: `task/T-002-auth-cleanup`. Catatan: saat tugas diminta, `main` ternyata belum memuat T-003; agent melakukan fast-forward `main` ke tip T-003 (linier, tanpa konflik) lalu membuat cabang dari situ. Dua berkas edit user (`AGENTS.md`, backlog T-002) ikut ter-commit sebagai `7dfee80`.
- Status: selesai
- Langkah 2 — pemeriksaan `laravel/chisel` (dari fakta, bukan ingatan):

  ```
  PS> Test-Path "config\chisel.php"
  False
  PS> php artisan list | Select-String chisel
  (tidak ada output)
  PS> Get-ChildItem "vendor\laravel\chisel" -Name
  src
  composer.json
  LICENSE.md
  ```

  Dokumentasi resmi dibaca di README paket (github.com/laravel/chisel, branch main, 2026-10-11). Kutipan kunci: *"Compatible starter kits include a `chisel.php` script that defines the optional features and the file mutations needed to remove them"* dan *"an external process, such as an Artisan command, is responsible for rendering them and passing the answers to Chisel's `chisel()` method"*. Kesimpulan: chisel adalah toolkit untuk **membangun** script pruning, bukan perintah siap pakai; scaffold ini tidak menyertakan `chisel.php` maupun command pemanggil, jadi **pruning dilakukan manual** mengikuti dokumentasi resmi Starter Kits (laravel.com/docs/13.x/starter-kits, bagian "Enabling and Disabling Features") dan aturan 13 AGENTS.md. (Laravel/chisel sendiri dilaporkan di "Pertanyaan terbuka", tidak dihapus tanpa konfirmasi.)
- Langkah 3/4 — pruning manual, commit terpisah agar diff mudah dibaca: `8fa8bcd refactor(auth): remove registration, email verification, two-factor and passkeys` (39 file, +15/−2.224):
  - Backend: `config/fortify.php` — features tinggal `Features::resetPasswords()` (registration, emailVerification, twoFactorAuthentication, passkeys dibuang; blok konfigurasi `passkeys` dan limiters `two-factor`/`passkeys` ikut dibuang). `FortifyServiceProvider` — `createUsersUsing`, view register/verify-email/two-factor-challenge, dan rate limiter `two-factor`/`passkeys` dibuang. Dihapus: `CreateNewUser.php`, `TwoFactorAuthenticationRequest.php`, route `.well-known/passkey-endpoints`. `User` — `implements MustVerifyEmail, PasskeyUser` dan trait `PasskeyAuthenticatable, TwoFactorAuthenticatable` dibuang. `SecurityController` — props 2FA/passkeys dibuang (tinggal `passwordRules`). `ProfileController` — share `mustVerifyEmail`/`status` dan pen-nullan `email_verified_at` saat email berubah dibuang. Middleware `'verified'` dilepas dari `routes/web.php` dan `routes/settings.php`.
  - Migration: `2024_01_01_000000_create_passkeys_table.php` dan `2025_08_14_170933_add_two_factor_columns_to_users_table.php` dihapus (aturan 13: fitur dibuang termasuk migrationnya).
  - Frontend: halaman `register.tsx`, `two-factor-challenge.tsx`, `verify-email.tsx`; komponen `manage-passkeys`, `manage-two-factor`, `passkey-item`, `passkey-register`, `passkey-verify`, `two-factor-recovery-codes`, `two-factor-setup-modal`, `ui/input-otp` (yatim setelah dependensinya dilepas); hook `use-two-factor-auth.ts`. Referensi rute/fitur dihapus dari `login.tsx` (link Sign up + PasskeyVerify), `welcome.tsx` (tombol Register), `confirm-password.tsx` (opsi passkey), `profile.tsx` (banner verifikasi email), `security.tsx` (blok 2FA + passkeys), `types/auth.ts`.
  - npm: `npm uninstall @laravel/passkeys input-otp` (dependensi tak terpakai ikut dibuang, sesuai pola `Chisel::npm()->remove()`).
  - `UserFactory` — kolom `two_factor_*` dihapus dari `definition()` (wajib: kolom sudah tidak ada) dan state `withTwoFactor()` dihapus.
- Langkah 5 — tes: 4 file tes fitur terbuang dihapus (**bukan dilewati**): `RegistrationTest` (2 tes), `EmailVerificationTest` (7), `VerificationNotificationTest` (2), `TwoFactorChallengeTest` (2). Disesuaikan: `AuthenticationTest` (tes redirect 2FA dihapus), `ProfileUpdateTest` (tes status verifikasi email dihapus + assert `email_verified_at` null dibuang), `SecurityTest` ditulis ulang 5→4 tes (2 tes 2FA/passkey dihapus, tes tampilan halaman disesuaikan, 2 tes ubah password dipertahankan). Total 40 → 24 tes (13 + 1 + 1 + 1 = 16 dibuang/disesesuaikan). Helper `skipUnlessFortifyHas` dipertahankan karena masih dipakai `PasswordResetTest` (fitur yang dipertahankan). Karena registrasi publik tidak ada, akun hanya bisa dibuat lewat factory/seeder (T-007); alur login/logout tetap diverifikasi otomatis: `test_users_can_authenticate_using_the_login_screen`, `test_users_can_logout`, `test_users_can_not_authenticate_with_invalid_password` — semua lulus.
- Langkah 6 — `migrate:fresh` di database development:

  ```
  PS> php artisan migrate:fresh --force
   Dropping all tables .. 105.70ms DONE
   INFO Preparing database.
   Creating migration table .. 27.25ms DONE
   INFO Running migrations.
   0001_01_01_000000_create_users_table .. 175.78ms DONE
   0001_01_01_000001_create_cache_table .. 25.43ms DONE
   0001_01_01_000002_create_jobs_table .. 42.11ms DONE
  EXIT=0
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin -c "\d users"
                                               Table "public.users"
        Column       |              Type              | Collation | Nullable |              Default
  -------------------+--------------------------------+-----------+----------+-----------------------------------
   id                | bigint                         |           | not null | nextval('users_id_seq'::regclass)
   name              | character varying(255)         |           | not null |
   email             | character varying(255)         |           | not null |
   email_verified_at | timestamp(0) without time zone |           |          |
   password          | character varying(255)         |           | not null |
   remember_token    | character varying(100)         |           |          |
   created_at        | timestamp(0) without time zone |           |          |
   updated_at        | timestamp(0) without time zone |           |          |
  ```

  Pencocokan dengan aturan 13: **dibuang** — `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` (migration dihapus) dan tabel `passkeys`. **Dipertahankan** — `email_verified_at`, `remember_token`, `created_at`, `updated_at` (kolom bawaan `users` yang secara eksplisit diizinkan aturan 13 di luar ERD; fitur verifikasi email sendiri sudah nonaktif, kolomnya hanya sisa netral). Framework tables (`migrations`, `cache`, `jobs`, `sessions`, `password_reset_tokens`) tetap diizinkan aturan 13.
- Langkah 7 — `.github/workflows/tests.yml` diubah menjadi `workflow_dispatch` saja (push/pull_request dibuang, tanpa service baru): commit `515dd7d ci(tests): trigger test workflow manually only`.
- Langkah 8 — `laravel/chisel` dicopot via `composer remove laravel/chisel` atas persetujuan dan keputusan user (2026-10-11). Keputusan user dicatat:
  - Hapus `laravel/chisel` dari `composer.json` (selesai dieksekusi).
  - `laravel/sail` tetap dipertahankan di dev dependencies.
  - Kegagalan `npm run types:check` (3 error pre-existing di wayfinder generated file) diabaikan sampai T-015.
  - Dokumen di folder `docs/` tidak diformat otomatis oleh `npm run check:fix` agar format dokumen sumber tetap terjaga.
- Verifikasi akhir (dijalankan mandiri setelah pencopotan `laravel/chisel`):

  ```
  PS> composer remove laravel/chisel
  ./composer.json has been updated
  Running composer update laravel/chisel
  Loading composer repositories with package information
  Updating dependencies
  Lock file operations: 0 installs, 0 updates, 1 removal
    - Removing laravel/chisel (v0.1.1)
  Writing lock file
  Installing dependencies from lock file (including require-dev)
  Package operations: 0 installs, 0 updates, 1 removal
    - Removing laravel/chisel (v0.1.1)
  Generating optimized autoload files
  EXIT=0

  PS> npm run build
  > build
  > vp build

  [plugin @laravel/vite-plugin-wayfinder] Types generated for actions, routes, form variants
  [plugin laravel:fonts] Optimized font fallbacks require the optional "fontaine" package. Install it, or set "optimizedFallbacks: false" on your fonts to disable the feature.
  transforming...
  ✓ 2272 modules transformed.
  rendering chunks...
  computing gzip size...
  public/build/fonts-manifest.json                                 5.74 kB │ gzip:   0.71 kB
  public/build/manifest.json                                       8.03 kB │ gzip:   1.08 kB
  public/build/assets/instrument-sans-400-normal-DRC__1Mx.woff2   16.86 kB
  public/build/assets/instrument-sans-500-normal-Dk9ku72i.woff2   17.23 kB
  public/build/assets/instrument-sans-600-normal-B7fBEWYG.woff2   17.40 kB
  public/build/assets/instrument-sans-400-normal-D1W7dsQl.woff    21.24 kB
  public/build/assets/instrument-sans-500-normal-Z6ESRlEs.woff    21.65 kB
  public/build/assets/instrument-sans-600-normal-B9e8oLYv.woff    21.67 kB
  public/build/assets/fonts-C9MNnjVw.css                           2.35 kB │ gzip:   0.38 kB
  public/build/assets/app-CYJLrJos.css                            87.70 kB │ gzip:  14.84 kB
  public/build/assets/text-link-DtlpXzPV.js                        0.38 kB │ gzip:   0.27 kB
  public/build/assets/spinner-RtE3nBn8.js                          0.47 kB │ gzip:   0.34 kB
  public/build/assets/confirm-D6TOEIIp.js                          0.49 kB │ gzip:   0.32 kB
  public/build/assets/rolldown-runtime-CbXtAM7H.js                 0.58 kB │ gzip:   0.36 kB
  public/build/assets/label-5PMKdBo0.js                            1.13 kB │ gzip:   0.69 kB
  public/build/assets/confirm-password-BU4SULQ8.js                 1.32 kB │ gzip:   0.66 kB
  public/build/assets/password-input-M88CCpN9.js                   1.88 kB │ gzip:   1.04 kB
  public/build/assets/forgot-password-ytaAR2Ww.js                  1.92 kB │ gzip:   0.89 kB
  public/build/assets/appearance-DAQjAs3g.js                       2.24 kB │ gzip:   1.08 kB
  public/build/assets/reset-password-Db3t3zSK.js                   2.26 kB │ gzip:   0.92 kB
  public/build/assets/dashboard-C92CeTr3.js                        2.56 kB │ gzip:   0.91 kB
  public/build/assets/password-7NQpakei.js                         2.73 kB │ gzip:   0.73 kB
  public/build/assets/security-JMniF0xB.js                         3.62 kB │ gzip:   1.26 kB
  public/build/assets/input-D9_h-dyj.js                            6.68 kB │ gzip:   2.81 kB
  public/build/assets/dist-Du5u34lk.js                             7.02 kB │ gzip:   2.72 kB
  public/build/assets/login-B1o74MBG.js                            7.45 kB │ gzip:   3.08 kB
  public/build/assets/profile-CHtjvNlO.js                          9.53 kB │ gzip:   2.85 kB
  public/build/assets/createLucideIcon-XCVbE_uO.js                28.65 kB │ gzip:   9.25 kB
  public/build/assets/welcome-GPcO79iE.js                         30.38 kB │ gzip:   6.41 kB
  public/build/assets/app-CuvrQ3-T.js                            167.88 kB │ gzip:  51.32 kB
  public/build/assets/wayfinder-DllTFPd3.js                      347.33 kB │ gzip: 108.63 kB
  ✓ built in 4.70s
  EXIT=0

  PS> php artisan test
  {"tool":"pest","result":"passed","tests":24,"passed":24,"assertions":68,"duration_ms":4419}
  EXIT=0

  PS> composer run lint:check
  > pint --parallel --test
  {"tool":"pint","result":"passed"}
  EXIT=0

  PS> php artisan route:list | filter rute register|verification|two-factor|passkey
  rute terhapus yang masih ada: 0
  PS> (rute dipertahankan terlihat: GET/POST login, POST logout, forgot-password, reset-password/{token}, user/confirm-password, settings/profile, settings/security, settings/appearance)
  ```
- Temuan jujur di luar ruang lingkup: (a) `npm run types:check` gagal pada 3 error pre-existing di file generated `resources/js/actions/.../RedirectController.ts` (plugin wayfinder 0.1.3 vs tipe Method Inertia) — memblokir `composer ci:check` tetapi bukan build/tes, diputuskan diabaikan sampai T-015; (b) `npm run check` (vp) menandai format 9 dokumen lama di luar T-002, diputuskan tidak diformat otomatis demi integritas dokumen.
- Formatter: `composer run lint` (Pint) lulus tanpa perubahan; `composer run lint:check` lulus.
- Tes: 24 lulus, 0 gagal (68 assert, Pest 4.7.8, PostgreSQL 18.6).
- File kunci: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `app/Models/User.php`, `app/Http/Controllers/Settings/SecurityController.php`, `app/Http/Controllers/Settings/ProfileController.php`, `routes/web.php`, `routes/settings.php`, `database/factories/UserFactory.php`, `.github/workflows/tests.yml`, `tests/Feature/Settings/SecurityTest.php`, `composer.json`.
- Penyimpangan dari dokumen sumber: tidak ada. Backlog T-002 menyebut "Gunakan mekanisme pruning resmi starter kit (`laravel/chisel`) **bila tersedia**" — chisel ternyata tidak tersedia sebagai perintah di scaffold ini (alasan terdokumentasi di atas), jadi jalur manual sesuai ketentuan backlog dan dokumentasi resmi.
- Perintah kunci dan sumber dokumentasi:
  - Penonaktifan fitur: `config/fortify.php` `features` + hapus view/route/referensi frontend — laravel.com/docs/13.x/starter-kits, bagian "Enabling and Disabling Features" ("Once Fortify's feature registration has been removed, you should also remove the route registration from your frontend routes")
  - laravel/chisel: README github.com/laravel/chisel (branch main, dibaca 2026-10-11)
  - Kolom users & tabel framework: aturan keras AGENTS.md nomor 13
- Penjelasan untuk sidang (bagaimana dan kenapa): Fitur starter kit yang tidak dipakai Kamarin — registrasi publik, verifikasi email, 2FA, dan passkeys — dibuang habis-habisan, bukan sekadar dinonaktifkan di konfigurasi, supaya tidak ada kode, rute, halaman, migration, kolom database, maupun dependensi mati yang mengaburkan keputusan desain aplikasi. `laravel/chisel` diperiksa lebih dulu sesuai instruksi, tetapi dokumentasi resminya menunjukkan chisel hanya toolkit pembuat script pruning dan scaffold ini tidak menyertakan script `chisel.php`-nya, sehingga pruning dilakukan manual mengikuti dokumentasi resmi Laravel. Setiap lapis dibersihkan secara konsisten: config Fortify → provider → model User → controller/rute → halaman dan komponen React → migration → factory → tes, sehingga `npm run build` dan seluruh tes lulus tanpa referensi yang menggantung. Karena registrasi dibuang, pembuatan akun kini hanya melalui factory/seeder (T-007); alur login, logout, dan reset password tetap utuh dan tetap diverifikasi otomatis oleh tes. Workflow CI dijadikan manual sesuai instruksi agar tidak gagal diam-diam di GitHub tanpa layanan PostgreSQL.
- Reviewer: Antigravity (2026-10-11). Hasil review: **LULUS (semua kriteria terpenuhi)**.
  1. *Rate Limiting Login:* Terkonfigurasi di `FortifyServiceProvider::configureRateLimiting()` (`RateLimiter::for('login', ...)`, 5 req/menit per email+IP) dan diaktifkan di `config/fortify.php` (`limiters.login`). Terbukti berfungsi via tes `AuthenticationTest::test_users_are_rate_limited` yang memverifikasi response 429 Too Many Requests (`$response->assertTooManyRequests()`).
  2. *Alur Reset Password:* Utuh dan terisolasi dari fitur terbuang. `Features::resetPasswords()` aktif di `config/fortify.php`, handler `ResetUserPassword` terdaftar, rute (`forgot-password`, `reset-password/{token}`, `user/confirm-password`) berfungsi, tampilan `forgot-password.tsx`, `reset-password.tsx`, `confirm-password.tsx` bersih dari fitur yang dibuang. Terverifikasi oleh 5 tes di `PasswordResetTest` dan 2 tes di `PasswordConfirmationTest` (semua lulus).
  3. *Referensi Menggantung:* Tidak ditemukan referensi menggantung pada rute (`php artisan route:list` bersih dari register/verification/2fa/passkey), imports, komponen UI, maupun tipe TypeScript (`resources/js/types/auth.ts` bersih). `npm run build` berhasil tanpa kendala.
  4. *Kesesuaian Kolom users (Aturan 13):* Migration passkeys dan 2FA telah dihapus. Kolom `users` di migration awal hanya memuat kolom bawaan framework yang diizinkan (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `timestamps`). Model `User`, `UserFactory`, dan seeder bebas dari kolom 2FA/passkey.
  5. *Dependensi (composer.json & package.json):* Dependensi frontend yang tidak terpakai (`@laravel/passkeys`, `input-otp`) sudah dicopot. `laravel/chisel` telah dicopot (`composer remove`) sesuai keputusan user. Sisa paket `laravel/passkeys` di composer hanyalah transitive dependency dari `laravel/fortify` (terlihat di `composer.lock`), bukan dependensi langsung. Komponen Radix UI di package.json dipakai oleh UI primitives. `laravel/sail` dipertahankan di dev dependencies.
  - Verifikasi mandiri: `npm run build` lulus (EXIT=0); `php artisan test` 24 lulus, 0 gagal (68 assert, PostgreSQL `kamarin_test`); `composer run lint:check` lulus.

### T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu (2026-10-11)

- Cabang: `task/T-003-tests-on-postgres` (dari `main` setelah T-001 di-merge)
- Status: selesai
- Yang dikerjakan:
  - `phpunit.xml`: `DB_CONNECTION=sqlite`/`:memory:` diganti `DB_CONNECTION=pgsql`, `DB_DATABASE=kamarin_test`. Host, port, username, dan password diambil dari `.env` (satu server yang sama), jadi tidak ada kredensial ganda.
  - Pengaman di `tests/TestCase.php`: `setUp()` memanggil `guardTestDatabase()` **sebelum** `parent::setUp()`. Guard membaca `Env::get('DB_DATABASE')` dan melempar `RuntimeException` jika nama tidak berakhiran `_test`. Karena `RefreshDatabase` (yang menjalankan `migrate:fresh` = menghapus semua tabel) baru berjalan di dalam `parent::setUp()`, guard selalu mendahului migrasi atau penghapusan apa pun.
  - Helper pembeku waktu `freezeNow()` di `tests/Pest.php` (default beku di `2026-01-01 12:00:00`, menerima string atau `Carbon`), plus hook global `pest()->beforeEach/afterEach` yang mereset jam antar-tes. Temuan jujur: hook `beforeEach()` telanjang di `Pest.php` **tidak dieksekusi** di Pest 4.7.8 (dibuktikan tes probe); diperbaiki memakai API `pest()->beforeEach(...)` sesuai dokumentasi resmi Global Hooks, lalu probe lulus.
  - README diperbarui: perintah tes, catatan bahwa ekstensi SQLite tidak lagi dibutuhkan, pengaman, dan helper waktu.
- Bukti pengaman terhadap database yang salah (dijalankan 2026-10-11, saat database development masih berisi tabel framework sisa verifikasi T-001 dengan 0 baris data bisnis):

  ```
  PS> $env:DB_DATABASE="kamarin"; php artisan test
  {"tool":"pest","result":"failed","tests":40,"passed":0,"assertions":0,"duration_ms":2943,"errors":40,"error_details":[{"test":"Tests\\Unit\\ExampleTest::test_that_true_is_true","file":"C:\\Users\\andikaa\\Desktop\\Kamarin\\tests\\Unit\\ExampleTest.php","line":12,"message":"Refusing to run tests against database [kamarin]. The database name must end with \"_test\" so that tests can never drop development data. Fix DB_DATABASE in phpunit.xml before running the suite again.", ... }, ...39 error_details lainnya dengan pesan guard yang sama...]}
  EXIT=2
  ```

  Database development dicek langsung setelah run gagal — utuh, tidak ada tabel yang terhapus:

  ```
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin -t -c "select count(*)||' tables' from information_schema.tables where table_schema='public';"
   10 tables
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin -c "select count(*) as users from users;"
   users
  -------
       0
  (1 row)
  ```

- Tes di PostgreSQL `kamarin_test` (dijalankan dua kali: setelah pengaturan akhir, dan sekali lagi setelah formatter):

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":40,"passed":40,"assertions":138,"duration_ms":6667}
  EXIT=0
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin_test -t -c "select count(*)||' tables' from information_schema.tables where table_schema='public';"
   10 tables
  ```

  Tidak ada tes yang gagal karena perbedaan perilaku SQLite vs PostgreSQL. Semua 40 tes bawaan starter kit lulus apa adanya.

- Verifikasi helper pembeku waktu (tes probe sementara di `tests/Unit/FreezeNowProbeTest.php`, dihapus setelah verifikasi, tidak masuk suite):

  ```
  PS> vendor\bin\pest tests/Unit/FreezeNowProbeTest.php
  {"tool":"pest","result":"passed","tests":3,"passed":3,"assertions":4,"duration_ms":89}
  EXIT=0
  ```

- Formatter: `composer run lint` (Pint) memperbaiki style di `tests/TestCase.php`, lalu `composer run lint:check` lulus (`{"tool":"pint","result":"passed"}`, EXIT=0)
- Tes: 40 lulus, 0 gagal (138 assert, Pest 4.7.8, PostgreSQL 18.6)
- File kunci: `phpunit.xml`, `tests/TestCase.php`, `tests/Pest.php`, `README.md`
- Penyimpangan dari dokumen sumber: tidak ada. Dua hal di luar ruang lingkup yang dilaporkan: (1) `.github/workflows/tests.yml` bawaan starter kit menjalankan `composer setup` + `composer ci:check` tanpa layanan PostgreSQL sehingga akan gagal jika CI GitHub diaktifkan; (2) database development holds 10 tabel framework sisa verifikasi T-001 (users=0) — bukan "kosong" murni, dicatat agar bukti di atas jujur
- Perintah kunci dan sumber dokumentasi:
  - `phpunit.xml` `<env name="DB_DATABASE" value="kamarin_test"/>` — konfigurasi standar pengujian Laravel (laravel.com/docs/13.x/testing), aturan keras AGENTS.md nomor 10
  - Guard: `Env::get('DB_DATABASE')` sebelum `parent::setUp()` — `RefreshDatabase` menjalankan `migrate:fresh` di dalam `setUp()` aplikasi Laravel (laravel.com/docs/13.x/database)
  - Hook global: `pest()->beforeEach(...)` — https://pestphp.com/docs/global-hooks (diverifikasi 2026-10-11; halaman docs Pest saat ini 5.x, proyek memakai Pest 4.7.8, perilaku diverifikasi lewat tes probe)
- Penjelasan untuk sidang (bagaimana dan kenapa): Semua tes dipindah dari SQLite `:memory:` ke database PostgreSQL `kamarin_test` yang terpisah, karena aturan seperti unique index parsial dan kunci baris hanya bisa dibuktikan di PostgreSQL sungguhan. Sebuah pengaman dipasang di `tests/TestCase.php` yang membaca nama database sebelum aplikasi boot, sehingga sebelum `RefreshDatabase` sempat menghapus apa pun; jika namanya tidak diakhiri `_test`, seluruh suite langsung gagal dengan pesan jelas — ini dibuktikan dengan menjalankan tes terhadap database `kamarin` dan memverifikasi tabel development tetap utuh. Helper `freezeNow()` disediakan karena banyak aturan bisnis Kamarin bergantung pada "hari ini" (expiry, no-show), jadi tes harus bisa membekukan jam secara deterministik; jam otomatis direset antar-tes lewat hook global Pest. Satu bug nyata ditemukan saat verifikasi: hook `beforeEach()` biasa di `Pest.php` tidak dieksekusi Pest 4, sehingga dibetulkan memakai API `pest()->beforeEach(...)` sesuai dokumentasi resmi — buktinya tes probe berubah dari gagal menjadi lulus.
- Reviewer: belum ada (backlog menandai T-003 "Tidak perlu" review Claude)

### T-001 Setup lingkungan Laravel 13 + React + PostgreSQL (2026-10-10)

- Cabang: `task/T-001-setup-environment`
- Status: selesai
- Yang dikerjakan:
  - Prasyarat: aktifkan ekstensi PHP lokal `pdo_pgsql`, `zip`, `pdo_sqlite`, `sqlite3` di `php.ini` Laragon (backup: `php.ini.bak-t001`). `pdo_pgsql` adalah syarat user; `zip` dibutuhkan Composer; `sqlite3`/`pdo_sqlite` dibutuhkan tes bawaan yang memakai SQLite `:memory:`.
  - Pasang Laravel installer v5.32.0 dengan `composer global require laravel/installer` (perintah resmi dokumentasi Installation Laravel 13).
  - `laravel new kamarin-scaffold -n --database=pgsql --react --pest --npm --no-boost` di folder sementara (`%LOCALAPPDATA%\Temp\opencode`), lalu dipindah ke root repo dengan `robocopy /E /MOVE`. `docs/` dan `AGENTS.md` tidak tersentuh (tidak ada di scaffold, tidak ditimpa).
  - Pilihan installer (diverifikasi lewat `laravel new --help`): `--react` (React Starter Kit resmi: Inertia + React + TypeScript + Tailwind), autentikasi default Laravel via Fortify (bukan WorkOS), tanpa `--teams`, `--database=pgsql`, test framework `--pest` (default masuk akal), `--no-boost` (Boost = paket tambahan, butuh persetujuan).
  - Buat `compose.yaml` (hanya layanan `postgres:18-alpine`) dengan database `kamarin` (dev) dan `kamarin_test` (tes, dibuat lewat `docker/postgresql/init-test-db.sql` pada start pertama).
  - `.env` dan `.env.example`: `APP_TIMEZONE=Asia/Makassar`, `DB_*` placeholder (`kamarin`/`kamarin`/`kamarin`), `MAIL_MAILER=log`. `.env` tidak masuk git (cek `.gitignore`).
  - Verifikasi: `php artisan migrate --force` sukses di database kosong (5 migration bawaan), halaman awal `http://127.0.0.1:8000` via `composer run dev` mengembalikan HTTP 200 (welcome page React, Vite dev aktif), `php artisan config:show app.timezone` = `Asia/Makassar`.
- Tes: 40 lulus, 0 gagal (138 assert, Pest 4.7.8). Tes bawaan starter kit; masih memakai SQLite `:memory:` sesuai default `phpunit.xml` — pengalihan ke PostgreSQL `kamarin_test` dikerjakan di T-003.
- File kunci: `compose.yaml`, `docker/postgresql/init-test-db.sql`, `.env.example`, `config/app.php` (baris timezone), `README.md`
- Penyimpangan dari dokumen sumber: (1) `config/app.php` bawaan skeleton memakai `'timezone' => 'UTC'` hardcode, diubah ke `env('APP_TIMEZONE', 'UTC')` agar timezone bisa diatur lewat `.env` sesuai instruksi tugas dan AGENTS.md. (2) `laravel/sail` tetap berada di `require-dev` (bawaan skeleton, tidak dipakai; `arsitektur_sistem.md` menyebut Sail tetap alternatif). (3) Tes bawaan berjalan di SQLite, bukan PostgreSQL (aturan keras 10) — hanya sementara sampai T-003.
- Perintah kunci dan sumber dokumentasi:
  - `composer global require laravel/installer`, `laravel new ... --react --database=pgsql --pest --npm --no-boost` — dokumentasi resmi Laravel 13 Installation (laravel.com/docs/13.x/installation); daftar opsi diverifikasi langsung dari `laravel new --help` (installer 5.32.0).
  - `composer run dev` → isinya `@php artisan dev` (server + queue worker + Vite) — diverifikasi dari `composer.json`, sejalan AGENTS.md bagian Lingkungan pengembangan.
  - `docker compose up -d` — Docker Compose v5.5.1; Docker Desktop dijalankan manual oleh agent (disetujui user).
- Penjelasan untuk sidang (bagaimana dan kenapa): Proyek dibuat dengan Laravel installer resmi sehingga kerangka, starter kit React (Inertia + Fortify), dan Pest mengikuti default resmi Laravel 13 tanpa konfigurasi manual. Karena repo berada di drive Windows dan Sail mewajibkan WSL2, diputuskan PHP, Composer, dan Node berjalan lokal, sementara hanya PostgreSQL 18 yang dikemas lewat Docker; `compose.yaml` sengaja hanya berisi satu layanan database. Database dibagi dua sejak awal — `kamarin` untuk development dan `kamarin_test` untuk tes — agar pengujian tidak pernah menyentuh data development, sesuai aturan keras nomor 10. Timezone dipindah dari hardcode ke `.env` (`APP_TIMEZONE=Asia/Makassar`) mengikuti prinsip bahwa kebijakan tidak boleh hardcode. Kelayakan lingkungan dibuktikan dengan migration di database kosong, halaman awal yang terbuka lewat `composer run dev`, dan seluruh tes bawaan yang lulus.
- Reviewer: Antigravity (2026-10-10) — hasil di bawah

#### Hasil review Reviewer (2026-10-10)

Reviewer memeriksa cabang secara mandiri tanpa mengedit kode. Semua perintah dijalankan langsung; tidak hanya mempercayai laporan Builder.

**compose.yaml**

| Sub-poin | Status | Bukti |
| --- | --- | --- |
| Port host:container | ❌ → ✅ **Diperbaiki** | Semula `5432:5432` (bind ke semua antarmuka, melanggar AGENTS.md). Diperbaiki ke `127.0.0.1:5432:5432`. Diverifikasi: `docker ps` → `127.0.0.1:5432->5432/tcp`. **Temuan ini terlewat oleh Reviewer saat review pertama** |
| Kredensial | ✅ Sesuai | `kamarin/kamarin/kamarin` — development-only, bukan rahasia produksi; didokumentasikan di `.env.example` |
| Volume data | ✅ Sesuai | Mount ke `/var/lib/postgresql` — **benar untuk image 18**. Saran Reviewer untuk mengubah ke `/var/lib/postgresql/data` ditolak user. Sumber: README resmi `docker-library/postgres` (PGDATA dan VOLUME berubah di versi 18 ke atas) |
| Init script | ✅ Sesuai | `./docker/postgresql/init-test-db.sql:ro` → `CREATE DATABASE kamarin_test;` — diverifikasi langsung |
| Kontainer berjalan | ✅ Sesuai | `docker ps` → `kamarin-postgres Up (healthy)`, port `127.0.0.1:5432->5432/tcp` (setelah perbaikan) |
| Koneksi DB dari app | ✅ Sesuai | `php artisan db:show` → PostgreSQL 18.6, database `kamarin`, 10 tabel, koneksi berhasil |
| Database `kamarin_test` ada | ✅ Sesuai | `docker exec kamarin-postgres psql -U kamarin -c "\l"` → `kamarin` dan `kamarin_test` keduanya ada |


**`.env.example`**

| Sub-poin | Status | Bukti |
| --- | --- | --- |
| `APP_TIMEZONE=Asia/Makassar` | ✅ Sesuai | Nilai tepat, sesuai keputusan user 2026-10-09. `php artisan config:show app.timezone` → `Asia/Makassar` |
| `DB_CONNECTION=pgsql` dan `DB_*` lengkap | ✅ Sesuai | `host=127.0.0.1`, `port=5432`, `database/username/password=kamarin` |
| `MAIL_MAILER=log` | ✅ Sesuai | Tertera eksplisit |
| Tidak ada rahasia nyata | ✅ Sesuai | `APP_KEY=` dikosongkan; tidak ada token atau password produksi |
| `.env` tidak ter-commit | ✅ Sesuai | `.gitignore` mencantumkan `.env`; `git ls-files --cached` tidak menemukan `.env` |

**README.md**

| Sub-poin | Status | Bukti |
| --- | --- | --- |
| Langkah setup | ✅ Sesuai | Tiga langkah jelas: `docker compose up -d`, `composer install`, `composer run setup` |
| Ekstensi PHP yang dibutuhkan | ✅ Sesuai | `pdo_pgsql` disebutkan dengan versi terverifikasi |
| Tabel versi | ✅ Sesuai | PHP 8.3.35, Laravel 13.35.0, PostgreSQL 18.6, Node/npm, Composer, Pest 4.7.8 |
| Catatan SQLite sementara | ✅ Sesuai | README jujur: tes masih di SQLite `:memory:`; T-003 yang akan mengalihkan |

**Aturan keras AGENTS.md**

| Aturan | Status | Bukti |
| --- | --- | --- |
| #1 Skema: tidak ada migration di luar bawaan framework | ✅ Sesuai | Migration yang ada: 3 bawaan framework + 2 bawaan Fortify starter kit. Tidak ada migration domain Kamarin. Diizinkan aturan #13 |
| #6 Kebijakan tidak di-hardcode: timezone lewat `.env` | ✅ Sesuai | `config/app.php` → `env('APP_TIMEZONE', 'UTC')` |
| #8 Otorisasi: tanpa paket role-permission | ✅ Sesuai | `composer.json` tidak mengandung paket role-permission pihak ketiga |
| #10 Tes memakai PostgreSQL | ⚠️ Penyimpangan sementara (tercatat jujur) — **selesai di T-003** | `phpunit.xml` masih `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Builder mendelegasikan ke T-003 |
| #11 Dependensi tanpa persetujuan | ✅ Sesuai | `laravel/sail`, `laravel/chisel`, `laravel/wayfinder` bawaan installer resmi — **disetujui user 2026-10-10**. Paket lain di luar installer tetap butuh persetujuan eksplisit |
| #13 Fitur starter kit tidak dipakai harus dinonaktifkan | ❌ **Tidak sesuai — ditindaklanjuti di T-002** — **selesai di T-002 (2026-10-11)** | `config/fortify.php` masih mengaktifkan `Features::registration()`, `Features::emailVerification()`, `Features::twoFactorAuthentication()`, `Features::passkeys()`. Halaman `register.tsx` masih ada. Akan dibuang di T-002 menggunakan `laravel/chisel` bila tersedia |

**Tes (dijalankan sendiri oleh Reviewer)**

```
php artisan test --no-ansi
→ 40 passed (138 assertions), 0 failed — 4139ms — Pest 4.7.8
```

✅ Semua lulus. Catatan: masih di SQLite `:memory:` (penyimpangan aturan #10, didelegasikan ke T-003).

**Keputusan user (2026-10-10) setelah review:**
- Volume `/var/lib/postgresql` tetap (benar untuk image 18; saran Reviewer ditolak karena salah).
- Port diperbaiki ke `127.0.0.1:5432:5432` — **temuan ini terlewat oleh Reviewer, ditemukan user**. `compose.yaml` sudah diperbarui dan diverifikasi.
- Paket bawaan installer resmi (`laravel/wayfinder`, `laravel/chisel`, `laravel/sail`) disetujui. Paket lain butuh izin.
- Fitur starter kit yang tidak dipakai dibuang di T-002 menggunakan `laravel/chisel` bila tersedia.

**Kesimpulan Reviewer (final):** T-001 memenuhi kriteria selesai di BACKLOG_FASE4_5.md setelah perbaikan port. Satu temuan kritis (port terbuka ke semua antarmuka) terlewat oleh Reviewer dan ditemukan user — dicatat sebagai kelemahan review. Satu penyimpangan aktif tersisa: fitur Fortify tidak dipakai masih aktif, ditindaklanjuti di T-002.
