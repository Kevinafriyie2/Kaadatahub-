(function($) {
    'use strict';

    $(function() {
        // Load analytics charts
        if ($('#daily-sales-chart').length) {
            load_analytics_data();
        }

        function load_analytics_data() {
            $.ajax({
                url: kaa_mall_reseller_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kaa_mall_get_reseller_analytics',
                    nonce: kaa_mall_reseller_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        render_daily_sales_chart(response.data.daily_sales);
                        render_weekly_sales_chart(response.data.weekly_sales);
                    }
                }
            });
        }

        function render_daily_sales_chart(data) {
            var ctx = document.getElementById('daily-sales-chart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        label: 'Sales (GHS)',
                        data: Object.values(data),
                        backgroundColor: 'rgba(255, 193, 7, 0.5)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        function render_weekly_sales_chart(data) {
            var ctx = document.getElementById('weekly-sales-chart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        label: 'Sales (GHS)',
                        data: Object.values(data),
                        backgroundColor: 'rgba(138, 43, 226, 0.5)',
                        borderColor: 'rgba(138, 43, 226, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

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

        $('#kaa-mall-whatsapp-group-link-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var data = form.serialize() + '&action=kaa_mall_save_whatsapp_group_link&nonce=' + kaa_mall_reseller_params.nonce;

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

        $('body').on('click', '.copy-marketing-message-btn', function() {
            var button = $(this);
            var messageContent = button.siblings('.message-content').text();

            var tempTextarea = $('<textarea>');
            $('body').append(tempTextarea);
            tempTextarea.val(messageContent).select();
            document.execCommand('copy');
            tempTextarea.remove();

            var originalText = button.text();
            button.text('Copied!');
            setTimeout(function() {
                button.text(originalText);
            }, 2000);
        });
    });

})(jQuery);
