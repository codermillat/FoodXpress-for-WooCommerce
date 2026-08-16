/* global jQuery, google, fxw_checkout_params */
/**
 * FoodXpress Native Checkout Manager v3
 *
 * Coordinates-only map flow: PlaceAutocompleteElement, Promise-based
 * Geocoder (display caption only — never fills form fields), draggable
 * pin, geolocation, delivery-radius circle, and REST-based zone
 * validation. Fees depend on the pin's lat/lng only.
 *
 * @since 1.1.0
 */
(function ($) {
    'use strict';

    const FXW = {
        // ─── State ───────────────────────────────────────────────
        state: {
            lat: null,
            lng: null,
            isCalculating: false,
            isValid: false,
            map: null,
            marker: null,
            geocoder: null,
            settings: {}
        },

        // ─── Constants ───────────────────────────────────────────
        C: {
            DEFAULT_CENTER: { lat: 23.8103, lng: 90.4125 },
            SKELETON_CLASS: 'fxw-skeleton-loading',
            NOTIFICATION_DURATION: 4000
        },

        // ─── Boot ────────────────────────────────────────────────
        init: function () {
            if (!$('#fxw-map').length) {
                return;
            }

            this.state.settings = { ...fxw_checkout_params };
            this.cacheElements();
            this.bindEvents();

            // Expose global callback for Google Maps async loading
            window.fxwInitMap = this.initMap.bind(this);

            if (typeof google !== 'undefined' && google.maps) {
                this.initMap();
            }
        },

        cacheElements: function () {
            this.$el = {
                mapContainer: $('#fxw-map'),
                searchWrapper: $('.fxw-location-search-wrapper'),
                searchInput: $('#fxw-location-search-input'),
                locateBtn: $('#fxw-get-location'),
                selectedLocation: $('#fxw-selected-location'),
                selectedAddress: $('#fxw-selected-address'),
                hiddenLat: $('#fxw_lat'),
                hiddenLng: $('#fxw_lng')
            };
        },

        bindEvents: function () {
            // Locate Me button
            this.$el.locateBtn.on('click', (e) => {
                e.preventDefault();
                this.getCurrentLocation();
            });
        },

        // ─── Google Maps Init ────────────────────────────────────
        initMap: async function () {
            if (this.state.map) {
                return;
            }

            try {
                const { Map } = await google.maps.importLibrary('maps');
                const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
                await google.maps.importLibrary('places');

                // Geocoder (promise-based)
                this.state.geocoder = new google.maps.Geocoder();

                const startCenter = this.getSavedCenter();

                this.state.map = new Map(this.$el.mapContainer[0], {
                    center: startCenter,
                    zoom: 15,
                    mapId: 'FOODXPRESS_MAP',
                    streetViewControl: false,
                    mapTypeControl: false,
                    fullscreenControl: false
                });

                // Draggable marker
                this.state.marker = new AdvancedMarkerElement({
                    map: this.state.map,
                    position: startCenter,
                    gmpDraggable: true
                });

                this.state.marker.addListener('dragend', () => {
                    const pos = this.state.marker.position;
                    this.onLocationChange(pos.lat, pos.lng, true);
                });

                // Delivery radius circle around the restaurant (visual zone limit)
                this.drawDeliveryRadius();

                // Setup PlaceAutocompleteElement
                this.setupAutocomplete();

                // Validate the saved/default location on load
                if (this.state.settings.saved_address && this.state.settings.saved_address.lat) {
                    this.onLocationChange(
                        parseFloat(this.state.settings.saved_address.lat),
                        parseFloat(this.state.settings.saved_address.lng),
                        false
                    );
                }
            } catch (err) {
                console.error('FXW: Map init failed', err);
                this.notify(this.t('error_generic'), 'error');
            }
        },

        // ─── PlaceAutocompleteElement ────────────────────────────
        /**
         * Creates and attaches the <gmp-place-autocomplete> web component.
         * This auto-manages session tokens internally (no billing leaks).
         */
        setupAutocomplete: function () {
            const autocompleteEl = new google.maps.places.PlaceAutocompleteElement({
                requestedRegion: 'bd',
                requestedLanguage: 'en',
                includedPrimaryTypes: ['geocode', 'establishment']
            });

            // Style the element to match the existing input
            autocompleteEl.style.width = '100%';
            autocompleteEl.style.marginBottom = '10px';

            // Hide the old text input and replace it with the web component
            this.$el.searchInput.hide();
            this.$el.searchWrapper[0].insertBefore(autocompleteEl, this.$el.searchInput[0]);

            // Listen for place selection
            autocompleteEl.addEventListener('gmp-placeselect', async (event) => {
                const place = event.place;

                // fetchFields ends the session token automatically
                await place.fetchFields({
                    fields: ['location', 'formattedAddress', 'displayName']
                });

                if (place.location) {
                    const lat = place.location.lat();
                    const lng = place.location.lng();

                    this.panMap(lat, lng);
                    this.onLocationChange(lat, lng, false);

                    // Show selected address caption (display only — no form filling)
                    this.showSelectedAddress(place.formattedAddress || place.displayName || '');
                }
            });
        },

        // ─── Location Lifecycle ──────────────────────────────────
        /**
         * Called whenever the delivery location changes (autocomplete,
         * geolocation, drag). The pin's coordinates are the ONLY thing
         * that flows onward — the reverse geocode feeds a display
         * caption and never touches any form field.
         *
         * @param {number}  lat
         * @param {number}  lng
         * @param {boolean} reverseGeocode  Whether to reverse-geocode for the caption.
         */
        onLocationChange: async function (lat, lng, reverseGeocode) {
            this.state.lat = lat;
            this.state.lng = lng;

            // Hidden fields for form submission
            this.$el.hiddenLat.val(lat);
            this.$el.hiddenLng.val(lng);

            // Caption under the map (display only)
            if (reverseGeocode && this.state.geocoder) {
                try {
                    const { results } = await this.state.geocoder.geocode({
                        location: { lat, lng }
                    });

                    if (results && results.length > 0) {
                        this.showSelectedAddress(results[0].formatted_address);
                    }
                } catch (err) {
                    console.warn('FXW: Reverse geocode failed', err);
                }
            }

            // Validate delivery zone via REST (also stores the session
            // coordinates the shipping method reads server-side)
            this.validateZone(lat, lng);
        },

        // ─── REST API: Validate Zone ─────────────────────────────
        validateZone: async function (lat, lng) {
            this.setLoading(true);

            try {
                const res = await fetch(
                    fxw_checkout_params.rest_url + '/validate-location',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': fxw_checkout_params.rest_nonce
                        },
                        body: JSON.stringify({ lat, lng })
                    }
                );

                const data = await res.json();

                if (res.ok && data.status === 'success') {
                    this.state.isValid = true;
                    const currency = fxw_checkout_params.currency_symbol || '৳';
                    this.notify(
                        `${this.t('calculating').replace('...', '')} ${currency}${data.fee} · ${data.distance_km} km · ${data.duration_text}`,
                        'success'
                    );
                    $(document.body).trigger('update_checkout');
                } else {
                    this.state.isValid = false;
                    this.notify(data.message || data.code || this.t('out_of_zone'), 'error');
                    $(document.body).trigger('update_checkout');
                }
            } catch (err) {
                console.error('FXW: Zone validation error', err);
                this.notify(this.t('error_generic'), 'error');
            } finally {
                this.setLoading(false);
            }
        },

        // ─── Auto-fill WC Fields ─────────────────────────────────
        // Removed in v1.2.3: Google Maps supplies coordinates only. The
        // exact address comes from the customer's own input and the fee
        // depends on the pin's lat/lng alone.

        // ─── Geolocation ─────────────────────────────────────────
        getCurrentLocation: function () {
            if (!navigator.geolocation) {
                this.notify(this.t('geolocation_unsupported'), 'error');
                return;
            }

            const $btn = this.$el.locateBtn;
            const originalText = $btn.text();
            $btn.addClass('loading').prop('disabled', true).text(this.t('locating'));

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    $btn.removeClass('loading').prop('disabled', false).text(originalText);
                    this.panMap(pos.coords.latitude, pos.coords.longitude);
                    this.onLocationChange(pos.coords.latitude, pos.coords.longitude, true);
                },
                (err) => {
                    $btn.removeClass('loading').prop('disabled', false).text(originalText);
                    const messages = {
                        1: this.t('location_denied'),
                        2: this.t('location_unavailable'),
                        3: this.t('location_timeout')
                    };
                    this.notify(messages[err.code] || this.t('error_generic'), 'error');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        },

        // ─── Map Helpers ─────────────────────────────────────────
        panMap: function (lat, lng) {
            if (!this.state.map || !this.state.marker) {
                return;
            }
            const pos = { lat, lng };
            this.state.map.panTo(pos);
            this.state.marker.position = pos;
        },

        getSavedCenter: function () {
            const saved = this.state.settings.saved_address;
            if (saved && saved.lat && saved.lng) {
                return {
                    lat: parseFloat(saved.lat),
                    lng: parseFloat(saved.lng)
                };
            }
            const restaurant = this.state.settings.restaurant_center;
            if (restaurant && restaurant.lat && restaurant.lng) {
                return {
                    lat: parseFloat(restaurant.lat),
                    lng: parseFloat(restaurant.lng)
                };
            }
            return this.C.DEFAULT_CENTER;
        },

        /**
         * Draws the delivery-radius circle around the restaurant so the
         * customer can see the selectable zone. Pins outside it are
         * rejected by the zone validation toast (and server-side).
         */
        drawDeliveryRadius: function () {
            const restaurant = this.state.settings.restaurant_center;
            const radiusKm = parseFloat(this.state.settings.radius_km);

            if (!restaurant || !restaurant.lat || !restaurant.lng || !radiusKm) {
                return;
            }

            try {
                new google.maps.Circle({
                    map: this.state.map,
                    center: { lat: parseFloat(restaurant.lat), lng: parseFloat(restaurant.lng) },
                    radius: radiusKm * 1000,
                    clickable: false,
                    fillColor: '#28a745',
                    fillOpacity: 0.06,
                    strokeColor: '#28a745',
                    strokeOpacity: 0.5,
                    strokeWeight: 1.5
                });
            } catch (err) {
                console.warn('FXW: Radius circle failed', err);
            }
        },

        // ─── UI Helpers ──────────────────────────────────────────
        showSelectedAddress: function (address) {
            if (!address) {
                return;
            }
            this.$el.selectedAddress.text(address);
            this.$el.selectedLocation.slideDown(200);
        },

        setLoading: function (on) {
            this.state.isCalculating = on;
            this.$el.mapContainer.toggleClass(this.C.SKELETON_CLASS, on);
        },

        notify: function (message, type) {
            let $n = $('#fxw-map-notification');
            if (!$n.length) {
                $n = $('<div id="fxw-map-notification" role="status" aria-live="polite"></div>')
                    .insertAfter('#fxw-location-picker-container');
            }

            // Clear any pending fadeOut
            $n.stop(true, true)
                .removeClass('error success info')
                .addClass(type)
                .text(message)
                .fadeIn(200);

            if (type !== 'error') {
                $n.delay(this.C.NOTIFICATION_DURATION).fadeOut(300);
            }
        },

        /**
         * Safe translation getter.
         */
        t: function (key) {
            return (fxw_checkout_params.translations && fxw_checkout_params.translations[key]) || key;
        }
    };

    // ─── DOM Ready ───────────────────────────────────────────────
    $(function () {
        FXW.init();
    });

})(jQuery);
