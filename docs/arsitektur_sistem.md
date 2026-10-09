# Arsitektur Sistem Kamarin (Draf v1)

Diperbarui: 8 Oktober 2026

**Sumber kebenaran:** `erd_v2_1.mermaid` dan `business_rules_kamarin.md` (v1.1). Dokumen ini harus mengikuti keduanya. Jika ERD atau aturan bisnis berubah, dokumen ini diperbarui, bukan sebaliknya.

**Diagram terkait:** `architecture_diagram.mermaid` (komponen), `seq_booking_staf.mermaid`, `seq_checkin_checkout.mermaid`, `seq_pembatalan.mermaid`, `seq_perubahan_booking.mermaid`, `booking_website_sequence.mermaid`, `booking_state_diagram.mermaid`, `usecase_diagram.mermaid`.

Status keputusan: ✅ sudah disetujui, 🟦 usulan menunggu keputusan, 🔎 perlu diverifikasi di dokumentasi resmi sebelum dipakai.

---

## 1. Gambaran

Satu aplikasi Laravel 13 melayani dua antarmuka: dashboard staf dan halaman booking publik untuk tamu. Keduanya memakai satu codebase dan satu database PostgreSQL ✅. Frontend memakai React dan Tailwind CSS ✅. Pekerjaan latar (email dan kedaluwarsa booking) dijalankan oleh queue worker dan scheduler milik aplikasi yang sama.

## 2. Komponen

| Komponen | Tanggung jawab | Catatan |
| --- | --- | --- |
| Routes dan controllers | Menerima request, validasi (Form Request), memanggil service, mengembalikan halaman atau JSON | Tipis. Tidak berisi logika bisnis |
| Policy dan Gate | Otorisasi per peran sesuai matriks hak akses | Berbasis `roles` dan `users.role_id` |
| AvailabilityService | Menentukan kamar tersedia (BR-01, BR-07) | Dipakai website, form staf, dan perubahan booking |
| PricingService | Memilih tarif per malam, menghitung total (BR-11, BR-12) | Menyalin harga ke `booking_nights.price` |
| BookingService | Membuat, mengonfirmasi, membatalkan, check-in, check-out, no-show | Semua perubahan status lewat sini, dengan transition guard |
| BookingChangeService | Ubah tanggal, perpanjang, percepat, pindah kamar (BR-06) | Menulis `booking_change_logs` |
| PaymentService | Mencatat pembayaran, menghitung terbayar, sisa, dan status bayar | Status bayar dihitung, tidak disimpan |
| Analytics queries | Okupansi, ADR, RevPAR, lead time, dll (BR-10) | Query langsung ke `booking_nights` dan `payments`, tanpa tabel analitik |
| Queue worker | Mengirim email booking received dan booking confirmed | Driver `database` cukup untuk skala ini 🟦 |
| Scheduler | Menjalankan perintah kedaluwarsa booking pending tiap beberapa menit (BR-13) | Harus idempoten |
| PostgreSQL | Transaksi, constraint, unique index parsial | Juga dipakai saat pengujian (lihat rencana pengujian) |

## 3. Struktur folder usulan 🟦

```
app/
  Enums/            BookingStatus, PaymentType, PaymentStatus, PaymentOption,
                    RoomStatus, HousekeepingStatus
  Models/
  Policies/
  Services/         AvailabilityService, PricingService, BookingService,
                    BookingChangeService, PaymentService
  Queries/          OccupancyQuery, RevenueQuery, LeadTimeQuery, ...
  Http/Controllers/ Staff/, Public/
  Jobs/ Mail/
  Console/Commands/ ExpirePendingBookings
database/           migrations, seeders
resources/js/       pages (Staff, Public), components
tests/              Unit, Feature
docs/               erd, business rules, diagram, dokumen ini
```

## 4. Prinsip desain

1. Controller tipis. Semua perubahan status booking lewat `BookingService` dengan transition guard sesuai matriks status × peran.
2. Pencegahan double booking tiga lapis: pengecekan service, kunci baris kamar dan cek ulang di dalam transaksi, serta unique index parsial `(room_id, stay_date) WHERE is_active` pada `booking_nights` (BR-01).
3. Harga dihitung sekali oleh `PricingService` lalu disalin ke `booking_nights.price`. Perubahan tarif tidak mengubah booking lama.
4. Tidak ada tabel analitik. Semua metrik dihitung dari data transaksi.
5. Semua tanggal adalah tanggal kalender lokal properti. Zona waktu aplikasi diatur satu kali di `.env`.
6. Setiap aturan di Business Rules punya minimal satu tes otomatis.

## 5. Keputusan teknis

| Hal | Usulan | Status | Alasan atau catatan |
| --- | --- | --- | --- |
| Otorisasi per peran | Tabel `roles` dan `users.role_id` dari ERD, diterapkan lewat Policy dan Gate. **Tanpa** paket role-permission | 🟦 | Paket role-permission umumnya membawa tabel roles dan permissions sendiri yang bertabrakan dengan tabel `roles` di ERD. Cukup tiga hingga empat peran tetap, jadi tidak perlu izin granular. Menghapus satu dependensi yang perlu dicek kompatibilitasnya dengan Laravel 13 |
| Integrasi React dengan Laravel | Ikuti starter kit resmi | 🔎 | Cek di dokumentasi Laravel apa yang dipakai starter kit React pada versi 13 (misalnya Inertia) sebelum menyusun struktur halaman |
| Kalender timeline | Komponen buatan sendiri dengan CSS grid | 🟦 | Skala kecil (maksimal sekitar 15 kamar × 30 hari). Hindari risiko lisensi. Verifikasi dengan membandingkan 2-3 library (lisensi, perawatan) sebelum final |
| Grafik | Recharts | ✅ | |
| Ekspor laporan | Satu format saja, PDF atau Excel | 🔎 | Pilih paket yang masih terawat dan kompatibel dengan Laravel 13 |
| Email | Laravel Mail dengan queue. Pengembangan: Mailtrap atau mode log | ✅ | |
| Lingkungan Docker | Laravel Sail untuk development, Dockerfile produksi dibuat saat deploy | 🟦 | Lebih cepat untuk solo. Belum diputuskan |
| Alat tes | Pest atau PHPUnit | 🟦 | Pilih salah satu dan konsisten |
| Database saat tes | PostgreSQL, bukan SQLite | 🟦 | Unique index parsial dan perilaku kunci baris hanya bisa dibuktikan di PostgreSQL |
| Queue driver | `database` | 🟦 | Tidak perlu menambah layanan Redis untuk volume sekecil ini |

## 6. Proses dalam Docker

| Proses | Fungsi |
| --- | --- |
| app | Web server aplikasi Laravel |
| pgsql | PostgreSQL |
| queue worker | Menjalankan job email |
| scheduler | Menjalankan perintah terjadwal (kedaluwarsa booking) |
| vite (hanya development) | Build aset React |

Pada produksi, queue worker dan scheduler harus berjalan terus dan dipantau (checklist Fase 11).

## 7. Basis data dan performa

Perkiraan volume: dengan 15 kamar, `booking_nights` paling banyak sekitar 5.500 baris per tahun (15 × 365) jika terisi penuh. Skala ini sangat kecil, jadi fokusnya kebenaran, bukan optimasi.

Indeks yang diperlukan:

| Tabel | Indeks | Tujuan |
| --- | --- | --- |
| `booking_nights` | Unique parsial `(room_id, stay_date) WHERE is_active` | Mencegah double booking |
| `booking_nights` | `stay_date` | Query okupansi dan pendapatan |
| `bookings` | `booking_code` unik | Cek pesanan |
| `bookings` | `(property_id, check_in_date, check_out_date)` dan `status` | Daftar, kalender, check-in hari ini |
| `payments` | `booking_id` | Hitung terbayar |
| `guests` | `email` dan `phone` | Pencarian tamu |
| `room_blocks` | `(room_id, start_date, end_date)` | Cek ketersediaan |
| `rooms` | Unik `(property_id, room_number)` | Integritas data |

Unique index parsial tidak bisa ditulis dengan schema builder biasa. Gunakan `DB::statement` di migration.

## 8. Keamanan dasar

| Aspek | Penanganan |
| --- | --- |
| CSRF dan XSS | Proteksi bawaan framework, output di-escape |
| Validasi | Form Request di semua input, termasuk halaman publik |
| Autentikasi | Password ter-hash, pembatasan percobaan login |
| Halaman publik | Pembatasan percobaan pada cek pesanan. Kode booking acak dan pesan error generik (BR-14) |
| Data sensitif | `guests.id_number` opsional dan tidak diisi pada data dummy. Log tidak memuat data pribadi |
| Otorisasi | Dicek di server melalui Policy. Data keuangan tidak dikirim ke peran yang tidak berhak, bukan sekadar disembunyikan di UI |

## 9. Risiko teknis

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Race condition saat dua booking bersamaan | Double booking | Tiga lapis pencegahan dan tes konkurensi |
| Kesalahan tanggal dan zona waktu | Malam bergeser satu hari | Satu zona waktu di `.env`, pakai tanggal kalender (bukan timestamp) untuk malam menginap, tes dengan waktu dibekukan |
| Worker atau scheduler mati di produksi | Email tidak terkirim dan booking pending tidak expired | Pemantauan sederhana dan pengujian saat deploy |
| Kalender timeline lambat atau rumit | Waktu terbuang | Mulai tanpa drag-and-drop, batasi rentang 30 hari, query satu kali per tampilan |
| Metrik berbeda dari hitungan manual | Kepercayaan hasil analitik turun | Verifikasi sampel manual (AN-09 di rencana pengujian) |
| Terlalu banyak fitur untuk solo 4 bulan | Tidak selesai | Prioritas Must, Should, Could di `user_stories.md` dan daftar pemangkasan di Business Rules |

## 10. Alur penting dan diagramnya

| Alur | Diagram |
| --- | --- |
| Booking oleh tamu lewat website, kedaluwarsa, dan cek pesanan | `booking_website_sequence.mermaid` |
| Booking oleh staf, pembayaran, dan konfirmasi | `seq_booking_staf.mermaid` |
| Check-in dan check-out | `seq_checkin_checkout.mermaid` |
| Pembatalan | `seq_pembatalan.mermaid` |
| Perubahan booking | `seq_perubahan_booking.mermaid` |
| Siklus status booking | `booking_state_diagram.mermaid` |
| Aktor dan fungsi | `usecase_diagram.mermaid` |
