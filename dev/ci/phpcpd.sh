#!/usr/bin/env bash
# Copy/paste detector (phpcpd 6.0.3, min 5 lines / 70 tokens) on the given directories.
#
# phpcpd cannot be installed with Composer next to PHPUnit 10 (its last release needs sebastian/cli-parser ^1), so the phar is
# downloaded from phar.phpunit.de and checked against a pinned SHA-256 before it runs. The checksum was taken from the first
# download of this release (the author also publishes a PGP signature, .asc, next to the phar).
#
# Usage: dev/ci/phpcpd.sh <directory>...
set -euo pipefail

VERSION=6.0.3
SHA256=2cbaea7cfda1bb4299d863eb075e977c3f49055dd16d88529fae5150d48a84cb

cd "$(dirname "$0")/../.."
PHAR="build/phpcpd-${VERSION}.phar"
mkdir -p build

[ -f "$PHAR" ] || curl -fsSL -o "$PHAR" "https://phar.phpunit.de/phpcpd-${VERSION}.phar"

ACTUAL="$( (sha256sum "$PHAR" 2>/dev/null || shasum -a 256 "$PHAR") | cut -d' ' -f1)"
if [ "$ACTUAL" != "$SHA256" ]; then
    echo "phpcpd: checksum mismatch for $PHAR (expected $SHA256, got $ACTUAL)" >&2
    rm -f "$PHAR"
    exit 1
fi

php "$PHAR" --min-lines 5 --min-tokens 70 "$@"
