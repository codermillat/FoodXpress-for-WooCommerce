function fxw_toggle_delivery_status(element) {
    // Add a simple loading indicator
    element.innerHTML = 'Updating...';

    // Security: Include nonce for CSRF protection
    jQuery.post(ajaxurl, { 
        action: 'fxw_toggle_delivery_status',
        nonce: fxw_admin_params.nonce 
    }, function(response) {
        if (response.success) {
            element.innerHTML = response.data.label;
        } else {
            element.innerHTML = 'Error!';
        }
    });
}
