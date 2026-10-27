# Contribution Guidelines

### Repository setup:
- Fork the repository to your account.
- More details about [how to fork a repo](https://docs.github.com/en/github/getting-started-with-github/fork-a-repo) can be found [here](https://docs.github.com/en/github/getting-started-with-github/fork-a-repo).

### Making changes:
- Create a branch from the develop branch.
- The name of the branch should be something like: `feature/GITHUB-ISSUE-ID-slug` (eg: `feature/50-configprovider-update`)
- Your code changes should follow the PrestaShop coding standard.
- Including unit tests is encouraged.

### Quality checks:
Every pull request runs the checks below; run them locally before opening the PR.

```bash
composer install                      # adds the dev tools (PHPUnit, PHPStan, php-dev-tools) on top of the shipped vendor/
composer test                         # PHPUnit
composer validate-source              # local equivalent of the PrestaShop Validator checks
composer build-release                # builds build/buckaroo3.zip from the working tree and validates it
_PS_ROOT_DIR_=/path/to/prestashop composer phpstan   # PHPStan, needs a PrestaShop installation
composer install --no-dev             # restore the production vendor/ before committing
```

The shipped `vendor/` directory is committed **without** development packages. Always run `composer install --no-dev` before committing changes under `vendor/`; the package checks fail when development packages leak into it.

Files that must never ship in the release ZIP (tests, `dev/`, CI configuration, dotfiles) are listed in `.gitattributes` with `export-ignore`. The release ZIP is built with `git archive`, which honours that list.

### Pull Request:
- Open the PR to develop branch.
- If there is no issue referenced, add a description about the problem and the way it is being solved.
- Allow edits from maintainers.

### Releasing:
See [RELEASE.md](RELEASE.md) for the release checklist, including the mandatory PrestaShop Validator run.
