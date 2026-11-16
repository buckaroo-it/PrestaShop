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
{* Stays hidden until Google Pay confirms it can be used in this browser *}
<div class="buckaroo-googlepay-express buckaroo-googlepay-express--{$buckarooExpressPlacement|escape:'htmlall':'UTF-8'}"
     data-placement="{$buckarooExpressPlacement|escape:'htmlall':'UTF-8'}"
     data-id-product="{$buckarooExpressProductId|intval}"
     data-virtual="{if $buckarooExpressVirtual}1{else}0{/if}"
     hidden>
    <div class="buckaroo-googlepay-express__button"></div>
    <div class="buckaroo-googlepay-express__message alert alert-danger" role="alert" hidden></div>
</div>
