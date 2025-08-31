jQuery(function($) {
    $(document).on('click', '.fxw-print-receipt', function(e) {
        e.preventDefault();
        var orderId = $(this).data('order-id');
        var printUrl = fxw_checkout_params.ajax_url + '?action=fxw_print_receipt&order_id=' + orderId;
        var printWindow = window.open(printUrl, '_blank');
        printWindow.onload = function() {
            printWindow.print();
        };
    });
});
