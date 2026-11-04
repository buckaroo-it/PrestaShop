
// @ts-ignore
import toastr  from 'toastr'

export const useToastr = () => {
    toastr.options = {
        "closeButton": false,
        "debug": false,
        "newestOnTop": true,
        "progressBar": false,
        "positionClass": "toast-bottom-right",
        // Don't stack the same message several times
        "preventDuplicates": true,
        "showDuration": "200",
        "hideDuration": "200",
        "timeOut": "3500",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    }

    return {
        toastr
    }
}
