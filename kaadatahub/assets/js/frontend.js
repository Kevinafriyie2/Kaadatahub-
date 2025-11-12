(function($) {
    'use strict';

    $(document).ready(function() {

        // Function to update wallet balance via AJAX
        function updateWalletBalance() {
            $.ajax({
                url: kdh_ajax.ajax_url,
                type: 'GET',
                data: {
                    action: 'kdh_get_wallet_balance',
                    nonce: kdh_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.wallet-balance').text(response.data.balance);
                    }
                }
            });
        }

        // Initial balance update
        updateWalletBalance();

        // Handle data purchase form submission
        $('#kdh-purchase-form').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();
            formData += '&action=kdh_purchase_data';
            formData += '&nonce=' + kdh_ajax.nonce;

            $.ajax({
                url: kdh_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert('Purchase successful!');
                        updateWalletBalance();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        });

        // Handle wallet top-up
        $('#kdh-topup-form').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();
            formData += '&action=kdh_top_up_wallet';
            formData += '&nonce=' + kdh_ajax.nonce;

            $.ajax({
                url: kdh_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        alert('Top-up successful!');
                        updateWalletBalance();
                    } else {
                        alert('Error: ' + response.data);
                    }
                }
            });
        });

    });

})(jQuery);
