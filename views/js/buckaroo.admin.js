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

    $(".buckaroo-toggle-switch").each(function() {
        if ($(this).is(":checked")) {
            togglePanel.call(this);
        }
    }).change(function() {
        $(this).prev().val(this.checked ? 1 : 0);
        togglePanel.call(this);
    });

    function togglePanel() {
        var panelID = this.dataset.target;
        $(panelID).collapse('toggle');
    }

    $("#sortable").sortable();
    $("#sortable").disableSelection();

    function updatePositions() {
        $("#sortable .panel").each(function(index) {
            $(this).find('.position-input').val(index);
        });
    }

    $("#sortable").sortable({
        update: function() {
            updatePositions();
        }
    });

    updatePositions();

    var $apiVersion = $('#BUCKAROO_IN3_API_VERSION');
    var $nextElement = $apiVersion.parent().parent().next();

    $apiVersion.on('change', function() {
        var shouldHide = $apiVersion.val() === 'V3';
        $nextElement.toggle(!shouldHide);
    });
});