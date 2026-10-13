# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-13

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-016 (`RoomBlockService`: membuat dan menghapus blokir kamar) SELESAI** di `task/T-016-room-blocks`, dibuat dari `main` **`9058ad0`**. `main` tidak disentuh. **Merge T-014b ke `main` dijalankan atas perintah eksplisit user** (fast-forward, `8b03f6f` → `9058ad0`, tanpa force push) sebagai prasyarat cabang ini. Review menurut backlog: **Wajib — SUDAH DIJALANKAN, verdict LULUS tanpa temuan BLOCKING** (lihat bagian "Review T-016").
- Isi T-016: `app/Services/RoomBlockService.php` baru berisi `block()` dan `unblock()`. Rentang **setengah terbuka `[start_date, end_date)`** — malam yang dicakup `start_date` sampai `end_date - 1`, persis konvensi check-out milik booking, jadi hari check-out tidak ikut terblokir. Alasan wajib (tidak boleh kosong), dan `created_by_user_id` selalu terisi pelaku walaupun kolomnya nullable di skema. Otorisasi memakai **allow-list `RoleName`** `[Owner, Manager]`; resepsionis, housekeeping, dan peran tak dikenal (mis. `accountant`) semuanya ditolak — peran tak dikenal ditolak karena allow-list dibandingkan lewat `RoleName::tryFrom`, bukan deny-list.
- **Pola kunci (BR-01.5)** dijalankan persis seperti `BookingService::createBooking`: `SELECT ... FROM rooms ... ORDER BY id ASC FOR UPDATE`, lalu cek ulang bentrok **di bawah** kunci, baru tulis. `unblock()` ikut mengunci baris kamar karena menghapus blokir justru menambah ketersediaan dan harus diserialisasi terhadap penulis bersamaan.
- **`AvailabilityService::assertRoomCanBeBlocked()` dilengkapi.** Metode itu sudah ada tetapi **hanya memeriksa malam aktif**; pemeriksaan **blokir-lawan-blokir belum ada**, padahal BR-01.6 menyatakan index parsial hanya menjaga booking melawan booking. Ditambahkan pemeriksaan tumpang tindih blokir plus parameter aditif `$excludeBlockId` supaya blokir yang baru ditulis tidak dianggap bertabrakan dengan dirinya sendiri. Pemanggil lama tidak berubah perilaku.
- **Mengapa ada verifikasi kedua setelah menulis:** BR-01.6 secara eksplisit menyatakan **tidak ada index database yang menjaga blokir**, sehingga blokir tidak punya jaring pengaman lapis ketiga seperti booking. Karena itu `block()` memeriksa ulang **setelah** baris ditulis, masih di dalam transaksi yang sama. **Tafsirnya harus tepat:** cek kedua itu menutup jeda terhadap konflik yang **sudah ter-commit** dari penulis yang tidak mengunci kamar; ia **tidak** bisa melihat apalagi menggulung baris uncommitted milik koneksi lain (Read Committed). Terhadap penulis bersamaan yang patuh pada BR-01.5, penjaga sesungguhnya adalah kunci `FOR UPDATE` yang memaksa mereka menunggu.
- Hasil verifikasi: **Tes ditulis lebih dulu, merah bersih 9/9** (`Target class [App\Services\RoomBlockService] does not exist.`). **Review independen: LULUS, 0 temuan BLOCKING** (5 NIT dan beberapa INFO, rincian di bagian "Review T-016"); 4 NIT dan INFO terpenting sudah diperbaiki, sisanya dicatat beserta alasannya. Setelah perbaikan review, **tes 9 → 11** dan seluruh suite **281/281 tes, 2395 assert** lulus di PostgreSQL `kamarin_test` (50,2 detik); `composer run lint` + `composer run lint:check` (Pint) lulus; `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong** (skema dan dependensi tidak berubah); phpstan **123 error, tidak bertambah** dari T-016. Tes kunci baris kamar membuktikan `for update` benar-benar dikeluarkan **dan** urutannya mendahului cek ulang (`array_search` pada daftar SQL yang ditangkap `DB::listen`), bukan sekadar niat.
- **Insiden yang harus dicatat jujur:** sempat menjalankan **dua proses `php artisan test` bersamaan** pada database tes yang sama (suite di latar belakang + filter di foreground). Keduanya melakukan `migrate:fresh`, sehingga saling menjatuhkan tabel: muncul 23 error `relation "properties" does not exist` dan beberapa `Deadlock detected`. **Kedua hasil itu batal total dan bukan kegagalan kode.** Dijalankan ulang satu proses dan lulus bersih 279/279. Pelajaran: jangan pernah menjalankan dua proses tes ke `kamarin_test` secara bersamaan.
- **Tiga kegagalan merah yang berasal dari tes saya sendiri** (bukan kode): `$source` dikirim sebagai `int` padahal tanda tangan `createBooking` menerima `?BookingSource` (TypeError); `expect(RoomBlock::count())->toBe($index)` salah karena penolakan tidak boleh meninggalkan baris apa pun; dan helper `blockUser` membuat role duplikat kena `roles_name_unique`. Ketiganya diperbaiki di tes, bukan di kode.
- File kunci: `app/Services/RoomBlockService.php` (baru), `app/Services/AvailabilityService.php` (dilengkapi), `tests/Feature/Services/RoomBlockServiceTest.php` (baru, 11 tes AV-12/US-08/BR-01.5).
- Penyimpangan prosedural: `app/Services/AvailabilityService.php` disentuh (file T-008) dengan penambahan aditif `$excludeBlockId` dan pemeriksaan tumpang tindih blokir — ini melengkapi metode yang memang ditujukan untuk aturan BR-01.6, bukan jalan keluar.
- Langkah berikutnya: **T-015 (CI GitHub Actions)**, lalu putuskan nasib **phpstan 123 error** yang tidak tercakup backlog mana pun.
- Perhatian user yang tetap dicatat:
  1. **Temuan pre-existing, tak bisa diperbaiki tanpa menulis ulang riwayat `main`:** pesan commit merge `079edc2` di `main` rusak (perintah multi-baris tergabung jadi satu). Sudah dianalisis dan diputuskan **dibiarkan**: pengaruh fungsional nol, biaya perbaikan (tulis ulang 6 commit yang sudah terpush + force push) jauh lebih besar dari manfaat kosmetiknya. Hanya tercatat di sini.
  2. **Pertanyaan terbuka no. 3 kini TERJAWAB SEPENUHNYA** — sisi `room_blocks` yang dulu menggantung sudah dikerjakan lewat T-016, sehingga ketiga penulis penempatan kamar (booking baru, perubahan booking, blokir kamar) kini memakai pola kunci yang sama.
  3. **Pertanyaan terbuka no. 6 SUDAH DIJAWAB user**: pindah antar tipe kamar tetap di luar MVP, menjadi tugas lanjutan (**T-017**).
  4. **`composer run types:check` (phpstan level 7) masih merah project-wide dengan 123 error**, hampir semuanya docblock generic relasi Eloquent di `app/Models/*` — pre-existing, di luar T-016, belum ada baseline.
  5. Catatan non-blocking / NIT dari tugas T-012 dan tiga catatan non-blocking hasil review kedua T-013 (N1', N2', N3').

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` **v1.5** (perubahan v1.5: hasil T-013 — pola kunci baris kamar berlaku untuk semua penulis penempatan termasuk blokir kamar, BR-01.4 hanya bila `check_in_date` dipindah, pindah antar tipe ditunda ke T-017), keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006, T-008, T-009, T-010, T-010b, T-011, T-011b, T-012 **selesai + di-merge ke main** (`04c2c7e`); **T-014** (`079edc2`); **T-013 selesai + review LULUS** (`8b03f6f`); **T-014b selesai + di-merge ke main** (`9058ad0`, fast-forward atas perintah user); **T-016 selesai** di `task/T-016-room-blocks`, menunggu review wajib dan perintah merge. T-007 (seeder) ditunda atas keputusan user. **Berikutnya: T-015 (CI)** |
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

Entri T-001 sampai T-004, entri T-005, T-005b, review mandiri T-005, entri T-010b, entri T-011, serta entri T-011b dan T-012 (semua sudah selesai, terjawab, atau sudah di-merge) dipindahkan ke `docs/STATUS_ARCHIVE.md`. Entri terbaru ada di bagian "Log tugas" di bawah; ringkasan satu baris per tugas lama ada di tabel "Tugas yang sudah selesai (diarsipkan)" di paling bawah.

1. ~~**(T-010, terbuka)** Temuan review T-009 meminta "gabung cek `is_active` di `assertHasDefaultRate`"...~~ → **TERJAWAB di T-010b.**
2. ~~**(T-010, terbuka)** Temuan M6 review T-010: `findOrCreateGuest()` mencocokkan email mentah tanpa `trim`...~~ → **TERJAWAB di T-010b.**
3. **(T-010, terbuka — sebagian TERJAWAB di T-013)** Temuan I5 review T-010: `lockForUpdate` (lapis 2 BR-01) saat ini **hanya** ada di `BookingService::createBooking`. Sisi pembuatan `room_blocks` (BR-01.6: index parsial hanya menjaga booking terhadap booking, jadi booking vs blokir dijaga di service) belum mengunci baris kamar, sehingga race "booking dibuat bersamaan dengan blokir kamar yang sama" secara teori masih bisa lolos. **Keputusan user saat T-013 (2026-10-13): ya — setiap kode yang membuat atau mengubah penempatan kamar wajib mengunci baris kamar dengan `lockForUpdate` mengikuti pola `BookingService::createBooking`.** Aturan itu sudah diterapkan dan diuji di `BookingChangeService` (tes "T-013: locks the room rows for update before rechecking availability"). **Masih menggantung: penerapannya di pembuatan `room_blocks` (baris T-016), yang mengikuti aturan yang sama nanti.**
4. ~~**(T-012, terbuka)** Temuan M4 review T-012: `record()` menerima `PaymentType::Refund`...~~ → **TERJAWAB oleh user di T-012 (M4/M6).**
5. ~~**(T-012, terbuka)** Temuan M5 review T-012: `record()` membuat `reference_number` nullable...~~ → **TERJAWAB oleh user di T-012 (M5).**
6. ~~**(T-013, terbuka)** BR-06 mendefinisikan **pindah antar tipe kamar**: "malam yang dipindah dihitung ulang dengan tarif tipe baru dan selisihnya ditampilkan sebelum disimpan", sedangkan baris T-013 di backlog menuliskan lingkup MVP "**Tidak** mendukung ... pindah antar tipe kamar".~~ → **TERJAWAB oleh user saat T-013: tetap di luar MVP, dijadikan tugas lanjutan.** T-013 hanya menerima pindah kamar **dalam tipe yang sama** dan menolak tipe berbeda dengan pesan eksplisit (tes CH-08 "refuses to move a booking into a room of a different type"). Konsekuensinya yang harus diingat: **selisih harga antar tipe belum pernah dihitung maupun ditampilkan**, dan BR-06 tetap mendefinisikannya untuk tugas lanjutan itu.
   - Terkait, keputusan lingkup dari baris T-013: `moveRoom()` hanya menangani booking yang memegang **satu kamar** (menolak booking multi-kamar), karena menambah atau menghapus kamar pada booking multi-kamar juga dinyatakan di luar lingkup. Ubah tanggal sendiri tetap berlaku untuk semua kamar booking (teruji di tes CH-04 multi-kamar).

### Temuan pre-existing (masih terbuka)

> Tanda ini ditandai supaya tidak hilang di antara entri tugas. Semua isi di bawah ini **bukan** hasil kerja tugas berjalan.

1. **`docs/tasks/BACKLOG_FASE4_5.md` baris 51 (T-005b)**: baris itu tertulis sebagai **5 kolom** (`| T-005b | Penguatan skema booking ... | ERD v2.1 ... | Tes membuktikan ... | Ya |`) padahal tabel "Kebijakan review berbasis risiko" hanya punya **3 kolom** (`Tugas | Reviewer agent | Review Claude`). Isinya tampaknya tersalin dari tabel deskripsi tugas, dan kolom reviewer yang benar mestinya `Wajib`. Baris 56 (`T-016`) sudah diperbaiki dengan izin user di `a374e6f`; baris 51 belum.
   - **Status: izin user sudah diberikan saat T-013** dengan pilihan *"Perbaiki sekarang jadi `| T-005b | Wajib | Ya |`"*, **tetapi belum dikerjakan** — perbaikan itu sengaja ditahan agar diff T-013 tetap berisi perubahan T-013 saja. **Belum dieksekusi, menunggu giliran.** Setelah dieksekusi, pindahkan butir ini ke "sudah diperbaiki".

### Catatan non-blocking dari review T-013 (review kedua, semua INFO/NIT)

1. **(N1')** `moveRoom()` memindahkan `room_id` pada **semua** `booking_nights` milik item, termasuk baris `is_active = false` sisa perubahan tanggal. Tidak memengaruhi ketersediaan (index parsial hanya menutup baris aktif) dan tidak dihitung BR-10 — keputusan pencatatan yang perlu dijelaskan saat sidang.
2. **(N2')** `preview()` mengambil `lockForUpdate` di dalam transaksi read-only, jadi satu pratinjau singkat memblokir perubahan lain pada booking yang sama. Konsisten dengan jalur simpan; negligible untuk satu properti ±15 kamar.
3. **(N3')** Malam yang dilepas lalu "masuk kembali" dihargai tarif berlaku saat itu, bukan harga lama yang pernah dimilikinya. Konsisten dengan BR-11.7 dan K3, bukan penyimpangan.
4. **`composer run types:check` merah project-wide dengan 128 error** (phpstan level 7, tanpa baseline), hampir semuanya docblock generic relasi Eloquent di `app/Models/*`. Pre-existing dan di luar ruang lingkup T-013. Dua error tersisa di file yang disentuh T-013 juga pre-existing: `AvailabilityService::activeNightsQuery()` (file T-008) dan satu nullsafe `?->name ??` yang sudah ada sejak `18eeb58`.

### Catatan non-blocking / keterbatasan dari T-012 (PaymentService)
1. Race condition antara `record()` refund-manual dan `settleCancellation()` belum dikunci baris pembayarannya (kunci saat ini hanya di baris booking pada `settleCancellation()`).
2. Race condition antara `verify()` dan `cancel()` belum mengunci baris pembayaran.
3. Refund manual yang melebihi terbayar membuat `paymentStatus()` melabeli `unpaid`.
4. Refund pembatalan (`settleCancellation`) tercatat dengan `reference_number = null` (keputusan user m3).

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

### T-013 BookingChangeService: ubah tanggal, pindah kamar, dan jumlah tamu (2026-10-13)

- Cabang: `task/T-013-booking-change-service`, dibuat dari `main` **`079edc2`** (setelah T-014 di-merge ke `main`). `main` tidak disentuh.
- Status: **selesai dan review LULUS**. Kebijakan review: **Wajib** (baris T-013 di `docs/tasks/BACKLOG_FASE4_5.md`). Review pertama (agent reviewer independen) memberi verdict **TIDAK LULUS** dengan 1 temuan BLOCKING; temuan itu diperbaiki di `ea8f805`, lalu review kedua oleh reviewer independen lain memberi verdict **LULUS**. Detail lengkapnya di bagian "Review T-013" di bawah entri ini.
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (aturan 2, 3, 4, 5, 9, 10, 11); baris T-013 `docs/tasks/BACKLOG_FASE4_5.md`; BR-01, BR-06, BR-11, BR-12 beserta keputusan K3 dan K4 di `docs/business_rules_kamarin.md`; US-16 `docs/user_stories.md`; CH-01 sampai CH-11 `docs/rencana_pengujian.md` bagian 4.5; `BOOKING_CHANGE_LOGS` di `docs/erd_v2_1.mermaid` (baris 232) dan migrasinya; `docs/seq_perubahan_booking.mermaid`; serta `BookingService`, `PricingService`, `AvailabilityService`, `PaymentService` yang sudah ada sebagai pola.
- Yang dikerjakan:
  - **Test-first.** `tests/Feature/Services/BookingChangeServiceTest.php` ditulis lebih dulu, 19 tes, dan merah bersih sebelum implementasi: 9 error `Target class [App\Services\BookingChangeService] does not exist` + 10 gagal `BindingResolutionException` (19 tes, 0 lulus).
  - **`app/Services/BookingChangeService.php`** — enam metode publik:
    - `changeDates()` (memuat geser penuh), `extend()`, `shorten()` (keduanya pembungkus yang memvalidasi arah perubahan), `moveRoom()`, `changeGuestCount()`, dan `preview()` yang menghitung tanpa menulis.
    - **Otorisasi (aturan 3 dan 9):** allow-list `RoleName` — owner, manager, receptionist. Housekeeping dan peran di luar empat kanonik (mis. `night_audit`) ditolak, memakai `RoleName::tryFrom()` sehingga peran tak dikenal tidak lolos seperti pada deny-list.
    - **Alasan wajib semua peran (K4):** `trim($reason) === ''` sudah ditolak, jadi alasan hanya spasi pun gagal.
    - **Urutan transaksi (lapis 2 BR-01, keputusan Pertanyaan terbuka no. 3):** baca ulang booking dengan `lockForUpdate` → kunci baris kamar dengan `lockForUpdate` berurutan `orderBy('id')` → jalankan ulang `AvailabilityService::assertValidStay()` dengan `excludeBookingId = booking.id` agar malam milik booking sendiri tidak dianggap bentrok → cek ulang `minimum_nights` pada check-in baru → tulis diff malam → tulis satu `booking_change_logs` → hitung ulang total.
    - **K3 (harga terkunci):** `nightDiff()` hanya menyentuh malam yang keluar dan yang masuk. Malam yang tetap berada di rentang tidak di-update sama sekali, jadi harganya tetap. Hanya malam baru yang dihargai lewat `PricingService::nightlyRateFor()`, dengan `pricePerNight()` tetap dipanggil sebagai penyampai pesan penolakan ketika tidak ada tarif.
    - **Urutan operasi anti pelanggaran index parsial (CH-04):** malam yang keluar **dinonaktifkan dulu**, baru malam baru disisipkan. Tanpa urutan ini, pergeseran tanggal yang saling tumpang tindih (mis. 10–12 Juli → 11–13 Juli) membuat malam lama untuk 11 Juli masih `is_active` saat malam baru 11 Juli disisipkan, dan unique index parsial `(room_id, stay_date) WHERE is_active` akan menolaknya.
    - **BR-12 / PR-10:** `recalculate()` menghitung ulang `booking_items.subtotal` dari malam **aktif** lalu memanggil `PricingService::recalculateTotal()`, sehingga `total_amount = Σ booking_nights aktif + Σ booking_charges`.
    - **Status (CH-09, backlog T-013):** `pending` dan `confirmed` menerima semua jenis perubahan; `checked_in` hanya boleh `extend`, `shorten`, dan pindah kamar (ubah check-in, ubah jumlah tamu ditolak); status akhir `checked_out`, `cancelled`, `expired`, `no_show` menolak semuanya.
    - **minimum_nights (CH-07):** dicek ulang lewat `PricingService::assertMinimumNightsSatisfied()` terhadap tarif malam check-in yang baru; override hanya owner atau manager, dan karena alasan selalu terisi pada waktu simpan, peran itulah yang menentukan.
    - **Pelanggaran layer 3:** `translateDuplicateNight()` menerjemahkan SQLSTATE `23505` pada index parsial menjadi `AvailabilityException`, konsisten dengan `BookingService`.
  - **`app/Support/BookingChangeResult.php`** — DTO hasil perubahan berisi `oldTotal`, `newTotal`, `PaymentSummary` dari `PaymentService::summary()`, daftar `warnings` (konstanta `WARN_OVERPAID`, BR-12.3), dan `log` yang ditulis (null untuk pratinjau). Dipakai bersama oleh metode perubahan dan `preview()`.
  - **`expires_at` (CH-11):** tidak pernah disentuh oleh salah satu metode; tes memverifikasi nilainya identik setelah tanggal diubah dan status tetap `pending`.
  - **29 tes (19 saat implementasi + 10 dari hasil review), pemetaan ke kasus uji:** CH-01 (perpanjangan, harga lama terkunci, tarif dinaikkan setelah booking dibuat), CH-02 (malam baru bentrok → dicek ulang `booking_nights`, `total_amount` dan `booking_change_logs` = 0, bukan hanya exception-nya), CH-03 (percepat check-out → malam sisa nonaktif, total turun), CH-04 dua tes (geser sukses tanpa menganggap malam sendiri bentrok + geser menabrak booking lain tetap ditolak), CH-05 (alasan kosong/spasi untuk owner, manager, receptionist), CH-06 (satu baris log per perubahan berisi jenis, nilai lama, nilai baru, alasan, pelaku — dua perubahan menghasilkan dua baris), CH-07 (receptionist ditolak, owner dan manager lolos), CH-08 dua tes (pindah kamar tipe sama harga tetap + tipe berbeda ditolak), CH-09 (checked_in ubah check-in ditolak, perpanjang berhasil), CH-10 (kelebihan bayar ditandai lewat `PaymentService::summary`), CH-11 (`expires_at` tidak berubah), BR-06 status akhir (4 status), BR-06 allow-list peran, BR-01.2 jumlah tamu, T-013 pratinjau dua tes, dan bukti `lockForUpdate` pada baris kamar. **Sepuluh tes tambahan dari hasil review:** CH-09 diperbaiki fixture-nya menjadi realistis (check-in kemarin, bukan masa depan — fixture lama menyembunyikan temuan N1); resepsionis mempercepat check-out tamu yang sedang menginap; resepsionis memindahkan tamu yang sedang menginap; resepsionis meng-pratinjau perubahan tamu yang sedang menginap; BR-01.4 tetap menutup saat check-in digeser ke masa lalu (gerbang tidak dihapus melainkan dipindah); BR-06 `extend` menolak perubahan yang hanya masuk akal terhadap model basi (temuan N2, dua staf); BR-12 `booking_items.subtotal` ikut dihitung ulang (N3); CH-08 menolak pindah ke kamar yang terisi sebagian (N4); CH-04 memindahkan seluruh kamar booking multi-kamar (N4); T-013 pratinjau tidak menyentuh model yang diterimanya dan menolak lebih dari satu jenis perubahan sekaligus (N5).
- Tes — keluaran mentah `php artisan test` (run terakhir, sesudah seluruh perbaikan review):
  ```
  {"tool":"pest","result":"passed","tests":270,"passed":270,"assertions":2350,"duration_ms":39996}
  ```
  270/270 lulus (29 tes di `BookingChangeServiceTest`, naik dari 260), di PostgreSQL `kamarin_test`, bukan SQLite. Run oleh reviewer kedua sendiri: `{"tool":"pest","result":"passed","tests":270,"passed":270,"assertions":2350,"duration_ms":105254}`.
- Formatter — keluaran mentah `composer run lint` dan `composer run lint:check`:
  ```
  > pint --parallel
  {"tool":"pint","result":"passed"}
  ```
  ```
  > pint --parallel --test
  {"tool":"pint","result":"passed"}
  ```
- Static analysis — keluaran mentah `vendor/bin/phpstan analyse app/Services/BookingChangeService.php --no-progress`:
  ```
  {"tool":"phpstan","result":"passed","errors":0}
  ```
  `composer run types:check` (level 7, tanpa baseline) **masih merah dengan 128 error project-wide** — hampir semuanya docblock generic relasi Eloquent di `app/Models/*` yang tidak tersentuh T-013. Bukti bahwa T-013 tidak menambah satu pun error pun: phpstan dijalankan pada file yang sama **sebelum** perbaikan review (dengan `git stash`) dan menghasilkan **6 error yang identik pesan dan identifikatornya**, hanya barisnya bergeser. Sesudah perbaikan tinggal **2**, keduanya pre-existing: `AvailabilityService::activeNightsQuery()` (file T-008, di luar ruang lingkup) dan satu nullsafe `?->name ??` di `assertMayChange()` yang sudah ada sejak `18eeb58`. Empat error phpstan di file T-013 sendiri diperbaiki, satu di antaranya bug nyata: `translateDuplicateNight()` menyatakan mengembalikan `QueryException` tetapi mengembalikan `AvailabilityException`, yang akan memicu `TypeError` tepat pada jalur safety-net-nya.
- File kunci: `app/Services/BookingChangeService.php`, `app/Support/BookingChangeResult.php`, `tests/Feature/Services/BookingChangeServiceTest.php`.
- Dependensi dan skema: `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong**. Tidak ada paket baru (aturan 11) dan tidak ada perubahan tabel/kolom (aturan 1).
- Penyimpangan dari dokumen sumber: tidak ada pada aturan maupun dependensi. Dua hal adalah **keputusan lingkup yang diturunkan dari baris T-013 sendiri** dan dicatat di "Pertanyaan terbuka" no. 6: pindah antar tipe kamar ditolak (BR-06 mendefinisikannya, baris T-013 mengeluarkannya dari MVP), dan `moveRoom()` hanya menerima booking satu kamar. Tidak ada controller, halaman, atau seeder yang dibuat.
- **Penyimpangan prosedural yang perlu diketahui:** perbaikan temuan review N1 mengharuskan menyentuh `app/Services/AvailabilityService.php`, yaitu file tugas T-008, di luar ruang lingkup harfiah T-013. Perubahannya **murni aditif** — satu parameter baru `bool $enforcePastCheckInRule = true` di posisi terakhir `assertValidStay()`, sehingga `BookingService` dan seluruh `AvailabilityServiceTest` tetap berperilaku persis seperti sebelumnya (semua tes AV tetap lulus dalam 270/270). Tanpa parameter ini gerbang BR-01.4 tidak bisa dipisahkan per kasus, dan T-013 tidak akan memenuhi BR-06. Tidak ada perubahan skema di luar tugas.
- Penjelasan untuk sidang (3–5 kalimat, bagaimana dan kenapa): Setiap perubahan booking dijalankan di dalam satu transaksi yang lebih dulu mengunci baris booking lalu baris kamar secara berurutan berdasarkan id, sehingga dua staf yang mengubah kamar atau tanggal yang sama saling menunggu alih-alih menulis bersamaan dan memicu double booking. Malam yang tetap berada dalam rentang menginap tidak disentuh sama sekali sehingga harganya tetap terkunci sesuai keputusan K3, sementara hanya malam baru yang dihitung ulang memakai tarif yang berlaku pada saat perubahan dilakukan; urutan penulisannya sengaja menonaktifkan malam yang keluar lebih dulu sebelum menyisipkan malam baru, karena unique index parsial `(room_id, stay_date) WHERE is_active` akan menolak malam baru bila malam lama untuk tanggal yang sama masih aktif. Aturan BR-01.4 (check-in tidak boleh tanggal lampau) hanya dijalankan ketika perubahan itu benar-benar memindahkan `check_in_date`, bukan setiap kali booking diubah — kalau diterapkan tanpa syarat, resepsionis tidak akan bisa memperpanjang tamu yang sedang menginap, padahal BR-06 dan US-16 justru mengizinkannya, dan kunciannya adalah bahwa kedua aturan itu tidak bertentangan: BR-01.4 mengatur *penentuan* tanggal check-in, bukan ulang validasi tanggal yang sudah dijalani. Seluruh penolakan (alasan kosong, peran di luar allow-list, status akhir, dan minimum_nights) diperiksa di dalam service sehingga controller tetap tipis, dan setiap perubahan meninggalkan tepat satu baris `booking_change_logs` serta satu objek hasil berisi total lama, total baru, dan peringatan kelebihan bayar, sehingga staf selalu punya jejak audit dan alasan yang bisa dijelaskan.
- Reviewer: **Wajib** menurut backlog, sudah dijalankan dua kali oleh dua agent reviewer independen (bukan penulis kode). Ringkasan: review pertama **TIDAK LULUS**, review kedua setelah perbaikan **LULUS**. Rinciannya di bagian "Review T-013" di bawah.

#### Review T-013

**Review pertama — verdict TIDAK LULUS.** Verifikasi yang ditempel reviewer: `kamarin-postgres Up 5 hours (healthy)`; `{"tool":"pest","result":"passed","tests":260,"passed":260,"assertions":2300,"duration_ms":103804}`; `{"tool":"pint","result":"passed"}`; diff skema/dependensi kosong. Seluruh checklist DoD baris T-013 (14 butir) terpenuhi secara struktural, tetapi ada satu temuan BLOCKING:

- **N1 (BLOCKING) — gerbang BR-01.4 bocor ke jalur perubahan.** `AvailabilityService::assertValidStay()` memanggil `assertCheckInIsNotInPast()` tanpa syarat (baris 107 sebelum diperbaiki), sedangkan `BookingChangeService::assertStayStillValid()` memakainya pada setiap perubahan. Karena `$from` untuk booking `checked_in` adalah tanggal check-in yang sudah lewat, **resepsionis ditolak memperpanjang, mempercepat, dan memindahkan booking yang sedang diinapi** — kasus inti US-16. Temuan ini saya verifikasi sendiri sebelum meneruskan ke user: `assertCheckInIsNotInPast()` memang di baris 107 dan melempar `AvailabilityException: "Check-in date is in the past; only the owner or manager may book it."` Tes CH-09 tidak menangkapnya karena fixture-nya mustahil di produksi: booking `checked_in` dengan check-in **masa depan** (`2026-07-10` terhadap `freezeNow('2026-06-15')`).
- Temuan bernomor lain (semua NIT/INFO): N2 `extend()`/`shorten()` memvalidasi arah terhadap model yang bisa basi sebelum kunci diambil; N3 tidak ada tes yang meng-assert `booking_items.subtotal`; N4 dua jalur tak teruji (pindah kamar ke kamar terisi sebagian, `changeDates` pada booking multi-kamar); N5a `preview()` menyetel `total_amount` hipotetis pada objek hasil; N5c `preview()` diam-diam menjatuhkan satu parameter bila beberapa diberikan; N6 `preview()` mengambil kunci `FOR UPDATE` pada transaksi baca-saja (dapat diterima); N7 label komentar tes tertulis "CH-04 / K4" padahal tesnya tentang okupansi; N8 parameter guard bertipe nullable pada pemanggil yang selalu non-null; N9 pesan commit `079edc2` di `main` rusak (pre-existing, di luar T-013).

**Keputusan user atas tiga pertanyaan yang saya ajukan sebelum memperbaiki** (agar aturan tidak diubah diam-diam):
1. **N1 → "Lewati bila check-in tidak digeser" (disarankan).** Gerbang BR-01.4 hanya jalan bila perubahan benar-benar memindahkan `check_in_date`; menggeser check-in ke tanggal lampau tetap ditolak untuk resepsionis dan tetap boleh untuk Owner/Manager dengan alasan.
2. **Pertanyaan terbuka no. 6 (pindah antar tipe kamar) → "Tetap di luar MVP, jadi tugas lanjutan".** Penolakan eksplisit yang sudah ada dipertahankan dan tetap teruji CH-08; konsekuensinya tercatat jujur.
3. **Temuan pre-existing baris 51 backlog → "Perbaiki sekarang".**

**Perbaikan (`ea8f805`, 3 file, +498/−49):**
- **N1:** `AvailabilityService::assertValidStay()` mendapat parameter aditif `bool $enforcePastCheckInRule = true`; `BookingChangeService::assertStayStillValid()` menghitung `$checkInMoved` dari baris yang sudah terkunci dan mengoper parameternya. Empat tes baru dibuat merah dulu (4 error persis di `AvailabilityService.php:247`) lalu hijau: resepsionis memperpanjang/mempercepat/memindahkan tamu yang sedang menginap, pratinjau untuk tamu yang sedang menginap, plus satu tes penjaga bahwa menggeser check-in ke lampau tetap butuh Owner/Manager. Fixture CH-09 diganti realistis (check-in kemarin).
- **N2:** `changeDates()`, `extend()`, `shorten()` kini lewat satu `runDateChange()`; `$from` di-resolve dari baris terkunci (`$from ??= ...`) dan arah divalidasi oleh `assertCheckOutMoves()` **setelah** `lockBooking()`, sehingga staf kedua diukur terhadap baris terkunci dan ditolak dengan pesan jelas alih-alih membalik pekerjaan staf pertama (tes dua staf).
- **N3/N4:** tes `booking_items.subtotal` naik-turun 600.000 → 1.200.000 → 300.000, tes pindah kamar ke kamar yang terisi sebagian (ditolak, tanpa jejak), dan tes `changeDates` pada booking dua kamar (8 baris malam, 4 aktif, subtotal per item).
- **N5:** `preview()` menolak lebih dari satu jenis perubahan sekaligus; tes membuktikan model pemanggil tidak tersentuh (`isDirty('total_amount')` false). Catatan: temuan N5a ternyata **positif palsu** — `preview()` sudah meng-assign ulang `$booking` ke salinan hasil `lockForUpdate`, jadi `setAttribute()` tidak pernah mengenai objek milik pemanggil; tesnya tetap dipertahankan sebagai penjaga regresi.
- **N7/N8:** label komentar diperbaiki menjadi "CH-04 / BR-01.1"; parameter `assertMayChange(User)` dan `assertReasonGiven(string)` dibuat non-null.
- **Bonus dari phpstan:** `translateDuplicateNight()` kini mengembalikan `Exception` (sebelumnya `QueryException` padahal mengembalikan `AvailabilityException` — akan `TypeError` pada jalur yang justru ada untuk menangkapnya), `@throws PricingException` mendapat import yang benar, dan `writeLog()` mendapat tipe nilai array-nya.

**Review kedua — verdict LULUS.** Verifikasi reviewer kedua sendiri: `kamarin-postgres Up 8 hours (healthy)`; `{"tool":"pest","result":"passed","tests":270,"passed":270,"assertions":2350,"duration_ms":105254}`; `> pint --parallel --test` + `{"tool":"pint","result":"passed"}`; `{"tool":"phpstan","result":"passed","errors":0}` untuk `BookingChangeService.php`; diff skema/dependensi kosong; `git status --porcelain` bersih; `git diff --stat main` hanya 5 file, semuanya dalam ruang lingkup T-013. Semua temuan N1–N8 terkonfirmasi LULUS dengan bukti baris kode dan tes, tidak ada regresi, dan perubahan pada file T-008 dikonfirmasi murni aditif. Tiga catatan baru semuanya non-blocking:

- **N1' (INFO):** `moveRoom()` memindahkan `room_id` pada **semua** `booking_nights` milik item, termasuk baris `is_active = false` sisa perubahan tanggal. Baris nonaktif tidak memengaruhi ketersediaan (index parsial hanya menutup baris aktif) dan tidak dihitung BR-10 — murni keputusan pencatatan yang perlu dijelaskan saat sidang.
- **N2' (NIT):** `preview()` mengambil `lockForUpdate` di transaksi read-only, jadi satu pratinjau singkat memblokir perubahan lain pada booking yang sama. Konsisten dengan jalur simpan (pratinjau melihat baris yang sama dengan yang akan disimpan); dampaknya negligible untuk satu properti ±15 kamar dengan pemakaian staf internal.
- **N3' (INFO):** malam yang dilepas lalu "masuk kembali" dihargai tarif berlaku saat itu, bukan harga lama yang pernah dimilikinya. Konsisten dengan BR-11.7 dan K3 (harga disalin saat malam dibuat; yang tidak berubah terkunci, yang keluar lalu masuk lagi adalah malam baru), bukan penyimpangan.

Catatan: keluaran runner tes/Pint/phpstan memang berupa JSON satu baris — itu keluaran asli yang dipakai sebagai tempelan mentah, bukan hasil parse tambahan.

### T-014 Perintah kedaluwarsa booking pending dan penjadwalannya (2026-10-13)

- Cabang: `task/T-014-expire-pending`, dibuat dari `main` **`a374e6f`** (setelah T-011b + T-012 di-merge ke `main`). `main` tidak disentuh.
- Status: selesai.
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (aturan 2, 3, 6, 10, 13), `docs/business_rules_kamarin.md` (BR-13 kedaluwarsa booking pending), `docs/tasks/BACKLOG_FASE4_5.md` (baris T-014), `docs/rencana_pengujian.md` (EX-01 sampai EX-06).
- Yang dikerjakan:
  - Command console `app/Console/Commands/ExpirePendingBookings.php` dengan signature `bookings:expire-pending`. Mengambil booking dengan kriteria `status = pending`, `expires_at IS NOT NULL`, dan `expires_at <= now()`.
  - Loop pemrosesan memanggil `BookingService::expire()` dengan penanganan error try-catch per baris booking (partial failure recovery) sehingga jika ada satu booking yang gagal (misalnya lock timeout atau exception lain), command tetap melanjutkan sisa booking dan mencatat error ke log.
  - Penjadwalan command di `routes/console.php` menggunakan `Schedule::command(ExpirePendingBookings::class)->everyFiveMinutes()->withoutOverlapping()`.
  - Tes fitur `tests/Feature/Console/ExpirePendingBookingsTest.php` ditulis lebih dulu (test-first):
    - EX-01: Membatalkan booking pending yang sudah melewati batas waktu `expires_at` menjadi `expired`, melepas malam (`is_active = false`), dan mencatat status log.
    - EX-04: Booking staf tanpa `expires_at` (`expires_at = null`) tidak pernah dibatalkan oleh scheduler.
    - EX-05: Idempotensi - menjalankan command berulang kali tidak mengubah status maupun merusak data booking yang sudah expired.
    - Penanganan kegagalan parsial: Satu kegagalan tidak menghentikan sisa booking yang kedaluwarsa.
    - Assertion penjadwalan: Command terjadwal setiap 5 menit (`*/5 * * * *`) dan mencegah tumpang tindih (`withoutOverlapping`).
  - Dokumentasi lokal di `README.md` ditambahkan untuk cara menjalankan scheduler (`php artisan schedule:work`) dan perintah manual (`php artisan bookings:expire-pending`).
- Tes: 241/241 lulus, 2196 assertions di PostgreSQL `kamarin_test` (5 tes baru di `ExpirePendingBookingsTest`).
- Formatter: `composer run lint:check` (Pint) lulus (0 exit code).
- File kunci: `app/Console/Commands/ExpirePendingBookings.php`, `routes/console.php`, `tests/Feature/Console/ExpirePendingBookingsTest.php`, `README.md`.
- Penyimpangan dari dokumen sumber: tidak ada. Skema database dan dependensi tidak disentuh (`git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` kosong).
- Penjelasan untuk sidang (3-5 kalimat, bagaimana dan kenapa): Perintah pembatalan pesanan kedaluwarsa didelegasikan langsung ke `BookingService::expire()`, sehingga seluruh transisi status tetap tunduk pada transition guard dan kunci baris transaksi yang sama dengan alur bisnis lainnya. Pengecekan `expires_at IS NOT NULL` di query command memastikan bahwa pesanan offline yang dibuat oleh staf tidak akan pernah tersentuh oleh scheduler otomatis, sesuai aturan BR-13. Setiap pemrosesan pesanan dalam perulangan dibungkus blok try-catch mandiri agar kegagalan pada satu pesanan tidak menghentikan pesanan lainnya yang juga harus dibatalkan. Penjadwalan dijalankan setiap lima menit dengan proteksi `withoutOverlapping` untuk mencegah proses bertabrakan jika terdapat antrean pesanan dalam jumlah besar.
- Reviewer: Opsional menurut backlog.

### T-014b Perbaikan kecil T-014: kunci jadwal 10 menit dan kode keluar gagal (2026-10-13)

- Cabang: `task/T-014b-schedule-lock`, dibuat dari `main` **`8b03f6f`** (setelah T-013 di-merge user ke `main`). `main` tidak disentuh.
- Status: selesai. Review: **Tidak perlu** menurut backlog.
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (aturan 10, 11, "Cara kerja"), `docs/tasks/BACKLOG_FASE4_5.md` (baris T-014b), `docs/business_rules_kamarin.md` (BR-13), `docs/rencana_pengujian.md` (EX-05), dokumentasi resmi Laravel 13 (Task Scheduling, bagian *Preventing Task Overlaps*).
- Yang dikerjakan:
  - `routes/console.php`: `->withoutOverlapping()` diubah menjadi `->withoutOverlapping(10)`, disertai komentar yang menjelaskan alasannya.
  - `app/Console/Commands/ExpirePendingBookings.php`: laporan akhir kini **selalu memuat kedua jumlah** (`Expired %d pending booking(s), %d failed.`) tanpa syarat, dan `return` berubah dari `Command::SUCCESS` tak bersyarat menjadi `$failedCount > 0 ? Command::FAILURE : Command::SUCCESS`. Docblock diperbarui agar kontrak keluaran ini terdokumentasi.
  - **Tidak ada perubahan pada logika bisnis**: seleksi booking, pemanggilan `BookingService::expire()`, try-catch per booking, `Log::warning`, dan penulisan status log semuanya tetap seperti semula. Sisa booking tetap diproses saat kegagalan parsial.
  - **Tes ditulis lebih dulu (merah) sebelum implementasi**, dua tes diubah/ditambah di `tests/Feature/Console/ExpirePendingBookingsTest.php`:
    - Tes jadwal diperkaya dengan `->and($event->expiresAt)->toBe(10)`, memeriksa kedaluwarsa kunci 10 menit.
    - Tes kegagalan parsial diubah menjadi `->expectsOutputToContain('Expired 1 pending booking(s), 1 failed.')` + `->assertFailed()`, memeriksa laporan sekaligus kode keluar non-nol. Nama tesnya diberi awalan `EX-05`.
- **Bukti merah (sebelum implementasi)** — kedua kegagalan ini justru membuktikan klaim di backlog:
  - `Failed asserting that 1440 is identical to 10.` → membuktikan bawaan framework memang **1440 menit = 24 jam**.
  - `Unexpected status code 0 was received. Failed asserting that 0 is not equal to 0.` → membuktikan perintah memang **selalu mengembalikan sukses**.
- **Sumber dokumentasi resmi (aturan 11 — diverifikasi, bukan diingat):**
  - **https://laravel.com/framework/docs/scheduling**, bagian **"Preventing Task Overlaps"**: *"If needed, you may specify how many minutes must pass before the 'without overlapping' lock expires. **By default, the lock will expire after 24 hours**: `Schedule::command('emails:send')->withoutOverlapping(10);`"*. ⚠️ Catatan: URL `laravel.com/docs/13.x/schedule` dan `/docs/12.x/schedule` **keduanya mengembalikan 404**; untuk Laravel 13 dokumen berada di `/framework/docs/scheduling`.
  - Konfirmasi kedua dari framework yang benar-benar terpasang (**`laravel/framework v13.35.0`**, dilihat lewat `composer show` dan file di `vendor/`):
    - `Illuminate\Console\Scheduling\ManagesAttributes.php:86` → `public $expiresAt = 1440;` (bawaan 1440 menit = 24 jam).
    - `Illuminate\Console\Scheduling\ManagesAttributes.php:184` → `public function withoutOverlapping($expiresAt = 1440, $releaseOnTerminationSignals = true)`.
    - `Illuminate\Console\Scheduling\CacheEventMutex.php:45` → `->lock($event->mutexName(), $event->expiresAt * 60)`. Perkalian `× 60` inilah **bukti keras bahwa satuannya menit** (dikonversi ke detik untuk lock cache).
- **Jebakan teknis yang ditemui dan perlu diingat:** percobaan pertama memakai dua `expectsOutputToContain` terpisah (`'Expired 1 pending booking(s)'` dan `'1 failed'`) dan gagal dengan `Output does not contain "1 failed"`. Penyebabnya bukan data, melainkan semantik Mockery di `Illuminate\Testing\PendingCommand.php:615-623`: tiap `expectsOutputToContain` mendaftarkan **satu** ekspektasi `doWrite`, dan **satu** panggilan `doWrite` hanya memuaskan **satu** ekspektasi. Karena kedua substring berada di **baris keluaran yang sama**, substring pertama menelannya. Solusinya satu pemeriksaan berisi keseluruhan baris laporan — yang juga lebih ketat karena memaksa format laporan persis.
- Keluaran mentah verifikasi:
  - Merah (2 tes gagal): `{"tool":"pest","result":"failed","tests":5,"passed":3,"assertions":29,"duration_ms":3644,"failed":2,...}`
  - Setelah implementasi: `{"tool":"pest","result":"passed","tests":5,"passed":5,"assertions":31,"duration_ms":117195}`
  - Seluruh suite: `{"tool":"pest","result":"passed","tests":270,"passed":270,"assertions":2351,"duration_ms":60672}`
  - Formatter: `> pint --parallel --test` lalu `{"tool":"pint","result":"passed"}`
  - Skema/dependensi: `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong** (tanpa keluaran).
- Catatan: jumlah assert naik **+1** (2350 → 2351) persis dari pemeriksaan `expiresAt` yang ditambahkan.
- File kunci: `routes/console.php`, `app/Console/Commands/ExpirePendingBookings.php`, `tests/Feature/Console/ExpirePendingBookingsTest.php`.
- Penyimpangan dari dokumen sumber: tidak ada. Skema database dan dependensi tidak disentuh.
- **Konsekuensi yang disengaja, bukan efek samping:** dengan kode keluar non-nol, `schedule:run` kini menandai tugas itu **gagal** ketika ada kegagalan parsial. Itu justru yang diinginkan — kegagalan cron harus terlihat oleh pemantauan, bukan hilang senyap. Tidak ada hook `onFailure` yang dipasang, jadi saat ini dampaknya hanya pada catatan hasil `schedule:run`.
- **Temuan pre-existing (di luar ruang lingkup T-014b, tidak diubah):** pesan commit merge `079edc2` di `main` rusak — isinya perintah multi-baris yang tergabung jadi satu: `Merge bgit status git checkout main git merge --no-ff task/T-014-expire-pending php artisan test git pushranch 'task/T-014-expire-pending'`. Memperbaikinya berarti menulis ulang riwayat `main` (force push) yang dilarang, jadi hanya dicatat di sini.
- **Catatan prosedural:** saat tugas dimulai `git status` **tidak bersih** — ada 3 file docs milik user yang berubah namun belum di-commit (`docs/business_rules_kamarin.md`, `docs/rencana_pengujian.md`, `docs/tasks/BACKLOG_FASE4_5.md`, memuat baris T-014b dan T-017 yang baru serta perbaikan baris 51 T-005b yang sudah diizinkan). Sesuai instruji "jangan mengedit `docs/`", ketiganya tidak disentuh dan tidak di-commit; hanya `docs/STATUS.md` yang diubah. Perubahan itu masih menggantung di working tree.
- Penjelasan untuk sidang (3-5 kalimat, bagaimana dan kenapa): `withoutOverlapping` bekerja dengan mengunci sebuah "mutex" di cache; tanpa argumen, lock itu baru kedaluwarsa setelah **24 jam (1440 menit)**. Artinya, bila satu kali proses scheduler mati di tengah jalan — listrik padam, proses dibunuh, atau server restart — lock yang tertinggal akan **memblokir kedaluwarsa booking selama sehari penuh**, padahal perintah ini seharusnya jalan tiap 5 menit dan durasi normalnya jauh di bawah itu. Karena itu diberi argumen `10`, sehingga lock hanya bertahan 10 menit: paling buruk hanya dua siklus yang terlewat, bukan 288 siklus. Perubahan kedua menyangkut **kegagalan parsial** — sebelumnya perintah selalu melaporkan sukses walaupun ada booking yang gagal, sehingga kegagalan itu tak terlihat oleh cron; kini laporan selalu menyebut kedua jumlah dan kode keluar jadi non-nol, sehingga pemantauan bisa mendeteksinya. Kedua perubahan sengaja dibuat tanpa menyentuh logika bisnis, karena transisi status tetap sepenuhnya milik `BookingService::expire()`.
- Reviewer: Tidak perlu menurut backlog (T-014b | Opsional | Tidak).

### T-016 RoomBlockService: membuat dan menghapus blokir kamar (2026-10-13)

- Cabang: `task/T-016-room-blocks`, dibuat dari `main` **`9058ad0`**. `main` tidak disentuh.
- **Merge T-014b ke `main` dijalankan atas perintah eksplisit user**, fast-forward `8b03f6f` → `9058ad0`, tanpa force push. Ini prasyarat yang diminta user ("dari main setelah T-014b di-merge").
- Status: selesai. Review: **Wajib** menurut backlog (T-016 | Wajib | Ya) — **reviewer terpisah masih harus dijalankan**.
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (aturan 3, 4, 8, 10, 11), `docs/tasks/BACKLOG_FASE4_5.md` (baris T-016), `docs/business_rules_kamarin.md` **v1.5** (BR-01 butir 1, 5, 6), `docs/user_stories.md` (US-08), `docs/rencana_pengujian.md` (AV-12), `docs/erd_v2_1.mermaid` (ROOM_BLOCKS), pola kunci di `BookingService` baris 187-192 dan `BookingChangeService::lockRooms`, serta pola hook pada tes BK-05.
- Yang dikerjakan:
  - **`app/Services/RoomBlockService.php` (baru)**: `block(Room, start, end, reason, actor): RoomBlock` dan `unblock(RoomBlock, actor): void`. Rentang **setengah terbuka `[start_date, end_date)`**, malam yang dicakup `start_date` sampai `end_date - 1`, mengikuti konvensi check-out yang dipakai booking sehingga hari check-out tidak ikut terblokir. Alasan wajib dan tidak boleh kosong. `created_by_user_id` selalu terisi pelaku walaupun kolomnya nullable di skema (US-08 "Alasan blokir tercatat").
  - **Otorisasi allow-list** `private const BLOCK_ROLES = [RoleName::Owner, RoleName::Manager]`, dibandingkan lewat `RoleName::tryFrom($roleName)`, sehingga peran tak dikenal ditolak, bukan lolos karena tidak tercantum di deny-list (AGENTS aturan 3 dan 8, sama seperti `BookingChangeService::assertMayChange`).
  - **Pola kunci (BR-01.5)**: `Room::query()->whereIn('id', ...)->orderBy('id')->with('roomType')->lockForUpdate()->get()`, lalu `assertRoomCanBeBlocked` dijalankan **di bawah** kunci, baru `RoomBlock::create`. `unblock()` juga mengunci baris kamar, karena menghapus blokir menambah ketersediaan dan wajib diserialisasi terhadap penulis bersamaan.
  - **`app/Services/AvailabilityService.php` dilengkapi** (file T-008): `assertRoomCanBeBlocked()` sudah ada tetapi **hanya memeriksa malam aktif**. Ditambahkan (a) pemeriksaan tumpang tindih **blokir-lawan-blokir** pada kamar yang sama, dan (b) parameter aditif `?int $excludeBlockId = null` supaya blokir yang baru ditulis tidak dianggap bertabrakan dengan dirinya sendiri. Pemanggil lama tidak berubah perilaku.
  - **Verifikasi kedua setelah menulis**: `block()` memanggil `assertRoomCanBeBlocked` lagi tepat setelah `RoomBlock::create`, masih di dalam transaksi yang sama, dengan `$block->getKey()` sebagai pengecualian. Alasannya bukan kelebihan hati-hati, melainkan **BR-01.6**: index parsial hanya menjaga booking melawan booking, jadi blokir **tidak punya jaring pengaman lapis ketiga** seperti booking. Bila ada yang menyelip di jeda antara cek ulang dan penulisan, pemeriksaan kedua ini menolak dan seluruh transaksi — termasuk baris penyelip itu — dibatalkan. Ini adaptasi pola BK-05 untuk kasus yang memang tak punya index.
  - **Tes ditulis lebih dulu** di `tests/Feature/Services/RoomBlockServiceTest.php` (9 tes).
- **Bukti merah**: `{"tool":"pest","result":"failed","tests":9,"passed":0,"assertions":4,"duration_ms":3355,"failed":4,"errors":5,...}` — semuanya `Target class [App\Services\RoomBlockService] does not exist.`
- Keluaran mentah verifikasi:
  - Setelah implementasi + perbaikan tes: `{"tool":"pest","result":"passed","tests":9,"passed":9,"assertions":36,"duration_ms":3052}`
  - Seluruh suite: `{"tool":"pest","result":"passed","tests":279,"passed":279,"assertions":2387,"duration_ms":47731}`
  - Formatter: `> pint --parallel` → `{"tool":"pint","result":"fixed",...}` lalu `> pint --parallel --test` → `{"tool":"pint","result":"passed"}`
  - Skema/dependensi: `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong** (tanpa keluaran).
- Tes yang menutupi aturan: AV-12 (blokir pada tanggal ber-`malam aktif` ditolak), US-08 (blokir kamar bebas mencatat alasan dan pelaku), rentang setengah terbuka (blokir bersebelahan boleh, tumpang tindih ditolak), alasan wajib, allow-list peran (resepsionis/housekeeping/peran tak dikenal ditolak, owner+manager lolos), **kunci baris kamar dengan `DB::listen`** (membuktikan `for update` benar-benar dikeluarkan **dan** urutannya mendahului cek ulang `booking_nights`), **hook di jeda antara cek-ulang dan penulisan** (`RoomBlock::creating` menyisipkan blokir balapan lewat `withoutEvents`, lalu menolak dan membuktikan tidak satu baris pun tersisa), blokir menutup ketersediaan untuk booking berikutnya lewat `AvailabilityService`, dan `unblock` mengembalikan ketersediaan sekaligus membatasi siapa yang boleh melakukannya.
- **Insiden yang dicatat jujur (bukan kegagalan kode):** sempat menjalankan **dua proses `php artisan test` bersamaan** pada `kamarin_test` — suite di latar belakang dan berkas terfilter di foreground. Keduanya melakukan `migrate:fresh` sehingga saling menjatuhkan tabel; muncul **23 error `relation "properties" does not exist`** dan beberapa **`Deadlock detected`**. **Kedua hasil itu batal total.** Dijalankan ulang sebagai satu proses dan lulus bersih. Pelajarannya tercatat: jangan pernah menjalankan dua proses tes ke satu database tes.
- **Tiga kegagalan merah yang berasal dari tes saya sendiri**, diperbaiki di tes bukan di kode: (1) `$source` dikirim sebagai `int` padahal `createBooking` menerima `?BookingSource` → `TypeError`; (2) `expect(RoomBlock::count())->toBe($index)` salah konsep karena penolakan tidak boleh meninggalkan baris apa pun; (3) helper `blockUser` membuat role duplikat dan kena `roles_name_unique`, diperbaiki dengan memakai ulang role yang sudah ada.
- File kunci: `app/Services/RoomBlockService.php`, `app/Services/AvailabilityService.php`, `tests/Feature/Services/RoomBlockServiceTest.php`.
- Penyimpangan dari dokumen sumber: tidak ada. Skema database dan dependensi tidak disentuh.
- **Penyimpangan prosedural**: `app/Services/AvailabilityService.php` (milik T-008) disentuh dengan penambahan aditif. Berbeda dari sekadar jalan keluar, metode `assertRoomCanBeBlocked()` memang dibuat untuk aturan BR-01.6 dan pemeriksaan blokir-lawan-blokir adalah bagian yang belum lengkap darinya.
- Penjelasan untuk sidang (3-5 kalimat, bagaimana dan kenapa): Blokir kamar diperlakukan **persis seperti booking** dari sisi teknis, yaitu sebagai penulis penempatan kamar, sehingga ia mengunci baris `rooms` dengan `SELECT ... FOR UPDATE` sebelum mengecek ulang ketersediaan — kalau tidak, dua staf bisa saling menyela dan menghasilkan blokir yang bertumpuk. Rentangnya **setengah terbuka**, `[start_date, end_date)`, karena itu konvensi yang sama dengan booking: hari check-out bukan malam menginap, jadi blokir 10 sampai 12 menutup malam 10 dan 11 saja, dan blokir kedua boleh mulai tepat dari tanggal 12. Yang paling penting untuk dipahami: **blokir tidak punya jaring pengaman di database**, karena unique index parsial di `booking_nights` hanya menjaga booking terhadap booking (BR-01.6) — karena itu ketersediaan diperiksa dua kali, sebelum dan sesudah penulisan, di dalam satu transaksi. **Perlu ditafsirkan tepat**, jangan sampai dikoreksi penguji: cek kedua itu **bukan** jaring pengaman terhadap transaksi bersamaan — di bawah Read Committed, PostgreSQL hanya menampilkan data yang sudah ter-commit, jadi baris konflik milik koneksi lain yang belum ter-commit tidak terlihat dan tidak bisa digulung oleh kita. Cek kedua menutup jeda terhadap penulis yang **tidak mengunci** kamar dan sudah ter-commit lebih dulu; terhadap penulis bersamaan yang patuh pada BR-01.5, penjaga sesungguhnya adalah kunci `FOR UPDATE` yang memaksa mereka menunggu lalu mengecek ulang sendiri setelah kita selesai. Terakhir, otorisasi memakai **allow-list**, bukan deny-list: hanya `owner` dan `manager` yang terdaftar, sehingga peran baru yang belum dikenal sistem justru otomatis ditolak, bukan lolos begitu saja.
- Reviewer: **Wajib** menurut backlog, sudah dijalankan oleh reviewer independen (agent terpisah yang tidak menulis kode). **Verdict: LULUS, tanpa temuan BLOCKING.** Rinciannya di bagian "Review T-016" di bawah.

### Review T-016 (2026-10-13)

Reviewer independen membaca ulang ERD v2.1, BR-01 butir 5 dan 6, US-08, AV-12, dan seluruh diff terhadap `9058ad0`, lalu menjalankan sendiri suite, Pint, dan diff skema. Verdict: **LULUS — 0 temuan BLOCKING**. Skema, enum, penempatan logika, pola kunci BR-01.5, BR-01.6, rentang setengah terbuka, otorisasi, dan kualitas tes semuanya dinyatakan lulus. phpstan tetap **123 error** — identik dengan angka sebelum T-016, sehingga **T-016 menambah 0 error**; `app/Services/RoomBlockService.php` tidak muncul di daftar error sama sekali.

**Temuan yang sudah diperbaiki oleh Builder:**

1. **INFO terpenting — koreksi penjelasan, bukan kode.** Klaim lama di komentar `RoomBlockService` dan di paragraf sidang ("apa pun yang menyelip di jeda ikut tergulung") **kelewat jauh**. Di bawah Read Committed, PostgreSQL hanya menampilkan data yang sudah ter-commit (https://www.postgresql.org/docs/current/transaction-iso.html §13.2.1), sehingga cek kedua **tidak melihat** dan **tidak bisa menggulung** baris konflik milik koneksi lain yang belum ter-commit. Versi benar: cek kedua menutup jeda terhadap penulis yang **tidak mengunci** kamar dan sudah ter-commit lebih dulu; terhadap penulis bersamaan yang patuh pada BR-01.5, penjaga sesungguhnya adalah `FOR UPDATE`. Komentar dan paragraf sidang kini ditulis ulang — ini yang paling penting karena bagian itu dibacakan saat sidang dan gampang dikoreksi penguji bila salah.
2. **NIT — penamaan ID tes.** Tes kunci memakai awalan `AV-12:`, padahal AV-12 sudah dipakai tes lain, sehingga dua tes berbeda mengklaim satu ID kasus uji (melanggar AGENTS aturan 10). Diganti jadi `BR-01.5: locks the room row for update before rechecking availability`.
3. **NIT — panjang alasan.** `room_blocks.reason` `varchar(255)` tetapi hanya kekosongan yang diperiksa, jadi alasan > 255 karakter berujung `QueryException`, bukan galat domain. Ditambahkan pemeriksaan `mb_strlen > 255`, nama metode disesuaikan jadi `assertReasonIsUsable()`.
4. **NIT — validasi rentang ganda tanpa tes.** `RoomBlockService` melempar `BookingException` sementara `stayNights` melempar `AvailabilityException` untuk kondisi rentang terbalik yang sama, dan tidak ada tes yang menutupinya. Ditambahkan tes `refuses a reversed or empty range`.
5. **NIT — salah ketik di STATUS.md.** "Tes jadwal kunci" (istilah sisa T-014b) → "Tes kunci baris kamar".

**Temuan yang TIDAK diubah, beserta alasannya:**

- **INFO — kamar `maintenance` dan tanggal lampau tetap bisa diblokir** (`assertRoomCanBeBlocked` tidak memeriksa `rooms.status`). Reviewer menilai ini memang perilaku yang diinginkan: blokir berarti mengeluarkan dari penjualan, bukan menjual, dan ketersediaan masa lalu tidak pernah dijual (BR-01.4). **Keputusan: dibiarkan, dan kini dicatat di sini** — reviewer justru menemukan bahwa keputusan ini sebelumnya tidak tertulis di mana pun, jadi nilainya baru disadari.
- **INFO — tidak ada operasi mengganti rentang blokir.** Memperpanjang blokir (mis. 10-12 → 10-14) ditolak karena tumpang tindih dengan blokirnya sendiri; jalurnya `unblock` lalu `block` ulang, yang membuka kamar selama jeda. Ini friction, bukan cacat integritas. **Catatan untuk tugas UI blokir: sebaiknya ada operasi ganti rentang atomik.**
- **NIT — `lockRooms(...)[0]` tanpa pengawan.** Mustahil praktis (kamar tidak pernah dihapus) dan pola yang sama sudah dipakai `BookingChangeService`. Tidak diubah supaya konsisten dengan codebase.
- **INFO — belum ada `Gate`/`Policy` sama sekali di `app/`.** AGENTS aturan 8 baru terpenuhi sebagian, tetapi konsisten dengan codebase (semua otorisasi domain ada di service) dan memang belum ada route untuk dijaga. Saat HTTP layer dibuat, allow-list yang sama harus dijajarkan lewat Gate/Policy — dokumentasi resmi Laravel menegaskan authorization harus ditangani di server (https://laravel.com/docs/authorization).
- **INFO pre-existing, di luar lingkup T-016** — `BookingService::releaseNights` menonaktifkan malam tanpa mengunci baris kamar. Arahnya hanya membebaskan ketersediaan sehingga tidak pernah membuat bentrok, jadi maksud BR-01.5 untuk T-016 tetap terpenuhi; dicatat bila aturan itu dibaca harfiah.

**Keluaran mentah setelah perbaikan review (dijalankan Builder, satu proses tes):**

- `php artisan test` (suite penuh) → `{"tool":"pest","result":"passed","tests":281,"passed":281,"assertions":2395,"duration_ms":50237}`
- `php artisan test tests/Feature/Services/RoomBlockServiceTest.php` → `{"tool":"pest","result":"passed","tests":11,"passed":11,"assertions":44,"duration_ms":3565}` (naik dari 9 ke 11 tes)
- `composer run lint` → `{"tool":"pint","result":"passed"}` (tanpa file yang perlu diperbaiki)
- `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` → **(tanpa keluaran)**

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
| T-011b Check-in lewat batas no-show + T-012 PaymentService | 2026-10-13 | Selesai, review LULUS (tiga putaran) | `a374e6f` (merge ke main) |
| T-001 Setup lingkungan Laravel 13 + React + PostgreSQL | 2026-10-10 | Selesai, review lulus setelah perbaikan port | `32fac10` |
| T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu | 2026-10-11 | Selesai | `b66530f` |
| T-002 Pruning autentikasi starter kit | 2026-10-11 | Selesai, review lulus | `2d1233c` (squash-merge di main; cabang asli `task/T-002-auth-cleanup`) |
| T-004 Migration tahap 1 (master) + tes kesesuaian skema | 2026-10-11 | Selesai (termasuk amandemen ERD nullable + expiry 3 jam) | `5124cf1` |
| T-005 Migration tahap 2 (transaksi) + pengaman double booking | 2026-10-11 | Selesai | `fee2337` |
| T-005b Penguatan skema: FK gabungan + 12 CHECK kewajaran | 2026-10-11 | Selesai | `e58b8ac` |
| Review mandiri T-005 + T-005b (semua fokus lulus, termasuk FK exact-set) | 2026-10-12 | Selesai | `52a5d6c` (merge ke main) |
| T-006 Enum, model, relasi, dan factory | 2026-10-12 | Selesai, review LULUS, di-merge & di-push | `6112b76` (tercatat di `743d8eb`) |
| T-008 AvailabilityService + tes AV-01..AV-13 | 2026-10-12 | Selesai, review LULUS, di-merge & di-push | `b487153` (tercatat di `743d8eb`) |
| T-009 PricingService + tes PR-01..PR-11 | 2026-10-12 | Selesai, review LULUS, di-merge & di-push | `99699ff` (tercatat di `be191f3`) |
| T-010 BookingService + tes BK-01..BK-08 | 2026-10-12 | Selesai, review LULUS dua kali, di-merge & di-push | `b81ed5c` (tercatat di `b2f5eca`) |
| T-010b Perbaikan review T-009/T-010 (email, tipe nonaktif, BookingCode) | 2026-10-12 | Selesai, tidak di-review (Opsional), di-merge user ke `main` | `d46892e` |
| T-011 Transisi status dan transition guard | 2026-10-12 | Selesai, review LULUS (putaran 1 TIDAK LULUS, putaran 2 LULUS), di-merge user (fast-forward) & di-push | `2e6ceda` |
