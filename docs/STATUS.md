# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-12

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: T-005 + lanjutannya **T-005b**, `task/T-005-transaction-migrations` (T-005 dibuat dari `main` setelah fast-forward ke tip T-004 `5124cf1` — linier, tanpa merge commit; untuk T-005b cabang `main` **tidak disentuh** sesuai instruksi user terbaru)
- Status: selesai (T-005 dan T-005b)
- Hasil verifikasi akhir (dijalankan mandiri, bukan klaim): `php artisan migrate:fresh` di development sukses — **23 migration**; siklus `migrate:rollback --step=3` lalu `migrate` lagi sukses — **`down()` terbukti bekerja**; **57 tes lulus, 0 gagal** (867 assert) di PostgreSQL `kamarin_test` (sekali sebelum dan sekali sesudah formatter); `composer run lint:check` (Pint) lulus; keluaran `\d booking_nights`, `\d bookings`, `\d payments` (T-005) dan `\d booking_items`, `\d booking_nights` (T-005b) persis desain — tempelan di log tugas masing-masing
- Yang dibuat: 8 migration tabel transaksi (`000012`..`000019`) + `000020` indeks pencarian tamu + **`000021` penguatan skema T-005b** — unique `(id, room_id)` di `booking_items`, FK gabungan `booking_nights(booking_item_id, room_id) → booking_items(id, room_id)` dengan **ON UPDATE CASCADE + ON DELETE NO ACTION** (via `DB::statement`), dan **12 CHECK kewajaran bernama eksplisit di 7 tabel** (bookings 2, booking_items 1, booking_nights 1, payments 1, rates 3, room_types 1, properties 3) — tanpa CHECK di `booking_charges.amount` (diskon negatif). Wajib T-005 juga terpenuhi: unique parsial `(room_id, stay_date) WHERE is_active`, CHECK tanggal bernama, indeks bagian 7, soft delete `bookings`, `decimal(12,0)`, `booking_source_id` NOT NULL
- Analisis yang diminta di T-005 kini **SELESAI**: penyimpangan `booking_nights.room_id` dari `booking_items.room_id` ditolak database oleh FK gabungan (keputusan user 2026-10-11: opsi A); pindah kamar (BR-06) cukup mengubah `booking_items.room_id`, malam-malamnya ikut berpindah lewat cascade, lalu tetap dijaga unique parsial
- `SchemaConformityTest` **tidak diubah satu karakter pun** dan tetap lulus (tidak dilonggarkan); tes FK global tetap lulus: 29 FK (1 lama dibuang, 1 gabungan masuk), **semua `delete_rule` = `NO ACTION`**, dan FK gabungan satu-satunya dengan `update_rule` = `CASCADE`. `SchemaHardeningTest` (18 tes, berdataset) membuktikan tiap penolakan lewat **pesan constraint yang bernama**
- File kunci untuk di-review (path): `database/migrations/2026_10_11_000012_create_room_blocks_table.php` .. `000021_add_composite_fk_and_reasonableness_checks.php`, `tests/Feature/Database/TransactionSchemaTest.php`, `tests/Feature/Database/SchemaHardeningTest.php`, `tests/Feature/Database/SchemaConformityTest.php`
- Review mandiri 2026-10-12 terhadap AGENTS.md/ERD/BR-01/backlog: fokus (1)(2)(3)(5) **LULUS** lewat psql manual (keluaran mentah di entri log); fokus (4) `rollback`/`migrate` **TERBLOKIR lingkungan** — Smart App Control Windows mode enforce memblokir `pdo_pgsql` (dan modul native Vite di proyek); **putusan user ditunggu** (entri "Pertanyaan terbuka" 2026-10-12). Tidak ada temuan cacat kode; 3 rekomendasi opsional bertanda sumber dokumentasi

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-004, T-005, dan T-005b selesai (migration master + transaksi + penguatan skema FK gabungan dan CHECK kewajaran), T-006 menyusul |
| ERD | v2.1, dibekukan |

## Keputusan yang sudah dikunci

- Skema: `docs/erd_v2_1.mermaid` (19 tabel). Anti double booking lewat tabel `booking_nights` dengan unique index parsial.
- Harga per malam. Priority tertinggi menang. Diskon dicatat sebagai baris `booking_charges` bernilai negatif.
- Default kebijakan (semuanya pengaturan properti): check-in 14:00, check-out 12:00, DP 50%, expiry booking pending 3 jam, batal gratis 2 hari sebelum check-in, batas no-show 22:00. Sebagian masih placeholder.
- Web dulu, antarmuka bahasa Inggris, data dummy dulu, pembayaran dicatat manual oleh staf.
- Dashboard dibangun desktop-first, lalu dibuat responsif untuk subset layar tertentu.

## Keputusan yang masih terbuka

| Hal | Usulan | Status |
| --- | --- | --- |
| Alat tes | Pest | Dipilih: Pest 4 (default installer Laravel 13, disetujui user 2026-10-09) |
| Docker | PostgreSQL saja di Docker (compose), aplikasi lokal | Diputuskan 2026-10-09 menggantikan usulan Sail |
| Role dan permission | Tabel `roles` + Policy, tanpa paket | Usulan |
| Kalender timeline | Komponen CSS grid buatan sendiri | Usulan, perlu verifikasi |
| Starter kit React | Starter kit React resmi Laravel 13 (Inertia, Fortify) | Terverifikasi dan dipasang di T-001 |
| Paket ekspor laporan | PDF atau Excel, satu format | Perlu diverifikasi |

## Pertanyaan terbuka dari agent

### 2026-10-11, T-005 — Satu keputusan skema (SELESAI - sudah dijawab user)

1. **`booking_nights.room_id` bisa menyimpang dari `booking_items.room_id` (SELESAI 2026-10-11).** Fakta: `booking_nights.room_id` dan `booking_items.room_id` sama-sama foreign key lepas ke `rooms(id)`; tidak ada penghubung di database yang memaksa keduanya bernilai sama untuk satu rantai malam-item. Tiga opsi pencegahan di level database:
   - **Opsi A — foreign key gabungan:** tambah unique `(id, room_id)` di `booking_items`, lalu ganti FK `booking_nights.booking_item_id` menjadi FK gabungan `(booking_item_id, room_id) REFERENCES booking_items(id, room_id)`. Malam dengan room_id yang tidak sama dengan item induknya langsung ditolak database; biaya satu unique index tambahan + perubahan definisi FK. Paling murah dan tetap deklaratif.
   - **Opsi B — trigger:** bekerja, tapi logika pindah ke luar deklarasi skema, sulit terlihat saat membaca ERD/migration, dan perlu pemeliharaan sendiri.
   - **Opsi C — CHECK dengan subquery:** tidak bisa di PostgreSQL (CHECK tidak boleh membaca tabel lain). Dicatat agar keputusan tidak mengulang jalur ini.

   **Keputusan user: opsi A**, dengan `ON UPDATE CASCADE` dan `ON DELETE NO ACTION`, dikerjakan langsung di **T-005b** (migration `2026_10_11_000021`, commit `bd658bd`). Sudah diimplementasi dan dibuktikan tes: malam yang room_id-nya menyimpang ditolak, pindah kamar memindahkan malam lewat cascade, dan cascade yang mendarat di kamar terisi ditolak unique parsial. Rincian di entri log T-005, bagian "T-005b".

### 2026-10-12, Review T-005 — Smart App Control memblokir driver PHP (MENUNGGU KEPUTUSAN USER)

1. **Fakta.** Sejak 2026-10-12 sekitar 09:00, Smart App Control (SAC) Windows berada dalam mode **enforce** (`HKLM:\SYSTEM\CurrentControlSet\Control\CI\Policy\VerifiedAndReputablePolicyState = 1`) dan memblokir `php_pdo_pgsql.dll` (juga `php_pdo_sqlite.dll`, `php_sqlite3.dll`, `php_zip.dll`) lewat event Code Integrity 3033/3077. Akibatnya semua `php artisan` / Pest yang butuh PostgreSQL gagal dengan `could not find driver`. Modul native Vite di proyek ini (`node_modules/@voidzero-dev/vite-plus-win32-x64-msvc/vite-plus.win32-x64-msvc.node`) juga kena blokir yang sama — build frontend ikut terdampak. DLL tidak berubah sejak 2026-10-06 dan pada 2026-10-11 semua perintah ini masih lulus; perubahannya ada di kebijakan OS, bukan di proyek. Sumber: Microsoft Learn, "Smart App Control overview" (https://learn.microsoft.com/en-us/windows/apps/develop/smart-app-control/overview) — "In enforcement mode ... Apps cannot be run unless they are recognized by Microsoft's app intelligence services, or they are signed with a trusted certificate"; "unknown, unsigned code are blocked by default".
2. **Opsi (keputusan keamanan mesin — agent tidak mengambil inisiatif apa pun):**
   - **A — Nonaktifkan SAC** lewat Settings > Windows Security > App and Browser Control (pilihan "Off"). Catatan Microsoft: mode diperiksa di halaman itu; setelah mati, keputusan menyalakan lagi sepenuhnya di tangan pengguna. Setelah nonaktif: jalankan ulang fokus (4) + seluruh suite, lalu entri review diperbarui.
   - **B — Pertahankan SAC.** Verifikasi berbasis PHP di mesin ini tetap terblokir. Fokus (4) memakai bukti historis 2026-10-11 (siklus identik pada commit sama, EXIT=0, tercatat di entri T-005b) dan rencana dijalankan ulang di mesin/CI lain.
   - **C — PHP sementara di container Docker.** Perubahan lingkungan (keputusan Opsi D: PHP lokal) → butuh persetujuan eksplisit dan verifikasi kompatibilitas lebih dulu (AGENTS aturan 11); agent tidak melakukannya tanpa persetujuan.
3. **Jika mesin ini dikelola organisasi**, kebijakan mungkin tidak boleh diubah — konfirmasi dulu sebelum memilih opsi A.

Entri T-001, T-002, T-003, dan T-004 dipindahkan ke `docs/STATUS_ARCHIVE.md` (semua sudah terjawab user). Entri baru ditambahkan di bawah ini.

---

## Log tugas

Entri terbaru di atas. Template:

```
### T-xxx judul (tanggal)
- Cabang:
- Status: selesai | sebagian | terblokir
- Yang dikerjakan:
- Tes: ... lulus, ... gagal (ID kasus uji yang gagal: ...)
- File kunci:
- Penyimpangan dari dokumen sumber: tidak ada | (jelaskan)
- Penjelasan untuk sidang (3-5 kalimat, bagaimana dan kenapa):
- Reviewer: (agent lain atau Claude) dan hasilnya
```

### Review mandiri T-005 + T-005b terhadap dokumen sumber (2026-10-12)

- Cabang: `task/T-005-transaction-migrations` (tip `e58b8ac`, tanpa perubahan apa pun selama review). Rujukan: AGENTS.md, `docs/erd_v2_1.mermaid` (dibaca ulang per blok), BR-01, `docs/tasks/BACKLOG_FASE4_5.md`.
- Status: **(1) LULUS, (2) LULUS, (3) LULUS, (5) LULUS; (4) TERBLOKIR lingkungan** — `php` tidak bisa memuat `pdo_pgsql` karena Smart App Control Windows mode enforce memblokir DLL ekstensi PHP. Menunggu keputusan user (entri "Pertanyaan terbuka" 2026-10-12). Tidak ada temuan cacat kode.
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

Tes otomatis `SchemaConformityTest` (8 tes, memparse ERD vs `information_schema`) terakhir hijau pada commit yang sama (`e58b8ac`, 2026-10-11: `tests:8, passed:8, assertions:738`); file tes tidak diubah dan kode tidak berubah sejak — namun **jalur otomatis hari ini terblokir SAC**, sehingga verifikasi fokus (5) di atas memakai jalur manual psql.

#### Rekomendasi (tidak ada perubahan wajib; semua bersumber dokumentasi)

1. **Pertahankan `NO ACTION`, jangan diganti `RESTRICT`.** Untuk kasus ini keduanya identik — keduanya menolak penghapusan baris yang dirujuk; bedanya hanya `RESTRICT` tidak boleh ditunda sedangkan `NO ACTION` bisa ditunda tapi tetap diperiksa segera (PostgreSQL Docs, "Constraints", https://www.postgresql.org/docs/current/ddl-constraints.html). Mengubah hanya menambah diff tanpa manfaat perilaku.
2. **Opsional, butuh persetujuan user — perketat `SchemaConformityTest` FK jadi exact-set.** Saat ini asersi FK hanya subset (expected ⊆ actual): 29 FK ERD harus ada, tetapi FK tambahan di luar ERD tidak akan ditolak tes (tes "tolak tabel/kolom di luar ERD" ada, untuk FK belum). Sumber: AGENTS.md aturan 1 ("migration harus persis sesuai ERD v2.1"). Menyentuh tes, bukan skema.
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

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
| T-001 Setup lingkungan Laravel 13 + React + PostgreSQL | 2026-10-10 | Selesai, review lulus setelah perbaikan port | `32fac10` |
| T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu | 2026-10-11 | Selesai | `b66530f` |
| T-002 Pruning autentikasi starter kit | 2026-10-11 | Selesai, review lulus | `2d1233c` (squash-merge di main; cabang asli `task/T-002-auth-cleanup`) |
| T-004 Migration tahap 1 (master) + tes kesesuaian skema | 2026-10-11 | Selesai (termasuk amandemen ERD nullable + expiry 3 jam) | `5124cf1` |
