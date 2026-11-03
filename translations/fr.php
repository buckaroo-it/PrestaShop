<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

global $_MODULE;
$_MODULE = [];

// --- Riverty (Afterpay) ---
// Date of birth
$_MODULE['<{buckaroo3}prestashop>payment_4965e9e891c3381d1ec7379f036131de'] = 'Date de naissance :';
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_4965e9e891c3381d1ec7379f036131de'] = 'Date de naissance :';
// Date of birth (shipping)
$_MODULE['<{buckaroo3}prestashop>payment_aff945f1c24fe3821254851ce65e9525'] = 'Date de naissance (adresse de livraison) :';
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_aff945f1c24fe3821254851ce65e9525'] = 'Date de naissance (adresse de livraison) :';
// Terms & conditions checkbox
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_ec003bdd4337c2e6c4c424e79dc41bf3'] = "J'accepte les conditions générales de Riverty.";
// Phone number label
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_d4cec97dc40e65adf4f9c8e6591b0b4f'] = 'Numéro de téléphone (adresse de facturation) :';
// CoC-number
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_c66a608eb37f73d6474df12b5f15e380'] = 'Numéro de registre du commerce :';
// Identification Number (Finland)
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_f7042b0fa1e4c4a5c96b5e7dd5ce432a'] = "Numéro d'identification :";
// Address errors
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_b6d9d9454b9542fde21e2cb7f5ba054c'] = 'Adresse de facturation invalide, numéro de maison introuvable.';
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_9049de8f08e66a01b91fb26e9af52ed3'] = 'Adresse de livraison invalide, numéro de maison introuvable.';

// --- Shared gender labels ---
$_MODULE['<{buckaroo3}prestashop>payment_caa5b7a48ce4b1c1492c445446433fb5'] = 'Sexe :';
$_MODULE['<{buckaroo3}prestashop>payment_54588cfd99c9e853c9e7710dbae8d8cd'] = 'Il/lui';
$_MODULE['<{buckaroo3}prestashop>payment_68b62df49a394689d2779ec0e4534e9b'] = 'Elle/elle';
$_MODULE['<{buckaroo3}prestashop>payment_39056d267dad6001a11d789756d26512'] = 'Iel/iel';
$_MODULE['<{buckaroo3}prestashop>payment_b8ce55ba025e0b423379182b33ea6773'] = 'Je préfère ne pas préciser';
// JJ / MM / AAAA
$_MODULE['<{buckaroo3}prestashop>payment_350bfcb1e3cfb28ddff48ce525d4f139'] = 'JJ';
$_MODULE['<{buckaroo3}prestashop>payment_ad05f78187c942f9dd521605fa81f1ba'] = 'MM';
$_MODULE['<{buckaroo3}prestashop>payment_8df34bb962577b90d574a51ed2ca757f'] = 'AAAA';
// --- Billink ---
// CoC-number (Billink)
$_MODULE['<{buckaroo3}prestashop>payment_billink_c66a608eb37f73d6474df12b5f15e380'] = 'Numéro de registre du commerce :';
// Please select gender (legacy / shared)
$_MODULE['<{buckaroo3}prestashop>payment_52db1a3d3cd7bf584459aa4c2db08b25'] = 'Veuillez sélectionner votre genre :';
// Apple Pay express button
$_MODULE['<{buckaroo3}prestashop>buckaroo3_9951b21e5273124ff34d11ec2f4ba231'] = 'Apple Pay n’est pas disponible pour le moment. Veuillez réessayer ou choisir un autre moyen de paiement.';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_a5ab7d8806c91c3c058255fddca2ba06'] = 'Votre paiement Apple Pay n’a pas pu être lancé. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_17ffb24862c7db18f577ee8328688a9d'] = 'Le moyen de paiement choisi n’est pas disponible.';
$_MODULE['<{buckaroo3}prestashop>applepayexpress_9951b21e5273124ff34d11ec2f4ba231'] = 'Apple Pay n’est pas disponible pour le moment. Veuillez réessayer ou choisir un autre moyen de paiement.';
$_MODULE['<{buckaroo3}prestashop>applepayexpress_395a6203037d74e78e4473a56fba7612'] = 'Votre session Apple Pay a expiré. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>applepayexpress_478b602c0dbff0663d136682a74bcab8'] = 'Apple Pay n’est pas disponible pour cette commande. Veuillez choisir un autre moyen de paiement.';
$_MODULE['<{buckaroo3}prestashop>applepayexpress_bd4d7b4f4b3a53570b677dd6a31f26f6'] = 'Le montant total de la commande a changé. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_5cdfa1df00601931e222cb6382d7e0da'] = 'Ce produit ne peut pas être acheté avec Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_b31c9745e443d5d5325d5654ecec3f81'] = 'Veuillez sélectionner au moins la quantité minimale pour ce produit.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_2cb44aa56605e05e0b912131875edcb7'] = 'Il n’y a pas assez de produits en stock.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_879f6b8877752685a966564d072f498f'] = 'Votre panier est vide.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_1e89432e47a87bea7a07ea326bda7347'] = 'Votre panier n’a pas pu être préparé pour Apple Pay. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_2d3a357afaabc2209bdaaac83c776019'] = 'Nous ne livrons pas à cette adresse. Veuillez choisir une autre adresse.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_3db1f3472fc989f357b856a1d3a3154c'] = 'Le mode de livraison choisi n’est pas disponible pour cette adresse.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_2b4d238f91479d8ea9369233d43609bf'] = 'Apple Pay n’a pas transmis d’adresse de facturation. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_040ab967bd054299a41b143eb4719966'] = 'Apple Pay n’a pas transmis d’adresse de livraison. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_478b602c0dbff0663d136682a74bcab8'] = 'Apple Pay n’est pas disponible pour cette commande. Veuillez choisir un autre moyen de paiement.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_bd4d7b4f4b3a53570b677dd6a31f26f6'] = 'Le montant total de la commande a changé. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_578410aa770f955b694795be351fa34a'] = 'Nous n’avons pas pu calculer les frais de livraison. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_ea9cf7e47ff33b2be14e6dd07cbcefc6'] = 'Livraison';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_104d9898c04874d0fbac36e125fa1369'] = 'Réduction';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_484f5a79672cebe198ebdde45a1d672f'] = 'Emballage cadeau';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_020c2091aec3019d02193cef080bcf97'] = 'Frais de paiement';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_12dbd3ce741c680b57975bd34313a4ee'] = 'Veuillez vous connecter pour payer avec Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_22c6b3c60efa647658199ff050cf9c91'] = 'Veuillez saisir une adresse e-mail valide dans Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_dc6ad90e43c36e0e60699162cee13661'] = 'Nous n’avons pas pu créer votre compte client. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_80ee04aa268b1a1ecaaf8908228ed9a7'] = 'Veuillez saisir un prénom et un nom valides dans Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_f315359c29753c1d98c4967d4993b76d'] = 'Veuillez saisir une adresse valide dans Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_5c06411de545445fa80bd3631379da33'] = 'Veuillez saisir une ville valide dans Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_3fe300358b5bc01cb0413f4351737b1b'] = 'Veuillez saisir un code postal valide dans Apple Pay.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_f171d1706ffa1f5ed63afb027f729c27'] = 'Nous n’avons pas pu enregistrer votre adresse. Veuillez réessayer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_f04f000f8861cdb34519bdc6d1e93108'] = 'Nous ne livrons pas dans ce pays. Veuillez choisir une autre adresse.';
