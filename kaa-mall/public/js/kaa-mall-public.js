(function( $ ) {
    'use strict';

    $(function() {
        // Load recent orders on page load
        load_recent_orders();

        // Handle network selection change
        $('select[name="network"]').on('change', function() {
            var network = $(this).val();
            var bundle_select = $('select[name="bundle"]');
            bundle_select.empty();

            if (network) {
                $.ajax({
                    url: kaa_mall_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kaa_mall_get_bundle_prices',
                        network: network,
                        nonce: kaa_mall_params.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $.each(response.data, function(bundle_name, price) {
                                bundle_select.append('<option value="' + bundle_name + '">' + bundle_name + ' (₵' + price + ')</option>');
                            });
                        }
                    }
                });
            }
        });

        // Handle wallet top-up form submission
        $('#kaa-mall-topup-form').on('submit', function(e) {
            e.preventDefault();

            var amount = $(this).find('input[name="topup_amount"]').val();
            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: kaa_mall_params.user_email,
                amount: amount * 100, // in pesewas
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response){
                    // Verify the transaction
                    $.ajax({
                        url: kaa_mall_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'kaa_mall_verify_paystack_transaction',
                            reference: response.reference,
                            amount: amount,
                            nonce: kaa_mall_params.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                update_wallet_balance();
                                load_recent_orders();
                                show_notification('Top-up successful!', 'success');
                            } else {
                                show_notification('An error occurred: ' + response.data.message, 'error');
                            }
                        }
                    });
                },
                onClose: function(){
                    show_notification('Transaction was not completed, window closed.', 'error');
                },
            });
            handler.openIframe();
        });

        // Handle data bundle form submission
        $('#kaa-mall-bundle-form').on('submit', function(e) {
            e.preventDefault();

            var payment_method = $(this).find('input[name="payment_method"]:checked').val();
            var formData = $(this).serialize();

            if (payment_method === 'wallet') {
                $.ajax({
                    url: kaa_mall_params.ajax_url,
                    type: 'POST',
                    data: formData + '&action=kaa_mall_purchase_bundle&nonce=' + kaa_mall_params.nonce,
                    success: function(response) {
                        if (response.success) {
                            update_wallet_balance();
                            load_recent_orders();
                            show_notification('Bundle purchase successful!', 'success');
                        } else {
                            show_notification('An error occurred: ' + response.data.message, 'error');
                        }
                    }
                });
            } else {
                var bundle_price = $('select[name="bundle"] option:selected').text().split('(₵')[1].split(')')[0];
                var handler = PaystackPop.setup({
                    key: kaa_mall_params.paystack_public_key,
                    email: kaa_mall_params.user_email,
                    amount: bundle_price * 100, // in pesewas
                    ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                    callback: function(response){
                        // Verify the transaction
                        $.ajax({
                            url: kaa_mall_params.ajax_url,
                            type: 'POST',
                            data: formData + '&action=kaa_mall_purchase_bundle_paystack&reference=' + response.reference + '&nonce=' + kaa_mall_params.nonce,
                            success: function(response) {
                                if (response.success) {
                                    load_recent_orders();
                                    show_notification('Bundle purchase successful!', 'success');
                                } else {
                                    show_notification('An error occurred: ' + response.data.message, 'error');
                                }
                            }
                        });
                    },
                    onClose: function(){
                        show_notification('Transaction was not completed, window closed.', 'error');
                    },
                });
                handler.openIframe();
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
                        show_notification('An error occurred: ' + response.data.message, 'error');
                    }
                }
            });
        });

        function load_recent_orders() {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_recent_orders',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var orders_table = $('.recent-orders tbody');
                        orders_table.empty();
                        $.each(response.data, function(index, order) {
                            orders_table.append(
                                '<tr>' +
                                '<td>' + order.reference + '</td>' +
                                '<td>' + order.date + '</td>' +
                                '<td>' + order.network + '</td>' +
                                '<td>' + order.bundle + '</td>' +
                                '<td>' + order.phone + '</td>' +
                                '<td>' + order.amount + '</td>' +
                                '<td>' + order.status + '</td>' +
                                '</tr>'
                            );
                        });
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
                        $('.wallet-balance p').text('₵' + response.data);
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

})( jQuery );
