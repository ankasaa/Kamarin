# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-12

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-009 (PricingService + tes PR-01..PR-11) SELESAI, sudah di-review agent Reviewer terpisah dan LULUS, sudah di-merge fast-forward ke `main` dan di-push**. `main` = `origin/main` = **`99699ff`** (dari `743d8eb`), merge `--ff-only` EXIT=0, linier tanpa merge commit. T-006 dan T-008 juga sudah di-review LULUS dan sudah masuk `main`
- Hasil review T-009 (agent terpisah, `git status` bersih di awal/akhir): **LULUS**, semua temuan MINOR, tanpa pelanggaran aturan keras (skema tidak diubah, logika bisnis di `app/Services`, uang integer, tes PostgreSQL). **PR-01..PR-11 semua terpetakan penuh** — assertion benar-benar membuktikan ekspektasi (bukan tes kosong; reviewer membuktikan tes tidak bisa lulus dengan stub). Enam titik yang Builder tandai dikonfirmasi reviewer: `assertHasDefaultRate` cek keberadaan memadai (lebih ketat dari BR-11.4, gabung `is_active` bisa di T-010); ISO weekday `format('N')` benar + tanggal tes akurat (07-09=Thu, 07-10=Fri, 07-11=Sabtu); PR-08 tidak vacuous (membuktikan `price` kolom salinan bukan relasi hidup); merah- tidak- bersih = catatan jujur bukan cacat; tie-break K2 benar (comparator `[priority,id]` descending); `assertNoConflictingRate` semantik overlap benar. Dua hal untuk siap jawab saat sidang: bukti end-to-end penguncian harga ada di T-010; tie-break id = fallback defensif data rusak (tarif seri sudah ditolak saat simpan)
- Hasil verifikasi T-009: tes PR **11/11 (35 assert)**; seluruh suite **149/149 tes, 1119 assert, 0 risky** di PostgreSQL `kamarin_test`; `composer run lint` + `lint:check` lulus
- Yang dibuat T-009: `app/Services/PricingService.php` (10 metode: `nightlyRateFor`, `pricePerNight`, `quoteStay`, `minimumNightsForCheckIn`, `assertMinimumNightsSatisfied`, `assertHasDefaultRate`, `assertNoConflictingRate`, `calculateTotals`, `recalculateTotal`, `addCharge`) + `app/Exceptions/PricingException.php`; `RateFactory::defaultRate()` (state tarif default, catatan reviewer T-008); `Booking::bookingNights()` `hasManyThrough` (jalur ERD bookings→items→nights untuk BR-12)
- Keputusan teknis T-009 (diverifikasi): ISO weekday pakai **`format('N')`** — Carbon versi repo **tidak punya** `isoDayOfWeek` (dibuktikan `UnknownMethodException`); pemenang tarif = priority tertinggi, seri → id terbesar (K2) lewat comparator `[priority,id]` descending; `minimum_nights` dari tarif pemenang malam check-in, override hanya owner/manager dengan alasan; total = Σ malam aktif + Σ biaya tambahan; diskon = charge negatif hanya owner/manager
- **Kejujuran proses T-009:** tes PR ditulis lebih dulu dari kasus uji, tapi red-proof **tidak sebersih T-008** — run pertama gagal karena (a) 11 error pola factory statis (`RoomTypeFactory::create()` → harus `RoomType::factory()->create()`) dan (b) typo kutip string di `PricingService` baris 206 (`php -l` syntax error); keduanya diperbaiki, **tidak ada assertion dilonggarkan**. Reviewer mengonfirmasi 11 tes substansif dan tidak bisa lulus dengan stub — merah tidak bersih = catatan jujur, bukan cacat kualitas
- Konvensi keputusan (terkunci di STATUS, tidak di business_rules): rentang `room_blocks` `[start_date, end_date)`; "masa depan" AV-13 = `stay_date >= today`; nama role `owner`/`manager` konstanta privat (canonical di seeder T-007). Untuk T-010 BookingService: penguncian harga BR-11.7 (tulis salinan ke `booking_nights`), gabung cek `is_active` room type di `assertHasDefaultRate`, dan `booking_code` BR-14
- T-007 (seeder) ditunda atas keputusan user. **Langkah berikutnya: T-010 BookingService** (BR-01 lapis 2 kunci baris, BR-14 kode booking)

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006 (enum, model, relasi, factory) **selesai + review LULUS + di-merge & di-push**; T-008 (AvailabilityService) **selesai + review LULUS + di-merge & di-push**; T-009 (PricingService) **selesai + review LULUS + di-merge & di-push**. `main` = `origin/main` = `99699ff`. T-007 (seeder) ditunda atas keputusan user. **Berikutnya: T-010 BookingService** (BR-01 lapis 2 kunci baris, BR-14 kode booking, AV + PR + BK kasus uji) |
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

Entri T-001 sampai T-004, entri T-005, T-005b, dan review mandiri T-005 (semua sudah selesai atau terjawab) dipindahkan ke `docs/STATUS_ARCHIVE.md` (batch 2026-10-12). Entri baru ditambahkan di bawah ini.

1. **(T-010, terbuka)** Temuan review T-009 meminta "gabung cek `is_active` di `assertHasDefaultRate`", tapi tabel `rates` di ERD v2.1 **tidak punya kolom `is_active`** (yang ada hanya `is_default`, `priority`, rentang tanggal, `days_of_week`, `minimum_nights` — diverifikasi di `docs/erd_v2_1.mermaid` baris 125-137). Yang dimaksud reviewer mungkin `room_types.is_active` (PR-06 menyebut "tipe kamar **aktif**"). Belum dikerjakan karena di luar ruang lingkup T-010 (BR-01/BR-13/BR-14) dan melibatkan `PricingService` milik T-009. Pertanyaan untuk user: apakah `assertHasDefaultRate` juga harus menolak saat `room_types.is_active` false, atau cek keberadaan tarif default sudah cukup untuk PR-06?
2. **(T-010, terbuka)** Temuan M6 review T-010: `BookingService::findOrCreateGuest()` mencocokkan email **mentah** (`where('email', $email)`) tanpa `trim` maupun normalisasi huruf besar/kecil, sehingga `' a@x.test'` dan `'a@x.test'` akan membuat dua tamu berbeda; `phone` juga hanya dipakai saat **membuat**, tidak pernah untuk **mencari**. Kasus uji BK-07 hanya menguji pencarian via email sehingga ini di luar T-010, tapi US-21 AC1 menyebut "pencarian lewat **email atau telepon**". Pertanyaan untuk user: apakah normalisasi (trim + lowercase saat menyimpan dan mencari) serta pencarian via telepon dikerjakan sekarang di `BookingService`, atau ditunda ke tugas khusus US-21 (guest management)?
3. **(T-010, terbuka)** Temuan I5 review T-010: `lockForUpdate` (lapis 2 BR-01) saat ini **hanya** ada di `BookingService::createBooking`. Sisi pembuatan `room_blocks` (BR-01.6: index parsial hanya menjaga booking terhadap booking, jadi booking vs blokir dijaga di service) belum mengunci baris kamar, sehingga race "booking dibuat bersamaan dengan blokir kamar yang sama" secara teori masih bisa lolos. Di luar ruang lingkup T-010 (tidak ada kode blokir di repo sekarang). Pertanyaan untuk user: apakah tugas pembuatan blokir kamar nanti juga wajib mengunci baris kamar dengan `lockForUpdate` mengikuti pola `BookingService`?

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
