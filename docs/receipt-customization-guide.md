# FoodXpress Receipt Customization Guide

## Overview
FoodXpress has a comprehensive receipt system with multiple customization options. The receipt system is designed to look like a professional restaurant bill and can be customized through both settings and template modifications.

## 📍 Where to Find Receipt Settings

### 1. WordPress Admin Settings
Navigate to: **WordPress Admin → Settings → FoodXpress**

Current available settings that affect receipts:
- **Restaurant Name**: Auto-pulled from WooCommerce store name
- **Store Address**: Auto-pulled from WooCommerce store settings
- **Store Phone**: Auto-pulled from WooCommerce store settings

### 2. WooCommerce Store Settings
Navigate to: **WooCommerce → Settings → General**

These settings directly affect your receipts:
- **Store Name** (`woocommerce_store_name`)
- **Store Address** (`woocommerce_store_address`) 
- **Store City** (`woocommerce_store_city`)
- **Store Postcode** (`woocommerce_store_postcode`)
- **Store Phone** (`woocommerce_store_phone`)

## 🎨 Receipt Customization Options

### A. Template-Based Customizations (File: `templates/receipt-template.php`)

#### 1. **Restaurant Branding Section**
```php
<div class="restaurant-name"><?php echo esc_html( $store_name ); ?></div>
```
**Customizable Elements:**
- Restaurant name display
- Address formatting
- Phone number display
- Logo addition (requires template modification)

#### 2. **Receipt Header Information**
```php
<div class="bill-title"><?php esc_html_e( 'Delivery Bill', 'foodxpress' ); ?></div>
```
**Customizable Elements:**
- Receipt title ("Delivery Bill" → "Invoice", "Receipt", etc.)
- Order information layout
- Date/time formatting

#### 3. **Styling Customizations**
The template includes extensive CSS that can be modified:

**Font & Typography:**
```css
body {
    font-family: 'Courier New', monospace; /* Change to any font */
    font-size: 14px; /* Adjust base font size */
}
```

**Colors & Layout:**
```css
.restaurant-name {
    font-size: 20px;
    font-weight: bold;
    text-transform: uppercase; /* Can be removed */
}
```

**Receipt Width:**
```css
.bill-container {
    max-width: 400px; /* Adjust receipt width */
}
```

### B. Content Customizations

#### 1. **Customer Information Display**
Currently shows:
- Customer name
- Phone number  
- Delivery address
- Unit/Flat information

#### 2. **Item Details**
- Product names
- Variations and add-ons
- Quantity and unit price
- Line totals

#### 3. **Payment Information**
- Payment method
- Amount to collect (for COD)
- Payment completion status

#### 4. **Footer Customization**
```php
<div class="thank-you"><?php esc_html_e( 'Thank You!', 'foodxpress' ); ?></div>
<div><?php esc_html_e( 'Have a great day!', 'foodxpress' ); ?></div>
```

## 🛠️ How to Customize Receipts

### Method 1: Basic Text Changes
Edit the text strings directly in `templates/receipt-template.php`:

```php
// Change "Delivery Bill" to "Invoice"
<div class="bill-title"><?php esc_html_e( 'Invoice', 'foodxpress' ); ?></div>

// Change "Thank You!" message
<div class="thank-you"><?php esc_html_e( 'Thanks for choosing us!', 'foodxpress' ); ?></div>
```

### Method 2: Add New Settings to Admin Panel
Modify `includes/class-fxw-settings.php` to add receipt-specific settings:

```php
// Add Receipt Settings Section
add_settings_section(
    'fxw_receipt_settings_section',
    __( 'Receipt Settings', 'foodxpress' ),
    null,
    'foodxpress-settings'
);

// Add Receipt Title Setting
add_settings_field(
    'fxw_receipt_title',
    __( 'Receipt Title', 'foodxpress' ),
    array( $this, 'render_text_field' ),
    'foodxpress-settings',
    'fxw_receipt_settings_section',
    array( 'id' => 'fxw_receipt_title' )
);
```

### Method 3: Advanced Styling
Modify the CSS section in `templates/receipt-template.php`:

```css
/* Add restaurant logo */
.bill-header::before {
    content: "";
    display: block;
    width: 100px;
    height: 100px;
    background: url('path-to-logo.png') no-repeat center;
    background-size: contain;
    margin: 0 auto 10px;
}

/* Change color scheme */
.restaurant-name {
    color: #ff6b35; /* Orange theme */
}

.bill-title {
    background: #ff6b35;
    color: white;
    padding: 5px;
    margin: 10px -20px;
}
```

## 📱 Current Receipt Features

### ✅ What's Already Available:
1. **Auto-print functionality** - Receipt automatically opens print dialog
2. **Mobile-responsive design** - Works on all devices
3. **Professional restaurant bill layout**
4. **Complete order information display**
5. **Customer details and delivery address**
6. **Itemized billing with variations**
7. **Payment method indication**
8. **Delivery boy information**
9. **COD collection amount highlighting**
10. **Special instructions display**

### 📋 Receipt Sections:
1. **Header**: Restaurant name, address, phone
2. **Bill Info**: Order number, date, status, delivery person
3. **Customer Details**: Name, phone, delivery address
4. **Items**: Product list with quantities and prices
5. **Totals**: Subtotal, taxes, shipping, final total
6. **Payment**: Payment method and collection instructions
7. **Footer**: Thank you message and print timestamp

## 🔧 Quick Customization Examples

### Example 1: Change Receipt Title
```php
// In templates/receipt-template.php, find:
<div class="bill-title"><?php esc_html_e( 'Delivery Bill', 'foodxpress' ); ?></div>

// Change to:
<div class="bill-title"><?php esc_html_e( 'Restaurant Invoice', 'foodxpress' ); ?></div>
```

### Example 2: Add Restaurant Logo
```php
// Add after the opening <div class="bill-header">:
<?php 
$logo_url = get_option( 'fxw_restaurant_logo' ); // Add this setting first
if ( $logo_url ) : 
?>
    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" style="max-width: 100px; margin-bottom: 10px;">
<?php endif; ?>
```

### Example 3: Customize Thank You Message
```php
// Find this section in the footer:
<div class="thank-you"><?php esc_html_e( 'Thank You!', 'foodxpress' ); ?></div>
<div><?php esc_html_e( 'Have a great day!', 'foodxpress' ); ?></div>

// Change to:
<div class="thank-you"><?php esc_html_e( 'Thanks for your order!', 'foodxpress' ); ?></div>
<div><?php esc_html_e( 'We appreciate your business!', 'foodxpress' ); ?></div>
<div><?php esc_html_e( 'Follow us on social media!', 'foodxpress' ); ?></div>
```

## 🎯 Access Receipt Settings

### Via WordPress Admin:
1. Go to **WordPress Admin Dashboard**
2. Navigate to **Settings → FoodXpress**
3. Update store information in **WooCommerce → Settings → General**

### Via Code:
- Main template: `templates/receipt-template.php`
- Settings file: `includes/class-fxw-settings.php`
- Print handlers: `includes/class-fxw-shortcodes.php` and `includes/class-fxw-order-admin.php`

## 💡 Tips for Customization

1. **Always backup** the original template before making changes
2. **Test receipts** after modifications to ensure proper printing
3. **Use WordPress translation functions** (`__()`, `esc_html_e()`) for text that might need translation
4. **Follow WordPress coding standards** when adding new settings
5. **Consider mobile devices** when changing layout or font sizes

The FoodXpress receipt system is quite comprehensive and allows for extensive customization while maintaining a professional appearance suitable for food delivery businesses.
