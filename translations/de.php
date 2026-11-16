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
$_MODULE['<{buckaroo3}prestashop>payment_4965e9e891c3381d1ec7379f036131de'] = 'Geburtsdatum:';
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_4965e9e891c3381d1ec7379f036131de'] = 'Geburtsdatum:';
// Date of birth (shipping)
$_MODULE['<{buckaroo3}prestashop>payment_aff945f1c24fe3821254851ce65e9525'] = 'Geburtsdatum (Lieferadresse):';
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_aff945f1c24fe3821254851ce65e9525'] = 'Geburtsdatum (Lieferadresse):';
// Terms & conditions checkbox
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_ec003bdd4337c2e6c4c424e79dc41bf3'] = 'Ich akzeptiere die Allgemeinen Geschäftsbedingungen von Riverty.';
// Phone number label
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_d4cec97dc40e65adf4f9c8e6591b0b4f'] = 'Telefonnummer (Rechnungsadresse):';
// CoC-number
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_c66a608eb37f73d6474df12b5f15e380'] = 'Handelsregisternummer:';
// Identification Number (Finland)
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_f7042b0fa1e4c4a5c96b5e7dd5ce432a'] = 'Identifikationsnummer:';
// Address errors
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_b6d9d9454b9542fde21e2cb7f5ba054c'] = 'Ungültige Rechnungsadresse, Hausnummer nicht gefunden.';
$_MODULE['<{buckaroo3}prestashop>payment_afterpay_9049de8f08e66a01b91fb26e9af52ed3'] = 'Ungültige Lieferadresse, Hausnummer nicht gefunden.';

// --- Shared gender labels ---
$_MODULE['<{buckaroo3}prestashop>payment_caa5b7a48ce4b1c1492c445446433fb5'] = 'Geschlecht:';
$_MODULE['<{buckaroo3}prestashop>payment_54588cfd99c9e853c9e7710dbae8d8cd'] = 'Er/ihn';
$_MODULE['<{buckaroo3}prestashop>payment_68b62df49a394689d2779ec0e4534e9b'] = 'Sie/ihr';
$_MODULE['<{buckaroo3}prestashop>payment_39056d267dad6001a11d789756d26512'] = 'Divers';
$_MODULE['<{buckaroo3}prestashop>payment_b8ce55ba025e0b423379182b33ea6773'] = 'Keine Angabe';
// DD / MM / JJJJ
$_MODULE['<{buckaroo3}prestashop>payment_350bfcb1e3cfb28ddff48ce525d4f139'] = 'TT';
$_MODULE['<{buckaroo3}prestashop>payment_ad05f78187c942f9dd521605fa81f1ba'] = 'MM';
$_MODULE['<{buckaroo3}prestashop>payment_8df34bb962577b90d574a51ed2ca757f'] = 'JJJJ';
// --- Billink ---
// CoC-number (Billink)
$_MODULE['<{buckaroo3}prestashop>payment_billink_c66a608eb37f73d6474df12b5f15e380'] = 'Handelsregisternummer:';
// Please select gender (legacy / shared)
$_MODULE['<{buckaroo3}prestashop>payment_52db1a3d3cd7bf584459aa4c2db08b25'] = 'Bitte Geschlecht auswählen:';
// Apple Pay express button
$_MODULE['<{buckaroo3}prestashop>buckaroo3_9951b21e5273124ff34d11ec2f4ba231'] = 'Apple Pay ist derzeit nicht verfügbar. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_a4fea24d5435e7d2ffa0d6c1d5d60f9e'] = 'Google Pay ist derzeit nicht verfügbar. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_1bfc5be6816976d4bad04dfb06e9770a'] = 'PayPal ist derzeit nicht verfügbar. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsmethode.';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_065c0f86a51748c88ef793db1fb4d956'] = 'Die Versandkosten ergeben sich aus Ihrer Adresse';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_adb05ac441f7f3a2109cdaaa226c09ba'] = 'Ihre %s-Zahlung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>buckaroo3_17ffb24862c7db18f577ee8328688a9d'] = 'Die gewählte Zahlungsart ist nicht verfügbar.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_f643d6cffddc8fd80c67fa3de5b13b00'] = '%s ist derzeit nicht verfügbar. Bitte versuchen Sie es erneut oder wählen Sie eine andere Zahlungsart.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_e9c750fb17f59a277659a3bffef65f96'] = 'Ihre %s-Sitzung ist abgelaufen. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_fc4cfc6d7ad957681e7a463ec1df4ead'] = 'Dieses Produkt kann nicht mit %s gekauft werden.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_b31c9745e443d5d5325d5654ecec3f81'] = 'Bitte wählen Sie mindestens die Mindestmenge für dieses Produkt.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_2cb44aa56605e05e0b912131875edcb7'] = 'Es sind nicht genügend Produkte auf Lager.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_879f6b8877752685a966564d072f498f'] = 'Ihr Warenkorb ist leer.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_68c070afbc3bf28c3b9b5cc08d0c3a20'] = 'Ihr Warenkorb konnte nicht für %s vorbereitet werden. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_2d3a357afaabc2209bdaaac83c776019'] = 'Wir liefern nicht an diese Adresse. Bitte wählen Sie eine andere Adresse.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_3db1f3472fc989f357b856a1d3a3154c'] = 'Die gewählte Versandart ist für diese Adresse nicht verfügbar.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_a552bc7e269b8ef2b41ebbbd76ee2e01'] = '%s hat keine Rechnungsadresse übermittelt. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_70c0c17a899b230f3bfaa67624bd5624'] = '%s hat keine Lieferadresse übermittelt. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_9ab768ccc193c7c75ebd6ba25dc85506'] = '%s ist für diese Bestellung nicht verfügbar. Bitte wählen Sie eine andere Zahlungsart.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_bd4d7b4f4b3a53570b677dd6a31f26f6'] = 'Der Gesamtbetrag der Bestellung hat sich geändert. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_578410aa770f955b694795be351fa34a'] = 'Die Versandkosten konnten nicht berechnet werden. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_ea9cf7e47ff33b2be14e6dd07cbcefc6'] = 'Versand';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_104d9898c04874d0fbac36e125fa1369'] = 'Rabatt';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_484f5a79672cebe198ebdde45a1d672f'] = 'Geschenkverpackung';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_020c2091aec3019d02193cef080bcf97'] = 'Zahlungsgebühr';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_5642d4ad9a4afd1dc287cd975ad84ea4'] = 'Bitte melden Sie sich an, um mit %s zu bezahlen.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_48421d3773497fba24a6799bbe3f7250'] = 'Bitte geben Sie in %s eine gültige E-Mail-Adresse an.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_dc6ad90e43c36e0e60699162cee13661'] = 'Ihr Kundenkonto konnte nicht angelegt werden. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_ede2a07e7d00be1f13364d3b158d16eb'] = 'Bitte geben Sie in %s einen gültigen Vor- und Nachnamen an.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_19e032a0850720553768e57a927f6a43'] = 'Bitte geben Sie in %s eine gültige Straße an.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_092631abc4f7b157f0857aec8508c190'] = 'Bitte geben Sie in %s einen gültigen Ort an.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_925b76d1049649c9829688b9847b57c4'] = 'Bitte geben Sie in %s eine gültige Postleitzahl an.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_f171d1706ffa1f5ed63afb027f729c27'] = 'Ihre Adresse konnte nicht gespeichert werden. Bitte versuchen Sie es erneut.';
$_MODULE['<{buckaroo3}prestashop>expresscheckoutservice_f04f000f8861cdb34519bdc6d1e93108'] = 'Wir liefern nicht in dieses Land. Bitte wählen Sie eine andere Adresse.';
