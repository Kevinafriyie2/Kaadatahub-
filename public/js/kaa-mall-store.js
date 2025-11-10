(function($) {
    'use strict';

    $(document).ready(function() {
        // --- Network Tab Switching ---
        $('.network-tabs').on('click', '.tab-link', function() {
            var network = $(this).data('network');
            $('.tab-link').removeClass('active');
            $(this).addClass('active');
            $('.data-bundle-form').removeClass('active');
            $('#form-' + network).addClass('active');
        });

        // --- Direct Purchase ---
        $('.direct-purchase-btn').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var network = $button.data('network');
            var $form = $('#form-' + network);
            var productId = $form.find('#bundle-select-' + network).val();
            var paymentMethod = $form.find('input[name="payment-method-' + network + '"]:checked').val();

            if (!productId) {
                alert('Please select a data bundle.');
                return;
            }

            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Purchasing...');

            if (paymentMethod === 'paystack') {
                $.ajax({
                    url: kaa_mall_store_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kaa_mall_direct_purchase',
                        product_id: productId,
                        payment_method: paymentMethod,
                        reseller_id: kaa_mall_store_ajax.reseller_id,
                        nonce: kaa_mall_store_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var handler = PaystackPop.setup({
                                key: response.data.publicKey,
                                email: response.data.email,
                                amount: response.data.amount,
                                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                                onClose: function() {
                                    $button.prop('disabled', false).html('<i class="fas fa-rocket"></i> Purchase');
                                },
                                callback: function(transaction) {
                                    $.ajax({
                                        url: kaa_mall_store_ajax.ajax_url,
                                        type: 'POST',
                                        data: {
                                            action: 'kaa_mall_verify_paystack_transaction',
                                            reference: transaction.reference,
                                            productId: response.data.productId,
                                            reseller_id: kaa_mall_store_ajax.reseller_id,
                                            nonce: kaa_mall_store_ajax.nonce
                                        },
                                        success: function(verifyResponse) {
                                            if (verifyResponse.success) {
                                                alert(verifyResponse.data);
                                            } else {
                                                alert('Error: ' + verifyResponse.data);
                                            }
                                        }
                                    });
                                }
                            });
                            handler.openIframe();
                        } else {
                            alert('Error: ' + response.data);
                            $button.prop('disabled', false).html('<i class="fas fa-rocket"></i> Purchase');
                        }
                    },
                    error: function() {
                        alert('An unexpected error occurred. Please try again.');
                        $button.prop('disabled', false).html('<i class="fas fa-rocket"></i> Purchase');
                    }
                });
            }
        });
    });

})(jQuery);
