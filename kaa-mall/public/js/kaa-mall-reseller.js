(function($) {
    'use strict';

    $(function() {
        $('.reseller-prices-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var network = form.data('network');
            var data = form.serialize() + '&action=kaa_mall_save_reseller_prices&nonce=' + kaa_mall_reseller_params.nonce + '&network=' + network;

            $.post(kaa_mall_reseller_params.ajax_url, data, function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert('An error occurred: ' + response.data.message);
                }
            });
        });

        $('.network-tabs .tab-link').on('click', function() {
            var network = $(this).data('network');

            $('.network-tabs .tab-link').removeClass('active');
            $(this).addClass('active');

            $('.network-tab-content').removeClass('active');
            $('#reseller-prices-' + network).addClass('active');
        });

        $('#kaa-mall-shop-name-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var data = form.serialize() + '&action=kaa_mall_save_shop_name&nonce=' + kaa_mall_reseller_params.nonce;

            $.post(kaa_mall_reseller_params.ajax_url, data, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert('An error occurred: ' + response.data.message);
                }
            });
        });

        $('#kaa-mall-withdrawal-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var data = form.serialize() + '&action=kaa_mall_request_withdrawal&nonce=' + kaa_mall_reseller_params.nonce;

            $.post(kaa_mall_reseller_params.ajax_url, data, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert('An error occurred: ' + response.data.message);
                }
            });
        });

        $('#kaa-mall-apply-btn').on('click', function() {
            var fee = $(this).data('fee');
            var handler = PaystackPop.setup({
                key: kaa_mall_reseller_params.paystack_public_key,
                email: kaa_mall_reseller_params.user_email,
                amount: fee * 100,
                currency: 'GHS',
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    $.post(kaa_mall_reseller_params.ajax_url, {
                        action: 'kaa_mall_handle_reseller_application',
                        nonce: kaa_mall_reseller_params.nonce
                    }, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.reload();
                        } else {
                            alert('An error occurred: ' + response.data.message);
                        }
                    });
                },
                onClose: function() {
                    alert('Transaction was not completed.');
                },
            });
            handler.openIframe();
        });
    });

})(jQuery);
