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
| T-006 | Enum, model, relasi, dan factory | ERD v2.1, `AGENTS.md` aturan 2 | Nilai enum sama dengan ERD, relasi sesuai ERD, factory untuk semua model | Opsional |
| T-007 | Seeder dasar: roles, booking_sources, satu properti dengan pengaturan default, akun demo per peran, contoh tipe kamar, kamar, dan tarif | BR-09, BR-11, `rencana_pengujian.md` bagian 3 | Tanpa data pribadi nyata, `id_number` kosong, tarif contoh memuat default, akhir pekan, musim ramai, dan kombinasi | Tidak perlu |
| T-008 | AvailabilityService beserta tes | BR-01, BR-07, AV-01 sampai AV-13, US-13 | Semua tes AV lulus. Turnover hari yang sama diizinkan, blokir dan booking aktif menghalangi, housekeeping tidak memengaruhi tanggal mendatang | **Ya** |
| T-009 | PricingService beserta tes | BR-11, BR-12, PR-01 sampai PR-11 | Semua tes PR lulus. Harga per malam, priority, `days_of_week` ISO, minimum_nights dari tarif malam check-in, harga terkunci | **Ya** |
| T-010 | BookingService: membuat booking dalam transaksi (kunci baris, cek ulang, simpan nights, status log, kode booking) | BR-01, BR-13, BR-14, BK-01 sampai BK-08, US-12, US-13 | Semua tes BK lulus. Termasuk pengujian konkurensi atau, jika tidak bisa diotomatisasi, tes penanganan pelanggaran unique index dengan pesan yang benar (dicatat jujur di STATUS.md) | **Ya** |
| T-011 | Transition guard dan transisi status (confirm, cancel, check-in, check-out, no-show, expire) beserta log status | Business Rules bagian 5, ST-01 sampai ST-09 | Semua tes ST lulus. Matriks status x peran dipenuhi, status akhir tidak dapat dibuka kembali | **Ya** |
| T-012 | PaymentService: DP wajib, terbayar, sisa tagihan, status bayar, refund | BR-03, BR-04, BR-12, PY-01 sampai PY-06 | Semua tes PY lulus. Hanya pembayaran verified yang dihitung | Opsional |
| T-013 | BookingChangeService: ubah tanggal, perpanjang, percepat, pindah kamar, beserta change log | BR-06, K3, K4, CH-01 sampai CH-11 | Semua tes CH lulus. Harga malam yang tidak berubah terkunci, cek ulang atomik, alasan wajib | **Ya** |
| T-014 | Perintah kedaluwarsa booking pending dan penjadwalannya | BR-13, EX-01 sampai EX-06 | Semua tes EX lulus. Idempoten, booking staf tidak ikut expired | Opsional |
| T-015 | CI GitHub Actions (hanya jika repo dipakai di GitHub): workflow menjalankan tes dengan service PostgreSQL dan database `kamarin_test`, lalu formatter | `rencana_pengujian.md` bagian 2, `AGENTS.md` aturan 10 | Workflow lulus di GitHub dengan PostgreSQL dan trigger otomatis diaktifkan kembali. Dikerjakan setelah T-010 agar sudah ada tes yang bermakna. Selesaikan juga kegagalan `npm run types:check` (3 error di file generated wayfinder, pilihan: kecualikan folder generated dari tsc) dan kecualikan `docs/` dari `npm run check` alih-alih memformatnya otomatis | Tidak perlu |

Urutan: T-001 sampai T-003 berurutan. T-004 sampai T-007 berurutan. T-008 sampai T-014 berurutan sesuai nomor, karena tiap layanan memakai layanan sebelumnya. Tugas Fase 6 dan seterusnya (seeder dummy, dashboard, booking publik, analitik) ditulis setelah Fase 5 selesai.

## Kebijakan review berbasis risiko

| Tugas | Reviewer agent | Review Claude |
| --- | --- | --- |
| T-004 | Opsional (tes kesesuaian skema sudah menjaga) | Tidak |
| T-005 | Wajib | Ya |
| T-006, T-007 | Opsional | Tidak |
| T-008, T-009, T-010, T-011, T-013 | Wajib | Ya |
| T-012, T-014 | Wajib, ringan | Opsional |
| T-015 | Opsional | Tidak |

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
