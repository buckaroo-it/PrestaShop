{*
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}
{* Stays hidden until the browser confirms Apple Pay is supported on this device *}
<div class="buckaroo-applepay-express buckaroo-applepay-express--{$buckarooApplePayPlacement|escape:'htmlall':'UTF-8'}"
     data-placement="{$buckarooApplePayPlacement|escape:'htmlall':'UTF-8'}"
     data-id-product="{$buckarooApplePayProductId|intval}"
     hidden>
    <div class="buckaroo-applepay-express__button"></div>
    <div class="buckaroo-applepay-express__message alert alert-danger" role="alert" hidden></div>
</div>
