# Rencana Pengujian Kamarin (Draf v1)

Diperbarui: 8 Oktober 2026

**Sumber kebenaran:** `erd_v2_1.mermaid`, `business_rules_kamarin.md` (v1.1), dan `user_stories.md`. Setiap kasus uji menunjuk ke aturan atau cerita yang diuji. Jika aturan berubah, kasus ujinya ikut diubah.

---

## 1. Tujuan dan ruang lingkup

1. Membuktikan bahwa aturan inti benar: ketersediaan, harga, perubahan status, pembayaran, kedaluwarsa, dan metrik.
2. Membuktikan bahwa double booking tidak mungkin terjadi, termasuk pada permintaan bersamaan.
3. Membuktikan hak akses per peran dan keamanan dasar.
4. Mengukur kegunaan dashboard dengan skenario terukur.

Di luar lingkup: sinkronisasi OTA, payment gateway, dan aplikasi mobile (Tahap 2 dan 3).

## 2. Strategi

| Tingkat | Fokus | Alat | Kapan |
| --- | --- | --- | --- |
| Unit | `PricingService`, perhitungan metrik, transition guard | Pest atau PHPUnit | Setiap commit |
| Feature (HTTP dan database) | Alur booking, perubahan, pembayaran, hak akses, halaman publik | Tes fitur Laravel dengan **PostgreSQL asli** | Setiap commit |
| Konkurensi | Dua permintaan bersamaan untuk kamar dan malam yang sama | Dua koneksi atau proses paralel | Sebelum Fase 5 dianggap selesai |
| Query analitik | Rumus metrik pada dataset kecil terkontrol | Pest dan spreadsheet pembanding | Fase 9 |
| End-to-end | Website, email, dashboard, konfirmasi, check-in, check-out | Skrip manual atau alat E2E, dipilih di Fase 10 | Fase 10 |
| Kegunaan | Tugas terukur oleh 3 sampai 5 orang | Skenario dan lembar catatan | Fase 10 |

Catatan penting:

- Tes memakai PostgreSQL, **bukan SQLite**. Unique index parsial dan perilaku kunci baris hanya bisa dibuktikan di PostgreSQL.
- Tes konkurensi sebenarnya sulit diotomatisasi sepenuhnya. Minimal: (a) uji bahwa pelanggaran unique index ditangani dengan pesan yang benar, dan (b) uji dengan dua proses paralel. Catat di laporan jika hanya (a) yang bisa diotomatisasi.
- Tanggal dan waktu dibekukan dalam tes (fixed now), karena aturan bergantung pada "hari ini", expiry, dan batas no-show.

## 3. Data uji

| Dataset | Isi | Dipakai untuk |
| --- | --- | --- |
| Kecil terkontrol | 1 properti, 2 tipe, 3 kamar, periode 7 hari, booking ditentukan tangan | Tes ketersediaan, harga, metrik. Hasilnya bisa dihitung manual |
| Seeder dummy | 15 kamar, 6 sampai 12 bulan, pola akhir pekan, musim ramai, pembatalan | Verifikasi performa dan tampilan. Tidak untuk tes aturan |
| Tarif contoh | Default (priority 0), akhir pekan (10), musim ramai (20), musim ramai akhir pekan (30) | Tes harga |

## 4. Kasus uji

### 4.1 Ketersediaan (AV)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| AV-01 | Kamar active tanpa booking dan blokir | Tersedia | BR-01 |
| AV-02 | Booking aktif 10-12, permintaan 11-13 untuk kamar sama | Ditolak (bentrok sebagian) | BR-01 |
| AV-03 | Booking aktif 10-12, permintaan 12-14 untuk kamar sama | Diizinkan (turnover hari yang sama) | BR-01 |
| AV-04 | `room_blocks` mencakup salah satu malam | Tidak tersedia | BR-01 |
| AV-05 | `rooms.status` maintenance atau inactive | Tidak tersedia | BR-07 |
| AV-06 | Booking cancelled, expired, atau no_show pada kamar dan tanggal sama | Tidak menghalangi | BR-01 |
| AV-07 | Booking pending pada kamar dan tanggal sama | Menghalangi | BR-01 |
| AV-08 | Housekeeping dirty pada kamar untuk tanggal mendatang | Tetap tersedia | BR-07 |
| AV-09 | `guest_count` melebihi total kapasitas kamar | Ditolak | BR-01 |
| AV-10 | `check_out_date` sama atau sebelum `check_in_date` | Ditolak (validasi dan constraint) | BR-01 |
| AV-11 | Check-in tanggal lampau oleh resepsionis, lalu oleh Manager dengan alasan | Resepsionis ditolak, Manager diizinkan | BR-01 |
| AV-12 | Membuat blokir pada tanggal yang punya malam aktif | Ditolak | BR-01, US-08 |
| AV-13 | Mengubah kamar ke maintenance saat ada malam aktif mendatang | Ditolak | BR-07, US-05 |
| AV-14 | Kamar bertipe nonaktif (`room_types.is_active = false`) | Tidak tersedia | BR-01, BR-07 |

### 4.2 Harga (PR)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| PR-01 | Hanya tarif default | Semua malam memakai harga default | BR-11 |
| PR-02 | Tarif akhir pekan dengan `days_of_week` [5,6] | Malam Jumat dan Sabtu memakai harga akhir pekan (ISO) | BR-11 |
| PR-03 | Malam akhir pekan di dalam musim ramai | Tarif dengan priority tertinggi menang | BR-11 |
| PR-04 | Menginap melintasi batas musim | Harga campuran per malam, total sama dengan jumlah malam | BR-11, BR-12 |
| PR-05 | Menyimpan dua tarif bertumpuk dengan priority sama | Ditolak | BR-11, K2 |
| PR-06 | Tipe kamar aktif tanpa tarif default | Tidak dapat dijual atau validasi gagal | BR-11 |
| PR-07 | Menginap 1 malam saat tarif malam check-in mensyaratkan minimum 2 malam | Ditolak. Owner atau Manager dapat override dengan alasan | BR-11, K1 |
| PR-08 | Tarif diubah setelah booking dibuat | Harga booking lama tidak berubah | BR-11 |
| PR-09 | Semua nilai uang | Bilangan bulat rupiah | Istilah dasar |
| PR-10 | Subtotal booking item dan total | Subtotal sama dengan jumlah `booking_nights.price` aktif. `total_amount` sama dengan subtotal ditambah jumlah `booking_charges` | BR-12 |
| PR-11 | Biaya tambahan positif dan diskon negatif | Total mengikuti. Diskon oleh resepsionis ditolak | BR-12, US-19 |

### 4.3 Pembuatan booking dan konkurensi (BK)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| BK-01 | Membuat booking valid | Status pending, kode 8 karakter unik, jumlah malam aktif benar, `booking_status_logs` terisi | BR-14, US-12 |
| BK-02 | Booking website dan booking staf | Website: `expires_at` = waktu dibuat + `pending_expiry_hours`. Staf: `expires_at` kosong | BR-13 |
| BK-03 | Booking multi-kamar, salah satu kamar bentrok | Seluruh booking dibatalkan (tidak ada data tersimpan) | BR-01 |
| BK-04 | Dua permintaan bersamaan untuk kamar dan malam yang sama | Tepat satu berhasil | BR-01, US-13 |
| BK-05 | Insert langsung dua malam aktif dengan `(room_id, stay_date)` sama | Ditolak oleh database | BR-01 |
| BK-06 | Booking tanpa sumber | Ditolak | BR-09 |
| BK-07 | Tamu lama dengan email sama | Dicari, tidak digabung otomatis | US-21 |
| BK-08 | Dua booking menghasilkan kode booking | Kode berbeda, bukan berurutan | BR-14 |

### 4.4 Transisi status (ST)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| ST-01 | Setiap transisi valid pada matriks, oleh peran yang diizinkan | Berhasil | Matriks status |
| ST-02 | Transisi tidak valid (mis. checked_in ke cancelled, membuka status akhir) | Ditolak | Matriks status |
| ST-03 | Resepsionis membatalkan tanpa alasan | Ditolak | BR-05 |
| ST-04 | Konfirmasi transfer bank tanpa DP terverifikasi | Ditolak. Override Owner atau Manager dengan alasan berhasil. Override resepsionis ditolak | K6 |
| ST-05 | No-show sebelum `no_show_cutoff_time`, lalu sesudahnya | Sebelum ditolak, sesudah berhasil dan semua malam dilepas | BR-08 |
| ST-06 | Pembatalan lalu pemesanan ulang kamar dan tanggal yang sama | Berhasil (malam dilepas) | BR-05 |
| ST-07 | Setiap transisi | Menulis `booking_status_logs` | Matriks status |
| ST-08 | Check-out | `housekeeping_statuses` kamar menjadi dirty | BR-07 |
| ST-09 | Check-in saat kamar belum ready atau sisa tagihan lebih dari nol | Peringatan, tidak memblokir | BR-04, BR-07 |
| ST-10 | Check-in booking confirmed setelah `no_show_cutoff_time` pada tanggal check-in | Berhasil dengan peringatan, tidak ditolak | BR-08 butir 3 |

### 4.5 Perubahan booking (CH)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| CH-01 | Perpanjang 1 malam, kamar tersedia | Malam baru ditambah, total naik, harga malam lama tetap | BR-06, K3 |
| CH-02 | Perpanjang tetapi malam baru bentrok | Ditolak dan tidak ada perubahan sama sekali | BR-06 |
| CH-03 | Percepat check-out | Malam sisa dinonaktifkan, total turun | BR-06 |
| CH-04 | Ubah tanggal penuh ke tanggal lain | Malam lama nonaktif, malam baru aktif. Malam milik booking sendiri tidak dianggap bentrok | BR-06 |
| CH-05 | Perubahan tanpa alasan oleh semua peran | Ditolak | K4 |
| CH-06 | Setiap perubahan | `booking_change_logs` berisi jenis, nilai lama, nilai baru, alasan, pelaku | BR-06 |
| CH-07 | Check-in baru melanggar `minimum_nights` | Ditolak. Override Owner atau Manager | BR-06, K1 |
| CH-08 | Pindah kamar di tipe yang sama | Harga tetap | BR-06 |
| CH-09 | Booking checked_in: ubah check-in lalu perpanjang | Ubah check-in ditolak, perpanjang berhasil | BR-06 |
| CH-10 | Terbayar melebihi total setelah perubahan | Ditandai kelebihan bayar | BR-12 |
| CH-11 | Perubahan pada booking pending | `expires_at` tidak berubah | BR-13 |

### 4.6 Pembayaran (PY)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| PY-01 | DP wajib | Sama dengan total × `deposit_percent` / 100, dibulatkan ke rupiah terdekat | BR-03 |
| PY-02 | Status bayar | unpaid saat terbayar nol, partial saat sebagian, paid saat terbayar mencapai total | BR-12 |
| PY-03 | Pembayaran berstatus pending atau failed | Tidak dihitung ke terbayar | BR-04 |
| PY-04 | Refund verified | Mengurangi terbayar | BR-12 |
| PY-05 | `payer_name` berbeda dari nama tamu | Diterima | BR-04 |
| PY-06 | Pembatalan tepat pada batas `free_cancel_days`, dan sehari lebih dekat | Pada batas: saran DP dikembalikan. Lebih dekat: saran DP hangus | BR-05 |
| PY-07 | Pembatalan dengan keputusan DP dikembalikan | Dibuat satu baris refund verified senilai DP bersih. Baris DP asli tetap verified. Terbayar menjadi nol | BR-04 butir 3, BR-05 butir 3 |
| PY-08 | Resepsionis membatalkan dengan keputusan DP berbeda dari saran, lalu Owner atau Manager | Resepsionis ditolak, Owner dan Manager diizinkan | BR-05, Keterangan 2 matriks status |
| PY-09 | Resepsionis mencatat pembayaran bertipe refund secara manual, lalu Owner atau Manager | Resepsionis ditolak sebelum ada baris yang ditulis, Owner dan Manager diizinkan | BR-04 butir 4 |
| PY-10 | Mencatat pembayaran tanpa referensi dengan metode bank_transfer, ota, cash, card_or_qris | bank_transfer dan ota ditolak (kosong, null, atau hanya spasi), cash dan card_or_qris diterima | BR-04 butir 5 |

### 4.7 Kedaluwarsa (EX)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| EX-01 | Booking website pending melewati `expires_at` | Menjadi expired dan malam dinonaktifkan | BR-13 |
| EX-02 | Konfirmasi sebelum `expires_at` | `expires_at` dikosongkan dan tidak expired | BR-13 |
| EX-03 | Mengonfirmasi booking expired | Ditolak | BR-13 |
| EX-04 | Booking staf pending | Tidak ikut expired | K5 |
| EX-05 | Scheduler dijalankan dua kali | Idempoten, tidak ada error atau duplikasi log | US-29 |
| EX-06 | Log status saat expired | Pelaku sistem (`changed_by_user_id` kosong) | BR-13 |

### 4.8 Halaman publik (PB)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| PB-01 | Cek pesanan dengan kode dan email benar | Status tampil | BR-14 |
| PB-02 | Kode benar tetapi email salah, dan kode tidak ada | Pesan error sama untuk keduanya | BR-14 |
| PB-03 | Percobaan cek pesanan berulang | Dibatasi | BR-14 |
| PB-04 | Input tidak valid (tanggal lampau, tamu nol, email salah) | Ditolak dengan pesan | US-26, US-27 |
| PB-05 | Booking website sukses dan staf konfirmasi | Email booking received dan booking confirmed diantrekan (email dipalsukan dalam tes) | US-27, US-15 |
| PB-06 | Booking OTA atau tamu tanpa email | Tidak ada email konfirmasi | BR-09 |

### 4.9 Hak akses (AC)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| AC-01 | Setiap rute dan aksi untuk tiap peran (diuji berbasis tabel) | Sesuai matriks hak akses | Matriks hak akses |
| AC-02 | Resepsionis meminta data keuangan lewat API | 403 dan data tidak dikirim | US-04, US-35 |
| AC-03 | Housekeeping membuka halaman selain daftar kamar | 403 | US-04 |
| AC-04 | Pengguna `is_active = false` | Tidak bisa login | US-01 |
| AC-05 | Manager membuat atau mengubah Owner | Ditolak | US-03 |

### 4.10 Analitik (AN)

| ID | Skenario | Hasil yang diharapkan | Dasar |
| --- | --- | --- | --- |
| AN-01 | Okupansi harian pada dataset kecil | Sama dengan hitungan manual | BR-10 |
| AN-02 | Okupansi periode | Σ terjual ÷ Σ tersedia, bukan rata-rata persentase harian | BR-10 |
| AN-03 | Booking pending, cancelled, expired, no_show | Tidak dihitung sebagai kamar terjual | BR-10 |
| AN-04 | ADR saat terjual nol, dan RevPAR | ADR kosong. RevPAR sama dengan ADR × okupansi | BR-10 |
| AN-05 | Blokir kamar | Mengurangi kamar tersedia | BR-10 |
| AN-06 | Booking multi-kamar pada lama menginap dan lead time | Dihitung sekali per booking | BR-10 |
| AN-07 | Tingkat pembatalan dan no-show | Penyebut sesuai definisi, pending dan expired tidak dihitung | BR-10 |
| AN-08 | Kas diterima | Pembayaran verified dikurangi refund, per tanggal pembayaran | BR-10 |
| AN-09 | Seeder dummy: sampel bulan tertentu | Angka dashboard sama dengan hitungan manual di spreadsheet | US-31 |
| AN-10 | Diskon pada booking | ADR dan RevPAR memakai harga sebelum diskon | BR-12 |
| AN-11 | Performa query dengan data dummy 12 bulan | Memenuhi target waktu yang ditetapkan (angka target diputuskan setelah pengukuran awal) | Arsitektur |

## 5. Keamanan dasar (SC)

| ID | Pemeriksaan | Hasil yang diharapkan |
| --- | --- | --- |
| SC-01 | Request tanpa token CSRF | Ditolak |
| SC-02 | Input berisi skrip pada nama tamu dan catatan | Di-escape saat ditampilkan |
| SC-03 | Percobaan login berulang | Dibatasi |
| SC-04 | Password di database | Ter-hash |
| SC-05 | Log aplikasi | Tidak memuat data pribadi atau token |
| SC-06 | Parameter query | Memakai binding, tidak ada penggabungan string mentah |
| SC-07 | Data keuangan pada respons peran tanpa hak | Tidak ada |

## 6. Uji kegunaan

**Peserta:** 3 sampai 5 orang (teman atau dosen) yang berperan sebagai resepsionis. Beri latar singkat tentang guesthouse. Tiap peserta mengerjakan tugas yang sama dengan data dummy.

| Tugas | Skenario | Diukur |
| --- | --- | --- |
| T1 | Tamu menghubungi lewat WhatsApp dan minta 2 malam akhir pekan untuk 2 orang, catat booking beserta DP | Waktu, jumlah kesalahan, selesai atau tidak |
| T2 | Cari tanggal terdekat yang masih punya kamar Family kosong | Waktu, selesai atau tidak |
| T3 | Tamu minta perpanjang satu malam | Waktu, kesalahan |
| T4 | Tamu membatalkan, catat alasan dan perlakuan DP | Waktu, kesalahan |
| T5 | Check-in tamu dan catat pelunasan | Waktu, kesalahan |
| T6 | Jawab dari dashboard: okupansi bulan lalu dan sumber booking terbanyak | Benar atau salah, waktu |

Metrik yang dilaporkan (sesuai catatan project): waktu mencatat satu booking, jumlah kesalahan atau bentrok, dan kemudahan memahami laporan.

**Pembanding:** agar klaim "lebih cepat atau lebih sedikit salah" punya dasar, minta peserta yang sama mengerjakan T1 di spreadsheet atau buku sebagai pembanding. Tanpa pembanding, waktu hanya angka tanpa konteks.

**Kuesioner:** SUS bersifat opsional (boleh dipangkas). Cukup tiga pertanyaan skala 1-5 tentang kemudahan input, kejelasan kalender, dan kejelasan laporan.

Catat jumlah peserta kecil dan bukan pengguna guesthouse sungguhan sebagai keterbatasan di laporan.

## 7. Kriteria lulus MVP

| Kriteria | Bukti |
| --- | --- |
| Semua tes otomatis lulus, termasuk AV, PR, BK, ST, CH, PY, EX, AC | Hasil tes dan cakupan |
| Tanggal bentrok selalu ditolak | BK-04 dan BK-05 lulus |
| Angka dashboard sama dengan hitungan manual | AN-09 |
| Alur end-to-end berjalan tanpa error | Skrip E2E atau demo terekam |
| Uji kegunaan selesai | Lembar catatan dan ringkasan metrik |
| Pemeriksaan keamanan dasar selesai | Daftar SC |