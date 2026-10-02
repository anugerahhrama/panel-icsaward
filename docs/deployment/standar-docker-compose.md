# Standar Docker Compose — Project Deployment

Dokumen ini jadi acuan struktur `docker-compose.yml` untuk semua project yang di-deploy ke VPS (Traefik sebagai reverse proxy, sesuai panduan setup VPS).

---

## 1. Struktur Folder Project

```
/opt/apps/<nama-project>/
├── docker-compose.yml
├── .env
├── .env.example
└── data/              # kalau ada volume bind-mount lokal
```

- `.env` **tidak** masuk git, isi credential asli.
- `.env.example` masuk git, jadi referensi variable yang dibutuhkan.

---

## 2. Template Dasar

```yaml
services:
    app:
        image: <registry>/<nama-project>:<tag>
        container_name: <nama-project>-app
        restart: unless-stopped
        env_file:
            - .env
        networks:
            - traefik-public
            - backend-internal
        depends_on:
            - db
        labels:
            - 'traefik.enable=true'
            - 'traefik.http.routers.<nama-project>.rule=Host(`<domain>`)'
            - 'traefik.http.routers.<nama-project>.entrypoints=websecure'
            - 'traefik.http.routers.<nama-project>.tls.certresolver=myresolver'
            - 'traefik.http.services.<nama-project>.loadbalancer.server.port=<internal_port>'
        logging:
            driver: json-file
            options:
                max-size: '10m'
                max-file: '3'
        deploy:
            resources:
                limits:
                    cpus: '1.0'
                    memory: 512M
                reservations:
                    memory: 256M

    db:
        image: postgres:16
        container_name: <nama-project>-db
        restart: unless-stopped
        env_file:
            - .env
        volumes:
            - db-data:/var/lib/postgresql/data
        networks:
            - backend-internal
        logging:
            driver: json-file
            options:
                max-size: '10m'
                max-file: '3'
        # TIDAK ada "ports:" — akses hanya lewat network internal

volumes:
    db-data:

networks:
    traefik-public:
        external: true
    backend-internal:
        external: true
```

---

## 3. Aturan Wajib

### 3.1 Network

- Service yang perlu diakses publik → gabung ke `traefik-public`.
- Service internal (database, cache, queue worker) → **jangan** gabung ke `traefik-public`. Pisahkan ke network internal per-project (`backend-internal` atau nama spesifik project kalau perlu isolasi antar-project).
- Network dibuat via `docker network create` sekali di luar compose (`external: true`), bukan didefinisikan ulang tiap project, supaya Traefik dan semua project bisa saling nyambung tanpa duplikasi.

### 3.2 Port binding

- **Jangan pernah** publish port ke `0.0.0.0` untuk service yang harusnya lewat Traefik. Traefik yang expose ke publik lewat label, bukan lewat `ports:`.
- Kalau butuh expose port langsung tanpa Traefik (kasus khusus, misal debugging), bind ke localhost saja:
    ```yaml
    ports:
        - '127.0.0.1:5432:5432'
    ```
- Database dan service internal lainnya: **tidak ada** `ports:` sama sekali kalau cukup diakses lewat Docker network internal.

### 3.3 Environment variable & secret

- Semua credential lewat `.env` + `env_file:`, jangan hardcode di `environment:` langsung dalam compose file.
- `.env` masuk `.gitignore`. Commit `.env.example` sebagai referensi.
- Password minimal 16 karakter random, generate pakai `openssl rand -base64 24`.

### 3.4 Restart policy

- Default: `restart: unless-stopped` untuk semua service production.
- Jangan pakai `restart: always` untuk service yang masih dalam tahap development/debugging (biar gampang di-stop manual).

### 3.5 Resource limit

- Semua service **wajib** ada `deploy.resources.limits` minimal `cpus` dan `memory`, supaya satu container bermasalah (memory leak, infinite loop) tidak menghabiskan resource VPS yang dipakai bareng project lain.
- Sesuaikan angka dengan kapasitas VPS dan kebutuhan riil service — jangan asal copy angka dari project lain.

### 3.6 Logging

- Semua service pakai `json-file` driver dengan `max-size` dan `max-file` dibatasi (contoh: `10m` / `3` file), supaya log tidak memenuhi disk VPS dalam jangka panjang.

### 3.7 Naming convention

- `container_name`: `<nama-project>-<service>` (contoh: `biodrop-app`, `biodrop-db`).
- `traefik.http.routers.<name>` dan `traefik.http.services.<name>`: pakai nama project yang unik biar tidak bentrok antar-project di satu Traefik instance yang sama.

### 3.8 Image & versioning

- Hindari tag `latest` untuk production. Pin ke versi spesifik (`postgres:16`, bukan `postgres:latest`) supaya update tidak terjadi tanpa sengaja saat `docker compose pull`.
- Kalau build image sendiri, tag dengan git commit hash atau semantic version, bukan `latest`.

### 3.9 Volume

- Data yang perlu persist (database, uploaded files) → named volume, bukan anonymous volume, supaya jelas kepemilikan dan gampang di-backup.
- Named volume ikut prefix nama project: `<nama-project>-db-data`.

---

## 4. Checklist sebelum deploy project baru

- [ ] Tidak ada service internal yang ikut network `traefik-public`
- [ ] Tidak ada `ports:` yang publish ke `0.0.0.0` kecuali sengaja dan sudah direview
- [ ] Semua credential lewat `.env`, tidak ada hardcode di compose file
- [ ] `.env` sudah masuk `.gitignore`
- [ ] Semua service punya `restart: unless-stopped`
- [ ] Semua service punya resource limit (`cpus`, `memory`)
- [ ] Logging dibatasi (`max-size`, `max-file`)
- [ ] Image di-pin ke versi spesifik, bukan `latest`
- [ ] Volume database pakai named volume, sudah masuk skema backup (lihat panduan backup)
- [ ] Domain sudah di-set DNS ke IP VPS sebelum `docker compose up -d`

---

## 5. Perintah operasional standar

```bash
# Deploy / update
cd /opt/apps/<nama-project>
docker compose pull
docker compose up -d

# Cek log
docker compose logs -f app

# Restart satu service
docker compose restart app

# Stop tanpa hapus volume
docker compose down

# Stop + hapus volume (HATI-HATI, data hilang)
docker compose down -v
```
