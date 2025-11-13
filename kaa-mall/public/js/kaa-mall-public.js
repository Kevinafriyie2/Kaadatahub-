(function($) {
    'use strict';

    $(function() {
        // --- Sidebar & Navigation ---
        const sidebar = $('.portal-sidebar');
        const sidebarOverlay = $('.sidebar-overlay');
        const menuIcon = $('.menu-icon');
        const navItems = $('.nav-item');
        const portalViews = $('.portal-view');

        // Function to switch views
        function switch_view(view_id) {
            portalViews.removeClass('active');
            $('#' + view_id).addClass('active');

            navItems.removeClass('active');
            $('.nav-item[data-view="' + view_id + '"]').addClass('active');

            // On mobile, hide the sidebar after switching
            if ($(window).width() < 768) {
                sidebar.removeClass('active');
                sidebarOverlay.removeClass('active');
            }

            // If switching to a network view, load its prices
            if (['mtn', 'airteltigo', 'telecel'].includes(view_id)) {
                load_bundle_prices(view_id);
            } else if (view_id === 'purchase-history') {
                load_recent_orders();
            }
        }

        // Handle sidebar menu toggle
        menuIcon.on('click', function() {
            sidebar.addClass('active');
            sidebarOverlay.addClass('active');
        });

        sidebarOverlay.on('click', function() {
            sidebar.removeClass('active');
            sidebarOverlay.removeClass('active');
        });

        // Handle navigation item clicks
        navItems.on('click', function(e) {
            e.preventDefault();
            const view_id = $(this).data('view');
            if (view_id) {
                switch_view(view_id);
            }
        });


        // --- Modal Handling ---
        const modal = $('#success-modal');
        const closeModalBtn = $('#close-modal-btn');

        function show_success_modal() { modal.fadeIn(); }
        function hide_success_modal() { modal.fadeOut(); }

        closeModalBtn.on('click', function(){
            hide_success_modal();
            switch_view('dashboard'); // Go to dashboard after closing
        });

        modal.on('click', function(e) {
            if ($(e.target).is(modal)) {
                hide_success_modal();
                switch_view('dashboard'); // Go to dashboard
            }
        });

        // --- [Existing JS functions remain here] ---
        // show_notification, update_wallet_balance, load_bundle_prices, etc.
        // They will be called by the new navigation logic.

        function show_notification(message, type = 'info') {
            var notification = $('<div class="kaa-mall-notification ' + type + '">' + message + '</div>');
            $('.kaa-mall-main-content').prepend(notification);
            notification.fadeIn();
            setTimeout(function() {
                notification.fadeOut(500, function() { $(this).remove(); });
            }, 4000);
        }

        if (kaa_mall_params.is_user_logged_in) {
            update_wallet_balance();
            load_afa_status();
            load_recent_orders(3); // Load top 3 for dashboard
        }

        $('.network-card, .view-all-history').on('click', function(e) {
            e.preventDefault();
            switch_view($(this).data('view'));
        });

        // Function to fetch AFA status
        function load_afa_status() {
            const afaContainer = $('.afa-status-content');
            afaContainer.html('<div class="spinner"></div>');
            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_afa_status',
                    nonce: kaa_mall_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const afaContainer = $('.afa-status-content');
                        if (response.data.is_registered) {
                            afaContainer.html('<i class="fas fa-check-circle"></i> Registered').addClass('registered');
                        } else {
                            afaContainer.html('<a href="#" data-view="afa-registration" class="btn-secondary">Register Now</a>').addClass('not-registered');
                            afaContainer.find('a').on('click', function(e){ e.preventDefault(); switch_view('afa-registration'); });
                        }
                    }
                }
            });
        }

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
                        form.trigger('reset');
                        switch_view('dashboard'); // Redirect to dashboard
                        show_notification('🎉 Registration Successful! You now have access to exclusive AFA bundles.', 'success');
                        load_afa_status(); // Refresh the AFA status on the dashboard
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
                             // Format to "1GB - 5.20"
                            bundle_select.append('<option value="' + bundle_name + '">' + bundle_name.split(' ')[1] + ' - ' + price.toFixed(2) + '</option>');
                        });
                    } else {
                        bundle_select.append('<option value="">No packages available yet.</option>');
                    }
                },
                error: function(){
                     bundle_select.empty().append('<option value="">Could not load bundles</option>');
                }
            });
        }

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
                        if(response.data.message.includes('Invalid bundle')){
                            form.trigger('reset');
                        }
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
                                if(ajax_response.data.message.includes('Invalid bundle')){
                                    form.trigger('reset');
                                }
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
        // ... [The rest of the existing JS functions: form submissions, AJAX calls, etc.] ...
        function load_recent_orders(limit = -1) { // -1 for all
            if (!kaa_mall_params.is_user_logged_in) return;

            const container = limit === 3 ? $('.recent-purchases .history-table-container') : $('#purchase-history .table-responsive');
            container.html('<div class="spinner"></div>');

            $.ajax({
                url: kaa_mall_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_recent_orders',
                    nonce: kaa_mall_params.nonce,
                    limit: limit
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let table = '<table class="history-table"><thead><tr><th>ID</th><th>Date</th><th>Bundle</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
                        response.data.forEach(order => {
                            table += `<tr>
                                <td>${order.reference}</td>
                                <td>${order.date}</td>
                                <td>${order.bundle || 'N/A'}</td>
                                <td>GH₵${order.amount}</td>
                                <td>${order.status}</td>
                            </tr>`;
                        });
                        table += '</tbody></table>';
                        container.html(table);
                    } else {
                        container.html('<p>No recent purchases yet. Start shopping!</p>');
                    }
                },
                error: function() {
                    container.html('<p>Could not load purchase history.</p>');
                }
            });
        }

        // Default to dashboard view on load
        switch_view('dashboard');
    });

})(jQuery);
