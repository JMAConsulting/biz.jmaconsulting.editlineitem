{* HEADER *}

{foreach from=$fieldNames item=fieldName}
<div class="crm-section">
    <div class="label">{$form.$fieldName.label}</div>
    <div class="content">
      {if in_array($fieldName, array('unit_price', 'line_total', 'tax_amount'))}
        {$currency}
      {/if}
      <span>{$form.$fieldName.html}</span>
      {if $fieldName eq 'price_field_value_id'}
        <div><span class="description">{ts}The quick-config price field(s) are marked with *{/ts}</span></div>
      {/if}
    </div>
    <div class="clear"></div>
</div>
{/foreach}

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
  <script type="text/javascript">
    CRM.$(function($) {
      var $form = $('form.{/literal}{$form.formClass}{literal}');
      $('#price_field_value_id', $form).change(function() {
        var price_field_value_id = $(this).val();
        var priceFieldDataURL =  CRM.url('civicrm/ajax/get-pricefield-info', { pfv_id:  price_field_value_id });
        $.ajax({
          url         : priceFieldDataURL,
          dataType    : "json",
          timeout     : 5000, //Time in milliseconds
          success     : function(data, status) {
            for (var ele in data) {
              if (ele == 'financial_type_id') {
                $('#' + ele).select2('val', data[ele]);
              }
              else {
                $('#' + ele, $form).val(data[ele]);
              }
            }
          }
        });
      });

      $('#qty', $form).change( function() {
        var unit_price = $('#unit_price', $form).val();
        var qty = $('#qty', $form).val();
        var totalAmount = qty * unit_price;
        $('#line_total', $form).val(CRM.formatMoney(totalAmount, true));
      });
      $('#unit_price', $form).change( function() {
        var unit_price = $('#unit_price', $form).val();
        var qty = $('#qty', $form).val();
        var totalAmount = qty * unit_price;
        $('#line_total', $form).val(CRM.formatMoney(totalAmount, true));
      });
      $('#financial_type_id', $form).change( function() {
        if ($('#tax_amount').length) {
          var tax_rates = {/literal}{$taxRates}{literal};
          var financial_type_id = $(this).val();
          var tax_amount = 0;
          if (financial_type_id in tax_rates) {
            tax_amount = (tax_rates[financial_type_id] / 100 ) * $('#line_total').val();
          }
          $('#tax_amount', $form).val(CRM.formatMoney(tax_amount, true));
        }
      });
    });
  </script>
{/literal}
