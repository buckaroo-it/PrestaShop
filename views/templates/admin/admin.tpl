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
                    {if $fieldset.name == 'GLOBAL'}
                        <div style="margin: 0 0 10px 0; padding: 5px;">
                            <b>Welcome to Buckaroo Payment Engine</b><br>
                            Contact Buckaroo for any questions regarding your account. <b>Phone number</b>: +31 (0)30 711 50 00 <b>E-mail</b>: info@buckaroo.nl
                        </div>
                    {/if}
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
        {/if}
    {/foreach}

    <!-- Apply sortable and collapse functionalities to the rest of the fieldsets -->
    <div id="sortable">
        {foreach from=$fields_form item=fieldset}
            {if $fieldset.name != 'GLOBAL'}
                <div class="panel panel-default">
                    <div class="panel-heading" id="heading{$fieldset.name}">
                        <h2 class="mb-0">
                            <span class="handle" style="cursor: move;">☰ </span>
                            {foreach from=$fieldset.input item=input}
                                {if $input.type == 'enabled'}
                                    <label class="switch">
                                        <input type="checkbox" id="{$input.name|escape:'quotes':'UTF-8'}" data-target="#collapse{$fieldset.name}" class="toggle-switch" name="{$input.name|escape:'quotes':'UTF-8'}" value="1" {if !empty($fields_value[$input.name]) && ($fields_value[$input.name] == 1)}checked{/if}>
                                        <span class="slider round"></span>
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

<style>
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 28px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
    }

    input:checked + .slider {
        background-color: #2196F3;
    }

    input:focus + .slider {
        box-shadow: 0 0 1px #2196F3;
    }

    input:checked + .slider:before {
        transform: translateX(22px); /* Adjusted for moderately sized switch */
    }

    .slider.round {
        border-radius: 28px; /* Adjusted for moderately sized switch */
    }

    .slider.round:before {
        border-radius: 50%;
    }
</style>

<script>
    $(document).ready(function() {
        // Check the initial state of the toggle switches
        $(".toggle-switch").each(function() {
            var panelID = this.dataset.target;

            // Check if the switch is enabled (checked)
            if ($(this).is(":checked")) {
                // Remove the "collapse" class to keep the panel open
                $(panelID).collapse('toggle');
            }
        });
        // Listen for the change event on switches
        $(".toggle-switch").change(function() {
            // Get the ID of the panel associated with this switch
            var panelID = this.dataset.target;
            // Check if the switch is enabled (checked)
            $(panelID).collapse('toggle');
        });
        $( function() {
            $( "#sortable" ).sortable();
            $( "#sortable" ).disableSelection();
        } );

        function updatePositions() {
            // Iterate over each sortable item
            $("#sortable .panel").each(function(index) {
                // Update the value of the hidden input field to reflect the current index
                $(this).find('.position-input').val(index);
            });
        }

        $("#sortable").sortable({
            update: function(event, ui) {
                // Call the function to update positions whenever a sort operation is performed
                updatePositions();
            }
        });

        // Also call the function on page load to initialize positions
        updatePositions();
    });
</script>
