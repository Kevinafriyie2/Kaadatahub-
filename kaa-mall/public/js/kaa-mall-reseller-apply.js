jQuery(document).ready(function($) {
    $('#kaa-mall-apply-now-btn').on('click', function() {
        var button = $(this);
        var fee = button.data('fee');

        var handler = PaystackPop.setup({
            key: kaa_mall_params.paystack_public_key,
            email: kaa_mall_params.user_email,
            amount: fee * 100, // Amount in pesewas
            currency: kaa_mall_params.currency,
            ref: '' + Math.floor((Math.random() * 1000000000) + 1),
            callback: function(response) {
                // Payment successful, submit application
                $.post(kaa_mall_params.ajax_url, {
                    action: 'kaa_mall_submit_reseller_application',
                    nonce: kaa_mall_params.nonce,
                    reference: response.reference
                }, function(response) {
                    if (response.success) {
                        $('#kaa-mall-reseller-apply-form').html('<p>' + response.data.message + '</p>');
                    } else {
                        alert(response.data.message);
                    }
                });
            },
            onClose: function() {
                // User closed the popup
            }
        });
        handler.openIframe();
    });
});
