# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-11

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: T-004, `task/T-004-master-migrations` (dari `main` setelah T-002 di-merge dengan squash-merge `2d1233c`, lalu di atasnya commit arsip `026c9d5`; amandemen ERD: `379f180` + `3177e2c`)
- Status: selesai
- Hasil verifikasi akhir (dijalankan mandiri, bukan klaim): `php artisan migrate:fresh` di development sukses — 11 tabel master + 2 tabel framework; **33 tes lulus, 0 gagal** (458 assert) di PostgreSQL `kamarin_test` (24 tes lama + 9 tes kesesuaian skema, lulus terhadap ERD hasil revisi user); `composer run lint:check` (Pint) lulus; keluaran `\d users`, `\d rooms`, `\d rates`, `\d properties`, `\d guests`, `\d housekeeping_statuses` persis ERD v2.1 (tempelan di log T-004 + amandemen)
- Yang dibuat: 10 migration baru (`2026_10_11_000001`..`000011`) — roles, properties, room_types, rooms, amenities, room_type_amenities, housekeeping_statuses, guests, booking_sources, rates. Migration `users` bawaan dimodifikasi di tempat dan **diganti nama filenya** menjadi `2026_10_11_000003_create_users_table.php` (diizinkan backlog) agar urutannya setelah `roles` dan `properties`; kolom baru: `role_id` (FK not null), `property_id` (FK nullable), `is_active`. Model `Role` + `RoleFactory` baru; `UserFactory` diperbarui agar 24 tes lama tetap lulus
- Amandemen 2026-10-11 (ERD direvisi user): 9 kolom master menjadi nullable (`roles.description`, `properties.phone`, `room_types.description`, `rooms.floor`, `amenities.icon`, `housekeeping_statuses.notes`, `guests.email`/`phone`/`country`) dan default `properties.pending_expiry_hours` 24 → **3**; migration diedit di tempat, tes kesesuaian skema **dikencangkan** (default dibaca langsung dari ERD, bukan hardcoded; unique `housekeeping_statuses.room_id` masuk daftar periksa — ketika itu membuka bug: `->unique()` setelah `->constrained()` tidak pernah membuat unique index, sudah diperbaiki). Detail + keluaran di bagian "Amandemen 2026-10-11" pada entri log T-004
- Pengaman utama: `tests/Feature/Database/SchemaConformityTest.php` mem-parsing `docs/erd_v2_1.mermaid` lalu membandingkannya dengan `information_schema` PostgreSQL: nama tabel, nama kolom, tipe data, nullability, primary key, unique, foreign key, `decimal(12,0)` untuk uang, dan default kebijakan properti. Tabel atau kolom di luar ERD **ditolak**, kecuali yang diizinkan aturan 13 (tabel framework; kolom `users`: `email_verified_at`, `remember_token`, timestamps)
- Daftar pengecualian eksplisit — 8 tabel transaksi, sengaja belum dimigrasi, **dikosongkan di T-005**: `room_blocks`, `bookings`, `booking_items`, `booking_nights`, `booking_charges`, `payments`, `booking_status_logs`, `booking_change_logs`. Tes memastikan kedelapannya belum ada
- File kunci untuk di-review (path): `database/migrations/2026_10_11_000001_create_roles_table.php` .. `000011_create_rates_table.php`, `database/migrations/2026_10_11_000003_create_users_table.php`, `tests/Feature/Database/SchemaConformityTest.php`, `app/Models/Role.php`, `database/factories/RoleFactory.php`, `database/factories/UserFactory.php`, `app/Models/User.php`
- Pertanyaan yang butuh keputusan: **tidak ada** — pertanyaan `housekeeping_statuses.notes` NOT NULL sudah dijawab user (dibuat nullable, ERD direvisi 2026-10-11, migration mengikuti)

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-004 selesai (migration tahap 1 master + tes kesesuaian skema), T-005 menyusul |
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

### 2026-10-11, T-004 — Satu pertanyaan skema (SELESAI - sudah dijawab user)

1. **`housekeeping_statuses.notes` (SELESAI 2026-10-11):** Pertanyaan awal: kolom ini jadi NOT NULL karena di ERD tidak ditandai nullable. **Keputusan user: dibuat nullable.** ERD v2.1 direvisi user pada hari yang sama (penanda nullable dirapikan untuk 9 kolom master, `properties.pending_expiry_hours` default menjadi 3) dan migration T-004 mengikuti. Tes kesesuaian skema tidak dilonggarkan — justru dikencangkan (default dibaca dari ERD; unique `room_id` 1:1 ikut diperiksa, ketika itu membuka bug index yang tidak pernah terbentuk). Rincian di entri log T-004, bagian "Amandemen 2026-10-11".

Entri T-001, T-002, dan T-003 dipindahkan ke `docs/STATUS_ARCHIVE.md` (semua sudah terjawab user). Entri baru ditambahkan di bawah ini.

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

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
| T-001 Setup lingkungan Laravel 13 + React + PostgreSQL | 2026-10-10 | Selesai, review lulus setelah perbaikan port | `32fac10` |
| T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu | 2026-10-11 | Selesai | `b66530f` |
| T-002 Pruning autentikasi starter kit | 2026-10-11 | Selesai, review lulus | `2d1233c` (squash-merge di main; cabang asli `task/T-002-auth-cleanup`) |
