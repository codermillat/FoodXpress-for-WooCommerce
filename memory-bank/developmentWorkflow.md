# Development Workflow & Testing Strategy

This document outlines the official workflow, tools, and testing procedures for the FoodXpress for WooCommerce project.

---

## 1. Workspace & Environment

-   **Primary Directory:** All development work will be performed exclusively within the `/Users/mdmillathosen/Local Sites/foodxpress-for-woocommerce/app/public/wp-content/plugins/FoodXpress for WooCommerce/` directory.
-   **Local URL:** The development and testing site is `http://foodxpress-for-woocommerce.local/`.
-   **WordPress Admin:** Admin credentials have been provided and are noted for testing purposes.

## 2. Development Tools & Technologies

-   **Backend:** PHP 7.4+
-   **Frontend:** HTML5, CSS3 (Mobile-First), JavaScript (Vanilla JS and WordPress-bundled jQuery).
-   **Core APIs:** WordPress Plugin API, WooCommerce API.
-   **External APIs:** A designated Mapping Service API (e.g., Google Maps) for geolocation and distance calculations.
-   **Browser APIs:** Browser Geolocation API for live location pickup.
-   **Cline Tools:** All file modifications and commands will be executed through Cline's standard toolset.

## 3. Testing Strategy

Our testing strategy prioritizes real-world functionality and user experience.

-   **Development Principle:** Code will be written in a modular, function-oriented way to ensure it is inherently testable and maintainable, even without a formal PHPUnit suite.

-   **Primary Method: Manual Functional Testing**
    -   This will be our core method for ensuring quality.
    -   After I complete the development of each feature or a logical group of features, I will notify you.
    -   You will then perform manual testing on your local environment (`http://foodxpress-for-woocommerce.local/`) to verify that the feature works as expected.

-   **Guided Testing Scenarios:**
    -   To make testing efficient and thorough, I will provide clear, step-by-step test cases for each feature. For example:
        -   **Testing Delivery Zones:**
            1.  *Setup:* "Go to the FoodXpress settings page and define a delivery radius of 5km around the restaurant."
            2.  *Test Case (Outside Zone):* "As a logged-out customer, go to the checkout page and enter an address that is 10km away. **Expected Result:** The delivery shipping option should not be available."
            3.  *Test Case (Inside Zone):* "Now, enter an address that is 3km away. **Expected Result:** The delivery option should appear, and a fee should be calculated."
        -   **Testing Address Entry (Interactive Map):**
            1.  *Setup:* "Go to the checkout page and look for the interactive map with a draggable pin."
            2.  *Test Case (Pinpoint Selection):* "Drag the pin to your exact location on the map. **Expected Result:** The address fields are auto-filled with the correct address, and the delivery fee/ETA are updated."
            3.  *Test Case (GPS Denied):* "Deny GPS permission and use only the map. **Expected Result:** You can still select your location and proceed with checkout."

-   **Feedback & Iteration Loop:**
    1.  **Build:** I will develop a feature based on our finalized documentation.
    2.  **Test:** I will ask you to test the feature using the provided scenarios.
    3.  **Feedback:** You will report back on whether the feature works as expected or if there are any issues.
    4.  **Iterate:** If any issues are found, I will debug and fix them, and we will repeat the testing cycle.

This structured workflow ensures that we maintain high quality throughout the development process and that the final product is robust and reliable.
