#!/usr/bin/env bash
# One-time setup of a development checkout: dependencies and the git hooks.
set -euo pipefail
cd "$(dirname "$0")"

# The original Magento packages, mirrored without authentication (no repo.magento.com keys needed)
composer config repositories.magento-mirror composer https://mirror.mage-os.org/ --global 2>/dev/null || true
composer install
npm install

git config core.hooksPath .githooks
chmod +x .githooks/pre-commit .githooks/pre-push

echo "Done. Hooks enabled (core.hooksPath = .githooks); run 'composer check' and 'npm run lint' anytime."
