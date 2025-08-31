# Product Context: FoodXpress for WooCommerce

This file explains the "why" behind the project, the problems it solves, and the intended user experience.

---

## 1. Problem Statement

Restaurants using WooCommerce for online orders lack a simple, integrated tool to manage the crucial "last-mile" delivery process. The workflow from "order received" to "order delivered" is often handled manually through phone calls, text messages, or separate systems. This creates inefficiency, a disconnect between the kitchen and the delivery personnel, and leaves the customer with no visibility into their delivery status.

## 2. Core Solution

FoodXpress provides a lightweight, WordPress-native solution to manage the entire delivery lifecycle within the familiar WooCommerce environment. It empowers restaurant admins to digitally assign orders to delivery staff and allows customers to track their food's journey in real-time, improving operational efficiency and customer satisfaction.

## 3. User Personas & Experience Goals

### a. Restaurant Admin (Shop Manager)

- **Goal:** To have full control over the delivery process from a central, mobile-friendly hub. This includes defining delivery zones, setting fees and times, managing availability, assigning orders, printing receipts, and viewing simple daily reports.
- **Experience:** The admin should feel empowered, not overwhelmed. A dedicated settings page will allow them to configure all rules (API key, fee structure, prep time, delivery zones) in one place. A simple toggle switch will let them open or close the restaurant for deliveries instantly. The Deliveries Dashboard will be their command center, showing payment methods and providing access to daily reports. All screens will be responsive and easy to use on a tablet or phone.

### b. Delivery Boy

- **Goal:** To have a simple, clear, mobile-friendly view of their assigned tasks, know how to handle payment, and easily find the customer's location.
- **Experience:** The delivery boy can access a special, non-admin page on their phone that lists only their assigned orders. This view will be clean and simple, with large, easy-to-tap links to open the customer's address in Google Maps and see delivery notes. For payment, they will rely on the printed receipt provided by the admin, which will clearly state the payment method and the amount to collect for COD orders.

### c. Customer

- **Goal:** A smooth, transparent, and predictable ordering experience, from checkout to their doorstep.
- **Experience:** The process should feel modern, convenient, and flexible. At checkout, they will have multiple options for setting their address: a "Get My Location" button for GPS auto-fill, an interactive map to drop a pin for precise selection, or traditional manual entry. This ensures accuracy and handles cases where GPS is unavailable. Once the address is set and validated against the delivery zone, they will see a clear delivery fee and an ETA. They can add instructions for the driver, track their order status on a dedicated page, and use a "Re-order" button for future purchases.
