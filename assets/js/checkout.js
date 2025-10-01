// Global variables for map components (must be accessible outside jQuery wrapper)
var fxwMap;
var fxwMarker; // AdvancedMarkerElement if available, else classic Marker
var fxwGeocoder;
var fxwAutocomplete; // PlaceAutocompleteElement or legacy Autocomplete
var fxwLat = null, fxwLng = null, fxwCoordsLocked = false;
// Constructors captured from module loader or legacy fallback
var fxwMapCtor = null, fxwAdvancedMarkerCtor = null, fxwMarkerCtor = null, fxwPlaceAutocompleteCtor = null, fxwCircleCtor = null;
var fxwMapRetryScheduled = false;

// DEBUG: Immediately log that the callback function is being defined
if (window.console && window.console.log) {
    console.log('FXW: Defining global callback function fxwInitMap');
}

// Global callback function for Google Maps API
window.fxwInitMap = function() {
    // DEBUG: Log every time the callback is triggered
    if (window.console && window.console.log) {
        console.log('FXW: Global callback fxwInitMap triggered');
    }
    
    // Check retry limit to prevent infinite loops
    if (fxwMapRetryScheduled && typeof fxw_checkout_params !== 'undefined' && fxw_checkout_params.max_retries) {
        var currentRetries = parseInt(window.fxwRetryCount || 0);
        if (currentRetries >= fxw_checkout_params.max_retries) {
            if (window.console && window.console.error) {
                console.error('FXW: Max retries exceeded, aborting map initialization');
            }
            return;
        }
        window.fxwRetryCount = currentRetries + 1;
    }
    
    // Ensure jQuery is available before proceeding
    if (typeof jQuery === 'undefined') {
        if (window.console && window.console.log) {
            console.log('FXW: jQuery not available, retrying in 100ms');
        }
        setTimeout(function() {
            window.fxwInitMap();
        }, 100);
        return;
    }
    
    // Check if DOM is ready and map container is accessible via jQuery
    if (jQuery('#fxw-map').length === 0) {
        if (window.console && window.console.log) {
            console.log('FXW: Map container not accessible via jQuery, retrying in 100ms');
        }
        setTimeout(function() {
            window.fxwInitMap();
        }, 100);
        return;
    }
    
    // All checks passed, initialize the map
    if (window.console && window.console.log) {
        console.log('FXW: All conditions met, calling initialization');
    }
    
    // Call the initialization function
    if (typeof window.fxwInternalInit === 'function') {
        window.fxwInternalInit();
    } else {
        if (window.console && window.console.log) {
            console.log('FXW: fxwInternalInit not available yet, setting up fallback');
        }
        // If the internal init function isn't ready yet, wait for it
        var initRetries = 0;
        var waitForInit = function() {
            if (typeof window.fxwInternalInit === 'function') {
                window.fxwInternalInit();
            } else if (initRetries < fxw_checkout_params.max_retries) {
                initRetries++;
                setTimeout(waitForInit, fxw_checkout_params.retry_delay);
            } else {
                if (window.console && window.console.error) {
                    console.error('FXW: Failed to initialize map - fxwInternalInit never became available');
                }
            }
        };
        waitForInit();
    }
};

// Ensure the callback function is immediately available
if (window.console && window.console.log) {
    console.log('FXW: Callback function ready, type:', typeof window.fxwInitMap);
}

jQuery(function($) {

    // Override console.error to catch Google Maps specific errors
    (function() {
        var originalError = console.error;
        console.error = function(message) {
            if (typeof message === 'string' && message.includes('without a valid Map ID')) {
                showError('Google Maps could not load. A Map ID may be required. Please check your API key and Map ID in the FoodXpress settings.');
            }
            originalError.apply(console, arguments);
        };
    })();

    // Google Maps authentication failure callback
    window.gm_authFailure = function() {
        showError('Google Maps authentication failed. Please check your API key.');
    };

    if (fxw_checkout_params.saved_address) {
        var saved = fxw_checkout_params.saved_address;
        if (saved.lat && saved.lng) {
            lockCoords(saved.lat, saved.lng);
            setValAndChange('#billing_address_1', saved.address_1);
            setValAndChange('#billing_address_2', saved.address_2);
            setValAndChange('#billing_city', saved.city);
            setValAndChange('#billing_postcode', saved.postcode);
            setValAndChange('#billing_country', saved.country);
            setTimeout(function () {
                setValAndChange('#billing_state', saved.state);
            }, 120);

            setValAndChange('#shipping_address_1', saved.address_1);
            setValAndChange('#shipping_address_2', saved.address_2);
            setValAndChange('#shipping_city', saved.city);
            setValAndChange('#shipping_postcode', saved.postcode);
            setValAndChange('#shipping_country', saved.country);
            setTimeout(function () {
                setValAndChange('#shipping_state', saved.state);
            }, 120);

            if (saved.unit) {
                $('#fxw_address_unit').val(saved.unit);
            }
            triggerUpdateCheckoutDebounced();
        }
    }
    // Prevent double initialization
    if (window.fxwMapInitialized) {
        if (window.console && window.console.log) {
            console.log('FXW: Map already initialized, skipping');
        }
        return;
    }

    // Use global variables instead of local ones
    var map = fxwMap;
    var marker = fxwMarker;
    var geocoder = fxwGeocoder;
    var autocomplete = fxwAutocomplete;
    // Constructors captured from module loader or legacy fallback
    var MapCtor = fxwMapCtor, AdvancedMarkerCtor = fxwAdvancedMarkerCtor, MarkerCtor = fxwMarkerCtor, PlaceAutocompleteCtor = fxwPlaceAutocompleteCtor, CircleCtor = fxwCircleCtor;
    // Note: fxwMapRetryScheduled is already declared globally, don't redeclare

    // Debounced checkout update to avoid race conditions
    var updateCheckoutTimer;
    function triggerUpdateCheckoutDebounced() {
        clearTimeout(updateCheckoutTimer);
        updateCheckoutTimer = setTimeout(function() {
            jQuery(document.body).trigger('update_checkout');
        }, 250);
    }

    // Helper to set value and trigger change (works for inputs and select2)
    function setValAndChange(selector, value) {
        var $el = jQuery(selector);
        if ($el.length) {
            $el.val(value).trigger('change');
        }
    }

    function ensureHiddenCoordFields() {
        var $form = $('form.checkout');
        if (!$form.length) { $form = $(document.body); }
        if (!$('#fxw_lat').length) { $('<input/>', { type: 'hidden', id: 'fxw_lat', name: 'fxw_lat' }).appendTo($form); }
        if (!$('#fxw_lng').length) { $('<input/>', { type: 'hidden', id: 'fxw_lng', name: 'fxw_lng' }).appendTo($form); }
    }

    function lockCoords(lat, lng) {
        fxwLat = lat; fxwLng = lng; fxwCoordsLocked = true;
        ensureHiddenCoordFields();
        $('#fxw_lat').val(lat);
        $('#fxw_lng').val(lng);
    }

    // Track user edits to Address_1 fields so we don't overwrite manual details
    $(document).on('input', '#billing_address_1, #shipping_address_1', function() {
        var $el = $(this);
        $el.data('fxwUserEdited', true);
        $el.data('fxwAuto', false);
    });

    function setAddress1IfAllowed(selector, value) {
        var $el = $(selector);
        if (!$el.length) return;
        var userEdited = $el.data('fxwUserEdited') === true;
        var isAuto     = $el.data('fxwAuto') === true;
        var current    = ($el.val() || '').trim();

        // Only set if field is empty, or previously auto-filled by us
        if (!userEdited && (current === '' || isAuto)) {
            $el.val(value);
            $el.data('fxwAuto', true);
            if (!fxwCoordsLocked) { $el.trigger('change'); }
        }
    }

    // Helpers for AdvancedMarkerElement vs classic Marker
    function setMarkerPosition(pos) {
        if (!marker) return;
        // AdvancedMarkerElement has a writable "position" property
        if ('position' in marker) {
            marker.position = pos;
        }
        // Classic Marker has setPosition()
        if (typeof marker.setPosition === 'function') {
            marker.setPosition(pos);
        }
    }

    function getMarkerPosition() {
        if (!marker) return null;
        if ('position' in marker && marker.position) {
            return marker.position;
        }
        if (typeof marker.getPosition === 'function') {
            return marker.getPosition();
        }
        return null;
    }

    /**
     * Calculate address completeness score (0-100).
     * @param {string} address - The address to score
     * @returns {number} Score from 0 to 100
     */
    function calculateAddressScore(address) {
        if (!address || address.trim().length === 0) {
            return 0;
        }

        var score = 0;
        var addressLower = address.toLowerCase();

        // Length scoring (max 25 points)
        if (address.length >= 20) score += 10;
        if (address.length >= 40) score += 10;
        if (address.length >= 60) score += 5;

        // Number presence (15 points)
        if (/\d+/.test(address)) score += 15;

        // Building info (20 points)
        var buildingKeywords = ['flat', 'apartment', 'apt', 'floor', 'building', 'block', 'house', 'home', 'tower', 'complex', 'society', 'villa', 'bungalow', 'street', 'road', 'lane', 'avenue'];
        for (var i = 0; i < buildingKeywords.length; i++) {
            if (addressLower.indexOf(buildingKeywords[i]) !== -1) {
                score += 20;
                break;
            }
        }

        // Location context (15 points)
        var locationKeywords = ['near', 'opposite', 'behind', 'next to', 'beside', 'landmark', 'gate', 'entrance', 'sector', 'area', 'locality', 'colony', 'city', 'town'];
        for (var i = 0; i < locationKeywords.length; i++) {
            if (addressLower.indexOf(locationKeywords[i]) !== -1) {
                score += 15;
                break;
            }
        }

        // Delivery hints (15 points)
        var deliveryKeywords = ['floor', 'gate', 'entrance', 'lift', 'stairs', 'bell', 'security', 'guard', 'door', 'parking'];
        for (var i = 0; i < deliveryKeywords.length; i++) {
            if (addressLower.indexOf(deliveryKeywords[i]) !== -1) {
                score += 15;
                break;
            }
        }

        // Punctuation indicating structure (10 points)
        if (/[,;]/.test(address)) score += 10;

        return Math.min(100, score);
    }

    /**
     * Generate actionable suggestions to improve address.
     * @param {string} address - The address to analyze
     * @param {object} validationResult - The validation result object
     * @returns {array} Array of suggestion strings
     */
    function generateAddressSuggestions(address, validationResult) {
        var suggestions = [];
        var addressLower = address.toLowerCase();
        var hasNumbers = /\d+/.test(address);
        
        var buildingKeywords = ['flat', 'apartment', 'apt', 'floor', 'building', 'block', 'house', 'home', 'tower', 'complex', 'society', 'street', 'road', 'lane'];
        var hasBuildingInfo = buildingKeywords.some(function(kw) { return addressLower.indexOf(kw) !== -1; });
        
        var deliveryKeywords = ['floor', 'gate', 'entrance', 'lift', 'stairs', 'bell', 'security', 'door'];
        var hasDeliveryHints = deliveryKeywords.some(function(kw) { return addressLower.indexOf(kw) !== -1; });

        // Generate specific suggestions based on what's missing
        if (!hasNumbers) {
            suggestions.push('Add your flat/house/building number');
        }

        if (!hasBuildingInfo) {
            suggestions.push('Include building or street name');
        }

        if (address.length < 40) {
            suggestions.push('Add more context (e.g., nearby landmarks, cross streets)');
        }

        if (!hasDeliveryHints && address.length < 60) {
            suggestions.push('Add delivery instructions (e.g., "2nd floor, ring bell twice")');
        }

        // Positive reinforcement
        if (validationResult.score >= 80 && suggestions.length === 0) {
            suggestions.push('Great! Your address is detailed and complete.');
        }

        return suggestions;
    }

    /**
     * Validates address completeness for delivery requirements with detailed feedback.
     * @param {string} address - The address to validate
     * @returns {object} Object with 'isComplete' boolean, 'message' string, 'severity' string, 'score' number, 'suggestions' array
     */
    function validateAddressCompleteness(address) {
        var score = calculateAddressScore(address);
        var result = {
            isComplete: false,
            message: '',
            severity: 'error',
            score: score,
            suggestions: []
        };

        if (!address || address.trim().length === 0) {
            result.message = 'Address field is empty';
            result.severity = 'error';
            result.suggestions = ['Use the map to select your location', 'Or search for your address above'];
            return result;
        }

        if (address.length < 20) {
            result.message = 'Address is too short - please provide more details';
            result.severity = 'warning';
            result.suggestions = generateAddressSuggestions(address, result);
            return result;
        }

        var addressLower = address.toLowerCase();
        var hasNumbers = /\d+/.test(address);
        
        var buildingKeywords = ['flat', 'apartment', 'apt', 'floor', 'building', 'block', 'house', 'home', 'tower', 'complex', 'society', 'villa', 'bungalow', 'street', 'road', 'lane', 'avenue'];
        var hasBuildingInfo = buildingKeywords.some(function(kw) { return addressLower.indexOf(kw) !== -1; });
        
        var locationKeywords = ['near', 'opposite', 'behind', 'next to', 'beside', 'landmark', 'gate', 'entrance', 'sector', 'area', 'locality', 'colony', 'city', 'town'];
        var hasLocationInfo = locationKeywords.some(function(kw) { return addressLower.indexOf(kw) !== -1; });

        // Score-based evaluation
        if (score >= 80) {
            result.isComplete = true;
            result.message = 'Excellent! Your address is complete and detailed';
            result.severity = 'success';
        } else if (score >= 60) {
            result.isComplete = true;
            result.message = 'Good address - consider adding more details for easier delivery';
            result.severity = 'info';
        } else if (score >= 40) {
            result.isComplete = (hasBuildingInfo || hasLocationInfo) && hasNumbers;
            result.message = result.isComplete ? 'Address is acceptable but could be improved' : 'Please add more specific details';
            result.severity = 'warning';
        } else {
            result.isComplete = false;
            result.message = 'Address needs more information for successful delivery';
            result.severity = 'error';
        }

        result.suggestions = generateAddressSuggestions(address, result);
        return result;
    }

    // Update address field feedback with progressive disclosure and visual indicators
    function updateAddressFieldFeedback(result) {
        var addressField = $('#fxw_delivery_address');
        var feedbackContainer = addressField.closest('.form-row').find('.fxw-address-feedback');

        // Create feedback container if it doesn't exist
        if (feedbackContainer.length === 0) {
            addressField.closest('.form-row').append('<div class="fxw-address-feedback"></div>');
        }

        var container = addressField.closest('.form-row').find('.fxw-address-feedback');

        // Clear previous feedback
        container.removeClass('success info warning error visible').empty();

        if (result.message) {
            // Build feedback HTML with progressive disclosure
            var feedbackHTML = '<span class="fxw-feedback-icon"></span>';
            feedbackHTML += '<div class="fxw-feedback-content">';
            feedbackHTML += '<p class="fxw-feedback-message">' + result.message + '</p>';
            
            // Add score indicator for non-error states
            if (result.score > 0) {
                feedbackHTML += '<div class="fxw-completeness-score">';
                feedbackHTML += '<span>Completeness:</span>';
                feedbackHTML += '<div class="fxw-score-bar"><div class="fxw-score-fill" style="width: ' + result.score + '%"></div></div>';
                feedbackHTML += '<span>' + result.score + '%</span>';
                feedbackHTML += '</div>';
            }
            
            // Add suggestions with progressive disclosure
            if (result.suggestions && result.suggestions.length > 0) {
                feedbackHTML += '<button type="button" class="fxw-suggestions-toggle">Show suggestions</button>';
                feedbackHTML += '<ul class="fxw-address-suggestions">';
                result.suggestions.forEach(function(suggestion) {
                    feedbackHTML += '<li>' + suggestion + '</li>';
                });
                feedbackHTML += '</ul>';
            }
            
            feedbackHTML += '</div>';
            
            container.addClass(result.severity + ' visible').html(feedbackHTML);
            
            // Bind toggle for suggestions
            container.find('.fxw-suggestions-toggle').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var list = btn.next('.fxw-address-suggestions');
                btn.toggleClass('expanded');
                list.toggleClass('visible');
                btn.text(btn.hasClass('expanded') ? 'Hide suggestions' : 'Show suggestions');
            });
        }
    }

    // Real-time address validation handler with debouncing
    function handleAddressValidation() {
        var addressField = $('#fxw_delivery_address');
        var validationTimeout;

        // Debounced validation to avoid excessive checks
        function debounceValidation() {
            clearTimeout(validationTimeout);
            validationTimeout = setTimeout(function() {
                var result = validateAddressCompleteness(addressField.val());
                updateAddressFieldFeedback(result);

                // Update field styling based on validation
                addressField.removeClass('fxw-valid fxw-invalid fxw-incomplete');
                if (result.isComplete && result.score >= 80) {
                    addressField.addClass('fxw-valid');
                } else if (!result.isComplete || result.severity === 'error') {
                    addressField.addClass('fxw-invalid');
                } else {
                    addressField.addClass('fxw-incomplete');
                }
            }, 500); // Wait 500ms after user stops typing
        }

        // Bind validation events
        addressField.on('input paste keyup', debounceValidation);

        // Initial validation on page load
        if (addressField.val().length > 0) {
            debounceValidation();
        }
    }

    // Initialize address validation when DOM is ready
    $(document).ready(function() {
        if ($('#fxw_delivery_address').length > 0) {
            handleAddressValidation();
        }
    });

    /**
     * Initializes the map and all related components using modern Google Maps APIs.
     */
    async function initMap() {
        try {
            // Load libraries with the new loader (module constructors)
            const { Map, Circle } = await google.maps.importLibrary('maps');
            const { AdvancedMarkerElement } = await google.maps.importLibrary('marker');
            const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
            const { Geocoder } = await google.maps.importLibrary('geocoding');
            geocoder = new Geocoder();
            // Capture constructors
            MapCtor = typeof Map === 'function' ? Map : MapCtor;
            AdvancedMarkerCtor = typeof AdvancedMarkerElement === 'function' ? AdvancedMarkerElement : AdvancedMarkerCtor;
            PlaceAutocompleteCtor = typeof PlaceAutocompleteElement === 'function' ? PlaceAutocompleteElement : PlaceAutocompleteCtor;
            CircleCtor = typeof Circle === 'function' ? Circle : CircleCtor;
        } catch (e) {
            // If importLibrary fails (older keys), continue with globals
            if (window.fxw_checkout_params && fxw_checkout_params.debug) {
                console.log('fxw: importLibrary fallback', e);
            }
            // Fallback to legacy constructor if available
            if (google.maps && google.maps.Geocoder) {
                geocoder = new google.maps.Geocoder();
            }
            // Legacy constructors as fallback
            if (google.maps && typeof google.maps.Map === 'function') { MapCtor = google.maps.Map; }
            if (google.maps && google.maps.marker && typeof google.maps.marker.AdvancedMarkerElement === 'function') { AdvancedMarkerCtor = google.maps.marker.AdvancedMarkerElement; }
            if (google.maps && typeof google.maps.Marker === 'function') { MarkerCtor = google.maps.Marker; }
            if (google.maps && google.maps.places && typeof google.maps.places.PlaceAutocompleteElement === 'function') { PlaceAutocompleteCtor = google.maps.places.PlaceAutocompleteElement; }
            if (google.maps && typeof google.maps.Circle === 'function') { CircleCtor = google.maps.Circle; }
        }

        // Default to a central location if restaurant location isn't available.
        var defaultLatLng = { lat: 23.8103, lng: 90.4125 }; // Default to Dhaka

        var mapOpts = {
            center: defaultLatLng,
            zoom: 12,
            streetViewControl: false,
            mapTypeControl: false
        };

        // Optional Map ID support if provided in settings
        try {
            if (fxw_checkout_params && fxw_checkout_params.options && fxw_checkout_params.options.fxw_map_id) {
                mapOpts.mapId = fxw_checkout_params.options.fxw_map_id;
            }
        } catch(_) {}

        // Create map with robust fallback
        var mapEl = document.getElementById('fxw-map');
        var mapCreated = false;
        if (typeof MapCtor === 'function') {
            try {
                map = new MapCtor(mapEl, mapOpts);
                mapCreated = true;
            } catch (e) {
                if (fxw_checkout_params && fxw_checkout_params.debug) {
                    console.log('fxw: MapCtor failed', e);
                }
            }
        }
        if (!mapCreated && google && google.maps && typeof google.maps.Map === 'function') {
            try {
                map = new google.maps.Map(mapEl, mapOpts);
                mapCreated = true;
            } catch (e) {
                if (fxw_checkout_params && fxw_checkout_params.debug) {
                    console.log('fxw: legacy Map constructor failed', e);
                }
            }
        }
        if (!mapCreated) {
            if (fxw_checkout_params && fxw_checkout_params.debug) {
                console.log('fxw: Map creation failed - no constructor available');
            }
            if (!fxwMapRetryScheduled) {
                fxwMapRetryScheduled = true;
                setTimeout(function() {
                    if (fxw_checkout_params && fxw_checkout_params.debug) {
                        console.log('fxw: retrying map initialization after backoff');
                    }
                    initMap();
                }, 500);
                return;
            }
            showError('Map failed to load. Please reload the page.');
            return;
        }

        // Prefer AdvancedMarkerElement (new API). Fallback to classic Marker if not available.
        if (typeof AdvancedMarkerCtor === 'function') {
            marker = new AdvancedMarkerCtor({
                map: map,
                position: defaultLatLng,
                gmpDraggable: true
            });
            if (typeof marker.addListener === 'function') {
                marker.addListener('dragend', handleMarkerDrag);
            }
        } else if (typeof MarkerCtor === 'function') {
            marker = new MarkerCtor({
                position: defaultLatLng,
                map: map,
                draggable: true,
                icon: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
            });
            if (google.maps && google.maps.event && typeof google.maps.event.addListener === 'function') {
                google.maps.event.addListener(marker, 'dragend', handleMarkerDrag);
            } else if (typeof marker.addListener === 'function') {
                marker.addListener('dragend', handleMarkerDrag);
            }
        } else {
            // Last-resort legacy fallback
            marker = new google.maps.Marker({
                position: defaultLatLng,
                map: map,
                draggable: true,
                icon: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png'
            });
            if (google.maps && google.maps.event && typeof google.maps.event.addListener === 'function') {
                google.maps.event.addListener(marker, 'dragend', handleMarkerDrag);
            }
        }

        // Set up Places Autocomplete - prefer PlaceAutocompleteElement if available
        var searchInput = document.getElementById('fxw-location-search-input');
        if (searchInput) {
            if (typeof PlaceAutocompleteCtor === 'function' && window.customElements) {
                try {
                    var pae = new PlaceAutocompleteCtor();
                    // Bind the existing input to the element
                    pae.inputElement = searchInput;

                    pae.addEventListener('gmp-select', async function() {
                        try {
                            var place = pae.getPlace && pae.getPlace();
                            if (place && typeof place.fetchFields === 'function') {
                                await place.fetchFields({
                                    fields: ['displayName', 'formattedAddress', 'location', 'addressComponents']
                                });
                            }
                            handlePlaceResult(place);
                        } catch (err) {
                            showError('Address lookup failed. Please try again.');
                            if (fxw_checkout_params && fxw_checkout_params.debug) {
                                console.log('fxw: pae gmp-select error', err);
                            }
                        }
                    });
                    autocomplete = pae;
                } catch (e) {
                    // Fallback to legacy Autocomplete
                    setupLegacyAutocomplete(searchInput);
                }
            } else {
                // Fallback to legacy Autocomplete
                setupLegacyAutocomplete(searchInput);
            }
        }

        $('#fxw-get-location').on('click', handleGetCurrentLocation);

        // Mark map as successfully initialized
        window.fxwMapInitialized = true;

        // Store global references for potential external access
        fxwMap = map;
        fxwMarker = marker;
        fxwGeocoder = geocoder;
        fxwAutocomplete = autocomplete;

        if (window.console && window.console.log) {
            console.log('FXW: Map initialization completed successfully');
        }

        // Fetch restaurant location to center the map
        fetchRestaurantLocation();

        // Attempt to prefill session from existing shipping fields if present (saved address flow)
        setTimeout(tryPrefillFromShippingFields, 0);
    }

    function setupLegacyAutocomplete(searchInput) {
        var fields = ['address_components', 'geometry', 'name', 'formatted_address'];
        var ac = new google.maps.places.Autocomplete(searchInput, { fields: fields });
        if (typeof ac.bindTo === 'function') {
            ac.bindTo('bounds', map);
        }
        ac.addListener('place_changed', function() {
            var place = ac.getPlace();
            handlePlaceResult(place);
        });
        autocomplete = ac;
    }

    /**
     * Normalizes and handles a Place selection from either PlaceAutocompleteElement or legacy Autocomplete.
     */
    function handlePlaceResult(place) {
        if (!place) {
            showError('Please select a valid address from the suggestions.');
            return;
        }

        // New Places API (PlaceAutocompleteElement) returns Place with "location" and "addressComponents"
        var hasNewShape = !!(place.location || place.addressComponents || place.formattedAddress || place.displayName);

        if (hasNewShape) {
            var loc = place.location || (place.geometry && place.geometry.location) || null;
            if (loc) {
                map.setCenter(loc);
                setMarkerPosition(loc);
            }

            // Build a legacy-like object for downstream processing
            var legacy = {
                address_components: [],
                geometry: { location: loc || null },
                name: '',
                formatted_address: place.formatted_address || place.formattedAddress || ''
            };

            // Name/display name
            if (place.name) {
                legacy.name = place.name;
            } else if (place.displayName) {
                // displayName can be plain string or { text }
                legacy.name = typeof place.displayName === 'string' ? place.displayName : (place.displayName.text || '');
            }

            // Address components conversion (new -> legacy)
            if (Array.isArray(place.addressComponents) && place.addressComponents.length) {
                legacy.address_components = place.addressComponents.map(function(c) {
                    return {
                        long_name: c.longText || c.long_name || '',
                        short_name: c.shortText || c.short_name || '',
                        types: c.types || []
                    };
                });
            } else if (Array.isArray(place.address_components)) {
                legacy.address_components = place.address_components;
            }

            // Update fields and input
            if (legacy.formatted_address) {
                $('#fxw-location-search-input').val(legacy.formatted_address);
            }
            updateAddressFields(legacy);
            return;
        }

        // Legacy Autocomplete place
        if (place.geometry && place.geometry.location) {
            map.setCenter(place.geometry.location);
            setMarkerPosition(place.geometry.location);
            updateAddressFields(place);
        } else {
            showError('Please select a valid address from the suggestions.');
        }
    }


    /**
     * Fetches the restaurant's location to center the map.
     */
    function fetchRestaurantLocation() {
        $.post(fxw_checkout_params.ajax_url, { action: 'fxw_get_restaurant_location', nonce: fxw_checkout_params.nonce })
            .done(function(response) {
                if (response && response.success && response.data && response.data.lat && response.data.lng) {
                    var restaurantLatLng = { lat: response.data.lat, lng: response.data.lng };
                    map.setCenter(restaurantLatLng);
                    setMarkerPosition(restaurantLatLng);
                    drawDeliveryZone(restaurantLatLng);
                } else {
                    var msg = (response && response.data && response.data.message) ? response.data.message : 'Invalid restaurant location response from server.';
                    showError(msg);
                }
            })
            .fail(function(jqXHR) {
                var errorMsg = 'Could not fetch restaurant location. Please try reloading the page.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMsg = jqXHR.responseJSON.data.message;
                } else if (jqXHR.status === 403) {
                    errorMsg = 'Session expired. Please reload the page and try again.';
                }
                showError(errorMsg);
                if (fxw_checkout_params && fxw_checkout_params.debug) {
                    console.error('fxw_get_restaurant_location fail:', {
                        status: jqXHR.status,
                        response: jqXHR.responseJSON || jqXHR.responseText
                    });
                }
            });
    }

    /**
     * Attempt to geocode current shipping fields and persist session lat/lng
     * Useful when checkout is pre-filled from saved/account address.
     */
    function tryPrefillFromShippingFields() {
        if (window.fxwPrefilledFromShipping) return;
        if (fxwCoordsLocked) return;
        var addr1    = ($('#shipping_address_1').val() || '').trim();
        var addr2    = ($('#shipping_address_2').val() || '').trim();
        var city     = ($('#shipping_city').val() || '').trim();
        var state    = ($('#shipping_state').val() || '').trim();
        var postcode = ($('#shipping_postcode').val() || '').trim();
        var country  = ($('#shipping_country').val() || '').trim();

        // Require at least city and country to avoid meaningless lookups
        if (!city || !country) return;

        var parts = [];
        if (addr1) parts.push(addr1);
        if (addr2) parts.push(addr2);
        parts.push(city);
        if (state) parts.push(state);
        if (postcode) parts.push(postcode);
        parts.push(country);

        var fullAddress = parts.join(', ');
        if (!geocoder || typeof geocoder.geocode !== 'function') {
            if (window.fxw_checkout_params && fxw_checkout_params.debug) {
                console.log('fxw: geocoder unavailable for prefill; skipping');
            }
            return;
        }
        try {
            geocoder.geocode({ address: fullAddress }, function(responses) {
                if (responses && responses.length > 0) {
                    // Update map UI (center + marker) if available
                    if (map && marker) {
                        var loc = responses[0].geometry.location;
                        map.setCenter(loc);
                        setMarkerPosition(loc);
                    }
                    // Reuse existing flow to set fields + persist session
                    updateAddressFields(responses[0]);
                    $('#fxw-location-search-input').val(responses[0].formatted_address);
                    window.fxwPrefilledFromShipping = true;
                    triggerUpdateCheckoutDebounced();
                    if (window.fxw_checkout_params && fxw_checkout_params.debug) {
                        console.log('fxw: prefilling from shipping fields via geocode', fullAddress);
                    }
                }
            });
        } catch (e) {
            if (window.fxw_checkout_params && fxw_checkout_params.debug) {
                console.log('fxw: tryPrefillFromShippingFields error', e);
            }
        }
    }

    /**
     * Draws the delivery zone circle on the map.
     * @param {google.maps.LatLng} center - The center of the circle.
     */
    function drawDeliveryZone(center) {
        var options = fxw_checkout_params.options || {};
        var radius = parseFloat(options.fxw_delivery_zone_radius || 0) * 1000; // in meters
        if (radius > 0) {
            var circleOpts = {
                strokeColor: '#007cff',
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: '#007cff',
                fillOpacity: 0.1,
                map: map,
                center: center,
                radius: radius
            };
            if (typeof CircleCtor === 'function') {
                try { new CircleCtor(circleOpts); } catch (e) {
                    if (fxw_checkout_params && fxw_checkout_params.debug) { console.log('fxw: CircleCtor failed', e); }
                }
            } else if (google.maps && typeof google.maps.Circle === 'function') {
                try { new google.maps.Circle(circleOpts); } catch (e) {
                    if (fxw_checkout_params && fxw_checkout_params.debug) { console.log('fxw: legacy Circle failed', e); }
                }
            }
        }
    }

    /**
     * Handles the marker drag event.
     */
    function handleMarkerDrag() {
        var pos = getMarkerPosition();
        if (pos) {
            geocodePosition(pos);
        }
    }

    /**
     * Handles the click event for "Use My Location".
     * @param {Event} e - The click event.
     */
    function handleGetCurrentLocation(e) {
        e.preventDefault();
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                var pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                map.setCenter(pos);
                setMarkerPosition(pos);
                geocodePosition(pos);
            }, function() {
                showError('Geolocation failed. Please search for your address or use the map.');
            });
        } else {
            showError('Your browser does not support Geolocation.');
        }
    }

    /**
     * Geocodes a position to get the address.
     * @param {google.maps.LatLng|{lat:number,lng:number}} pos - The position to geocode.
     */
    function geocodePosition(pos) {
        if (!geocoder || typeof geocoder.geocode !== 'function') {
            if (fxw_checkout_params && fxw_checkout_params.debug) {
                console.log('fxw: geocoder unavailable, skipping reverse geocode');
            }
            showError('Address lookup is unavailable at the moment. Please type your address.');
            return;
        }
        geocoder.geocode({ location: pos }, function(responses) {
            if (responses && responses.length > 0) {
                updateAddressFields(responses[0]);
                $('#fxw-location-search-input').val(responses[0].formatted_address);
            } else {
                showError('Cannot determine address at this location.');
            }
        });
    }

    /**
     * Updates the WooCommerce checkout address fields and the new single delivery address field.
     * Accepts either a legacy PlaceResult/GeocoderResult or a normalized object built in handlePlaceResult.
     * @param {object} place - The place/geocoder result.
     */
    function updateAddressFields(place) {
        var components = {};
        var addrComps = [];

        // Support new shape (normalized) and legacy
        if (Array.isArray(place.address_components)) {
            addrComps = place.address_components;
        } else if (Array.isArray(place.addressComponents)) {
            addrComps = place.addressComponents.map(function(c) {
                return {
                    long_name: c.longText || c.long_name || '',
                    short_name: c.shortText || c.short_name || '',
                    types: c.types || []
                };
            });
        }

        if (addrComps.length) {
            addrComps.forEach(function(component) {
                var type = component.types && component.types[0];
                if (!type) return;
                components[type] = {
                    long_name: component.long_name,
                    short_name: component.short_name
                };
            });

            var streetNumber = components.street_number ? components.street_number.long_name : '';
            var route = components.route ? components.route.long_name : '';
            var sublocality = components.sublocality_level_1 ? components.sublocality_level_1.long_name : '';
            var city = components.locality ? components.locality.long_name : (components.administrative_area_level_2 ? components.administrative_area_level_2.long_name : '');
            var state = components.administrative_area_level_1 ? components.administrative_area_level_1.short_name : '';
            var country = components.country ? components.country.short_name : '';
            var postcode = components.postal_code ? components.postal_code.long_name : '';

            var address1 = '';
            var nameFromPlace = place.name || (place.displayName ? (typeof place.displayName === 'string' ? place.displayName : (place.displayName.text || '')) : '');
            if (nameFromPlace && nameFromPlace !== route && nameFromPlace !== streetNumber) {
                address1 = nameFromPlace;
            }
            if (route && streetNumber) {
                address1 += (address1 ? ', ' : '') + streetNumber + ' ' + route;
            } else if (route) {
                address1 += (address1 ? ', ' : '') + route;
            }
            if (sublocality) {
                address1 += (address1 ? ', ' : '') + sublocality;
            }

            // Build complete address for the single delivery address field
            var fullDeliveryAddress = '';
            var formattedAddress = place.formatted_address || place.formattedAddress || '';
            if (formattedAddress) {
                fullDeliveryAddress = formattedAddress;
            } else {
                // Fallback: build address from components
                var addressParts = [];
                if (address1) addressParts.push(address1);
                if (city) addressParts.push(city);
                if (state) addressParts.push(state);
                if (postcode) addressParts.push(postcode);
                if (country) addressParts.push(country);
                fullDeliveryAddress = addressParts.join(', ');
            }

            // Enhanced delivery address field update with validation
            if (fullDeliveryAddress && $('#fxw_delivery_address').length) {
                var currentValue = $('#fxw_delivery_address').val().trim();
                var hasUserContent = currentValue.length > 0 && !currentValue.includes(formattedAddress);
                
                if (!hasUserContent || currentValue.length < 15) {
                    // Replace with new address if field is empty or has insufficient detail
                    $('#fxw_delivery_address').val(fullDeliveryAddress).trigger('change');
                } else {
                    // If user has added custom content, append new address
                    var combinedAddress = fullDeliveryAddress;
                    if (hasUserContent && !currentValue.includes(fullDeliveryAddress)) {
                        combinedAddress = fullDeliveryAddress + '\n' + currentValue;
                    }
                    $('#fxw_delivery_address').val(combinedAddress).trigger('change');
                }
                
                // Enhanced visual feedback for address completion
                var $addressField = $('#fxw_delivery_address');
                $addressField.removeClass('fxw-address-incomplete fxw-address-error').addClass('fxw-address-complete');
                
                // Add completion indicator
                if (!$('.fxw-address-status').length) {
                    $addressField.after('<div class="fxw-address-status fxw-address-status-complete">✓ Address selected from map</div>');
                } else {
                    $('.fxw-address-status').removeClass('fxw-address-status-incomplete fxw-address-status-error')
                        .addClass('fxw-address-status-complete')
                        .text('✓ Address selected from map');
                }
                
                // Validate address completeness in real-time
                var result = validateAddressCompleteness($addressField.val());
                updateAddressFieldFeedback(result);
            }

            // Still update hidden WooCommerce fields for backend compatibility
            setValAndChange('#billing_address_1', (address1 || '').trim());
            setValAndChange('#billing_city', city);
            setValAndChange('#billing_postcode', postcode);
            setValAndChange('#billing_country', country);
            setTimeout(function () {
                setValAndChange('#billing_state', state);
            }, 120);

            setValAndChange('#shipping_address_1', (address1 || '').trim());
            setValAndChange('#shipping_city', city);
            setValAndChange('#shipping_postcode', postcode);
            setValAndChange('#shipping_country', country);
            setTimeout(function () {
                setValAndChange('#shipping_state', state);
            }, 120);

            // Send the location data to the backend
            var lat = null, lng = null;
            if (place.geometry && place.geometry.location) {
                lat = typeof place.geometry.location.lat === 'function' ? place.geometry.location.lat() : place.geometry.location.lat;
                lng = typeof place.geometry.location.lng === 'function' ? place.geometry.location.lng() : place.geometry.location.lng;
            } else if (place.location) {
                lat = typeof place.location.lat === 'function' ? place.location.lat() : place.location.lat;
                lng = typeof place.location.lng === 'function' ? place.location.lng() : place.location.lng;
            }

            var payload = {
                action: 'fxw_update_customer_location',
                nonce: fxw_checkout_params.nonce,
                lat: lat,
                lng: lng,
                address: {
                    address_1: (address1 || '').trim(),
                    address_2: '', // optional unit captured separately
                    city: city,
                    state: state,
                    postcode: postcode,
                    country: country
                }
            };
            if (fxw_checkout_params && fxw_checkout_params.debug) {
                console.log('fxw_update_customer_location: payload', payload);
            }
            $.post(fxw_checkout_params.ajax_url, payload)
                .done(function(response) {
                    if (fxw_checkout_params && fxw_checkout_params.debug) {
                        console.log('fxw_update_customer_location: response', response);
                    }
                    if (response && response.success) {
                        // Persist and lock coordinates, then trigger checkout update
                        if (lat !== null && lng !== null) { lockCoords(lat, lng); }
                        triggerUpdateCheckoutDebounced();
                    } else {
                        var msg = (response && response.data) ? (typeof response.data === 'string' ? response.data : 'Update failed') : 'Update failed';
                        showError('Could not update location: ' + msg);
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    var serverMsg = (jqXHR.responseJSON && (jqXHR.responseJSON.data || jqXHR.responseJSON.message)) || jqXHR.responseText || errorThrown || textStatus;
                    showError('Update location failed: ' + serverMsg);
                    if (fxw_checkout_params && fxw_checkout_params.debug) {
                        console.log('fxw_update_customer_location: fail', { status: jqXHR.status, textStatus: textStatus, errorThrown: errorThrown, responseText: jqXHR.responseText });
                    }
                });
        }
    }

    /**
     * Shows an error message to the user.
     * @param {string} message - The error message to display.
     */
    function showError(message) {
        var errorDiv = $('#fxw-geolocation-error');
        if (errorDiv.text() === message && errorDiv.is(':visible')) {
            return; // Don't show the same message twice
        }
        errorDiv.text(message);
        errorDiv.slideDown();
        setTimeout(function() {
            errorDiv.slideUp();
        }, 5000);
    }

    // Set up the internal init function that the global callback will use
    window.fxwInternalInit = function() {
        if (window.console && window.console.log) {
            console.log('FXW: fxwInternalInit called');
        }
        if ($('#fxw-map').length) {
            if (window.console && window.console.log) {
                console.log('FXW: Map container found, calling initMap()');
            }
            initMap();
        } else {
            if (window.console && window.console.error) {
                console.error('FXW: Map container not found in fxwInternalInit');
            }
        }
    };
    
    // Debug: Log that the internal init function is ready
    if (window.console && window.console.log) {
        console.log('FXW: fxwInternalInit function is now available');
    }

    // Initialize on document ready
    function initializeGoogleMaps() {
        // Initialize immediately if Google Maps is already loaded (fallback)
        // Ensure native browser autocomplete does not interfere with Places Autocomplete UX
        if ($('#fxw-location-search-input').length) {
            $('#fxw-location-search-input').attr('autocomplete', 'off');
        }
        
        // If Google Maps is already loaded, init immediately
        if (typeof google !== 'undefined' && google.maps && $('#fxw-map').length) {
            initMap();
        } else if ($('#fxw-map').length) {
            // If the map container exists but Google Maps isn't loaded, it's likely blocked.
            // Wait a moment to see if it loads, then show an error.
            setTimeout(function() {
                if (!window.fxwMapInitialized) {
                    showError('Google Maps failed to load. Please check your internet connection and any ad-blockers, and ensure a valid API key and Map ID are configured.');
                }
            }, 2500);
        }
    }

    function setupCheckoutValidation() {
        // On checkout update, restore coords from hidden fields if present
        $(document).on('updated_checkout', function() {
            var $lat = $('#fxw_lat');
            var $lng = $('#fxw_lng');
            if ($lat.length && $lng.length && $lat.val() && $lng.val()) {
                var lat = parseFloat($lat.val());
                var lng = parseFloat($lng.val());
                if (!isNaN(lat) && !isNaN(lng)) {
                    lockCoords(lat, lng);
                }
            }
        });

        // Debug helper: fetch FXW status (settings, zones, session)
        function fxwDebugStatus() {
            var payload = {
                action: 'fxw_debug_status',
                nonce: fxw_checkout_params.nonce
            };
            $.post(fxw_checkout_params.ajax_url, payload)
                .done(function(response) {
                    if (fxw_checkout_params && fxw_checkout_params.debug) {
                        console.log('fxw_debug_status: response', response);
                    }
                    if (!response || !response.success) {
                        var msg = (response && response.data) ? (typeof response.data === 'string' ? response.data : JSON.stringify(response.data)) : 'Unknown error';
                        showError('FXW Debug error: ' + msg);
                    } else {
                        showError('FXW Debug OK. See console for details.');
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    var serverMsg = (jqXHR.responseJSON && (jqXHR.responseJSON.data || jqXHR.responseJSON.message)) || jqXHR.responseText || errorThrown || textStatus;
                    showError('FXW Debug request failed: ' + serverMsg);
                    if (fxw_checkout_params && fxw_checkout_params.debug) {
                        console.log('fxw_debug_status: fail', { status: jqXHR.status, textStatus: textStatus, errorThrown: errorThrown, responseText: jqXHR.responseText });
                    }
                });
        }

        // If debug mode, add a debug button to trigger status dump
        if (fxw_checkout_params && fxw_checkout_params.debug) {
            var $debugBtn = $('<a/>', { href: '#', id: 'fxw-debug-status', class: 'button', text: 'FXW Debug' });
            $('.fxw-location-search-wrapper').append($debugBtn);
            $(document).on('click', '#fxw-debug-status', function(e) {
                e.preventDefault();
                fxwDebugStatus();
            });
            // Expose to window for manual triggering
            window.fxwDebugStatus = fxwDebugStatus;
        }

        // Trigger a checkout update on page load to ensure shipping options are displayed correctly.
        $(document.body).trigger('update_checkout');
    }

    // Initialize on document ready
    $(document).ready(function() {
        initializeGoogleMaps();
        setupCheckoutValidation();
        handleAddressValidation(); // Initialize real-time address validation
    });
});
