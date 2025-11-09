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
