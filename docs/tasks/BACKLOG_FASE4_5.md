# Backlog Tugas Fase 4 dan 5

Setiap tugas dikerjakan satu per satu di cabang `task/T-xxx-slug` oleh satu agent (Builder), lalu di-review agent lain (Reviewer). Aturan umum ada di `AGENTS.md`. Kolom "Review Claude" menandai tugas inti yang sebaiknya juga di-review di chat Claude, karena kesalahan di sana paling mahal.

## Template tugas

Untuk tugas baru, salin dan isi:

```
### T-xxx Judul
Tujuan:
Rujukan wajib dibaca: (BR-xx, tabel ERD, ID kasus uji, cerita US-xx)
Ruang lingkup (boleh disentuh):
Di luar ruang lingkup:
Kriteria selesai:
Cara memverifikasi: (perintah tes, hasil yang diharapkan)
```

## Daftar tugas

| ID | Tugas | Rujukan | Kriteria selesai | Review Claude |
| --- | --- | --- | --- | --- |
| T-001 | Setup lingkungan: pasang Laravel installer, buat proyek dengan `laravel new` (starter kit React, autentikasi bawaan Laravel bukan WorkOS, tanpa teams) di folder sementara lalu pindahkan ke root repo tanpa menimpa `docs/` dan `AGENTS.md`. PostgreSQL berjalan di Docker (compose). Git dan salin `docs/` | `arsitektur_sistem.md` bagian 5-6, `AGENTS.md` bagian Lingkungan | Container PostgreSQL berjalan, koneksi database berhasil, `composer run dev` berjalan dan aplikasi terbuka, `.env.example` ada (termasuk timezone), pilihan installer dan versi dicatat di STATUS.md beserta sumber dokumentasinya | Tidak perlu |
| T-002 | Konfigurasi autentikasi starter kit: nonaktifkan registrasi publik, 2FA, verifikasi email, dan passkeys, bersihkan referensi frontend, migration, dan kolom yang tidak dipakai, pertahankan login, logout, dan reset password. Gunakan mekanisme pruning resmi starter kit (`laravel/chisel`) bila tersedia, dan verifikasi cara pakainya di dokumentasi paket sebelum menjalankannya | `AGENTS.md` aturan 13, US-01, US-02 | `npm run build` lulus, login, logout, dan reset password berjalan, tidak ada rute registrasi publik. Tes bawaan untuk fitur yang dibuang dihapus atau disesuaikan, bukan dilewati | Opsional |
| T-003 | Pindahkan tes ke PostgreSQL: Pest 4 (sudah dipilih), database tes `kamarin_test`, formatter kode (bawaan), helper pembeku waktu | `rencana_pengujian.md` bagian 2-3, `AGENTS.md` aturan 10 | Seluruh tes bawaan lulus di PostgreSQL `kamarin_test`, bukan SQLite. Ada pengaman yang menggagalkan tes jika nama database tidak berakhiran `_test`, agar tes tidak menghapus data development. Perintah tes ada di README. Disarankan dikerjakan sebelum T-002 | Tidak perlu |
| T-004 | Migration tahap 1 (master): roles, users, properties (beserta kolom pengaturan), room_types, rooms, amenities, room_type_amenities, housekeeping_statuses, guests, booking_sources, rates. Tabel `users` bawaan starter kit diubah langsung di migration aslinya (belum ada data produksi). Urutan: `roles` dan `properties` sebelum `users`. Boleh mengganti timestamp nama file migration `users` agar urutannya benar, dan jelaskan di STATUS.md. Kolom uang memakai `decimal(12,0)` (rupiah bulat) dengan cast integer di model | ERD v2.1, BR bagian 1 (mata uang), `AGENTS.md` aturan 13, US-05, US-06, US-07 | Tabel dan kolom persis seperti ERD (ditambah kolom bawaan framework yang diizinkan), unik `(property_id, room_number)`, `migrate:fresh` berhasil di PostgreSQL. **Tes kesesuaian skema (Pest, PostgreSQL):** mem-parsing `docs/erd_v2_1.mermaid`, membandingkan nama tabel, nama kolom, dan tipe dengan `information_schema`, memastikan kolom yang ditandai `nullable` di ERD memang nullable, dan menolak tabel atau kolom di luar ERD kecuali yang diizinkan aturan 13. Tabel transaksi yang belum dimigrasi dicatat di daftar pengecualian eksplisit yang dikosongkan di T-005 | Opsional |
| T-005 | Migration tahap 2 (transaksi): bookings, booking_items, booking_nights, booking_charges, room_blocks, payments, booking_status_logs, booking_change_logs | ERD v2.1, BR-01, `arsitektur_sistem.md` bagian 7 | Termasuk unique index parsial `(room_id, stay_date) WHERE is_active`, CHECK tanggal (`check_out_date > check_in_date`, akhir blokir setelah awal), dan indeks bagian 7. Tes BK-05 (insert duplikat ditolak database) lulus. Daftar pengecualian di tes kesesuaian skema dikosongkan dan tes tetap lulus untuk semua 19 tabel ERD | Ya |
| T-005b | Penguatan skema booking. (1) Tambah unique `(id, room_id)` di `booking_items` dan ganti FK `booking_nights.booking_item_id` menjadi FK gabungan `(booking_item_id, room_id)` ke `booking_items(id, room_id)` dengan `ON DELETE NO ACTION` dan `ON UPDATE CASCADE`, sehingga malam tidak bisa menunjuk kamar yang berbeda dari item induknya dan pemindahan kamar cukup mengubah `booking_items.room_id`. (2) CHECK kewajaran data dengan nama eksplisit lewat `DB::statement`: `bookings` (`guest_count > 0`, `total_amount >= 0`), `booking_items` (`subtotal >= 0`), `booking_nights` (`price >= 0`), `payments` (`amount > 0`), `rates` (`price >= 0`, `minimum_nights >= 1`, tanggal akhir tidak sebelum tanggal awal bila keduanya ada), `room_types` (`capacity > 0`), `properties` (`deposit_percent` 0 sampai 100, `pending_expiry_hours > 0`, `free_cancel_days >= 0`). Tanpa CHECK pada `booking_charges.amount` karena diskon bernilai negatif | ERD v2.1, BR-01, BR-06, BR-12, `AGENTS.md` aturan 4 | Tes membuktikan: malam dengan `room_id` berbeda dari item induknya ditolak. Mengubah `booking_items.room_id` ikut memindahkan malamnya dan tetap ditolak bila kamar tujuan sudah terisi pada tanggal itu. Tiap CHECK menolak nilai buruk (tes berdataset) dengan nama constraint yang benar. Seluruh tes lulus, aturan hapus FK tetap `NO ACTION`, `migrate:fresh` dan `migrate:rollback` berhasil | Ya |
| T-006 | Enum, model, relasi, dan factory | ERD v2.1, `AGENTS.md` aturan 2 | Nilai enum sama dengan ERD, relasi sesuai ERD, factory untuk semua model, cast integer untuk semua kolom uang (`decimal(12,0)`), cast tanggal untuk kolom date, soft delete pada `Booking` dan `Guest` | Opsional |
| T-007 | Seeder dasar: roles, booking_sources, satu properti dengan pengaturan default, akun demo per peran, contoh tipe kamar, kamar, dan tarif | BR-09, BR-11, `rencana_pengujian.md` bagian 3 | Tanpa data pribadi nyata, `id_number` kosong, tarif contoh memuat default, akhir pekan, musim ramai, dan kombinasi | Tidak perlu |
| T-008 | AvailabilityService beserta tes | BR-01, BR-07, AV-01 sampai AV-13, US-13 | Semua tes AV lulus. Turnover hari yang sama diizinkan, blokir dan booking aktif menghalangi, housekeeping tidak memengaruhi tanggal mendatang | **Ya** |
| T-009 | PricingService beserta tes | BR-11, BR-12, PR-01 sampai PR-11 | Semua tes PR lulus. Harga per malam, priority, `days_of_week` ISO, minimum_nights dari tarif malam check-in, harga terkunci | **Ya** |
| T-010 | BookingService: membuat booking dalam transaksi (kunci baris, cek ulang, simpan nights, status log, kode booking) | BR-01, BR-13, BR-14, BK-01 sampai BK-08, US-12, US-13 | Semua tes BK lulus. Termasuk pengujian konkurensi atau, jika tidak bisa diotomatisasi, tes penanganan pelanggaran unique index dengan pesan yang benar (dicatat jujur di STATUS.md) | **Ya** |
| T-010b | Perbaikan kecil hasil review T-009 dan T-010: (1) normalisasi email (trim dan huruf kecil) saat menyimpan dan mencari tamu di `findOrCreateGuest`, tanpa pencocokan otomatis lewat telepon; (2) `assertHasDefaultRate` hanya untuk tipe kamar aktif, dan `AvailabilityService` menganggap kamar bertipe nonaktif tidak tersedia (AV-14 baru); (3) pindahkan pembuatan kode booking ke satu kelas (mis. `App\Support\BookingCode`) yang dipakai `BookingService` dan `BookingFactory` agar data uji memenuhi BR-14 | BR-01, BR-07 butir 6, BR-14, bagian Data dan privasi, AV-14, BK-07, US-21 | Tes untuk setiap butir lulus (email `' A@X.test '` dan `'a@x.test'` menghasilkan tamu yang sama, kamar bertipe nonaktif tidak tersedia, factory menghasilkan kode sesuai BR-14). Seluruh suite hijau, skema tidak diubah | Opsional |
| T-011 | Transition guard dan transisi status (confirm, cancel, check-in, check-out, no-show, expire) beserta log status | Business Rules bagian 5, ST-01 sampai ST-09 | Semua tes ST lulus. Matriks status x peran dipenuhi, status akhir tidak dapat dibuka kembali | **Ya** |
| T-011b | Perbaikan tafsir: `checkIn` tidak lagi menolak setelah `no_show_cutoff_time`. Batas itu hanya menggerbang `markNoShow`. Setelah batas, check-in booking confirmed berhasil dengan peringatan | BR-08 butir 3, baris matriks confirmed ke checked_in, ST-10 | Tes lama yang menolak check-in setelah batas diubah menjadi tes yang memastikan check-in berhasil dengan peringatan (ST-10). `markNoShow` tetap hanya boleh setelah batas. Seluruh suite hijau. Dikerjakan sebagai commit pertama sebelum T-012 | Ya (Reviewer) |
| T-012 | PaymentService: DP wajib, terbayar, sisa tagihan, status bayar, penanda kelebihan bayar, pencatatan dan verifikasi pembayaran, refund, saran perlakuan DP saat pembatalan. Pindahkan `verifiedDeposit()` dan `netPaid()` dari `BookingService` ke sini sebagai satu-satunya definisi, dan sambungkan penegakan saran DP ke `BookingService::cancel` | BR-03, BR-04, BR-05, BR-12, PY-01 sampai PY-08, matriks status (Keterangan 2) | Semua tes PY lulus. Hanya pembayaran verified yang dihitung. Pembulatan DP tanpa float. Refund = baris baru bertipe refund berstatus verified, baris asli tetap verified. Resepsionis tidak boleh menyimpang dari saran DP, Owner dan Manager boleh. Tes `BookingService` yang ada tetap lulus tanpa dilonggarkan | Opsional |
| T-013 | BookingChangeService, lingkup MVP: ubah tanggal (perpanjang, percepat, geser), pindah kamar **dalam tipe yang sama**, ubah jumlah tamu, pratinjau perubahan tanpa menulis, dan change log. **Tidak** mendukung tambah atau hapus kamar pada booking multi-kamar, pindah antar tipe kamar, atau pertukaran kamar dua booking | BR-06, K1, K3, K4, BR-12, CH-01 sampai CH-11, `seq_perubahan_booking.mermaid` | Semua tes CH lulus. Harga malam yang tidak berubah terkunci dan hanya malam baru memakai tarif berlaku. Cek ulang ketersediaan atomik di bawah kunci baris kamar berurutan id, dan malam milik booking sendiri tidak dianggap bentrok. Alasan wajib untuk semua peran. Otorisasi allow-list `RoleName` (peran tak dikenal ditolak). Booking checked_in hanya boleh perpanjang, percepat, dan pindah kamar. Status akhir ditolak. `total_amount` dan `booking_items.subtotal` dihitung ulang. Satu baris `booking_change_logs` per perubahan. `expires_at` tidak berubah. Kelebihan bayar dikembalikan sebagai peringatan lewat `PaymentService::summary` | **Ya** |
| T-014 | Perintah kedaluwarsa booking pending dan penjadwalannya: perintah artisan memanggil `BookingService::expire()` per booking (pelaku sistem), hanya booking pending dengan `expires_at` terisi dan terlewat, dijadwalkan tiap 5 menit tanpa tumpang tindih | BR-13, EX-01 sampai EX-06, `arsitektur_sistem.md` bagian 6 | Semua tes EX lulus. Idempoten (dijalankan dua kali tidak mengubah apa pun), booking staf (expires_at kosong) tidak ikut expired, ada tes yang membuktikan perintah terdaftar di jadwal tiap 5 menit, README menjelaskan cara menjalankan penjadwal secara lokal | Opsional |
| T-015 | CI GitHub Actions (hanya jika repo dipakai di GitHub): workflow menjalankan tes dengan service PostgreSQL dan database `kamarin_test`, lalu formatter | `rencana_pengujian.md` bagian 2, `AGENTS.md` aturan 10 | Workflow lulus di GitHub dengan PostgreSQL dan trigger otomatis diaktifkan kembali. Dikerjakan setelah T-010 agar sudah ada tes yang bermakna. Selesaikan juga kegagalan `npm run types:check` (3 error di file generated wayfinder, pilihan: kecualikan folder generated dari tsc) dan kecualikan `docs/` dari `npm run check` alih-alih memformatnya otomatis | Tidak perlu |
| T-016 | RoomBlockService: membuat blokir kamar dengan pola kunci yang sama seperti `BookingService` (`SELECT ... FOR UPDATE` pada `rooms` berurutan id, cek ulang bentrok di bawah kunci) dan menolak jika ada malam aktif di rentang itu. Rentang blokir setengah terbuka `[start_date, end_date)` | BR-01 butir 6, US-08, AV-12 | AV-12 lulus, dan ada tes yang membuktikan pembuatan blokir mengunci baris kamar dengan hook di jeda antara cek-ulang dan penulisan (pola yang sama dengan BK-05 service) | Ya |

Urutan: T-001 sampai T-003 berurutan. T-004 sampai T-007 berurutan. T-008 sampai T-014 berurutan sesuai nomor, karena tiap layanan memakai layanan sebelumnya. Tugas Fase 6 dan seterusnya (seeder dummy, dashboard, booking publik, analitik) ditulis setelah Fase 5 selesai.

## Kebijakan review berbasis risiko

| Tugas | Reviewer agent | Review Claude |
| --- | --- | --- |
| T-004 | Opsional (tes kesesuaian skema sudah menjaga) | Tidak |
| T-005 | Wajib | Ya |
| T-005b | Penguatan skema booking. (1) Tambah unique `(id, room_id)` di `booking_items` dan ganti FK `booking_nights.booking_item_id` menjadi FK gabungan `(booking_item_id, room_id)` ke `booking_items(id, room_id)` dengan `ON DELETE NO ACTION` dan `ON UPDATE CASCADE`, sehingga malam tidak bisa menunjuk kamar yang berbeda dari item induknya dan pemindahan kamar cukup mengubah `booking_items.room_id`. (2) CHECK kewajaran data dengan nama eksplisit lewat `DB::statement`: `bookings` (`guest_count > 0`, `total_amount >= 0`), `booking_items` (`subtotal >= 0`), `booking_nights` (`price >= 0`), `payments` (`amount > 0`), `rates` (`price >= 0`, `minimum_nights >= 1`, tanggal akhir tidak sebelum tanggal awal bila keduanya ada), `room_types` (`capacity > 0`), `properties` (`deposit_percent` 0 sampai 100, `pending_expiry_hours > 0`, `free_cancel_days >= 0`). Tanpa CHECK pada `booking_charges.amount` karena diskon bernilai negatif | ERD v2.1, BR-01, BR-06, BR-12, `AGENTS.md` aturan 4 | Tes membuktikan: malam dengan `room_id` berbeda dari item induknya ditolak. Mengubah `booking_items.room_id` ikut memindahkan malamnya dan tetap ditolak bila kamar tujuan sudah terisi pada tanggal itu. Tiap CHECK menolak nilai buruk (tes berdataset) dengan nama constraint yang benar. Seluruh tes lulus, aturan hapus FK tetap `NO ACTION`, `migrate:fresh` dan `migrate:rollback` berhasil | Ya |
| T-006, T-007 | Opsional | Tidak |
| T-008, T-009, T-010, T-011, T-013 | Wajib | Ya |
| T-012, T-014 | Wajib, ringan | Opsional |
| T-015 | Opsional | Tidak |
| T-016 | Wajib | Ya |

Reviewer wajib menempelkan keluaran asli perintah verifikasi (build dan tes), bukan ringkasan. Setiap rekomendasi perubahan disertai sumber dokumentasi resmi.

## Cara meminta agent mengerjakan satu tugas

Contoh perintah ke agent Builder:

```
Kerjakan T-008 dari docs/tasks/BACKLOG_FASE4_5.md di cabang task/T-008-availability-service.
Ikuti AGENTS.md. Baca dulu rujukan di tugas itu (BR-01, BR-07, kasus uji AV-01 sampai AV-13, tabel ERD terkait).
Tulis tes lebih dulu dari kasus uji, lalu implementasi. Jalankan seluruh tes.
Perbarui docs/STATUS.md di akhir. Jika ada yang tidak jelas, tulis di Pertanyaan terbuka dan berhenti di bagian itu.
```

Contoh perintah ke agent Reviewer (agent yang berbeda dari Builder):

```
Review cabang task/T-008-availability-service terhadap docs/erd_v2_1.mermaid, docs/business_rules_kamarin.md,
dan kasus uji AV-01 sampai AV-13 di docs/rencana_pengujian.md.
Jangan mengedit kode. Laporkan: kolom atau nilai yang tidak sesuai ERD, aturan yang tidak dipenuhi,
kasus uji yang terlewat, dan risiko bug. Jalankan seluruh tes dan laporkan hasilnya.
```