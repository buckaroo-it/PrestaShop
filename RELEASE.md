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

Add a `## vX.Y.Z` section to `CHANGELOG.md`, add an `upgrade/upgrade-X.Y.Z.php` script when the release needs one, and keep `$this->displayName` exactly `$this->l('Buckaroo Payments')`. The module name must never carry the version or change at runtime: PrestaShop uses it in the Module Manager and regenerates `config.xml` from it.

## 2. Rebuild the admin assets (only when `dev/` changed)

```bash
cd dev && npm ci && npm run build
```

`dev/vite.config.js` stamps the license header on `views/js/buckaroo.vue.js` and the hashed `vendor-*.js` / `i18n-*.js` chunks and removes stale chunks. Commit the regenerated files under `views/`.

## 3. Build and validate the ZIP locally

```bash
composer install --no-dev             # shipped vendor/ must not contain dev packages
composer validate-source              # index.php files, _PS_VERSION_ guards, license headers, Smarty escaping, metadata
composer build-release                # build/buckaroo3.zip from the working tree, then validates the ZIP
```

The ZIP validation checks the root structure (`buckaroo3/` only, no nested folder), required runtime files, production-only `vendor/`, compiled asset consistency, index files, guards, license headers, version consistency, metadata, static Context calls and Smarty escaping. Fix every failure before continuing.

## 4. Run the automated checks

- Unit tests: `composer test` (CI runs them on PHP 7.4, 8.1 and 8.3).
- Static analysis: `_PS_ROOT_DIR_=/path/to/prestashop composer phpstan` (CI runs it inside the official PrestaShop 1.7.8.x, 8.x and 9.x Docker images).
- The `Quality checks` workflow must be green on the release branch.

## 5. Run the official PrestaShop Validator on the exact release ZIP

1. Open https://validator.prestashop.com/module/ and upload **the ZIP that will be attached to the release** (the artifact of the `Package checks` job, or `build/buckaroo3.zip` built from the release commit). Never validate a GitHub "Source code" archive: it unpacks to `PrestaShop-x.y.z/` and is not installable.
2. Review **all** categories: Requirements, Security, Structure, Licenses, Errors, Standards and Compatibility. A report marked *Incomplete* must be re-run until every section is present.
3. Required outcome: zero findings in Requirements, Security, Structure, Licenses and Errors. Any finding there blocks the release. The admin controllers extend Symfony's `AbstractController` on purpose: PrestaShop's `FrameworkBundleAdminController` is deprecated and `PrestaShopAdminController` does not exist before PrestaShop 9, so do not reintroduce either.
4. The Compatibility section reports static-analysis findings that mirror the PHPStan baseline in `tests/phpstan/baseline/`. Its count must not grow between releases; entries disappear as the code is cleaned up.
5. Record the validation date and the ZIP checksum (`sha256sum build/buckaroo3.zip`) in the release notes.

## 6. Installation smoke tests

Install the release ZIP through **Modules → Module Manager → Upload a module** on each supported major version (the official `prestashop/prestashop` Docker images cover this):

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
2. Create the GitHub release. The workflow builds the ZIP with `git archive`, validates it and attaches `buckaroo3.zip`.
3. Download the attached asset and confirm its checksum matches the ZIP that passed the official validator in step 5. If it differs, re-run the validator on the attached asset before announcing the release.
4. Update the related GitHub issues with the release version, the validator result and the tested PrestaShop/PHP combinations.
