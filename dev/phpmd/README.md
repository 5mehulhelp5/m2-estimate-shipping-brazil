# PHPMD rule set

`ruleset.xml` is the PHPMD rule set Magento uses for its own static tests
(`dev/tests/static/testsuite/Magento/Test/Php/_files/phpmd/ruleset.xml`, from `magento/magento2-base` 2.4.9).
It is vendored here because that package is a 94 MB Magento root component, not something a module should require.

Only two things differ from the original:

- the `php-includepath` was dropped and the Magento rules are referenced by their path in this folder;
- `CodeMessDetector/` is a copy of `dev/tests/static/framework/Magento/CodeMessDetector` (Magento 2.4.8), the rule classes
  `AllPurposeAction`, `CookieAndSessionMisuse` and `UnusedFormalParameter` and their rule sets. They keep their
  Magento copyright headers and are autoloaded through `autoload-dev` in `composer.json`.

Run it from the module root: `composer phpmd`.
