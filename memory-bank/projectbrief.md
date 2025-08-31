# Project Brief: FoodXpress for WooCommerce

This document outlines the core requirements, goals, and scope for the FoodXpress for WooCommerce plugin. It serves as the foundational source of truth for the project.

---

## 1. Project Overview

- **Plugin Name:** FoodXpress for WooCommerce
- **Type:** Extension for the WooCommerce plugin on WordPress.
- **Scope:** A single-restaurant delivery management system.
- **Author:** MD MILLAT HOSEN ([https://github.com/codermillat](https://github.com/codermillat))
- **Project URI:** [https://github.com/codermillat/foodxpress-woocommerce](https://github.com/codermillat/foodxpress-woocommerce)
- **License:** Proprietary (private use only, not for public distribution).

## 2. Core Purpose

The primary goal of FoodXpress is to provide a simple, integrated system for a single restaurant to manage its local delivery operations directly within WooCommerce. It bridges the gap between receiving an online order and the final delivery to the customer.

## 3. Key Features

- **Custom "Delivery Boy" Role:** A secure, limited-access role for delivery staff.
- **Delivery Assignment:** An admin interface on the order page to assign a delivery boy.
- **Custom Delivery Statuses:** A full tracking workflow: `Assigned`, `Picked Up`, `On The Way`, `Delivered`.
- **Deliveries Dashboard:** A central admin screen to monitor all deliveries.
- **"Open in Google Maps" Link:** A direct link for delivery staff to open the customer's location in their maps app.
- **Order Rejection/Cancellation:** An admin button to cancel an order and notify the customer.
- **Order Notes for Delivery Boy:** A field at checkout for customers to add special delivery instructions.
- **Multiple Address Entry Options:**
    - **Live Location Pickup:** A button at checkout for customers to auto-fill their address using their device's GPS.
    - **Manual Pinpoint Selection:** An interactive map where customers can drop a pin to select their precise delivery location.
    - **Manual Text Entry:** Traditional address fields as a fallback.
- **Distance-Based Delivery Fees:** Automatically calculates delivery fees based on travel distance.
- **Estimated Delivery Time:** Calculates and displays an ETA to the customer.
- **Customer Tracking Page:** A page for customers to look up their order and see its live delivery status.
- **Email Notifications:** Automated emails to customers as the delivery status changes.
- **Plugin Settings Page:** A page to enter the mapping API key and configure delivery fee/time rules.
- **"Open/Closed" for Deliveries Toggle:** A switch for admins to enable or disable the delivery option.
- **Print Receipt Button:** An admin button on the order page to print a customer-friendly receipt, formatted for thermal printers, which includes payment method details.
- **Payment Method Display:** Clear indication of the payment method (and amount to collect for COD) on the admin dashboard and order screen.
- **Delivery Zone Management:** An interface for admins to define delivery boundaries (by radius or postal codes) to prevent out-of-area orders.
- **Simple Reporting:** A basic end-of-day report showing total deliveries and fees.
- **Dedicated Delivery Boy View:** A simple, mobile-optimized, non-admin page for delivery boys to view their assigned orders.

## 4. Technical Requirements

- **Integration & Compatibility:** Must integrate seamlessly with the latest versions of WordPress, WooCommerce, and popular themes like Astra by following all official best practices.
- **Mobile-Optimized Admin:** All admin interfaces, including the dashboard and order meta box, must be fully responsive and easy to use on mobile devices.
- **Data Storage:** Order-specific data will be stored in `wp_postmeta`. Global settings (API key, fee rules) will be stored in `wp_options`. No custom database tables will be created.
- **Code Quality:** The codebase must be modular, simple, and well-documented.
- **Dependencies:** The plugin requires an external mapping service API key (e.g., Google Maps) to be configured by the admin for distance and time calculations.
