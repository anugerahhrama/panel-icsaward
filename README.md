# ICS Award 2026 — Assessment App

Aplikasi registrasi peserta, pengumpulan paper, verifikasi administrasi, dan penjurian dua tahap untuk **Indonesia Corporate Sustainability Award (ICS Award) 2026** — Olahkarsa Group bersama IBCSD sebagai Knowledge Partner.

App ini terpisah dari Landing Page (repo, database, dan domain berbeda) dan menjadi **source of truth** untuk kategori, juri, serta statistik pendaftar. Landing menarik data tersebut lewat API read-only.

Spesifikasi lengkap ada di [`ASSESSMENT-BRIEF.md`](ASSESSMENT-BRIEF.md); status fitur terkini ada di [`.ai/PROJECT.md`](.ai/PROJECT.md).

## Peran & fitur utama

| Peran                  | Fitur                                                                                                                                                                                                                                                                |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Participant**        | Sign Up multi-step (Account → Initiative → Terms), login email/password atau Google, email konfirmasi, upload paper + revisi, dashboard status (verifikasi, finalis, jadwal pitching)                                                                                |
| **Admin / Superadmin** | Settings (registrasi & deadline, file & T&C, template email, penjurian, Landing API), Categories + Assessment Templates, Participants + export Excel, verifikasi administrasi, preview dokumen, Judges, Score Recap, Finalis Top 5, Pitching, Awards, Admin Accounts |
| **Judge**              | Overview progres, daftar submission yang ditugaskan, penilaian Tahap 1 (Desk Evaluation) dan Tahap 2 (Pitching)                                                                                                                                                      |

Alur penjurian: skor Tahap 1 dinormalisasi (z-score per juri) → ranking per kategori → Finalis Top 5 → pitching → skor Tahap 2 → Final Score berbobot → penghargaan (Gold/Silver/Bronze).

## Stack

- **Backend:** PHP 8.3+ (lokal 8.5), Laravel 13, Fortify, Socialite (Google), Wayfinder, maatwebsite/excel
- **Frontend:** Inertia v3, React 19, TypeScript, Tailwind CSS v4, shadcn/ui, TanStack Table, Vite (via `vite-plus`)
- **Testing & kualitas:** Pest 5, PHPStan (Larastan), Pint, `vp check`, `tsc`
- **Default lokal:** SQLite, queue/cache/session di database, mail ke log

## Menjalankan secara lokal

Prasyarat: PHP 8.3+, Composer, Node.js, npm.

```bash
composer run setup          # install dependency, .env, key, migrate, build aset
php artisan db:seed         # settings, 20 kategori, assessment template, akun superadmin
php artisan storage:link    # agar template/foto yang diunggah admin bisa diakses
composer run dev            # dev server + Vite
```

Jalankan worker queue di terminal terpisah — email konfirmasi & notifikasi verifikasi hanya terkirim lewat queue:

```bash
php artisan queue:work
```

Akun superadmin hasil seeder (**hanya untuk lokal**): `admin@app.com` / `123123123`.

Panel admin berada di bawah prefix `ADMIN_PANEL_PREFIX` (lihat `.env`). Juri memakai rute `/judge/dashboard`, peserta memakai `/dashboard`. Halaman `/` mengarah ke `/login`.

### Jadwal dari deck

`DeckScheduleSeeder` mengisi timeline & periode registrasi sesuai deck. Seeder ini **menimpa** nilai jadwal di Settings, jadi jalankan dengan sadar:

```bash
php artisan db:seed --class=DeckScheduleSeeder
```

## Konfigurasi `.env`

Selain variabel standar Laravel:

| Variabel                                                          | Keterangan                                                                                                           |
| ----------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| `ADMIN_PANEL_PREFIX`                                              | Prefix URL panel admin yang tidak mudah ditebak. **Ganti dengan nilai privat di produksi.**                          |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Login Google untuk peserta yang sudah terdaftar. Daftarkan `<APP_URL>/auth/google/callback` di Google Cloud Console. |
| `MAIL_*`                                                          | SMTP untuk email konfirmasi & notifikasi (lokal default `log`).                                                      |

Token Landing API tidak disimpan di `.env` — dibuat superadmin lewat **Settings → Landing API** dan disimpan terenkripsi di database.

## API untuk Landing

Read-only, autentikasi `Authorization: Bearer <token>`, dibatasi 60 request/menit:

```
GET /api/v1/landing/categories
GET /api/v1/landing/judges
GET /api/v1/landing/stats
```

Kontrak lengkap: `ASSESSMENT-BRIEF.md` § API untuk Landing.

## Testing & quality gate

```bash
composer run ci:check       # vp check · tsc · Pint · PHPStan · Pest (sama dengan CI)
php artisan test --compact  # test saja
composer run lint           # format PHP dengan Pint
npm run check:fix           # format & lint frontend
```

CI berjalan lewat GitHub Actions ([`.github/workflows/tests.yml`](.github/workflows/tests.yml)).

## Deploy

Checklist setelah deploy produksi:

1. `php artisan migrate --force`
2. `php artisan storage:link` (sekali)
3. Seeder data sesuai kebutuhan: `AwardCategorySeeder` → `AssessmentTemplateSeeder` → `DeckScheduleSeeder`
4. Pastikan worker `php artisan queue:work` berjalan (supervisor/daemon)
5. Set `ADMIN_PANEL_PREFIX` privat, kredensial `MAIL_*` dan `GOOGLE_*`
6. Ganti password akun superadmin seeder, atau buat superadmin baru lewat **Admin Accounts** lalu hapus akun seeder

## Struktur singkat

```
app/Actions/            logika bisnis (RegisterSubmission, SubmitPaper, VerifySubmission, CalculateStage*Scores, …)
app/Http/Controllers/   Admin/, Judge/, Api/, Auth/, Settings/ + controller peserta
app/Enums/              status submission, tahap penjurian, award, dll.
routes/                 web.php, admin.php, judge.php, api.php, settings.php
resources/js/pages/     halaman Inertia: admin/, judge/, auth/, submissions/, settings/, dashboard.tsx
database/seeders/       settings, kategori, assessment template, jadwal deck
.ai/                    PROJECT.md (status & backlog) + rules/ (keputusan & konvensi kode)
```

## Cara kerja tim

Repo ini memakai alur **Session-per-Feature** (1 sesi = 1 fitur) — lihat [`AGENTS.md`](AGENTS.md). Konvensi kode per area ada di [`.ai/rules/`](.ai/rules/index.md); baca rule yang cocok sebelum mengubah file.
