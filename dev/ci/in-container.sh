#!/usr/bin/env bash
# Runs a command of the PHP toolchain inside the Docker container of the Magento dev/QA environment (PHP 8.3 + xdebug), on a copy
# of this checkout, so the result does not depend on the PHP of the host. When the container is not running it prints a warning
# and skips (exit 0). It does the same when Docker does not answer within QA_DOCKER_TIMEOUT seconds (default 5), instead of hanging.
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

# Docker Desktop can hang `docker` commands while it starts or when it is stuck, and macOS has no `timeout`: run the command in
# the background and kill it after $1 seconds. Its output is left in $OUTPUT (a file, not a pipe, so a child that outlives the
# kill cannot keep the caller waiting); the status is 124 when the deadline was hit.
run_with_deadline() {
    local seconds="$1" status=0
    shift
    [ -z "${OUTPUT:-}" ] || rm -f "$OUTPUT"
    OUTPUT="$(mktemp)"
    "$@" >"$OUTPUT" 2>/dev/null &
    local pid=$!
    (sleep "$seconds"; kill -9 "$pid" 2>/dev/null) >/dev/null 2>&1 &
    local watchdog=$!
    wait "$pid" 2>/dev/null || status=$?
    kill "$watchdog" 2>/dev/null || true
    [ "$status" -eq 137 ] && return 124
    return "$status"
}

# Empty when the container answers; otherwise why the checks are skipped. QA_DOCKER_TIMEOUT: seconds to wait (default 5).
unavailable_reason() {
    local wait="${QA_DOCKER_TIMEOUT:-5}" status=0 OUTPUT
    command -v docker >/dev/null 2>&1 || { echo "docker is not installed"; return; }
    run_with_deadline "$wait" docker inspect -f '{{.State.Running}}' "$NAME" || status=$?
    if [ "$status" -eq 124 ]; then
        echo "Docker did not answer within ${wait}s (it may be starting or stuck)"
    elif [ "$(cat "$OUTPUT")" != "true" ]; then
        echo "container '$NAME' is not running"
    elif ! run_with_deadline "$wait" docker exec "$NAME" true; then
        echo "container '$NAME' did not answer within ${wait}s"
    fi
    rm -f "$OUTPUT"
}

REASON="$(unavailable_reason)"
if [ -n "$REASON" ]; then
    echo "WARNING: $REASON, skipping: ${*:-sync}" >&2
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
