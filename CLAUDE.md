# CLAUDE.md — Fineweb_SimulateProductShipping

Magento 2 / Adobe Commerce module (`fineweb/module-simulateproductshipping`, GPL-3.0-or-later). On the product page the shopper
types a Brazilian postcode (CEP) and sees every shipping method the store offers for that product, the chosen options and
the quantity. Declared for Magento 2.4.7+ (`magento/framework ^103.0.7`) and PHP 8.2 – 8.3.

## Layout

| Path | What |
|---|---|
| `Api/ShippingEstimatorInterface.php` | Contract of the estimate; bound to the model in `etc/di.xml` |
| `Model/ShippingEstimator.php` | All the quote / totals / rates logic (no quote is saved, works for guests) |
| `Controller/Simulate/Index.php` | `POST /fineweb/simulate/index`: reads and validates the request, calls the service, returns JSON |
| `ViewModel/ShippingForm.php` | Data for the template: Correios link and the `x-magento-init` JSON |
| `view/frontend/layout/catalog_product_view.xml` | Block in `product.info.main` (not `product.info.form.content`: it is not printed for products with options) |
| `view/frontend/templates/product/view/simulate-shipping.phtml` | Markup only; `data-role` / `data-action` hooks, BEM classes (`fineweb-shipping-estimate`) |
| `view/frontend/web/js/fineweb-shipping.js` | Widget: mask, validation, results built with `.text()`, `$t()` whole phrases |
| `i18n/{en_US,pt_BR}.csv` | Exact phrases; UTF-8 without BOM; final newline |
| `Test/Unit` | PHPUnit, mirrors the structure; `Test/Unit/bootstrap.php` declares the generated `QuoteFactory` |
| `e2e/` | Playwright tests against a running store |
| `dev/ci`, `dev/phpmd` | CI checks (PHP scripts) and the vendored Magento PHPMD rule set |

The response is `{ "<carrier title>": [{ "title", "price", "message" }] }`, or `{ "error": { "message" } }` with HTTP 400 / 500
(HTTP 200 when no carrier offers a rate).

## Commands

```bash
./setup-dev.sh                # composer install, npm install, enables the git hooks (core.hooksPath = .githooks)
composer check                # blacklist, phpcs, phpmd, phpstan, tests-exist, multi-store, coverage
composer test                 # PHPUnit only
composer coverage             # PHPUnit with xdebug + the 70% per-file gate (prints the percentage of every file)
composer phpcs | phpmd | phpstan | blacklist | check-tests-exist | check-scope
npm run lint                  # ESLint 10 (flat config) on view/**/*.js, zero warnings allowed
```

The dependencies come from a public mirror of the original Magento packages, so no `repo.magento.com` keys are needed:
`composer config -g repositories.magento-mirror composer https://mirror.mage-os.org/`.

## Quality rules (enforced in CI and by the git hooks)

- Blacklist: no `var_dump(`, `print_r(`, `console.log(`, `die`, `exit`, `eval(`, `phpinfo`, `ObjectManager::getInstance`,
  `DebuggerUtility`, `localhost`, `$_GET[`/`$_POST[`/`$_REQUEST[`, merge markers in php/js/phtml/html (not in `Test/`, `e2e/`).
- phpcs Magento2 on php + phtml (+ less) with **warnings blocking** (`warning-severity` 1). Two documented exclusions:
  the BEM `__` class name sniff, and `dev/ci` + `dev/phpmd` (tooling / vendored code).
- phpmd with the Magento rule set (`dev/phpmd/ruleset.xml`), phpstan level 9, no baseline.
- PHPUnit with coverage of at least 70% **per file**; every production PHP file (except `registration.php`, `Api/`,
  interfaces) has its `Test/Unit` counterpart; every `scopeConfig->getValue()/isSetFlag()` passes a scope.
- JavaScript: whole sentences with placeholders in `$t()`, no concatenated HTML (`.text()` or templates), `data-*` hooks scoped
  to the widget element, no fixed ids, no inline styles; CSS in BEM kebab-case with the `fineweb-` prefix.
- The controller only reads and validates the request; logic goes in the service behind the `Api/` interface;
  templates get data from the ViewModel.
- Source files keep their GPL-3.0 headers. `composer validate --strict` passes. Not enforced: `phpcpd` — its last release (6.0.3)
  needs `sebastian/cli-parser ^1`, which conflicts with PHPUnit 10.

## Behavior to preserve

With the test store (Flat Rate R$ 15,00; Table Rate by package weight: R$ 20,00 below 5 kg, R$ 35,00 from 5 kg, R$ 12,50 for postcode
88010400) the estimate for CEP 88010-400 lists Flat Rate R$ 15,00 and Best Way R$ 12,50; for CEP 01310-100 with six 1 kg items,
Best Way R$ 35,00. The end-to-end tests must keep passing.

## End-to-end tests

They need a Magento store with the module installed (see `e2e/README.md`). `QA_BROWSER` can point to a Chromium-based
browser you already have, so no browser download is needed.
