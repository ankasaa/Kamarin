# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-13

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-013 (BookingChangeService: ubah tanggal, pindah kamar, jumlah tamu) SELESAI di `task/T-013-booking-change-service`**, dibuat dari `main` **`079edc2`** (setelah T-014 di-merge ke main). `main` tidak disentuh.
- Isi T-013: `app/Services/BookingChangeService.php` berisi `changeDates()` (memuat geser), `extend()`, `shorten()`, `moveRoom()`, `changeGuestCount()` dan `preview()` tanpa menulis. Semua transaksi berjalan dalam urutan tetap: baca ulang booking dengan `lockForUpdate`, kunci baris kamar dengan `lockForUpdate` berurutan id (keputusan Pertanyaan terbuka no. 3), jalankan ulang `AvailabilityService::assertValidStay()` dengan `excludeBookingId` (malam milik booking sendiri bukan bentrok), cek ulang `minimum_nights`, baru tulis diff malam + `booking_change_logs`. Malam yang tetap berada di rentang tidak disentuh sehingga harganya terkunci (K3); hanya malam baru yang dihargai `PricingService`. Otorisasi memakai allow-list `RoleName` (owner, manager, receptionist) dan alasan wajib untuk semua peran (K4). Hasil dikembalikan sebagai `App\Support\BookingChangeResult` berisi `oldTotal`, `newTotal`, `PaymentSummary`, daftar warning (`overpaid` dari BR-12.3) dan log yang ditulis.
- Hasil verifikasi: Tes ditulis lebih dulu (19 tes, merah bersih: 9 error class-not-found + 10 binding resolution); setelah implementasi, seluruh suite **260/260 tes, 2300 assert** lulus di PostgreSQL `kamarin_test`; `composer run lint:check` (Pint) lulus; `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong** (skema dan dependensi tidak berubah).
- File kunci: `app/Services/BookingChangeService.php`, `app/Support/BookingChangeResult.php`, `tests/Feature/Services/BookingChangeServiceTest.php`.
- Langkah berikutnya: T-015 (atau sesuai urutan backlog). T-013 berstatus **Wajib / Review Claude: Ya** menurut baris kebijakan review.
- Perhatian user yang tetap dicatat:
  1. **Pertanyaan terbuka no. 3 sebagian terjawab** (keputusan user saat T-013): setiap kode yang membuat atau mengubah penempatan kamar wajib mengunci baris kamar dengan `lockForUpdate` mengikuti pola `BookingService::createBooking` — sudah diterapkan di `BookingChangeService`. **Sisi `room_blocks` (T-016) mengikuti aturan yang sama nanti, masih menggantung.**
  2. **Pertanyaan terbuka no. 6 baru**: pindah antar tipe kamar — BR-06 mendefinisikannya, baris T-013 mengeluarkannya dari MVP. Butuh konfirmasi user.
  3. **Temuan pre-existing**: baris 51 `docs/tasks/BACKLOG_FASE4_5.md` (T-005b) punya 5 kolom di tabel 3 kolom. Belum diubah, butuh izin user.
  4. Catatan non-blocking / NIT dari tugas T-012 (race `record()` refund-manual bersamaan `settleCancellation()`, race verify vs cancel).

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` **v1.4** (perubahan v1.4: keputusan M4 refund manual O/M, M5 referensi wajib transfer bank/OTA, M6 refund manual verified saat dicatat), keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006, T-008, T-009, T-010, T-010b, T-011, T-011b, T-012 **selesai + di-merge ke main** (`04c2c7e`); **T-014 selesai + di-merge ke main** (`079edc2`); **T-013 selesai** di `task/T-013-booking-change-service`. T-007 (seeder) ditunda atas keputusan user. **Berikutnya: review T-013** (Wajib) lalu T-015 / T-016 |
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
6. **(T-013, terbuka — perlu konfirmasi user)** BR-06 mendefinisikan **pindah antar tipe kamar**: "malam yang dipindah dihitung ulang dengan tarif tipe baru dan selisihnya ditampilkan sebelum disimpan", sedangkan baris T-013 di backlog menuliskan lingkup MVP "**Tidak** mendukung ... pindah antar tipe kamar". Karena baris tugas lebih spesifik dan lebih baru, T-013 saat ini hanya menerima pindah kamar **dalam tipe yang sama** dan menolak tipe berbeda dengan pesan eksplisit (tes CH-08 "refuses to move a booking into a room of a different type"). Konsekuensinya selisih harga antar tipe belum pernah dihitung maupun ditampilkan. **Berhenti di bagian ini: belum ada kode pindah antar tipe, menunggu keputusan user** apakah tetap di luar MVP atau menjadi bagian T-013.
   - Terkait, keputusan lingkup dari baris T-013: `moveRoom()` hanya menangani booking yang memegang **satu kamar** (menolak booking multi-kamar), karena menambah atau menghapus kamar pada booking multi-kamar juga dinyatakan di luar lingkup. Ubah tanggal sendiri tetap berlaku untuk semua kamar booking.

### Temuan pre-existing (belum diubah, butuh izin user)

> Tanda ini ditandai supaya tidak hilang di antara entri tugas. Semua isi di bawah ini **bukan** hasil kerja tugas berjalan.

1. **`docs/tasks/BACKLOG_FASE4_5.md` baris 51 (T-005b)**: baris itu tertulis sebagai **5 kolom** (`| T-005b | Penguatan skema booking ... | ERD v2.1 ... | Tes membuktikan ... | Ya |`) padahal tabel "Kebijakan review berbasis risiko" hanya punya **3 kolom** (`Tugas | Reviewer agent | Review Claude`). Isinya tampaknya tersalin dari tabel deskripsi tugas, dan kolom reviewer yang benar mestinya `Wajib`. Baris 56 (`T-016`) sudah diperbaiki dengan izin user di `a374e6f`; baris 51 belum, karena perbaikan `docs/` di luar `STATUS.md` butuh izin. **Menunggu keputusan user.**

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
- Status: selesai. Kebijakan review: **Wajib** (baris T-013 di `docs/tasks/BACKLOG_FASE4_5.md`).
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
  - **19 tes, pemetaan ke kasus uji:** CH-01 (perpanjangan, harga lama terkunci, tarif dinaikkan setelah booking dibuat), CH-02 (malam baru bentrok → dicek ulang `booking_nights`, `total_amount` dan `booking_change_logs` = 0, bukan hanya exception-nya), CH-03 (percepat check-out → malam sisa nonaktif, total turun), CH-04 dua tes (geser sukses tanpa menganggap malam sendiri bentrok + geser menabrak booking lain tetap ditolak), CH-05 (alasan kosong/spasi untuk owner, manager, receptionist), CH-06 (satu baris log per perubahan berisi jenis, nilai lama, nilai baru, alasan, pelaku — dua perubahan menghasilkan dua baris), CH-07 (receptionist ditolak, owner dan manager lolos), CH-08 dua tes (pindah kamar tipe sama harga tetap + tipe berbeda ditolak), CH-09 (checked_in ubah check-in ditolak, perpanjang berhasil), CH-10 (kelebihan bayar ditandai lewat `PaymentService::summary`), CH-11 (`expires_at` tidak berubah), BR-06 status akhir (4 status), BR-06 allow-list peran, BR-01.2 jumlah tamu, T-013 pratinjau dua tes, dan bukti `lockForUpdate` pada baris kamar.
- Tes — keluaran mentah `php artisan test`:
  ```
  {"tool":"pest","result":"passed","tests":260,"passed":260,"assertions":2300,"duration_ms":66813}
  ```
  260/260 lulus (19 tes baru di `BookingChangeServiceTest`), di PostgreSQL `kamarin_test`, bukan SQLite.
- Formatter — keluaran mentah `composer run lint:check`:
  ```
  > pint --parallel --test
  {"tool":"pint","result":"passed"}
  ```
  Pint memperbaiki `app/Services/BookingChangeService.php` pada `composer run lint` di antara dua run di atas; `git status --porcelain` sesudahnya hanya menunjukkan tiga file baru T-013, tidak ada file lama yang berubah.
- Verifikasi keluaran kedua setelah formatter: `{"tool":"pest","result":"passed","tests":260,"passed":260,"assertions":2300,"duration_ms":41469}` dan `{"tool":"pint","result":"passed"}`.
- File kunci: `app/Services/BookingChangeService.php`, `app/Support/BookingChangeResult.php`, `tests/Feature/Services/BookingChangeServiceTest.php`.
- Dependensi dan skema: `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong**. Tidak ada paket baru (aturan 11) dan tidak ada perubahan tabel/kolom (aturan 1).
- Penyimpangan dari dokumen sumber: tidak ada pada skema, aturan, maupun dependensi. Dua hal adalah **keputusan lingkup yang diturunkan dari baris T-013 sendiri** dan dicatat di "Pertanyaan terbuka" no. 6: pindah antar tipe kamar ditolak (BR-06 mendefinisikannya, baris T-013 mengeluarkannya dari MVP), dan `moveRoom()` hanya menerima booking satu kamar. Tidak ada controller, halaman, atau seeder yang dibuat.
- Penjelasan untuk sidang (3–5 kalimat, bagaimana dan kenapa): Setiap perubahan booking dijalankan di dalam satu transaksi yang lebih dulu mengunci baris booking lalu baris kamar secara berurutan berdasarkan id, sehingga dua staf yang mengubah kamar atau tanggal yang sama saling menunggu alih-alih menulis bersamaan dan memicu double booking. Malam yang tetap berada dalam rentang menginap tidak disentuh sama sekali sehingga harganya tetap terkunci sesuai keputusan K3, sementara hanya malam baru yang dihitung ulang memakai tarif yang berlaku pada saat perubahan dilakukan. Urutan penulisannya sengaja menonaktifkan malam yang keluar lebih dulu sebelum menyisipkan malam baru, karena unique index parsial `(room_id, stay_date) WHERE is_active` akan menolak malam baru bila malam lama untuk tanggal yang sama masih aktif — persis yang terjadi pada pergeseran tanggal yang saling tumpang tindih. Seluruh penolakan (alasan kosong, peran di luar allow-list, status akhir, dan minimum_nights) diperiksa di dalam service, jadi controller tetap tipis dan tidak ada aturan bisnis yang bocor ke lapisan HTTP. Hasil setiap perubahan berupa satu baris `booking_change_logs` dan satu objek hasil berisi total lama, total baru, dan peringatan kelebihan bayar, sehingga staf selalu punya jejak audit dan alasan yang bisa dijelaskan.
- Reviewer: **Wajib** menurut backlog — agent lain atau Claude, belum dijalankan.

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
