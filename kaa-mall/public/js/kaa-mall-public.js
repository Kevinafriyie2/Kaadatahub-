(function($) {
    'use strict';

    $(function() {
        // Handle hamburger menu toggle
        $('.hamburger-menu').on('click', function() {
            $('.header-nav').toggleClass('active');
        });

        // Initial setup on page load
        update_wallet_balance();
        load_recent_orders();
        load_bundle_prices('mtn'); // Load default tab prices

        // Handle network tab switching
        $('.network-tabs .tab-link').on('click', function() {
            var network = $(this).data('network');

            $('.network-tabs .tab-link').removeClass('active');
            $(this).addClass('active');

            $('.network-tab-content').removeClass('active');
            $('#' + network).addClass('active');

            load_bundle_prices(network);
        });

        // Handle wallet top-up button click
        $('.top-up-wallet-btn').on('click', function(e) {
            e.preventDefault();

            var amount = prompt("Enter amount to top up:", "10");
            if (amount === null || amount === "" || isNaN(amount) || parseFloat(amount) <= 0) {
                show_notification('Please enter a valid amount.', 'error');
                return;
            }
            amount = parseFloat(amount);

            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: kaa_mall_params.user_email,
                amount: amount * 100, // in pesewas
                currency: kaa_mall_params.currency,
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    verify_paystack_topup(response.reference, amount);
                },
                onClose: function() {
                    show_notification('Transaction was not completed.', 'error');
                },
            });
            handler.openIframe();
        });

        // Handle data bundle form submission for all networks
        $('.bundle-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var payment_method = form.find('input[name="payment_method"]:checked').val();
            var network = form.data('network');
            var bundle_select = form.find('select[name="bundle"] option:selected');
            var bundle_text = bundle_select.text();
            var bundle_price = parseFloat(bundle_text.match(/(\d+\.\d+)/)[0]);

            if (payment_method === 'wallet') {
                purchase_from_wallet(form);
            } else {
                purchase_with_paystack(form, bundle_price);
            }
        });

        // Handle AFA registration form submission
        $('#kaa-mall-afa-form').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: formData + '&action=kaa_mall_afa_registration&nonce=' + kaa_mall_params.nonce,
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        load_recent_orders();
                        show_notification('AFA registration successful!', 'success');
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        });

        function purchase_from_wallet(form) {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=kaa_mall_purchase_bundle&nonce=' + kaa_mall_params.nonce + '&network=' + form.data('network'),
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        load_recent_orders();
                        show_notification('Bundle purchase successful!', 'success');
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        }

        function purchase_with_paystack(form, amount) {
            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: kaa_mall_params.user_email,
                amount: amount * 100,
                currency: kaa_mall_params.currency,
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    $.ajax({
                        url: kaa_mall_params.ajax_url,
                        type: 'POST',
                        data: form.serialize() + '&action=kaa_mall_purchase_bundle_paystack&reference=' + response.reference + '&nonce=' + kaa_mall_params.nonce + '&network=' + form.data('network'),
                        success: function(response) {
                            if (response.success) {
                                load_recent_orders();
                                show_notification('Bundle purchase successful!', 'success');
                            } else {
                                show_notification('Error: ' + response.data.message, 'error');
                            }
                        }
                    });
                },
                onClose: function() {
                    show_notification('Transaction was not completed.', 'error');
                },
            });
            handler.openIframe();
        }

        function verify_paystack_topup(reference, amount) {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_verify_paystack_transaction',
                    reference: reference,
                    amount: amount,
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        load_recent_orders();
                        show_notification('Top-up successful!', 'success');
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        }

        function load_bundle_prices(network) {
            var bundle_select = $('#' + network).find('select[name="bundle"]');
            bundle_select.empty().append('<option>Loading...</option>');

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_bundle_prices',
                    network: network,
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    bundle_select.empty();
                    if (response.success && Object.keys(response.data).length > 0) {
                        $.each(response.data, function(bundle_name, price) {
                            bundle_select.append('<option value="' + bundle_name + '">' + bundle_name + ' (GH₵' + price.toFixed(2) + ')</option>');
                        });
                    } else {
                        bundle_select.append('<option>No bundles available</option>');
                    }
                }
            });
        }

        function load_recent_orders() {
            var orders_table = $('.purchase-history tbody');
            orders_table.empty().append('<tr><td colspan="4">Loading...</td></tr>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_recent_orders',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    orders_table.empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, order) {
                            var order_date = new Date(order.date);
                            var formatted_date = order_date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + order_date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            var order_link = kaa_mall_params.ajax_url.replace('admin-ajax.php', 'post.php?post=' + order.reference + '&action=edit');
                            orders_table.append(
                                '<tr>' +
                                '<td><a href="' + order_link + '">' + order.reference + '</a></td>' +
                                '<td>' + formatted_date + '</td>' +
                                '<td>' + (order.bundle || 'N/A') + '</td>' +
                                '<td>' + (order.phone || 'N/A') + '</td>' +
                                '<td>GH₵' + parseFloat(order.amount).toFixed(2) + '</td>' +
                                '<td>' + order.status + '</td>' +
                                '</tr>'
                            );
                        });
                    } else {
                        orders_table.append('<tr><td colspan="6">No recent orders found.</td></tr>');
                    }
                }
            });
        }

        function update_wallet_balance() {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_wallet_balance',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.balance-amount').text('GH₵' + response.data);
                    }
                }
            });
        }

        function show_notification(message, type) {
            var notification = $('<div class="kaa-mall-notification ' + type + '">' + message + '</div>');
            $('.kaa-mall-portal').prepend(notification);
            setTimeout(function() {
                notification.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    });

})(jQuery);
