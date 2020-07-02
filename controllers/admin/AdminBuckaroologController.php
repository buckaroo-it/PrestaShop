<?php
/**
 *
 *
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

class AdminBuckaroologController extends AdminControllerCore
{
    public function __construct()
    {
        $this->lang = (!Tools::getIsset($this->context->cookie) || !is_object(
            $this->context->cookie
        )) ? (int) (Configuration::get('PS_LANG_DEFAULT')) : (int) ($this->context->cookie->id_lang);
        $this->bootstrap = true;
        parent::__construct();
    }

    public function display()
    {
        parent::display();
    }

    public function renderList()
    {
        $file       = dirname(__FILE__) . '/../../api/log/report_log.txt';
        $returndata = array();
        if (file_exists($file)) {
            $data   = array();
            $handle = @fopen($file, "r");
            if ($handle) {
                while (($buffer = fgets($handle, 4096)) !== false) {
                    $data[] = $buffer;
                }
                fclose($handle);
            }
            if (!empty($data)) {
                $data = array_reverse($data);
                $i    = 1;
                foreach ($data as $d) {
                    $tmp = explode("|||", $d);
                    if (!empty($tmp[1])) {
                        list($time, $value) = $tmp;
                    } else {
                        $time  = 'unknown';
                        $value = $d;
                    }
                    $returndata[$i]["id"]    = $i;
                    $returndata[$i]["time"]  = $time;
                    $returndata[$i]["value"] = $value;
                    $i++;
                }
            }
        }
        $this->context->smarty->assign(array("data" => $returndata));
        return $this->context->smarty->fetch(dirname(__FILE__) . '/../../views/templates/admin/buckaroolog.tpl');
    }
}
