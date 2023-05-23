/*
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
 */
$(document).ready(function () {
    $(".hidable.disabled").hide();
    $(".enabledisable").change(function () {
        if ($(this).val() == 0) {
            $(this).parents("fieldset").children("legend").removeClass("test").removeClass("active");
            $(this).parents("fieldset").children(".hidable").hide();
        } else {
            if ($(this).parents("fieldset").find(".mode").val() == 0) {
                $(this).parents("fieldset").children("legend").addClass("active");
            } else {
                $(this).parents("fieldset").children("legend").addClass("test");
            }
            ;

            $(this).parents("fieldset").children(".hidable").show();
        }
    });
    $(".mode").change(function () {
        if ($(this).val() == 0) {
            $(this).parents("fieldset").children("legend").addClass("active").removeClass("test");
        } else {
            $(this).parents("fieldset").children("legend").addClass("test").removeClass("active");
        }
    });

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