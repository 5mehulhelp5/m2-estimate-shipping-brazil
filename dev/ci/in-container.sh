#!/usr/bin/env bash
# Runs a command of the PHP toolchain inside the Docker container of the Magento dev/QA environment (PHP 8.3 + xdebug), on a copy
# of this checkout, so the result does not depend on the PHP of the host. When the container is not running it prints a warning
# and skips (exit 0).
#
#   dev/ci/in-container.sh --sync            copy the checkout (and install the Composer dependencies when they changed)
#   dev/ci/in-container.sh <command...>      sync, then run the command in the copy (e.g. vendor/bin/phpcs)
#   IN_CONTAINER_SYNCED=1 dev/ci/in-container.sh <command...>   run without syncing again
#
# QA_PHP_CONTAINER names the container (default m2-phpfpm-1, the one of markshust/docker-magento for a project called "m2").
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
NAME="${QA_PHP_CONTAINER:-m2-phpfpm-1}"
DIR="/tmp/dev-checks/$(basename "$ROOT")"
MIRROR="${MAGENTO_MIRROR:-https://mirror.mage-os.org/}"

if ! command -v docker >/dev/null 2>&1 || [ "$(docker inspect -f '{{.State.Running}}' "$NAME" 2>/dev/null || true)" != "true" ]; then
    echo "WARNING: container '$NAME' is not running, skipping: ${*:-sync}" >&2
    mkdir -p "$ROOT/build" && echo "${*:-sync}" >> "$ROOT/build/.hook-skipped"
    exit 0
fi

sync_checkout() {
    docker exec "$NAME" mkdir -p "$DIR"
    # Drop the previous copy of every tracked top-level path, so a deleted file does not survive (vendor stays)
    for top in $(git -C "$ROOT" ls-files --cached --others --exclude-standard | cut -d/ -f1 | sort -u); do
        docker exec "$NAME" rm -rf "${DIR:?}/$top"
    done
    git -C "$ROOT" ls-files -z --cached --others --exclude-standard \
        | COPYFILE_DISABLE=1 tar -C "$ROOT" --null -T - --no-xattrs -cf - 2>/dev/null \
        | docker exec -i "$NAME" tar -x -C "$DIR"

    local wanted current
    wanted="$(shasum -a 256 "$ROOT/composer.json" | cut -d' ' -f1)"
    current="$(docker exec "$NAME" cat "$DIR/vendor/.composer-json-hash" 2>/dev/null || true)"
    if [ "$current" != "$wanted" ]; then
        echo "in-container: composer update (first run or composer.json changed)..." >&2
        docker exec -w "$DIR" "$NAME" composer config -g repositories.magento-mirror composer "$MIRROR"
        docker exec -w "$DIR" "$NAME" composer update --no-interaction --no-progress --prefer-dist -q
        docker exec "$NAME" sh -c "echo $wanted > $DIR/vendor/.composer-json-hash"
    fi
}

if [ "${1:-}" = "--sync" ]; then
    sync_checkout
    exit 0
fi

[ -n "${IN_CONTAINER_SYNCED:-}" ] || sync_checkout
exec docker exec -w "$DIR" "$NAME" "$@"
