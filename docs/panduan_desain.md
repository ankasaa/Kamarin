# Panduan Desain Dasar Kamarin (Draf v1)

Diperbarui: 8 Oktober 2026

**Sumber kebenaran:** nama status dan istilah mengikuti `erd_v2_1.mermaid` dan `business_rules_kamarin.md` (v1.1). Antarmuka berbahasa Inggris, jadi label di dokumen ini ditulis dalam bahasa Inggris.

Dokumen ini berisi **usulan** untuk dikunci setelah wireframe selesai. Nama warna memakai palet Tailwind agar langsung bisa dipakai. Kontras teks perlu dicek dengan alat pemeriksa kontras sebelum final, jangan diasumsikan.

---

## 1. Prinsip

1. Dashboard staf adalah alat kerja: padat tetapi terbaca. Prioritaskan kecepatan input dan kejelasan status.
2. Status tidak boleh dibedakan hanya dengan warna. Selalu sertakan teks atau ikon.
3. Dashboard staf desktop-first. Halaman booking publik mobile-first karena tamu memesan dari ponsel.
4. Satu pola untuk satu hal. Detail booking selalu dibuka di panel samping (drawer), form booking selalu memakai tata letak yang sama.
5. Peringatan tidak memblokir (kebersihan kamar, sisa tagihan) dan harus terlihat berbeda dari error yang memblokir.

## 2. Warna

### Antarmuka umum

| Peran | Usulan |
| --- | --- |
| Primary (tombol utama, tautan, item aktif) | teal-600, hover teal-700 |
| Netral (teks, border, latar) | slate-900 untuk teks, slate-500 untuk teks sekunder, slate-200 untuk border, slate-50 untuk latar halaman |
| Sukses, peringatan, error | emerald-600, amber-500, red-600 |

### Status booking (nilai `bookings.status`)

| Status | Label UI | Warna batang timeline dan badge | Penanda tambahan |
| --- | --- | --- | --- |
| pending | Pending | amber-400, teks slate-900 | Ikon jam |
| confirmed | Confirmed | blue-500, teks putih | Ikon centang |
| checked_in | Checked in | green-600, teks putih | Ikon kunci atau pintu |
| checked_out | Checked out | slate-400, teks slate-900 | Ikon panah keluar |
| cancelled | Cancelled | red-300, teks slate-900 | Teks dicoret |
| expired | Expired | slate-300, teks slate-700 | Garis putus-putus |
| no_show | No-show | rose-700, teks putih | Ikon tanda seru |

Pada kalender, `cancelled`, `expired`, dan `no_show` tidak menempati kamar. Tampilkan hanya jika filter "show inactive" aktif.

### Kamar dan pembayaran

| Hal | Nilai | Warna |
| --- | --- | --- |
| Blokir dan maintenance (`room_blocks`, `rooms.status`) | Blocked, Maintenance | slate-500 dengan pola garis diagonal |
| Status kebersihan | dirty, cleaning, ready | orange-500, sky-500, emerald-500 |
| Status bayar (dihitung) | unpaid, partial, paid | red-500, amber-500, green-600 |

Pembedaan konteks: badge status bayar selalu berisi teks ("Unpaid", "Partial", "Paid") karena warnanya mirip dengan status booking.

## 3. Tipografi dan format

| Hal | Usulan |
| --- | --- |
| Font | Satu keluarga sans-serif (mis. Inter atau font sistem). Verifikasi lisensi jika memakai font hosting sendiri |
| Skala | 12 (keterangan), 14 (tabel dan form), 16 (isi), 20 (judul seksi), 24 dan 30 (judul halaman dan angka KPI) |
| Angka | Gunakan angka tabular pada tabel dan KPI agar kolom rapi |
| Uang | `Rp 1.250.000` (bilangan bulat, tanpa desimal) |
| Tanggal | `08 Oct 2026` di UI. Format ISO hanya di data |
| Persentase | Satu desimal untuk okupansi (mis. 72,5%) |

## 4. Tata letak dan spasi

| Hal | Usulan |
| --- | --- |
| Grid spasi | Kelipatan 4 px |
| Sidebar | Lebar tetap sekitar 240 px, dapat dilipat. Item menu mengikuti peran |
| Konten | Lebar maksimum untuk halaman form. Dashboard dan kalender memakai lebar penuh |
| Kartu | Sudut membulat sedang, bayangan tipis |
| Tabel | Kepadatan sedang, header menempel saat gulir |

## 5. Komponen

| Komponen | Varian dan kondisi | Dipakai di |
| --- | --- | --- |
| Button | Primary, secondary, destructive, ghost. Kondisi: hover, fokus, disabled, loading | Semua halaman |
| Input, select, textarea | Normal, fokus, error, disabled. Pesan error di bawah field | Form booking, master data |
| Date range picker | Menandai tanggal tidak tersedia pada halaman publik | Booking publik, form booking |
| Badge status | Satu badge per nilai status booking, kebersihan, dan bayar | Daftar, detail, kalender |
| Tabel data | Urut, filter, pencarian, paginasi, keadaan kosong | Daftar booking, tamu, tarif |
| Kartu KPI | Judul, angka, pembanding periode (naik atau turun) | Dashboard |
| Drawer detail | Panel kanan berisi info, tombol aksi menurut peran | Kalender, daftar booking |
| Modal konfirmasi | Untuk aksi berisiko (batal, no-show, override) dengan kolom alasan wajib | Pembatalan, perubahan, override |
| Banner peringatan | Peringatan tidak memblokir (kamar belum ready, sisa tagihan) | Check-in, check-out |
| Toast | Hasil aksi singkat | Semua halaman |
| Keadaan kosong, memuat, error | Setiap daftar dan grafik punya ketiganya | Semua halaman |
| Grafik (Recharts) | Garis (tren), batang (perbandingan), donat atau batang (komposisi) | Analitik |

## 6. Kalender timeline

| Aspek | Usulan |
| --- | --- |
| Struktur | Baris = kamar, dikelompokkan per tipe dan dapat dilipat. Kolom = satu tanggal |
| Kolom dan baris kamar | Tetap (sticky) saat gulir horizontal dan vertikal |
| Ukuran awal | Lebar sel sekitar 56 px dan tinggi baris sekitar 44 px (disesuaikan setelah prototipe) |
| Hari ini | Kolom disorot, ada garis penanda |
| Akhir pekan | Latar sedikit lebih gelap. Harian yang dihitung akhir pekan mengikuti pengaturan tarif |
| Batang booking | Menutupi sel malam menginap, sudut membulat. Teks: nama tamu, terpotong jika sempit. Hari check-out tidak diwarnai |
| Indikator tambahan | Titik atau ikon kecil untuk "belum lunas" dan sumber booking |
| Kolom kamar | Nomor kamar, tipe, titik status kebersihan |
| Interaksi | Klik batang membuka drawer. Klik sel kosong membuka form dengan kamar dan tanggal terisi. Tanpa drag-and-drop di MVP |
| Rentang | 7, 14, atau 30 hari, dengan navigasi sebelum, sesudah, dan hari ini |

## 7. Responsivitas

| Konteks | Aturan |
| --- | --- |
| Dashboard staf | Dirancang untuk lebar minimal sekitar 1024 px. Di bawah itu sidebar dilipat dan tabel boleh digulir ke samping |
| Kalender | Selalu boleh digulir horizontal, tidak dipaksa muat |
| Booking publik | Dirancang mulai lebar sekitar 360 px. Target sentuh minimal sekitar 44 px |

## 8. Aksesibilitas

1. Status selalu punya teks atau ikon, tidak hanya warna.
2. Semua elemen interaktif dapat dijangkau keyboard dan punya indikator fokus yang jelas.
3. Kontras teks dan latar dicek dengan alat pemeriksa kontras, terutama pada batang amber dan abu.
4. Modal mengembalikan fokus ke elemen pemicu setelah ditutup.
5. Field form punya label yang terhubung, dan pesan error dibacakan oleh pembaca layar.

## 9. Teks antarmuka (contoh)

| Situasi | Teks |
| --- | --- |
| Bentrok saat submit | "This room is no longer available for the selected dates. Please choose another option." |
| Minimum menginap | "A minimum stay of {n} nights applies to this check-in date." |
| Peringatan kebersihan | "This room is not marked as ready yet. You can still continue." |
| Sisa tagihan | "This booking still has an outstanding balance of {amount}." |
| Cek pesanan gagal | "We couldn't find a booking with those details." |
| Alasan wajib | "Please provide a reason for this change." |

## 10. Yang perlu diputuskan setelah wireframe selesai

| Hal | Keterangan |
| --- | --- |
| Warna primary dan identitas Kamarin | Teal hanya usulan awal. Bisa diganti jika ada logo atau identitas |
| Ukuran sel kalender | Diuji dengan prototipe nyata dan data dummy |
| Font final | Cek lisensi bila tidak memakai font sistem |
| Komponen UI | Apakah memakai pustaka komponen bawaan starter kit atau membuat sendiri. Verifikasi setelah starter kit dipilih |
