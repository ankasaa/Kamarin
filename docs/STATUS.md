# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-12

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-006 dan T-008 SELESAI, sudah di-review agent Reviewer terpisah dan LULUS** — perbaikan minor sudah diterapkan. Cabang: `task/T-006-models` (tip `6112b76`) dan `task/T-008-availability-service` (tip menyusul, menumpuk dari T-006 lalu rebase). `main` tidak disentuh (masih `52a5d6c`); kedua cabang belum di-push. **Menunggu merge oleh user** (T-006 dulu, baru T-008 — linier, fast-forward)
- Hasil review (agent terpisah, `git status` bersih di awal/akhir, tidak mengubah apa pun): **LULUS**, tanpa pelanggaran aturan keras. Nilai enum, kolom/cast, SoftDeletes, factory, dan pemetaan **AV-01..AV-13 semua terpetakan penuh — tidak ada kasus uji terlewat**. Suite review: **134/134, 1068 assert**, `lint:check` lulus. Tiga titik keputusan dinilai sah: (a) konvensi blokir `[start, end)` — reviewer setuju (dasar CHECK `end_date > start_date`), (b) `stay_date >= today`, (c) nama role konstanta privat boleh sebagai jembatan sampai T-007
- Perbaikan minor yang diterapkan (commit `fix(models): ...` + `fix(availability): ...`): (1) `Property::bookings()` ditambahkan sesuai ERD + assertion; (2) `User` `#[Fillable]` kini memuat `role_id`, `property_id`, `is_active` (diuji `User::create` tidak menjatuhkan FK); (3) `assertValidStay()` **mendeduplikasi kamar by id** sehingga kapasitas tidak tergandakan; (4) 3 tes baru: dedupe kapasitas, `excludeBookingId` (booking tidak menghalangi dirinya sendiri, booking lain tetap menghalangi), dan batas **"tepat hari ini"** AV-13 (`stay_date == today` menghalangi maintenance). Tes AV kini **20/20 (55 assert)**
- Hasil verifikasi akhir (dijalankan mandiri): seluruh suite **138/138 tes, 1084 assert, 0 risky** di PostgreSQL `kamarin_test`; `composer run lint:check` lulus. (Rincian: 118 di cabang T-006 [base + enum/model], 20 tes AV di T-008; `composer run lint` tidak mengubah file lagi — bersih)
- Yang dibuat T-008: `app/Services/AvailabilityService.php` (lapis 1 dari 3 lapis BR-01.5) — `availableRooms()` (kamar aktif bebas malam aktif + blokir, filter kapasitas opsional, `housekeeping` sengaja tidak ikut query BR-07.5), `assertValidStay()` (AV-02/03/04/05/06/07/09/10/11 + dedupe), `assertRoomCanBeBlocked()` (AV-12), `assertRoomStatusChangeAllowed()` (AV-13/BR-07.2) + `app/Exceptions/AvailabilityException`. T-006: 7 enum + `holdsNights()`/`countsAsSold()`, 19 model, 19 factory
- Konvensi keputusan (terkunci di STATUS sesuai pilihan user, tidak di business_rules): rentang `room_blocks` **`[start_date, end_date)`** — CHECK database `end_date > start_date` melarang blokir satu hari bentuk lain; "masa depan" AV-13 = `stay_date >= today`; nama role `owner`/`manager` konstanta privat (nama canonical ditetapkan seeder T-007). Poin yang direview & diperiksa reviewer: konvensi rentang blokir, interpretasi today, pemetaan AV
- Catatan jujur: `excludeBookingId` kini **sudah diuji** (temuan reviewer sebelumnya: "belum terbukti"); `booking_code` factory belum memenuhi BR-14 — itu memang tugas T-010 (BookingService), bukan cacat T-006/T-008. **T-007 (seeder) ditunda** atas keputusan user. Langkah berikutnya: merge user, lalu **T-009 PricingService** (PR-01..PR-11, BR-11; `RateFactory` perlu state tarif default)

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006 (enum, model, relasi, factory) **selesai + review LULUS** di `task/T-006-models` (`6112b76`); T-008 (AvailabilityService) **selesai + review LULUS** di `task/T-008-availability-service` (menumpuk dari T-006, sudah rebase); **menunggu merge user** lalu push; T-007 (seeder) ditunda atas keputusan user; `main` di `52a5d6c` |
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

Entri T-001 sampai T-004, entri T-005, T-005b, dan review mandiri T-005 (semua sudah selesai atau terjawab) dipindahkan ke `docs/STATUS_ARCHIVE.md` (batch 2026-10-12). Entri baru ditambahkan di bawah ini. Saat ini **tidak ada pertanyaan terbuka**.

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

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
| T-001 Setup lingkungan Laravel 13 + React + PostgreSQL | 2026-10-10 | Selesai, review lulus setelah perbaikan port | `32fac10` |
| T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu | 2026-10-11 | Selesai | `b66530f` |
| T-002 Pruning autentikasi starter kit | 2026-10-11 | Selesai, review lulus | `2d1233c` (squash-merge di main; cabang asli `task/T-002-auth-cleanup`) |
| T-004 Migration tahap 1 (master) + tes kesesuaian skema | 2026-10-11 | Selesai (termasuk amandemen ERD nullable + expiry 3 jam) | `5124cf1` |
| T-005 Migration tahap 2 (transaksi) + pengaman double booking | 2026-10-11 | Selesai | `fee2337` |
| T-005b Penguatan skema: FK gabungan + 12 CHECK kewajaran | 2026-10-11 | Selesai | `e58b8ac` |
| Review mandiri T-005 + T-005b (semua fokus lulus, termasuk FK exact-set) | 2026-10-12 | Selesai | `52a5d6c` (merge ke main) |
