{*
*
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * @author Buckaroo.nl <plugins@buckaroo.nl>
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   http://opensource.org/licenses/afl-3.0 Academic Free License (AFL 3.0)
*}

{extends file='page.tpl'}
{block name='page_content'}
    <div class="container js-buckaroo-payment-error">
        <article class="alert alert-danger" role="alert" data-alert="danger">
            <ul id="buckaroo-notifications">
                <li>{$error_message|escape:'html':'UTF-8'}</li>
            </ul>
        </article>
    </div>
{/block}
