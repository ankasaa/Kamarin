# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-13

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-014 (Perintah kedaluwarsa booking pending dan penjadwalannya) SELESAI di `task/T-014-expire-pending`**, dibuat dari `main` **`a374e6f`** (setelah T-011b + T-012 di-merge ke main).
- Isi T-014: Perintah console `app/Console/Commands/ExpirePendingBookings.php` (`bookings:expire-pending`, BR-13) memanggil `BookingService::expire()` per booking pending website yang telah melewati batas `expires_at` (pelaku sistem, `changed_by_user_id` null). Looping dibungkus try-catch per booking agar kegagalan parsial tidak menghentikan pemrosesan pesanan lain. Dijadwalkan di `routes/console.php` setiap 5 menit tanpa tumpang tindih (`Schedule::command(...)->everyFiveMinutes()->withoutOverlapping()`). Dokumentasi scheduler lokal (`php artisan schedule:work`) ditambahkan ke `README.md`.
- Hasil verifikasi: Tes ditulis lebih dulu dan merah bersih; setelah implementasi, seluruh suite **241/241 tes, 2196 assert** lulus di PostgreSQL `kamarin_test` (5 tes baru di `tests/Feature/Console/ExpirePendingBookingsTest.php`: EX-01, EX-04, EX-05 idempotensi, recovery kegagalan parsial, schedule assertion); `composer run lint:check` (Pint) lulus; `git diff --stat main -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong** (skema dan dependensi tidak berubah).
- File kunci: `app/Console/Commands/ExpirePendingBookings.php`, `routes/console.php`, `tests/Feature/Console/ExpirePendingBookingsTest.php`, `README.md`.
- Langkah berikutnya: T-013 (BookingChangeService, review Wajib).
- Perhatian user yang tetap dicatat:
  1. Pertanyaan terbuka no. 3 (`lockForUpdate` sisi `room_blocks` untuk T-016) tetap menggantung.
  2. Catatan non-blocking / NIT dari tugas T-012 (race `record()` refund-manual bersamaan `settleCancellation()`, race verify vs cancel).

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` **v1.4** (perubahan v1.4: keputusan M4 refund manual O/M, M5 referensi wajib transfer bank/OTA, M6 refund manual verified saat dicatat), keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006, T-008, T-009, T-010, T-010b, T-011, T-011b, T-012 **selesai + di-merge ke main** (`a374e6f`); **T-014 selesai** di `task/T-014-expire-pending`. T-007 (seeder) ditunda atas keputusan user. **Berikutnya: T-013** BookingChangeService |
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
3. **(T-010, terbuka)** Temuan I5 review T-010: `lockForUpdate` (lapis 2 BR-01) saat ini **hanya** ada di `BookingService::createBooking`. Sisi pembuatan `room_blocks` (BR-01.6: index parsial hanya menjaga booking terhadap booking, jadi booking vs blokir dijaga di service) belum mengunci baris kamar, sehingga race "booking dibuat bersamaan dengan blokir kamar yang sama" secara teori masih bisa lolos. Di luar ruang lingkup T-010 (tidak ada kode blokir di repo sekarang). Pertanyaan untuk user: apakah tugas pembuatan blokir kamar nanti (baris T-016) juga wajib mengunci baris kamar dengan `lockForUpdate` mengikuti pola `BookingService`? **Menjelang T-016, belum dijawab.**
4. ~~**(T-012, terbuka)** Temuan M4 review T-012: `record()` menerima `PaymentType::Refund`...~~ → **TERJAWAB oleh user di T-012 (M4/M6).**
5. ~~**(T-012, terbuka)** Temuan M5 review T-012: `record()` membuat `reference_number` nullable...~~ → **TERJAWAB oleh user di T-012 (M5).**

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
