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

namespace Buckaroo\PrestaShop\Src\Install;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Move the log files that module versions before 5.4.0 wrote to
 * modules/buckaroo3/api/log/ into the shop's var/logs/buckaroo3/ directory
 * and remove the old directory.
 *
 * The old location is inside the public module directory: on Nginx (and on
 * Apache without .htaccess support) its date-named files were downloadable.
 * The migration must never fail an upgrade, so every filesystem call is
 * tolerant and a file that cannot be moved is simply left where it is.
 */
class LogDirectoryMigrator
{
    /** @var string old directory, with trailing slash */
    private $source;

    /** @var string new directory, with trailing slash */
    private $target;

    public function __construct(?string $source = null, ?string $target = null)
    {
        $this->source = rtrim($source ?? _PS_MODULE_DIR_ . 'buckaroo3/api/log/', '/\\') . '/';
        $this->target = rtrim($target ?? \CoreLogger::logDirectory(), '/\\') . '/';
    }

    /**
     * @return int number of log files moved to the new directory
     */
    public function migrate(): int
    {
        if (!is_dir($this->source)) {
            return 0;
        }
        if (!is_dir($this->target) && !@mkdir($this->target, 0755, true) && !is_dir($this->target)) {
            return 0;
        }

        $moved = 0;
        foreach ((array) @scandir($this->source) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === false) {
                continue;
            }
            $path = $this->source . $entry;
            if (!is_file($path)) {
                continue;
            }
            if ($entry === 'index.php' || $entry === '.htaccess') {
                // Directory protection stubs of the old location; nothing to keep.
                @unlink($path);
                continue;
            }
            if ($this->moveLog($path, $this->target . $entry)) {
                ++$moved;
            }
        }
        @rmdir($this->source);

        return $moved;
    }

    /**
     * Rename when possible; append when the new directory already holds a file
     * of the same name (the new logger may have written there before the
     * upgrade script ran).
     */
    private function moveLog(string $from, string $to): bool
    {
        if (!file_exists($to)) {
            if (@rename($from, $to)) {
                return true;
            }
            if (@copy($from, $to)) {
                @unlink($from);

                return true;
            }

            return false;
        }

        $content = @file_get_contents($from);
        if ($content === false) {
            return false;
        }
        if (@file_put_contents($to, $content, FILE_APPEND | LOCK_EX) === false) {
            return false;
        }
        @unlink($from);

        return true;
    }
}
