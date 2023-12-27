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

namespace Buckaroo\PrestaShop\Controllers\admin;

use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;
use Buckaroo\PrestaShop\Src\Repository\RawGiftCardsRepository;

class Giftcards extends BaseApiController
{
    public function initContent()
    {
        try {
            $data = [
                'status' => true,
            ];
            switch (\Tools::getValue('action')) {
                case 'add':
                    $this->add();
                    $data['message'] = 'Giftcard added successfully';
                    $data['giftcard'] = RawGiftCardsRepository::getGiftcardById(\Db::getInstance()->Insert_ID())->toArray();
                    break;
                case 'delete':
                    $this->delete(\Tools::getValue('id'));
                    $data['message'] = 'Giftcard deleted successfully';
                    break;
                case 'update':
                    $this->update();
                    $data['message'] = 'Giftcard updated successfully';
                    $data['giftcard'] =  RawGiftCardsRepository::getGiftcardById(\Tools::getValue('id'))->toArray();
                    break;
                default:
                    $data['giftcards'] = RawGiftCardsRepository::getGiftCardsFromDB();
            }
            return $this->sendResponse($data);
        } catch (\Exception $e){
            return $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    private function add()
    {
        $giftCard = new BkGiftcards(\Tools::getAllValues());

        if(isset($_FILES['logo'])) {
            $fileName = $this->upload($_FILES['logo']);

            $giftCard->setLogo($fileName);
        }

        RawGiftCardsRepository::insertGiftCard($giftCard);
    }

    /**
     * @throws \Exception
     */
    private function delete(int $id) {

        $giftCard = RawGiftCardsRepository::getGiftcardById($id);

        if ($giftCard->getLogo()){
            if(!unlink(_PS_MODULE_DIR_.'buckaroo3/views/img/buckaroo/Giftcards/SVG/'.$giftCard->getLogo())){
                throw new \Exception('Could not delete giftcard logo');
            }
        }
        RawGiftCardsRepository::deleteGiftcard($id);
    }

    /**
     * @throws \Exception
     */
    private function update(){
        $giftCard = new BkGiftcards(\Tools::getAllValues());

        if(isset($_FILES['logo'])) {
            $oldGiftcard = RawGiftCardsRepository::getGiftcardById($giftCard->getId());
            if($oldGiftcard->getLogo()){
                if(!unlink(_PS_MODULE_DIR_.'buckaroo3/views/img/buckaroo/Giftcards/SVG/'.$oldGiftcard->getLogo())){
                    throw new \Exception('Could not delete giftcard logo');
                }
            }
            $fileName = $this->upload($_FILES['logo']);

            $giftCard->setLogo($fileName);
        }
        RawGiftCardsRepository::updateGiftcard($giftCard);
    }
    /**
     * @throws \Exception
     */
    private function upload($file){
        $target_dir = _PS_MODULE_DIR_.'buckaroo3/views/img/buckaroo/Giftcards/SVG/';

        $imageFileType = pathinfo($file['name'],PATHINFO_EXTENSION);

        $filename = tempnam($target_dir,'custom_logo_');

        unlink($filename);

        $filename = $filename.'.'.$imageFileType;
        // Check if file already exists
        if (file_exists($filename)) {
            throw new \Exception('Filename already exists.');
        }
        // Check if file is real
        if ($file['size']===0) {
            throw new \Exception('File is not a real image.');
        }
        // Allow certain file formats
        if($imageFileType != "svg") {
            throw new \Exception('Sorry, only SVG files are allowed.');
        }

        if ($filename && move_uploaded_file($file["tmp_name"], $filename))
        {
            return str_replace($target_dir,'',$filename);
        }
        else
        {
            throw new \Exception('Sorry, there was an error uploading your file.');
        }

    }
}
