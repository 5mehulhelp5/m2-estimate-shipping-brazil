#!/usr/bin/env bash
# One-time setup of a development checkout: ESLint and the git hooks.
#
# The PHP checks (phpcs, phpmd, phpstan, PHPUnit with coverage, phpcpd) run inside the Docker container of your Magento dev
# environment (PHP 8.3 with xdebug), through dev/ci/in-container.sh: start it before committing, or the hooks skip those checks
# with a warning. Set QA_PHP_CONTAINER if your php-fpm container is not called m2-phpfpm-1.
set -euo pipefail
cd "$(dirname "$0")"

npm install

git config core.hooksPath .githooks
chmod +x .githooks/pre-commit .githooks/pre-push dev/ci/*.sh

echo "Done. Hooks enabled (core.hooksPath = .githooks)."
echo "PHP checks run in the container '${QA_PHP_CONTAINER:-m2-phpfpm-1}'."
