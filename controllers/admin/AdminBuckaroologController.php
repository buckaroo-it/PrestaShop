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
if (!defined('_PS_VERSION_')) {
    exit;
}

use Buckaroo\PrestaShop\Src\Logs\LogFileReader;

class AdminBuckaroologController extends ModuleAdminController
{
    public function __construct()
    {
        $this->lang = Configuration::get('PS_LANG_DEFAULT');
        $this->bootstrap = true;
        parent::__construct();
    }

    public function renderList()
    {
        require_once dirname(__FILE__) . '/../../api/corelogger.php';
        $reader = new LogFileReader(CoreLogger::logDirectory());
        $files = $reader->listFiles();

        $names = array_column($files, 'name');
        $selected = (string) Tools::getValue('file');
        if (!in_array($selected, $names, true)) {
            $selected = $names ? $names[0] : '';
        }
        $level = (string) Tools::getValue('level');
        if (!in_array($level, LogFileReader::LEVELS, true)) {
            $level = '';
        }

        $read = $selected !== '' ? $reader->readEntries($selected, $level) : ['entries' => [], 'truncated' => false];

        $baseUrl = $this->context->link->getAdminLink('AdminBuckaroolog');
        foreach ($files as &$file) {
            $file['url'] = $baseUrl . '&file=' . rawurlencode($file['name']) . ($level !== '' ? '&level=' . $level : '');
            $file['size_label'] = Tools::formatBytes($file['size'], 1);
        }
        unset($file);

        $levels = [['label' => 'All', 'value' => '']];
        foreach (LogFileReader::LEVELS as $name) {
            $levels[] = ['label' => $name, 'value' => $name];
        }
        foreach ($levels as &$option) {
            $option['url'] = $baseUrl . '&file=' . rawurlencode($selected) . ($option['value'] !== '' ? '&level=' . $option['value'] : '');
        }
        unset($option);

        $this->context->smarty->assign([
            'files' => $files,
            'selected_file' => $selected,
            'levels' => $levels,
            'selected_level' => $level,
            'entries' => $read['entries'],
            'truncated' => $read['truncated'],
            'log_directory' => 'var/logs/buckaroo3/',
        ]);

        return $this->context->smarty->fetch(dirname(__FILE__) . '/../../views/templates/admin/buckaroolog.tpl');
    }
}
