(function($) {
    'use strict';

    $(document).ready(function() {
        // Example: Handle reseller approval/denial
        $('.approve-reseller').on('click', function() {
            var userId = $(this).data('user-id');
            // AJAX call to approve reseller
        });

        $('.deny-reseller').on('click', function() {
            var userId = $(this).data('user-id');
            // AJAX call to deny reseller
        });
    });

})(jQuery);
