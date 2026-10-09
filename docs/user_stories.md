# User Stories dan Acceptance Criteria Kamarin (Draf v1)

Diperbarui: 8 Oktober 2026

**Sumber kebenaran:** `erd_v2_1.mermaid` dan `business_rules_kamarin.md` (v1.1). Nama tabel, kolom, dan status memakai penulisan di ERD. Antarmuka berbahasa Inggris, sedangkan cerita ditulis dalam bahasa Indonesia.

**Prioritas:** **M** = Must (inti MVP, tidak dipangkas), **S** = Should (dikerjakan jika waktu cukup), **C** = Could (pertama dipangkas jika waktu mepet). Prioritas selaras dengan daftar "boleh dipotong" di checklist dan jalur kritis.

Kolom "Aturan" menunjuk ke Business Rules (BR) yang menjadi dasar kriteria.

---

## Epic A. Akses dan pengguna

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-01 | Sebagai staf, saya ingin login dan logout agar hanya orang berwenang yang memakai sistem | 1. Kredensial benar membuka dashboard sesuai peran<br>2. Kredensial salah menampilkan pesan umum tanpa menyebut mana yang salah<br>3. Pengguna dengan `users.is_active = false` ditolak<br>4. Percobaan login dibatasi | | M |
| US-02 | Sebagai staf, saya ingin mereset password lewat email agar tidak terkunci | 1. Tautan reset berlaku sekali dan kedaluwarsa<br>2. Password baru disimpan ter-hash | | S |
| US-03 | Sebagai Owner, saya ingin mengelola pengguna dan peran agar akses sesuai tugas | 1. Owner dapat membuat, mengubah peran, dan menonaktifkan pengguna<br>2. Manager hanya dapat mengelola pengguna secara terbatas dan tidak dapat membuat atau mengubah Owner<br>3. Pengguna nonaktif tidak dapat login | Matriks hak akses | M |
| US-04 | Sebagai pemilik sistem, saya ingin akses dibatasi per peran agar data tidak bocor | 1. Housekeeping hanya melihat daftar kamar dan status kebersihan<br>2. Resepsionis tidak melihat halaman maupun angka keuangan, termasuk di respons server<br>3. Akses terlarang mengembalikan 403 | Matriks hak akses | M |

## Epic B. Data master

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-05 | Sebagai Manager, saya ingin mengelola tipe kamar dan kamar agar inventaris sesuai kenyataan | 1. Tipe kamar punya `capacity` (maksimum) dan fasilitas lewat `room_type_amenities`<br>2. `room_number` unik per properti<br>3. `rooms.status` hanya active, maintenance, atau inactive<br>4. Mengubah ke maintenance atau inactive ditolak jika masih ada malam aktif di masa depan | BR-07 | M |
| US-06 | Sebagai Manager, saya ingin mengelola tarif agar harga akhir pekan dan musim ramai benar | 1. Setiap tipe kamar aktif wajib punya satu tarif default<br>2. Dua tarif aktif yang tumpang tindih dengan priority sama ditolak<br>3. `days_of_week` memakai ISO 1 sampai 7<br>4. Contoh priority (default, akhir pekan, musim ramai, kombinasi) menghasilkan harga yang sesuai | BR-11 | M |
| US-07 | Sebagai Manager, saya ingin mengatur kebijakan properti agar aturan sesuai guesthouse | 1. `check_in_time`, `check_out_time`, `deposit_percent`, `pending_expiry_hours`, `free_cancel_days`, dan `no_show_cutoff_time` dapat diubah<br>2. Perubahan langsung dipakai aturan terkait pada booking baru | BR-02, BR-03, BR-05, BR-08, BR-13 | M |
| US-08 | Sebagai Manager, saya ingin memblokir tanggal kamar agar kamar tidak terjual saat dipakai lain | 1. Kamar yang diblokir tidak tersedia untuk tanggal itu<br>2. Blokir pada tanggal yang punya malam aktif ditolak<br>3. Alasan blokir tercatat | BR-01 | M |
| US-09 | Sebagai Manager, saya ingin mengelola sumber booking agar laporan per sumber akurat | 1. Nilai awal tersedia (Website, WhatsApp, Telepon, Walk-in, Instagram, OTA per platform, Lainnya)<br>2. Nama sumber unik | BR-09 | S |

## Epic C. Kalender dan ketersediaan

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-10 | Sebagai resepsionis, saya ingin melihat kalender timeline agar tahu kamar terisi dan kosong sekilas | 1. Grid kamar × tanggal untuk 7, 14, atau 30 hari, dikelompokkan per tipe<br>2. Batang per booking diwarnai menurut status<br>3. Blokir dan maintenance tampil berbeda<br>4. Hari ini disorot<br>5. Klik batang membuka panel detail<br>6. Filter tipe kamar dan status | BR-01 | M |
| US-11 | Sebagai resepsionis, saya ingin membuat booking dari sel kosong agar input lebih cepat | 1. Kamar dan tanggal awal terisi dari sel yang diklik | | S |

## Epic D. Pemesanan oleh staf

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-12 | Sebagai resepsionis, saya ingin mencatat booking dari WhatsApp, telepon, walk-in, atau OTA agar semua tercatat di satu tempat | 1. Hanya kamar tersedia yang ditampilkan, dengan rincian harga per malam<br>2. Kapasitas dan `minimum_nights` divalidasi<br>3. Sumber booking wajib<br>4. Status awal pending dan kode booking 8 karakter dibuat<br>5. Tanggal check-in masa lalu ditolak untuk resepsionis (Owner dan Manager boleh dengan alasan)<br>6. `expires_at` kosong untuk booking staf | BR-01, BR-09, BR-11, BR-13, BR-14 | M |
| US-13 | Sebagai sistem, saya ingin mencegah bentrok agar tidak ada double booking | 1. Dua permintaan bersamaan untuk kamar dan malam yang sama menghasilkan tepat satu booking<br>2. Permintaan yang kalah menerima pesan jelas<br>3. Tidak ada data setengah tersimpan | BR-01 | M |
| US-14 | Sebagai resepsionis, saya ingin mencatat pembayaran agar status bayar jelas | 1. Memuat jumlah, tipe (deposit, settlement, refund), metode, dan `payer_name`<br>2. Hanya pembayaran verified yang dihitung ke terbayar<br>3. Terbayar, sisa tagihan, dan status bayar (unpaid, partial, paid) diperbarui | BR-03, BR-04, BR-12 | M |
| US-15 | Sebagai resepsionis, saya ingin mengonfirmasi booking agar tamu tahu pesanan diterima | 1. Transfer bank hanya bisa dikonfirmasi jika DP verified mencapai DP wajib<br>2. Override hanya Owner atau Manager dengan alasan<br>3. `expires_at` dikosongkan<br>4. Email booking confirmed diantrekan kecuali sumber OTA atau email tamu kosong | BR-03, BR-13, matriks status | M |
| US-16 | Sebagai resepsionis, saya ingin mengubah tanggal atau memperpanjang booking agar jadwal selalu terbaru | 1. Alasan wajib untuk semua peran<br>2. Ketersediaan dicek ulang secara atomik, malam milik booking sendiri tidak dianggap bentrok<br>3. Harga malam yang tidak berubah tetap, malam baru memakai tarif berlaku<br>4. `minimum_nights` dicek ulang<br>5. Setiap perubahan tercatat di `booking_change_logs`<br>6. Kelebihan bayar ditandai | BR-06 | M |
| US-17 | Sebagai resepsionis, saya ingin membatalkan booking agar kamar bisa dijual lagi | 1. Alasan wajib<br>2. Sistem menyarankan perlakuan DP menurut `free_cancel_days`<br>3. Resepsionis tidak boleh menyimpang dari saran, Owner dan Manager boleh<br>4. Malam dinonaktifkan dan kamar tersedia kembali<br>5. Refund dicatat sebagai pembayaran bertipe refund jika DP dikembalikan<br>6. Booking checked_in tidak dapat dibatalkan | BR-05 | M |
| US-18 | Sebagai resepsionis, saya ingin menandai no-show agar kamar tidak tertahan | 1. Hanya untuk booking confirmed<br>2. Hanya setelah `no_show_cutoff_time` pada tanggal check-in<br>3. Semua malam booking dinonaktifkan<br>4. DP default hangus | BR-08 | M |
| US-19 | Sebagai Owner atau Manager, saya ingin mencatat biaya tambahan dan diskon agar total akurat | 1. Biaya tambahan (staf) menambah total<br>2. Diskon adalah baris `booking_charges` bernilai negatif, hanya Owner dan Manager dengan alasan<br>3. `total_amount` dihitung ulang | BR-12 | S |
| US-20 | Sebagai staf, saya ingin mencari dan melihat detail booking agar cepat menjawab tamu | 1. Filter status, tanggal, dan sumber, serta pencarian kode, nama, atau email<br>2. Detail menampilkan malam beserta harga, pembayaran, riwayat status, dan riwayat perubahan | | M |
| US-21 | Sebagai staf, saya ingin melihat data dan riwayat tamu agar melayani tamu lama dengan baik | 1. Pencarian lewat email atau telepon<br>2. Riwayat menginap tampil<br>3. Data tamu tidak digabung otomatis<br>4. `guests.id_number` opsional | Data dan privasi | S |

## Epic E. Operasional harian

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-22 | Sebagai resepsionis, saya ingin daftar check-in dan check-out hari ini agar shift berjalan teratur | 1. Daftar menampilkan status bayar dan status kebersihan kamar<br>2. Panel menampilkan booking pending dan belum lunas | BR-07 | M |
| US-23 | Sebagai resepsionis, saya ingin melakukan check-in agar status tamu tercatat | 1. Hanya booking confirmed<br>2. Peringatan jika kamar belum ready atau sisa tagihan lebih dari nol, tidak memblokir<br>3. `checked_in_at` terisi dan status log tercatat | BR-04, BR-07 | M |
| US-24 | Sebagai resepsionis, saya ingin melakukan check-out agar kamar siap dibersihkan | 1. Hanya booking checked_in<br>2. Peringatan jika sisa tagihan lebih dari nol, tidak memblokir<br>3. `checked_out_at` terisi dan kamar menjadi dirty | BR-04, BR-07 | M |
| US-25 | Sebagai housekeeping, saya ingin memperbarui status kebersihan agar resepsionis tahu kamar siap | 1. Perubahan dirty, cleaning, ready<br>2. Housekeeping hanya melihat daftar kamar dan status<br>3. Pengubah tercatat di `updated_by_user_id` | BR-07 | M |

## Epic F. Booking publik

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-26 | Sebagai tamu, saya ingin mengecek ketersediaan dan harga agar tahu bisa menginap atau tidak | 1. Input tanggal dan jumlah tamu<br>2. Hasil menampilkan tipe tersedia beserta total<br>3. Jika tidak tersedia, pesan jelas<br>4. `minimum_nights` ditampilkan jika relevan<br>5. Nyaman di layar ponsel | BR-01, BR-11 | M |
| US-27 | Sebagai tamu, saya ingin mengirim pesanan agar kamar tertahan untuk saya | 1. Data tamu divalidasi dan metode bayar dipilih (transfer bank atau bayar saat tiba)<br>2. Ketersediaan dicek ulang di dalam transaksi, jika bentrok pesan jelas dan tidak ada booking tersimpan<br>3. Booking pending bersumber Website dengan kode booking dan `expires_at`<br>4. Halaman sukses menampilkan kode dan instruksi pembayaran<br>5. Email booking received diantrekan | BR-01, BR-13, BR-14 | M |
| US-28 | Sebagai tamu, saya ingin mengecek status pesanan agar tahu sudah dikonfirmasi | 1. Memerlukan kode booking dan email yang cocok<br>2. Jika salah satu salah, pesan error sama<br>3. Percobaan dibatasi | BR-14 | M |
| US-29 | Sebagai sistem, saya ingin melepas booking pending yang kedaluwarsa agar kamar tidak tertahan | 1. Booking website yang lewat `expires_at` menjadi expired<br>2. Malam dinonaktifkan dan log status mencatat pelaku sistem<br>3. Menjalankan scheduler dua kali tidak menimbulkan error | BR-13 | M |
| US-30 | Sebagai staf, saya ingin tahu ada booking baru dari website agar segera diproses | 1. Penanda jumlah booking pending dari website<br>2. Daftar booking menunggu konfirmasi | | S |

## Epic G. Analitik dan laporan

| ID | Cerita | Kriteria penerimaan | Aturan | P |
| --- | --- | --- | --- | --- |
| US-31 | Sebagai Owner atau Manager, saya ingin KPI utama agar tahu performa guesthouse | 1. Okupansi, ADR, RevPAR, pendapatan kamar, dan jumlah booking untuk periode terpilih dengan pembanding periode sebelumnya<br>2. Rumus sesuai BR-10<br>3. Angka cocok dengan hitungan manual pada sampel | BR-10 | M |
| US-32 | Sebagai Owner atau Manager, saya ingin tren dan distribusi agar memahami pola | 1. Tren okupansi dan pendapatan<br>2. Booking dan pendapatan per sumber, pendapatan per tipe kamar<br>3. Lama menginap dan lead time<br>4. Tingkat pembatalan dan no-show terpisah | BR-10 | M |
| US-33 | Sebagai Owner atau Manager, saya ingin tahu kamar paling laku dan tanggal kosong agar bisa mengatur harga | 1. Peringkat kamar atau tipe<br>2. Tanggal kosong ke depan | BR-10 | S |
| US-34 | Sebagai Owner atau Manager, saya ingin laporan bulanan yang dapat diekspor | 1. Satu format (PDF atau Excel)<br>2. Angka sama dengan dashboard | BR-10 | C |
| US-35 | Sebagai resepsionis, saya ingin ringkasan operasional tanpa data keuangan agar fokus pada tugas | 1. Panel check-in, check-out, pending, dan belum lunas tampil<br>2. Kartu dan grafik keuangan tidak ada di UI maupun respons API | Matriks hak akses | M |

---

## Ringkasan prioritas

| Prioritas | Jumlah | Keterangan |
| --- | --- | --- |
| M (Must) | 27 | Inti MVP, tidak dipangkas |
| S (Should) | 7 | Dikerjakan jika waktu cukup |
| C (Could) | 1 | Pertama dipangkas jika waktu mepet |
| **Total** | **35** | |

Aturan pemangkasan jika waktu mepet: pangkas C lebih dulu, lalu S, mulai dari yang paling tidak mengganggu alur inti. Cerita M tidak dipangkas.
