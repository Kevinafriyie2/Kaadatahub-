(function($) {
    'use strict';

    $(function() {

        // --- Core Functions from Original Script ---

        function update_wallet_balance() {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_get_wallet_balance', nonce: kaa_mall_params.nonce },
                success: function(response) {
                    if (response.success) {
                        $('.balance-amount').text('GH₵ ' + response.data);
                    }
                }
            });
        }

        function load_bundle_prices(network) {
            var form_container = $('#buy-data-' + network);
            if (!form_container.length) {
                console.error('No container found for network:', network);
                return;
            }
            var bundle_select = form_container.find('select[name="bundle"]');
            bundle_select.empty().append('<option>Loading...</option>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_get_bundle_prices', network: network, nonce: kaa_mall_params.nonce },
                success: function(response) {
                    bundle_select.empty();
                    if (response.success && Object.keys(response.data).length > 0) {
                        $.each(response.data, function(bundle_name, price) {
                            bundle_select.append('<option value="' + bundle_name + '">' + bundle_name + ' (GH₵ ' + price.toFixed(2) + ')</option>');
                        });
                    } else {
                        bundle_select.append('<option>No bundles available</option>');
                    }
                }
            });
        }

        function load_recent_orders() {
            var transactions_list = $('.recent-transactions .transactions-list');
            transactions_list.empty().html('<p>Loading...</p>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_get_recent_orders', nonce: kaa_mall_params.nonce },
                success: function(response) {
                    transactions_list.empty();
                    if (response.success && response.data.length > 0) {
                        var table = `
                            <table class="transaction-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Bundle</th>
                                        <th>Phone</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        $.each(response.data, function(index, order) {
                            table += `
                                <tr>
                                    <td>${order.reference}</td>
                                    <td>${new Date(order.date).toLocaleString()}</td>
                                    <td>${order.bundle || 'N/A'}</td>
                                    <td>${order.phone || 'N/A'}</td>
                                    <td>GH₵ ${parseFloat(order.amount).toFixed(2)}</td>
                                    <td><span class="status-${order.status.toLowerCase()}">${order.status}</span></td>
                                </tr>
                            `;
                        });
                        table += '</tbody></table>';
                        transactions_list.html(table);
                    } else {
                        transactions_list.html('<p>No recent transactions found.</p>');
                    }
                }
            });
        }

        function show_notification(message, type) {
            var notification = $('<div class="kaa-mall-notification ' + type + '">' + message + '</div>');
            $('.kaa-mall-portal-body').prepend(notification);
            setTimeout(function() {
                notification.fadeOut(500, function() { $(this).remove(); });
            }, 3000);
        }

        function render_sales_chart() {
            var ctx = document.getElementById('salesChart');
            if (!ctx) return;
            var salesChart = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Sales',
                        data: [12, 19, 3, 5, 2, 3, 9], // Sample data
                        backgroundColor: 'rgba(111, 66, 193, 0.1)',
                        borderColor: '#6f42c1',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        function verify_paystack_topup(reference) {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: { action: 'kaa_mall_verify_paystack_transaction', reference: reference, nonce: kaa_mall_params.nonce },
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_notification('Top-up successful!', 'success');
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        }


        // --- New UI Interactivity & Integrations ---

        // Initial Load
        update_wallet_balance();
        load_recent_orders();
        load_bundle_prices('mtn'); // Load default tab prices
        render_sales_chart();

        // Sidebar toggle
        $('.hamburger-menu').on('click', function() { $('.kaa-mall-sidebar').addClass('open'); });
        $('.close-sidebar-btn').on('click', function() { $('.kaa-mall-sidebar').removeClass('open'); });

        // Hide/Show balance
        $('.hide-balance-btn').on('click', function() {
            var btn = $(this);
            var balance = $('.balance-amount');
            if (btn.text() === 'Hide') {
                balance.data('original-balance', balance.text());
                balance.text('GH₵ ****');
                btn.text('Show');
            } else {
                balance.text(balance.data('original-balance'));
                btn.text('Hide');
            }
        });

        // Top-up Button
        $('.top-up-btn').on('click', function(e) {
            e.preventDefault();
            var amount = prompt("Enter amount to top up:", "10");
            if (amount === null || amount === "" || isNaN(amount) || parseFloat(amount) <= 0) {
                show_notification('Please enter a valid amount.', 'error');
                return;
            }
            amount = parseFloat(amount);

            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: kaa_mall_params.user_email,
                amount: amount * 100,
                currency: kaa_mall_params.currency,
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) { verify_paystack_topup(response.reference); },
                onClose: function() { show_notification('Transaction was not completed.', 'error'); },
            });
            handler.openIframe();
        });

        // Navigation (Sidebar and Bottom Nav)
        $('.sidebar-nav a, .kaa-mall-bottom-nav a, .quick-actions a').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');

            // Handle "Buy Data" quick action default
            if (target === 'buy-data') {
                target = 'buy-data-mtn';
            }

            // De-activate all nav links
            $('.sidebar-nav a, .kaa-mall-bottom-nav a').removeClass('active');
            // Activate the one that was clicked (and its counterpart in the other nav)
            $('[data-target="' + target + '"]').addClass('active');

            // Show/hide content sections
            $('.portal-main > .kaa-mall-card').hide();

            if (target === 'dashboard' || target === 'home') {
                $('.balance-card, .quick-actions, .sales-performance, .recent-transactions').show();
            } else if (target.startsWith('buy-data-')) {
                 var network = target.replace('buy-data-', '');
                 $('#' + target).show();
                 load_bundle_prices(network);
            } else if (target === 'afa-registration') {
                 $('#afa-registration-form').show();
            } else if (target === 'history') {
                $('.recent-transactions').show(); // Simplification: just show the transactions card
                load_recent_orders();
            } else {
                 $('.balance-card, .quick-actions, .sales-performance, .recent-transactions').show(); // fallback to dashboard
            }

            if ($('.kaa-mall-sidebar').hasClass('open')) {
                $('.kaa-mall-sidebar').removeClass('open');
            }
        });

        // Handle ALL form submissions
        $('.bundle-form').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var submit_button = form.find('button[type="submit"]');
            var original_button_text = submit_button.text();
            submit_button.text('Validating...').prop('disabled', true);

            // Pre-payment validation
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=kaa_mall_validate_purchase&nonce=' + kaa_mall_params.nonce,
                success: function(validation_response) {
                    if (validation_response.success) {
                        // Proceed with payment
                        proceed_with_payment(form);
                    } else {
                        show_notification('Error: ' + validation_response.data.message, 'error');
                        submit_button.text(original_button_text).prop('disabled', false);
                    }
                },
                error: function() {
                     show_notification('An unexpected error occurred. Please try again.', 'error');
                     submit_button.text(original_button_text).prop('disabled', false);
                }
            });
        });

        function proceed_with_payment(form) {
            var submit_button = form.find('button[type="submit"]');
            var original_button_text = submit_button.text();
            submit_button.text('Processing...');

            var payment_method = form.find('input[name="payment_method"]:checked').val();
            var bundle_select = form.find('select[name="bundle"] option:selected');
            var bundle_text = bundle_select.text();
            var bundle_price_match = bundle_text.match(/(\d+\.\d+)/);
            var bundle_price = parseFloat(bundle_price_match[0]);

            if (payment_method === 'wallet') {
                $.ajax({
                    url: kaa_mall_params.ajax_url,
                    type: 'POST',
                    data: form.serialize() + '&action=kaa_mall_purchase_bundle&nonce=' + kaa_mall_params.nonce,
                    success: function(response) {
                        if (response.success) {
                            update_wallet_balance(); load_recent_orders();
                            show_notification('Bundle purchase successful!', 'success');
                        } else {
                            show_notification('Error: ' + response.data.message, 'error');
                        }
                    },
                    complete: function() {
                        submit_button.text(original_button_text).prop('disabled', false);
                    }
                });
            } else { // Paystack
                 var handler = PaystackPop.setup({
                    key: kaa_mall_params.paystack_public_key,
                    email: form.find('input[name="email"]').val() || kaa_mall_params.user_email,
                    amount: bundle_price * 100,
                    currency: kaa_mall_params.currency,
                    ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                    callback: function(response) {
                        $.ajax({
                            url: kaa_mall_params.ajax_url,
                            type: 'POST',
                            data: form.serialize() + '&action=kaa_mall_purchase_bundle_paystack&reference=' + response.reference + '&nonce=' + kaa_mall_params.nonce,
                            success: function(response) {
                                if (response.success) {
                                    load_recent_orders();
                                    show_notification('Bundle purchase successful!', 'success');
                                } else {
                                    show_notification('Error: ' + response.data.message, 'error');
                                }
                            },
                            complete: function() {
                                submit_button.text(original_button_text).prop('disabled', false);
                            }
                        });
                    },
                    onClose: function() {
                        show_notification('Transaction was not completed.', 'error');
                        submit_button.text(original_button_text).prop('disabled', false);
                    },
                });
                handler.openIframe();
            }
        }

        $('#kaa-mall-afa-form').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: $(this).serialize() + '&action=kaa_mall_afa_registration&nonce=' + kaa_mall_params.nonce,
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_notification('AFA registration successful!', 'success');
                        $('#kaa-mall-afa-form')[0].reset();
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        });

    });

})(jQuery);
