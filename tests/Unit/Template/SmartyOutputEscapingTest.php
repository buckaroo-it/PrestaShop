<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

namespace Buckaroo\PrestaShop\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

/**
 * Mirrors the PrestaShop Validator "Security" checks on Smarty templates: every
 * output tag must go through an accepted sanitising modifier and the
 * number_format / string_format modifiers are not allowed.
 */
final class SmartyOutputEscapingTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/../../../views/templates';
    private const SAFE_MODIFIERS = ['escape', 'intval', 'floatval', 'boolval', 'json_encode'];

    public function testEveryOutputTagIsEscaped(): void
    {
        $problems = [];
        foreach ($this->templates() as $template) {
            $problems = array_merge($problems, $this->problems($template));
        }

        self::assertSame([], $problems, "Unescaped Smarty output:\n" . implode("\n", $problems));
    }

    public function testPaymentFeeTableRendersPreformattedAmounts(): void
    {
        $template = (string) file_get_contents(self::TEMPLATES . '/hook/payment-fee-table.tpl');

        self::assertStringNotContainsString('number_format', $template);
        self::assertStringNotContainsString('$currency->sign', $template);
        self::assertStringContainsString("{\$buckaroo_fee_display.tax_excl|escape:'htmlall':'UTF-8'}", $template);
        self::assertStringContainsString("{\$buckaroo_fee_display.tax|escape:'htmlall':'UTF-8'}", $template);
        self::assertStringContainsString("{\$buckaroo_fee_display.tax_incl|escape:'htmlall':'UTF-8'}", $template);
        // The numeric value is still used for the condition, never for display.
        self::assertStringContainsString('{if $buckaroo_fee.buckaroo_fee_tax_incl > 0}', $template);
    }

    public function testRivertyTermsLinkIsEscapedAndUsesHttps(): void
    {
        $template = (string) file_get_contents(self::TEMPLATES . '/hook/payment_afterpay.tpl');

        self::assertStringContainsString("href=\"{\$riverty_tc_url|escape:'htmlall':'UTF-8'}\"", $template);
        self::assertStringNotContainsString('{$riverty_tc_url}', $template);

        preg_match_all("/assign var='riverty_tc_url' value='([^']+)'/", $template, $urls);
        self::assertNotEmpty($urls[1]);
        foreach ($urls[1] as $url) {
            self::assertStringStartsWith('https://documents.riverty.com/', $url);
        }
    }

    /**
     * @return string[]
     */
    private function templates(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::TEMPLATES));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'tpl') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return string[]
     */
    private function problems(string $path): array
    {
        $problems = [];
        $template = (string) file_get_contents($path);
        $template = (string) preg_replace('/\{\*.*?\*\}/s', '', $template);
        $template = (string) preg_replace('/\{literal\}.*?\{\/literal\}/s', '', $template);

        foreach (preg_split('/\R/', $template) as $number => $line) {
            if (!preg_match_all('/\{\$[^}]*\}/', $line, $matches)) {
                continue;
            }
            foreach ($matches[0] as $tag) {
                if (preg_match('/\|\s*(number_format|string_format)/', $tag)) {
                    $problems[] = sprintf('%s:%d unsupported modifier in %s', basename($path), $number + 1, $tag);
                    continue;
                }
                $safe = false;
                foreach (self::SAFE_MODIFIERS as $modifier) {
                    if (preg_match('/\|\s*' . preg_quote($modifier, '/') . '\b/', $tag)) {
                        $safe = true;
                    }
                }
                if (!$safe) {
                    $problems[] = sprintf('%s:%d unescaped output %s', basename($path), $number + 1, $tag);
                }
            }
        }

        return $problems;
    }
}
