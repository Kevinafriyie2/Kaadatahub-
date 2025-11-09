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
                        network: network
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
                            amount: amount
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Top-up successful!');
                                location.reload();
                            } else {
                                alert('An error occurred: ' + response.data.message);
                            }
                        }
                    });
                },
                onClose: function(){
                    alert('Transaction was not completed, window closed.');
                },
            });
            handler.openIframe();
        });

        // Handle data bundle form submission
        $('#kaa-mall-bundle-form').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: formData + '&action=kaa_mall_purchase_bundle',
                success: function(response) {
                    if (response.success) {
                        alert('Bundle purchase successful!');
                        location.reload();
                    } else {
                        alert('An error occurred: ' + response.data.message);
                    }
                }
            });
        });

        // Handle AFA registration form submission
        $('#kaa-mall-afa-form').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: formData + '&action=kaa_mall_afa_registration',
                success: function(response) {
                    if (response.success) {
                        alert('AFA registration successful!');
                        location.reload();
                    } else {
                        alert('An error occurred: ' + response.data.message);
                    }
                }
            });
        });

        function load_recent_orders() {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_recent_orders'
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
    });

})( jQuery );
