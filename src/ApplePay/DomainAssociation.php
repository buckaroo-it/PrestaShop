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

namespace Buckaroo\PrestaShop\Src\ApplePay;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Places Buckaroo's Apple Pay domain association file in /.well-known/ of the
 * shop, where Apple fetches it to verify the domain.
 *
 * An existing file is never replaced: it may belong to another Apple Pay
 * provider of the shop.
 */
final class DomainAssociation
{
    public const FILE_NAME = 'apple-developer-merchantid-domain-association';

    public const STATUS_INSTALLED = 'installed';
    public const STATUS_PRESENT = 'present';
    public const STATUS_OTHER_FILE = 'other_file';
    public const STATUS_FAILED = 'failed';

    /** @var string */
    private $shopRoot;

    /** @var string */
    private $sourceFile;

    public function __construct(string $shopRoot, string $sourceFile)
    {
        $this->shopRoot = rtrim($shopRoot, '/\\');
        $this->sourceFile = $sourceFile;
    }

    public static function forModule(string $moduleDir): self
    {
        return new self(_PS_ROOT_DIR_, rtrim($moduleDir, '/\\') . '/views/apple/' . self::FILE_NAME);
    }

    public function getDestinationFile(): string
    {
        return $this->shopRoot . '/.well-known/' . self::FILE_NAME;
    }

    /**
     * @return string one of the STATUS_* constants
     */
    public function install(): string
    {
        $source = @file_get_contents($this->sourceFile);
        if ($source === false || $source === '') {
            return self::STATUS_FAILED;
        }

        $destination = $this->getDestinationFile();
        if (is_file($destination)) {
            return @file_get_contents($destination) === $source ? self::STATUS_PRESENT : self::STATUS_OTHER_FILE;
        }

        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return self::STATUS_FAILED;
        }

        return @file_put_contents($destination, $source) === strlen($source)
            ? self::STATUS_INSTALLED
            : self::STATUS_FAILED;
    }
}
