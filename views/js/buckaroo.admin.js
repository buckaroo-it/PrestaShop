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
 * @copyright 2014-2015 Buckaroo.nl
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
});