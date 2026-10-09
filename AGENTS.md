# AGENTS.md - Kamarin

Instruksi tetap untuk semua AI agent di repo ini (OpenCode, Antigravity). Sengaja singkat: detail ada di `docs/`. Sebelum mengerjakan tugas, baca file yang disebut di tugas itu.

Balas ke user dalam bahasa Indonesia. Kode, komentar kode, pesan commit, dan teks antarmuka (UI) dalam bahasa Inggris.

## Proyek

Kamarin adalah dashboard manajemen dan analitik pemesanan untuk guesthouse, ditambah booking engine publik. Ini tugas akhir yang dikerjakan satu orang. Satu aplikasi Laravel 13 + React + Tailwind + PostgreSQL, dijalankan lewat Docker. Satu properti (maksimal sekitar 15 kamar), tetapi `property_id` tetap ada di tabel terkait.

## Sumber kebenaran (urutan prioritas)

1. `docs/erd_v2_1.mermaid` : skema database
2. `docs/business_rules_kamarin.md` : aturan bisnis (BR-01 sampai BR-14, keputusan K1 sampai K10)
3. `docs/user_stories.md` : cerita dan kriteria penerimaan
4. `docs/rencana_pengujian.md` : kasus uji (ID: AV, PR, BK, ST, CH, PY, EX, PB, AC, AN, SC)
5. `docs/arsitektur_sistem.md` : komponen dan keputusan teknis
6. `docs/panduan_desain.md` : warna, komponen, kalender

Jika kode, permintaan user, dan dokumen saling bertentangan: **berhenti dan tanyakan**. Jangan mengubah skema atau aturan secara diam-diam.

## Aturan keras

1. **Skema:** migration harus persis sesuai ERD v2.1 (nama tabel, kolom, tipe, nullable, unik). Jangan menambah, menghapus, atau mengganti nama tabel atau kolom tanpa persetujuan eksplisit user. Jika menurut kamu skema salah, tulis di `docs/STATUS.md` bagian "Pertanyaan terbuka".
2. **Nilai status** memakai teks persis seperti di ERD (huruf kecil, snake_case), diimplementasikan sebagai PHP enum:
   - booking: pending, confirmed, checked_in, checked_out, cancelled, expired, no_show
   - kamar: active, maintenance, inactive. Kebersihan: dirty, cleaning, ready
   - pembayaran: type deposit, settlement, refund. Status pending, verified, failed, refunded
   - `payment_option`: bank_transfer, pay_on_arrival, via_ota
   - `payments.method`: bank_transfer, cash, card_or_qris, ota
3. **Logika bisnis hanya di `app/Services`.** Controller tipis. Status booking hanya boleh berubah lewat `BookingService` dengan transition guard sesuai matriks di Business Rules bagian 5.
4. **Anti double booking** (BR-01) berlapis: pengecekan di service, transaksi dengan kunci baris kamar dan cek ulang, serta unique index parsial `(room_id, stay_date) WHERE is_active` pada `booking_nights` (dibuat dengan `DB::statement` di migration). Jangan hanya mengandalkan cek di aplikasi.
5. **Harga:** dihitung per malam oleh `PricingService` (BR-11) lalu disalin ke `booking_nights.price`. Uang tidak pernah memakai float. Rupiah bulat tanpa desimal.
6. **Kebijakan tidak di-hardcode.** Jam check-in dan check-out, persen DP, expiry, batas batal gratis, dan batas no-show dibaca dari tabel `properties`.
7. **Tanggal:** `stay_date`, `check_in_date`, `check_out_date` bertipe date. Hari check-out bukan malam menginap. Zona waktu diatur di `.env`.
8. **Otorisasi:** tabel `roles` + `users.role_id` + Policy atau Gate. Jangan memasang paket role-permission kecuali user menyetujui.
9. **Data keuangan** tidak boleh dikirim ke peran tanpa hak. Pengecekan dilakukan di server, bukan hanya disembunyikan di UI.
10. **Tes memakai PostgreSQL, bukan SQLite.** Setiap aturan BR punya tes. Pakai ID kasus uji dari `docs/rencana_pengujian.md` sebagai nama atau komentar tes (mis. AV-03).
11. **Dependensi:** jangan menambah paket tanpa persetujuan user. Verifikasi versi dan kompatibilitas dengan Laravel 13 lewat Composer, npm, dan dokumentasi resmi. Jangan menebak versi atau perintah.
12. **Data dan rahasia:** tanpa data pribadi nyata. `guests.id_number` dikosongkan pada seeder. Jangan commit `.env` atau rahasia apa pun, hanya `.env.example`.

## Cara kerja

- Satu tugas per cabang `task/T-xxx-slug`. Tugas dan rujukannya ada di `docs/tasks/`. Jangan menyentuh file di luar ruang lingkup tugas dan jangan merefaktor hal lain.
- Hanya satu agent aktif di repo pada satu waktu. Mulai dengan `git status` yang bersih.
- Urutan: baca rujukan, tulis tes dari kasus uji, implementasi, jalankan **seluruh** tes, perbarui `docs/STATUS.md`, commit.
- Jangan melapor selesai jika tes gagal. Laporkan jujur apa yang gagal dan kenapa.
- Jika spesifikasi kurang jelas atau bertentangan, tulis pertanyaan di `docs/STATUS.md` (bagian "Pertanyaan terbuka") dan berhenti di bagian itu. Jangan menebak.
- User harus bisa menjelaskan kode ini saat sidang. Setelah tiap tugas, tulis 3 sampai 5 kalimat penjelasan "bagaimana dan kenapa" di `docs/STATUS.md`.

## Peran agent

- **Builder:** mengimplementasikan satu tugas sampai Definition of Done.
- **Reviewer:** membaca diff terhadap dokumen sumber, menjalankan tes, dan melaporkan ketidaksesuaian (nama kolom, aturan, kasus uji yang terlewat). Tidak mengedit kode kecuali diminta. Agent yang menulis kode tidak me-review kodenya sendiri.

## Definition of Done

- Sesuai ERD v2.1 dan Business Rules.
- Kasus uji terkait ditulis dan lulus, seluruh test suite hijau.
- Formatter dijalankan.
- Tidak ada perubahan skema di luar tugas dan tidak ada rahasia di commit.
- `docs/STATUS.md` diperbarui, termasuk penyimpangan dari dokumen (jika ada).

## Format commit

`type(scope): ringkasan` dalam bahasa Inggris, mis. `feat(availability): add room availability service`. Satu commit untuk satu perubahan logis.
