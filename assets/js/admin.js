function fxw_toggle_delivery_status(element) {
    // Add a simple loading indicator
    element.innerHTML = 'Updating...';

    jQuery.post(ajaxurl, { action: 'fxw_toggle_delivery_status' }, function(response) {
        if (response.success) {
            element.innerHTML = response.data.label;
        } else {
            element.innerHTML = 'Error!';
        }
    });
}
