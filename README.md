# Keevault License Manager - Contracts WooCommerce Integration

This is a WordPress plugin designed to integrate Keevault License Manager with WooCommerce. It automatically creates contracts on Keevault when WooCommerce products are purchased, allowing for license management directly from WooCommerce.

## Features

- **API Integration**: Seamlessly integrates Keevault with WooCommerce using Keevault API keys and URL configuration.
- **Product-Specific Settings**: Customize product settings like Keevault Product ID and License Key Quantity for each product and/or variation.
- **Order Processing**: Automatically generates Keevault contracts for products purchased in WooCommerce.
- **My Account Endpoint**: Adds a new 'Contracts' section in the WooCommerce "My Account" page where customers can view their contracts.
- **Database Integration**: Contracts are stored in a custom database table and linked to WooCommerce orders.

## Installation

1. **Upload Plugin**: Upload the plugin folder to the `/wp-content/plugins/` directory.
2. **Activate the Plugin**: Go to the WordPress admin dashboard, navigate to **Plugins** > **Installed Plugins**, and activate the **Keevault License Manager - Contracts WooCommerce Integration** plugin.
3. **Configure API Settings**: After activation, go to **Keevault** in the WordPress admin menu to enter your Keevault API Key and URL.
4. **Configure Product Settings**: Edit your WooCommerce products to define Keevault-specific settings like the Keevault Product ID and License Quantity.

## Configuration

1. **Keevault API Configuration**:
    - **API Key**: Enter your Keevault API key.
    - **API URL**: Enter the Keevault API URL.

2. **Product Settings**:
    - **Keevault Product ID**: Define the Keevault Product ID for each product.
    - **License Keys Quantity**: Set the number of license keys to create per product purchase.

3. **Variation Settings**:
    - Product variations also support Keevault settings. Configure these in the product variations section.

4. **My Account Endpoint**: A new endpoint 'Contracts' will appear in the WooCommerce **My Account** page for customers to view their contract details.

## Usage

1. **Creating Contracts**:
    - When a customer makes a purchase, the plugin will automatically create contracts on Keevault based on the product's configuration.
    - The generated contracts will be associated with the WooCommerce order.

2. **Viewing Contracts**:
    - Customers can view their contracts from the **My Account** page under the **Contracts** tab.
    - The contract list will display relevant details such as Contract ID, Order ID, Name, Contract Key, and Created At.

3. **Customizing Orders**:
    - The plugin supports both simple products and variations, allowing for the specification of different contract parameters for each variation.

## Database Table

The plugin creates a custom database table (`keevault_contracts`) to store the contract information for each WooCommerce order.

**Table Structure**:

- `id`: Contract ID (Primary Key)
- `order_id`: WooCommerce Order ID
- `item_id`: WooCommerce Order Item ID
- `item_number`: Item number (for orders with WooCommerce product quantity higher than 1)
- `user_id`: Customer's WordPress user ID
- `name`: Contract name
- `information`: Contract information
- `contract_key`: Unique contract key
- `license_keys_quantity`: Quantity of license keys the contract owner can generate
- `product_id`: Keevault Product ID associated with the contract
- `can_get_info`: Whether the contract can be queried for information
- `can_generate`: Whether the contract can generate keys
- `can_destroy`: Whether the contract owner destroy license keys
- `can_destroy_all`: Whether the contract owner can destroy all license keys
- `status`: Current status of the contract (active, inactive)
- `created_at`: Date and time of contract creation
- `updated_at`: Date and time of the last contract update

## Hooks and Actions

The plugin integrates with the following WooCommerce actions:

- **WooCommerce Order Processing**: Triggers contract creation when the order status is "processing" or "completed".
- **Admin Menu**: Adds a Keevault settings page in the WordPress admin area.
- **Product and Variation Hooks**: Adds fields for Keevault Product ID and License Quantity in both simple product and variation settings.

## Development

### Activating the Plugin

When the plugin is activated, it will:

- Create a custom database table for storing contract information.
- Flush WordPress rewrite rules to ensure the new contracts endpoint is registered.
