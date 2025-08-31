# Feature Summary: FoodXpress

This document provides a high-level summary of the main feature epics for the FoodXpress plugin. Refer to `projectbrief.md` for the complete, granular feature list.

---

### 1. Core System & Settings
This epic covers the foundational administrative setup. It includes creating the "Delivery Boy" role, custom order statuses, and a central settings page for the restaurant admin to input their mapping API key, define delivery zones, set fee structures, and specify default food preparation times. It also includes the global "Open/Closed for Deliveries" toggle.

### 2. Checkout Experience
This epic focuses on enhancing the customer's checkout process. It includes multiple address entry methods (GPS, interactive map pin, manual), validation against delivery zones, and the dynamic calculation and display of both the delivery fee and an estimated delivery time (ETA). It also includes a field for customers to add delivery notes.

### 3. Order Management
This epic covers the admin's day-to-day workflow. It includes the custom meta box on the order edit screen for assigning drivers, viewing payment details (with COD highlighting), printing a thermal-style receipt, and linking directly to the customer's location on a map. It also includes functionality for rejecting or re-assigning orders.

### 4. Dashboards & Views
This epic involves creating the primary user interfaces for monitoring. It includes the main, mobile-optimized Deliveries Dashboard for admins, which will have simple end-of-day reporting capabilities. It also includes the separate, secure, and mobile-friendly view for delivery boys to see their assigned orders.

### 5. Customer-Facing Features
This epic focuses on post-order customer engagement. It includes the `[fxw_track_order]` shortcode and page for customers to view their live order status, automated email notifications for status changes, and a "Re-order" button in their account area for quick repeat purchases.
