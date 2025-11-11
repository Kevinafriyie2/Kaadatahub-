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
                    $('input[name="shop_name"]').val(response.data.shop_name);
                    $('input[readonly]').val(response.data.referral_link);
                } else {
                    alert('An error occurred: ' + response.data.message);
                }
            });
        });

        $('#kaa-mall-whatsapp-number-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var data = form.serialize() + '&action=kaa_mall_save_whatsapp_number&nonce=' + kaa_mall_reseller_params.nonce;

            $.post(kaa_mall_reseller_params.ajax_url, data, function(response) {
                if (response.success) {
                    alert(response.data.message);
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

        $('#kaa-mall-copy-btn').on('click', function() {
            var copyText = document.getElementById("kaa-mall-referral-link");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices
            document.execCommand("copy");

            var button = $(this);
            var originalText = button.text();
            button.text('Copied!');
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });
    });

})(jQuery);
