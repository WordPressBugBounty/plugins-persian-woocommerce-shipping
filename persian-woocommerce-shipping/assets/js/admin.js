(function ($) {
    $(document).ready(function () {
        /**
         * Control map settings behaviour, show or hide form inputs on certain selections.
         *  @since 4.0.4
         * */
        if ($('.toplevel_page_pws-tools').length) {
            let map_enable_checkbox = $("input[name=pws_map\\[enable\\]]");
            let map_service_provider = $("select[name=pws_map\\[provider\\]]");
            /**
             * Hide all specific inputs and only enable General options.
             * Static selectors are presented here to be more clarified
             *
             * @return void
             * */
            function pws_map_hide_specific_map_fields() {
                $("#pws_map\\[neshan_api_key\\]").closest('tr').hide();
                $("#pws_map\\[neshan_service_key\\]").closest('tr').hide();
                $("#pws_map\\[neshan_type\\]").closest('tr').hide();
            }

            /**
             * Show specific fields based on provider
             * */
            map_service_provider.on('change', function () {
                let map_selected_provider = $(this).val();

                switch (map_selected_provider) {
                    case 'neshan':
                        $("#pws_map\\[neshan_api_key\\]").closest('tr').show();
                        $("#pws_map\\[neshan_service_key\\]").closest('tr').show();
                        $("#pws_map\\[neshan_type\\]").closest('tr').show();
                        break;
                    case 'osm':
                        // OpenStreetMap has no specific fields yet.
                        pws_map_hide_specific_map_fields();
                        break;
                    default:
                        pws_map_hide_specific_map_fields();
                        break;
                }

            });
            map_service_provider.trigger('change');

            /**
             * Map enabling behaviour
             * */
            map_enable_checkbox.on('change', function () {
                if (!$(this).prop('checked')) {
                    $("#pws_map table tr:not(:nth-child(1), :nth-child(2))").css('display', 'none');
                    pws_map_hide_specific_map_fields();
                } else {
                    $("#pws_map table tr:not(:nth-child(1), :nth-child(2))").css('display', 'table-row');
                    map_service_provider.trigger('change');
                }
            });
            map_enable_checkbox.trigger('change');
        }/*End Code execution in tools section*/

    });/* End document.ready */
})(jQuery); /* End jQuery noConflict */