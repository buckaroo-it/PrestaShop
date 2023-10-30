
// @ts-ignore
import toastr  from 'toastr'

export const useToastr = () => {
    toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": false,
        "progressBar": true,
        "positionClass": "toast-bottom-right",
        "preventDuplicates": false,
        // "onclick": null,
        // "showDuration": "300",
        // "hideDuration": "1000",
        // "timeOut": "5000",
        // "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }

    return {
        toastr
    }
}
