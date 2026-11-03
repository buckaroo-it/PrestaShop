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

use Buckaroo\PrestaShop\Src\Config\Config;
use Buckaroo\PrestaShop\Src\Service\BuckarooPaymentService;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Server side of the Apple Pay express button.
 *
 * Each express payment uses its own cart: the selected product (product page)
 * or a copy of the shopper's cart (cart and checkout page). The shopper's own
 * cart is not touched until the payment is authorized in the Apple Pay sheet.
 * While the sheet is open, carriers and totals are calculated on that cart
 * with a hidden estimate address built from the (partial) contact Apple Pay
 * shares. On authorization the customer and addresses are created from the
 * Apple Pay contacts, the cart becomes the shopper's cart and the regular
 * Buckaroo request controller creates the order and the payment.
 */
class ExpressCheckoutService
{
    /** Express cart of this browser session. */
    public const COOKIE_CART = 'buckaroo_ape_cart';
    /** Hidden address carriers and taxes are estimated with. */
    public const COOKIE_ADDRESS = 'buckaroo_ape_address';
    /** Shopper's own cart, put back after a product page express order. */
    public const COOKIE_RESTORE_CART = 'buckaroo_ape_restore';
    /** Express cart that was authorized and may be paid with an Apple Pay token. */
    public const COOKIE_READY = 'buckaroo_ape_ready';
    /** Guest customer created by an earlier authorization attempt of this session. */
    public const COOKIE_GUEST = 'buckaroo_ape_guest';

    private const ADDRESS_ALIAS = 'Apple Pay';
    private const AMOUNT_TOLERANCE = 0.01;

    /** @var \Buckaroo3 */
    private $module;

    /** @var \Context */
    private $context;

    /** @var BuckarooPaymentService */
    private $paymentService;

    /**
     * @param \Buckaroo3 $module
     */
    public function __construct(\Buckaroo3 $module, \Context $context, BuckarooPaymentService $paymentService)
    {
        $this->module = $module;
        $this->context = $context;
        $this->paymentService = $paymentService;
    }

    /**
     * Start an express payment for one product, in the selected combination
     * and quantity.
     *
     * @param int[] $attributeIds attribute id per attribute group, as posted by the product form
     *
     * @throws ExpressException
     */
    public function startFromProduct(int $idProduct, int $idProductAttribute, array $attributeIds, int $quantity): \Cart
    {
        $product = new \Product($idProduct, false, (int) $this->context->language->id, (int) $this->context->shop->id);
        $customerId = (int) $this->context->customer->id;

        if (!\Validate::isLoadedObject($product)
            || !$product->active
            || !$product->available_for_order
            || !$product->checkAccess($customerId)
            || !empty(\Product::getRequiredCustomizableFieldsStatic($idProduct))
        ) {
            throw new ExpressException($this->module->l('This product cannot be bought with Apple Pay.', 'expresscheckoutservice'));
        }

        $idProductAttribute = $this->resolveCombination($product, $idProductAttribute, $attributeIds);
        $cart = $this->createCart();

        $added = $this->withCart($cart, null, static function () use ($cart, $idProduct, $idProductAttribute, $quantity) {
            return $cart->updateQty(max(1, $quantity), $idProduct, $idProductAttribute, false, 'up', 0, null, true);
        });

        if ($added !== true) {
            $cart->delete();

            throw new ExpressException($added === -1 ? $this->module->l('Please select the minimum quantity for this product.', 'expresscheckoutservice') : $this->module->l('There are not enough products in stock.', 'expresscheckoutservice'));
        }

        $restoreCart = $this->context->cart;
        $this->rememberCart($cart, \Validate::isLoadedObject($restoreCart) ? (int) $restoreCart->id : 0);

        return $cart;
    }

    /**
     * Start an express payment for the shopper's cart, with its vouchers.
     *
     * @throws ExpressException
     */
    public function startFromCart(): \Cart
    {
        $source = $this->context->cart;
        if (!\Validate::isLoadedObject($source) || (int) $source->nbProducts() <= 0 || \Order::getIdByCartId((int) $source->id)) {
            throw new ExpressException($this->module->l('Your shopping cart is empty.', 'expresscheckoutservice'));
        }

        $duplication = $source->duplicate();
        if (!is_array($duplication) || empty($duplication['success']) || !\Validate::isLoadedObject($duplication['cart'])) {
            throw new ExpressException($this->module->l('Your shopping cart could not be prepared for Apple Pay. Please try again.', 'expresscheckoutservice'));
        }

        /** @var \Cart $cart */
        $cart = $duplication['cart'];
        foreach ($source->getCartRules() as $cartRule) {
            $cart->addCartRule((int) $cartRule['id_cart_rule']);
        }

        $this->rememberCart($cart, 0);

        return $cart;
    }

    /**
     * The express cart of this session, as long as it has not become an order.
     */
    public function getExpressCart(): ?\Cart
    {
        $idCart = (int) $this->context->cookie->__get(self::COOKIE_CART);
        if ($idCart <= 0 || \Order::getIdByCartId($idCart)) {
            return null;
        }

        $cart = new \Cart($idCart);
        if (!\Validate::isLoadedObject($cart) || (int) $cart->id_shop !== (int) $this->context->shop->id) {
            return null;
        }

        return $cart;
    }

    /**
     * Whether Apple Pay may be used for the express cart, with the shop's
     * country rules for the visitor's country.
     */
    public function isAvailable(\Cart $cart): bool
    {
        return $this->withCart($cart, null, function () use ($cart) {
            return $this->paymentService->isMethodAvailableForCart($cart, ExpressSettings::METHOD_NAME);
        });
    }

    /**
     * Carriers, line items and total for the address the shopper picked in the
     * sheet. Apple Pay only shares country, region, postcode and city before
     * the payment is authorized, which is all carriers and taxes depend on.
     *
     * @param array<string, mixed> $contact Apple Pay (redacted) shipping contact; empty for the visitor's country
     *
     * @return array<string, mixed>
     *
     * @throws ExpressException
     */
    public function estimate(\Cart $cart, array $contact, ?string $shippingKey): array
    {
        $country = empty($contact['countryCode'])
            ? $this->context->country
            : $this->resolveCountry((string) $contact['countryCode']);

        $address = $this->saveEstimateAddress($country, $contact);
        $this->setCartAddresses($cart, (int) $address->id, (int) $address->id);

        return $this->withCart($cart, $country, function () use ($cart, $shippingKey) {
            $shippingMethods = $this->selectDeliveryOption($cart, $shippingKey);

            if (!$cart->isVirtualCart() && empty($shippingMethods)) {
                throw new ExpressException($this->module->l('We do not deliver to this address. Please choose another address.', 'expresscheckoutservice'), 'postalAddress');
            }

            return $this->summarize($cart, $shippingMethods);
        });
    }

    /**
     * Totals after the shopper picked another shipping method in the sheet;
     * the address stays the one of the last estimate.
     *
     * @return array<string, mixed>
     *
     * @throws ExpressException
     */
    public function changeShipping(\Cart $cart, string $shippingKey): array
    {
        $idCountry = (int) \Address::getCountryAndState((int) $cart->id_address_delivery)['id_country'];
        $country = $idCountry > 0 ? new \Country($idCountry) : $this->context->country;

        return $this->withCart($cart, $country, function () use ($cart, $shippingKey) {
            $shippingMethods = $this->selectDeliveryOption($cart, $shippingKey);

            if (!$cart->isVirtualCart() && (empty($shippingMethods) || $shippingMethods[0]['identifier'] !== $shippingKey)) {
                throw new ExpressException($this->module->l('The selected shipping method is not available for this address.', 'expresscheckoutservice'), 'postalAddress');
            }

            return $this->summarize($cart, $shippingMethods);
        });
    }

    /**
     * Turn the express cart into the shopper's cart for the authorized Apple
     * Pay payment: customer (logged in, or a new guest), delivery and invoice
     * address, carrier, and a total that matches the authorized amount.
     *
     * @param array<string, mixed> $payment Apple Pay payment (contacts; the token is posted to the request controller)
     *
     * @throws ExpressException
     */
    public function authorize(\Cart $cart, array $payment, string $shippingKey, float $amount): void
    {
        $billing = isset($payment['billingContact']) && is_array($payment['billingContact']) ? $payment['billingContact'] : null;
        $shipping = isset($payment['shippingContact']) && is_array($payment['shippingContact']) ? $payment['shippingContact'] : null;

        if ($billing === null) {
            throw new ExpressException($this->module->l('Apple Pay did not share a billing address. Please try again.', 'expresscheckoutservice'), 'postalAddress');
        }
        if ($shipping === null || empty($shipping['countryCode'])) {
            if (!$cart->isVirtualCart()) {
                throw new ExpressException($this->module->l('Apple Pay did not share a shipping address. Please try again.', 'expresscheckoutservice'), 'postalAddress');
            }
            $shipping = array_merge($billing, $shipping ?? []);
        }

        $email = trim((string) ($shipping['emailAddress'] ?? $billing['emailAddress'] ?? ''));
        $phone = trim((string) ($shipping['phoneNumber'] ?? $billing['phoneNumber'] ?? ''));

        // Validate everything with the hidden estimate address first, so a
        // rejected authorization does not leave customers or addresses behind.
        $deliveryFields = $this->getAddressFields($shipping);
        $invoiceFields = $this->getAddressFields($billing);
        $deliveryCountry = new \Country((int) $deliveryFields['id_country']);

        $estimate = $this->saveEstimateAddress($deliveryCountry, $shipping);
        $this->setCartAddresses($cart, (int) $estimate->id, (int) $estimate->id);
        $this->verifyCart($cart, $shippingKey, $amount, $deliveryCountry);

        $customer = $this->resolveCustomer($billing, $email);
        $idDelivery = $this->saveCustomerAddress($customer, $deliveryFields, $phone);
        $idInvoice = $deliveryFields === $invoiceFields ? $idDelivery : $this->saveCustomerAddress($customer, $invoiceFields, $phone);

        // Taxes follow the real invoice address, so check the amount again.
        $this->assignCart($cart, $customer, $idDelivery, $idInvoice);
        $this->verifyCart($cart, $shippingKey, $amount, $deliveryCountry);

        // The express cart becomes the shopper's cart; a guest is signed in as
        // PrestaShop's own guest checkout does, which resets the cart addresses
        // and carrier, so they are assigned again afterwards.
        $this->context->cart = $cart;
        $this->context->cookie->__set('id_cart', (int) $cart->id);
        if (!$this->isCustomerLoggedIn()) {
            $this->context->updateCustomer($customer);
        }
        $this->assignCart($cart, $customer, $idDelivery, $idInvoice);
        $this->verifyCart($cart, $shippingKey, $amount, $deliveryCountry);
        $this->context->country = $deliveryCountry;

        $this->context->cookie->__set(self::COOKIE_READY, (int) $cart->id);
        $this->context->cookie->write();
    }

    /**
     * Whether the cart was authorized in the Apple Pay sheet and may be paid
     * with the posted token. The marker is used once.
     */
    public function consumeAuthorization(\Cart $cart): bool
    {
        $authorized = (int) $cart->id > 0 && (int) $this->context->cookie->__get(self::COOKIE_READY) === (int) $cart->id;

        $this->context->cookie->__unset(self::COOKIE_READY);
        $this->context->cookie->write();

        return $authorized;
    }

    /**
     * After a product page express order, give the shopper back the cart they
     * had before, unless it was ordered or belongs to someone else.
     */
    public function restoreShopperCart(\Order $order): void
    {
        $cookie = $this->context->cookie;
        if ((int) $cookie->__get(self::COOKIE_CART) !== (int) $order->id_cart) {
            return;
        }

        $idRestore = (int) $cookie->__get(self::COOKIE_RESTORE_CART);
        $cookie->__unset(self::COOKIE_CART);
        $cookie->__unset(self::COOKIE_RESTORE_CART);
        $cookie->__unset(self::COOKIE_ADDRESS);
        $cookie->__unset(self::COOKIE_READY);
        $cookie->__unset(self::COOKIE_GUEST);

        if ($idRestore > 0 && !\Order::getIdByCartId($idRestore)) {
            $restore = new \Cart($idRestore);
            $owner = (int) $restore->id_customer;
            if (\Validate::isLoadedObject($restore)
                && (int) $restore->nbProducts() > 0
                && ($owner === 0 || $owner === (int) $this->context->customer->id)
            ) {
                $cookie->__set('id_cart', $idRestore);
            }
        }

        $cookie->write();
    }

    /**
     * @param int[] $attributeIds
     *
     * @throws ExpressException
     */
    private function resolveCombination(\Product $product, int $idProductAttribute, array $attributeIds): int
    {
        if (!$product->hasAttributes()) {
            return 0;
        }

        $attributeIds = array_values(array_filter(array_map('intval', $attributeIds)));
        if (!empty($attributeIds)) {
            // Renamed in PrestaShop 1.7.3; the old name is gone in 8.
            $lookup = method_exists('Product', 'getIdProductAttributeByIdAttributes')
                ? 'getIdProductAttributeByIdAttributes'
                : 'getIdProductAttributesByIdAttributes';
            $idProductAttribute = (int) call_user_func(['Product', $lookup], (int) $product->id, $attributeIds, true);
        }

        if ($idProductAttribute <= 0) {
            $idProductAttribute = (int) \Product::getDefaultAttribute((int) $product->id);
        }

        $combination = new \Combination($idProductAttribute);
        if (!\Validate::isLoadedObject($combination) || (int) $combination->id_product !== (int) $product->id) {
            throw new ExpressException($this->module->l('This product cannot be bought with Apple Pay.', 'expresscheckoutservice'));
        }

        return $idProductAttribute;
    }

    /**
     * @throws ExpressException
     */
    private function createCart(): \Cart
    {
        $cart = new \Cart();
        $cart->id_shop = (int) $this->context->shop->id;
        $cart->id_shop_group = (int) $this->context->shop->id_shop_group;
        $cart->id_lang = (int) $this->context->language->id;
        $cart->id_currency = (int) $this->context->currency->id;
        $cart->id_guest = (int) $this->context->cookie->__get('id_guest');

        if ($this->isCustomerLoggedIn()) {
            $cart->id_customer = (int) $this->context->customer->id;
            $cart->secure_key = (string) $this->context->customer->secure_key;
        }

        if (!$cart->add()) {
            throw new ExpressException($this->module->l('Your shopping cart could not be prepared for Apple Pay. Please try again.', 'expresscheckoutservice'));
        }

        return $cart;
    }

    /**
     * One express cart per session: an earlier one that was never paid is
     * removed, so repeated clicks do not pile up carts.
     */
    private function rememberCart(\Cart $cart, int $restoreCartId): void
    {
        $previous = $this->getExpressCart();
        if ($previous !== null
            && (int) $previous->id !== (int) $cart->id
            && (int) $previous->id !== (int) $this->context->cookie->__get('id_cart')
        ) {
            $previous->delete();
        }

        $cookie = $this->context->cookie;
        $cookie->__set(self::COOKIE_CART, (int) $cart->id);
        $cookie->__set(self::COOKIE_RESTORE_CART, $restoreCartId);
        $cookie->__unset(self::COOKIE_READY);
        $cookie->write();
    }

    /**
     * The hidden estimate address belongs to no customer and is flagged as
     * deleted, so it never shows up in the shop; it is reused for the session.
     *
     * @param array<string, mixed> $contact
     *
     * @throws ExpressException
     */
    private function saveEstimateAddress(\Country $country, array $contact): \Address
    {
        $address = new \Address((int) $this->context->cookie->__get(self::COOKIE_ADDRESS));
        if (!\Validate::isLoadedObject($address) || (int) $address->id_customer !== 0 || !$address->deleted) {
            $address = new \Address();
        }

        $postcode = strtoupper(trim((string) ($contact['postalCode'] ?? '')));
        $city = trim((string) ($contact['locality'] ?? ''));

        $address->id_customer = 0;
        $address->alias = self::ADDRESS_ALIAS;
        $address->firstname = 'Apple';
        $address->lastname = 'Pay';
        $address->address1 = '-';
        $address->id_country = (int) $country->id;
        $address->id_state = $this->resolveState($country, (string) ($contact['administrativeArea'] ?? ''));
        $address->postcode = \Validate::isPostCode($postcode) ? $postcode : '';
        $address->city = $city !== '' && \Validate::isCityName($city) ? $city : '-';
        $address->deleted = true;

        if (!$address->save()) {
            throw new ExpressException($this->module->l('We could not calculate the shipping costs. Please try again.', 'expresscheckoutservice'));
        }

        $this->context->cookie->__set(self::COOKIE_ADDRESS, (int) $address->id);
        $this->context->cookie->write();

        return $address;
    }

    /**
     * Point the cart and all its lines at the addresses. Carriers are
     * calculated from the line addresses, and Cart::autosetProductAddress()
     * only fills lines that have no address yet.
     */
    private function setCartAddresses(\Cart $cart, int $idDelivery, int $idInvoice): void
    {
        $cart->id_address_delivery = $idDelivery;
        $cart->id_address_invoice = $idInvoice;
        $cart->update();

        $where = '`id_cart` = ' . (int) $cart->id;
        \Db::getInstance()->update('cart_product', ['id_address_delivery' => $idDelivery], $where);
        \Db::getInstance()->update('customization', ['id_address_delivery' => $idDelivery], $where);
        $cart->getProducts(true);
    }

    /**
     * Select the requested carrier when it is available for the cart address,
     * otherwise the cart's current or the first available one.
     *
     * @return array<int, array<string, string>> Apple Pay shipping methods, the selected one first
     */
    private function selectDeliveryOption(\Cart $cart, ?string $shippingKey): array
    {
        if ($cart->isVirtualCart()) {
            return [];
        }

        $idAddress = (int) $cart->id_address_delivery;
        $options = $cart->getDeliveryOptionList(null, true);
        $addressOptions = isset($options[$idAddress]) && is_array($options[$idAddress]) ? $options[$idAddress] : [];

        $methods = [];
        foreach ($addressOptions as $key => $option) {
            $methods[(string) $key] = $this->toShippingMethod((string) $key, $option);
        }

        if (empty($methods)) {
            $cart->setDeliveryOption(null);
            $cart->update();

            return [];
        }

        $current = $cart->getDeliveryOption(null, true);
        if ($shippingKey !== null && isset($methods[$shippingKey])) {
            $selected = $shippingKey;
        } elseif (is_array($current) && isset($current[$idAddress], $methods[(string) $current[$idAddress]])) {
            $selected = (string) $current[$idAddress];
        } else {
            $selected = (string) array_key_first($methods);
        }

        $cart->setDeliveryOption([$idAddress => $selected]);
        $cart->update();

        // Apple Pay preselects the first method in the list.
        return array_values([$selected => $methods[$selected]] + $methods);
    }

    /**
     * @param array<string, mixed> $option a PrestaShop delivery option
     *
     * @return array<string, string>
     */
    private function toShippingMethod(string $key, array $option): array
    {
        $names = [];
        $delays = [];
        $idLang = (int) $this->context->language->id;

        foreach ($option['carrier_list'] ?? [] as $carrierData) {
            $carrier = $carrierData['instance'] ?? null;
            if (!$carrier instanceof \Carrier) {
                continue;
            }
            $names[] = $carrier->name === '0' ? (string) \Configuration::get('PS_SHOP_NAME') : (string) $carrier->name;
            $delay = is_array($carrier->delay) ? (string) ($carrier->delay[$idLang] ?? '') : (string) $carrier->delay;
            if ($delay !== '') {
                $delays[] = $delay;
            }
        }

        return [
            'identifier' => $key,
            'label' => $this->truncate(implode(', ', $names) ?: $this->module->l('Shipping', 'expresscheckoutservice')),
            'detail' => $this->truncate(implode(', ', $delays)),
            'amount' => $this->formatAmount((float) ($option['total_price_with_tax'] ?? 0)),
        ];
    }

    /**
     * Apple Pay line items, shipping methods and total of the cart.
     *
     * @param array<int, array<string, string>> $shippingMethods
     *
     * @return array<string, mixed>
     */
    private function summarize(\Cart $cart, array $shippingMethods): array
    {
        $lineItems = [];
        foreach ($cart->getProducts(true) as $product) {
            $name = (string) $product['name'];
            if (!empty($product['attributes_small'])) {
                $name .= ' - ' . $product['attributes_small'];
            }
            $lineItems[] = $this->lineItem((int) $product['cart_quantity'] . ' x ' . $name, (float) $product['total_wt']);
        }

        $discounts = (float) $cart->getOrderTotal(true, \Cart::ONLY_DISCOUNTS);
        if ($discounts > 0) {
            $lineItems[] = $this->lineItem($this->module->l('Discount', 'expresscheckoutservice'), -$discounts);
        }

        $wrapping = (float) $cart->getOrderTotal(true, \Cart::ONLY_WRAPPING);
        if ($wrapping > 0) {
            $lineItems[] = $this->lineItem($this->module->l('Gift wrapping', 'expresscheckoutservice'), $wrapping);
        }

        if (!empty($shippingMethods)) {
            $lineItems[] = $this->lineItem($shippingMethods[0]['label'], (float) $cart->getOrderTotal(true, \Cart::ONLY_SHIPPING));
        }

        $fee = $this->getFee($cart);
        if ($fee > 0) {
            $feeLabel = (string) \Configuration::get(Config::PAYMENT_FEE_FRONTEND_LABEL);
            $lineItems[] = $this->lineItem($feeLabel !== '' ? $feeLabel : $this->module->l('Payment fee', 'expresscheckoutservice'), $fee);
        }

        return [
            'lineItems' => $lineItems,
            'total' => [
                'label' => $this->truncate((string) \Configuration::get('PS_SHOP_NAME')),
                'amount' => $this->formatAmount($this->getTotal($cart)),
                'type' => 'final',
            ],
            'shippingMethods' => $shippingMethods,
            'selectedShipping' => empty($shippingMethods) ? null : $shippingMethods[0]['identifier'],
        ];
    }

    /**
     * The amount Buckaroo is asked to collect: the cart total with the Apple
     * Pay payment fee, as the request controller and the checkout calculate it.
     */
    private function getTotal(\Cart $cart): float
    {
        return (float) \Tools::ps_round((float) $cart->getOrderTotal(true, \Cart::BOTH) + $this->getFee($cart), $this->getPrecision());
    }

    private function getFee(\Cart $cart): float
    {
        $fee = $this->module->getBuckarooFee(ExpressSettings::METHOD_NAME, (float) $cart->getOrderTotal(true, \Cart::BOTH));

        return is_array($fee) ? (float) $fee['buckaroo_fee_tax_incl'] : 0.0;
    }

    /**
     * @param array<string, mixed> $billing
     *
     * @throws ExpressException
     */
    private function resolveCustomer(array $billing, string $email): \Customer
    {
        if ($this->isCustomerLoggedIn()) {
            return new \Customer((int) $this->context->customer->id);
        }

        if (!\Configuration::get('PS_GUEST_CHECKOUT_ENABLED')) {
            throw new ExpressException($this->module->l('Please sign in to pay with Apple Pay.', 'expresscheckoutservice'));
        }

        if (!\Validate::isEmail($email)) {
            throw new ExpressException($this->module->l('Please enter a valid email address in Apple Pay.', 'expresscheckoutservice'), 'emailAddress');
        }

        // A guest created for an earlier, rejected attempt in this session is reused.
        $previous = new \Customer((int) $this->context->cookie->__get(self::COOKIE_GUEST));
        if (\Validate::isLoadedObject($previous)
            && $previous->is_guest
            && !$previous->deleted
            && \Tools::strtolower((string) $previous->email) === \Tools::strtolower($email)
        ) {
            return $previous;
        }

        [$firstname, $lastname] = $this->resolveName($billing);

        $customer = new \Customer();
        $customer->firstname = $firstname;
        $customer->lastname = $lastname;
        $customer->email = $email;
        $customer->is_guest = true;
        $customer->passwd = $this->hashRandomPassword();
        $customer->id_shop = (int) $this->context->shop->id;
        $customer->id_shop_group = (int) $this->context->shop->id_shop_group;
        $customer->id_lang = (int) $this->context->language->id;

        if (!$customer->add()) {
            throw new ExpressException($this->module->l('We could not create your customer account. Please try again.', 'expresscheckoutservice'));
        }

        $this->context->cookie->__set(self::COOKIE_GUEST, (int) $customer->id);
        $this->context->cookie->write();

        return $customer;
    }

    /**
     * @param array<string, mixed> $contact Apple Pay contact
     *
     * @return string[] first name and last name
     *
     * @throws ExpressException
     */
    private function resolveName(array $contact): array
    {
        $firstname = trim((string) ($contact['givenName'] ?? ''));
        $lastname = trim((string) ($contact['familyName'] ?? ''));

        if ($firstname === '' || $lastname === '' || !\Validate::isName($firstname) || !\Validate::isName($lastname)) {
            throw new ExpressException($this->module->l('Please enter a valid first and last name in Apple Pay.', 'expresscheckoutservice'), 'name');
        }

        return [$firstname, $lastname];
    }

    /**
     * Validated address fields of an Apple Pay contact.
     *
     * @param array<string, mixed> $contact Apple Pay contact
     *
     * @return array<string, int|string>
     *
     * @throws ExpressException
     */
    private function getAddressFields(array $contact): array
    {
        [$firstname, $lastname] = $this->resolveName($contact);
        $country = $this->resolveCountry((string) ($contact['countryCode'] ?? ''));
        $idState = $this->resolveState($country, (string) ($contact['administrativeArea'] ?? ''));

        $lines = [];
        if (isset($contact['addressLines']) && is_array($contact['addressLines'])) {
            foreach ($contact['addressLines'] as $line) {
                $line = is_scalar($line) ? trim((string) $line) : '';
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }
        $address1 = $lines[0] ?? '';
        $address2 = implode(' ', array_slice($lines, 1));
        $city = trim((string) ($contact['locality'] ?? ''));
        $postcode = strtoupper(trim((string) ($contact['postalCode'] ?? '')));

        if ($address1 === '' || !\Validate::isAddress($address1) || !\Validate::isAddress($address2)) {
            throw new ExpressException($this->module->l('Please enter a valid street address in Apple Pay.', 'expresscheckoutservice'), 'addressLines');
        }
        if ($city === '' || !\Validate::isCityName($city)) {
            throw new ExpressException($this->module->l('Please enter a valid city in Apple Pay.', 'expresscheckoutservice'), 'locality');
        }
        if (!\Validate::isPostCode($postcode) || ($country->need_zip_code && !$country->checkZipCode($postcode))) {
            throw new ExpressException($this->module->l('Please enter a valid postal code in Apple Pay.', 'expresscheckoutservice'), 'postalCode');
        }

        return [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'address1' => $address1,
            'address2' => $address2,
            'postcode' => $postcode,
            'city' => $city,
            'id_country' => (int) $country->id,
            'id_state' => $idState,
        ];
    }

    /**
     * Save validated address fields as an address of the customer, or reuse an
     * identical address the customer already has.
     *
     * @param array<string, int|string> $fields see getAddressFields()
     *
     * @throws ExpressException
     */
    private function saveCustomerAddress(\Customer $customer, array $fields, string $phone): int
    {
        foreach ($customer->getAddresses((int) $this->context->language->id) as $existing) {
            if ($this->isSameAddress($existing, $fields)) {
                return (int) $existing['id_address'];
            }
        }

        $address = new \Address();
        foreach ($fields as $field => $value) {
            $address->{$field} = $value;
        }
        $address->id_customer = (int) $customer->id;
        $address->alias = self::ADDRESS_ALIAS;
        $address->phone = \Validate::isPhoneNumber($phone) ? $phone : '';

        if (!$address->add()) {
            throw new ExpressException($this->module->l('We could not save your address. Please try again.', 'expresscheckoutservice'), 'postalAddress');
        }

        return (int) $address->id;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $fields
     */
    private function isSameAddress(array $existing, array $fields): bool
    {
        foreach ($fields as $field => $value) {
            $same = is_int($value)
                ? (int) ($existing[$field] ?? 0) === $value
                : \Tools::strtolower(trim((string) ($existing[$field] ?? ''))) === \Tools::strtolower((string) $value);

            if (!$same) {
                return false;
            }
        }

        return true;
    }

    private function assignCart(\Cart $cart, \Customer $customer, int $idDelivery, int $idInvoice): void
    {
        $cart->id_customer = (int) $customer->id;
        $cart->secure_key = (string) $customer->secure_key;
        $this->setCartAddresses($cart, $idDelivery, $idInvoice);
    }

    /**
     * The carrier chosen in the sheet must be available for the cart address,
     * Apple Pay must be allowed for the cart and the total must be the amount
     * the shopper authorized.
     *
     * @throws ExpressException
     */
    private function verifyCart(\Cart $cart, string $shippingKey, float $amount, \Country $country): void
    {
        $this->withCart($cart, $country, function () use ($cart, $shippingKey, $amount) {
            if (!$cart->isVirtualCart()) {
                $methods = $this->selectDeliveryOption($cart, $shippingKey);
                if (empty($methods) || $methods[0]['identifier'] !== $shippingKey) {
                    throw new ExpressException($this->module->l('The selected shipping method is not available for this address.', 'expresscheckoutservice'), 'postalAddress');
                }
            }

            if (!$this->paymentService->isMethodAvailableForCart($cart, ExpressSettings::METHOD_NAME)) {
                throw new ExpressException($this->module->l('Apple Pay is not available for this order. Please choose another payment method.', 'expresscheckoutservice'));
            }

            if (abs($this->getTotal($cart) - $amount) > self::AMOUNT_TOLERANCE) {
                throw new ExpressException($this->module->l('The order total has changed. Please try again.', 'expresscheckoutservice'));
            }
        });
    }

    /**
     * @throws ExpressException
     */
    private function resolveCountry(string $isoCode): \Country
    {
        $idCountry = (int) \Country::getByIso(strtoupper(trim($isoCode)), true);
        $country = new \Country($idCountry, (int) $this->context->language->id);

        if ($idCountry <= 0 || !\Validate::isLoadedObject($country) || !$country->active) {
            throw new ExpressException($this->module->l('We do not deliver to this country. Please choose another address.', 'expresscheckoutservice'), 'country');
        }

        return $country;
    }

    /**
     * PrestaShop state of the Apple Pay administrative area. Apple sends a
     * code ("CA", "NH") or a name, and often nothing for countries such as the
     * Netherlands, where PrestaShop stores provinces as "NL-NH". An unknown or
     * missing area leaves the state empty rather than blocking the payment.
     */
    private function resolveState(\Country $country, string $area): int
    {
        $area = trim($area);
        if (!$country->contains_states || $area === '') {
            return 0;
        }

        $idCountry = (int) $country->id;
        $code = strtoupper($area);
        foreach ([$code, strtoupper((string) $country->iso_code) . '-' . $code] as $isoCode) {
            $idState = (int) \State::getIdByIso($isoCode, $idCountry);
            if ($idState > 0) {
                return $idState;
            }
        }

        foreach (\State::getStatesByIdCountry($idCountry, true) as $state) {
            if (\Tools::strtolower((string) $state['name']) === \Tools::strtolower($area)) {
                return (int) $state['id_state'];
            }
        }

        return 0;
    }

    /**
     * Run a calculation with the cart (and country) in the context, as
     * PrestaShop's price, fee and availability code reads them from there.
     *
     * @return mixed
     */
    private function withCart(\Cart $cart, ?\Country $country, callable $callback)
    {
        $previousCart = $this->context->cart;
        $previousCountry = $this->context->country;

        $this->context->cart = $cart;
        if ($country !== null) {
            $this->context->country = $country;
        }

        try {
            return $callback();
        } finally {
            $this->context->cart = $previousCart;
            $this->context->country = $previousCountry;
        }
    }

    private function isCustomerLoggedIn(): bool
    {
        $customer = $this->context->customer;

        return $customer instanceof \Customer && (int) $customer->id > 0 && $customer->isLogged();
    }

    private function hashRandomPassword(): string
    {
        $password = \Tools::passwdGen(32);

        if (class_exists('PrestaShop\PrestaShop\Adapter\ServiceLocator')) {
            $hashing = \PrestaShop\PrestaShop\Adapter\ServiceLocator::get('\PrestaShop\PrestaShop\Core\Crypto\Hashing');

            return $hashing->hash($password);
        }

        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * @return array<string, string>
     */
    private function lineItem(string $label, float $amount): array
    {
        return [
            'label' => $this->truncate($label),
            'amount' => $this->formatAmount($amount),
            'type' => 'final',
        ];
    }

    private function formatAmount(float $amount): string
    {
        $precision = $this->getPrecision();

        return number_format((float) \Tools::ps_round($amount, $precision), $precision, '.', '');
    }

    private function getPrecision(): int
    {
        return (int) $this->context->getComputingPrecision();
    }

    private function truncate(string $label): string
    {
        $label = trim(strip_tags($label));

        return \Tools::strlen($label) > 60 ? \Tools::substr($label, 0, 57) . '...' : $label;
    }
}
