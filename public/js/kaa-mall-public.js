(function($) {
    'use strict';

    $(document).ready(function() {

        // --- Hamburger Menu Toggling ---
        $('.open-hamburger').on('click', function() {
            $('#kaa-mall-hamburger-nav').addClass('open');
        });

        $('.close-hamburger').on('click', function() {
            $('#kaa-mall-hamburger-nav').removeClass('open');
        });

        // --- Logout Dropdown ---
        $('.user-profile').on('click', function() {
            $('.profile-dropdown').toggleClass('show');
        });

        // Close dropdown if clicked outside
        $(window).on('click', function(event) {
            if (!$(event.target).closest('.user-profile').length) {
                $('.profile-dropdown').removeClass('show');
            }
        });

        // --- Submenu Toggling ---
        $('.submenu-toggle').on('click', function(e) {
            e.preventDefault();
            $(this).parent().toggleClass('open');
        });

        // --- AJAX Page Loading ---
        function loadContent(page, network) {
            // If no page is specified, do nothing.
             if (!page) {
                return;
            }

            $('.main-dashboard-content').html('<p class="loading-text">Loading...</p>'); // Show a loading message

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_load_page',
                    page: page,
                    network: network,
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.main-dashboard-content').html(response.data);
                    } else {
                        $('.main-dashboard-content').html('<p class="error-text">' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('.main-dashboard-content').html('<p class="error-text">An error occurred while loading the page.</p>');
                }
            });
        }

        // Click handler for sidebar navigation
        $('.sidebar-nav').on('click', 'a', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            var network = $(this).data('network');

            // Update active class
            $('.sidebar-nav li').removeClass('active');
            $(this).parent().addClass('active');

            loadContent(page, network);

            // Auto-close sidebar on mobile
            if ($(window).width() <= 768) {
                $('body').removeClass('sidebar-open');
            }
        });

        // Load the initial "Dashboard" page
        loadContent('dashboard');
        $('.sidebar-nav a[data-page="dashboard"]').parent().addClass('active');

        // --- Network Tab Switching ---
        $('.main-dashboard-content').on('click', '.tab-link', function() {
            var network = $(this).data('network');
            $('.tab-link').removeClass('active');
            $(this).addClass('active');
            $('.data-bundle-form').removeClass('active');
            $('#form-' + network).addClass('active');
        });

        // --- Auth Portal Tab Switching ---
        $('.kaa-auth-portal').on('click', '.tab-link', function() {
            var tab = $(this).data('tab');
            $('.tab-link').removeClass('active');
            $(this).addClass('active');
            $('.auth-tab-content').removeClass('active');
            $('#' + tab).addClass('active');
        });

        // --- Login Form Submission ---
        $('#kaa-login-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button');
            var $notice = $form.find('.kaa-mall-notice');

            $button.prop('disabled', true).text('Logging in...');
            $notice.hide();

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_login',
                    username: $form.find('#username').val(),
                    password: $form.find('#password').val(),
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        $notice.text('Error: ' + response.data).fadeIn();
                    }
                },
                error: function() {
                    $notice.text('An unexpected error occurred. Please try again.').fadeIn();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Log in');
                }
            });
        });

        // --- Register Form Submission ---
        $('#kaa-register-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button');
            var $notice = $form.find('.kaa-mall-notice');

            $button.prop('disabled', true).text('Registering...');
            $notice.hide();

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_register',
                    email: $form.find('#reg_email').val(),
                    password: $form.find('#reg_password').val(),
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $notice.text(response.data).addClass('success').fadeIn();
                        // Switch to the login tab
                        setTimeout(function() {
                            $('.tab-link[data-tab="login"]').click();
                        }, 2000);
                    } else {
                        $notice.text('Error: ' + response.data).fadeIn();
                    }
                },
                error: function() {
                    $notice.text('An unexpected error occurred. Please try again.').fadeIn();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Register');
                }
            });
        });

        // --- Top Up Wallet ---
        $(document).on('click', '.top-up-btn', function(e) {
            e.preventDefault();

            var amount = $('#top-up-amount').val();
            if (!amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid amount from the input field.');
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Initializing...');

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_top_up_wallet',
                    amount: amount,
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var handler = PaystackPop.setup({
                            key: response.data.publicKey,
                            email: response.data.email,
                            amount: response.data.amount,
                            ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                            onClose: function() {
                                $button.prop('disabled', false).html('<i class="fas fa-arrow-up"></i> Top Up with Paystack');
                            },
                            callback: function(transaction) {
                                $.ajax({
                                    url: kaa_mall_ajax.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'kaa_mall_verify_top_up',
                                        reference: transaction.reference,
                                        amount: amount,
                                        nonce: kaa_mall_ajax.nonce
                                    },
                                    success: function(verifyResponse) {
                                        if (verifyResponse.success) {
                                            alert(verifyResponse.data);
                                            loadContent('wallet'); // Reload the wallet page
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
                        $button.prop('disabled', false).html('<i class="fas fa-arrow-up"></i> Top Up with Paystack');
                    }
                },
                error: function() {
                    alert('An unexpected error occurred. Please try again.');
                    $button.prop('disabled', false).html('<i class="fas fa-arrow-up"></i> Top Up with Paystack');
                }
            });
        });

        // --- AFA Bundle Registration ---
        $('.main-dashboard-content').on('click', '.register-btn', function(e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Initializing...');

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_afa_registration',
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var handler = PaystackPop.setup({
                            key: response.data.publicKey,
                            email: response.data.email,
                            amount: response.data.amount,
                            ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                            onClose: function() {
                                $button.prop('disabled', false).html('<i class="fas fa-hand-holding-usd"></i> Register Now - GHS 13');
                            },
                            callback: function(transaction) {
                                $.ajax({
                                    url: kaa_mall_ajax.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'kaa_mall_verify_afa_registration',
                                        reference: transaction.reference,
                                        nonce: kaa_mall_ajax.nonce
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
                    }
                },
                error: function() {
                    alert('An unexpected error occurred. Please try again.');
                    $button.prop('disabled', false).html('<i class="fas fa-hand-holding-usd"></i> Register Now - GHS 13');
                }
            });
        });

        // --- Save Reseller Prices ---
        $('.main-dashboard-content').on('submit', '#reseller-prices-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button');
            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_save_reseller_prices',
                    prices: $form.serialize(),
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Prices');
                }
            });
        });

        // --- Save Shop Name ---
        $('.main-dashboard-content').on('submit', '#reseller-shop-name-form', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button');
            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: kaa_mall_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_save_shop_name',
                    shop_name: $form.find('#shop_name').val(),
                    nonce: kaa_mall_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        loadContent('my-shop');
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred. Please try again.');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Shop Name');
                }
            });
        });

        // --- Direct Purchase ---
        $(document).on('click', '.direct-purchase-btn', function(e) {
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

            if (paymentMethod === 'wallet') {
                var resellerId = (typeof kaa_mall_store_ajax !== 'undefined') ? kaa_mall_store_ajax.reseller_id : 0;
                $.ajax({
                    url: kaa_mall_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kaa_mall_direct_purchase',
                        product_id: productId,
                        payment_method: paymentMethod,
                        reseller_id: resellerId,
                        nonce: kaa_mall_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data);
                            loadContent('buy-data');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('An unexpected error occurred. Please try again.');
                    },
                    complete: function() {
                        $button.prop('disabled', false).html('<i class="fas fa-rocket"></i> Purchase');
                    }
                });
            } else if (paymentMethod === 'paystack') {
                $.ajax({
                    url: kaa_mall_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kaa_mall_direct_purchase',
                        product_id: productId,
                        payment_method: paymentMethod,
                        nonce: kaa_mall_ajax.nonce
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
                                        url: kaa_mall_ajax.ajax_url,
                                        type: 'POST',
                                        data: {
                                            action: 'kaa_mall_verify_paystack_transaction',
                                            reference: transaction.reference,
                                            productId: response.data.productId,
                                            nonce: kaa_mall_ajax.nonce
                                        },
                                        success: function(verifyResponse) {
                                            if (verifyResponse.success) {
                                                alert(verifyResponse.data);
                                                loadContent('buy-data');
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
