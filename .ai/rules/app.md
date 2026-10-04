---
paths:
    - 'app/**'
    - 'routes/**'
---

# App

## Semua peran = `users.role` (`App\Enums\UserRole`), bukan tabel/guard terpisah

Superadmin, admin, judge, dan participant semuanya baris di `users` dengan kolom `role` (default `participant`), di-cast ke `UserRole`. Fortify sudah wired ke guard `web` → `User`, jadi jangan buat tabel `admins`/guard baru. Tabel `judges` (profil untuk landing) nanti menunjuk ke `users` lewat `user_id` nullable. `User::$attributes` meniru default kolom supaya user yang baru dibuat (mis. lewat registrasi Fortify) langsung punya `role` sebelum di-reload — kalau default kolom diubah, ubah juga di sana.

## Superadmin vs admin

`superadmin` = developer, boleh mengatur semuanya (termasuk kelola akun admin/role, Settings sistem). `admin` = tim marketing/panitia yang mengecek data dan operasional harian. Keduanya masuk panel admin (`role:superadmin,admin`); aksi yang khusus superadmin dibatasi per fitur (route/middleware `role:superadmin` atau Gate), diputuskan di fitur yang bersangkutan — jangan anggap admin setara superadmin.

## Gate peran lewat middleware `role:<peran>`, bukan middleware per peran

`EnsureUserHasRole` (alias `role` di `bootstrap/app.php`) menerima satu atau lebih peran: `role:admin`, `role:judge`, `role:admin,judge`. Jangan bikin `EnsureUserIsAdmin`/`EnsureUserIsJudge` terpisah seperti di Landing.

## Panel admin di prefix yang tidak mudah ditebak, bukan `/admin`

Semua route admin masuk `routes/admin.php` (didaftarkan lewat `withRouting(then:)` di `bootstrap/app.php`) di bawah `prefix(config('admin.prefix'))` — nilainya dari `ADMIN_PANEL_PREFIX` di `.env`, jangan hardcode di tempat lain. Nama route tetap `admin.*`, jadi Wayfinder/`routeIs()` tidak perlu tahu path aslinya. Halaman login tidak disembunyikan. Jangan buat route group admin kedua. Route juri ada di `routes/judge.php` (prefix `judge`, name `judge.*`, `role:judge`), didaftarkan di `then:` yang sama.

## Nilai yang masih TBA disimpan di `Setting`, bukan hardcode

Model `Setting` (tabel `settings`, `Setting::get($key, $default)` / `Setting::put($key, $value)`) menyimpan deadline, jadwal tahap, bobot, template email, dll. Key yang dipakai lebih dari satu fitur tidak diberi prefix (mis. `is_registration_open`, `contact_email`) — pakai nama key dari bagian "Settings" di `ASSESSMENT-BRIEF.md`.

## Expose URL file lewat accessor Eloquent dari kolom `*_path`

Model dengan kolom `*_path` yang disajikan ke frontend dapat accessor `*Url` modern (`Attribute::make(get: ...)`) dengan PHPDoc `@return Attribute<string|null, never>` (Larastan menolak tanpa generics). Paper peserta disimpan di disk privat — URL-nya lewat route ber-auth/signed URL, bukan `Storage::url()` publik.

## `nullsafe.neverNull` palsu dari Larastan pada akses lewat parameter union-typed

Kalau Larastan menyuruh mengganti `?->` jadi `->` padahal properti memang nullable di skema, jangan dituruti (bisa crash null di produksi) dan jangan di-`@phpstan-ignore`. Tulis ulang sebagai cabang eksplisit: `$rank = ...; return $rank === null ? $fallback : $rank->value;`.

## Tanggal di `Setting` = akhir hari WIB, app tetap UTC

Nilai tanggal `YYYY-MM-DD` (`paper_deadline`, `registration_deadline`, dst.) dibaca lewat `Setting::endOfDay($key)` → 23:59:59 `Setting::EVENT_TIMEZONE` (`Asia/Jakarta`). Jangan ubah `config/app.php` timezone atau parse manual dengan `Carbon::parse()` (jadi tengah malam UTC = 07:00 WIB). Frontend memformat ISO string dengan `timeZone: 'Asia/Jakarta'` + sufiks "WIB". (Upload Paper, 2026-09-29.)

## Verifikasi email peserta = link submission, bukan email Fortify

`User::sendEmailVerificationNotification()` di-override: user yang punya submission menerima `SubmissionConfirmation` (lewat `SendSubmissionConfirmation`), user tanpa submission (admin/juri) tetap email verifikasi default. Listener `Registered` dan tombol Resend (`verification.send`) otomatis ikut jalur ini — jangan tambah email verifikasi terpisah. `GET /submissions/{uuid}` sengaja di group `auth` TANPA `verified` karena membukanya yang menandai email terverifikasi. (Upload Paper, 2026-09-29.)

## Template email dari `Setting`: escape nilai, lalu substitusi placeholder

Subject + body (markdown) email yang bisa diedit admin disimpan di `Setting` dengan placeholder `{{name}}` dst. Di body, setiap nilai di-`e()` sebelum `strtr` (nama peserta bisa berisi HTML); subject plain text tidak di-escape. Link ditulis sebagai `[url](url)` — renderer markdown mail Laravel tidak meng-autolink URL polos. View markdown cukup `{!! $body !!}` di dalam `<x-mail::message>`, tanpa indentasi. Reference: `app/Mail/SubmissionConfirmation.php`. (Upload Paper, 2026-09-29.)

## Upload yang mengunci record: simpan file dulu, cek aturan di transaksi, hapus file bila ditolak

Pola `App\Actions\Submissions\SubmitPaper`: file disimpan ke disk privat `local` (`submissions/{uuid}/`) sebelum transaksi (I/O jangan menahan row lock), lalu di `DB::transaction` baris di-`lockForUpdate` dan lock/deadline dicek ulang di situ; `catch (Throwable)` menghapus file yang baru disimpan lalu rethrow. Aturan bisnis ditolak sebagai `ValidationException` pada key field (`paper`) supaya tampil di field form. (Upload Paper, 2026-09-29.)

Revisi (`needs_revision`) memakai action yang sama. Status buka/kunci selalu dibaca lewat `Submission::isRevisionOpen()` / `isPaperLocked()`, jangan dari `paper_uploaded_at` saja. Revisi dibatasi `revision_deadline`, bukan `paper_deadline`. Hanya file yang diunggah ulang yang diganti (`StorePaperRequest` → `required_without`), dan file lama baru dihapus SETELAH transaksi commit. `revision_note`/`revision_deadline` dibiarkan sebagai riwayat; keputusan verifikasi berikutnya yang menimpanya. (Upload ulang revisi, 2026-09-30.)

## `/dashboard` = pintu masuk semua peran, bercabang di `DashboardController`

Fortify `home` tetap `/dashboard`. `DashboardController` me-redirect superadmin/admin ke `admin.dashboard`, juri ke `judge.dashboard`, dan merender dashboard peserta untuk sisanya. Jangan ubah `fortify.home` per peran atau bikin `LoginResponse` kustom — cabangkan di controller ini. (Dashboard peserta, 2026-09-29.)

## Jadwal tahap `timeline_*` = teks tampilan, bukan tanggal

`timeline_administrative_selection`, `timeline_desk_evaluation`, `timeline_finalists_announcement`, `timeline_pitching`, `timeline_awarding_night` berisi teks bebas ("12 – 30 October 2026") untuk panel "What's next", jadi tidak lewat `Setting::endOfDay()`. Jangan dipakai untuk logika buka/tutup tahap — itu tugas `judging_stage` dan key tanggal `YYYY-MM-DD`. Judul & deskripsi tahap ada di `DashboardController::TIMELINE`. (Dashboard peserta, 2026-09-29.)

## File peserta diunduh lewat `SubmissionFileController` + ability `downloadFiles`, bukan URL disk

`SubmissionFileController` menyajikan file dari disk privat `SubmitPaper::DISK` dengan nama asli, lewat dua route: `submissions.files.show` (peserta) dan `admin.participants.files.show` (group admin). Route ketiga `judge.submissions.files.show` untuk juri. Semuanya di-authorize dengan `SubmissionPolicy::downloadFiles` (pemilik, superadmin/admin, atau lolos `score()` = juri yang ditugaskan dan tidak recused). Jangan memperluas `view` untuk admin/juri, karena `view` membuka halaman submission yang menandai email terverifikasi. Jangan expose `paper_path` lewat `Storage::url()`. (Dashboard peserta; diperbarui di Participants, 2026-09-29.)

## Admin Settings: satu controller + FormRequest + page per sub-halaman

`app/Http/Controllers/Admin/Settings/{Registration,File,Email}SettingsController` (`edit`/`update`, route `admin.settings.<grup>.*`). `edit()` membaca sekaligus lewat `Setting::many($keys)`; `update()` menulis `Setting::put` di `DB::transaction`, flash `toast`, `to_route(...edit)`. Sub-halaman baru (Judging, Landing API) ikut pola ini — jangan satu endpoint untuk semua. Template unduhan di disk `public` (`settings/`), disimpan sebagai `{field}_path` + `{field}_name`; file lama dihapus setelah commit, file baru dihapus bila transaksi gagal. Route upload pakai `POST` (multipart), bukan `PUT`. (Admin Settings, 2026-09-29.)

## Filter tabel admin server-side: satu FormRequest + satu scope, dipakai tabel DAN export

Query string tabel (`search`, `category`, `status`, `sort`, `per_page`) divalidasi `Admin\ParticipantFilterRequest`. Nilai `sort` di-whitelist dari `SORTABLE`, dengan awalan `-` untuk descending. `filters($defaultSort)` mengembalikan filter beserta default-nya, lalu diteruskan ke scope `Submission::filteredForAdmin()` (eager load, search `whereLike`, filter, sort). Sort kolom relasi memakai subquery `orderBy`, bukan join, ditambah tie-breaker `id`. Index (`paginate()->withQueryString()->through()`) dan export memanggil scope yang sama supaya Excel = tampilan tabel. Score Recap / daftar juri nanti ikut pola ini. (Participants, 2026-09-29.)

## Export Excel = `maatwebsite/excel` v4 di `app/Exports/`

Buat dengan `php artisan make:export`. Implement `FromQuery` + `WithHeadings` + `WithMapping` + `ShouldAutoSize`. Di v4, signature bertipe native: `map(mixed $row): array`, dan `query()` mengembalikan `Builder`. Query wajib punya `ORDER BY` unik (tie-breaker `id`); tanpa itu chunk export bisa menduplikasi atau melewatkan baris. Tanggal diformat WIB (`Setting::EVENT_TIMEZONE`). Controller: `Excel::download($export, "<nama>-{Ymd-Hi WIB}.xlsx")`. Test: `Excel::fake()` + `Excel::assertDownloaded($name, fn ($export) => ...query()/map()...)`. (Participants, 2026-09-29.)

## Buka/tutup registrasi = `RegistrationStatus::current()`, bukan baca setting manual

`App\Enums\RegistrationStatus::current()` menggabungkan toggle `is_registration_open` (default `'1'`), `Setting::startOfDay('registration_opens_at')` (00:00 WIB), dan `Setting::endOfDay('registration_deadline')`. Tanggal kosong = tidak membatasi. Endpoint sign-up memanggil `ensureRegistrationIsOpen()` (trait `RegistrationValidationRules`) → `ValidationException` pada key `registration`; `Fortify::registerView` merender `auth/registration-closed`. Fitur baru yang menerima pendaftaran (mis. "Register another initiative") wajib lewat gate yang sama. Tanggal "mulai" pakai `startOfDay`, bukan `endOfDay`. (Gate registrasi, 2026-09-29.)

## Keputusan panitia atas submission: update kondisional + job dengan klaim `notified_at`

Pola `App\Actions\Submissions\VerifySubmission`: di `DB::transaction`, `Submission::whereKey()->where('status', <status asal>)->update([...])`; hasil `0` baris = sudah diputuskan orang lain → `ValidationException` (key `decision`). Detail yang tak relevan dengan keputusan di-set `null`, `notified_at` direset. Email dikirim oleh job `SendVerificationDecision` (`dispatch()->afterCommit()`), yang mengklaim `notified_at` secara atomik (`whereNull('notified_at')->update(...)`, `0` → return) sebelum `Mail::send` dan mengembalikannya ke `null` bila kirim gagal. Mailable-nya TIDAK `ShouldQueue` karena sudah di dalam job. Pakai pola ini untuk pengumuman finalis/pemenang nanti. (Verifikasi administrasi, 2026-09-30.)

## Carbon ber-timezone WIB tidak dikonversi saat disimpan — baik lewat builder maupun atribut model

`Model::query()->...->update(['kolom' => $carbon])` dan assignment atribut `$model->kolom = $carbon` (cast `datetime`) sama-sama memformat Carbon apa adanya, jadi jam WIB tertulis sebagai UTC (diverifikasi 2026-09-30: `09:00 Asia/Jakarta` → tersimpan `09:00:00`). Tanggal/jam WIB wajib `->setTimezone(config('app.timezone'))` sebelum ditulis. Reference: `VerifySubmissionRequest::details()`, `SavePitchingSchedule::wibDateTime()`. (Verifikasi administrasi; dikoreksi di Pitching, 2026-09-30.)

## Route model binding `Submission` = `uuid`, juga di route admin

`getRouteKeyName()` = `uuid`, jadi Wayfinder admin (`admin.participants.verification.update`, `files.show`) juga menerima uuid. Row tabel admin yang memicu aksi per submission wajib mengirim `uuid`, bukan hanya `id`. (Verifikasi administrasi, 2026-09-30.)

## Baris anak yang akan direferensikan skor: sinkron by `id`, jangan hapus-lalu-buat-ulang

`App\Actions\AssessmentTemplates\SaveAssessmentTemplate` menyimpan kriteria template dengan mencocokkan `id` dari payload: baris yang ada di-update, baris tanpa `id` dibuat, baris yang tidak dikirim dihapus, lalu `sort_order` = urutan payload. Tujuannya agar `scoring_criteria.id` tetap stabil untuk `judge_scores`. `criteria.*.id` divalidasi `exists` terhadap template yang sama (`prohibited` saat create). Payload nested dibaca lewat method bertipe di FormRequest (`templateData()`, memakai `$this->string("criteria.{$i}.x")`/`integer()`), bukan `validated()`, supaya shape array lolos PHPStan. (Assessment Templates, 2026-09-30.)

## `withSum`/`withAvg` di MySQL mengembalikan string

Agregat relasi (`criteria_sum_weight`) dikembalikan MySQL sebagai string desimal (`"100"`), jadi perbandingan ketat di frontend (`=== 100`) gagal diam-diam. Tambah `->withCasts(['<kolom>_sum_<field>' => 'integer'])` di query. Reference: `Admin\AssessmentTemplateController::index`. (Assessment Templates, 2026-09-30.)

## Akun juri dibuat admin lewat `SaveJudge`, bukan registrasi/undangan

`App\Actions\Judges\SaveJudge` membuat/memperbarui user role `judge` dari form Judges: `email_verified_at` langsung diisi (juri tidak melewati verify-email), `name`/`position`/`company_name` disalin dari profil, password hanya diganti bila diisi. Penugasan disimpan dengan `categories()->sync([id => ['is_recused' => …]])`. Menghapus juri (hanya bila tanpa penugasan) ikut menghapus akunnya — jangan tinggalkan user judge tanpa profil. Update memakai route `POST` (multipart foto), bukan `PUT`. (Judges, 2026-09-30.)

## Password juri bisa dilihat ulang — khusus superadmin, terenkripsi

Keputusan user: salinan password yang diset admin disimpan di `judges.account_password` (cast `encrypted`, `#[Hidden]`), di samping hash di `users.password`. Nilainya hanya keluar lewat prop `Inertia::optional(...)` `accountPassword` di `admin.judges.edit`; closure-nya mengecek `role === Superadmin` (admin dapat `null`) dan `Hash::check` terhadap password user — gagal = juri sudah ganti password → kolom di-`null`-kan. Jangan kirim lewat prop biasa, index, atau export. Mengganti `APP_KEY` membuat salinan lama tak terbaca (login tetap jalan). (Judges, 2026-09-30.)

## Password akun admin bisa dilihat ulang — khusus superadmin, terenkripsi

Keputusan user: pola sama dengan juri, tapi salinannya di `users.account_password` (cast `encrypted`, `#[Hidden]`, tidak fillable). Diisi hanya oleh `SaveAdminAccount` saat password diset (create/reset); superadmin dari `DatabaseSeeder` tidak punya salinan. Karena form edit = Sheet di index, nilainya keluar lewat prop `Inertia::optional` `revealedPassword` di `admin.accounts.index` untuk akun di query `?reveal=<id>` (client: `router.reload({ only, data, preserveUrl: true })`). Prop membawa `account_id` supaya Sheet akun lain tidak menampilkan nilai basi. Akun trashed/juri/peserta → `null`; `Hash::check` gagal → kolom di-`null`-kan. Row index hanya membawa `has_account_password`. (Lihat ulang password admin, 2026-10-01.)

## `@property` tanggal di model = `CarbonImmutable`

`AppServiceProvider` memakai `Date::use(CarbonImmutable::class)`, jadi `now()` dan cast `datetime` menghasilkan `CarbonImmutable`. Docblock `@property Carbon|null` (Illuminate) membuat PHPStan menolak assignment `$model->kolom = now()`. Untuk kolom tanggal yang di-assign dari kode, tulis `@property CarbonImmutable|null` (contoh: `User::$email_verified_at`). (Judges, 2026-09-30.)

## Test prop `Inertia::optional`/partial reload lewat `reloadOnly`

Uji dengan `->assertInertia(fn ($page) => $page->missing('prop')->reloadOnly('prop', fn ($reload) => $reload->where('prop', …)))`. Mengirim header `X-Inertia-Partial-*` manual di `get()` tidak mengembalikan JSON (konflik versi aset → 409). Reference: `tests/Feature/Admin/JudgesTest.php`. (Judges, 2026-09-30.)

## Template paper peserta = template kategori, fallback ke template global

Setiap URL template paper untuk peserta di-resolve di server: `$category->paper_template_url ?? Setting::publicFileUrl('submission_template_path')`. URL global dihitung sekali per request (bukan per kategori), lalu dikirim per submission (`paperTemplateUrl`) atau per kategori (`paper_template_url` di Sign Up). Jangan kirim lagi prop page `submissionTemplateUrl` yang terpisah. File disimpan di disk `public` (`AwardCategory::PAPER_TEMPLATE_DISK`, folder `categories/`) lewat `SaveCategory`. `statementLetterTemplateUrl` tetap global. (Paper Template per kategori, 2026-09-30.)

## Kolom file `*_path` tidak fillable, hanya diisi lewat action

`paper_template_path`/`_name` (dan pola serupa) sengaja tidak masuk `#[Fillable]`; yang boleh mengisinya hanya action (`SaveCategory`, `SaveJudge`), supaya file lama bisa dihapus setelah commit. Akibatnya, `$model->update(['*_path' => …])` diabaikan tanpa error. Di test, isi kolom lewat factory (`create([...])`, unguarded) atau `forceFill()->save()`. (Paper Template per kategori, 2026-09-30.)

## Skor juri: `SaveJudgeScores` dengan baris `judging_stage` dikunci

Tahap aktif selalu dibaca lewat `JudgingStage::current()` (enum `closed`/`desk_evaluation`/`pitching`, nilai yang sama dipakai `judge_scores.stage`), jangan dari `Setting::get()` langsung. Penyimpanan skor (`App\Actions\Judging\SaveJudgeScores`) memanggil `current(lock: true)` di awal transaksi, lalu mengecek ulang `assignedToJudge` (qualified + ditugaskan + tidak recused) dan memastikan id kriteria milik template kategori. Pelanggaran → `ValidationException` key `scores`. Skor disimpan dengan `JudgeScore::upsert` pada unique (submission, judge, criterion, stage). Submit wajib mengisi semua kriteria. Skor yang sudah di-submit boleh di-submit ulang ("Update scores") selama tahap aktif, tapi request draft ditolak. `JudgingSettingsController::update` juga mengunci baris yang sama sebelum menulis. Stage yang bisa dibuka admin dibatasi `JudgingStage::selectable()` (sejak Skor Tahap 2 termasuk `Pitching`). Progres/status juri memakai scope `withJudgeProgress` + `Submission::judgeScoringStatus()` (`submitted` = ada baris dengan `submitted_at`). Data juri lain tidak pernah dikirim ke juri. (Dashboard juri, 2026-09-30.)

## Lock setup penjurian = Gate `manage-judging-setup` + middleware `judging.unlocked`

Admin tidak boleh mengubah kategori, assessment template/kriteria, penugasan/recusal juri, atau menghapus juri saat `JudgingStage::current()` ≠ `closed`; superadmin selalu boleh. Aturannya satu Gate di `AppServiceProvider` (`Response::deny` membawa pesan). Route mutasi memasang `judging.unlocked:<route index>` (`EnsureJudgingSetupIsEditable`: toast error + redirect ke index) lewat `middlewareFor`. Lock yang hanya mengenai sebagian form (penugasan juri) dicek di `FormRequest::after()` dengan membandingkan payload dengan data tersimpan. Frontend menerima prop `canManageJudgingSetup` per halaman, bukan shared prop. Endpoint setup baru (mis. slot pitching) wajib ikut Gate yang sama. (Lock CRUD penjurian, 2026-09-30.)

## Data yang sudah dinilai dilindungi di action, untuk semua peran

FK `judge_scores` → `scoring_criteria`/`judges` = `restrict`, jadi penghapusan harus dicegah sebelum query, bukan menunggu exception. Guard ditulis di dalam transaksi action (`SaveAssessmentTemplate`, `SaveCategory`, `SaveJudge`) → `ValidationException` pada key field (`criteria`, `assessment_template_id`, `assignments`); di `destroy()` → toast error. Juri yang sudah menilai boleh di-recuse (skor tetap disimpan), tapi tidak boleh dilepas dari kategorinya. Recap Fase 3 wajib mengabaikan skor juri `is_recused`. Helper: `AwardCategory::judgeScores()` (through submissions), `ScoringCriterion::scores()`, `Judge::scoredCategoryIds()`. (Lock CRUD penjurian, 2026-09-30.)

## Passkey dihapus; `/` = redirect ke login

Keputusan user: passkey (bawaan starter kit Fortify) tidak dipakai. `Features::passkeys`, trait `PasskeyAuthenticatable`, route `.well-known/passkey-endpoints`, komponen `passkey-*`, dan npm `@laravel/passkeys` sudah dihapus, lalu tabel di-drop lewat migrasi baru (migrasi create asli tetap ada karena sudah jalan di produksi). Jangan aktifkan lagi. Route `home` = `Route::redirect('/', '/login')`; user yang sudah login diteruskan middleware `guest` ke `/dashboard`. Nama `home` dipertahankan untuk Wayfinder `home()` dan redirect logout, jadi jangan buat halaman landing di `/`, karena landing ada di repo terpisah. (Branding & auth polish, 2026-09-30.)

## Preview file peserta: `SubmissionFilePreviewController` shared + `FilePreviewKind`, Office lewat signed URL relatif

`App\Http\Controllers\SubmissionFilePreviewController` (authorize `downloadFiles`) dipakai dua route: `admin.participants.files.preview` dan `judge.submissions.files.preview` (juri lolos lewat `score()`: ditugaskan, tidak recused, tahap `forJudges()`). Penyajian dari `FilePreviewKind::fromFileName()`: PDF/gambar → `$disk->response(..., 'inline')`, Office → redirect ke Office Online (`OFFICE_VIEWER_URL?src=`) dengan signed URL baru ke `submissions.files.shared`, sisanya → download. Route shared sengaja tanpa `auth` (Microsoft yang mengambil file); otorisasinya signature 10 menit (`SHARED_LINK_MINUTES`) yang hanya dibuat endpoint preview. Pakai `URL::temporarySignedRoute(..., absolute: false)` + `url()` dan middleware `signed:relative`, supaya signature tidak gagal di belakang proxy HTTPS. Jangan kirim signed URL lewat props (kedaluwarsa kalau halaman lama terbuka). Props file ke frontend = bentuk `SubmittedFile` `{name, url, preview_url, preview_kind}` (lihat `PaperSubmissionController`, `Judge\SubmissionController::show`). Path/nama file dibaca lewat `Submission::submittedFile($file)`. Preview Office tidak bisa diuji di localhost. (Preview dokumen admin, 2026-09-30; dibuka untuk juri 2026-10-01.)

## Login Google = khusus peserta yang sudah terdaftar, tanpa 2FA

Keputusan user: `Auth\GoogleLoginController` (Socialite, route `auth.google.*` di group `guest`) hanya memasukkan user role `participant` yang emailnya sudah ada. Google tidak pernah membuat akun (Sign Up tetap lewat wizard + gate registrasi). Admin/superadmin/juri ditolak dengan pesan yang sama, tanpa menyebut perannya. Email wajib `email_verified` dari Google. `email_verified_at` yang kosong diisi, dan 2FA sengaja dilewati. Penolakan = `to_route('login')->withErrors(['google' => …])`, dibaca halaman login lewat `usePage().props.errors`, bukan render prop `<Form>`. Test memakai `Socialite::fake('google', $user)`; closure yang melempar `InvalidStateException` mensimulasikan state gagal. (Login via Google, 2026-09-30.)

## API Landing: `routes/api.php` manual + token terenkripsi di `Setting`

Keputusan user: API read-only untuk repo Landing (`/api/v1/landing/*`, nama route `api.v1.landing.*`) didaftarkan lewat `api:` di `bootstrap/app.php`, tanpa `install:api`, karena perintah itu memasang Sanctum. Auth = Bearer token statis di Setting `landing_api_token` (terenkripsi lewat `Setting::putEncrypted`/`getEncrypted`, gagal decrypt = null), dicek middleware `landing.token` (`EnsureLandingApiToken`, `hash_equals`, token kosong = API mati → 401 JSON). Throttle `landing-api` (60/menit per IP) dipasang sebelum cek token. Token hanya dikelola superadmin di Settings → Landing API dan hanya dikirim lewat prop `Inertia::optional`. Kontraknya adalah `ASSESSMENT-BRIEF.md` § "API untuk Landing" (respons dibungkus `data`, daftar lengkap). Mengubah atau menghapus field wajib koordinasi dengan repo Landing. URL file di resource dibungkus `url()`, karena disk `public` bisa mengembalikan path relatif. (API untuk Landing, 2026-09-30.) Keputusan user (2026-10-05): `judges` mengirim `landing_category_ids` = id kategori penugasan `category_judges` yang tidak recused (eager load ber-constraint di controller), karena Landing menampilkan semua kategori yang dijuri. Flag `is_recused` dan kategori recused tetap tidak pernah dikirim; `landing_category_id` (label lama) tetap dikirim untuk kompatibilitas.

## Score Recap Tahap 1 = `CalculateStageOneScores`, hasil disimpan di `submissions.stage1_*`

Keputusan user: Recalculate berjalan sinkron di controller (tanpa queue), dibungkus `Cache::lock(LOCK_KEY)`. Kalau lock sedang dipegang → `ValidationException` key `recalculate` → toast error. Input yang dihitung hanya skor desk evaluation yang sudah di-submit, dari submission `qualified`, oleh juri yang tidak `is_recused` di kategori itu. Weighted per juri = Σ raw × weight / 100. Normalisasi: z per juri (mean/σ populasi lintas kategori) lalu diskalakan balik `μ_global + z × σ_global`, supaya skor juri yang fallback (sampel < `normalization_min_sample` atau σ = 0) tetap satu skala 0–100. Rank per kategori: skor desc, tie-break raw desc, sisa seri mendapat rank yang sama (1, 2, 2, 4); submission tanpa skor tidak mendapat rank. Setiap recalculate mengosongkan semua `stage1_*` dulu. Halaman, export, dan fitur finalis membaca kolom tersimpan, jangan menghitung ulang per request. Kolom `stage1_*` tidak fillable; di test isi lewat `forceFill`. (Score Recap, 2026-09-30.)

## Checkbox di `<Form>` Inertia: rule `nullable|boolean`, bukan `required`

Checkbox yang tidak dicentang tidak ikut terkirim, jadi `required` membuat mematikan opsi selalu gagal validasi. Pakai `['nullable', 'boolean']` dan baca dengan `$request->boolean(...)`. Reference: `is_registration_open`, `normalization_enabled`. (Score Recap, 2026-09-30.)

## Finalis = status `finalist` + pembekuan per kategori, dalam satu transaksi

Keputusan user: tidak ada kolom `is_finalist`, karena status `finalist` sudah jadi sumber kebenaran. Pilihan finalis hanya ada di client sampai Confirm. `App\Actions\Judging\ConfirmFinalists` memegang `Cache::lock(CalculateStageOneScores::LOCK_KEY)`, menolak konfirmasi saat desk evaluation aktif, lalu mengklaim kategori dengan update kondisional (`whereNull('finalists_confirmed_at')`). Setelah itu ia mengecek ulang bahwa semua id qualified + ranked + milik kategori itu (maks `MAX_FINALISTS` = 5, _Incomplete_ tetap boleh), dan mengubah status dalam transaksi yang sama. Recalculate ditolak selama ada kategori dengan `finalists_confirmed_at`. `ReopenFinalists` (route `role:superadmin`) mengembalikan status ke `qualified` dan ditolak bila sudah ada skor pitching. Fitur pitching/pengumuman membaca finalis dari status dan kategori yang sudah dikonfirmasi. (Finalis Top 5, 2026-09-30.)

## Query "submission yang dinilai" = `SubmissionStatus::rankable()`, bukan `Qualified` saja

Submission yang menjadi finalis tetap harus tampil di Score Recap (`scoreRecapForAdmin`), daftar/progres juri (`assignedToJudge`), dan fitur pitching nanti. Pakai `whereIn('status', SubmissionStatus::rankable())` (`[Qualified, Finalist]`), karena `where('status', Qualified)` membuat finalis hilang tanpa error. Pengecualian yang disengaja: `CalculateStageOneScores` dan `ConfirmFinalists` tetap hanya memakai `Qualified`. Keduanya aman karena Recalculate diblokir selama ada finalis yang dikonfirmasi. (Finalis Top 5, 2026-09-30.)

## Jadwal pitching = `SavePitchingSchedule`, satu sesi per kategori

Keputusan user: `pitching_sessions` unique per `award_category_id`, `pitching_slots` unique per `submission_id`. Jam slot diisi manual (HH:MM WIB pada tanggal sesi), dan kosong berarti belum dijadwalkan. `App\Actions\Judging\SavePitchingSchedule` mengunci baris kategori, menolak kategori yang belum `finalists_confirmed_at` (key `schedule`) dan submission yang bukan finalis kategori itu (key `slots`), lalu menyinkronkan slot by `submission_id` (tanpa jam → dihapus). `ReopenFinalists` menghapus slot kategori itu, tapi sesinya tetap. Route `update`/`destroy` di `admin.pitching.*` memakai `judging.unlocked:admin.pitching.index`. Peserta menerima `pitching` di dashboard hanya bila status `finalist` + finalis kategori sudah diumumkan (`Announcement::Finalists`) + sesi ada. Jadwal ikut email pengumuman finalis; perubahan setelahnya tidak memicu email. (Pitching, 2026-09-30; diperbarui di Pengumuman, 2026-10-01.)

## `User` pakai SoftDeletes, tapi hanya akun admin/superadmin yang di-soft delete

Keputusan user: akun admin/superadmin yang dihapus di Admin Accounts di-soft delete (tidak bisa login, sesi lama putus karena user provider melewati baris trashed) dan bisa di-restore. Semua jalur hapus lain wajib `forceDelete()` supaya email bisa dipakai lagi dan FK `cascade`/`nullOnDelete` tetap jalan: hapus juri (`JudgeController::destroy`), hapus akun peserta (Settings → Delete account). Relasi riwayat ke user (`Submission::reviewer`, `AwardCategory::finalistsConfirmer`) memakai `->withTrashed()`. `Rule::unique(User::class)` ikut menghitung baris trashed, jadi email akun terhapus tetap "terpakai". (Kelola akun admin, 2026-09-30.)

## Akun admin dikelola di Admin Accounts, minimal satu superadmin aktif

`Admin\AdminAccountController` (route `admin.accounts.*`, `role:superadmin`, `restore` pakai `->withTrashed()`) hanya menyentuh user ber-role `UserRole::adminRoles()`; akun juri/peserta → 404. `SaveAdminAccount`/`DeleteAdminAccount` menolak ubah role/hapus diri sendiri, lalu `EnsureSuperadminRemains` me-`lockForUpdate` SEMUA baris superadmin (termasuk target, supaya dua superadmin tidak bisa saling hapus bersamaan) sebelum menurunkan/menghapus superadmin. Admin/superadmin tidak bisa Delete account dari profil (`ProfileDeleteRequest::authorize()` → 403, tombol disembunyikan). Password admin bisa dilihat ulang superadmin — lihat § Password akun admin. (Kelola akun admin, 2026-09-30.)

## Tahap yang dilihat juri = `JudgingStage::forJudges()`, daftar submission = `assignedToJudge($judge, $stage)`

Halaman juri (Overview, My Submissions, scoring) dan `SubmissionPolicy::score`/`downloadFiles` memakai `JudgingStage::forJudges()`: tahap aktif, atau saat `closed` → `Pitching` begitu ada kategori `finalists_confirmed_at`, selain itu `DeskEvaluation`. `assignedToJudge($judge, $stage)`: desk evaluation = `rankable()`, pitching = status `finalist` + kategori terkonfirmasi. Jangan hardcode `JudgingStage::DeskEvaluation` di kode juri. Akibatnya selama pitching juri hanya bisa membuka/mengunduh paper finalis. (Skor Tahap 2, 2026-09-30.)

## Skor juri dibekukan per kategori: `AwardCategory::isScoringFrozen($stage)`

Keputusan: Settings → Judging tetap boleh membuka lagi Desk Evaluation/Pitching (untuk kategori yang belum beku). Satu-satunya cek beku adalah `AwardCategory::isScoringFrozen($stage)`: desk evaluation beku saat `finalists_confirmed_at`, pitching beku saat `awards_confirmed_at`. `SaveJudgeScores` menolaknya (key `scores`), dan UI juri menerima `is_frozen`/`isFrozen` (tombol "View" + banner "Desk evaluation is final"/"Pitching is final"). Jangan menulis ulang kondisinya di tempat lain, dan jangan pindahkan larangan ini ke validasi Settings. (Skor Tahap 2, 2026-09-30; pitching ditambah 2026-10-01.)

## Kalkulasi skor tahap = `StageScoreCalculator`, satu untuk semua tahap

Weighted per juri, normalisasi (z per juri lintas kategori dalam tahap itu, diskalakan balik ke μ/σ global, fallback raw), rank per kategori, dan reset + tulis kolom hanya ada di `App\Actions\Judging\StageScoreCalculator::calculateAndStore($stage, $submissionsQuery, 'stage1'|'stage2')`. Action per tahap (`CalculateStageOneScores`, `CalculateStageTwoScores`) hanya memegang `Cache::lock` sendiri (`score-recap:stage-1`/`-2`), guard tahapnya, dan query submission yang eligible (Stage 2 = finalis kategori terkonfirmasi, hanya skor `pitching`). Konstanta `NORMALIZATION_*`/`DEFAULT_MIN_SAMPLE` ada di `StageScoreCalculator`, dan kedua tahap memakai setting yang sama. Kolom `stage2_*` tidak fillable; di test isi lewat `forceFill`. (Skor Tahap 2, 2026-09-30.)

## Score Recap per tahap = filter `stage` (`ScoreRecapStage`), pindah tab dengan full visit

`ScoreRecapFilterRequest::filters()['stage']` memakai enum `App\Enums\ScoreRecapStage` (`desk_evaluation`/`pitching`/`final`, default desk evaluation), bukan `JudgingStage`, karena tab Final bukan tahap penjurian. `prefix()` (`stage1`/`stage2`/`final`) dan `listsFinalists()` dipakai `scoreRecapForAdmin`, controller (`recapRow()`, bentuk row per tab ditulis eksplisit karena akses kolom dinamis `{"{$prefix}_rank"}` membuat PHPStan gagal), dan `ScoreRecapExport` (heading & nama file per tab). Tab baru ditambahkan di enum ini. Recalculate Stage 2 = route `admin.score-recap.stage-2.recalculate`. Toggle tab di frontend memakai `router.get(scoreRecap.index({ query: { stage } }))`, bukan `setFilter` dari `useServerTable`, karena partial reload hanya memuat `submissions`/`filters` sehingga `calculatedAt`/`candidates` jadi basi. (Skor Tahap 2, 2026-09-30.)

## Final Score & award: dihitung bersama Stage 2, dikonfirmasi panitia per kategori

Keputusan user: `CalculateStageTwoScores` menulis `stage2_*` lalu `final_*` dalam lock yang sama (satu tombol Recalculate untuk tab Stage 2 dan Final). Final = `stage1_score × w1/100 + stage2_score × w2/100`, dengan bobot dibaca lewat `CalculateStageTwoScores::weights()` (Setting `stage_1_weight`/`stage_2_weight`, default 50/50). Final hanya dihitung untuk finalis yang punya kedua skor. Rank memakai `StageScoreCalculator::rankPerCategory` tanpa tie-break, jadi seri mendapat rank yang sama dan panitia yang memutuskan. Award (`submissions.award`, enum `Award`) hanya disarankan oleh rank (`Award::suggestedFor`). `ConfirmAwards` (lock Stage 2, pitching harus ditutup, klaim kondisional `awards_confirmed_at`, kuota `Award::quota()` 1/2/2, penerima = finalis kategori ber-`final_rank`) yang menyimpannya. Selama ada kategori dengan award terkonfirmasi, Recalculate Stage 2 ditolak dan bobot tidak bisa diubah (`UpdateJudgingSettingsRequest::after()`). `ReopenAwards` (route `role:superadmin`) mengosongkan award kategori itu. Peserta baru melihat `award` setelah `Announcement::Winners` (lihat rule Pengumuman). (Skor Tahap 2 (2/2), 2026-10-01.)

## Angka Admin Overview = `BuildAdminOverview`, filter per tahap = `scoredInStage()`

Semua angka halaman Overview admin dihitung di `App\Actions\Overview\BuildAdminOverview`, satu method per section, dipanggil dari `Admin\DashboardController`. Item "Needs attention" baru ditambahkan di `attention()` sebagai `{key, count, label, href}`; item dengan count 0 tidak dikirim. Progres juri dan progres skor per kategori memakai matriks yang sama (`scoring()`, dihitung sekali per request, tahap = `JudgingStage::forJudges()`, juri recused tidak dihitung). Submission yang dinilai di suatu tahap selalu lewat scope `Submission::scoredInStage($stage)` (dipakai juga oleh `assignedToJudge`); jangan menyalin kondisi status/finalis ke tempat lain. Hanya tren dan aktivitas terbaru yang memakai `Inertia::defer` (grup `activity`). (Admin Overview, 2026-10-01.)

## Agregasi per hari = tanggal WIB, dikelompokkan di PHP

Hitungan per hari (mis. tren registrasi Overview) mengambil kolom timestamp, lalu `->setTimezone(Setting::EVENT_TIMEZONE)->toDateString()` di PHP. Jangan `DATE(created_at)` di SQL: hasilnya hari UTC (event 23:30 WIB pindah ke hari lain) dan sintaksnya beda antara SQLite (test) dan MySQL. Batas bawah query dikonversi dulu ke `config('app.timezone')` (lihat rule "Carbon ber-timezone WIB"). (Admin Overview, 2026-10-01.)

## Pengumuman ke peserta = `AnnounceCategory` + enum `Announcement`; peserta membaca `statusForParticipant()`

Keputusan user: konfirmasi finalis/award tetap internal. Peserta baru melihat hasil setelah admin menekan Announce per kategori di Admin → Announcements, berurutan: `Finalists` → `Invitations` (undangan Awarding Night ke semua finalis, award TIDAK diungkap) → `Winners` (hanya penerima award). Semua perbedaan per jenis (kolom `*_announced_at`/`invitations_sent_at` + `_by` di kategori, kolom `*_notified_at` di submission, key template email, penerima, prasyarat `blockedReason()`) ada di enum `App\Enums\Announcement`; jangan menulis ulang kondisinya. `AnnounceCategory` mengunci baris kategori, mengklaim kolom kategori secara kondisional (`whereNull`), lalu `SendAnnouncement` (`afterCommit`) mengklaim kolom notified per submission (pola `SendVerificationDecision`). Pengumuman final: `ReopenFinalists`/`ReopenAwards` ditolak setelah diumumkan. Status yang dikirim ke halaman peserta selalu `Submission::statusForParticipant()` (finalis = `qualified` sampai diumumkan), bukan `$submission->status`. Non-finalis tidak dapat email, hanya `notSelected` di dashboard. Count alias `withCount` dinamis dibaca lewat `getAttribute("…_count")` (akses properti dinamis gagal PHPStan). (Pengumuman, 2026-10-01.)

## Link submission peserta = khusus superadmin, lewat field row `submission_url`

Keputusan user: superadmin bisa menyalin link pribadi peserta (`route('submissions.show', $submission)`) dari Participants → Registrations → View, untuk dikirim manual bila email konfirmasi tidak sampai. Berlaku juga di produksi (tanpa flag env). `RegistrationController::index` mengisi `submission_url` hanya bila `role === Superadmin`; admin menerima `null` dari server, bukan sekadar disembunyikan di UI, karena membuka link menandai email peserta terverifikasi (§ Verifikasi email peserta). Jangan masukkan link ke export atau ke tabel Paper Submissions tanpa cek peran yang sama. Reference: `tests/Feature/Admin/ParticipantsTest.php`. (Detail peserta, 2026-10-01.)
