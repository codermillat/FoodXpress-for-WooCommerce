# FoodXpress for WooCommerce - Product Specification

## Product Overview

**FoodXpress for WooCommerce** is a complete delivery management system designed specifically for single-restaurant WooCommerce stores. It provides end-to-end order tracking, delivery management, and customer communication features.

## Core Purpose

Enable restaurant owners to manage food delivery orders efficiently through WooCommerce, with dedicated tools for kitchen staff, delivery personnel, and customers.

## Key Features

### 1. Delivery Management System

**Custom Order Statuses:**
- **In Kitchen**: Order is being prepared
- **Assigned**: Delivery personnel assigned to order
- **Picked Up**: Order collected by delivery person
- **Delivered**: Order completed (standard WooCommerce status)

**Order Flow:**
```
Order Placed → In Kitchen → Assigned → Picked Up → Delivered
```

**Delivery Boy Role:**
- Dedicated user role with restricted access
- Access to delivery dashboard at `/delivery-dashboard/`
- Can only view assigned orders
- Can update order status (Picked Up / Delivered)
- Cannot access other orders or admin functions

**Order Assignment:**
- Admin can assign delivery personnel to orders from admin dashboard
- Delivery personnel receive notifications when assigned
- Orders filtered by assignment status

### 2. Location-Based Delivery

**Google Maps Integration:**
- Interactive map on checkout page for delivery location selection
- Address geocoding (convert addresses to coordinates)
- Distance calculation between restaurant and delivery location
- Delivery radius validation

**Dynamic Pricing:**
- Base delivery fee (configurable)
- Per-kilometer fee (configurable)
- Automatic calculation based on distance
- Delivery radius limit enforcement

**Checkout Integration:**
- Custom WooCommerce shipping method
- Real-time fee calculation
- Address validation
- Distance-based restrictions

### 3. Customer Notifications

**Email System:**
- Custom WooCommerce email templates
- Status-specific emails:
  - Order In Kitchen notification
  - Driver Assigned notification
  - Order Picked Up notification
- Fully customizable via WooCommerce Settings → Emails
- Admin can edit subject, heading, and content

### 4. Order Management

**Admin Dashboard:**
- Centralized view of all delivery orders
- Filter by order status
- Assign delivery personnel
- Update order statuses
- View order details

**Receipt Printing:**
- Professional thermal receipt templates
- Custom branding (logo, restaurant name, tagline)
- Order details and customer information
- Print-friendly format

**Reporting:**
- Order statistics
- Delivery performance metrics
- Status distribution

### 5. Customer Features

**Order Tracking Shortcode:**
- `[fxw_track_order]` - Customers can track orders by order number
- Display order status and details
- Real-time status updates

**Reorder Shortcode:**
- `[fxw_reorder]` - Quick reorder previous orders
- Display order history for logged-in customers
- One-click reorder functionality

### 6. Settings & Configuration

**Plugin Settings Page:**
- Accessible via WooCommerce → Settings → FoodXpress
- Restaurant address configuration
- Google Maps API key (optional)
- Delivery radius setting
- Base delivery fee
- Per-kilometer fee
- Custom branding options

## Technical Requirements

### Dependencies
- **WordPress**: 6.0 or higher
- **WooCommerce**: 7.0 or higher
- **PHP**: 7.4 or higher
- **Google Maps API**: Optional (for location features)

### WordPress Integration
- Custom shipping method
- Custom order statuses
- Custom user role (Delivery Boy)
- Custom email classes
- AJAX endpoints with nonce verification
- Rewrite rules for delivery dashboard
- HPOS (High-Performance Order Storage) compatible

### Security Features
- ABSPATH checks in all PHP files
- Nonce verification for all AJAX requests
- Capability checks for user actions
- Input sanitization
- Output escaping

## User Roles & Permissions

### Administrator
- Full access to all features
- Can assign delivery personnel
- Can configure all settings
- Can view all orders

### Delivery Boy
- Access to delivery dashboard only
- Can view assigned orders only
- Can update order status (Picked Up / Delivered)
- Cannot access admin functions
- Cannot view other orders

### Customer
- Can place orders with delivery option
- Can track orders via shortcode
- Can reorder via shortcode
- Receives email notifications

## Workflow Examples

### Order Processing Workflow
1. Customer places order with FoodXpress delivery method
2. Order status automatically set to "In Kitchen"
3. Customer receives "Order In Kitchen" email
4. Admin assigns delivery personnel
5. Order status changes to "Assigned"
6. Customer receives "Driver Assigned" email
7. Delivery person picks up order
8. Order status changes to "Picked Up"
9. Customer receives "Order Picked Up" email
10. Delivery person marks order as "Delivered"
11. Order completed

### Delivery Dashboard Workflow
1. Delivery person logs in
2. Navigates to `/delivery-dashboard/`
3. Views list of assigned orders
4. Selects an order
5. Updates status to "Picked Up" when collected
6. Updates status to "Delivered" when completed
7. Can print receipt for order

## Configuration Options

### Required Settings
- Restaurant address (for distance calculation)
- Delivery radius (maximum delivery distance)
- Base delivery fee
- Per-kilometer fee

### Optional Settings
- Google Maps API key (enables map features)
- Custom branding (logo, name, tagline)
- Email customization

## Testing Requirements

### Functional Testing Areas
1. Plugin activation and WooCommerce dependency
2. Custom order statuses registration
3. Delivery boy role creation
4. Order status progression workflow
5. Order assignment functionality
6. Delivery dashboard access control
7. Shipping method availability
8. Google Maps integration (if API key provided)
9. Delivery radius validation
10. Shortcode functionality
11. Settings page functionality
12. Email notification system
13. Security (nonces, capabilities, ABSPATH checks)

### Test Scenarios
- Plugin activates successfully with WooCommerce active
- Plugin shows notice when WooCommerce inactive
- Order statuses appear in WooCommerce
- Delivery boy role has correct capabilities
- Orders can be assigned to delivery personnel
- Delivery dashboard only shows assigned orders
- Shipping method appears in checkout
- Distance calculation works correctly
- Delivery fee calculates based on distance
- Orders outside radius are rejected
- Shortcodes display correctly
- Settings save and apply correctly
- Emails send on status changes

## Success Criteria

### Core Functionality
- ✅ Plugin activates without errors
- ✅ All custom order statuses registered
- ✅ Delivery boy role created with correct permissions
- ✅ Orders can progress through status workflow
- ✅ Delivery personnel can access dashboard
- ✅ Shipping method appears in checkout
- ✅ Distance-based fees calculate correctly
- ✅ Email notifications send on status changes

### User Experience
- ✅ Intuitive admin interface
- ✅ Clear delivery dashboard for delivery personnel
- ✅ Easy order tracking for customers
- ✅ Professional email notifications
- ✅ Smooth checkout experience

### Technical Quality
- ✅ No PHP errors or warnings
- ✅ Security best practices followed
- ✅ WordPress coding standards compliant
- ✅ HPOS compatible
- ✅ Proper error handling
- ✅ Clean code structure

## Notes on Google Maps API

**Google Maps API is optional** for core functionality:
- Plugin works without API key for basic features
- API key required only for:
  - Interactive map on checkout
  - Address geocoding
  - Distance calculation
  - Delivery radius validation

**Without API key:**
- Manual address entry still works
- Fixed delivery fee can be used
- All other features function normally

**With API key:**
- Enhanced checkout experience
- Automatic distance calculation
- Dynamic pricing
- Delivery radius enforcement

