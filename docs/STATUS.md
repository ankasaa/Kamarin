# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-12

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-006 (enum, model, relasi, factory) SELESAI** di `task/T-006-models` (dari `main` `52a5d6c`; `main` tidak disentuh; commit pertama cabang = arsip STATUS `343e842`). Menunggu **review** — penulis kode tidak self-review (AGENTS.md), disarankan agent lain/Claude
- Hasil verifikasi (dijalankan mandiri): seluruh suite **117/117 tes, 1023 assert** di PostgreSQL `kamarin_test` (sekali saat hijau pertama, sekali sesudah formatter — EXIT=0 keduanya, hilang risky setelah perbaikan assertion); 60 tes baru lulus semua (`tests/Feature/Models` + `tests\Unit\Enums`: 60/60, 154 assert); `composer run lint:check` lulus; Pint merapikan 11 file (import + FQCN docblock, tanpa perubahan perilaku)
- Yang dibuat: **7 enum backed string** di `app/Enums/` dengan nilai persis aturan 2, termasuk `BookingStatus::holdsNights()` (pending/confirmed/checked_in/checked_out — malam menginap kamar, BR-01) dan `BookingStatus::countsAsSold()` (confirmed/checked_in/checked_out saja — BR-10); **18 model baru + `Role`/`User` dilengkapi** (19 tabel ERD), `#[Fillable]` eksplisit (atribut gaya Laravel 13 yang dipakai repo, bukan `$guarded = []`), relasi sesuai ERD, SoftDeletes hanya `Booking`+`Guest`; **19 factory** — `BookingFactory` punya 7 state per status + helper `createWithNights()` (room_id item == room_id malam, jumlah malam = selisih check-in/out, `is_active` = `holdsNights()`, subtotal/total = Σ harga malam)
- Cast: 6 kolom uang `integer`, semua kolom date `date:Y-m-d` (tes wajib: `stay_date`/`check_in_date` 2026-10-12 tetap "2026-10-12" di JSON dengan Asia/Makassar), `days_of_week` `array`, kolom enum ke enum yang sesuai, kolom time properti `string`
- File kunci untuk di-review (path): `app/Enums/BookingStatus.php` .. `app/Enums/HousekeepingStatus.php`, `app/Models/*.php` (19 file), `database/factories/*.php` (19 file), `tests/Feature/Models/DateSerializationTest.php`, `tests/Feature/Models/ModelRelationsTest.php`, `tests/Feature/Models/FactoryValidityTest.php`, `tests/Unit/Enums/BookingStatusTest.php`
- Catatan jujur: fase merah tidak dijalankan terpisah (model/factory belum ada saat tes ditulis — tes dan implementasi ditulis bersamaan); run pertama sudah hijau, lalu 19 tes dataset factory yang "risky" (tanpa assertion) diberi assertion `exists` → akhirnya 1023 assert. Posisi T-007 (seeder) vs T-008: melompat ke T-008 menunggu keputusan user ("Pertanyaan terbuka" di bawah). **Laporan berikutnya ke user = setelah T-008 lengkap dengan berkas servis dan tesnya** (permintaan user)

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006 (enum, model, relasi, factory) **selesai** di `task/T-006-models`, menunggu review; `main` di `52a5d6c` (T-004, T-005, T-005b sudah di-merge). T-007/T-008 menyusul |
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
- Reviewer: belum ada — menunggu review user atau agent lain (AGENTS.md: agent yang menulis kode tidak me-review kodenya sendiri; self-review tidak dilakukan untuk tugas ini).

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
