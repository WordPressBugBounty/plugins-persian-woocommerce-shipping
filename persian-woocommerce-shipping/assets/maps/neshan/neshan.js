(function ($) {
    $(document).ready(function () {
        /**
         * Set the api keys
         * */
        const PWS_MAP_NESHAN_API_KEY = atob(pws_map_params.api_key);
        const PWS_MAP_NESHAN_SERVICE_KEY = atob(pws_map_params.service_key);

        /**
         * Take out the map containers and loop on them to create multiple maps
         * */
        var pws_map_neshan_containers = $('.pws-map__neshan')

        if (pws_map_neshan_containers.length === 0) {
            return false;
        }

        pws_map_neshan_containers.each(function (index, element) {
            let pws_map_neshan_container = $(element);

            /**
             * Set map initial style
             * */
            pws_map_neshan_container.css({
                'min-width': pws_map_neshan_container.data('min-width'),
                'min-height': pws_map_neshan_container.data('min-height')
            });

            /**
             * Map marker color
             * */
            let pws_map_neshan_marker_color = pws_map_neshan_container.data('marker-color');

            /**
             * Map view center location
             * */
            let pws_map_neshan_init_lat = pws_map_neshan_container.data('center-lat')
            let pws_map_neshan_init_long = pws_map_neshan_container.data('center-long')
            let pws_map_neshan_init_location = [pws_map_neshan_init_lat, pws_map_neshan_init_long]  //latitude,longitude

            /**
             * Show famous places on the map
             * */
            var pws_map_neshan_poi = pws_map_neshan_container.data('poi');

            /**
             * Show traffic in the map
             * */
            var pws_map_neshan_traffic = pws_map_neshan_container.data('traffic');

            /**
             * Set zoom, Big number => closest to the earth
             * */
            var pws_map_neshan_zoom = pws_map_neshan_container.data('zoom');

            /**
             * Check if initial location is user last location or it's the default location
             * */
            var pws_map_neshan_user_has_location = pws_map_neshan_container.data('user-has-location');

            /**
             * Map type based on admin options
             * */
            var pws_map_neshan_type = pws_map_neshan_container.data('type');
            let pws_map_neshan_type_object = '';
            switch (pws_map_neshan_type) {
                case 'vector':
                    pws_map_neshan_type_object = nmp_mapboxgl.Map.mapTypes.neshanVector;
                    break;
                case 'night':
                    pws_map_neshan_type_object = nmp_mapboxgl.Map.mapTypes.neshanNight;
                    break;
                case 'raster':
                    pws_map_neshan_type_object = nmp_mapboxgl.Map.mapTypes.neshanRaster;
                    break;
                case 'raster_night':
                    pws_map_neshan_type_object = nmp_mapboxgl.Map.mapTypes.neshanRasterNight;
                    break;
                default:
                    pws_map_neshan_type_object = nmp_mapboxgl.Map.mapTypes.neshanVector;
            }

            /**
             * Create map instance based on configuration
             * */
            var pws_map_neshan = new nmp_mapboxgl.Map({
                mapType: pws_map_neshan_type_object,
                container: pws_map_neshan_container[0],
                zoom: pws_map_neshan_zoom,
                pitch: 0,
                center: pws_map_neshan_init_location.reverse(),
                minZoom: 2,
                maxZoom: 30,
                trackResize: true,
                mapKey: PWS_MAP_NESHAN_API_KEY,
                poi: pws_map_neshan_poi,
                traffic: pws_map_neshan_traffic,
                mapTypeControllerStatus: {
                    show: false,
                    position: 'bottom-right'
                }
            });

            // Disable showing layers in the map
            pws_map_neshan_container.find('.mapboxgl-ctrl-bottom-left').hide();

            /**
             * Initialize the marker on the map
             * */
            let pws_map_neshan_marker_dragging = !pws_map_after_checkout();
            pws_map_neshan_marker_dragging = !pws_is_admin();
            let pws_map_neshan_marker = new nmp_mapboxgl.Marker({
                color: pws_map_neshan_marker_color,
                draggable: pws_map_neshan_marker_dragging,
            });

            if (pws_map_neshan_user_has_location && !pws_map_is_utm(pws_map_neshan_init_lat, pws_map_neshan_init_long)) {
                pws_map_neshan_marker.setLngLat(pws_map_neshan_init_location)
                    .addTo(pws_map_neshan);
                pws_map_neshan.setCenter(pws_map_neshan_init_location);
                pws_map_neshan.setZoom(15);
                $('#pws_map_location').val(JSON.stringify(pws_map_neshan_init_location.reverse()));
            }

            /**
             * Enable GPS only for end user.
             * */
            var pws_map_neshan_geolocate_control = new nmp_mapboxgl.GeolocateControl({
                positionOptions: {
                    enableHighAccuracy: true,
                    timeout: 20000
                },
                trackUserLocation: true,
                showUserHeading: true
            });

            // GPS Shows only when the location input exists, also admin has no gps
            if (!pws_is_admin() && $('#pws_map_location').length > 0) {
                pws_map_neshan.addControl(pws_map_neshan_geolocate_control);
                // Handle geolocation events
                pws_map_neshan_geolocate_control.on('geolocate', function (event) {
                    var {latitude, longitude} = event.coords;
                    if ((latitude <= 0 || longitude <= 0) || pws_map_is_utm(latitude, longitude)) {
                        console.log('لوکیشن به درستی یافت نشد.')
                    }
                    // Center the map on the user's location
                    pws_map_neshan_marker.setLngLat([longitude, latitude]).addTo(pws_map_neshan);

                    let pws_map_neshan_selected_location_array = [latitude, longitude];

                    $('#pws_map_location').val(JSON.stringify(pws_map_neshan_selected_location_array));
                    pws_map_neshan.setCenter([longitude, latitude]);
                    pws_map_neshan.setZoom(pws_map_neshan_zoom);
                });

                pws_map_neshan_geolocate_control.on('error', function (error) {

                    console.error(`Geolocation error: ${error.message}`);
                });

            }

            /**
             * Get current lat,long of selected point on map
             * */
            pws_map_neshan.on('click', function (event) {
                if (pws_is_admin() && !pws_map_admin_editing_enabled()) {
                    return;
                }
                let pws_map_neshan_selected_location = event.lngLat;
                pws_map_neshan_marker.setLngLat(pws_map_neshan_selected_location).addTo(pws_map_neshan);

                let pws_map_neshan_selected_location_array = [pws_map_neshan_selected_location.lat, pws_map_neshan_selected_location.lng];

                $('#pws_map_location').val(JSON.stringify(pws_map_neshan_selected_location_array));

                // Ensure that if the map is zoomed out such that multiple
                // copies of the feature are visible, the popup appears
                // over the copy being pointed to.
                while (Math.abs(event.lngLat.lng - pws_map_neshan_selected_location[0]) > 180) {
                    pws_map_neshan_selected_location[0] += event.lngLat.lng > pws_map_neshan_selected_location[0] ? 360 : -360;
                }
            });

            /**
             * Update the map current location if address got changed
             * @return void
             * */
            function pws_neshan_show_location_on_map(user_input_address, zoom) {
                let pws_map_neshan_province_location = pws_map_get_province_location(user_input_address);
                if (pws_map_neshan_province_location == null) {
                    return;
                }
                pws_map_neshan.setCenter(pws_map_neshan_province_location);
                pws_map_neshan.setZoom(zoom);
                $("#pws_map_location").val('');
                pws_map_neshan_marker.remove();
            }

            /**
             * Handle user input address, step by step.
             * */
            $(document).ajaxComplete(function (event, xhr, settings) {

                $('#billing_state').on('change', function (e) {
                    let user_input_address = 'استان ' + $('#billing_state option:selected').text();

                    pws_neshan_show_location_on_map(user_input_address, 10, true);
                });

                $('#shipping_state').on('change', function (e) {
                    let user_input_address = 'استان ' + $('#shipping_state option:selected').text();
                    pws_neshan_show_location_on_map(user_input_address, 10, true);
                });

            });
        });/*End maps loop*/

    });/*End document ready*/
}(jQuery));/*End jQuery initalize*/