jQuery(function ($) {
    // Tab switching - delegated, no passive needed for click
    $(document).on('click', '[data-fxw-tab]', function () {
        var tabId = $(this).data('fxw-tab');
        // Sanitise tabId to prevent DOM selector injection
        if (!/^[a-zA-Z0-9_-]+$/.test(tabId)) {
            return;
        }

        var $panel = document.getElementById(tabId);
        if (!$panel) {
            return;
        }

        $('.fxw-tab-link').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('.fxw-tab-content').removeClass('active').hide();
        $($panel).addClass('active').show();
    });

    $(document).on('click', '.fxw-print-receipt', function (e) {
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

        var printUrl = fxw_checkout_params.ajax_url + '?action=fxw_print_receipt&order_id=' + orderId + '&nonce=' + fxw_checkout_params.nonce;

        try {
            var printWindow = window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');

            if (!printWindow) {
                alert('Receipt window was blocked. Please allow popups for this site and try again.');
                $button.prop('disabled', false).text(originalText);
                return;
            }

            // Reset button after a short delay
            setTimeout(function () {
                $button.prop('disabled', false).text(originalText);
            }, 2000);

            // Handle the print window load event
            printWindow.onload = function () {
                // Small delay to ensure content is rendered
                setTimeout(function () {
                    if (printWindow && !printWindow.closed) {
                        printWindow.print();
                    }
                }, 500);
            };

            // Handle case where window fails to load
            printWindow.onerror = function () {
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
    $(document).on('click', 'a[href*="fxw_print_receipt"]', function (e) {
        var $link = $(this);
        var originalText = $link.text();

        // Only handle if it's a JavaScript onclick, not a regular link
        if ($link.attr('onclick')) {
            $link.text('Opening receipt...');

            setTimeout(function () {
                $link.text(originalText);
            }, 2000);
        }
    });
    // AJAX handler for delivery status updates
    $(document).on('click', '.fxw-action-btn', function (e) {
        e.preventDefault();

        var $button = $(this);
        var orderId = $button.data('order-id');
        var actionStatus = $button.data('status');
        var nonce = $button.data('nonce');
        var originalText = $button.html();

        if (!orderId || !actionStatus || !nonce) {
            alert('Missing data for this action.');
            return;
        }

        if (!confirm('Are you sure you want to update the order status?')) {
            return;
        }

        $button.prop('disabled', true).text('Updating...');

        if (typeof fxw_checkout_params === 'undefined' || !fxw_checkout_params.ajax_url) {
            alert('Session expired. Please reload the page.');
            $button.prop('disabled', false).html(originalText);
            return;
        }

        $.ajax({
            url: fxw_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'fxw_update_delivery_status',
                order_id: orderId,
                status: actionStatus,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    var $card = $button.closest('.fxw-order-card');
                    var $currentTab = $card.closest('.fxw-tab-content');

                    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    var delay = reducedMotion ? 0 : 150;
                    $card.addClass('fxw-card-removing');
                    requestAnimationFrame(function () {
                        if (!reducedMotion) {
                            $card.css({ opacity: 0, transform: 'scale(0.98)' });
                        }
                        setTimeout(function () {
                            $card.remove();
                            if (typeof requestIdleCallback === 'function') {
                                requestIdleCallback(updateTabCounters, { timeout: 100 });
                            } else {
                                updateTabCounters();
                            }
                            if ($currentTab.find('.fxw-order-card').length === 0) {
                                var noOrdersMsg = getI18n().no_orders || 'No orders in this section.';
                                $currentTab.append($('<div class="fxw-no-orders"></div>').append($('<p></p>').text(noOrdersMsg)));
                            }
                        }, delay);
                    });
                } else {
                    alert((response.data && response.data.message) ? response.data.message : 'Failed to update status.');
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert('Connection error. Please try again.');
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    function getI18n() {
        return (typeof fxw_checkout_params !== 'undefined' && fxw_checkout_params.i18n) ? fxw_checkout_params.i18n : {};
    }

    function updateTabCounters() {
        var i18n = getI18n();
        var newCount = $('#new-orders .fxw-order-card').length;
        var inProgressCount = $('#in-progress .fxw-order-card').length;

        $('[data-fxw-tab="new-orders"]').text((i18n.new_tab || 'New') + ' (' + newCount + ')');
        $('[data-fxw-tab="in-progress"]').text((i18n.in_progress_tab || 'In Progress') + ' (' + inProgressCount + ')');
    }
});
