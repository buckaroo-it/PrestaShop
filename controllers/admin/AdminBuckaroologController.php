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

class AdminBuckaroologController extends AdminControllerCore
{
    public function __construct()
    {
        $this->lang = Configuration::get('PS_LANG_DEFAULT');
        $this->bootstrap = true;
        parent::__construct();
    }

    public function renderList()
    {
        $file = dirname(__FILE__) . '/../../api/log/report_log.txt';
        $returndata = [];
        if (file_exists($file)) {
            $data = [];
            $handle = @fopen($file, 'r');
            if ($handle) {
                while (($buffer = fgets($handle, 4096)) !== false) {
                    $data[] = $buffer;
                }
                fclose($handle);
            }
            if (!empty($data)) {
                $data = array_reverse($data);
                $i = 1;
                foreach ($data as $d) {
                    $tmp = explode('|||', $d);
                    if (!empty($tmp[1])) {
                        list($time, $value) = $tmp;
                    } else {
                        $time = 'unknown';
                        $value = $d;
                    }
                    $returndata[$i]['id'] = $i;
                    $returndata[$i]['time'] = $time;
                    $returndata[$i]['value'] = $value;
                    ++$i;
                }
            }
        }
        $this->context->smarty->assign(['data' => $returndata]);

        return $this->context->smarty->fetch(dirname(__FILE__) . '/../../views/templates/admin/buckaroolog.tpl');
    }
}
