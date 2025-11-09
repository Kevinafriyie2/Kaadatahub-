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
    });

})(jQuery);
