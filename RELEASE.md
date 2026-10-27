# Release checklist

This file is not shipped in the release ZIP (see `.gitattributes`). Every item is mandatory; a release is published only when all of them are satisfied.

## 1. Bump the version

The version is declared in four places and they must be identical (`.github/scripts/validate-package.php` and `tests/Unit/Module/ModuleMetadataTest.php` fail otherwise):

| Location | Example |
|---|---|
| `buckaroo3.php` — `const MODULE_VERSION` | `'5.4.0'` |
| `buckaroo3.php` — `$this->version` in `initializeModuleInfo()` (must stay a string literal) | `'5.4.0'` |
| `config.xml` — `<version>` | `5.4.0` |
| `composer.json` — `version` | `5.4.0` |

After changing the `composer.json` version run `composer update --lock` and commit `composer.lock` as well: the lock file's content hash covers the version, and the release build refuses an out-of-date lock file. Add a `## vX.Y.Z` section to `CHANGELOG.md`, add an `upgrade/upgrade-X.Y.Z.php` script when the release needs one, and keep `$this->displayName` exactly `$this->l('Buckaroo Payments')`. The module name must never carry the version or change at runtime: PrestaShop uses it in the Module Manager and regenerates `config.xml` from it.

## 2. Rebuild the admin assets (only when `dev/` changed)

```bash
cd dev && npm ci && npm run build
```

`dev/vite.config.js` stamps the license header on `views/js/buckaroo.vue.js` and the hashed `vendor-*.js` / `i18n-*.js` chunks and removes stale chunks. Commit the regenerated files under `views/`.

## 3. Build and validate the ZIP locally

```bash
composer validate                     # composer.lock must be in sync with composer.json
composer validate-source              # index.php files, _PS_VERSION_ guards, license headers, Smarty escaping, metadata
composer build-release                # build/buckaroo3.zip from the working tree, then validates the ZIP
```

`vendor/` is not tracked in git. `build-release` exports the tree with `git archive`, runs `composer install --no-dev --classmap-authoritative` inside the export using the pinned `composer.lock`, and zips the result, so the ZIP always carries the dependency versions from the lock file regardless of what is installed locally. Building needs git, PHP with the zip extension and Composer. A git clone (or the GitHub "Source code" archive) contains no `vendor/` and is not installable; merchants must use the `buckaroo3.zip` release asset.

The ZIP validation checks the root structure (`buckaroo3/` only, no nested folder), required runtime files, production-only `vendor/`, compiled asset consistency, index files, guards, license headers, version consistency, metadata, static Context calls and Smarty escaping. Fix every failure before continuing.

## 4. Run the automated checks

- Unit tests: `composer test` (CI runs them on PHP 7.4, 8.1 and 8.3).
- Static analysis: `_PS_ROOT_DIR_=/path/to/prestashop composer phpstan` (CI runs it inside the official PrestaShop 1.7.8.x, 8.x and 9.x Docker images).
- The `Quality checks` workflow must be green on the release branch.

## 5. Run the official PrestaShop Validator on the exact release ZIP

1. Open https://validator.prestashop.com/module/ and upload **the ZIP that will be attached to the release** (the artifact of the `Package checks` job, or `build/buckaroo3.zip` built from the release commit). Never validate a GitHub "Source code" archive: it unpacks to `PrestaShop-x.y.z/` and is not installable.
2. Review **all** categories: Requirements, Security, Structure, Licenses, Errors, Standards and Compatibility. A report marked *Incomplete* must be re-run until every section is present.
3. Required outcome: zero findings in Requirements, Security, Structure, Licenses and Errors. The sections below may only contain the accepted findings listed. Any other finding blocks the release. The admin controllers extend Symfony's `AbstractController` on purpose: PrestaShop's `FrameworkBundleAdminController` is deprecated and `PrestaShopAdminController` does not exist before PrestaShop 9, so do not reintroduce either.
4. Record the validation date, the ZIP checksum (`sha256sum build/buckaroo3.zip`) and the accepted findings in the release notes.

### Accepted findings

| Finding | Files | Reason | Review when |
|---|---|---|---|
| Compatibility section: static-analysis style findings (unused properties, `ModuleCore\|false` return types, legacy `Cookie` properties, `Logger` constants resolved from `api/corelogger.php`, etc.) | various | These are pre-existing code-quality findings that do not affect installation or payment processing; they mirror the PHPStan baseline in `tests/phpstan/baseline/`. Entries are removed from the baseline as the code is cleaned up; no new findings may be added. | Continuous; review the Compatibility section on every release and track the count going down. |
| Standards section: "An error happened during this validation step" | n/a | Failure inside the validator service itself (also present in the merchant's report of 2026-10-24). Re-run the validation; if the section keeps failing, contact the PrestaShop validator team with the report id. | Every release until the section renders. |

Official validator runs for the 5.4.0 release candidate (2026-10-26, when it was still numbered 5.3.1): reports `module/3782553`, `module/3782563` and `module/3782572` (the last one on the ZIP with the admin controllers moved to Symfony's AbstractController). Final result: Requirements, Structure, Licenses, Security, Translations, Optimizations and Errors clean ("Everything looks good in this section!"). Compatibility lists 202 pre-existing static-analysis findings (tracked in the backlog, see above); Standards failed inside the validator in all three runs.

## 6. Installation smoke tests

Install the release ZIP through **Modules → Module Manager → Upload a module** on each supported major version (the local Docker set-up in the parent repository and the `prestashop/prestashop` images cover this):

| PrestaShop | PHP | Checks |
|---|---|---|
| 1.7.8.x | 7.4 / 8.0 | |
| 8.x | 8.1 | |
| 9.x | 8.2 / 8.3 | |

For every environment verify: the upload installs without errors; the Module Manager shows **Buckaroo Payments**, the correct version and author **Buckaroo** in the *Payment* category; the configuration page loads and shows the version; no PHP fatal errors or warnings in the server/PrestaShop logs with debug mode on; uninstall and reinstall work.

## 7. Payment regression tests (test mode, never live transactions)

- iDEAL / Wero: initiation, redirect, return, push, final order status, no duplicate orders.
- Credit card (Hosted Fields): initiation, authorization, success and failure handling.
- Klarna: initiation, customer data, return and push, order state transitions.
- Refunds from the PrestaShop order page: request construction, currency, submission, refund status.
- Capture, cancellation and the payment-method configuration pages.

## 8. Publish

1. Merge to `master`, tag `vX.Y.Z` (the tag must match the module version; the `Pack on release` workflow refuses otherwise).
2. Create the GitHub release. The workflow exports the release commit with `git archive`, installs the production dependencies from `composer.lock`, validates the ZIP and attaches `buckaroo3.zip`.
3. Download the attached asset and confirm its checksum matches the ZIP that passed the official validator in step 5. If it differs, re-run the validator on the attached asset before announcing the release.
4. Update the related GitHub issues with the release version, the validator result and the tested PrestaShop/PHP combinations.
