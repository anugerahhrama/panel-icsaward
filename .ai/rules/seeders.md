---
paths:
    - 'database/seeders/**'
---

# Seeders

## Seeder harus aman dijalankan ulang: `updateOrCreate` pada field unik alami

Pakai `updateOrCreate` dengan key field yang unik secara alami (`key` untuk `Setting`, `name` untuk kategori, dst.), bukan `create`, supaya `db:seed` berulang tidak menduplikasi baris.

## Mengganti nama key `Setting` meninggalkan baris lama

`updateOrCreate` tidak tahu key lama. Saat mengganti nama key, hapus baris lama secara manual di dev lokal dan sebutkan di catatan verifikasi sesi.

## Pengecualian: default `Setting` pakai `firstOrCreate`

`SettingSeeder` mengisi nilai default (placeholder T&C, tanggal registrasi, `max_registrations_per_user`) dengan `firstOrCreate(['key' => …])`, bukan `updateOrCreate`, supaya re-seed tidak menimpa nilai yang sudah diedit admin. Tetap idempotent. Data master lain (kategori, dst.) tetap `updateOrCreate`. (Sign Up, 2026-09-29.)

## `AssessmentTemplateSeeder` meng-assign kategori berdasarkan nama

Template di-`updateOrCreate` by `name`, kriteria by (`assessment_template_id`, `sort_order`) — baris dengan `sort_order` di atas jumlah kriteria dihapus — lalu `award_categories.assessment_template_id` diisi dengan mencocokkan nama kategori. Rename kategori di `AwardCategorySeeder` wajib diikuti perubahan nama yang sama di seeder ini; kalau tidak, kategori itu diam-diam tanpa template (test seeder menangkapnya). (Assessment Templates, 2026-09-30.)

## Menimpa nilai `Setting` yang sudah ada di DB: seeder sekali-jalan

`SettingSeeder` (`firstOrCreate`) tidak mengubah nilai yang sudah tersimpan. Kalau nilai resmi berubah (mis. jadwal dari deck), buat seeder terpisah yang memakai `updateOrCreate` hanya untuk key yang berubah. Nilainya disimpan di `public const array`, dan `SettingSeeder` memakainya lewat spread (`...DeckScheduleSeeder::SCHEDULE`) supaya hanya ada satu sumber. Seeder ini **tidak** didaftarkan di `DatabaseSeeder`. Jalankan manual dengan `db:seed --class=…` di lokal & produksi, lalu catat perintahnya di Known issues. (Sinkronisasi deck, 2026-09-30.)

## Rename kategori: di tempat lewat `AwardCategorySeeder::RENAMED`

Jangan hapus baris lama, karena submission, penugasan juri, template paper, dan `assessment_template_id` menempel ke id-nya. Tambahkan pasangan `old => new` ke `RENAMED`. Seeder mengganti nama baris lama (bila baris bernama baru belum ada) sebelum menjalankan `updateOrCreate` by `name`. (Sinkronisasi deck, 2026-09-30.)
