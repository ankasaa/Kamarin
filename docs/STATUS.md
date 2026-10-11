# STATUS Kamarin

File hidup. Agent memperbarui bagian "Log tugas" setelah **setiap** tugas. Bagian "Ringkasan untuk Claude" disalin atau diunggah ke chat Claude saat meminta review atau saran, supaya tidak perlu menempelkan seluruh repo.

Diperbarui terakhir: 2026-10-11

---

## Ringkasan untuk Claude

Salin entri terbaru dari "Log tugas" ke sini, atau tulis ulang singkat. Isi minimal:

- Tugas dan cabang: T-002, `task/T-002-auth-cleanup` (dari `main` setelah T-003 di-merge)
- Status: selesai & di-review (LULUS)
- Hasil verifikasi akhir (dijalankan mandiri, bukan klaim): `laravel/chisel` dicopot (`composer remove`); `npm run build` lulus (EXIT=0); **24 tes lulus, 0 gagal** (68 assert) di PostgreSQL `kamarin_test`; `composer run lint:check` (Pint) lulus; rute register/verification/two-factor/passkey tersisa **0**; `php artisan migrate:fresh` di development sukses; kolom `users` tinggal id, name, email, email_verified_at, password, remember_token, created_at, updated_at (cocok aturan 13); Reviewer Antigravity menyatakan lulus di semua 5 kriteria fokus.
- Keputusan user:
  1. `laravel/chisel` dihapus dari `composer.json` (sudah dieksekusi via `composer remove`).
  2. `laravel/sail` dipertahankan di dev dependencies (bawaan installer, disetujui aturan 11).
  3. `npm run types:check` yang gagal karena 3 error pre-existing di file generated wayfinder diabaikan sementara sampai T-015.
  4. Dokumen di folder `docs/` tidak diformat otomatis oleh `npm run check:fix` (format dokumen sumber dipertahankan).
- File kunci untuk di-review (path): `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `app/Models/User.php`, `app/Http/Controllers/Settings/SecurityController.php`, `app/Http/Controllers/Settings/ProfileController.php`, `routes/web.php`, `routes/settings.php`, `database/factories/UserFactory.php`, `tests/Feature/Settings/SecurityTest.php`, `composer.json`.

---

## Posisi proyek

| Hal | Status |
| --- | --- |
| Fase 2 (aturan bisnis) | Selesai: `docs/business_rules_kamarin.md` v1.1, keputusan K1 sampai K10 disetujui |
| Fase 3 (perancangan) | Dokumen selesai dan disetujui. Wireframe Figma masih dikerjakan user |
| Fase 4 (setup) | Selesai: T-001 (lingkungan), T-003 (tes PostgreSQL + pengaman), T-002 (pruning autentikasi) |
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

Entri T-001, T-002, dan T-003 dipindahkan ke `docs/STATUS_ARCHIVE.md` (semua sudah terjawab user). Entri baru ditambahkan di bawah ini.

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

### Tugas yang sudah selesai (diarsipkan)

Ringkasan satu baris per tugas; entri lengkap ada di `docs/STATUS_ARCHIVE.md`.

| Tugas | Tanggal | Status | Commit akhir |
| --- | --- | --- | --- |
| T-001 Setup lingkungan Laravel 13 + React + PostgreSQL | 2026-10-10 | Selesai, review lulus setelah perbaikan port | `32fac10` |
| T-003 Tes pindah ke PostgreSQL, pengaman database, dan pembeku waktu | 2026-10-11 | Selesai | `b66530f` |
| T-002 Pruning autentikasi starter kit | 2026-10-11 | Selesai, review lulus | `2d1233c` (squash-merge di main; cabang asli `task/T-002-auth-cleanup`) |
