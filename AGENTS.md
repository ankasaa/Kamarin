# AGENTS.md - Kamarin

Instruksi tetap untuk semua AI agent di repo ini (OpenCode, Antigravity). Sengaja singkat: detail ada di `docs/`. Sebelum mengerjakan tugas, baca file yang disebut di tugas itu.

Balas ke user dalam bahasa Indonesia. Kode, komentar kode, pesan commit, dan teks antarmuka (UI) dalam bahasa Inggris.

## Proyek

Kamarin adalah dashboard manajemen dan analitik pemesanan untuk guesthouse, ditambah booking engine publik. Ini tugas akhir yang dikerjakan satu orang. Satu aplikasi Laravel 13 + React + Tailwind + PostgreSQL. Di development, PHP, Composer, dan Node berjalan lokal (Windows) dan hanya PostgreSQL yang berjalan di Docker. Satu properti (maksimal sekitar 15 kamar), tetapi `property_id` tetap ada di tabel terkait.

## Sumber kebenaran (urutan prioritas)

1. `docs/erd_v2_1.mermaid` : skema database
2. `docs/business_rules_kamarin.md` : aturan bisnis (BR-01 sampai BR-14, keputusan K1 sampai K10)
3. `docs/user_stories.md` : cerita dan kriteria penerimaan
4. `docs/rencana_pengujian.md` : kasus uji (ID: AV, PR, BK, ST, CH, PY, EX, PB, AC, AN, SC)
5. `docs/arsitektur_sistem.md` : komponen dan keputusan teknis
6. `docs/panduan_desain.md` : warna, komponen, kalender

Jika kode, permintaan user, dan dokumen saling bertentangan: **berhenti dan tanyakan**. Jangan mengubah skema atau aturan secara diam-diam.

## Aturan keras

1. **Skema:** migration harus persis sesuai ERD v2.1 (nama tabel, kolom, tipe, nullable, unik), dengan pengecualian hanya di butir 13. Jangan menambah, menghapus, atau mengganti nama tabel atau kolom tanpa persetujuan eksplisit user. Jika menurut kamu skema salah, tulis di `docs/STATUS.md` bagian "Pertanyaan terbuka".
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
11. **Dependensi:** jangan menambah paket tanpa persetujuan user. Verifikasi versi dan kompatibilitas dengan Laravel 13 lewat Composer, npm, dan dokumentasi resmi. Jangan menebak versi atau perintah. Paket yang dibawa installer resmi untuk opsi yang dipilih (mis. `laravel/wayfinder`, `laravel/chisel`, `laravel/sail`) dianggap sudah disetujui. Paket lain, termasuk dev dependency, butuh persetujuan.
12. **Data dan rahasia:** tanpa data pribadi nyata. `guests.id_number` dikosongkan pada seeder. Jangan commit `.env` atau rahasia apa pun, hanya `.env.example`.
13. **Bawaan framework dan starter kit:** tabel framework (`migrations`, `jobs`, `failed_jobs`, `sessions`, `cache`, `password_reset_tokens`) dan kolom bawaan pada `users` (`remember_token`, `email_verified_at`, timestamp) diizinkan di luar ERD. Fitur starter kit yang tidak dipakai (registrasi publik, 2FA, verifikasi email, teams, WorkOS) dinonaktifkan atau dibuang, termasuk migration dan kolomnya, dan referensi rutenya di frontend dihapus karena build gagal jika masih ada. Login, logout, dan reset password dipertahankan.

## Lingkungan pengembangan (Windows)

- PHP, Composer, dan Node berjalan lokal. Hanya PostgreSQL yang berjalan di Docker (compose), dengan satu database untuk development dan satu database terpisah untuk tes.
- Perintah dan versi diverifikasi dari dokumentasi resmi Laravel 13. Server, queue worker, dan Vite dijalankan lewat skrip `composer run dev` (verifikasi isinya di `composer.json`). Scheduler dijalankan terpisah dengan `php artisan schedule:work` saat dibutuhkan.
- Docker Desktop harus berjalan. Cek dengan `docker info`. Jika tidak berjalan, jalankan atau minta user.
- `compose.yaml`: port database hanya di-bind ke `127.0.0.1` (bukan semua antarmuka). Volume data PostgreSQL 18 di-mount ke `/var/lib/postgresql`, bukan `/var/lib/postgresql/data`, sesuai dokumentasi image resmi untuk versi 18 ke atas. Jangan diubah.
- Proyek memakai Pest 4.x, sedangkan dokumentasi Pest saat ini membahas versi 5.x. Verifikasi perilaku API dengan tes kecil sebelum mengandalkannya, dan catat jika berbeda.
- Zona waktu aplikasi: `Asia/Makassar` lewat `APP_TIMEZONE` di `.env` (keputusan user, final).
- Jangan menjalankan `npm` atau `composer` untuk folder yang sama dari dua lingkungan berbeda (mis. Windows dan WSL), karena `node_modules` dan `vendor` bisa bentrok.

## Cara kerja

- Satu tugas per cabang `task/T-xxx-slug`. Tugas dan rujukannya ada di `docs/tasks/`. Jangan menyentuh file di luar ruang lingkup tugas dan jangan merefaktor hal lain.
- Hanya satu agent aktif di repo pada satu waktu. Mulai dengan `git status` yang bersih.
- Jangan mengubah cabang utama (merge, fast-forward, rebase, push) kecuali user memerintahkannya secara eksplisit di prompt dan review sudah lulus. Dalam kasus itu hanya `merge --ff-only`, tanpa force push, dan catat hash sebelum dan sesudah di STATUS.md. Jika cabang utama tertinggal dari cabang tugas sebelumnya dan tidak ada perintah merge, buat cabang baru dari ujung cabang tugas sebelumnya dan catat di STATUS.md.
- Urutan: baca rujukan, tulis tes dari kasus uji, implementasi, jalankan **seluruh** tes, perbarui `docs/STATUS.md`, commit.
- Jangan melapor selesai jika tes gagal. Laporkan jujur apa yang gagal dan kenapa.
- Jangan menjalankan formatter otomatis pada folder `docs/`. Dokumen sumber punya format sendiri, kecuali user memintanya.
- Jika spesifikasi kurang jelas atau bertentangan, tulis pertanyaan di `docs/STATUS.md` (bagian "Pertanyaan terbuka") dan berhenti di bagian itu. Jangan menebak.
- User harus bisa menjelaskan kode ini saat sidang. Setelah tiap tugas, tulis 3 sampai 5 kalimat penjelasan "bagaimana dan kenapa" di `docs/STATUS.md`.

## Peran agent

- **Builder:** mengimplementasikan satu tugas sampai Definition of Done.
- **Reviewer:** membaca diff terhadap dokumen sumber, menjalankan tes, dan melaporkan ketidaksesuaian (nama kolom, aturan, kasus uji yang terlewat). Tidak mengedit kode kecuali diminta. Agent yang menulis kode tidak me-review kodenya sendiri. Setiap rekomendasi perubahan yang menyangkut versi, konfigurasi infrastruktur, atau keamanan harus disertai sumber dokumentasi resmi, bukan dari ingatan. Tandai juga paparan jaringan, misalnya port yang terbuka ke luar komputer. Uji pula apakah tafsir sebuah aturan masuk akal pada skenario operasional nyata (tamu terlambat, perubahan mendadak, dua staf bersamaan), bukan hanya apakah kode konsisten dengan tafsir Builder.

## Definition of Done

- Sesuai ERD v2.1 dan Business Rules.
- Kasus uji terkait ditulis dan lulus, seluruh test suite hijau.
- Formatter dijalankan.
- Tidak ada perubahan skema di luar tugas dan tidak ada rahasia di commit.
- `docs/STATUS.md` diperbarui, termasuk penyimpangan dari dokumen (jika ada).

## Format commit

`type(scope): ringkasan` dalam bahasa Inggris, mis. `feat(availability): add room availability service`. Satu commit untuk satu perubahan logis.