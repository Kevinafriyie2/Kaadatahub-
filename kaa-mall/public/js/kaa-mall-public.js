(function($) {
    'use strict';

    $(function() {

        // Sidebar functionality
        $('.open-sidebar-btn').on('click', function() {
            $('.kaa-mall-sidebar').addClass('open');
            $('.sidebar-overlay').addClass('open');
        });

        // Profile popup toggle
        $('#profile-icon').on('click', function(e) {
            e.stopPropagation();
            $('.profile-popup').toggleClass('open');
        });

        $('.close-popup-btn').on('click', function() {
            $('.profile-popup').removeClass('open');
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.profile-popup').length && !$(e.target).is('#profile-icon')) {
                $('.profile-popup').removeClass('open');
            }
        });

        // Dark mode toggle
        $('.dark-mode-toggle').on('click', function() {
            $('body').toggleClass('dark-mode');
            if ($('body').hasClass('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.setItem('darkMode', 'disabled');
            }
        });

        // Check for saved dark mode preference
        if (localStorage.getItem('darkMode') === 'enabled') {
            $('body').addClass('dark-mode');
        }

        // Top-up functionality
        $('.top-up-btn').on('click', function() {
            var amount = prompt("Enter amount to top up:");
            if (amount) {
                var handler = PaystackPop.setup({
                    key: kaa_mall_params.paystack_public_key,
                    email: kaa_mall_params.user_email,
                    amount: amount * 100,
                    currency: kaa_mall_params.currency,
                    ref: ''+Math.floor((Math.random() * 1000000000) + 1),
                    callback: function(response) {
                        var data = {
                            'action': 'kaa_mall_verify_paystack_transaction',
                            'reference': response.reference,
                            'amount': amount,
                            'nonce': kaa_mall_params.nonce
                        };
                        $.post(kaa_mall_params.ajax_url, data, function(res) {
                            if (res.success) {
                                alert('Top-up successful!');
                                updateWalletBalance();
                            } else {
                                alert('An error occurred: ' + res.data.message);
                            }
                        });
                    },
                    onClose: function() {
                        alert('Transaction was not completed, window closed.');
                    }
                });
                handler.openIframe();
            }
        });

        // Hide balance functionality
        $('body').on('click', '.hide-balance-btn', function() {
            var $balance = $(this).closest('.balance-card').find('.balance-amount');
            if ($(this).text() === 'Hide') {
                $balance.text('GH₵ ****');
                $(this).text('Show');
            } else {
                update_wallet_balance();
                $(this).text('Hide');
            }
        });

        $('.close-sidebar-btn, .sidebar-overlay').on('click', function() {
            $('.kaa-mall-sidebar').removeClass('open');
            $('.sidebar-overlay').removeClass('open');
        });

        // Handle sidebar navigation clicks
        $('.sidebar-nav .nav-link').on('click', function(e) {
            e.preventDefault();

            var $link = $(this);
            var main_view = $('#kaa-mall-main-view');

            // Show loading state
            main_view.html('<div class="kaa-mall-loader"></div>');
             $('.sidebar-nav .nav-link').removeClass('active');
            $link.addClass('active');

            var network = $link.data('network');
            var is_afa = $link.data('afa');
            var is_wallet = $link.data('wallet');
            var action = '';
            var data = {
                nonce: kaa_mall_params.nonce
            };

            if (network) {
                action = 'kaa_mall_get_data_bundle_form';
                data.action = action;
                data.network = network;
            } else if (is_afa) {
                action = 'kaa_mall_get_afa_registration_form';
                data.action = action;
            } else if (is_wallet) {
                action = 'kaa_mall_get_wallet_view';
                data.action = action;
            }

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    main_view.html(response);
                     if (network) {
                        load_bundle_prices(network);
                    }
                    if (is_wallet) {
                        update_wallet_balance();
                        load_wallet_transactions_for_view();
                    }
                },
                error: function() {
                    main_view.html('<p>An error occurred. Please try again.</p>');
                }
            });

             // Close sidebar on mobile after click
            if ($(window).width() < 768) {
                $('.kaa-mall-sidebar').removeClass('open');
                $('.sidebar-overlay').removeClass('open');
            }
        });


        // Initial setup on page load
        if (kaa_mall_params.is_user_logged_in) {
            if ($('.purchase-history').length > 0) {
                // We are on the history page
                load_all_orders();
                load_wallet_transactions_for_history();
            } else {
                // We are on the main dashboard
                update_wallet_balance();
                load_recent_transactions();
                setup_sales_chart();
            }
        } else {
            // For guests, check if the default form is present and load its prices
            var default_form = $('#kaa-mall-bundle-purchase-form');
            if (default_form.length) {
                var network = default_form.data('network');
                load_bundle_prices(network);
            }
        }

        $('body').on('click', '.action-icon', function() {
            var action = $(this).data('action');

            if (action === 'buy-data') {
                // Find the MTN link in the sidebar and trigger a click
                $('.sidebar-nav .nav-link[data-network="mtn"]').trigger('click');
            } else if (action === 'topup') {
                // Find and click the topup button inside the balance card
                $('.balance-card .top-up-btn').trigger('click');
            } else if (action === 'history') {
                // Find the history link from the bottom nav and navigate to it
                var history_url = $('.bottom-nav a[href*="history"]').attr('href');
                if (history_url) {
                    window.location.href = history_url;
                }
            } else if (action === 'refer') {
                // Load the refer view
                 $('#kaa-mall-main-view').html('<div class="kaa-mall-loader"></div>');
                $.ajax({
                    url: kaa_mall_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kaa_mall_get_referral_view',
                        nonce: kaa_mall_params.nonce
                    },
                    success: function(response) {
                        $('#kaa-mall-main-view').html(response);
                    }
                });
            }
        });

        $('body').on('click', '.header-icons .fa-user', function(e) {
            if (!$(this).is('#profile-icon')) {
                var profile_url = $('.bottom-nav a[href*="my-account"]').attr('href');
                if (profile_url) {
                    window.location.href = profile_url;
                }
            }
        });

        // Handle notification bell click
        $('body').on('click', '.header-icons .fa-bell', function() {
            show_notification('Notifications are not available yet.', 'info');
        });

        // Handle "Setting" link click
        $('body').on('click', '.profile-menu a[href="#"]', function(e) {
            e.preventDefault();
            if ($(this).find('.fa-cog').length) {
                show_notification('Settings are not yet available.', 'info');
            }
        });

        // Handle network tab switching
        $('.network-tabs .tab-link').on('click', function() {
            var network = $(this).data('network');

            $('.network-tabs .tab-link').removeClass('active');
            $(this).addClass('active');

            $('.network-tab-content').removeClass('active');
            $('#' + network).addClass('active');

            load_bundle_prices(network);
        });

        // Handle wallet top-up button click
        $('.top-up-wallet-btn').on('click', function(e) {
            e.preventDefault();

            var topup_fee = parseFloat(kaa_mall_params.paystack_topup_fee) || 0;
            var prompt_message = "Enter amount to top up:";
            if (topup_fee > 0) {
                prompt_message += "\n(A fee of GH₵" + topup_fee.toFixed(2) + " will be added)";
            }

            var amount = prompt(prompt_message, "10");
            if (amount === null || amount === "" || isNaN(amount) || parseFloat(amount) <= 0) {
                show_notification('Please enter a valid amount.', 'error');
                return;
            }
            amount = parseFloat(amount);
            var total_amount = amount + topup_fee;

            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: kaa_mall_params.user_email,
                amount: total_amount * 100, // in pesewas
                currency: kaa_mall_params.currency,
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    verify_paystack_topup(response.reference, amount);
                },
                onClose: function() {
                    show_notification('Transaction was not completed.', 'error');
                },
            });
            handler.openIframe();
        });

        // Handle data bundle form submission for all networks
        $('body').on('submit', '#kaa-mall-bundle-purchase-form', function(e) {
            e.preventDefault();

            var form = $(this);
            var payment_method = form.find('input[name="payment_method"]:checked').val();
            var bundle_select = form.find('select[name="bundle"] option:selected');
            var bundle_text = bundle_select.text();
            var bundle_price_match = bundle_text.match(/(\d+\.\d+)/);

            if (!bundle_price_match) {
                show_notification('Please select a valid bundle.', 'error');
                return;
            }
            var bundle_price = parseFloat(bundle_price_match[0]);

            if (payment_method === 'wallet') {
                purchase_from_wallet(form);
            } else {
                purchase_with_paystack(form, bundle_price);
            }
        });

        // Update price breakdown when bundle or payment method changes
        $('body').on('change', '#kaa-mall-bundle-purchase-form select[name="bundle"], #kaa-mall-bundle-purchase-form input[name="payment_method"]', function() {
             var form = $(this).closest('form');
            update_bundle_price_display(form);

            if ($(this).attr('name') === 'payment_method') {
                if ($(this).val() === 'paystack' && !kaa_mall_params.is_user_logged_in) {
                    $('#guest-email-field').show();
                } else {
                    $('#guest-email-field').hide();
                }
            }
        });

        function update_bundle_price_display(form, discount) {
            discount = discount || 0;
            var bundle_select = form.find('select[name="bundle"] option:selected');
            if (!bundle_select.length || !bundle_select.val()) return;

            var bundle_text = bundle_select.text();
            var bundle_price_match = bundle_text.match(/(\d+\.\d+)/);
            if (!bundle_price_match) return;

            var bundle_price = parseFloat(bundle_price_match[0]);
            var payment_method = form.find('input[name="payment_method"]:checked').val();
            var fee = 0;
            var fee_text = '';

            if (payment_method === 'wallet') {
                fee = parseFloat(kaa_mall_params.wallet_purchase_fee) || 0;
            } else {
                fee = parseFloat(kaa_mall_params.paystack_purchase_fee) || 0;
            }

            if (fee > 0) {
                fee_text = '<span>Fee: GH₵' + fee.toFixed(2) + '</span>';
            }

            var discount_text = '';
            if (discount > 0) {
                discount_text = '<span>Discount: -GH₵' + discount.toFixed(2) + '</span>';
            }

            var total_price = bundle_price + fee - discount;
            var breakdown_html =
                '<div>' +
                '<span>Bundle Price: GH₵' + bundle_price.toFixed(2) + '</span>' +
                fee_text +
                discount_text +
                '<hr style="border-top: 1px solid #444; margin: 5px 0;">' +
                '<strong>Total: GH₵' + total_price.toFixed(2) + '</strong>' +
                '</div>';

            form.find('.price-breakdown').html(breakdown_html);
        }

        // Handle AFA registration form submission
        // Handle history download button click
        $('body').on('click', '#download-history-btn', function(e) {
            e.preventDefault();
            window.location.href = kaa_mall_params.ajax_url + '?action=kaa_mall_download_history&nonce=' + kaa_mall_params.nonce;
        });

        $('body').on('click', '.apply-coupon-btn', function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            var coupon_code = form.find('input[name="coupon_code"]').val();

            if ( ! coupon_code ) {
                show_notification('Please enter a coupon code.', 'error');
                return;
            }

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_apply_coupon',
                    coupon_code: coupon_code,
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        show_notification(response.data.message, 'success');
                        update_bundle_price_display(form, response.data.discount_amount);
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        });

        // Handle AFA registration form submission
        $('body').on('submit', '#kaa-mall-afa-registration-form', function(e) {
            e.preventDefault();
            var form = $(this);
            var formData = form.serialize();

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: formData + '&action=kaa_mall_afa_registration&nonce=' + kaa_mall_params.nonce,
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_notification('AFA registration successful!', 'success');
                        form.trigger('reset');
                        // Optionally, redirect or clear form
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        });

        function purchase_from_wallet(form) {
            // Simple validation
            if (form.find('select[name="bundle"]').val() === "" || form.find('input[name="phone_number"]').val() === "") {
                show_notification('Please select a bundle and enter a phone number.', 'error');
                return;
            }

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: form.serialize() + '&action=kaa_mall_purchase_bundle&nonce=' + kaa_mall_params.nonce + '&network=' + form.data('network'),
                success: function(response) {
                    if (response.success) {
                        update_wallet_balance();
                        show_notification('Bundle purchase successful!', 'success');
                        form.trigger('reset'); // Clear form
                    } else {
                        show_notification('Error: ' + response.data.message, 'error');
                    }
                }
            });
        }

        function purchase_with_paystack(form, amount) {
            var purchase_fee = parseFloat(kaa_mall_params.paystack_purchase_fee) || 0;
            var total_amount = amount + purchase_fee;

            var user_email = kaa_mall_params.user_email;
            if (!kaa_mall_params.is_user_logged_in) {
                user_email = form.find('input[name="email"]').val();
                 if (!user_email) {
                    show_notification('Please enter your email address.', 'error');
                    return;
                }
            }

            var handler = PaystackPop.setup({
                key: kaa_mall_params.paystack_public_key,
                email: user_email,
                amount: total_amount * 100,
                currency: kaa_mall_params.currency,
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    $.ajax({
                        url: kaa_mall_params.ajax_url,
                        type: 'POST',
                        data: form.serialize() + '&action=kaa_mall_purchase_bundle_paystack&reference=' + response.reference + '&nonce=' + kaa_mall_params.nonce + '&network=' + form.data('network'),
                        success: function(response) {
                            if (response.success) {
                                show_notification('Bundle purchase successful!', 'success');
                                 form.trigger('reset');
                            } else {
                                show_notification('Error: ' + response.data.message, 'error');
                            }
                        }
                    });
                },
                onClose: function() {
                    show_notification('Transaction was not completed.', 'error');
                },
            });
            handler.openIframe();
        }

        function verify_paystack_topup(reference, amount) {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_verify_paystack_transaction',
                    reference: reference,
                    amount: amount,
                    nonce: kaa_mall_params.nonce
                },
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

        function load_bundle_prices(network) {
            var bundle_select = $('#kaa-mall-bundle-purchase-form select[name="bundle"]');
            bundle_select.empty().append('<option>Loading...</option>');

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_bundle_prices',
                    network: network,
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    bundle_select.empty().append('<option value="">-- Select Bundle --</option>');
                    if (response.success && Object.keys(response.data).length > 0) {
                        $.each(response.data, function(bundle_name, price) {
                            bundle_select.append('<option value="' + bundle_name + '">' + bundle_name + ' (GH₵' + price.toFixed(2) + ')</option>');
                        });
                    } else {
                        bundle_select.append('<option>No bundles available</option>');
                    }
                }
            });
        }

        function load_all_orders() {
            var orders_table = $('.purchase-history tbody');
            orders_table.empty().append('<tr><td colspan="6">Loading...</td></tr>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_all_orders',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    orders_table.empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, order) {
                            var order_date = new Date(order.date);
                            var formatted_date = order_date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + order_date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            var order_link = kaa_mall_params.ajax_url.replace('admin-ajax.php', 'post.php?post=' + order.reference + '&action=edit');
                            orders_table.append(
                                '<tr>' +
                                '<td><a href="' + order_link + '">' + order.reference + '</a></td>' +
                                '<td>' + formatted_date + '</td>' +
                                '<td>' + (order.bundle || 'N/A') + '</td>' +
                                '<td>' + (order.phone || 'N/A') + '</td>' +
                                '<td>GH₵' + parseFloat(order.amount).toFixed(2) + '</td>' +
                                '<td>' + order.status + '</td>' +
                                '</tr>'
                            );
                        });
                    } else {
                        orders_table.append('<tr><td colspan="6">No recent orders found.</td></tr>');
                    }
                }
            });
        }

        function load_wallet_transactions_for_history() {
            var transactions_table = $('.wallet-transactions tbody');
            transactions_table.empty().append('<tr><td colspan="5">Loading...</td></tr>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_wallet_transactions',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    transactions_table.empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, trx) {
                            var trx_date = new Date(trx.created_at);
                            var formatted_date = trx_date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + trx_date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            var amount_class = trx.amount > 0 ? 'positive' : 'negative';
                            transactions_table.append(
                                '<tr>' +
                                '<td>' + formatted_date + '</td>' +
                                '<td>' + trx.type + '</td>' +
                                '<td class="' + amount_class + '">GH₵' + parseFloat(trx.amount).toFixed(2) + '</td>' +
                                '<td>' + trx.details + '</td>' +
                                '<td>GH₵' + parseFloat(trx.balance_after).toFixed(2) + '</td>' +
                                '</tr>'
                            );
                        });
                    } else {
                        transactions_table.append('<tr><td colspan="5">No transactions found.</td></tr>');
                    }
                }
            });
        }

        function load_wallet_transactions_for_view() {
            var transactions_list = $('#wallet-transactions-list');
             if (!transactions_list.length) return;
            transactions_list.empty().append('<li>Loading...</li>');

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_wallet_transactions',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    transactions_list.empty();
                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(index, trx) {
                            var trx_date = new Date(trx.created_at);
                             var formatted_date = trx_date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + trx_date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            var amount_class = trx.amount > 0 ? 'positive' : 'negative';
                            var sign = trx.amount > 0 ? '+' : '';
                            var item = `
                                <li>
                                    <div class="transaction-details">
                                        <strong>${trx.details}</strong>
                                        <span>${formatted_date}</span>
                                    </div>
                                    <div class="transaction-amount ${amount_class}">
                                        ${sign}GH₵${parseFloat(Math.abs(trx.amount)).toFixed(2)}
                                    </div>
                                </li>`;
                            transactions_list.append(item);
                        });
                    } else {
                        transactions_list.append('<li>No transactions found.</li>');
                    }
                }
            });
        }

        function update_wallet_balance() {
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_wallet_balance',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.balance-amount').text('GH₵' + response.data);
                    }
                }
            });
        }

        function show_notification(message, type) {
            var notification = $('<div class="kaa-mall-notification ' + type + '">' + message + '</div>');
            $('body').append(notification);
            setTimeout(function() {
                notification.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        function load_recent_transactions() {
            var transactions_list = $('.transactions-list');
            transactions_list.empty().append('<li>Loading...</li>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_wallet_transactions',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    transactions_list.empty();
                    if (response.success && response.data.length > 0) {
                         // Slice to get only top 5
                        $.each(response.data.slice(0, 5), function(index, trx) {
                            var trx_date = new Date(trx.created_at);
                            var formatted_date = trx_date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                             var amount_class = trx.amount > 0 ? 'positive' : 'negative';
                             var sign = trx.amount > 0 ? '+' : '';
                            var item = `
                                <li>
                                    <div class="transaction-icon" style="background-color: ${trx.amount > 0 ? '#e6f7ff' : '#fff1f0'};">
                                        <i class="fas ${trx.amount > 0 ? 'fa-arrow-up' : 'fa-arrow-down'}" style="color: ${trx.amount > 0 ? '#1890ff' : '#cf1322'};"></i>
                                    </div>
                                    <div class="transaction-details">
                                        <strong>${trx.details}</strong>
                                        <span>${formatted_date}</span>
                                    </div>
                                    <div class="transaction-amount ${amount_class}">
                                        ${sign}GH₵${parseFloat(Math.abs(trx.amount)).toFixed(2)}
                                    </div>
                                </li>`;
                            transactions_list.append(item);
                        });
                    } else {
                        transactions_list.append('<li>No recent transactions found.</li>');
                    }
                }
            });
        }

        function setup_sales_chart() {
            var ctx = document.getElementById('sales-chart');
            if(!ctx) return;
            ctx = ctx.getContext('2d');

            // Dummy data for now
            var salesData = {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Sales',
                    data: [12, 19, 3, 5, 2, 3, 9],
                    backgroundColor: 'rgba(138, 43, 226, 0.2)',
                    borderColor: 'rgba(138, 43, 226, 1)',
                    borderWidth: 1,
                    tension: 0.4
                }]
            };

            new Chart(ctx, {
                type: 'line',
                data: salesData,
                options: {
                    responsive: true,
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
    });

})(jQuery);
