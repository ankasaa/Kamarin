# Business Rules Kamarin (Draf v1.1)

Diperbarui: 8 Oktober 2026

**Perubahan v1.1 (ERD tetap v2.1, tidak berubah):** default expiry booking pending menjadi 3 jam, diskon dicatat sebagai biaya tambahan bernilai negatif, dan `capacity` dipahami sebagai kapasitas maksimum.

**Legenda status:**

- ✅ sudah disetujui sebelumnya
- 🟦 usulan baru, menunggu konfirmasi (kode K1 sampai K10 di bawah)
- ⚠️ asumsi atau nilai placeholder yang perlu dicek ke narasumber

Aturan bernomor BR-01 sampai BR-10 mengikuti draf dari catatan wawancara. BR-11 sampai BR-14 ditambahkan karena dibutuhkan oleh desain.

---

## 0. Keputusan yang perlu dikonfirmasi

| Kode | Keputusan | Usulan default |
| --- | --- | --- |
| K1 | minimum_nights dipakai dari tarif yang berlaku pada malam check-in | Ya. Owner dan Manager boleh override dengan alasan, tercatat |
| K2 | Dua tarif aktif yang tumpang tindih dengan priority sama | Ditolak saat disimpan. Fallback jika tetap seri: id terbesar menang |
| K3 | Harga malam yang tidak berubah saat booking diubah | Terkunci (tidak dihitung ulang). Hanya malam baru yang memakai tarif berlaku |
| K4 | Alasan perubahan booking | Wajib untuk semua peran, bukan hanya resepsionis |
| K5 | expires_at untuk booking pending | Berlaku untuk booking dari website (semua metode bayar). Booking yang dibuat staf tidak expire |
| K6 | Konfirmasi booking transfer bank | Butuh DP terverifikasi. Override hanya Owner dan Manager dengan alasan |
| K7 | Batas waktu no-show | Setelah pukul 22:00 pada tanggal check-in (⚠️ placeholder, kolom baru di pengaturan properti) |
| K8 | Pembatalan oleh tamu | Di MVP hanya lewat staf. Pembatalan mandiri di website masuk Tahap 2 |
| K9 | Pendapatan untuk ADR dan RevPAR | Pendapatan kamar per malam menginap (accrual). Kas diterima dilaporkan terpisah |
| K10 | Booking dari OTA | Diinput manual dengan sumber OTA, pembayaran mengikuti OTA (nilai baru `via_ota`) |

---

## 1. Istilah dasar

| Istilah | Definisi |
| --- | --- |
| Malam menginap | Setiap tanggal dari check_in_date sampai sehari sebelum check_out_date. Hari check-out tidak dihitung. Contoh: 10 sampai 12 Oktober = malam 10 dan 11 (2 malam) |
| Malam aktif | Baris `booking_nights` dengan `is_active = true`. Aktif untuk booking berstatus pending, confirmed, checked_in, checked_out. Tidak aktif untuk cancelled, expired, no_show |
| Kamar terjual | Malam aktif milik booking berstatus confirmed, checked_in, atau checked_out. Booking pending ditampilkan terpisah sebagai "menunggu konfirmasi" |
| Tanggal dan zona waktu | Semua tanggal adalah tanggal kalender lokal properti. Zona waktu aplikasi diatur satu kali di `.env` (Fase 4) |
| Mata uang | Rupiah (IDR), bilangan bulat tanpa desimal |

---

## 2. Ketersediaan, kamar, dan housekeeping

### BR-01 Pencegahan double booking ✅

1. Kamar **r** tersedia untuk malam **d** jika semua syarat ini terpenuhi: (a) `rooms.status = active`, (b) tidak ada malam aktif untuk (r, d), (c) tidak ada `room_blocks` yang mencakup d.
2. Booking valid jika: semua malam tersedia, `check_out_date > check_in_date`, `guest_count` tidak melebihi total kapasitas kamar yang dipesan (`room_types.capacity` adalah kapasitas **maksimum**. Extra bed dicatat sebagai biaya tambahan tanpa penghitungan otomatis di MVP), dan jumlah malam memenuhi minimum_nights (BR-11).
3. Check-out tamu A dan check-in tamu B pada tanggal yang sama untuk kamar yang sama diperbolehkan (turnover di hari yang sama).
4. `check_in_date` tidak boleh lebih awal dari hari ini. Owner dan Manager boleh memakai tanggal lampau dengan alasan, untuk koreksi pencatatan 🟦.
5. Penegakan tiga lapis: (1) pengecekan di service, (2) kunci baris kamar dan cek ulang di dalam transaksi, (3) unique index parsial `(room_id, stay_date) WHERE is_active`.
6. Index hanya menjaga booking melawan booking. Bentrok antara booking dan `room_blocks` dijaga di service dan wajib diuji otomatis. Membuat blokir pada tanggal yang sudah punya malam aktif ditolak.

### BR-02 Jam check-in dan check-out ✅

1. Jam diambil dari pengaturan properti. Default check-in 14:00 dan check-out 12:00.
2. Sistem tidak memblokir check-in lebih awal. Check-in lebih awal tidak menambah malam.
3. Late check-out tidak otomatis. Jika dikenakan biaya, dicatat manual sebagai biaya tambahan.

### BR-07 Status kamar dan kebersihan ✅ (revisi disetujui)

1. `rooms.status` hanya `active`, `maintenance`, atau `inactive`. Kamar selain `active` tidak bisa dijual.
2. Mengubah kamar ke `maintenance` atau `inactive` ditolak jika masih punya malam aktif di masa depan. Booking harus dipindahkan lebih dulu 🟦.
3. Status kebersihan (`housekeeping_statuses`): `dirty`, `cleaning`, `ready`. Perubahan: dirty → cleaning → ready oleh Housekeeping, Resepsionis, Manager, atau Owner.
4. Saat booking checked_out, kamar otomatis menjadi `dirty`.
5. Status kebersihan **tidak memengaruhi ketersediaan tanggal mendatang**. Pada check-in hari ini, jika kamar belum `ready`, sistem menampilkan peringatan tetapi tidak memblokir.

---

## 3. Tarif dan harga

### BR-11 Pemilihan tarif ✅ (per malam) dan 🟦 (detail)

1. Harga dihitung **per malam** untuk setiap malam menginap.
2. Kandidat tarif untuk tipe kamar T pada malam d: tarif milik T yang (a) `is_default` atau `start_date ≤ d ≤ end_date`, dan (b) `days_of_week` kosong atau memuat hari dari d.
3. Pemenang adalah tarif dengan `priority` tertinggi. Validasi saat tarif disimpan menolak dua tarif aktif yang bertumpuk (tipe, rentang tanggal, hari) dengan priority sama. Jika tetap seri, id terbesar menang (K2).
4. Setiap tipe kamar aktif wajib punya satu tarif default dengan priority terendah, sehingga selalu ada tarif yang cocok. Kolom `base_price` sudah dihapus di ERD v2.1 agar harga hanya punya satu sumber. Jika karena data rusak tidak ada tarif yang cocok, pembuatan booking ditolak dengan pesan yang jelas, bukan memakai harga tebakan.
5. `days_of_week` memakai ISO 8601: 1 = Senin sampai 7 = Minggu. Contoh akhir pekan: `5,6` (malam Jumat dan Sabtu) ⚠️ nilai sebenarnya dari narasumber.
6. Contoh susunan priority: default 0, akhir pekan 10, musim ramai 20, musim ramai khusus akhir pekan 30.
7. Harga tiap malam disimpan sebagai salinan di `booking_nights.price` saat booking dibuat. Perubahan tarif sesudahnya tidak mengubah booking yang sudah ada.
8. `minimum_nights` diambil dari tarif pemenang pada **malam check-in**. Owner dan Manager boleh override dengan alasan, tercatat di log (K1).

---

## 4. Total, pembayaran, dan kedaluwarsa

### BR-12 Total dan status pembayaran 🟦 (rumus), ✅ (status dihitung)

| Besaran | Rumus |
| --- | --- |
| Subtotal kamar | Σ `booking_nights.price` untuk malam aktif milik booking (sama dengan Σ `booking_items.subtotal`) |
| Biaya tambahan | Σ `booking_charges.amount` |
| `total_amount` | Subtotal kamar + biaya tambahan |
| Terbayar | Σ `payments.amount` berstatus verified bertipe deposit atau settlement, dikurangi Σ yang bertipe refund |
| Sisa tagihan | `total_amount` − terbayar |
| Status bayar | `unpaid` jika terbayar = 0, `partial` jika 0 < terbayar < total, `paid` jika terbayar ≥ total |

1. MVP tidak memakai kolom diskon dan pajak. Harga dianggap sudah final (⚠️ cek apakah guesthouse narasumber memakai pajak atau service charge terpisah). **Diskon** dicatat sebagai baris `booking_charges` bernilai negatif dengan deskripsi "Diskon" dan alasannya. Hanya Owner dan Manager yang boleh membuatnya. Total tetap = subtotal kamar + Σ `booking_charges`. Laporan memisahkan biaya tambahan (positif) dari diskon (negatif).
2. `total_amount` disimpan di `bookings` dan dihitung ulang setiap ada perubahan malam, kamar, atau biaya. Sumber kebenarannya tetap `booking_nights` dan `booking_charges`. Tes otomatis memastikan keduanya konsisten.
3. Jika terbayar melebihi total (misalnya setelah booking dipercepat), booking ditandai "kelebihan bayar" sampai dicatat refund atau catatan penyelesaian.

### BR-03 DP ✅ (konsep), ⚠️ (nilai)

1. DP wajib = `total_amount × deposit_percent / 100`, dibulatkan ke rupiah terdekat. Default 50%.
2. Berlaku untuk booking website dengan metode transfer bank. Booking staf boleh meminta DP sesuai kebijakan (opsional). Bayar saat tiba tidak butuh DP.
3. DP hanya sah jika pembayarannya berstatus `verified`.

### BR-04 Pelunasan ✅

1. Sisa tagihan dibayar saat check-in (default).
2. Saat check-in dan check-out, jika sisa tagihan masih ada, sistem menampilkan peringatan tetapi tidak memblokir.
3. Pembayaran dicatat manual oleh staf: jumlah, tipe, metode, nama pembayar (`payer_name`, bisa berbeda dari nama tamu), tanggal, referensi, dan bukti opsional. Alur status pembayaran: pending → verified atau failed; refunded untuk yang dikembalikan.

### BR-13 Kedaluwarsa booking pending 🟦

1. Booking pending dari website mendapat `expires_at = waktu dibuat + pending_expiry_hours` (default 3 jam), baik transfer bank maupun bayar saat tiba. ⚠️ Default pendek karena kamar guesthouse sedikit. Booking yang dikirim malam hari bisa expired sebelum staf bertugas, jadi nilai ini disesuaikan per guesthouse. Booking yang dibuat staf tidak punya `expires_at` (K5).
2. Scheduler memeriksa secara berkala (mis. tiap 5 menit). Booking yang lewat `expires_at` menjadi `expired`, malamnya dinonaktifkan, dan dicatat di log status (pelaku: sistem).
3. Konfirmasi mengosongkan `expires_at`. Booking `expired` tidak bisa dikonfirmasi. Tamu harus membuat booking baru, yang mengecek ketersediaan ulang.
4. Perubahan tanggal pada booking pending tidak memperpanjang `expires_at`.

### BR-14 Kode booking dan cek pesanan 🟦

1. Kode booking acak 8 karakter (huruf besar dan angka, tanpa karakter yang mudah tertukar seperti 0/O dan 1/I), unik, dan tidak berurutan.
2. Cek pesanan membutuhkan kode **dan** email yang cocok. Jika salah satu salah, pesan error sama (tidak membocorkan apakah kode ada). Percobaan dibatasi (rate limiting).

---

## 5. Alur status dan hak akses

### Matriks transisi status × peran 🟦

Peran: **O** = Owner, **M** = Manager, **R** = Resepsionis. Housekeeping tidak mengubah status booking.

| Transisi | Siapa | Syarat | Efek |
| --- | --- | --- | --- |
| baru → pending | Tamu (website), O, M, R | Ketersediaan valid | Malam aktif dibuat, log status, expires_at (website), email "booking received" (website) |
| pending → confirmed | O, M, R | Transfer bank: DP terverifikasi ≥ DP wajib. Override hanya O dan M dengan alasan (K6) | expires_at dikosongkan, email "booking confirmed" |
| pending → expired | Sistem | Waktu ≥ expires_at | Malam dinonaktifkan |
| pending → cancelled | O, M, R (R wajib alasan) | Alasan wajib | Malam dinonaktifkan, `cancelled_at` dan `cancellation_reason` terisi |
| confirmed → cancelled | O, M, R (R wajib alasan) | Alasan wajib. Sistem menampilkan saran DP (BR-05) | Malam dinonaktifkan. Refund dicatat jika DP dikembalikan |
| confirmed → checked_in | O, M, R | Tanggal check-in = hari ini atau sudah lewat, belum no_show. Peringatan housekeeping | `checked_in_at` terisi |
| confirmed → no_show | O, M, R | Sekarang ≥ tanggal check-in pukul `no_show_cutoff_time` (K7) | Semua malam dinonaktifkan. Default DP hangus |
| checked_in → checked_out | O, M, R | Peringatan jika sisa tagihan > 0 | `checked_out_at` terisi, kamar menjadi `dirty` |
| checked_in → cancelled | Tidak diizinkan | Gunakan perubahan booking untuk mempercepat check-out | |
| Status akhir (checked_out, cancelled, expired, no_show) | Tidak bisa dibuka kembali | Buat booking baru | |

Keterangan:

1. Tamu di MVP hanya melihat status lewat halaman cek pesanan. Pembatalan oleh tamu dicatat staf setelah tamu menghubungi lewat WhatsApp atau telepon (K8).
2. Resepsionis yang membatalkan **tidak boleh** menyimpang dari saran kebijakan DP. Penyimpangan dilakukan Owner atau Manager.

### BR-05 Pembatalan ✅ (konsep), ⚠️ (angka)

1. Alasan pembatalan wajib diisi.
2. Jika dibatalkan paling lambat `free_cancel_days` hari sebelum `check_in_date` (selisih tanggal ≥ nilai itu; default 2 hari), saran sistem: DP dikembalikan atau dijadwal ulang. Jika lebih dekat, saran sistem: DP hangus.
3. DP dikembalikan dicatat sebagai pembayaran bertipe `refund`. DP hangus ditandai dengan tidak adanya refund (DP tetap tercatat).
4. Penjadwalan ulang (pindah tanggal) bukan pembatalan. Itu perubahan booking (BR-06).

### BR-08 No-show 🟦 (detail), ⚠️ (batas waktu)

1. No-show hanya untuk booking `confirmed`, dan hanya setelah batas waktu (default pukul 22:00 pada tanggal check-in).
2. Dicatat manual oleh staf. Efeknya: status `no_show`, semua malam booking dinonaktifkan sehingga kamar bisa dijual kembali, DP default hangus.

---

## 6. Perubahan booking

### BR-06 Perubahan booking 🟦

Jenis perubahan: ubah tanggal, perpanjang, percepat check-out, pindah kamar, ubah jumlah tamu, tambah atau hapus kamar pada booking multi-kamar.

| Aspek | Aturan |
| --- | --- |
| Siapa | O, M, R. Alasan wajib untuk semua (K4) |
| Status yang boleh diubah | pending dan confirmed (semua jenis). checked_in hanya perpanjang, percepat check-out, dan pindah kamar. Status akhir tidak boleh diubah |
| Cek ketersediaan | Dilakukan ulang di dalam transaksi pada malam baru. Malam milik booking itu sendiri tidak dihitung sebagai bentrok |
| Harga | Malam yang tidak berubah tetap memakai harga lama (K3). Malam baru memakai tarif berlaku saat itu. Pindah ke tipe kamar lain: malam yang dipindah dihitung ulang dengan tarif tipe baru dan selisihnya ditampilkan sebelum disimpan |
| minimum_nights | Dicek ulang pada check-in yang baru. Override hanya O dan M |
| Pembayaran | `total_amount` dan sisa tagihan dihitung ulang. Jika terbayar melebihi total, ditandai kelebihan bayar |
| expires_at | Tidak diperpanjang otomatis (BR-13) |
| Pencatatan | Satu baris `booking_change_logs` per perubahan: jenis, nilai lama, nilai baru, alasan, pelaku |
| Notifikasi | Tidak ada email otomatis di MVP. Staf memberi tahu tamu lewat WhatsApp |
| Percepat check-out saat checked_in | Malam setelah tanggal baru dinonaktifkan. Refund tidak otomatis, staf memutuskan dan mencatat |

---

## 7. Sumber booking

### BR-09 Sumber booking ✅ (wajib), 🟦 (nilai awal)

1. Setiap booking wajib punya sumber. Booking dari website otomatis bersumber Website.
2. Nilai awal master data (bisa diubah Owner dan Manager): Website, WhatsApp, Telepon, Walk-in, Instagram, OTA Traveloka, OTA Agoda, OTA Booking.com, Lainnya. OTA dipisah per platform agar laporan bisa membandingkan.
3. Booking OTA diinput manual dengan `payment_option = via_ota`. Pembayaran mengikuti mekanisme OTA, tidak ada email otomatis ke tamu. Sinkronisasi iCal masuk Tahap 2 (K10).

---

## 8. Metrik dan laporan

### BR-10 Definisi metrik 🟦

Semua metrik dihitung dari data (tidak ada tabel analitik) dan dikelompokkan menurut **tanggal menginap**, kecuali disebut lain.

| Metrik | Rumus | Catatan |
| --- | --- | --- |
| Kamar tersedia (room-nights) | Per hari: jumlah kamar berstatus bukan `inactive` saat ini, dikurangi kamar yang diblokir pada hari itu | ⚠️ Tidak ada riwayat status kamar, jadi kamar dianggap ada sepanjang periode. Tulis sebagai keterbatasan di laporan |
| Kamar terjual | Jumlah malam aktif milik booking confirmed, checked_in, checked_out pada hari itu | Pending tidak dihitung |
| Okupansi (%) | Kamar terjual ÷ kamar tersedia × 100 | Untuk periode: Σ terjual ÷ Σ tersedia, bukan rata-rata persentase harian |
| Pendapatan kamar | Σ `booking_nights.price` untuk kamar terjual dalam periode | Tidak termasuk biaya tambahan dan DP hangus, dan dihitung sebelum diskon |
| ADR | Pendapatan kamar ÷ kamar terjual | Kosong (null) jika terjual = 0 |
| RevPAR | Pendapatan kamar ÷ kamar tersedia | Sama dengan ADR × okupansi |
| Lama menginap | Rata-rata dan median jumlah malam per booking | Populasi: booking confirmed ke atas dengan check-in dalam periode. Booking multi-kamar dihitung satu kali |
| Lead time | `check_in_date` − tanggal booking dibuat (hari) | Median dan sebaran: 0-1, 2-7, 8-30, lebih dari 30 hari. Populasi sama dengan lama menginap |
| Tingkat pembatalan | Booking cancelled ÷ booking berstatus akhir (confirmed, checked_in, checked_out, cancelled, no_show) dengan check-in dalam periode | pending dan expired tidak dihitung. Tingkat no-show dilaporkan terpisah dengan penyebut yang sama |
| Sumber booking | Jumlah booking dan pendapatan kamar per sumber | Populasi: confirmed ke atas |
| Pendapatan per tipe kamar | Pendapatan kamar per tipe | |
| Kas diterima | Σ pembayaran verified (deposit, settlement) − Σ refund verified, per tanggal pembayaran | Termasuk biaya tambahan dan DP hangus. Dilaporkan terpisah dari pendapatan kamar (K9) |
| Booking belum lunas | Booking confirmed, checked_in, checked_out dengan terbayar < total | Daftar operasional |
| Menunggu konfirmasi | Booking berstatus pending | Daftar operasional |

Akses laporan keuangan (pendapatan, ADR, RevPAR, kas) hanya Owner dan Manager, sesuai matriks hak akses.

---

## 9. Data dan privasi

1. `guests.id_number` opsional dan tidak diisi pada data dummy.
2. Booking dan tamu memakai soft delete.
3. Tamu dicari berdasarkan email atau telepon. Sistem tidak menggabungkan data tamu secara otomatis.
4. Log tidak boleh memuat data sensitif.

---

## 10. Pengaturan properti (nilai default)

| Pengaturan | Default | Dasar |
| --- | --- | --- |
| `check_in_time` | 14:00 | Narasumber (n=5): 14:00 atau 15:00 |
| `check_out_time` | 12:00 | Narasumber: 11:00 atau 12:00 |
| `deposit_percent` | 50 | Narasumber: 30-50%, kebanyakan 50% |
| `pending_expiry_hours` | 3 | ⚠️ Placeholder. Menahan kamar tanpa pembayaran terlalu lama merugikan guesthouse kecil. Sesuaikan per guesthouse |
| `free_cancel_days` | 2 | Narasumber: antara 24 jam dan 2 hari |
| `no_show_cutoff_time` (kolom baru) | 22:00 | ⚠️ Placeholder, belum ada data narasumber |

---

## 11. Perubahan ERD v2 ke v2.1 (sudah diterapkan di `erd_v2_1.mermaid`)

| # | Perubahan | Alasan |
| --- | --- | --- |
| 1 | Tambah `properties.no_show_cutoff_time` (time) | BR-08 |
| 2 | Tambah nilai `via_ota` pada `bookings.payment_option` dan `ota` pada `payments.method` | BR-09, K10 |
| 3 | `bookings.created_at` eksplisit, dan semua tabel memakai timestamp standar Laravel | Lead time dan expiry |
| 4 | Hapus `room_types.base_price` | BR-11: harga hanya dari tabel tarif |
| 5 | `rates.days_of_week` dari teks menjadi `jsonb` (ISO 1 sampai 7) | Lebih mudah di-query |
