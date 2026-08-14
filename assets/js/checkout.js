/* global jQuery, google, fxw_checkout_params */
/**
 * FoodXpress Native Checkout Manager v2
 *
 * Uses PlaceAutocompleteElement (auto session tokens), Promise-based Geocoder,
 * WC Store API cart/update-customer, and REST-based zone validation.
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
            address: '',
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
            NOTIFICATION_DURATION: 4000,
            WC_STORE_API: '/wp-json/wc/store/v1'
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
                addressField: $('#fxw_delivery_address'),
                hiddenLat: $('#fxw_lat'),
                hiddenLng: $('#fxw_lng'),
                checkoutForm: $('form.checkout'),
                // WooCommerce standard shipping fields
                wc: {
                    address1: $('#shipping_address_1'),
                    city: $('#shipping_city'),
                    state: $('#shipping_state'),
                    postcode: $('#shipping_postcode'),
                    country: $('#shipping_country')
                }
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

                // Setup PlaceAutocompleteElement
                this.setupAutocomplete();

                // Load saved address or fetch restaurant centre
                if (this.state.settings.saved_address && this.state.settings.saved_address.lat) {
                    this.onLocationChange(
                        parseFloat(this.state.settings.saved_address.lat),
                        parseFloat(this.state.settings.saved_address.lng),
                        false
                    );
                } else {
                    this.fetchRestaurantCenter();
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
                    fields: ['location', 'formattedAddress', 'addressComponents', 'displayName']
                });

                if (place.location) {
                    const lat = place.location.lat();
                    const lng = place.location.lng();

                    this.panMap(lat, lng);
                    this.onLocationChange(lat, lng, false);

                    // Auto-fill WooCommerce fields
                    if (place.addressComponents) {
                        this.fillWCFields(place.addressComponents);
                    }

                    // Show selected address banner
                    this.showSelectedAddress(place.formattedAddress || place.displayName || '');
                }
            });
        },

        // ─── Location Lifecycle ──────────────────────────────────
        /**
         * Called whenever the delivery location changes (autocomplete, geolocation, drag).
         *
         * @param {number}  lat
         * @param {number}  lng
         * @param {boolean} reverseGeocode  Whether to reverse-geocode to get address.
         */
        onLocationChange: async function (lat, lng, reverseGeocode) {
            this.state.lat = lat;
            this.state.lng = lng;

            // Hidden fields for form submission
            this.$el.hiddenLat.val(lat);
            this.$el.hiddenLng.val(lng);

            // Reverse geocode if needed (e.g. marker drag, geolocation)
            if (reverseGeocode && this.state.geocoder) {
                try {
                    const { results } = await this.state.geocoder.geocode({
                        location: { lat, lng }
                    });

                    if (results && results.length > 0) {
                        this.showSelectedAddress(results[0].formatted_address);
                        this.fillWCFields(results[0].address_components);
                    }
                } catch (err) {
                    console.warn('FXW: Reverse geocode failed', err);
                }
            }

            // Two parallel actions:
            // 1. Validate delivery zone via our REST API
            // 2. Push address to WC Store API for shipping recalc
            this.validateZone(lat, lng);
            this.pushToStoreAPI();
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

        // ─── WC Store API: Push Address ──────────────────────────
        /**
         * Pushes the current shipping address to WC Store API
         * so that WooCommerce recalculates shipping rates & taxes in real time.
         */
        pushToStoreAPI: async function () {
            // Only push if we have WC fields populated
            const addr1 = this.$el.wc.address1.val();
            const city = this.$el.wc.city.val();
            const country = this.$el.wc.country.val();

            if (!addr1 || !city || !country) {
                return; // Not enough data to push yet
            }

            try {
                // Get the Store API nonce from WC's localized data
                const storeNonce = (typeof wc_store_js_params !== 'undefined' && wc_store_js_params.nonce)
                    ? wc_store_js_params.nonce
                    : '';

                const headers = {
                    'Content-Type': 'application/json'
                };

                if (storeNonce) {
                    headers['X-WC-Store-API-Nonce'] = storeNonce;
                } else {
                    // Fallback to WP REST nonce (works for logged-in users)
                    headers['X-WP-Nonce'] = fxw_checkout_params.rest_nonce;
                }

                await fetch(this.C.WC_STORE_API + '/cart/update-customer', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        shipping_address: {
                            address_1: this.$el.wc.address1.val() || '',
                            city: this.$el.wc.city.val() || '',
                            state: this.$el.wc.state.val() || '',
                            postcode: this.$el.wc.postcode.val() || '',
                            country: this.$el.wc.country.val() || ''
                        }
                    })
                });
                // We don't need to process the response —
                // WooCommerce checkout will refresh via update_checkout trigger.
            } catch (err) {
                // Non-critical; WC will still recalculate on form submit
                console.warn('FXW: Store API push failed', err);
            }
        },

        // ─── Auto-fill WC Fields ─────────────────────────────────
        /**
         * Maps Google Maps address_components to WooCommerce checkout fields.
         *
         * @param {Array} components  Google Maps GeocoderAddressComponent[]
         */
        fillWCFields: function (components) {
            if (!components || !components.length) {
                return;
            }

            const mapping = {};
            components.forEach(function (c) {
                // Handle both the old format ({types: []}) and new Place class ({types: []})
                const types = c.types || [];
                types.forEach(function (t) {
                    mapping[t] = c;
                });
            });

            // Street address (route + street_number if present)
            const streetNumber = mapping.street_number
                ? (mapping.street_number.long_name || mapping.street_number.longText || '') + ' '
                : '';
            const route = mapping.route
                ? (mapping.route.long_name || mapping.route.longText || '')
                : '';
            if (streetNumber || route) {
                this.$el.wc.address1.val(streetNumber + route).trigger('change');
            }

            // City
            const city = mapping.locality || mapping.sublocality_level_1 || mapping.administrative_area_level_2;
            if (city) {
                this.$el.wc.city.val(city.long_name || city.longText || '').trigger('change');
            }

            // State / Province
            const state = mapping.administrative_area_level_1;
            if (state) {
                this.$el.wc.state.val(state.short_name || state.shortText || '').trigger('change');
            }

            // Postcode
            const postcode = mapping.postal_code;
            if (postcode) {
                this.$el.wc.postcode.val(postcode.long_name || postcode.longText || '').trigger('change');
            }

            // Country (ISO 2-letter code)
            const country = mapping.country;
            if (country) {
                this.$el.wc.country.val(country.short_name || country.shortText || '').trigger('change');
            }
        },

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
            return this.C.DEFAULT_CENTER;
        },

        fetchRestaurantCenter: function () {
            fetch(fxw_checkout_params.rest_url + '/settings', {
                headers: { 'X-WP-Nonce': fxw_checkout_params.rest_nonce }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    // Restaurant lat/lng can be added to settings endpoint later
                    // For now we use the default center
                })
                .catch(function () { /* non-critical */ });
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
