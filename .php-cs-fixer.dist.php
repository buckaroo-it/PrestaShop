<?php
/**
 * PHP CS Fixer configuration.
 *
 * Mirrors the "Standards" section of the official PrestaShop Validator, which
 * runs PHP CS Fixer with the PrestaShop coding standard shipped in
 * prestashop/php-dev-tools. Run `composer cs-check` before opening a pull
 * request and `composer cs-fix` to apply the fixes.
 */

$config = new class() extends PrestaShop\CodingStandards\CsFixer\Config {
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            // Recent PHP CS Fixer releases also want a blank line between `<?php`
            // and a leading comment. PrestaShop's license header sits directly
            // under the open tag (as in PrestaShop itself and header-stamp), so
            // keep that layout.
            'blank_line_after_opening_tag' => false,
        ]);
    }
};

$config->setUsingCache(true)
    ->getFinder()
    ->in(__DIR__)
    ->exclude(['vendor', 'dev', 'build', 'var', 'tools']);

return $config;
