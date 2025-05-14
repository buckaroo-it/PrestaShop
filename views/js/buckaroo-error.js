document.addEventListener('DOMContentLoaded', () => {
    if (typeof buckaroo_error_msg !== 'undefined' && buckaroo_error_msg) {
        if (typeof toastr !== 'undefined') {
            toastr.error(buckaroo_error_msg);
        } else {
            alert(buckaroo_error_msg);
        }
    }
});
