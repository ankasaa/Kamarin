# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-12

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: **T-011 (transition guard + transisi status) SELESAI di `task/T-011-status-transitions`** — **wajib review agent terpisah**. Sebelumnya T-010b (perbaikan review T-009/T-010, menutup pertanyaan terbuka no. 1 & no. 2) **sudah di-merge user ke `main`** dan belum di-review karena backlog menandainya Opsional; T-006, T-008, T-009, T-010 review LULUS dan sudah di-merge + di-push. `main` = `origin/main` = **`d46892e`**
- Isi T-011 (tanpa perubahan skema, tanpa dependensi baru): (1) enum **`App\Enums\RoleName`** sebagai nama peran kanonik — seeder T-007 nanti memakainya; (2) **tabel transisi eksplisit `BookingService::TRANSITIONS`** sesuai matriks Business Rules bagian 5 (data, bukan if bertingkat) plus enam metode `confirm`, `cancel`, `checkIn`, `checkOut`, `markNoShow`, `expire`, semuanya dalam **satu transaksi dengan baris `bookings` terkunci**, memvalidasi peran + syarat (DP terverifikasi, alasan wajib, jendela check-in, `no_show_cutoff_time`, `expires_at`), menjalankan efek (is_active malam, `expires_at`, `checked_in_at`, `checked_out_at`, kamar `dirty`, `cancelled_at`, `cancellation_reason`) lalu menulis **satu** baris `booking_status_logs` berisi pelaku; (3) **tes arsitektur** yang menjaga hanya `BookingService` yang menulis `bookings.status`
- Hasil verifikasi T-011: **4 tes merah dulu** (metode belum ada); seluruh suite **220/220 tes, 2010 assert** di PostgreSQL `kamarin_test`; `pint --parallel --test` EXIT=0. Diff `composer.json`/`composer.lock`/`package.json`/`package-lock.json`/`database/migrations` = **kosong**
- Isi T-010b (3 butir, tanpa perubahan skema): (1) `findOrCreateGuest` menormalkan email — `trim` + huruf kecil — **saat menyimpan dan saat mencocokkan**, alamat kosong menjadi `null`, dan **telepon tidak pernah jadi kunci pencocokan otomatis** (BR v1.2 bagian Data dan privasi butir 3); (2) `assertHasDefaultRate` kini **hanya dijalankan untuk `room_types.is_active = true`** (menutup pertanyaan no. 1: tabel `rates` memang tidak punya `is_active`, yang dimaksud reviewer = `room_types.is_active`), dan `AvailabilityService` **menolak kamar bertipe nonaktif** di `availableRooms()` dan `assertValidStay()` (AV-14 baru, BR-01.1 + BR-07.6); (3) pembuatan kode booking pindah ke **`App\Support\BookingCode::generate()`**, dipakai `BookingService` **dan** `BookingFactory`, sehingga data uji juga memenuhi BR-14 (menutup catatan reviewer T-006 bahwa factory masih memakai pola `BK-` + `bothify`)
- Hasil verifikasi T-010b: **4 tes baru merah dulu, lalu hijau**; seluruh suite **163/163 tes, 1182 assert** di PostgreSQL `kamarin_test`; `pint --parallel --test` EXIT=0. Skema tidak diubah (tidak ada file `migrations/` di diff)
- Konvensi keputusan (terkunci di STATUS, tidak di business_rules): rentang `room_blocks` `[start_date, end_date)`; "masa depan" AV-13 = `stay_date >= today`; nama role kanonik = enum **`App\Enums\RoleName`** (T-011), sementara konstanta `PAST_DATE_ROLES`/`MINIMUM_NIGHTS_OVERRIDE_ROLES`/`DISCOUNT_ROLES` lama sengaja tidak disentuh dan disatukan saat T-007. Ditambah T-010b: **email kosong = `null`** (bukan string spasi) dan pencarian tamu lewat telepon = **fitur staf di US-21**, bukan pencocokan otomatis
- T-007 (seeder) ditunda atas keputusan user. **Langkah berikutnya: T-012** (PaymentService + sisa kelompok PY/PR) — T-011 review **putaran 1 TIDAK LULUS**, diperbaiki, **putaran 2 wajib dijalankan** sebelum boleh di-merge (lihat entri log T-011)

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` **v1.2** (perubahan v1.2: kamar bertipe nonaktif tidak dijual + normalisasi email), keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
| Fase 5 (implementasi) | Berjalan: T-006 (enum, model, relasi, factory), T-008 (AvailabilityService), T-009 (PricingService), T-010 (BookingService) **selesai + review LULUS + di-merge & di-push**; T-010b (perbaikan review) **selesai, di-merge user ke `main`, belum di-review (Opsional)**; T-011 (transition guard + transisi status) **selesai, review putaran 1 TIDAK LULUS → diperbaiki, putaran 2 wajib**. `main` = `origin/main` = `d46892e`. T-007 (seeder) ditunda atas keputusan user. **Berikutnya: T-012** PaymentService + sisa kelompok PY/PR |
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

Entri T-001 sampai T-004, entri T-005, T-005b, dan review mandiri T-005 (semua sudah selesai atau terjawab) dipindahkan ke `docs/STATUS_ARCHIVE.md` (batch 2026-10-12). Entri baru ditambahkan di bawah ini.

1. ~~**(T-010, terbuka)** Temuan review T-009 meminta "gabung cek `is_active` di `assertHasDefaultRate`", tapi tabel `rates` di ERD v2.1 tidak punya kolom `is_active`... apakah `assertHasDefaultRate` juga harus menolak saat `room_types.is_active` false, atau cek keberadaan tarif default sudah cukup untuk PR-06?~~ → **TERJAWAB oleh user lewat baris T-010b (2026-10-12) dan dikerjakan di T-010b.** Interpretasi reviewer dikonfirmasi benar: yang dimaksud `room_types.is_active` (bukan `rates.is_active`, dan memang tabel `rates` tidak punya kolom itu). Jawabannya **bukan** "menolak", melainkan kebalikannya: `assertHasDefaultRate` **hanya dijalankan untuk tipe kamar aktif** — tipe nonaktif memang tidak boleh dijual, tapi penolakannya ada di `AvailabilityService` (AV-14), jadi tipe nonaktif tidak wajib punya tarif default. Tes: `PR-06 (inactive): skips the default rate check for an inactive room type`.
2. ~~**(T-010, terbuka)** Temuan M6 review T-010: `findOrCreateGuest()` mencocokkan email mentah tanpa `trim`/case-normalisasi... apakah normalisasi serta pencarian via telepon dikerjakan sekarang di `BookingService`, atau ditunda ke tugas khusus US-21?~~ → **TERJAWAB oleh user lewat baris T-010b (2026-10-12) dan dikerjakan di T-010b.** Normalisasi (trim + huruf kecil, saat menyimpan **dan** saat mencocokkan) dikerjakan sekarang; **pencocokan otomatis lewat telepon tidak dibuat** (nomor bisa dipakai bersama) — pencarian lewat telepon tetap domain US-21 (fitur pencarian staf). Alamat kosong disimpan sebagai `null`, bukan string spasi. Tes: `Data dan privasi 3: normalises email on store and match, never by phone`.
3. **(T-010, terbuka)** Temuan I5 review T-010: `lockForUpdate` (lapis 2 BR-01) saat ini **hanya** ada di `BookingService::createBooking`. Sisi pembuatan `room_blocks` (BR-01.6: index parsial hanya menjaga booking terhadap booking, jadi booking vs blokir dijaga di service) belum mengunci baris kamar, sehingga race "booking dibuat bersamaan dengan blokir kamar yang sama" secara teori masih bisa lolos. Di luar ruang lingkup T-010 (tidak ada kode blokir di repo sekarang). Pertanyaan untuk user: apakah tugas pembuatan blokir kamar nanti (baris T-016) juga wajib mengunci baris kamar dengan `lockForUpdate` mengikuti pola `BookingService`? **Menjelang T-016, belum dijawab.**

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

### T-011 Transisi status dan transition guard (2026-10-12)

- Cabang: `task/T-011-status-transitions`, dari `main` `d46892e`. **`main` tidak disentuh.** Catatan aturan AGENTS "cabang baru dari ujung cabang tugas sebelumnya": sebelum tugas ini user sendiri meng-merge `task/T-010b-followups` ke `main` (fast-forward) **dan** meng-push, sehingga `main` = `origin/main` = `d46892e` = tip `task/T-010b-followups`. Cabang dari `main` karena itu **identik** dengan cabang dari tip tugas sebelumnya — tidak ada penyimpangan, tidak ada hash yang perlu dicatat.
- Status: selesai. Review **wajib** oleh agent Reviewer terpisah (AGENTS.md + baris T-011 di `docs/tasks/BACKLOG_FASE4_5.md`).
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (aturan 2 nilai status, 3 logika bisnis hanya di `app/Services`, 5 uang tanpa float, 6 kebijakan tidak di-hardcode, 7 tanggal, 10 ID kasus uji + PostgreSQL, 11 tanpa dependensi baru), baris **T-011** `docs/tasks/BACKLOG_FASE4_5.md`, `docs/business_rules_kamarin.md` **bagian 5 "Alur status dan hak akses" (matriks, baris 134-171)**, **BR-03, BR-04, BR-05, BR-07, BR-08, BR-13**, keputusan **K5, K6, K7**, `docs/rencana_pengujian.md` **ST-01..ST-09** (bagian 4.4) dan **EX-01..EX-06** (bagian 4.7), ERD `BOOKINGS`, `BOOKING_STATUS_LOGS`, `PAYMENTS`, `PROPERTIES`, `HOUSEKEEPING_STATUSES`.
- **Keputusan interpretasi (dijelaskan, bukan ditebak):**
  1. **Tabel transisi sebagai data.** `BookingService::TRANSITIONS` (baris 73) = `[from] => [to] => ['roles', 'system'?, 'requires', 'warns', 'effects']`; lookup `self::TRANSITIONS[$from->value][$to->value] ?? null`. Baris tidak ada = ditolak, sehingga **"status akhir tidak bisa dibuka kembali"** (`checked_out`, `cancelled`, `expired`, `no_show`) jadi properti tabel, bukan cabang if.
  2. **`pending -> expired` adalah baris `system`** (BR-13.2, EX-06) dan `expire()` (baris 397) memang tidak punya parameter actor — struktur API menutup kepura-puraan jadi sistem. Karena itu `pending` **tidak pernah** jadi target transisi (itu hasil `createBooking`, tidak ada metode yang mendarat ke sana), jadi dataset ST-02 = 7 status asal × 6 target = 42 pasang, **35 tidak valid** × 4 peran + **6 baris valid** × 2 aktor yang dikecualikan (housekeeping, peran di luar keempatnya) = **152 penolakan**, dikunci lewat `expect($refused)->toBe((7 * 6 - 6 - 1) * 4 + 6 * 2)`.
  3. **Alasan wajib untuk SEMUA peran** saat membatalkan (BR-05.1), bukan hanya resepsionis seperti bunyi kolom "Siapa" matriks. BR-05.1 menyebutnya umum, jadi yang dipakai BR-05.1.
  4. **Jendela check-in** (baris 597) = `check_in_date <= today` (BR-02.2: check-in awal diizinkan, sebelum tanggalnya tidak) **dan** `now < no_show_cutoff` ("belum no_show"); lewat itu check-in ditolak dan arahnya harus `no_show`.
  5. **Peringatan tidak boleh memblokir** (BR-04.2, BR-07.5, ST-09), jadi keluaran enam metode bukan `Booking` melainkan **`App\Support\BookingTransitionResult`** = booking + `array $warnings`. Tanpa ini "peringatan, tidak memblokir" mustahil diverifikasi.
  6. **`RoleName` dipakai tabel transisi.** Konstanta lama `PAST_DATE_ROLES`, `MINIMUM_NIGHTS_OVERRIDE_ROLES`, `DISCOUNT_ROLES` di `AvailabilityService`/`PricingService` **sengaja tidak disentuh** (AGENTS: jangan refaktor di luar ruang lingkup); penyatuannya dijadwalkan saat T-007 menulis seeder.
  7. **Kolom timestamp `expires_at`/`checked_in_at`/`checked_out_at`/`cancelled_at` tidak ada di `casts()` `Booking`**, jadi terbaca sebagai string dari PostgreSQL. Semuanya dinormalkan lewat helper privat `asMoment()`. Cast model **tidak ditambah** agar serialisasi JSON tidak berubah (di luar lingkup).
  8. **"DP terverifikasi"** = `verifiedDeposit()` (baris 580): satu query sederhana `payments.status = verified` dan `type in (deposit, settlement)` (BR-03.3). Peringatan sisa tagihan memakai `netPaid()` (BR-12: dikurangi refund). **Keduanya akan dipindah ke `PaymentService` di T-012** dan di situ jadi satu-satunya definisi "DP terverifikasi"/"terbayar" — sesuai instruksi T-011.
  9. **Tes arsitektur.** `pest-plugin-arch` sudah ikut terpasang (`pestphp/pest` mensyaratkan `pestphp/pest-plugin-arch ^4.0.2`, jadi **bukan dependensi baru**) dan API-nya **di-probe dulu** sebelum dipakai: `arch()->expect(...)->toUse()/toBeUsedIn()` membandingkan **dependensi antar-kelas**, jadi bisa menjawab "siapa menyebut `BookingStatus`" tapi **tidak bisa** menyatakan "kelas X menulis kolom Y". Sebagai gantinya `tests/Feature/Architecture/BookingStatusOwnershipTest.php` memindai berkas `app/` + `routes/` untuk tiga idiom penulisan status (`'status' =>` pada `update/create`, `->status =`, `DB::table('bookings')->update`) dengan allowlist `app/Services/BookingService.php` **dan canary tiga bagian** (pembaca langsung berkas `BookingService.php`, tiga idiom sintetis wajib tertangkap, kolom status `payments`/`rooms`/`housekeeping_statuses` tidak boleh dianggap pelanggaran), supaya pindaian tidak bisa lulus dalam keadaan kosong. `database/` dikecualikan **atas dasar sadar**: factory dan seeder mengisi status untuk membangun data, bukan mentransisikan booking hidup.
  10. **Batasan pindaian yang diketahui** (dicatat supaya bukan temuan tersembunyi): tidak melihat SQL mentah `DB::statement("UPDATE bookings SET status=...")`, tidak melihat pernyataan dua langkah (`$q = Booking::where(...);` lalu `$q->update(['status' => ...]);`), tidak melihat `$b->status = ...` bila nama variabelnya tidak memuat kata "booking", dan tidak memindai `bootstrap/`. Semuanya di luar `app/Services` yang jadi satu-satunya penulis.

- **Ditunda secara sadar (bukan terlewat):**
  - **EX-05** (scheduler dijalankan dua kali, idempoten) → scheduler kedaluwarsa datang di **T-014**, belum ada di repo.
  - **Saran DP saat pembatalan (BR-05 butir 2)** dan **Keterangan 2** (resepsionis tidak boleh menyimpang dari saran kebijakan DP) → baris `pending|confirmed -> cancelled` memakai `'warns' => []`; penegakan deviasi = **T-012** bersama kasus uji **PY-06** (kelompok PY). `free_cancel_days` memang belum dipakai kode produksi mana pun.
  - **Email "booking confirmed"** (matriks baris 143, US-15 butir 4, **PB-05**) → lingkup booking engine, di luar T-011. `confirm()` hanya mengosongkan `expires_at`.

- Yang dikerjakan:
  - Baru: `app/Enums/RoleName.php`, `app/Support/BookingTransitionResult.php`, `tests/Feature/Services/BookingStatusTransitionTest.php`, `tests/Feature/Architecture/BookingStatusOwnershipTest.php`
  - Diubah: `app/Services/BookingService.php` (tabel `TRANSITIONS` baris 73, enam metode publik baris 330-401, mesin transisi `transition()` baris 416, guard baris 472-660, efek baris 787-830, catatan log baris 835)
- Langkah 1 — tes ditulis dulu dan **merah**: `{"tool":"pest","result":"failed","tests":50,"passed":0,"assertions":6}` dengan `Call to undefined method App\Services\BookingService::cancel()`. Satu koreksi dataset di langkah merah: `to = pending` dibuang karena tidak ada metode yang mendarat ke sana (lihat keputusan 2).
- Hasil akhir: **220/220 tes, 2010 assert, 0 gagal, 0 risky** di PostgreSQL `kamarin_test`; `pint --parallel --test` → `PINT_EXIT=0`.
- Pemetaan kasus uji → tes: **ST-01** (baris 179, dataset 19 kasus: 6 baris staf × owner/manager/resepsionis + baris sistem), **ST-02** (baris 220, dataset penuh 152 penolakan dengan jarum pesan berbeda per kategori + status tak berubah + 0 log), **ST-03** (289), **ST-04** (313 + 387 `deposit_percent` 30%), **ST-05** (423 + 452 `no_show_cutoff_time` 20:00), **ST-06** (476), **ST-07** (515, dataset sama: satu log per transisi dengan pelaku), **ST-08** (552, termasuk peringatan sisa tagihan), **ST-09** (580), **EX-01** (636), **EX-02** (654), **EX-03** (674), **EX-04** (691), **EX-06** (727), plus dua tes ber-ID BR: **BR-02.2/BR-08.1** (608, kedua cabang penolakan check-in) dan **BR-13.2** (709, expire sebelum deadline). **EX-05** ditunda ke T-014.
- File kunci: `app/Services/BookingService.php`, `app/Enums/RoleName.php`, `app/Support/BookingTransitionResult.php`, `tests/Feature/Services/BookingStatusTransitionTest.php`, `tests/Feature/Architecture/BookingStatusOwnershipTest.php`.
- Penyimpangan dari dokumen sumber: **tidak ada**. Tidak ada file `database/migrations/` di diff, `git diff d46892e..HEAD -- composer.json composer.lock package.json package-lock.json database/migrations` **kosong**. Dua catatan kejujuran ruang lingkup: (a) instruksi meminta ST-01..ST-09 **dan EX-03**, sementara EX-01, EX-02, EX-04, EX-06 ikut ditulis karena keduanya menutup efek `confirm`/`expire` yang memang wajib diwajibkan T-011 (tanpa EX-01, EX-03 bisa lulus karena `expire()` rusak); (b) EX-05 tidak ditulis karena scheduler memang belum ada.
- Penjelasan untuk sidang (3-5 kalimat, bagaimana dan kenapa): Matriks transisi status × peran di Business Rules bagian 5 ditulis ulang menjadi **satu tabel data di dalam `BookingService`**, bukan deretan if — sehingga aturan "hanya `BookingService` yang mengubah `bookings.status`" bisa dibuktikan: tidak ada cabang yang bisa dilewati, dan status akhir otomatis tidak bisa dibuka kembali karena tabelnya memang tidak punya baris keluar. Setiap transisi berjalan di dalam **satu transaksi dengan baris `bookings` dikunci**, sehingga dua staf yang menekan tombol bersamaan tidak saling menimpa: yang kedua membaca status yang sudah berubah lalu ditolak matriks. Tiap transisi **memvalidasi peran, memvalidasi syarat (DP terverifikasi, alasan, batas waktu dari tabel `properties`), menjalankan efeknya, dan menulis tepat satu baris `booking_status_logs` berisi pelaku** — semuanya atomik, jadi tidak mungkin ada booking berstatus berubah tanpa log, atau log tanpa perubahan. Peringatan (kamar belum ready, sisa tagihan) sengaja **dikembalikan sebagai data, bukan exception**, karena aturannya memang "peringatan, tidak memblokir".
- **Reviewer: agent Reviewer terpisah (sesi lain, hanya membaca, tidak mengedit kode).**
  - **Putaran 1 — TIDAK LULUS.** Keluaran asli yang direkam reviewer: `git status --porcelain` = **kosong**; `php vendor\bin\pest` → `{"tool":"pest","result":"passed","tests":216,"passed":216,"assertions":1993,"duration_ms":35507}`; `vendor\bin\pint --parallel --test` → `{"tool":"pint","result":"passed"}` dan `EXITCODE=0`; `git diff --stat d46892e..HEAD -- composer.json composer.lock package.json package-lock.json database/migrations` = **kosong**. Substansi kode dinyatakan sesuai matriks, transaksi + kunci baris benar, `bookings.status` hanya ditulis `BookingService`, skema tak berubah, tak ada dependensi baru. Temuan: **1 CRITICAL** — `docs/STATUS.md` belum ditulis (inilah entri yang sedang kamu baca); **5 MINOR** — guard expiry sebelum deadline tak diuji, kedua cabang guard check-in tak diuji, `no_show_cutoff_time`/`deposit_percent` hanya diuji dengan nilai default pabrik (aturan 6 tidak terkunci), saran DP + Keterangan 2 tidak dicatat, email "booking confirmed" tidak dicatat; **4 NIT** — guard baris sistem tak terjangkau lewat API publik, ST-02 bukan harfiah semua pasangan, peringatan sisa tagihan check-out tak diuji, duplikasi query `verifiedDeposit`/`netPaid`.
  - **Perbaikan:** `docs/STATUS.md` ditulis (CRITICAL); commit `afae99e` menutup 3 MINOR + 1 NIT lewat **4 tes baru** — `BR-13.2` (expire sebelum deadline), `BR-02.2/BR-08.1` (kedua cabang guard check-in), `ST-05` non-default `no_show_cutoff_time` 20:00 dan `ST-04` non-default `deposit_percent` 30%; commit `45010a5` menjelaskan cabang guard sistem (NIT); saran DP, email, EX-05 dan batasan pindai dicatat di butir "Ditunda secara sadar" + keputusan 9-10 (MINOR); duplikasi query tetap mengikuti instruksi T-011 dan dicatat di keputusan 8 untuk digabung di T-012 (NIT). Setelah perbaikan: **220/220 tes, 2010 assert**, `PINT_EXIT=0`.
  - **Putaran 2:** wajib dijalankan setelah commit entri ini, dan hasilnya menempel di sini sebelum cabang ini boleh di-merge.

### T-010b Perbaikan hasil review T-009 dan T-010 (2026-10-12)

- Cabang: `task/T-010b-followups`, dari `main` `b2f5eca` (user memerintahkan cabang dari `main`; `main` sudah sejajar sehingga aturan "cabang dari tip tugas sebelumnya" tidak berlaku). **`main` tidak disentuh.** Commit pertama cabang = dokumen milik user yang belum ter-commit (`31863f0 docs(rules): add v1.2 inactive room type, email normalization, T-010b and T-016`) — atas persetujuan user, agar `git status` bersih di awal sesuai AGENTS.md, terpisah dari commit kode.
- Status: selesai. Backlog menandai review **Opsional**; **belum di-review** dan belum di-merge.
- Rujukan yang dibaca sebelum mulai: `AGENTS.md` (versi baru, aturan main), baris **T-010b** di `docs/tasks/BACKLOG_FASE4_5.md`, **BR-01 butir 1** (syarat (a) kini memuat `room_types.is_active = true`), **BR-07 butir 6** (kamar bertipe nonaktif tidak dapat dijual), **BR Data dan privasi butir 3** (normalisasi email; pencocokan otomatis hanya lewat email; pencarian lewat telepon = fitur staf), **BR-14** (kode booking), kasus uji **AV-14** (baru) dan **BK-07**, **US-21** AC1-AC3, ERD `ROOM_TYPES.is_active` (boolean, tanpa nullable) dan `GUESTS.email` (nullable).
- **Keputusan interpretasi (dijelaskan, bukan ditebak):**
  1. **"hanya untuk tipe kamar aktif" = aturan dilewati, bukan aturan menolak.** PR-06 berbunyi "Tipe kamar **aktif** tanpa tarif default → tidak dapat dijual atau validasi gagal", jadi hanya tipe aktif yang diwajibkan punya tarif default. Tipe nonaktif tidak perlu punya tarif default karena ia memang tidak bisa dijual — dan penolakannya ada di `AvailabilityService` (AV-14), bukan di `PricingService`. Ini menutup pertanyaan terbuka no. 1 dengan jawaban **berbeda dari dua alternatif yang dulu ditawarkan** (dulu: "menolak saat false" vs "cek keberadaan cukup" → keduanya salah baca; kenyataannya: **lewat saja**).
  2. **`rates.is_active` memang tidak ada.** Konfirmasi ulang terhadap ERD: `rates` hanya punya `is_default`, `priority`, rentang tanggal, `days_of_week`, `minimum_nights`. Yang dimaksud reviewer T-009 adalah `room_types.is_active`, sesuai dugaan yang tertulis di pertanyaan terbuka no. 1.
  3. **Email kosong → `null`, bukan string spasi.** `guests.email` nullable di ERD dengan catatan "kosong untuk sebagian booking OTA", jadi alamat hasil `trim` yang kosong dinormalkan menjadi `null`. Konsekuensinya `null` **tidak** pernah dipakai sebagai kunci pencocokan — dua tamu tanpa email tidak dianggap orang yang sama (sesuai US-21 AC3 "data tamu tidak digabung otomatis").
  4. **Telepon tidak dijadikan kunci pencocokan**, sesuai instruksi eksplisit user dan BR Data dan privasi butir 3 ("nomor bisa dipakai bersama"). Pencarian lewat telepon tetap domain US-21 (fitur pencarian staf) — **tidak** dikerjakan di sini.
  5. **`BookingCode` statis di `App\Support`, bukan di `app/Services`.** Generators/serializer bukan logika bisnis; kelas ini hanya meniru aturan format + cek unik, sehingga aturan 3 AGENTS.md tetap terpenuhi (tidak ada keputusan bisnis yang dipindah ke luar service).
- Yang dikerjakan — empat file kode + satu file baru:
  - **`app/Support/BookingCode.php` (baru)** — `BookingCode::generate()`: 8 karakter dari alfabet `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, diambil `random_int`, loop cek unik memakai `Booking::withTrashed()` (index unique tetap menutupi baris soft-deleted). Logika **dipindah dari** `BookingService::generateBookingCode()` (konstanta `CODE_ALPHABET`/`CODE_LENGTH` dan metodenya dihapus dari service).
  - `app/Services/BookingService.php` — `booking_code` kini `BookingCode::generate()`; `findOrCreateGuest()` memanggil helper baru `normalizeEmail()` (trim → huruf kecil → kosong jadi `null`) **sebelum** pencarian dan sebelum penyimpanan, sehingga satu bentuk dipakai di kedua sisi; metode `generateBookingCode()` dihapus.
  - `app/Services/AvailabilityService.php` — `availableRooms()` menambah `whereHas('roomType', is_active = true)`; `assertValidStay()` menambah penolakan `room_types.is_active = false` dengan pesan yang menyebut kamar dan BR-07.6; docblock kelas diperbarui.
  - `app/Services/PricingService.php` — `assertHasDefaultRate()` kembali lebih dulu (tanpa exception) bila `! $roomType->is_active`.
  - `database/factories/BookingFactory.php` — `'BK-'.bothify(...)` diganti `BookingCode::generate()`, sehingga data uji memenuhi BR-14 (menutup catatan reviewer T-006).
- **Langkah 1 — tes ditulis lebih dulu, dan merah bersih.** Empat tes baru ditulis sebelum implementasi; keempatnya gagal persis karena alasan yang benar (bukan karena typo atau import hilang):

  ```
  PS> php artisan test tests/Feature/Services/AvailabilityServiceTest.php tests/Feature/Services/BookingServiceTest.php tests/Feature/Services/PricingServiceTest.php tests/Feature/Models/FactoryValidityTest.php
  {"tool":"pest","result":"failed","tests":78,"passed":74,"assertions":210,"duration_ms":15037,"failed":3,
   "failures":[
     "AV-14 ... Failed asserting that two arrays are identical ... 0 => 27, + 1 => 28   (kamar bertipe nonaktif ikut terbawa)",
     "Data dan privasi 3 ... Failed asserting that two strings are identical ... -'a@x.test' + ' A@X.test '  (email tersimpan mentah)",
     "generates a BR-14 booking code ... 'BK-1960XJ' matches PCRE \"/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/\""],
   "errors":1,
   "error_details":["PR-06 (inactive) ... PricingService.php:205 ... has no default rate ... (lempar untuk tipe nonaktif)"]}
  RED_EXIT=1
  ```

  78 = 74 lulus + 3 gagal + 1 error. **Tidak ada assertion yang dilonggarkan** — keempatnya memang diuji merah dulu.
- **Kejujuran proses (satu kesalahan edit, tertangkap sebelum tes):** saat menghapus `generateBookingCode()` dari `BookingService`, satu edit saya memotong deklarasi `private function countNights(` karena `oldString` terlalu pendek, lalu edit perbaikan berikutnya malah menghapus tanda `{`. Keduanya terdeteksi lewat `read` atas baris sekitar **sebelum** tes dijalankan dan diperbaiki menjadi bentuk aslinya kembali; tidak ada perilaku yang berubah dan tidak ada tes yang dipakai untuk menyembunyikannya.
- Hasil akhir (EXIT=0):

  ```
  PS> php artisan test tests\Feature\Services\AvailabilityServiceTest.php tests\Feature\Services\BookingServiceTest.php tests\Feature\Services\PricingServiceTest.php tests\Feature\Models\FactoryValidityTest.php
  {"tool":"pest","result":"passed","tests":78,"passed":78,"assertions":227,"duration_ms":11404}
  FOCUS_EXIT=0
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":163,"passed":163,"assertions":1182,"duration_ms":25125}
  SUITE_EXIT=0
  PS> vendor\bin\pint --parallel --test
  {"tool":"pint","result":"passed"}
  exit code = 0
  ```

  163 = 159 tes (T-006, T-008, T-009, T-010, dan sebelumnya) + **4 tes baru**. **0 gagal.** PostgreSQL `kamarin_test`.
  Catatan jujur soal lint: `composer run lint:check 2>&1` lebih dulu dijalankan dengan redirect `2>&1`, dan PowerShell 5.1 menandainya `NativeCommandError`/`Exited with code 1` hanya karena Pint menulis ke stderr — padahal keluaran Pint-nya sendiri `{"result":"passed"}`. Karena AGENTS.md melarang melapor selesai saat tes gagal, perintah diulang **tanpa** redirect dan `echo $LASTEXITCODE` → **0**. Kode di atas adalah keluaran verifikasi ulang itu.
- Pemetaan butir T-010b → tes (semua lulus): (1) `Data dan privasi 3: normalises email on store and match, never by phone` — `' A@X.test '` disimpan sebagai `'a@x.test'`, pemanggilan kedua dengan `'a@x.test'` **mengembalikan id tamu yang sama** dan `Guest::where('email','a@x.test')->count() === 1`, nama lama tetap tidak ditimpa; tamu ketiga dengan `phone` sama tapi email beda **tetap tamu berbeda**; dua alamat kosong (`'   '`, `' '`) menjadi `null` dan tidak saling cocok. (2) `AV-14: excludes rooms whose room type is inactive` — `availableRooms()` hanya mengembalikan kamar bertipe aktif, dan `assertValidStay()` melempar `AvailabilityException` berpesan `inactive room type`; tes PR-06 lama tetap lulus (tipe **aktif** tanpa tarif default tetap ditolak) + `PR-06 (inactive): skips the default rate check for an inactive room type`. (3) `generates a BR-14 booking code from the factory` — tiga kode factory cocok regex `^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$` (menutup 0/O/1/I dan pemisah `BK-`), ketiganya unik, dan ketiganya benar-benar ada di tabel `bookings`.
- Tes: 163 lulus, **0 gagal, 0 risky** (1182 assert, Pest 4.x, PostgreSQL 18). Tes baru: 4.
- Formatter: `pint --parallel --test` lulus (EXIT=0). Perubahan garis akhir di `docs/STATUS*.md` dinormalisasi kembali ke **LF** setelah penyuntingan otomatis sempat menulis CRLF (diverifikasi `git diff --numstat` = murni blok yang dipindah).
- File kunci: `app/Support/BookingCode.php` (baru), `app/Services/BookingService.php`, `app/Services/AvailabilityService.php`, `app/Services/PricingService.php`, `database/factories/BookingFactory.php`, 4 file tes.
- Penyimpangan dari dokumen sumber: **tidak ada terhadap BR-01.1/BR-07.6/BR-14/BR Data dan privasi/AV-14/BK-07/US-21**, dan **skema tidak diubah** (tidak ada file di `database/migrations/` yang ikut berubah). Dua hal yang perlu user tahu: (1) baris **T-016** di tabel *Kebijakan review berbasis risiko* `BACKLOG_FASE4_5.md` salah tempel (5 kolom, tabel itu 3 kolom) — atas persetujuan user saya perbaiki menjadi `| T-016 | Wajib | Ya |`; baris **T-005b** di tabel yang sama juga rusak (6 pipe) **sejak commit lama**, bukan dari edit user, dan sengaja **tidak** saya sentuh; (2) `docs/` tidak diformatter otomatis (sesuai AGENTS.md).
- Penjelasan untuk sidang (bagaimana dan kenapa): Ketiga perbaikan ini menutup satu lubang yang sama — **aturan yang benar di satu tempat tapi salah di tempat lain**. Kode booking dulu dibuat dua kali dengan dua aturan berbeda (`BookingService` memenuhi BR-14, `BookingFactory` memakai pola `BK-` + angka acak), jadi data uji tidak pernah mewakili data nyata; dengan memindahkan pembuatannya ke satu kelas `App\Support\BookingCode`, format BR-14 kini dijamin oleh satu sumber dan tes membuktikan factory menghasilkan kode yang sama persis dengan yang dihasilkan booking sungguhan. Email tamu dulu dicocokkan apa adanya, sehingga `' A@X.test '` dan `'a@x.test'` akan menjadi dua tamu berbeda — satu orang dua identitas; normalisasi diterapkan di **kedua** sisi (menyimpan dan mencari) sehingga bentuk yang dicocokkan selalu sama dengan bentuk yang disimpan, sementara telepon sengaja tidak dijadikan kunci karena nomor bisa dipakai bersama dan menggabungkan tamu otomatis justru melanggar US-21. Kamar bertipe nonaktif dulu lolos kedua penjaga (`availableRooms` hanya melihat `rooms.status`, dan `assertHasDefaultRate` menuntut tarif default pada tipe yang sudah tidak dijual), padahal BR-01.1 dan BR-07.6 menyatakan tipe nonaktif tidak boleh dijual; kini penolakannya dipisah sesuai bagiannya — `AvailabilityService` menolak kamarnya (AV-14), `PricingService` hanya menguji tipe yang benar-benar dijual (PR-06). Semua tes ditulis merah dulu dan keempatnya gagal persis karena alasan yang benar, jadi perbaikan ini terbukti menutup lubangnya, bukan sekadar membuat tes hijau.
- Reviewer: **belum ada** (backlog menandai review T-010b Opsional). Belum di-merge; `main` = `origin/main` = `b2f5eca`.

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
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
