(function($) {
    'use strict';

    $(function() {
        $('.auth-tabs .tab-link').on('click', function() {
            var tab = $(this).data('tab');

            $('.auth-tabs .tab-link').removeClass('active');
            $(this).addClass('active');

            $('.auth-tab-content').removeClass('active');
            $('#' + tab).addClass('active');
        });

        // Password visibility toggle
        $('#togglePassword').on('click', function() {
            var input = $('#user_pass');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                $(this).removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                $(this).removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });

        $('#show-register').on('click', function(e) {
            e.preventDefault();
            $('#login').hide();
            $('#register').show();
        });

        $('#show-login').on('click', function(e) {
            e.preventDefault();
            $('#register').hide();
            $('#login').show();
        });

        $('#kaa-mall-register-form').on('submit', function(e) {
            e.preventDefault();

            var form = $(this);
            var data = form.serialize() + '&action=kaa_mall_register_user&nonce=' + kaa_mall_auth_params.nonce;

            $.post(kaa_mall_auth_params.ajax_url, data, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    window.location.href = response.data.redirect_url;
                } else {
                    alert('An error occurred: ' + response.data.message);
                }
            });
        });
    });

})(jQuery);
