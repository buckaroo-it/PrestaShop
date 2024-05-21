{include file="pdf/header.tpl"}

<table id="summary" width="100%">
  {include file="pdf/table_header.tpl"}
  {include file="pdf/table_body.tpl"}
  {include file="pdf/table_footer.tpl"}
  {if isset($order_buckaroo_fee) && $order_buckaroo_fee > 0}
    <tr class="order-summary">
      <td colspan="3" align="right" class="grey">
        {$payment_fee_label|escape:'html':'UTF-8'} :
      </td>
      <td align="right" class="white">
        {$order_buckaroo_fee|escape:'html':'UTF-8'}
      </td>
    </tr>
  {/if}
  {include file="pdf/total.tpl"}
</table>

{include file="pdf/footer.tpl"}
