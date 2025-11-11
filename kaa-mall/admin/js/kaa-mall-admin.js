jQuery(document).ready(function($) {
    var searchTimer;
    $('#kaa-mall-user-search').on('keyup', function() {
        clearTimeout(searchTimer);
        var searchTerm = $(this).val();
        if (searchTerm.length < 2) {
            $('#kaa-mall-user-search-results').empty();
            return;
        }

        searchTimer = setTimeout(function() {
            $.post(kaa_mall_admin_ajax.ajax_url, {
                action: 'kaa_mall_search_users',
                search: searchTerm,
                nonce: kaa_mall_admin_ajax.nonce
            }, function(response) {
                var resultsContainer = $('#kaa-mall-user-search-results');
                resultsContainer.empty();
                if (response.success && response.data.length) {
                    var list = $('<ul style="border: 1px solid #ddd; background: #fff; list-style: none; margin: 0; padding: 0; max-width: 300px;">');
                    $.each(response.data, function(i, user) {
                        list.append($('<li style="padding: 8px 12px; cursor: pointer;">').data('userid', user.id).text(user.text));
                    });
                    resultsContainer.append(list);
                } else {
                    resultsContainer.text('No users found.');
                }
            });
        }, 500);
    });

    $(document).on('click', '#kaa-mall-user-search-results li', function() {
        var userId = $(this).data('userid');
        var userName = $(this).text();
        $('#kaa-mall-user-id').val(userId);
        $('#kaa-mall-user-search').val(userName);
        $('#kaa-mall-user-search-results').empty();
    });
});
