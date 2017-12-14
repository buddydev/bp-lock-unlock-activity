jQuery(document).ready(function ($) {

$(document).on('click', 'a.open-close-activity', function() {
    var $button = $(this);
    var activity_id = $button.data('activity-id');
    var _wpnonce = get_var_in_url( '_wpnonce', $button.attr('href') );

    $.post(ajaxurl, {
        action: 'bpal_lock_unlock_activity',
        activity_id: activity_id,
        _wpnonce: _wpnonce
    }, function (response) {
        if (!response.success) {
            //show error
            var $content_el = $('#activity-' + activity_id + ' .activity-content');
            $content_el.find('.bp-lock-activity-error').remove();
            $content_el.append("<div class='error bp-lock-activity-error'><p>" + response.data + "</p></div>")
            return;
        }
        var activity = response.data;

        $button.parents('#activity-'+activity_id).replaceWith( activity );
        // reload the activity.

    },'json');

    return false;
});

    /**
     * Extract a query variable from url
     *
     * @param string item
     * @param string url
     * @returns {Boolean|mpp_L1.get_var_in_query.items|String}
     */
    function get_var_in_url( item, url ) {
        var url_chunks = url.split( '?' );

        return get_var_in_query( item, url_chunks[1] );

    }

    /**
     * Get the  value of a query parameter from the url
     *
     * @param string item url
     * @param string str the name of query string key
     * @returns string|Boolean
     */
    function get_var_in_query( item,  str ){
        var items;

        if( ! str ) {
            return false;
        }

        var data_fields = str.split('&');

        for( var i=0; i< data_fields.length; i++ ) {

            items = data_fields[i].split('=');

            if( items[0] == item ) {
                return items[1];
            }
        }

        return false;
    }
});