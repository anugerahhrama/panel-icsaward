---
paths:
    - '**'
---

# Process

> Disalin dari repo Landing (`icsa2026-app`) saat Project setup, 2026-09-29.

## Jangan matikan dev server verifikasi dengan `pkill` lebar

Kalau menjalankan `composer run dev` di background untuk verifikasi manual, jangan bersihkan dengan pola lebar seperti `pkill -f "artisan dev"`, karena dev server milik user (mis. port 8000 sudah terpakai, punyamu jadi 8001) ikut mati. Simpan PID proses yang kamu jalankan (`$!` atau `lsof -ti:<port>` untuk port yang benar-benar kamu bind) dan kill PID itu saja. Kalau tidak bisa diisolasi, biarkan menyala dan beri tahu user.

## zsh tidak memecah `$VAR` — kirim daftar file ke `vp fmt`/`vp check` sebagai array atau inline

`F="a.tsx b.tsx"; npx vp fmt $F` mengirim SATU path berisi spasi, dan vp gagal dengan pesan menyesatkan "Expected at least one target file". Pakai `F=(a.tsx b.tsx); npx vp fmt $F` atau tulis path-nya inline.

## Playwright untuk screenshot ada di cache npx, bukan dependency project

`playwright` bukan dependency project (jangan ditambahkan). Import lewat path absolut di script `.mjs` scratchpad (`find ~/.npm/_npx -maxdepth 4 -name playwright -type d` → `<dir>/index.mjs`); `import 'playwright'` biasa gagal `ERR_MODULE_NOT_FOUND`. Arahkan ke dev server verifikasi milikmu sendiri.

## PHPStan butuh `--memory-limit=512M` di mesin lokal

`memory_limit` PHP lokal 128M membuat worker PHPStan crash ("reached configured PHP memory limit") — terjadi juga di repo Landing. Skrip `types:check` di `composer.json` sudah memakai `phpstan analyse --memory-limit=512M`; jangan hapus flag itu, dan pakai flag yang sama kalau menjalankan `vendor/bin/phpstan` langsung. (Project setup, 2026-09-29.)

## `composer require` memicu `boost:update` yang menulis ulang AGENTS.md & skill

Script post-update Composer menjalankan `php artisan boost:update`, yang meregenerasi blok `<laravel-boost-guidelines>` di `AGENTS.md`, `boost.json`, dan skill di `.claude/`, `.kiro/`, dst. Setelah menambah package, cek bahwa section workflow di `AGENTS.md` (di luar blok boost) masih utuh, lalu beri tahu user agar meninjau diff itu sebelum commit. (Participants, 2026-09-29.)
