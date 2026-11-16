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

namespace Buckaroo\PrestaShop\Src\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Keeps the cart that is being paid unchanged until the payment ends.
 *
 * With the order created after payment, the paid cart would otherwise stay in the
 * customer's session while they are on the payment page, and changes made in another
 * tab would end up in the order. The session gets an identical copy instead; the
 * paid cart is reachable only through the payment attempt. A failed or cancelled
 * payment gives the customer the paid cart back (userreturn).
 */
class PaidCartGuard
{
    /** @var \Context */
    private $context;

    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    /**
     * Put a copy of the cart in the session. Returns the copy, or null when the cart
     * could not be copied (the session then keeps the paid cart).
     */
    public function moveSessionToCopy(\Cart $cart): ?\Cart
    {
        $duplication = $cart->duplicate();
        if (!is_array($duplication) || empty($duplication['success']) || !\Validate::isLoadedObject($duplication['cart'])) {
            return null;
        }

        /** @var \Cart $copy */
        $copy = $duplication['cart'];
        // Cart::duplicate() copies the products, not the vouchers.
        foreach ($cart->getCartRules() as $cartRule) {
            $copy->addCartRule((int) $cartRule['id_cart_rule']);
        }

        $this->context->cookie->__set('id_cart', (int) $copy->id);
        $this->context->cookie->write();

        return $copy;
    }
}
