<form action="{$form_action|escape:'quotes':'UTF-8'}" method="post" class="clear" id="buckaroo3settings_form" method="post"
      enctype="multipart/form-data">
    <!-- Always display the 'GLOBAL' fieldset first and keep it open -->
    {foreach from=$fields_form item=fieldset}
        {if $fieldset.name == 'GLOBAL'}
            <div class="panel panel-default">
                <div class="panel-heading">
                    {$fieldset.legend|escape:'html':'UTF-8'}
                </div>
                <div class="panel-body">
                    <div style="margin: 0 0 10px 0; padding: 5px;">
                        <b>Welcome to Buckaroo Payment Engine</b><br>
                        Contact Buckaroo for any questions regarding your account. <b>Phone number</b>: +31 (0)30 711 50 00 <b>E-mail</b>: info@buckaroo.nl
                    </div>
                    {foreach from=$fieldset.input item=input}
                        {include file="$dir/admin.input.tpl" input=$input enabled=$fieldset.enabled}
                    {/foreach}
                    <div class="form-group">
                    </div>
                    <div class="small">
                        <sup>*</sup> {l s='Required field' mod='buckaroo3'}
                    </div>
                </div>
            </div>
        {/if}

        {if $fieldset.name == 'REFUND'}
            <div class="panel panel-default">
                <div class="panel-heading">
                    {$fieldset.legend|escape:'html':'UTF-8'}
                </div>
                <div class="panel-body">
                    {foreach from=$fieldset.input item=input}
                        {include file="$dir/admin.input.tpl" input=$input enabled=$fieldset.enabled}
                    {/foreach}
                    <div class="form-group">
                    </div>
                    <div class="small">
                        <sup>*</sup> {l s='Required field' mod='buckaroo3'}
                    </div>
                </div>
            </div>
        {/if}
    {/foreach}

    <!-- Apply sortable and collapse functionalities to the rest of the fieldsets -->
    <div id="sortable">
        {foreach from=$fields_form item=fieldset}
            {if !in_array($fieldset.name, ['GLOBAL', 'REFUND'])}
                <div class="panel panel-default">
                    <div class="panel-heading" id="heading{$fieldset.name}">
                        <h2 class="mb-0">
                            <span class="handle" style="cursor: move;">☰ </span>
                            {foreach from=$fieldset.input item=input}
                                {if $input.type == 'enabled'}
                                    <label class="switch buckaroo-switch">
                                        <input type="hidden" name="{$input.name|escape:'quotes':'UTF-8'}" value="{if !empty($fields_value[$input.name]) && ($fields_value[$input.name] == 1)}1{else}0{/if}">
                                        <input type="checkbox" data-target="#collapse{$fieldset.name}" class="toggle-switch buckaroo-toggle-switch" {if !empty($fields_value[$input.name]) && ($fields_value[$input.name] == 1)}checked{/if}>
                                        <span class="slider buckaroo-slider round"></span>
                                    </label>
                                {/if}
                            {/foreach}
                            <img src="{$fieldset.image}" alt="Icon"> <!-- insert your image here -->
                            {$fieldset.legend|escape:'html':'UTF-8'}
                        </h2>
                    </div>
                    <div id="collapse{$fieldset.name}" class="collapse" aria-labelledby="heading{$fieldset.name}">
                        <div class="panel-body">
                            {foreach from=$fieldset.input item=input}
                                <div class="form-group">
                                    {include file="$dir/admin.input.tpl" input=$input enabled=$fieldset.enabled}
                                </div>
                            {/foreach}
                            <div class="small">
                                <sup>*</sup> {l s='Required field' mod='buckaroo3'}
                            </div>
                        </div>
                    </div>
                </div>
            {/if}
        {/foreach}
    </div>
</form>