# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-11

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: T-003, `task/T-003-tests-on-postgres` (dari `main` setelah T-001 di-merge)
- Status: selesai
- Hasil tes: **40 lulus, 0 gagal** (138 assert) di PostgreSQL `kamarin_test`. Pengaman terbukti: saat `DB_DATABASE=kamarin`, 40/40 tes gagal dengan pesan guard sebelum ada migrasi/penghapusan; database development utuh (10 tabel, users=0, sessions=2). Helper `freezeNow()` terverifikasi dengan tes probe sementara 3/3 lulus (file probe dihapus setelah verifikasi)
- File kunci untuk di-review (path): `phpunit.xml`, `tests/TestCase.php` (pengaman `_test`), `tests/Pest.php` (helper + hook global), `README.md`
- Penyimpangan dari dokumen sumber: tidak ada. Catatan: hook `beforeEach()`/`afterEach()` telanjang di `Pest.php` terbukti tidak dieksekusi di Pest 4.7.8; dipakai API `pest()->beforeEach(...)` sesuai dokumentasi resmi (pestphp.com/docs/global-hooks)
- Pertanyaan yang butuh keputusan: (1) `.github/workflows/tests.yml` bawaan starter kit menjalankan CI tanpa PostgreSQL — kalau GitHub Actions diaktifkan, CI akan gagal karena tes kini wajib PostgreSQL; perlu ditambah service Postgres di workflow, dinonaktifkan, atau dibiarkan. (2) Database development masih berisi 10 tabel framework sisa verifikasi T-001 (0 baris bisnis); kalau ingin benar-benar kosong, jalankan `php artisan migrate:rollback` atau `docker compose down -v`.

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Berjalan: T-001 dan T-003 selesai (tes di PostgreSQL), T-002 menyusul |
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

### 2026-10-11, T-003 — Dua catatan lanjutan (tidak memblokir)

1. **CI GitHub.** Dokumen yang dirujuk: `.github/workflows/tests.yml` (bawaan starter kit). Workflow menjalankan `composer setup` (yang menjalankan `migrate`) lalu `composer ci:check` tanpa layanan PostgreSQL. Dengan tes kini wajib PostgreSQL, CI akan gagal jika GitHub Actions diaktifkan. Opsi: (a) tambahkan service `postgres` di workflow beserta env `kamarin_test`, (b) nonaktifkan/hapus workflow sampai ada kebutuhan deploy, (c) biarkan sampai Fase 11. Rekomendasi agent: (b) atau (c) — CI bukan bagian Definition of Done tugas saat ini.
2. **Isi database development.** Saat bukti pengaman T-003 dijalankan, `kamarin` holds 10 tabel framework sisa verifikasi T-001 (users=0, sessions=2) — bukan kosong murni, seperti yang diasumsikan instruksi. Tidak berbahaya (guard membuktikan tabel tidak tersentuh). Kalau ingin benar-benar kosong: `php artisan migrate:rollback` atau `docker compose down -v` (menghapus kedua database dan volume). Rekomendasi agent: biarkan.

### 2026-10-09, T-001 — Hasil pemeriksaan prasyarat dan keputusan yang dibutuhkan

Dokumen yang dirujuk: `docs/arsitektur_sistem.md` bagian 5 dan 6, Laravel 13 Installation
(https://laravel.com/docs/13.x/installation) dan Sail (https://laravel.com/docs/13.x/sail).

Hasil pemeriksaan (tanpa instalasi apa pun):

| Prasyarat | Hasil |
| --- | --- |
| Sistem operasi | Windows 11 Home Single Language 10.0.26200 x64 (PowerShell 5.1) |
| Docker | Docker Desktop terpasang (user-level). Awalnya **tidak berjalan**; agent menjalankan manual → client 29.8.1, daemon naik, Compose v5.5.1 |
| WSL2 | Tersedia: Ubuntu 26.04.1, WSL2. Integrasi Docker berfungsi (`docker` di WSL terhubung ke daemon 29.8.1) |
| PHP | 8.3.35 (Laragon, Windows). Memenuhi `laravel/framework` 13.x (`php ^8.3`). Tidak ada PHP di WSL |
| Composer | 2.10.3 (Windows). Tidak ada di WSL |
| Node / npm | v24.21.0 / 11.19.0 (Windows). Tidak ada di WSL. Sail default Node 24 |
| Laravel installer | **Belum terpasang** (`laravel` tidak dikenal) |
| Lokasi repo | `C:\Users\andikaa\Desktop\Kamarin` = drive Windows (NTFS), bukan filesystem WSL. Di WSL terbaca sebagai `/mnt/c/Users/andikaa/Desktop/Kamarin` |

Perintah resmi dari dokumentasi Laravel 13: prasyarat Installation = PHP, Composer, dan Laravel
installer (plus Node/NPM), aplikasi dibuat dengan `laravel new example-app`; Sail dipasang dengan
`composer require laravel/sail --dev`, dikonfigurasi `php artisan sail:install`, dijalankan
`./vendor/bin/sail up`, diakses lewat `http://localhost`. Sail resmi didukung di Windows **lewat
WSL2**. Sail default: PHP 8.5, Node 24.

**Pertanyaan 1 — Cara membuat proyek (Laravel installer belum terpasang, langkah 1 melarang instalasi):**

- Opsi A (rekomendasi): izinkan instalasi `composer global require laravel/installer` lalu `laravel new` di folder sementara, pindahkan isinya ke root repo. Sesuai dokumentasi resmi Installation. Catatan: direktori bin Composer global (`C:\Users\andikaa\AppData\Roaming\Composer\vendor\bin`) belum ada di PATH, jadi akan dipanggil lewat path penuh.
- Opsi B: tanpa installer, pakai `composer create-project laravel/laravel <folder-sementara>`. Ini perintah Composer umum untuk skeleton resmi `laravel/laravel`, tetapi **tidak lagi tercantum** di halaman Installation Laravel 13, jadi kurang "terverifikasi dari dokumentasi resmi".

**Pertanyaan 2 — Repo di drive Windows sementara Sail hanya bisa dijalankan dari WSL:**

Skrip `sail` berbasis shell sh, jadi perintah Sail dijalankan dari WSL (misalnya
`wsl -e sh -c "cd /mnt/c/Users/andikaa/Desktop/Kamarin && ./vendor/bin/sail up -d"`), bukan dari
PowerShell. Konsekuensi bekerja di `/mnt/c`:

- Opsi A (rekomendasi): tetap di lokasi repo sekarang, jalankan Sail dari WSL. Tanpa perpindahan file, `docs/` dan `AGENTS.md` aman. Cocok untuk T-001 (setup backend). Performa I/O lebih lambat dari filesystem WSL, dan **file-watching Vite (HMR) berisiko tidak andal** saat T-002 karena perubahan file Windows tidak selalu terdeteksi di dalam kontainer (solusinya bisa polling, atau jalankan dev server dari Windows).
- Opsi B: pindahkan seluruh repo ke filesystem WSL (misalnya `~/kamarin`). I/O dan HMR paling cepat, tetapi lokasi repo berubah dari yang sudah ditetapkan (`C:\Users\andikaa\Desktop\Kamarin`) dan akses editor Windows/git harus menyesuaikan.
- Opsi C: tidak memakai Sail, memakai PHP lokal + PostgreSQL lokal. Bertentangan dengan `arsitektur_sistem.md` bagian 5 (Sail untuk development) dan butuh instalasi manual.

**Pertanyaan 3 — Docker Desktop tidak auto-start:** tiap sesi mesin harus menjalankan Docker Desktop dulu. Boleh disetel autostart, atau dibiarkan manual (agent bisa menjalankan sendiri saat tugas mulai).

**Pertanyaan 4 — Timezone `.env.example`:** tugas menulis `[zona waktu]` sebagai placeholder. Usulan: `Asia/Jakarta`. Konfirmasi.

Rekomendasi agent: **Pertanyaan 1 = A, Pertanyaan 2 = A, Pertanyaan 3 = biarkan manual (agent menjalankan), Pertanyaan 4 = Asia/Jakarta.** Menunggu jawaban sebelum melanjutkan ke Langkah 2.

**Status: sudah dijawab user (2026-10-09).** Keputusan: Pertanyaan 1 = Opsi A (pasang `laravel/installer`, buat di folder sementara, pindah ke root). Pertanyaan 2 = **Opsi D baru** (bukan A): PHP, Composer, Node lokal di Windows; hanya PostgreSQL di Docker (compose, database dev dan tes terpisah); repo tetap di lokasi sekarang; syarat `pdo_pgsql` aktif. Pertanyaan 3 = manual, agent boleh menjalankan. Pertanyaan 4 = **Asia/Makassar (fix)**, menggantikan usulan Asia/Jakarta. AGENTS.md diperbarui user (bagian Lingkungan pengembangan, aturan 13) dan `docs/arsitektur_sistem.md` bagian 5-6 direvisi sesuai keputusan ini. Catatan: baris zona waktu di AGENTS.md masih "Asia/Jakarta (sementara)" — perlu diperbarui user ke Asia/Makassar.

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

### T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu (2026-10-11)

- Cabang: `task/T-003-tests-on-postgres` (dari `main` setelah T-001 di-merge)
- Status: selesai
- Yang dikerjakan:
  - `phpunit.xml`: `DB_CONNECTION=sqlite`/`:memory:` diganti `DB_CONNECTION=pgsql`, `DB_DATABASE=kamarin_test`. Host, port, username, dan password diambil dari `.env` (satu server yang sama), jadi tidak ada kredensial ganda.
  - Pengaman di `tests/TestCase.php`: `setUp()` memanggil `guardTestDatabase()` **sebelum** `parent::setUp()`. Guard membaca `Env::get('DB_DATABASE')` dan melempar `RuntimeException` jika nama tidak berakhiran `_test`. Karena `RefreshDatabase` (yang menjalankan `migrate:fresh` = menghapus semua tabel) baru berjalan di dalam `parent::setUp()`, guard selalu mendahului migrasi atau penghapusan apa pun.
  - Helper pembeku waktu `freezeNow()` di `tests/Pest.php` (default beku di `2026-01-01 12:00:00`, menerima string atau `Carbon`), plus hook global `pest()->beforeEach/afterEach` yang mereset jam antar-tes. Temuan jujur: hook `beforeEach()` telanjang di `Pest.php` **tidak dieksekusi** di Pest 4.7.8 (dibuktikan tes probe); diperbaiki memakai API `pest()->beforeEach(...)` sesuai dokumentasi resmi Global Hooks, lalu probe lulus.
  - README diperbarui: perintah tes, catatan bahwa ekstensi SQLite tidak lagi dibutuhkan, pengaman, dan helper waktu.
- Bukti pengaman terhadap database yang salah (dijalankan 2026-10-11, saat database development masih berisi tabel framework sisa verifikasi T-001 dengan 0 baris data bisnis):

  ```
  PS> $env:DB_DATABASE="kamarin"; php artisan test
  {"tool":"pest","result":"failed","tests":40,"passed":0,"assertions":0,"duration_ms":2943,"errors":40,"error_details":[{"test":"Tests\\Unit\\ExampleTest::test_that_true_is_true","file":"C:\\Users\\andikaa\\Desktop\\Kamarin\\tests\\Unit\\ExampleTest.php","line":12,"message":"Refusing to run tests against database [kamarin]. The database name must end with \"_test\" so that tests can never drop development data. Fix DB_DATABASE in phpunit.xml before running the suite again.", ... }, ...39 error_details lainnya dengan pesan guard yang sama...]}
  EXIT=2
  ```

  Database development dicek langsung setelah run gagal — utuh, tidak ada tabel yang terhapus:

  ```
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin -t -c "select count(*)||' tables' from information_schema.tables where table_schema='public';"
   10 tables
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin -c "select count(*) as users from users;"
   users
  -------
       0
  (1 row)
  ```

- Tes di PostgreSQL `kamarin_test` (dijalankan dua kali: setelah pengaturan akhir, dan sekali lagi setelah formatter):

  ```
  PS> php artisan test
  {"tool":"pest","result":"passed","tests":40,"passed":40,"assertions":138,"duration_ms":6667}
  EXIT=0
  PS> docker exec kamarin-postgres psql -U kamarin -d kamarin_test -t -c "select count(*)||' tables' from information_schema.tables where table_schema='public';"
   10 tables
  ```

  Tidak ada tes yang gagal karena perbedaan perilaku SQLite vs PostgreSQL. Semua 40 tes bawaan starter kit lulus apa adanya.

- Verifikasi helper pembeku waktu (tes probe sementara di `tests/Unit/FreezeNowProbeTest.php`, dihapus setelah verifikasi, tidak masuk suite):

  ```
  PS> vendor\bin\pest tests/Unit/FreezeNowProbeTest.php
  {"tool":"pest","result":"passed","tests":3,"passed":3,"assertions":4,"duration_ms":89}
  EXIT=0
  ```

- Formatter: `composer run lint` (Pint) memperbaiki style di `tests/TestCase.php`, lalu `composer run lint:check` lulus (`{"tool":"pint","result":"passed"}`, EXIT=0)
- Tes: 40 lulus, 0 gagal (138 assert, Pest 4.7.8, PostgreSQL 18.6)
- File kunci: `phpunit.xml`, `tests/TestCase.php`, `tests/Pest.php`, `README.md`
- Penyimpangan dari dokumen sumber: tidak ada. Dua hal di luar ruang lingkup yang dilaporkan: (1) `.github/workflows/tests.yml` bawaan starter kit menjalankan `composer setup` + `composer ci:check` tanpa layanan PostgreSQL sehingga akan gagal jika CI GitHub diaktifkan; (2) database development holds 10 tabel framework sisa verifikasi T-001 (users=0) — bukan "kosong" murni, dicatat agar bukti di atas jujur
- Perintah kunci dan sumber dokumentasi:
  - `phpunit.xml` `<env name="DB_DATABASE" value="kamarin_test"/>` — konfigurasi standar pengujian Laravel (laravel.com/docs/13.x/testing), aturan keras AGENTS.md nomor 10
  - Guard: `Env::get('DB_DATABASE')` sebelum `parent::setUp()` — `RefreshDatabase` menjalankan `migrate:fresh` di dalam `setUp()` aplikasi Laravel (laravel.com/docs/13.x/database)
  - Hook global: `pest()->beforeEach(...)` — https://pestphp.com/docs/global-hooks (diverifikasi 2026-10-11; halaman docs Pest saat ini 5.x, proyek memakai Pest 4.7.8, perilaku diverifikasi lewat tes probe)
- Penjelasan untuk sidang (bagaimana dan kenapa): Semua tes dipindah dari SQLite `:memory:` ke database PostgreSQL `kamarin_test` yang terpisah, karena aturan seperti unique index parsial dan kunci baris hanya bisa dibuktikan di PostgreSQL sungguhan. Sebuah pengaman dipasang di `tests/TestCase.php` yang membaca nama database sebelum aplikasi boot, sehingga sebelum `RefreshDatabase` sempat menghapus apa pun; jika namanya tidak diakhiri `_test`, seluruh suite langsung gagal dengan pesan jelas — ini dibuktikan dengan menjalankan tes terhadap database `kamarin` dan memverifikasi tabel development tetap utuh. Helper `freezeNow()` disediakan karena banyak aturan bisnis Kamarin bergantung pada "hari ini" (expiry, no-show), jadi tes harus bisa membekukan jam secara deterministik; jam otomatis direset antar-tes lewat hook global Pest. Satu bug nyata ditemukan saat verifikasi: hook `beforeEach()` biasa di `Pest.php` tidak dieksekusi Pest 4, sehingga dibetulkan memakai API `pest()->beforeEach(...)` sesuai dokumentasi resmi — buktinya tes probe berubah dari gagal menjadi lulus.
- Reviewer: belum ada (backlog menandai T-003 "Tidak perlu" review Claude)

### T-001 Setup lingkungan Laravel 13 + React + PostgreSQL (2026-10-10)

- Cabang: `task/T-001-setup-environment`
- Status: selesai
- Yang dikerjakan:
  - Prasyarat: aktifkan ekstensi PHP lokal `pdo_pgsql`, `zip`, `pdo_sqlite`, `sqlite3` di `php.ini` Laragon (backup: `php.ini.bak-t001`). `pdo_pgsql` adalah syarat user; `zip` dibutuhkan Composer; `sqlite3`/`pdo_sqlite` dibutuhkan tes bawaan yang memakai SQLite `:memory:`.
  - Pasang Laravel installer v5.32.0 dengan `composer global require laravel/installer` (perintah resmi dokumentasi Installation Laravel 13).
  - `laravel new kamarin-scaffold -n --database=pgsql --react --pest --npm --no-boost` di folder sementara (`%LOCALAPPDATA%\Temp\opencode`), lalu dipindah ke root repo dengan `robocopy /E /MOVE`. `docs/` dan `AGENTS.md` tidak tersentuh (tidak ada di scaffold, tidak ditimpa).
  - Pilihan installer (diverifikasi lewat `laravel new --help`): `--react` (React Starter Kit resmi: Inertia + React + TypeScript + Tailwind), autentikasi default Laravel via Fortify (bukan WorkOS), tanpa `--teams`, `--database=pgsql`, test framework `--pest` (default masuk akal), `--no-boost` (Boost = paket tambahan, butuh persetujuan).
  - Buat `compose.yaml` (hanya layanan `postgres:18-alpine`) dengan database `kamarin` (dev) dan `kamarin_test` (tes, dibuat lewat `docker/postgresql/init-test-db.sql` pada start pertama).
  - `.env` dan `.env.example`: `APP_TIMEZONE=Asia/Makassar`, `DB_*` placeholder (`kamarin`/`kamarin`/`kamarin`), `MAIL_MAILER=log`. `.env` tidak masuk git (cek `.gitignore`).
  - Verifikasi: `php artisan migrate --force` sukses di database kosong (5 migration bawaan), halaman awal `http://127.0.0.1:8000` via `composer run dev` mengembalikan HTTP 200 (welcome page React, Vite dev aktif), `php artisan config:show app.timezone` = `Asia/Makassar`.
- Tes: 40 lulus, 0 gagal (138 assert, Pest 4.7.8). Tes bawaan starter kit; masih memakai SQLite `:memory:` sesuai default `phpunit.xml` — pengalihan ke PostgreSQL `kamarin_test` dikerjakan di T-003.
- File kunci: `compose.yaml`, `docker/postgresql/init-test-db.sql`, `.env.example`, `config/app.php` (baris timezone), `README.md`
- Penyimpangan dari dokumen sumber: (1) `config/app.php` bawaan skeleton memakai `'timezone' => 'UTC'` hardcode, diubah ke `env('APP_TIMEZONE', 'UTC')` agar timezone bisa diatur lewat `.env` sesuai instruksi tugas dan AGENTS.md. (2) `laravel/sail` tetap berada di `require-dev` (bawaan skeleton, tidak dipakai; `arsitektur_sistem.md` menyebut Sail tetap alternatif). (3) Tes bawaan berjalan di SQLite, bukan PostgreSQL (aturan keras 10) — hanya sementara sampai T-003.
- Perintah kunci dan sumber dokumentasi:
  - `composer global require laravel/installer`, `laravel new ... --react --database=pgsql --pest --npm --no-boost` — dokumentasi resmi Laravel 13 Installation (laravel.com/docs/13.x/installation); daftar opsi diverifikasi langsung dari `laravel new --help` (installer 5.32.0).
  - `composer run dev` → isinya `@php artisan dev` (server + queue worker + Vite) — diverifikasi dari `composer.json`, sejalan AGENTS.md bagian Lingkungan pengembangan.
  - `docker compose up -d` — Docker Compose v5.5.1; Docker Desktop dijalankan manual oleh agent (disetujui user).
- Penjelasan untuk sidang (bagaimana dan kenapa): Proyek dibuat dengan Laravel installer resmi sehingga kerangka, starter kit React (Inertia + Fortify), dan Pest mengikuti default resmi Laravel 13 tanpa konfigurasi manual. Karena repo berada di drive Windows dan Sail mewajibkan WSL2, diputuskan PHP, Composer, dan Node berjalan lokal, sementara hanya PostgreSQL 18 yang dikemas lewat Docker; `compose.yaml` sengaja hanya berisi satu layanan database. Database dibagi dua sejak awal — `kamarin` untuk development dan `kamarin_test` untuk tes — agar pengujian tidak pernah menyentuh data development, sesuai aturan keras nomor 10. Timezone dipindah dari hardcode ke `.env` (`APP_TIMEZONE=Asia/Makassar`) mengikuti prinsip bahwa kebijakan tidak boleh hardcode. Kelayakan lingkungan dibuktikan dengan migration di database kosong, halaman awal yang terbuka lewat `composer run dev`, dan seluruh tes bawaan yang lulus.
- Reviewer: Antigravity (2026-10-10) — hasil di bawah

#### Hasil review Reviewer (2026-10-10)

Reviewer memeriksa cabang secara mandiri tanpa mengedit kode. Semua perintah dijalankan langsung; tidak hanya mempercayai laporan Builder.

**compose.yaml**

| Sub-poin | Status | Bukti |
| --- | --- | --- |
| Port host:container | ❌ → ✅ **Diperbaiki** | Semula `5432:5432` (bind ke semua antarmuka, melanggar AGENTS.md). Diperbaiki ke `127.0.0.1:5432:5432`. Diverifikasi: `docker ps` → `127.0.0.1:5432->5432/tcp`. **Temuan ini terlewat oleh Reviewer saat review pertama** |
| Kredensial | ✅ Sesuai | `kamarin/kamarin/kamarin` — development-only, bukan rahasia produksi; didokumentasikan di `.env.example` |
| Volume data | ✅ Sesuai | Mount ke `/var/lib/postgresql` — **benar untuk image 18**. Saran Reviewer untuk mengubah ke `/var/lib/postgresql/data` ditolak user. Sumber: README resmi `docker-library/postgres` (PGDATA dan VOLUME berubah di versi 18 ke atas) |
| Init script | ✅ Sesuai | `./docker/postgresql/init-test-db.sql:ro` → `CREATE DATABASE kamarin_test;` — diverifikasi langsung |
| Kontainer berjalan | ✅ Sesuai | `docker ps` → `kamarin-postgres Up (healthy)`, port `127.0.0.1:5432->5432/tcp` (setelah perbaikan) |
| Koneksi DB dari app | ✅ Sesuai | `php artisan db:show` → PostgreSQL 18.6, database `kamarin`, 10 tabel, koneksi berhasil |
| Database `kamarin_test` ada | ✅ Sesuai | `docker exec kamarin-postgres psql -U kamarin -c "\l"` → `kamarin` dan `kamarin_test` keduanya ada |


**`.env.example`**

| Sub-poin | Status | Bukti |
| --- | --- | --- |
| `APP_TIMEZONE=Asia/Makassar` | ✅ Sesuai | Nilai tepat, sesuai keputusan user 2026-10-09. `php artisan config:show app.timezone` → `Asia/Makassar` |
| `DB_CONNECTION=pgsql` dan `DB_*` lengkap | ✅ Sesuai | `host=127.0.0.1`, `port=5432`, `database/username/password=kamarin` |
| `MAIL_MAILER=log` | ✅ Sesuai | Tertera eksplisit |
| Tidak ada rahasia nyata | ✅ Sesuai | `APP_KEY=` dikosongkan; tidak ada token atau password produksi |
| `.env` tidak ter-commit | ✅ Sesuai | `.gitignore` mencantumkan `.env`; `git ls-files --cached` tidak menemukan `.env` |

**README.md**

| Sub-poin | Status | Bukti |
| --- | --- | --- |
| Langkah setup | ✅ Sesuai | Tiga langkah jelas: `docker compose up -d`, `composer install`, `composer run setup` |
| Ekstensi PHP yang dibutuhkan | ✅ Sesuai | `pdo_pgsql` disebutkan dengan versi terverifikasi |
| Tabel versi | ✅ Sesuai | PHP 8.3.35, Laravel 13.35.0, PostgreSQL 18.6, Node/npm, Composer, Pest 4.7.8 |
| Catatan SQLite sementara | ✅ Sesuai | README jujur: tes masih di SQLite `:memory:`; T-003 yang akan mengalihkan |

**Aturan keras AGENTS.md**

| Aturan | Status | Bukti |
| --- | --- | --- |
| #1 Skema: tidak ada migration di luar bawaan framework | ✅ Sesuai | Migration yang ada: 3 bawaan framework + 2 bawaan Fortify starter kit. Tidak ada migration domain Kamarin. Diizinkan aturan #13 |
| #6 Kebijakan tidak di-hardcode: timezone lewat `.env` | ✅ Sesuai | `config/app.php` → `env('APP_TIMEZONE', 'UTC')` |
| #8 Otorisasi: tanpa paket role-permission | ✅ Sesuai | `composer.json` tidak mengandung paket role-permission pihak ketiga |
| #10 Tes memakai PostgreSQL | ⚠️ Penyimpangan sementara (tercatat jujur) | `phpunit.xml` masih `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Builder mendelegasikan ke T-003 |
| #11 Dependensi tanpa persetujuan | ✅ Sesuai | `laravel/sail`, `laravel/chisel`, `laravel/wayfinder` bawaan installer resmi — **disetujui user 2026-10-10**. Paket lain di luar installer tetap butuh persetujuan eksplisit |
| #13 Fitur starter kit tidak dipakai harus dinonaktifkan | ❌ **Tidak sesuai — ditindaklanjuti di T-002** | `config/fortify.php` masih mengaktifkan `Features::registration()`, `Features::emailVerification()`, `Features::twoFactorAuthentication()`, `Features::passkeys()`. Halaman `register.tsx` masih ada. Akan dibuang di T-002 menggunakan `laravel/chisel` bila tersedia |

**Tes (dijalankan sendiri oleh Reviewer)**

```
php artisan test --no-ansi
→ 40 passed (138 assertions), 0 failed — 4139ms — Pest 4.7.8
```

✅ Semua lulus. Catatan: masih di SQLite `:memory:` (penyimpangan aturan #10, didelegasikan ke T-003).

**Keputusan user (2026-10-10) setelah review:**
- Volume `/var/lib/postgresql` tetap (benar untuk image 18; saran Reviewer ditolak karena salah).
- Port diperbaiki ke `127.0.0.1:5432:5432` — **temuan ini terlewat oleh Reviewer, ditemukan user**. `compose.yaml` sudah diperbarui dan diverifikasi.
- Paket bawaan installer resmi (`laravel/wayfinder`, `laravel/chisel`, `laravel/sail`) disetujui. Paket lain butuh izin.
- Fitur starter kit yang tidak dipakai dibuang di T-002 menggunakan `laravel/chisel` bila tersedia.

**Kesimpulan Reviewer (final):** T-001 memenuhi kriteria selesai di BACKLOG_FASE4_5.md setelah perbaikan port. Satu temuan kritis (port terbuka ke semua antarmuka) terlewat oleh Reviewer dan ditemukan user — dicatat sebagai kelemahan review. Satu penyimpangan aktif tersisa: fitur Fortify tidak dipakai masih aktif, ditindaklanjuti di T-002.

