/**
 * FoodXpress Admin Dashboard - legacy AJAX layer.
 *
 * Stub only. The current dashboard renders plain <select> + <button>
 * elements that submit via standard form POSTs handled by
 * FXW_Dashboard_Actions — no JS-driven AJAX row updates. This file used
 * to bind `.fxw-assign-select` and `.fxw-status-btn` selectors that no
 * longer exist; kept as an empty module so any legacy page that still
 * references `fxwDashboard` (the localized nonce/object) won't error
 * (1.2.16). Safe to delete in a future release if no caller appears.
 *
 * @since 1.1.0
 * @package FoodXpress
 */

(function ($) {
    'use strict';

    // Intentional no-op. The legacy selectors (.fxw-assign-select,
    // .fxw-status-btn) don't exist in the current dashboard markup; the
    // form-POST handlers in FXW_Dashboard_Actions do the real work and
    // reload the page on success.
    if (typeof fxwDashboard === 'undefined') {
        return;
    }

    // Log once per page load so anyone inspecting the console knows the
    // legacy layer is intentionally inert.
    if (window.console && typeof window.console.info === 'function') {
        window.console.info('FXW admin-dashboard: legacy AJAX layer is a no-op; using form POST instead.');
    }
})(jQuery);