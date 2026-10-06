# Deployment — ICS Award 2026 (Assessment)

Panduan deploy aplikasi ini ke VPS. Mengikuti [standar Docker Compose](standar-docker-compose.md) organisasi (Traefik sebagai reverse proxy, network terpisah, resource limit, log rotation) — pola yang sama dengan `sf360-2026` dan repo Landing (`icsa2026-app`).

---

## 1. Arsitektur

```
Internet ─► Traefik (TLS, network traefik-public)
               │
               ▼
          regis-icsa2026-app ─────┐
          regis-icsa2026-worker ──┼──► regis-icsa2026-db (MySQL 8.4, tanpa port publik)
                                  │
                                  └── volume regis-icsa2026-storage (paper peserta, template)
                                      — semua di network regis-icsa2026-internal
```

| Service  | Image                       | Fungsi                                                                                                           |
| -------- | --------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `app`    | `regis-icsa2026:<git-hash>` | FrankenPHP (PHP 8.4 + Caddy), serve Laravel + Inertia (SSR mati). Satu-satunya yang menjalankan migration        |
| `worker` | `regis-icsa2026:<git-hash>` | `queue:work` — **wajib**: email konfirmasi, keputusan verifikasi & pengumuman finalis hanya terkirim lewat queue |
| `db`     | `mysql:8.4`                 | Database                                                                                                         |

Catatan keputusan:

- **MySQL, bukan PostgreSQL** (beda dengan sf360/Landing). Rule `.ai/rules/database.md` ditulis untuk MySQL, dan raw SQL skor juri `sum(raw_score * weight) / 100` (`StageScoreCalculator`) baru menghasilkan desimal di MySQL — SQLite/PostgreSQL membulatkan pembagian integer.
- **Tanpa SSR & scheduler**: `config/inertia.php` sengaja `ssr.enabled => false` (sesi Persiapan produksi), dan belum ada scheduled task.
- **Upload paper sampai 100 MB**: `docker/php.ini` (`upload_max_filesize=100M`) mengikuti batas maksimum setting `paper_max_size_mb`.
- **File**: paper di disk privat (`storage/app/private`), template unduhan di disk `public` — keduanya di volume `regis-icsa2026-storage`.

File-file terkait:

| File                               | Fungsi                                                                                                     |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `Dockerfile`                       | 2 stage: `build` (composer + `npm run build`), `runtime` (FrankenPHP + ext `gd`, `zip` untuk export Excel) |
| `docker-compose.yml`               | Service, network, volume, label Traefik, healthcheck, resource limit                                       |
| `docker/entrypoint.sh`             | Tunggu DB → migrate (hanya `app`) → cache config/route/view/event → `storage:link`                         |
| `docker/Caddyfile`                 | Konfigurasi web server                                                                                     |
| `docker/php.ini`                   | Batas upload 100 MB, memory 256M                                                                           |
| `.env.production.example`          | Template environment production                                                                            |
| `scripts/deploy.sh`                | Deploy / rollback di VPS                                                                                   |
| `scripts/backup-db.sh`             | Backup MySQL (`mysqldump`) + rotasi                                                                        |
| `.github/workflows/production.yml` | Deploy otomatis saat push tag `v*`                                                                         |

---

## 2. Prasyarat

- VPS dengan Docker + Compose plugin, Traefik sudah jalan (network `traefik-public` ada, certresolver bernama `myresolver` — sesuaikan label di `docker-compose.yml` kalau beda).
- DNS domain Assessment sudah mengarah ke IP VPS **sebelum** container dijalankan.
- VPS bisa `git fetch` repo `olahkarsa/ics-award-panel-2026` (pasang deploy key read-only).
- Kredensial SMTP, dan Google OAuth client (redirect URI `<APP_URL>/auth/google/callback`).

---

## 3. Deploy pertama kali

```bash
# 3.1 Network (sekali per server)
docker network create traefik-public 2>/dev/null || true
docker network create regis-icsa2026-internal

# 3.2 Clone
mkdir -p /opt/apps && cd /opt/apps
git clone git@github.com:olahkarsa/ics-award-panel-2026.git regis-icsa2026
cd regis-icsa2026

# 3.3 Env
cp .env.production.example .env
```

Isi variabel wajib di `.env`:

| Variabel                                   | Keterangan                                                    |
| ------------------------------------------ | ------------------------------------------------------------- |
| `APP_URL`                                  | `https://<domain>`                                            |
| `APP_DOMAIN`                               | `<domain>` tanpa `https://` (rule `Host()` Traefik)           |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`          | masing-masing `openssl rand -base64 24`                       |
| `MAIL_*`                                   | SMTP asli — tanpa ini peserta tidak menerima email konfirmasi |
| `ADMIN_PANEL_PREFIX`                       | nilai privat, mis. `panel-$(openssl rand -hex 4)`             |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | OAuth login peserta                                           |

```bash
# 3.4 APP_KEY — sekali, JANGAN diganti lagi
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate --show
# tempel hasilnya ke APP_KEY= di .env

# 3.5 Deploy (migration jalan otomatis)
bash scripts/deploy.sh

# 3.6 Seeder (sekali; aman diulang — superadmin yang sudah ada tidak disentuh)
docker compose exec app php artisan db:seed --force
```

DB lama yang di-upgrade (bukan deploy pertama) dan jadwal perlu mengikuti deck:

```bash
docker compose exec app php artisan db:seed --class=DeckScheduleSeeder --force   # MENIMPA jadwal di Settings
```

### Verifikasi

```bash
docker compose ps                                   # app & db healthy, worker running
curl -I https://<domain>/login
docker compose logs --tail=20 worker               # job email diproses
docker compose exec app php -r 'echo ini_get("upload_max_filesize"), PHP_EOL;'   # 100M
```

### Setelah deploy pertama — isi lewat admin

Ikuti langkah 8–9 **Checklist deploy produksi** di `.ai/PROJECT.md`: login superadmin di `/<ADMIN_PANEL_PREFIX>`, **wajib ganti password superadmin** (default tercantum di repo), ganti T&C placeholder, unggah Statement Letter & template paper 20 kategori, cek jadwal, lalu smoke test Sign Up sampai email konfirmasi masuk.

Lalu sambungkan ke Landing: Admin → Settings → **Landing API** → buat token, isi URL + token itu di Landing (Site Settings → System → Assessment Integration).

---

## 4. Rilis / update

Otomatis dari GitHub (setelah secrets di-setup, lihat §6):

```bash
git tag v1.0.0 && git push origin v1.0.0
```

Atau manual di VPS:

```bash
cd /opt/apps/regis-icsa2026
bash scripts/deploy.sh                      # deploy origin/main
DEPLOY_REF=v1.0.1 bash scripts/deploy.sh    # deploy tag/branch tertentu
```

`deploy.sh`: fetch → backup DB (`mysqldump`) → build image (tag = git short hash) → `up -d` → tunggu `app` healthy → `queue:restart` (worker memuat kode baru) → hapus image lama (3 tag terakhir disimpan).

### Rollback

```bash
docker images regis-icsa2026                # lihat tag yang tersedia
ROLLBACK_TAG=<hash-lama> bash scripts/deploy.sh
```

Rollback image **tidak** membatalkan migration. Kalau migration destruktif, restore dari backup otomatis sebelum deploy (`backups/`).

---

## 5. Operasional

```bash
docker compose logs -f app                  # log aplikasi
docker compose logs -f worker               # pengiriman email (queue)
docker compose exec app php artisan queue:failed         # job gagal
docker compose exec app php artisan queue:retry all      # kirim ulang

bash scripts/backup-db.sh                   # backup manual → backups/
gunzip -c backups/regis_icsa2026-YYYY-MM-DD-HHMM.sql.gz \
  | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'   # restore
```

Backup harian via cron di host — DB **dan** volume file (paper peserta tidak ada di database):

```cron
0 2 * * * cd /opt/apps/regis-icsa2026 && bash scripts/backup-db.sh >> backups/cron.log 2>&1
30 2 * * * cd /opt/apps/regis-icsa2026 && docker run --rm -v regis-icsa2026-storage:/data -v "$PWD/backups":/backup alpine tar czf /backup/storage-$(date +\%F).tar.gz -C /data app
```

---

## 6. GitHub Actions

Repo → **Settings → Environments** → buat `production` (opsional: _Required reviewers_), isi secrets:

| Secret                  | Isi                                 |
| ----------------------- | ----------------------------------- |
| `SSH_HOST` / `SSH_PORT` | IP & port SSH VPS                   |
| `SSH_USER`              | user deploy (anggota grup `docker`) |
| `SSH_PRIVATE_KEY`       | private key user deploy             |
| `WORK_DIR`              | `/opt/apps/regis-icsa2026`          |

---

## 7. Troubleshooting

- **Peserta tidak menerima email** — `docker compose ps worker` harus running; cek `docker compose logs worker` dan `queue:failed`; cek `MAIL_*` (setelah ubah `.env`: `docker compose up -d` agar config di-cache ulang, lalu `queue:restart`).
- **Upload paper gagal untuk file besar** — `upload_max_filesize` di `docker/php.ini` harus ≥ setting `paper_max_size_mb` (maks. 100 MB); rebuild setelah mengubah.
- **Skor juri tidak berdesimal** — DB bukan MySQL. Lihat §1 catatan keputusan.
- **URL/redirect jadi `http://` / Google OAuth redirect mismatch** — `bootstrap/app.php` harus punya `$middleware->trustProxies(at: '*')` (dijaga `tests/Feature/TrustedProxyTest.php`); `APP_URL` harus `https://`.
- **`app` tidak pernah healthy** — `docker compose logs app`. Macet di "Waiting for database" → cek `DB_*` dan `DB_ROOT_PASSWORD`.
- **Migration gagal di tengah** — DDL MySQL tidak transaksional; lihat `.ai/rules/database.md` (nama index maks. 64 karakter).
- **Paper hilang setelah redeploy** — jangan `docker compose down -v`; file ada di volume `regis-icsa2026-storage`.

---

## 8. Checklist sebelum deploy pertama

- [ ] Network `traefik-public` & `regis-icsa2026-internal` sudah dibuat
- [ ] DNS domain mengarah ke IP VPS
- [ ] `.env` lengkap: `APP_KEY`, `APP_URL`, `APP_DOMAIN`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `MAIL_*`, `ADMIN_PANEL_PREFIX`, `GOOGLE_*` (tidak ada `change-me`)
- [ ] `APP_DEBUG=false`
- [ ] `docker compose ps` → `app` & `db` healthy, `worker` running
- [ ] Seeder sudah dijalankan, password superadmin sudah diganti
- [ ] Sign Up → email konfirmasi masuk (bukti SMTP + worker)
- [ ] Token Landing API dibuat & tersambung ke Landing
- [ ] Backup DB + volume storage terjadwal (cron)
