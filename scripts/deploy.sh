#!/usr/bin/env bash
# Deploy production. Dijalankan di VPS (manual atau via GitHub Actions).
#
#   bash scripts/deploy.sh              # deploy origin/main
#   DEPLOY_REF=v1.2.0 bash scripts/deploy.sh   # deploy tag/branch tertentu
#   ROLLBACK_TAG=abc1234 bash scripts/deploy.sh # rollback ke image lama (tanpa build)
set -euo pipefail

cd "$(dirname "$0")/.."

DEPLOY_REF="${DEPLOY_REF:-main}"
KEEP_BACKUPS="${KEEP_BACKUPS:-14}"

set_env() {
	local key="$1" value="$2"
	if grep -q "^${key}=" .env; then
		sed -i.bak "s|^${key}=.*|${key}=${value}|" .env && rm -f .env.bak
	else
		echo "${key}=${value}" >> .env
	fi
}

wait_healthy() {
	local container="$1" tries=0
	echo "Waiting for ${container} to become healthy..."
	until [ "$(docker inspect -f '{{.State.Health.Status}}' "$container" 2>/dev/null)" = "healthy" ]; do
		tries=$((tries + 1))
		if [ "$tries" -ge 60 ]; then
			echo "✗ ${container} not healthy after 120s"
			docker compose logs --tail=100 app
			exit 1
		fi
		sleep 2
	done
	echo "✓ ${container} healthy"
}

PROJECT="$(sed -n 's/^ *container_name: *\(.*\)-app$/\1/p' docker-compose.yml)"

if [ -n "${ROLLBACK_TAG:-}" ]; then
	echo "→ Rollback ke image ${PROJECT}:${ROLLBACK_TAG}"
	set_env IMAGE_TAG "$ROLLBACK_TAG"
	docker compose up -d --no-build --remove-orphans
	wait_healthy "${PROJECT}-app"
	exit 0
fi

echo "→ Fetch ${DEPLOY_REF}"
git fetch --prune --tags origin
if git rev-parse --verify -q "origin/${DEPLOY_REF}" >/dev/null; then
	git checkout -q --detach "origin/${DEPLOY_REF}"
else
	git checkout -q --detach "${DEPLOY_REF}"
fi

IMAGE_TAG="$(git rev-parse --short HEAD)"
echo "→ Commit ${IMAGE_TAG}: $(git log -1 --pretty=%s)"

echo "→ Backup database sebelum migrate"
bash scripts/backup-db.sh || echo "! Backup gagal/skip (db belum jalan?)"

echo "→ Build image ${PROJECT}:${IMAGE_TAG}"
IMAGE_TAG="$IMAGE_TAG" docker compose build
# Baru ditulis ke .env setelah build sukses, supaya `docker compose up`
# berikutnya tetap pakai tag yang benar.
set_env IMAGE_TAG "$IMAGE_TAG"

echo "→ Up"
docker compose up -d --remove-orphans
wait_healthy "${PROJECT}-app"

# Worker perlu restart supaya pakai kode baru (queue:work long-running).
docker compose exec -T app php artisan queue:restart >/dev/null 2>&1 || true

echo "→ Bersihkan image lama (simpan 3 tag terakhir untuk rollback)"
for repository in "${PROJECT}" "${PROJECT}-ssr"; do
	docker images "$repository" --format '{{.Tag}}' \
		| grep -v -e '^latest$' -e "^${IMAGE_TAG}$" \
		| tail -n +3 \
		| xargs -r -I{} docker rmi "${repository}:{}" || true
done
docker image prune -f >/dev/null

echo "✓ Deploy ${IMAGE_TAG} selesai"
