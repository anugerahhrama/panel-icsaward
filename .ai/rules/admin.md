---
paths:
    - 'resources/js/pages/admin/**'
    - 'resources/js/components/admin/**'
    - 'resources/js/components/app-sidebar.tsx'
---

# Admin

## UI copy admin berbahasa Inggris

Semua teks di area admin (label sidebar, judul halaman, breadcrumb, tombol, label form, toast, dialog, empty state) berbahasa Inggris — sesuai nama menu di `ASSESSMENT-BRIEF.md` ("Participants", "Score Recap", "Assessment Templates", …).

## Tabel besar server-side, master data kecil client-side

Participants (Registrations & Paper Submissions), Score Recap, dan daftar submission juri: pagination/sort/filter/search di query Laravel lewat query string Inertia, TanStack Table mode manual (`manualPagination`, `manualSorting`, `manualFiltering`). Categories, Judges, Assessment Templates: client-side. Export Excel memakai filter yang sama dengan tampilan tabel.

## Menu sidebar bercabang per peran di `app-sidebar.tsx`

Sidebar (`AppLayout`) juga dipakai peserta/juri di `/settings/*`, jadi `adminNavItems` hanya untuk `auth.user.role` superadmin/admin, `judgeNavItems` (Overview, My Submissions) untuk juri, sisanya `participantNavItems` (Dashboard). Menu admin baru (Categories, Participants, …) ditambahkan ke `adminNavItems`. Import route Wayfinder per sub-grup sebagai default (`import fileSettings from '@/routes/admin/settings/files'`) — barrel `@/routes/admin/settings` tidak mengekspor named `files`/`email`. (Admin Settings, 2026-09-29.)

## Toast hasil CRUD dari server, bukan dari client

Controller admin mem-flash hasil aksi lewat `Inertia::flash('toast', ['type' => 'success'|'error', 'message' => ...])`; `hooks/use-flash-toast.ts` menampilkannya secara global. Di client jangan panggil `toast.success()` lagi di `onSuccess` (toast jadi dobel) — cukup `toast.error()` di `onError` untuk kegagalan validasi/jaringan. Reference: `Admin/CategoryController` + `pages/admin/categories/index.tsx`. (Admin Categories, 2026-09-29.)

## Master data yang sudah dipakai tidak bisa dihapus: tolak di controller + tombol Delete nonaktif

Hapus record yang masih direferensikan (kategori dengan submission, nanti template/juri yang sudah dipakai) dicek di `destroy()` (`->relation()->exists()`) → flash toast `error` + redirect, bukan mengandalkan exception FK. Kirim `*_count` (`withCount`) ke tabel; tombol Delete `disabled` dibungkus `DisabledTooltip` (`components/admin/disabled-tooltip.tsx`, `reason` = teks tooltip atau `null`) — button disabled tidak memicu event pointer sehingga tooltip butuh span pembungkus yang bisa difokus. Reference: `pages/admin/categories/columns.tsx` (`DeleteButton`). Kontrol yang terkunci saat penjurian memakai `JUDGING_LOCKED_REASON` + banner `JudgingLockAlert` (`components/admin/judging-lock-alert.tsx`). (Admin Categories, 2026-09-29.)

## Form dengan baris dinamis: key client stabil, dibuang lewat `transform`

Editor baris (kriteria template, nanti slot pitching dsb.) menyimpan array baris di satu `useForm`; tiap baris punya `key` dari counter `useRef` untuk React (jangan pakai index — naik/turun/hapus merusak state input). Sebelum submit, `transform` membuang `key` dan `id: null`. Error per baris dibaca dari `errors['criteria.N.field']` (cast `errors` ke `Record<string, string | undefined>`), error agregat di key array (`errors.criteria`). `SaveButton` menerima `disabled` untuk validasi client (mis. total bobot ≠ 100). Reference: `components/admin/assessment-templates/template-form.tsx`. (Assessment Templates, 2026-09-30.)
