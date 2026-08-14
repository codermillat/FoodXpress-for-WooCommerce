/**
 * FoodXpress Admin Dashboard - AJAX Functionality
 *
 * Provides seamless AJAX-based interactions for the deliveries dashboard.
 *
 * @since 1.1.0
 * @package FoodXpress
 */

(function ($) {
    'use strict';

    // Check if fxwDashboard is defined
    if (typeof fxwDashboard === 'undefined') {
        console.warn('FXW Dashboard: Configuration not loaded');
        return;
    }

    /**
     * Initialize dashboard functionality
     */
    function initDashboard() {
        bindAssignmentHandlers();
        bindStatusUpdateHandlers();
    }

    /**
     * Bind event handlers for delivery assignment
     */
    function bindAssignmentHandlers() {
        // Handle assignment dropdown changes
        $(document).on('change', '.fxw-assign-select', function () {
            var $select = $(this);
            var orderId = $select.data('order-id');
            var deliveryBoyId = $select.val();
            var $row = $select.closest('tr, .order-card');

            if (!orderId) return;

            // Disable the select while processing
            $select.prop('disabled', true);
            showRowLoading($row);

            $.ajax({
                url: fxwDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'fxw_ajax_assign_delivery',
                    nonce: fxwDashboard.nonce,
                    order_id: orderId,
                    delivery_boy_id: deliveryBoyId
                },
                success: function (response) {
                    if (response.success && response.data) {
                        showNotification(response.data.message || 'Order assigned', 'success');

                        // Update the row status if applicable
                        if (response.data.new_status) {
                            updateRowStatus($row, response.data.status_label);
                        }

                        // Optionally move row to different section
                        if (deliveryBoyId && $row.closest('.pending-orders').length) {
                            // Could move to assigned section via page reload or DOM manipulation
                            setTimeout(function () {
                                $row.fadeOut(300, function () {
                                    window.location.reload();
                                });
                            }, 1000);
                        }
                    } else {
                        showNotification((response.data && response.data.message) ? response.data.message : 'Error assigning order', 'error');
                        $select.val(''); // Reset on error
                    }
                },
                error: function (xhr, status, error) {
                    showNotification('Network error: ' + error, 'error');
                    $select.val(''); // Reset on error
                },
                complete: function () {
                    $select.prop('disabled', false);
                    hideRowLoading($row);
                }
            });
        });
    }

    /**
     * Bind event handlers for status updates
     */
    function bindStatusUpdateHandlers() {
        // Handle status update button clicks
        $(document).on('click', '.fxw-status-btn', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var newStatus = $btn.data('status');
            var $row = $btn.closest('tr, .order-card');

            if (!orderId || !newStatus) return;

            // Confirm completed/cancelled actions
            if (newStatus === 'completed' || newStatus === 'cancelled') {
                if (!confirm('Are you sure you want to mark this order as ' + newStatus + '?')) {
                    return;
                }
            }

            // Disable all buttons in this row while processing
            $row.find('.fxw-status-btn').prop('disabled', true);
            showRowLoading($row);

            $.ajax({
                url: fxwDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'fxw_ajax_update_status',
                    nonce: fxwDashboard.nonce,
                    order_id: orderId,
                    new_status: newStatus
                },
                success: function (response) {
                    if (response.success && response.data) {
                        showNotification(response.data.message || 'Status updated', 'success');
                        updateRowStatus($row, response.data.status_label);

                        // Handle row movement based on new status
                        if (newStatus === 'completed' || newStatus === 'cancelled') {
                            $row.fadeOut(300, function () {
                                $(this).remove();
                                updateOrderCounts();
                            });
                        } else if (newStatus === 'fxw-picked-up') {
                            // Move from assigned to out for delivery
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        }
                    } else {
                        showNotification((response.data && response.data.message) ? response.data.message : 'Error updating status', 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showNotification('Network error: ' + error, 'error');
                },
                complete: function () {
                    $row.find('.fxw-status-btn').prop('disabled', false);
                    hideRowLoading($row);
                }
            });
        });
    }

    /**
     * Show loading state on a row
     */
    function showRowLoading($row) {
        $row.css('opacity', '0.6');
        if (!$row.find('.fxw-spinner').length) {
            $row.append('<span class="fxw-spinner" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);">⏳</span>');
        }
    }

    /**
     * Hide loading state on a row
     */
    function hideRowLoading($row) {
        $row.css('opacity', '1');
        $row.find('.fxw-spinner').remove();
    }

    /**
     * Update the status display in a row
     */
    function updateRowStatus($row, statusLabel) {
        var $statusCell = $row.find('.order-status, .status-badge');
        if ($statusCell.length) {
            $statusCell.text(statusLabel);
        }
    }

    /**
     * Update order count badges in tabs
     */
    function updateOrderCounts() {
        // Update the count in section headers
        $('.fxw-section').each(function () {
            var $section = $(this);
            var count = $section.find('tbody tr').length || $section.find('.order-card').length;
            var $countBadge = $section.find('.order-count');
            if ($countBadge.length) {
                $countBadge.text('(' + count + ')');
            }
        });
    }

    /**
     * Show a notification message
     */
    function showNotification(message, type) {
        type = type || 'info';

        // Remove existing notifications
        $('.fxw-notification').remove();

        var bgColor = type === 'success' ? '#46b450' : (type === 'error' ? '#dc3232' : '#0073aa');

        var $notification = $('<div class="fxw-notification"></div>')
            .text(message)
            .css({
                position: 'fixed',
                top: '40px',
                right: '20px',
                padding: '12px 20px',
                background: bgColor,
                color: '#fff',
                borderRadius: '4px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.2)',
                zIndex: 99999,
                fontSize: '14px',
                maxWidth: '350px',
                animation: 'fxwSlideIn 0.3s ease'
            });

        $('body').append($notification);

        // Auto-hide after 4 seconds
        setTimeout(function () {
            $notification.fadeOut(300, function () {
                $(this).remove();
            });
        }, 4000);
    }

    // Add CSS animation
    $('<style>')
        .text('@keyframes fxwSlideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }')
        .appendTo('head');

    // Initialize when DOM is ready
    $(document).ready(initDashboard);

})(jQuery);
