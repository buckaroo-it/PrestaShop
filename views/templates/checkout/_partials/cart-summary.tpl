{if isset($cookie->buckaroo_error_msg) && $cookie->buckaroo_error_msg}
    <div class="alert alert-danger my-3">
        {$cookie->buckaroo_error_msg nofilter}
    </div>
    {$cookie->__unset('buckaroo_error_msg')}
{/if}
