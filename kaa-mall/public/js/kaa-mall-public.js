(function($) {
    'use strict';

    $(function() {
        // --- Modal Handling ---
        const modal = $('#success-modal');
        const closeModalBtn = $('#close-modal-btn');

        function show_success_modal() {
            modal.fadeIn();
        }

        function hide_success_modal() {
            modal.fadeOut();
        }

        closeModalBtn.on('click', hide_success_modal);
        modal.on('click', function(e) {
            if ($(e.target).is(modal)) {
                hide_success_modal();
            }
        });

        // --- Notification System ---
        function show_notification(message, type = 'info') {
            var notification = $('<div class="kaa-mall-notification ' + type + '">' + message + '</div>');
            $('.kaa-mall-portal-container').prepend(notification);
            notification.fadeIn();
            setTimeout(function() {
                notification.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 4000); // Increased duration for better readability
        }

        // --- Initial Setup ---
        if (kaa_mall_params.is_user_logged_in) {
            update_wallet_balance();
            load_recent_orders();
        }
        load_bundle_prices('mtn');

        // --- Event Handlers ---
        $('.network-tabs .tab-link').on('click', function() {
            var network = $(this).data('network');
            $('.network-tabs .tab-link').removeClass('active');
            $(this).addClass('active');
            $('.network-tab-content').removeClass('active');
            $('#' + network).addClass('active');
            load_bundle_prices(network);
        });

        $(document).on('submit', '.bundle-form', function(e) {
            e.preventDefault();
            var form = $(this);
            var payment_method = form.find('input[name^="payment_method"]:checked').val();
            var bundle_select = form.find('select[name="bundle"] option:selected');
            var bundle_text = bundle_select.text();
            var priceMatch = bundle_text.match(/(\d+\.\d+)/);

            if (!priceMatch) {
                show_notification('Invalid bundle selected. Please choose a valid package.', 'error');
                return;
            }
            var bundle_price = parseFloat(priceMatch[0]);

            if (payment_method === 'wallet') {
                purchase_from_wallet(form);
            } else {
                purchase_with_paystack(form, bundle_price);
            }
        });

        $('#kaa-mall-afa-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var submitButton = form.find('button[type="submit"]');
            submitButton.text('Processing...').prop('disabled', true);

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=kaa_mall_afa_registration&nonce=' + kaa_mall_params.nonce,
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_success_modal();
                        form.trigger('reset');
                    } else {
                        show_notification(response.data.message, 'error');
                    }
                },
                error: function() {
                    show_notification('An unexpected error occurred. Please try again.', 'error');
                },
                complete: function() {
                    submitButton.text('Register Now - GH₵13').prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.top-up-btn', function(e){
            e.preventDefault();
            var amount = prompt("Enter amount to top up:", "10");
            if (amount === null || amount === "" || isNaN(amount) || parseFloat(amount) <= 0) {
                if (amount !== null) { // Don't show error if user just cancelled
                   show_notification('Please enter a valid amount.', 'error');
                }
                return;
            }
            amount = parseFloat(amount);

            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: kaa_mall_params.user_email,
                amount: amount * 100,
                currency: 'GHS',
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    verify_paystack_topup(response.reference);
                },
                onClose: function() {
                    show_notification('Transaction was not completed.', 'info');
                },
            });
            handler.openIframe();
        });

        // --- AJAX Functions ---
        function purchase_from_wallet(form) {
            var submitButton = form.find('button[type="submit"]');
            submitButton.text('Processing...').prop('disabled', true);

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=kaa_mall_purchase_bundle&nonce=' + kaa_mall_params.nonce,
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_success_modal();
                    } else {
                        show_notification(response.data.message, 'error');
                    }
                },
                error: function() {
                    show_notification('An unexpected error occurred. Please try again.', 'error');
                },
                complete: function() {
                    submitButton.text('Continue').prop('disabled', false);
                }
            });
        }

        function purchase_with_paystack(form, amount) {
            var submitButton = form.find('button[type="submit"]');
            var email_input = form.find('input[name="email"]');
            var user_email = kaa_mall_params.is_user_logged_in ? kaa_mall_params.user_email : email_input.val();

            if (!user_email) {
                show_notification('Please provide a valid email address.', 'error');
                return;
            }

            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: user_email,
                amount: amount * 100,
                currency: 'GHS',
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    submitButton.text('Verifying...').prop('disabled', true);
                    $.ajax({
                        url: kaa_mall_params.ajax_url,
                        type: 'POST',
                        data: form.serialize() + '&action=kaa_mall_purchase_bundle_paystack&reference=' + response.reference + '&nonce=' + kaa_mall_params.nonce + '&email=' + user_email,
                        success: function(ajax_response) {
                            if (ajax_response.success) {
                                show_success_modal();
                            } else {
                                show_notification(ajax_response.data.message, 'error');
                            }
                        },
                        error: function() {
                            show_notification('Verification failed. Please contact support.', 'error');
                        },
                        complete: function() {
                            submitButton.text('Continue').prop('disabled', false);
                        }
                    });
                },
                onClose: function() {
                    show_notification('Transaction was not completed.', 'info');
                },
            });
            handler.openIframe();
        }

        function verify_paystack_topup(reference) {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_verify_paystack_transaction',
                    reference: reference,
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_success_modal();
                    } else {
                        show_notification(response.data.message, 'error');
                    }
                },
                error: function() {
                    show_notification('Top-up verification failed. Please contact support.', 'error');
                }
            });
        }

        function load_bundle_prices(network) {
            var bundle_select = $('#' + network).find('select[name="bundle"]');
            bundle_select.empty().append('<option value="">Loading...</option>');

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_get_bundle_prices', network: network, nonce: kaa_mall_params.nonce },
                success: function(response) {
                    bundle_select.empty().append('<option value="">Select package</option>');
                    if (response.success && Object.keys(response.data).length > 0) {
                        $.each(response.data, function(bundle_name, price) {
                            bundle_select.append('<option value="' + bundle_name + '">' + bundle_name.split(' ')[1] + ' - ' + price.toFixed(2) + '</option>');
                        });
                    } else {
                        bundle_select.append('<option value="">No bundles available</option>');
                    }
                },
                error: function() {
                    bundle_select.empty().append('<option value="">Could not load bundles</option>');
                }
            });
        }

        function load_recent_orders() {
            if (!kaa_mall_params.is_user_logged_in) return;
            var history_table_body = $('.purchase-history .history-table tbody');
            history_table_body.html('<tr><td colspan="6">Loading history...</td></tr>');

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_get_recent_orders', nonce: kaa_mall_params.nonce },
                success: function(response) {
                    history_table_body.empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, order) {
                            var row = '<tr>' +
                                '<td>' + order.reference + '</td>' +
                                '<td>' + order.date + '</td>' +
                                '<td>' + order.bundle + '</td>' +
                                '<td>' + order.phone + '</td>' +
                                '<td>GH₵' + order.amount + '</td>' +
                                '<td>' + order.status + '</td>' +
                                '</tr>';
                            history_table_body.append(row);
                        });
                    } else {
                        history_table_body.html('<tr><td colspan="6">No recent orders found.</td></tr>');
                    }
                },
                error: function() {
                    history_table_body.html('<tr><td colspan="6">Could not load history.</td></tr>');
                }
            });
        }

        function update_wallet_balance() {
            if (!kaa_mall_params.is_user_logged_in) return;
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_get_wallet_balance', nonce: kaa_mall_params.nonce },
                success: function(response) {
                    if (response.success) {
                        $('.wallet-balance-display').text(response.data);
                    }
                }
            });
        }
    });
})(jQuery);
