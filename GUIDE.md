# KAA Mall Plugin Setup Guide

Congratulations on your new KAA Mall plugin! This guide will walk you through the entire process of setting up, configuring, and using all the powerful features we've built.

## 1. Installation

1.  **Download the Plugin:** You will receive a `.zip` file containing the `kaa-mall` plugin.
2.  **Go to Your WordPress Dashboard:** Log in to your WordPress admin area.
3.  **Navigate to Plugins:** In the left-hand menu, go to `Plugins` > `Add New`.
4.  **Upload Plugin:** Click the `Upload Plugin` button at the top of the page.
5.  **Choose File:** Select the `kaa-mall.zip` file you downloaded and click `Install Now`.
6.  **Activate Plugin:** After the installation is complete, click the `Activate Plugin` button.

**Important:** The KAA Mall plugin requires **WooCommerce** to be installed and active. If you don't have it, please install and activate it before proceeding.

## 2. Initial Setup: Product Creation

The first time you activate the plugin, it will automatically create all the necessary data bundle products in your WooCommerce store with the correct prices for MTN, Airteltigo, and Vodafone.

You can verify this by going to `Products` > `All Products` in your WordPress dashboard. You should see all the data bundles listed.

## 3. Configuration: Paystack API Keys

To accept payments, you need to add your Paystack API keys.

1.  **Navigate to KAA Mall Settings:** In the WordPress admin menu, you will see a new "KAA Mall" item. Click on it.
2.  **Enter Your Keys:** On the settings page, you will see two fields:
    *   **Paystack Public Key:** Enter your Paystack Public Key here.
    *   **Paystack Secret Key:** Enter your Paystack Secret Key here.
3.  **Save Changes:** Click the `Save Changes` button.

Your plugin is now ready to process payments through Paystack.

## 4. Creating User Pages

The plugin uses shortcodes to display the main user-facing pages. You need to create new pages and add these shortcodes to them.

### Authentication Portal (Login/Registration)

1.  Go to `Pages` > `Add New`.
2.  Title the page something like "Login & Register".
3.  In the page content, add the following shortcode: `[kaa_auth_portal]`
4.  Publish the page. This page will now display your modern, tabbed login and registration forms.

### User Dashboard

1.  Go to `Pages` > `Add New`.
2.  Title the page something like "Dashboard".
3.  In the page content, add the following shortcode: `[kaa_mall_dashboard]`
4.  Publish the page. This is where logged-in users will manage their account, buy data, and (if they are a reseller) manage their shop.

## 5. User Roles: Assigning Resellers

To make a user a reseller, you need to assign them the correct role.

1.  Go to `Users` > `All Users`.
2.  Find the user you want to make a reseller and click `Edit`.
3.  Find the `Role` dropdown and change the user's role to `Reseller`.
4.  Click `Update User`.

This user will now see the "My Shop" section in their dashboard.

## 6. Admin Management

### User & Wallet Management

1.  In the WordPress admin menu, go to `KAA Mall` > `User Management`.
2.  On this page, you can:
    *   **Search for Users:** Use the search box to find a specific user by their username or email.
    *   **View Wallet Balances:** See the Main Wallet and Profit Wallet balances for every user.
    *   **Manually Adjust Wallets:** Click the `Adjust Wallet` button next to any user. A popup will appear allowing you to add or subtract funds from their Main or Profit wallet. This is useful for refunds, bonuses, or manual top-ups.

## 7. The User Experience

### Registration

New users can visit your "Login & Register" page and click the "Register" tab. When they register, the system automatically creates a WordPress account and a KAA Mall wallet for them.

### Buying Data

1.  Once logged in, users will be directed to their dashboard.
2.  They can select a network (MTN, Airteltigo, etc.) and a data bundle.
3.  They can choose to pay with their **Wallet Balance** or with **Paystack**.
4.  The purchase is processed, and a WooCommerce order is created for your records.

### Topping Up a Wallet

1.  On the dashboard, users can click the `Top Up Wallet` button.
2.  They will be prompted to enter an amount.
3.  The Paystack payment popup will appear for them to complete the payment.
4.  Once the payment is verified, their Main Wallet balance is automatically updated.

### The Reseller "My Shop"

1.  Users with the `Reseller` role will see a "My Shop" link in their dashboard.
2.  Here, they can:
    *   Set their own custom price for each data bundle (it must be equal to or higher than your base price).
    *   Click `Save Prices` to update their shop.
    *   Copy their unique **Shop Link**.
3.  When a customer (even a guest who is not logged in) uses this link to buy a data bundle, the reseller's custom price is used, and the profit (Reseller Price - Base Price) is automatically added to the reseller's **Profit Wallet**.

---

That's it! Your KAA Mall plugin is fully configured and ready for business. If you have any more questions, feel free to ask.
