jQuery(function($) {
    $(document).on('click', '.fxw-print-receipt', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var orderId = $button.data('order-id');
        
        // Check if AJAX params are available
        if (typeof fxw_checkout_params === 'undefined' || !fxw_checkout_params.ajax_url) {
            alert('Print receipt functionality is not available. Please contact support.');
            return;
        }
        
        if (!orderId) {
            alert('Order ID is missing. Cannot print receipt.');
            return;
        }
        
        // Disable button and show loading state
        var originalText = $button.text();
        $button.prop('disabled', true).text('Opening receipt...');
        
        var printUrl = fxw_checkout_params.ajax_url + '?action=fxw_print_receipt&order_id=' + orderId;
        
        try {
            var printWindow = window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
            
            if (!printWindow) {
                alert('Receipt window was blocked. Please allow popups for this site and try again.');
                $button.prop('disabled', false).text(originalText);
                return;
            }
            
            // Reset button after a short delay
            setTimeout(function() {
                $button.prop('disabled', false).text(originalText);
            }, 2000);
            
            // Handle the print window load event
            printWindow.onload = function() {
                // Small delay to ensure content is rendered
                setTimeout(function() {
                    if (printWindow && !printWindow.closed) {
                        printWindow.print();
                    }
                }, 500);
            };
            
            // Handle case where window fails to load
            printWindow.onerror = function() {
                alert('Failed to load receipt. Please try again or contact support.');
                $button.prop('disabled', false).text(originalText);
            };
            
        } catch (error) {
            console.error('Error opening receipt window:', error);
            alert('Failed to open receipt window. Please try again.');
            $button.prop('disabled', false).text(originalText);
        }
    });
    
    // Also handle receipt printing from admin order pages
    $(document).on('click', 'a[href*="fxw_print_receipt"]', function(e) {
        var $link = $(this);
        var originalText = $link.text();
        
        // Only handle if it's a JavaScript onclick, not a regular link
        if ($link.attr('onclick')) {
            $link.text('Opening receipt...');
            
            setTimeout(function() {
                $link.text(originalText);
            }, 2000);
        }
    });
});
