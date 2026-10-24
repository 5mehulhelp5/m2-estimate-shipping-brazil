<!--
    Thanks for contributing! Please fill in the sections below.
    Keep the pull request focused on one concern.
-->

### Description

<!-- What does this pull request change, and why? Mention anything a reviewer should look at first. -->

### Fixed Issues

<!-- List the issues this pull request fixes, one per line, so GitHub closes them on merge: -->
<!-- 1. Fixes #1 -->

### Manual testing scenarios

<!-- Steps to verify the change by hand, with the expected result of each: -->
1. ...
2. ...

### Contribution checklist

- [ ] The change does not alter behavior outside its description (a refactor keeps the end-to-end tests passing).
- [ ] New or changed behavior has unit tests (`Test/Unit` mirrors the module structure) and the 70% per-file coverage gate passes.
- [ ] `composer check` passes (blacklist, phpcs with warnings, phpmd, phpstan level 9, tests, coverage) and `npm run lint` is clean.
- [ ] Visible strings use whole phrases with placeholders in `__()` / `$t()`, and the CSV files are updated (UTF-8, no BOM, final newline).
- [ ] Every `scopeConfig` read passes its scope; templates escape their output and get data from ViewModels.
- [ ] The end-to-end tests were run when the storefront or admin changed, with the screenshots updated if the UI changed.
- [ ] `CHANGELOG.md` is updated for user-visible changes.
