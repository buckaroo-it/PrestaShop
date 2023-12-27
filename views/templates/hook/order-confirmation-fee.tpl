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
<script>
    document.addEventListener("DOMContentLoaded", function(){
        $(".total-value").before(
            $("<tr><td>Buckaroo Fee</td><td>{$orderBuckarooFee|escape:'htmlall':'UTF-8'}</td></tr>"))
    });
</script>