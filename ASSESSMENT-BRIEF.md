# ICS Award 2026 — Assessment App (Registrasi, Paper, Penjurian)

> Brief untuk repo baru. Pindahkan file ini ke root repo Assessment setelah repo dibuat. Menggabungkan brief hasil diskusi Claude web + keputusan diskusi 2026-09-29 di repo Landing.

## Ringkasan

Sistem registrasi peserta, pengumpulan paper, verifikasi administrasi, dan penjurian dua tahap untuk **Indonesia Corporate Sustainability Award (ICS Award) 2026** — Olahkarsa Group bersama IBCSD sebagai Knowledge Partner. Tema: _"Scaling Indonesia's Green Economy: From Regeneration to Transformation"_.

- **Terpisah** dari Landing Page (repo, database, domain berbeda). Tombol Register di landing (`registration_url`) mengarah ke app ini.
- App ini = **source of truth** untuk `award_categories`, `judges`, dan statistik pendaftar. Landing menarik (mirror) data itu lewat API read-only — lihat [API untuk Landing](#api-untuk-landing).
- Tiga peran pengguna: **participant**, **admin**, **judge**.

Sumber acuan: Brief Notion (alur registrasi Step 1–5) dan Updated Concept Deck (kategori, rubrik, tahap penilaian, timeline, ketentuan umum). Beberapa hal masih TBA — **jangan hardcode** nilai yang diragukan, simpan sebagai setting (lihat [Perlu Dikonfirmasi](#perlu-dikonfirmasi-ke-tim)).

> ⚠️ **Mendesak.** Open Submission menurut deck (update 2026-09-30) dibuka **1 Okt**, Desk Evaluation mulai **12 Okt**. Kerjakan berurutan per [Fase](#fase-pengerjaan): Fase 1 (peserta bisa daftar & upload) harus live secepatnya; penjurian Tahap 1 harus siap sebelum 12 Okt.

## Bahasa

- **UI: full English** (semua copy komponen, label, email default). Landing berbahasa Inggris juga di UI publik.
- Data konten dari deck (deskripsi kategori, kriteria rubrik) berbahasa Indonesia — tampilkan apa adanya.
- Dokumen kerja internal (`.ai/PROJECT.md`, `.ai/rules/`, brief ini) tetap Bahasa Indonesia.
- Bahasa email konfirmasi belum diputuskan — template email disimpan di settings (draft EN & ID di lampiran), default EN.

## Stack & Setup

Sama seperti Landing, **minus GSAP & Three.js**:

- Laravel 13 + Inertia v3 + React 19 + shadcn/ui + Tailwind v4 (token di `resources/css/app.css` via `@theme`) + Wayfinder
- Auth: Laravel Fortify (login, register, forgot/reset password, verify email)
- TanStack Table **v9** untuk tabel admin/juri
- Storage file: disk privat (S3, `league/flysystem-aws-s3-v3` seperti Landing); unduhan paper hanya lewat route ber-auth / signed URL. Foto juri disimpan di disk publik (URL-nya dikirim ke landing).
- Email: lewat queue (driver `database`). Provider/sender domain TBA.
- Export Excel: `maatwebsite/excel` (cek kompatibilitas Laravel 13 saat install; fallback CSV streaming kalau tidak cocok)
- Quality gate: `composer run ci:check` (Pint, PHPStan/Larastan, lint+format frontend, Pest) — sama seperti Landing
- Workflow kerja: jalankan skill `/newproject` untuk scaffold `.ai/PROJECT.md`, `.ai/rules/`, `AGENTS.md`, `CLAUDE.md` (Session-per-Feature, agent tidak menjalankan git yang menulis)

### Salin dari repo Landing (`icsa2026-app`)

Salin selektif (bukan package bersama). Setelah disalin, file ini milik repo Assessment dan boleh berkembang sendiri.

| Kebutuhan                        | Path di Landing                                                                                                                                                                                                                 |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Token brand + utility gradien KV | `resources/css/app.css` (`--color-ink/surface/deep`, `--color-brand-*`, `@utility text-brand-gradient`, `bg-kv-corners`, `bg-kv-stage`) — buang token khusus landing (`--color-gold`, `text-gold-metallic`) kalau tidak dipakai |
| Font                             | Plus Jakarta Sans (setup di `resources/css/app.css` Landing)                                                                                                                                                                    |
| Supergraphic & key visual        | `resources/js/components/supergraphic/`, `resources/js/components/key-visual/`, `resources/images/brand/`                                                                                                                       |
| Logo                             | `app-logo.tsx`, `app-logo-icon.tsx` (+ aset logo ICS Award)                                                                                                                                                                     |
| Data table                       | `resources/js/components/data-table/*`, `resources/js/hooks/table.ts`, `hooks/table-contexts.ts`, `lib/data-table.ts`, `components/ui/faceted.tsx`, `ui/sortable.tsx` (pola `useAppTable`, `AppHeader`/`AppCell`)               |
| Sidebar & shell                  | `app-sidebar.tsx`, `nav-main.tsx` (sub-menu collapsible), `nav-user.tsx`, `app-sidebar-header.tsx`, `layouts/app/app-sidebar-layout.tsx`, `layouts/app/app-header-layout.tsx`, `components/ui/sidebar.tsx`                      |
| Auth layout                      | `layouts/auth/auth-split-layout.tsx` (+ `auth-layout.tsx`)                                                                                                                                                                      |
| Form admin                       | `components/admin/logo-upload-field.tsx`, `save-button.tsx`, `settings-section.tsx`, pola CRUD via **Sheet** (contoh: `pages/admin/judges/` + `components/admin/judges/judge-form.tsx`)                                         |
| Hooks utilitas                   | `hooks/use-flash-toast.ts`, `use-initials.tsx`, `use-mobile.tsx`                                                                                                                                                                |
| Model settings                   | `app/Models/Setting.php` (key-value, `Setting::get()`)                                                                                                                                                                          |
| Admin prefix & role gate         | pola route group `/admin` dengan prefix tidak mudah ditebak di `routes/admin.php` + middleware role                                                                                                                             |
| Rules kerja                      | `.ai/rules/js.md`, `components.md`, `css.md`, `process.md` — ambil yang generik (SSR hydration, prop shape, pkill, zsh, Playwright), buang yang khusus section landing/GSAP                                                     |

**Tidak disalin:** GSAP, Three.js, `ScrollSmoother`, semua section landing, cookie consent (kecuali nanti dibutuhkan).

## Arah Desain

### Halaman publik & auth (tema Landing)

Login, Sign Up (multi-step), Forgot/Reset Password, Verify Email, halaman Upload Paper — semua pakai **tema landing**:

- `auth-split-layout`: panel brand (kiri di desktop, header ringkas di mobile) berisi `bg-kv-corners` + supergraphic + logo ICS Award + tema; panel form putih/`bg-surface`.
- Palet: Primary `#0567CC`, Cyan `#03F5FF`, Green `#03BD43` + tint (Primary Dark `#0229BF`, Teal `#007AA4`, Cyan Mid `#00BDF2`, Cyan Light `#9DDCF9`, Green Mid `#00A64C`, Mint `#3FFFC1`), netral Ink `#0B1220` & Surface. Teks putih hanya di zona hijau/biru gradien, bukan di zona mint/cyan muda.
- Sign Up bertahap dengan **stepper** bernuansa brand: Account → Initiative → Terms & Submit.

### Dashboard admin & juri (sidebar)

- Sidebar sama seperti Landing (collapsible, sub-menu), **logo ICS Award di header sidebar**.
- **Key visual hanya di banner halaman Overview** (strip `bg-kv-stage` + supergraphic + sapaan + ringkasan angka). Halaman tabel/form tetap netral — juri akan lama menatap tabel skor.
- Favicon & nama aplikasi "ICS Award 2026" sejak awal (jangan biarkan default Laravel).

### Dashboard peserta (tanpa sidebar)

Peserta hanya punya 1–2 submission, jadi pakai **header layout** (logo + user menu) dengan konten:

1. **Kartu status besar** per submission: stepper `Registered → Paper Submitted / Under Review → Qualified | Needs Revision | Disqualified → Finalist → Winner`.
2. **Panel aksi** sesuai status: belum upload → tombol Upload Paper + download template + sisa waktu deadline; `needs_revision` → catatan panitia + tenggat revisi + tombol upload ulang.
3. **Panel "What's next"** (wajib, juga setelah upload & file terkunci): tahap berikutnya + tanggalnya dari settings (Administrative Selection, Desk Evaluation, Top 5 Announcement, Pitching, Awarding Night), apa yang akan terjadi dan kapan peserta akan dihubungi, ringkasan file yang sudah dikirim (nama file + waktu upload, bisa diunduh ulang, read-only), kontak panitia.
4. Finalis: kartu **jadwal pitching** (tanggal, jam, link/lokasi). Pemenang/finalis: info & undangan Awarding Night.

## Alur Peserta

Registrasi dan pengumpulan paper adalah **dua fase terpisah** yang dihubungkan link di email.

**Step 1 — Sign Up.** Field: full name, phone, email, position, company name, password. Tanpa profil perusahaan/logo, tanpa OTP terpisah (email konfirmasi Step 4 sekaligus verifikasi email).

**Step 2 — Initiative Data.**

- Pilih **kategori award** (1 kategori per registrasi; kategori yang sudah dipakai user tidak bisa dipilih lagi)
- Initiative title (text) + short description
- Download **Submission Paper Template** & **Statement Letter** — file diunggah/diganti admin dari Settings, bukan hardcode

**Step 3 — Terms & Submit.** Tampilkan T&C dari settings — versi **Organization** (12 poin) atau **Individual** (9 poin) mengikuti `applicant_type` kategori → checkbox setuju (simpan `terms_accepted_at`) → submit registrasi.

**Step 4 — Email konfirmasi.** Berisi link pengumpulan paper unik (`/submissions/{uuid}`, butuh login, hanya pemilik). Klik link = email terverifikasi. Template email di lampiran.

**Step 5 — Upload Paper.** Lewat link email (atau dashboard):

1. Upload **Submission Paper** (format & ukuran maks dari settings; deck: PPTX — TBA)
2. Upload **Statement Letter bermaterai Rp10.000** — hanya kalau setting `require_statement_letter` aktif
3. Submit → file **terkunci**, status otomatis `under_review`, kirim email "paper received" (opsional, template di settings)
4. Tampilkan halaman sukses + panel **"What's next"** (lihat Dashboard peserta)

Aturan: 1 link = 1 submission pada 1 kategori. Upload ditutup otomatis saat lewat `paper_deadline` (cek waktu di request, tidak butuh cron).

**Step 6 — Verifikasi administrasi (panitia).** `under_review` → `qualified` / `needs_revision` (+ catatan + `revision_deadline`, file dibuka kembali sampai tenggat lalu terkunci lagi) / `disqualified` (+ alasan). Email notifikasi ke peserta di tiap perubahan status.

**Step 7 — Penjurian & Pitching.** Lihat [Desain Penjurian](#desain-penjurian). Finalis melihat jadwal pitching di dashboard.

**Step 8 — Pengumuman & Awarding Night.** Pengumuman via dashboard + email; finalis/pemenang mendapat undangan Awarding Night.

### Aturan peserta (dari deck)

- Inisiatif sudah terlaksana 1–3 tahun, asli, akurasi data tanggung jawab peserta → pernyataan di T&C, bukan field form
- 1 inisiatif per kategori per peserta → `unique(user_id, award_category_id)`
- Terlambat = tidak memenuhi kualifikasi → auto-lock di deadline
- Komite berhak mendiskualifikasi; keputusan Dewan Juri final; data dijaga kerahasiaannya
- **Tipe peserta diturunkan dari kategori** (`applicant_type`): #1–18 organization, #19–20 (Best Sustainability Leader) individual. Syarat masa jabatan individu diverifikasi panitia, tidak divalidasi form.
- Batas registrasi per akun: setting `max_registrations_per_user` (default **1**)

### Status submission

`registered` → `under_review` → `needs_revision` | `qualified` | `disqualified` → `finalist`. Level penghargaan di field terpisah `award` (`gold` / `silver` / `bronze` / `leader_winner`, nullable). Gunakan enum PHP (TitleCase key).

## Timeline Kompetisi (settings, JANGAN hardcode)

| Tahap                                                 | Tanggal (deck)                                            | Pelaku  |
| ----------------------------------------------------- | --------------------------------------------------------- | ------- |
| Open Submission                                       | 1 – 26 Okt                                                | peserta |
| Administrative Selection (termasuk tenggat perbaikan) | 1 – 26 Okt                                                | panitia |
| Desk Evaluation (Tahap 1)                             | 12 – 30 Okt                                               | juri    |
| Announcement Top 5 Finalists                          | 2 – 4 Nov                                                 | panitia |
| Pitching Process                                      | 6 – 10 Nov                                                | juri    |
| Final Assessment (Tahap 2)                            | 11 Nov                                                    | juri    |
| Awarding Night                                        | 20 Nov — Mason Pine Hotel, Kota Baru Parahyangan, Bandung | —       |
| Sustainability Trip                                   | 21 Nov                                                    | —       |

Sumber: slide timeline pertama di deck update 2026-09-30 (deck memuat slide kedua dengan jadwal mundur sampai 4 Des — dinyatakan tidak berlaku oleh user). Nilai ada di `DeckScheduleSeeder` (default `SettingSeeder` memakainya; jalankan `db:seed --class=DeckScheduleSeeder` untuk menimpa nilai di DB), selanjutnya diubah lewat Admin → Settings. Sustainability Trip belum punya key timeline sendiri. Desk Evaluation mulai sebelum submission tutup → juri hanya melihat submission `qualified` (rolling), dan submission `qualified` tidak bisa diedit lagi. Tanggal-tanggal ini juga dipakai panel "What's next" peserta.

## Kategori (20, dikelola penuh dari admin)

1 Best Renewable Energy Initiative · 2 Best Energy Efficiency Program · 3 Best Decarbonization Strategy · 4 Best Circular Economy Innovation · 5 Best Biodiversity Conservation Initiative · 6 Best Water Stewardship Initiative · 7 Best Nature-based Solutions · 8 Best Diversity & Inclusion Program · 9 Best Human Capital Development Program · 10 Best Occupational Health & Safety Program · 11 Best Community Education Program · 12 Best Community Economic Development Program · 13 Best Community Environmental Program · 14 Best Corporate Philanthropy Program · 15 Best Shared Value Creation · 16 Best Sustainable Supply Chain · 17 Best Sustainable Product/Service Innovation · 18 Best Sustainability Reporting · 19 Best Sustainability Leader (High Level Management) · 20 Best Sustainability Leader (Middle Level Management)

Di-seed dari daftar ini (seeder idempotent, `updateOrCreate` by name), lalu dikelola admin.

## Desain Penjurian

### Rubrik = Assessment Template

Kategori dikelompokkan ke **template penilaian**; tiap template berisi baris aspek dengan kolom **Assessment Aspect, Criteria, Description & Key Indicators, Weight (%)**. Total weight per template **wajib 100%** (validasi server). Kategori punya `assessment_template_id`.

| Template                      | Kategori | Bobot 5 aspek (urut)                                                                                                                                       |
| ----------------------------- | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Environmental Assessment      | #1–7     | Strategy & Governance 15 · Implementation & Stakeholder Engagement 20 · Measurable Impact 35 · Scale & Transformation 15 · Innovation & Uniqueness 15      |
| Workforce & Workplace         | #8–10    | 20 · 25 · 30 · 15 · 10                                                                                                                                     |
| Community & Social Investment | #11–14   | 15 · 20 · 30 · 20 · 15                                                                                                                                     |
| Business Model & Value Chain  | #15–17   | 15 · 15 · 25 · 30 · 15                                                                                                                                     |
| Reporting & Communication     | #18      | Kelengkapan 30 · Kredibilitas 35 · Komunikasi 20 · Multimedia Application 15 (**4 aspek**)                                                                 |
| Individual Leadership         | #19–20   | Visi & Kepemimpinan Strategis 20 · Hasil Terukur 30 · Transformasi Organisasi & Budaya 20 · Pengaruh Ekosistem & Advokasi 20 · Integritas & Keteladanan 10 |

Empat template standar memakai nama aspek sama tapi kriteria & deskripsi beda per template. Jumlah aspek tidak selalu 5 (Reporting = 4). Isi lengkap dari deck (update 2026-09-30) ada di `AssessmentTemplateSeeder`.

UX admin: saat create/edit kategori, pilih template yang ada **atau buat template baru inline** (tabel dinamis + "+ Add Assessment Aspect", indikator total bobot real-time, submit disabled kalau ≠ 100%). Assign juri di form yang sama (opsional).

### Juri ↔ Kategori

Many-to-many via `category_judges` + flag `is_recused` (conflict of interest → juri tidak melihat kategori/submission itu). Juri = profil di tabel `judges`; akun login (user role `judge`) ditautkan lewat `judges.user_id` **nullable** — profil publik bisa dibuat dulu (untuk landing) sebelum akun juri diundang.

### Dua tahap penilaian

Skala skor juri **0–100 per kriteria**. Rubrik sama untuk kedua tahap. Skor bisa disimpan sebagai draft lalu di-**submit** per submission. Juri tetap bisa mengedit skor yang sudah di-submit selama tahapnya aktif (keputusan 2026-09-30, belajar dari tahun lalu).

**Tahap 1 — Desk Evaluation** (submission `qualified`)

1. Weighted score per juri = Σ (raw_score × weight / 100)
2. Normalisasi z-score per juri: `(score − mean_juri) / stddev_juri`
3. Stage 1 Score = rata-rata z-score semua juri non-recused
4. Ranking per kategori → **Top 5 = finalis** (maks 5 per kategori; dikonfirmasi panitia 2–4 Nov). Panitia mencentang finalis di Score Recap (rank 1–5 jadi saran); konfirmasi mengubah status → `finalist` dan membekukan hasil kategori itu (`award_categories.finalists_confirmed_at`), sehingga Recalculate ditolak. Hanya superadmin yang bisa membuka kembali

**Tahap 2 — Pitching + Final Assessment** (finalis saja)

- Pitching = sesi per kategori, diakhiri **Pleno** juri; juri menilai dengan rubrik sama → Stage 2 Score (normalisasi sama)
- **Final Score = Stage1 × w1 + Stage2 × w2**, w1 + w2 = 100, di settings (nilai belum ditentukan)

**Penghargaan** per kategori dari ranking Final Score: rank 1 Gold · rank 2–3 Silver · rank 4–5 Bronze. Best Sustainability Leader: 3 winner (mekanisme TBA). Deck: 1 Gold · 2 Silver · 2 Bronze per kategori, total ≥ 96 winners.

**Normalisasi:** z-score tidak stabil untuk sampel kecil. Mean/stddev dihitung dari **semua submission yang dinilai juri itu dalam tahap yang sama (lintas kategori)**; fallback ke weighted raw score kalau sampel < `normalization_min_sample` atau stddev = 0; normalisasi bisa dimatikan (`normalization_enabled`). Tampilkan raw & normalized berdampingan di Score Recap agar panitia bisa menjelaskan hasil.

### Kontrol tahap (`judging_stage`)

- Setting `closed` / `desk_evaluation` / `pitching` (enum `JudgingStage`, nilai yang sama dipakai `judge_scores.stage`) — hanya satu aktif
- `closed` → juri tidak bisa input skor
- Tahap aktif → CRUD `award_categories`, `assessment_templates`, `scoring_criteria`, `category_judges` di-lock untuk admin; **superadmin tetap bisa edit** (keputusan 2026-09-30)

## Admin

### Sidebar

- **Overview** — banner KV + angka: registrasi, paper masuk, per status, per kategori
- **Categories** (sub: Categories, Assessment Templates)
- **Judges** (profil + akun + penugasan kategori)
- **Participants** — tab **Registrations** & **Paper Submissions** (verifikasi administrasi: status, catatan revisi, diskualifikasi), masing-masing **Export Excel** (filter kategori/status)
- **Score Recap** — rekap skor, ranking, finalis, penghargaan
- **Pitching** — sesi per kategori & slot finalis
- **Settings** (sub: Registration & Deadlines, Judging, Files & Terms, Email Templates, Landing API)

### Dashboard juri (sidebar, menu minimal)

- **Overview** — banner KV + progres penilaian (x dari y submission dinilai, per tahap)
- **My Submissions** — tabel submission yang ditugaskan (kategori non-recused, status sesuai tahap), kolom status penilaian draft/submitted
- **Scoring page** — viewer/unduh paper + form rubrik (aspek, kriteria, deskripsi, bobot, input 0–100, catatan), total weighted real-time
- Juri **tidak** melihat identitas juri lain maupun skor juri lain

### Export Excel

- **Registrations** (semua registrasi): waktu registrasi, nama, HP, email, jabatan, perusahaan, kategori, judul inisiatif, deskripsi, T&C disetujui, status
- **Paper Submissions** (sudah upload): waktu upload, nama, email, perusahaan, kategori, judul, nama file paper & statement letter (link download khusus admin), status verifikasi
- Selisih keduanya = peserta yang belum upload paper (bahan reminder)

### Settings (key-value `Setting`)

`is_registration_open`, `registration_deadline`, `paper_deadline`, jadwal tahap (tabel Timeline), `judging_stage`, `stage_1_weight`, `stage_2_weight`, `normalization_enabled`, `normalization_min_sample`, `max_registrations_per_user`, `require_statement_letter`, `paper_allowed_extensions`, `paper_max_size_mb`, file Submission Paper Template (per kategori — 1 kategori 1 template, keputusan 2026-09-30) & Statement Letter, teks T&C (Organization & Individual), template email (subject + body, placeholder), `contact_email`, `landing_api_token` (atau di `.env`, lihat di bawah).

## Skema Database (garis besar)

| Tabel                  | Field utama                                                                                                                                                                                                                                                                                                                                                                                                                          |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `users`                | full_name, phone, email, email_verified_at, position, company_name, password, `role` (participant/admin/judge)                                                                                                                                                                                                                                                                                                                       |
| `award_categories`     | name, description, `applicant_type` (organization/individual), `assessment_template_id`, sort_order, `finalists_confirmed_at`, `finalists_confirmed_by`                                                                                                                                                                                                                                                                              |
| `assessment_templates` | name, description                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `scoring_criteria`     | assessment_template_id, aspect, criteria, description, weight, sort_order                                                                                                                                                                                                                                                                                                                                                            |
| `judges`               | user_id (nullable), name, position, institution, bio, photo_path, `show_on_landing` (bool), `landing_category_id` (nullable FK kategori — label "kategori juri" di landing, bukan penugasan), sort_order                                                                                                                                                                                                                             |
| `category_judges`      | award_category_id, judge_id, `is_recused`; unique(award_category_id, judge_id)                                                                                                                                                                                                                                                                                                                                                       |
| `submissions`          | `uuid`, user_id, award_category_id, initiative_title, initiative_description, `terms_accepted_at`, `confirmation_sent_at`, `paper_path`, `paper_original_name`, `statement_path` (nullable), `statement_original_name`, `paper_uploaded_at`, status, `revision_note`, `revision_deadline`, `disqualified_reason`, `stage1_score`, `stage1_rank`, `final_score`, `final_rank`, `award` (nullable); unique(user_id, award_category_id) |
| `judge_scores`         | submission_id, judge_id, scoring_criteria_id, `stage` (desk_evaluation/pitching), raw_score (0–100), notes, submitted_at; unique(submission_id, judge_id, scoring_criteria_id, stage)                                                                                                                                                                                                                                                |
| `pitching_sessions`    | award_category_id, scheduled_at, location, meeting_link                                                                                                                                                                                                                                                                                                                                                                              |
| `pitching_slots`       | pitching_session_id, submission_id, starts_at                                                                                                                                                                                                                                                                                                                                                                                        |
| `settings`             | key, value                                                                                                                                                                                                                                                                                                                                                                                                                           |

Skor agregat (`stage1_score`, `final_score`, rank) dihitung oleh service/action yang dijalankan admin ("Recalculate") dan disimpan, bukan dihitung ulang di tiap request.

## API untuk Landing

Landing **me-mirror** data (tabel lokal + `external_id`, sync terjadwal ~15 menit + tombol "Sync now" di admin landing). Kontrak ini harus stabil — perubahan field = koordinasi dengan repo Landing.

- Prefix `/api/v1/landing/*`, **read-only**, GET saja
- Auth: header `Authorization: Bearer <token>`; token statis 64 karakter di Setting `landing_api_token` (terenkripsi), dibuat/diganti/dicabut superadmin di Settings → Landing API (keputusan 2026-09-30, menggantikan `.env`). Dicek middleware `landing.token` — tidak pakai Sanctum. Token tidak dikirim/salah/belum dibuat → `401 {"message": "Unauthenticated."}`. Di Landing disimpan sebagai `ASSESSMENT_API_TOKEN`
- Rate limit 60/menit per IP (`429` bila lewat), response JSON via Eloquent API Resource
- Semua respons dibungkus key `data`; list diurutkan `sort_order`, lalu `id`
- Endpoint mengembalikan **daftar lengkap** (bukan delta) — landing menghapus/menyembunyikan record yang tidak ada lagi

| Endpoint                         | Isi                                                                                                                                                                                                                                                                                                                          |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /api/v1/landing/categories` | `id`, `name`, `description`, `applicant_type`, `sort_order`                                                                                                                                                                                                                                                                  |
| `GET /api/v1/landing/judges`     | hanya `show_on_landing = true`: `id`, `name`, `position`, `institution`, `bio`, `photo_url` (absolut, publik), `landing_category_id`, `sort_order`. **Tidak pernah** mengirim `category_judges`/`is_recused`/email/akun                                                                                                      |
| `GET /api/v1/landing/stats`      | `{ registered: int, papers_submitted: int, by_category: [{ category_id, registered, papers_submitted }], updated_at }` — `registered` = semua submission (termasuk disqualified), `papers_submitted` = `paper_uploaded_at` terisi; `by_category` memuat semua kategori (termasuk 0); `updated_at` ISO 8601; di-cache 5 menit |

Statistik per jenis industri tidak tersedia (sign up tidak menyimpan sektor). Nanti (setelah Awarding): endpoint `winners` dari field `award`.

## Fase Pengerjaan

Satu sesi = satu fitur (lihat `AGENTS.md` hasil `/newproject`). Urutan:

**Fase 1 — Registrasi live (secepatnya)**

1. Setup project: starter kit + salin aset dari Landing (tabel "Salin dari repo Landing"), `.ai/` workflow, `ci:check`, favicon & nama app, role + admin prefix
2. Auth bertema landing (login, forgot/reset password) + Sign Up multi-step (Account → Initiative → Terms) + seeder kategori
3. Email konfirmasi (queue) + halaman Upload Paper (lock, deadline, `require_statement_letter`) + halaman sukses
4. Dashboard peserta (status + panel "What's next")
5. Admin minimal: Settings (registrasi/deadline, file template, T&C, email, kontak) + Categories CRUD sederhana + Participants (Registrations & Paper Submissions) + Export Excel

**Fase 2 — Siap penjurian (sebelum 12 Okt)** 6. Verifikasi administrasi (status, revisi + tenggat, diskualifikasi, email notifikasi) 7. Assessment Templates + scoring criteria (seed dari deck) + UX template inline di form kategori 8. Judges (profil, akun, penugasan kategori, recusal) 9. Dashboard juri + skor Tahap 1 + `judging_stage` + lock CRUD 10. API untuk Landing (categories, judges, stats)

**Fase 3 — Finalis & final** 11. Agregasi (normalisasi), ranking, finalis Top 5, Score Recap 12. Pitching sessions & slot + tampilan jadwal di dashboard peserta 13. Skor Tahap 2 + Final Score + penghargaan 14. Pengumuman & undangan Awarding Night (dashboard + email)

## Backlog — Pertimbangan Teknis

> Pencatatan saja — belum diimplementasi. Kerjakan bersama fitur terkait di Fase Pengerjaan.

### Strategi Tabel Admin

- **Server-side** (pagination, sort, filter, search dikerjakan query Laravel lewat query string Inertia; di TanStack Table pakai mode manual: `manualPagination`, `manualSorting`, `manualFiltering`): Participants (tab Registrations & Paper Submissions), Score Recap, daftar submission untuk juri
- **Client-side**: master data kecil (Categories ±20, Judges ±22, Assessment Template)
- Export Excel memakai filter yang sama dengan tampilan tabel
- Inertia SSR **TIDAK** dipakai (semua halaman di balik login, tidak butuh SEO)

### Race Condition & Konkurensi

**Prioritas TINGGI** (kerjakan bersamaan dengan fitur terkait):

1. Verifikasi administrasi: update status kondisional (`where status = 'under_review'`), cek jumlah baris yang terupdate, kirim email lewat job setelah commit (`afterCommit`) dengan flag `notified_at` agar tidak terkirim ganda
2. Skor juri: `unique(submission_id, judge_id, criteria_id, stage)` + upsert
3. Validasi `judging_stage` dan deadline dilakukan di server, di dalam transaksi saat menyimpan (bukan hanya di UI): skor tidak boleh masuk saat tahap ditutup, upload tidak boleh lolos setelah deadline

**Prioritas SEDANG:**

4. Optimistic locking untuk form edit admin (kolom `version` atau cek `updated_at`; kalau berbeda tampilkan "data sudah berubah, muat ulang")
5. Kalkulasi ranking/finalis dijalankan satu kali lewat job dengan `Cache::lock`, hasilnya dibekukan setelah dikonfirmasi panitia
6. Lock CRUD kategori/template/kriteria/assignment saat `judging_stage` aktif dicek di server (sudah tercatat di bagian Kontrol Tahap)

**Ditunda (nice to have):**

- Realtime / indikator "sedang direview oleh X" (Reverb)
- Polling ringan pakai `usePoll` Inertia untuk menyegarkan daftar

## Perlu Dikonfirmasi ke Tim

- 1 email boleh registrasi > 1 kategori? (Notion: 1; deck: boleh banyak) → `max_registrations_per_user`
- Statement Letter bermaterai wajib atau tidak; format & ukuran maks Submission Paper (PPTX saja?)
- Deadline registrasi vs upload paper: sama (26 Okt) atau beda?
- Field sektor industri di sign up (kalau statistik per industri tetap mau ditampilkan di landing)
- Bobot Tahap 1 vs Tahap 2 (w1:w2)
- Mekanisme penghargaan Best Sustainability Leader (3 winner per level atau total?) dan hitungan "≥96 total winners"
- Peran Pleno: diskusi saja atau bisa mengubah hasil skor?
- Pengumuman 18 Nov (Notion) masih berlaku? (Awarding Night 20 Nov & Trip 21 Nov sudah pasti di deck update)
- Legenda warna Judges/Committee di slide timeline tidak cocok
- Bahasa isian deskripsi & rubrik, bahasa email
- Provider email & domain pengirim; domain app Assessment (untuk `registration_url` di landing & `ASSESSMENT_API_URL`)

## Lampiran — Email Konfirmasi Registrasi (Step 4)

Placeholder: `{{name}}`, `{{category}}`, `{{initiative_title}}`, `{{submission_link}}`, `{{deadline}}`, `{{contact_email}}`. Kalimat "1 email = 1 submission" dan penyebutan Statement Letter menyesuaikan keputusan TBA.

**English**

Subject: ICS Award 2026: Your registration is confirmed. Next step: submit your paper

> Dear {{name}},
>
> Thank you for registering for the Indonesia Corporate Sustainability Award (ICS Award) 2026. We have received your registration:
>
> Category: {{category}}
> Initiative: {{initiative_title}}
>
> NEXT STEP: SUBMIT YOUR PAPER
> Please upload your Submission Paper and Statement Letter using your personal submission link:
> {{submission_link}}
>
> Before you submit, please make sure that you:
>
> 1. Use the official Submission Paper Template and Statement Letter. Both can be downloaded from the submission page.
> 2. Upload your completed files before the deadline on {{deadline}}. Submissions received after the deadline cannot be considered.
>
> Please note that each email address can submit one Submission Form, and each link is valid for one submission in one category.
>
> Once you have submitted, our committee will review the completeness of your documents. You can follow your status (Under Review, Qualified, or Needs Revision) on your dashboard.
>
> If you have any questions, please contact us at {{contact_email}}.
>
> Warm regards,
> ICS Award 2026 Committee
> Olahkarsa Group, with IBCSD as Knowledge Partner

**Bahasa Indonesia**

Subjek: ICS Award 2026: Registrasi Anda berhasil. Langkah berikutnya: kirim submission paper

> Yth. {{name}},
>
> Terima kasih telah mendaftar di Indonesia Corporate Sustainability Award (ICS Award) 2026. Registrasi Anda telah kami terima:
>
> Kategori: {{category}}
> Inisiatif: {{initiative_title}}
>
> LANGKAH BERIKUTNYA: KIRIM SUBMISSION PAPER
> Silakan unggah Submission Paper dan Surat Pernyataan (Statement Letter) melalui link pengumpulan pribadi Anda:
> {{submission_link}}
>
> Sebelum mengirim, mohon pastikan Anda:
>
> 1. Menggunakan Template Submission Paper dan Statement Letter resmi. Keduanya dapat diunduh dari halaman pengiriman.
> 2. Mengunggah berkas yang sudah lengkap sebelum batas waktu pada {{deadline}}. Berkas yang diterima setelah batas waktu tidak dapat diproses.
>
> Perlu diketahui, setiap alamat email hanya dapat mengumpulkan satu Submission Form, dan setiap link hanya berlaku untuk satu submission pada satu kategori.
>
> Setelah Anda mengirim, panitia akan memeriksa kelengkapan dokumen. Status Anda (Sedang Direview, Lolos Administrasi, atau Perlu Revisi) dapat dipantau di dashboard.
>
> Jika ada pertanyaan, silakan hubungi kami di {{contact_email}}.
>
> Salam hangat,
> Panitia ICS Award 2026
> Olahkarsa Group bersama IBCSD sebagai Knowledge Partner
