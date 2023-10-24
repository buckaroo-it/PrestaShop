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
<link href="{$pathCss|escape:'htmlall':'UTF-8'}" rel="stylesheet">
<div id="app">
    <Dashboard
            jwt="{$jwt|escape:'htmlall':'UTF-8'}"
            base-url="{$baseUrl|escape:'htmlall':'UTF-8'}"
    />
</div>
<script type="module" src="{$pathApp|escape:'htmlall':'UTF-8'}"></script>