# Contribution Guidelines

### Repository setup:
- Fork the repository to your account.
- More details about [how to fork a repo](https://docs.github.com/en/github/getting-started-with-github/fork-a-repo) can be found [here](https://docs.github.com/en/github/getting-started-with-github/fork-a-repo).

### Making changes:
- Create a branch from the develop branch.
- The name of the branch should be something like: `feature/GITHUB-ISSUE-ID-slug` (eg: `feature/50-configprovider-update`)
- Your code changes should follow the PrestaShop coding standard (`composer cs-fix` formats them, see below).
- Including unit tests is encouraged.

### Quality checks:
Every pull request runs the checks below; run them locally before opening the PR.

```bash
composer install                      # installs the pinned dependencies plus the dev tools (PHPUnit, PHPStan, php-dev-tools)
composer test                         # PHPUnit
composer cs-check                     # PHP CS Fixer with the PrestaShop ruleset (the validator's "Standards" section); `composer cs-fix` applies the fixes
composer validate-source              # local equivalent of the PrestaShop Validator checks
composer build-release                # builds build/buckaroo3.zip from the working tree and validates it
_PS_ROOT_DIR_=/path/to/prestashop composer phpstan   # PHPStan, needs a PrestaShop installation
```

`vendor/` is **not** tracked in git. A fresh clone needs `composer install` (or `composer install --no-dev` for a shop that runs from the clone) before the module works. The versions that ship are pinned in `composer.lock`; to change a dependency, edit `composer.json`, run `composer update <package>` and commit both files. `composer.json` pins `config.platform.php` to 7.4 so updates keep resolving packages that run on PHP 7.4 whatever PHP you run Composer with.

Files that must never ship in the release ZIP (tests, `dev/`, CI configuration, dotfiles) are listed in `.gitattributes` with `export-ignore`. The release ZIP is built by `.github/scripts/build-release.sh`: it exports the tree with `git archive`, which honours that list, installs the production dependencies from `composer.lock` into the export and zips the result. Merchants must install that `buckaroo3.zip` release asset; the GitHub "Source code" archives contain no `vendor/` and are not installable.

### Pull Request:
- Open the PR to develop branch.
- If there is no issue referenced, add a description about the problem and the way it is being solved.
- Allow edits from maintainers.

### Releasing:
See [RELEASE.md](RELEASE.md) for the release checklist, including the mandatory PrestaShop Validator run.
