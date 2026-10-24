# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-24

Quality release: no change in what the storefront shows (the end-to-end tests pass with the same prices), but the module is
now structured, tested and checked the way a Magento 2 module should be.

### Changed

- The estimate logic moved from the controller into `Model/ShippingEstimator`, behind `Api/ShippingEstimatorInterface`
  (bound in `etc/di.xml`). The controller only reads and validates the request, calls the service and returns the JSON.
- The block that fed the template is a **ViewModel** passed as a layout argument.
- A shipping method is described with the phrases `%1 - %2` and `%1 - %2 - %3` instead of glued pieces; the CSV files (an
  `en_US.csv` was added) carry exactly the phrases the code uses.
- The markup uses `data-role` / `data-action` hooks scoped to the widget element instead of fixed ids; CSS classes are BEM
  (`fineweb-shipping-estimate__*`).

### Added

- CI jobs: blacklist, `composer validate --strict`, tests exist, multi-store scope, phpcs with warnings blocking, phpmd (Magento
  rule set), phpstan level 9, PHPUnit with a 70% per-file coverage gate on PHP 8.2 and 8.3 and ESLint 10.
- Local git hooks (`.githooks`, `setup-dev.sh`), the review guide (`.github/copilot-instructions.md`), a pull request
  template and `CLAUDE.md`.
- Unit tests for the service and the ViewModel (32 tests, 100% coverage of every file).

### Fixed

- The Composer package no longer carries the tests, the CI files and the tooling (`export-ignore`).
- The "The product could not be loaded." message is translated.

## [1.0.0] - 2026-10-24

First tagged release. It modernizes the module first published in 2020 for Magento 2.4.7+ and PHP 8.2/8.3.

### Added

- PHPUnit unit tests for the controller and the block.
- GitHub Actions workflow running phpcs (Magento2 coding standard), phpstan (level 9) and PHPUnit on PHP 8.2 and 8.3.
- Postcode mask (`00000-000`), Enter key support and a validation message for incomplete postcodes.
- `.gitignore` and this changelog.

### Changed

- Requires PHP `~8.2.0||~8.3.0` and Magento framework `^103.0.7` (Magento 2.4.7+). All Magento modules the code
  depends on are declared in `composer.json`, `minimum-stability: dev` was dropped and PSR-4 autoloading is configured.
- The controller implements `HttpPostActionInterface`, answers through `ResultFactory` instead of the deprecated
  `Json\Helper\Data`, and estimates with `TotalsCollector`, so cart-dependent rates such as Table Rate by weight see the
  real cart data.
- The quote uses the current store and prices are converted to the display currency.
- The chosen configurable, bundle and custom options are forwarded to the quote.
- The widget is initialised with `x-magento-init` instead of being loaded on every page.
- The license identifier is the SPDX `GPL-3.0-or-later`, as stated in the file headers. The duplicated `LICENSE.txt`
  was removed.

### Fixed

- The estimate never appeared on configurable and bundle products: the block was placed in
  `product.info.form.content`, which `form.phtml` only prints for products without options. It now lives in
  `product.info.main`.
- Only the last carrier was shown when several carriers returned rates.
- Rates that depend on the cart weight (Table Rate) were evaluated with 0 kg: 6 items of 1 kg were quoted in the
  0 kg tier instead of the 5 kg tier.
- Prices came back as HTML (`<span class="price">`) that the widget inserted as markup; they are plain text now.
- Errors were returned as a bare string that the widget could not render; they are now `{"error": {"message": "..."}}`
  with a proper HTTP status, and unexpected exceptions are logged instead of being shown to the customer.
- The error string that `Quote::addProduct()` returns when a product cannot be added (for example a missing
  configurable option) was ignored; it is now reported to the shopper.
- Carrier names, method titles and messages were concatenated into HTML; they are now inserted as text.
- The Enter key in the postcode field had no handler: the field lives inside the add-to-cart form, so Enter submitted
  that form instead of estimating.
- The "I do not know the postcode" link used plain HTTP (it only worked through a redirect) and had no `rel="noopener"`.

[1.1.0]: https://github.com/roni-clei-santos/m2-estimate-shipping-brazil/releases/tag/v1.1.0
[1.0.0]: https://github.com/roni-clei-santos/m2-estimate-shipping-brazil/releases/tag/v1.0.0
