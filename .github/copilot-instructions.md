# Code review guide (Magento 2 / Adobe Commerce module)

Review the **whole module** the pull request touches, not only the diff: a changed file can break a caller, a template, a
translation or a test that did not change. Say what is wrong, why it matters, and the smallest fix.

## Severities

Start every comment with one of these tags.

| Tag | Meaning | Examples |
|---|---|---|
| **[Blocker]** | Must be fixed before merge: security hole, data loss, broken behavior, broken build | Unescaped output, SQL built from input, a scope-less config read that leaks the default value into every store |
| **[Major]** | Wrong or fragile, will hurt soon | Logic in a template, `ObjectManager` in business code, missing test for new behavior, deprecated API |
| **[Minor]** | Worth fixing in this PR, not worth blocking | Naming, duplicated code, comment that lies, missing docblock |
| **[Nit]** | Taste; the author may ignore it | Ordering, wording |

A refactor must not change behavior. If it does, that is at least **[Major]** unless the description says so and tests cover it.

## General

- One concern per pull request; the description says what changed and how to verify it by hand.
- No dead code, commented-out code, debugging leftovers or `TODO` without an issue.
- Small functions with a single job; early returns over nesting; no flag arguments.
- Names say what a thing is. No abbreviations the next reader has to decode.
- Prefer deleting code to adding code. Do not add configuration nobody asked for.
- Every new class, method and behavior has a test (see Tests). A bug fix has a test that fails without the fix.

## JavaScript (RequireJS / AMD)

- `define([...], function () {...})` modules with `'use strict'`; no globals, no `var`, no `eval`, no `alert`, no `console.*`.
- `===` and `!==` always (`== null` is the only accepted loose comparison). Braces on every `if`/`for`/`while`.
- Strings shown to the user go through `$t()` as **whole sentences with placeholders**:
  `$t('%1 x %2 (with %3% interest per month)')`, never concatenated pieces (`$t('with ') + rate + ...`). Fill the
  placeholders after translating. Translators must see the full sentence.
- Never build HTML by concatenating strings. Use a template (`mage/template`, Knockout) or create elements and set their
  content with `.text()`. Data coming from the server or the shopper is text, never markup.
- Select through `data-role` / `data-action` attributes, restricted to the widget element (`this.element.find(...)`).
  No fixed `id`s, no document-wide selectors for your own markup. Keep a reference to an element before a library moves it.
- Widgets (`$.widget`) or `function (config, element)` modules, initialised through `x-magento-init` / `data-mage-init`,
  not loaded on every page through `requirejs-config.js` `deps`.
- Money and numbers: format with the store's price format (`Magento_Catalog/js/price-utils`), never `toFixed` or the
  browser locale. Round only when displaying. Never parse a formatted price; read the numeric amount.
- Cache jQuery lookups, namespace events, unbind what you bind, and do not poll or observe the whole document.
- Max 120 columns, 4 spaces, semicolons, no trailing commas, newline at the end of the file.

## LESS / CSS

- **BEM in kebab-case with the module prefix**: `.vendor-module__element--modifier` (`.fineweb-shipping-estimate__submit`).
  No tag or `id` selectors, no `!important`, no inline `style` in templates.
- At most **3 nesting levels**. Flatten with BEM instead of descendant chains.
- **Mobile first**: base rules for small screens, `.media-width(@extremum, @break)` with `'min'` for larger ones.
- Use the theme's variables (`@indent__s`, `@screen__m`, colors) instead of magic numbers.
- Single quotes, newline at the end of the file, no vendor prefixes (the build adds them).
- Reuse the theme's classes (`action primary`, `field`, `control`) for look; add BEM classes for the module's own hooks.

## PHTML

- Escape **every** output: `$escaper->escapeHtml()`, `escapeHtmlAttr()`, `escapeUrl()`, `escapeJs()`.
  `/* @noEscape */` only where the value is already safe (for example `JsonHexTag` output in a `<script>`), with a reason.
- No logic and no `$block->helper()`: get data from a **ViewModel** (`ArgumentInterface`) passed as a layout `argument`.
  Templates loop and print; they do not query, calculate or format.
- `declare(strict_types=1)`, typed `/** @var */` headers, `__()` for every visible string (and escape its result).
- No inline `style`, no inline event handlers, no `<script>` with logic: configure widgets with `x-magento-init`.
- Hooks for scripts and tests are `data-*` attributes, not ids.

## PHP backend

- `declare(strict_types=1)`; typed properties, parameters and returns; constructor promotion with `readonly`.
- **Dependency injection only**: no `ObjectManager::getInstance()`, no `new` for injectable services, no inheritance for reuse.
- Business logic lives in **services** behind an interface in `Api/`, wired with a `<preference>` in `etc/di.xml`.
  **Controllers** read and validate the request, call the service and return the result; nothing else.
  Blocks that only feed a template become **ViewModels**.
- Controllers implement `HttpGetActionInterface` / `HttpPostActionInterface`; POST endpoints are CSRF protected
  (form key) and validate every input. Answer errors with a proper HTTP status and a stable JSON shape.
- **Multi-store**: every `scopeConfig->getValue()` / `isSetFlag()` passes a scope. A read without one returns the default
  scope and silently ignores website and store view values. Check the store of the current request, not the default one.
- Admin configuration declares its `<resource>` in `etc/acl.xml`, a sensible default in `etc/config.xml`, and a
  `showIn*` that matches the scope the code reads.
- No deprecated Magento API (`ArrayInterface`, `Json\Helper\Data`, `setup_version`, `Magento\Framework\Pricing\Helper\Data`
  for new code...). Use the interface Magento recommends today.
- Throw `LocalizedException` for messages a user may see; log anything else and answer with a generic message. Never
  return `$exception->getMessage()` of an unexpected error.
- Performance: no queries or loads in loops, no repeated config or collection reads, respect full page cache
  (private content / cache keys when output depends on the customer, store or currency).
- Keep classes small. `phpmd` (Magento rule set), `phpcs` (Magento2, warnings count) and `phpstan` level 9 must pass.

## i18n

- Every visible string goes through `__()` (PHP/PHTML) or `$t()` (JS), as a **complete sentence** with `%1`, `%2`
  placeholders. Never build a sentence from pieces or plurals by hand.
- `i18n/<locale>.csv` has the **exact** phrase as the key (UTF-8 **without BOM**, newline at the end). Add `en_US.csv` and
  every supported locale; remove entries nothing uses.
- Numbers, dates and currency come from the store's formatters, never from hard-coded separators.

## Tests

- **Unit tests** mirror the structure: `Foo/Bar.php` → `Test/Unit/Foo/BarTest.php`. Every production class has one
  (except `registration.php`, `Api/` and interfaces). Coverage is at least **70% per file**.
- One behavior per test, named after it. Arrange / act / assert; no logic, loops or branching in a test.
- Mock only the boundaries (Magento services), never the class under test. Use data providers for variants.
- Expected values are written by hand and kept fixed in the test; they are never computed with the code under test.
- **End-to-end tests** (Playwright) cover what a shopper or admin sees, against a real store, with hand-calculated values.
- A change that cannot be tested by hand or by a test is not ready.

## Security

- **XSS**: escape on output, build DOM with `.text()`, serialize data for scripts with `JsonHexTag`.
- **Injection**: never concatenate input into SQL, shell commands or paths; use bound parameters and the repositories.
- **Access control**: admin actions check an ACL resource; frontend endpoints validate input and never trust the client for
  prices, quantities or identifiers of other customers.
- **Secrets**: none in the repository, logs or error messages. No `localhost`, internal hosts or credentials in code.
- **Superglobals**: no `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER`; use the request object.
- **Output of errors**: do not leak stack traces, SQL or internal messages to the customer.

## Red flags

| Seen in the PR | Why it is a problem | Severity |
|---|---|---|
| `ObjectManager::getInstance()` outside a factory or test | Hidden dependency, untestable | **[Major]** |
| `scopeConfig->getValue($path)` with no scope | Ignores website and store view values | **[Blocker]** |
| `/* @noEscape */` without a reason, or `echo` of a variable in PHTML | XSS | **[Blocker]** |
| HTML built with `+` or template strings and `.html()` | XSS, impossible to translate | **[Blocker]** |
| `$t('...') + value + $t('...')` | Untranslatable sentence | **[Major]** |
| Fixed `id` in markup or `$('#id')` | Collides on the page, couples script and markup | **[Major]** |
| Business logic in a controller, block or template | Not reusable, not testable | **[Major]** |
| New class without `Test/Unit/...Test.php` | Behavior without a safety net | **[Major]** |
| Expected values computed with the code under test | The test proves nothing | **[Major]** |
| `catch (\Exception $e) {}` or returning `$e->getMessage()` | Swallowed errors or leaked internals | **[Major]** |
| `var_dump`, `print_r`, `console.log`, `die`, `exit`, `eval` | Debugging leftover or dangerous | **[Blocker]** |
| Query or `load()` inside a loop | N+1, slow pages | **[Major]** |
| Inline `style`, `!important`, nesting deeper than 3 levels | Unmaintainable CSS | **[Minor]** |
| CSV with a BOM or without a final newline, or a phrase that does not match the code | Translation silently missing | **[Major]** |
| Behavior change inside a "refactor" | Surprise in production | **[Major]** |
| Commented-out code, `TODO` without an issue | Noise | **[Nit]** |
