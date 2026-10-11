# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-11

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: T-005, `task/T-005-transaction-migrations` (dari `main`; `main` di-fast-forward dulu ke tip T-004 `5124cf1` karena instruksi menyebut "setelah T-004 di-merge" — linier, tanpa merge commit)
- Status: selesai
- Hasil verifikasi akhir (dijalankan mandiri, bukan klaim): `php artisan migrate:fresh` di development sukses — **22 migration** (19 tabel ERD + tabel framework); **39 tes lulus, 0 gagal** (832 assert) di PostgreSQL `kamarin_test`; `composer run lint:check` (Pint) lulus; keluaran `\d booking_nights`, `\d bookings`, `\d payments` persis ERD v2.1 lengkap dengan indeks bagian 7 (tempelan di log T-005)
- Yang dibuat: 8 migration tabel transaksi (`2026_10_11_000012`..`000019`) — room_blocks, bookings, booking_items, booking_nights, booking_charges, payments, booking_status_logs, booking_change_logs — plus `000020` indeks pencarian tamu (melengkapi arsitektur bagian 7). Wajib terpenuhi: unique parsial `(room_id, stay_date) WHERE is_active` via `DB::statement`; CHECK tanggal bernama via `DB::statement` (`bookings_check_out_after_checkin`, `room_blocks_end_after_start`); indeks bagian 7 semua terpasang; FK **NO ACTION di semua relasi (tanpa cascade, tanpa pengecualian)**; soft delete `bookings`; uang `decimal(12,0)` (6 kolom, diverifikasi otomatis dari ERD); `booking_source_id` NOT NULL (BK-06); `old_status` nullable (log pertama)
- Daftar pengecualian di tes kesesuaian skema **dikosongkan** — tes kini memeriksa semua 19 tabel ERD, 29 foreign key, unique `booking_code`, dan seluruh kolom uang. **BK-05 terbukti**: insert dua malam aktif untuk (room, tanggal) sama ditolak database dengan pesan constraint yang benar; malam nonaktif + aktif pada (room, tanggal) sama terbukti **DIIZINKAN**; CHECK tanggal terbukti menolak tanggal sama maupun terbalik
- Satu analisis yang diminta: `booking_nights.room_id` **bisa** menyimpang dari `booking_items.room_id` (keduanya FK lepas ke `rooms`, tidak ada penghubung di database). Tiga opsi pencegahan + rekomendasi (FK gabungan) tercatat di "Pertanyaan terbuka dari agent", entri T-005 — butuh persetujuan karena menyentuh skema
- File kunci untuk di-review (path): `database/migrations/2026_10_11_000012_create_room_blocks_table.php` .. `000020_add_guest_search_indexes_to_guests_table.php`, `tests/Feature/Database/TransactionSchemaTest.php`, `tests/Feature/Database/SchemaConformityTest.php`

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-004 dan T-005 selesai (migration master + transaksi dengan pengaman double booking), T-006 menyusul |
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

### 2026-10-11, T-005 — Satu keputusan skema (tidak memblokir)

1. **`booking_nights.room_id` bisa menyimpang dari `booking_items.room_id`.** Fakta: `booking_nights.room_id` dan `booking_items.room_id` sama-sama foreign key lepas ke `rooms(id)`; tidak ada penghubung di database yang memaksa keduanya bernilai sama untuk satu rantai malam-item. Saat ini disiplinnya ada di service (mulai T-006), sesuai ERD yang menyebut room_id di nights sebagai "salinan, untuk constraint". Tiga opsi pencegahan di level database:
   - **Opsi A (rekomendasi) — foreign key gabungan:** tambah unique `(id, room_id)` di `booking_items`, lalu ganti FK `booking_nights.booking_item_id` menjadi FK gabungan `(booking_item_id, room_id) REFERENCES booking_items(id, room_id)`. Malam dengan room_id yang tidak sama dengan item induknya langsung ditolak database; biaya satu unique index tambahan + perubahan definisi FK. Paling murah dan tetap deklaratif.
   - **Opsi B — trigger:** bekerja, tapi logika pindah ke luar deklarasi skema, sulit terlihat saat membaca ERD/migration, dan perlu pemeliharaan sendiri.
   - **Opsi C — CHECK dengan subquery:** tidak bisa di PostgreSQL (CHECK tidak boleh membaca tabel lain). Dicatat agar keputusan tidak mengulang jalur ini.
   
   Rekomendasi agent: opsi A, dikerjakan di migration terpisah (mis. T-006) karena menyentuh skema dan butuh persetujuan. Tidak memblokir T-005.

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

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
| T-001 Setup lingkungan Laravel 13 + React + PostgreSQL | 2026-10-10 | Selesai, review lulus setelah perbaikan port | `32fac10` |
| T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu | 2026-10-11 | Selesai | `b66530f` |
| T-002 Pruning autentikasi starter kit | 2026-10-11 | Selesai, review lulus | `2d1233c` (squash-merge di main; cabang asli `task/T-002-auth-cleanup`) |
| T-004 Migration tahap 1 (master) + tes kesesuaian skema | 2026-10-11 | Selesai (termasuk amandemen ERD nullable + expiry 3 jam) | `5124cf1` |
