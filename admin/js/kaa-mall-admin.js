(function($) {
    'use strict';

    $(document).ready(function() {
        // --- Modal Logic ---
        var modal = $('#adjust-wallet-modal');
        var span = $('.close');

        $('.adjust-wallet-btn').on('click', function() {
            var userId = $(this).data('user-id');
            $('#adjust-user-id').val(userId);
            modal.show();
        });

        span.on('click', function() {
            modal.hide();
        });

        $(window).on('click', function(event) {
            if (event.target == modal[0]) {
                modal.hide();
            }
        });

        // --- Adjust Wallet Form Submission ---
        $('#adjust-wallet-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button');
            $button.prop('disabled', true).text('Adjusting...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kaa_mall_adjust_wallet_balance',
                    user_id: $('#adjust-user-id').val(),
                    wallet_type: $('#wallet-type').val(),
                    amount: $('#adjustment-amount').val(),
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Apply Adjustment');
                    modal.hide();
                }
            });
        });
    });

})(jQuery);
