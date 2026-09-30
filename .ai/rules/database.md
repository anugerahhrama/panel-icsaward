---
paths:
    - 'database/migrations/**'
---

# Database

## Nama index komposit panjang wajib eksplisit (batas MySQL 64 karakter)

Nama otomatis Laravel (`<tabel>_<kolom…>_unique`) untuk index komposit banyak kolom bisa melewati 64 karakter dan membuat `migrate` gagal. Beri nama pendek: `$table->unique([...], 'judge_scores_unique_score')`. DDL MySQL tidak transaksional, jadi migration yang gagal di tengah meninggalkan tabel yang setengah jadi dan migration masih `Pending`. Cek dulu tabelnya kosong, drop, lalu migrate ulang. (Dashboard juri, 2026-09-30.)

## Index komposit yang diawali kolom FK "mengambil alih" index FK

MySQL membuang index bawaan FK (`<tabel>_<kolom>_foreign`) begitu ada index komposit baru yang diawali kolom yang sama. Akibatnya `dropIndex` di `down()` gagal dengan error "needed in a foreign key constraint". Hindari index semacam itu kecuali benar-benar dibutuhkan. Kalau tetap dipakai, `down()` wajib membuat ulang index FK sebelum drop. (Score Recap, 2026-09-30: index `(award_category_id, stage1_rank)` dibatalkan.)
