(function ($) {
    $(document).ready(function () {

        // Easy access to OpenLayers features
        const Feature = ol.Feature;
        const Geolocation = ol.Geolocation;
        const Map = ol.Map;
        const Point = ol.geom.Point;
        const View = ol.View;
        const CircleStyle = ol.style.Circle;
        const Fill = ol.style.Fill;
        const Stroke = ol.style.Stroke;
        const Style = ol.style.Style;
        const OSM = ol.source.OSM;
        const VectorSource = ol.source.Vector;
        const VectorLayer = ol.layer.Vector;

        /**
         * Take out the map containers and loop on them to create multiple maps
         * */
        var pws_map_osm_containers = $('.pws-map__container')

        if (pws_map_osm_containers.length === 0) {
            return false;
        }

        pws_map_osm_containers.each(function (index, element) {
            let pws_map_osm_container = $(element);

            /**
             * Set map initial style
             * */
            pws_map_osm_container.css({
                'min-width': pws_map_osm_container.data('min-width'),
                'min-height': pws_map_osm_container.data('min-height')
            });

            /**
             * Map marker color
             * */
            let pws_map_osm_marker_color = pws_map_osm_container.data('marker-color');

            /**
             * Map view center location
             * */
            let pws_map_osm_init_lat = pws_map_osm_container.data('center-lat')
            let pws_map_osm_init_long = pws_map_osm_container.data('center-long')
            let pws_map_osm_init_location = [pws_map_osm_init_lat, pws_map_osm_init_long]  //latitude,longitude

            /**
             * Set zoom, Big number => closest to the earth
             * */
            var pws_map_osm_zoom = pws_map_osm_container.data('zoom');

            /**
             * Set custom map marker for osm
             * */
            var pws_map_osm_marker_url = pws_map_osm_container.data('marker-url');

            /**
             * Check if initial location is user last location or it's the default location
             * */
            var pws_map_osm_user_has_location = pws_map_osm_container.data('user-has-location');

            /**
             * Create map instance based on configuration
             * */
            var pws_map_osm_id = pws_map_osm_container.attr('id');
            var pws_map_osm = new ol.Map({
                target: pws_map_osm_id,
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.OSM()
                    })
                ],
                view: new ol.View({
                    center: ol.proj.fromLonLat(pws_map_osm_init_location),
                    zoom: pws_map_osm_zoom,
                })
            });

            /**
             * Set marker and initial location
             * */
            const pws_map_osm_marker_layer = new ol.layer.Vector({
                source: new ol.source.Vector(),
            });
            pws_map_osm.addLayer(pws_map_osm_marker_layer);
            var currentMarker = null;

            /**
             * Easily add marker to the map
             * */
            function pws_map_osm_add_marker(coordinate) {

                const pws_map_osm_marker = new ol.Feature({
                    geometry: new ol.geom.Point(coordinate),
                });

                // Create a new marker feature
                /*currentMarker = new ol.Feature({
                    geometry: new ol.geom.Point(ol.proj.fromLonLat(coordinate))
                });*/

                // Set a custom icon style for the marker
                const pws_map_osm_maker_icon_style = new ol.style.Style({
                    image: new ol.style.Icon({
                        anchor: [0.5, 1],
                        src: pws_map_osm_marker_url,
                    })
                });
                pws_map_osm_marker.setStyle(pws_map_osm_maker_icon_style);
                pws_map_osm_marker_layer.getSource().clear(); // Clear previous markers
                pws_map_osm_marker_layer.getSource().addFeature(pws_map_osm_marker);
            }

            function removeMarker() {
                // Check if the marker layer's source exists
                if (!pws_map_osm_marker_layer.getSource()) {
                    console.error('Marker layer source is not defined.');
                    return;
                }
                pws_map_osm_marker_layer.getSource().clear();

            }

            if (pws_map_osm_user_has_location) {
                $('#pws_map_location').val(JSON.stringify(pws_map_osm_init_location));

                pws_map_osm_add_marker(ol.proj.fromLonLat(pws_map_osm_init_location.slice().reverse()));
                pws_map_osm.setView(
                    new ol.View({
                        center: ol.proj.fromLonLat(pws_map_osm_init_location.slice().reverse()),
                        zoom: 15,
                    })
                );
            }

            /**
             * Enable GPS only for end user.
             * */
            if (!pws_is_admin()) {

                const pws_map_osm_gps_view = new View({
                    center: [0, 0],
                    zoom: 2,
                });

                const pws_map_osm_geolocation = new Geolocation({
                    trackingOptions: {
                        enableHighAccuracy: true,
                        timeout: 20000,
                    },
                    projection: pws_map_osm_gps_view.getProjection(),
                });

                $('#pws-map__osm__track').on('change', function () {
                    pws_map_osm_geolocation.setTracking(this.checked);
                });

                pws_map_osm_geolocation.on('error', function (error) {
                    console.log(error.message);
                });

                pws_map_osm_geolocation.on('change', function () {
                    let pws_map_osm_gps_location = pws_map_osm_geolocation.getPosition();
                    // If there's no location coordinates do nothing
                    if (!pws_map_osm_gps_location) {
                        return;
                    }
                    let pws_map_osm_gps_location_standard = ol.proj.transform(pws_map_osm_gps_location, 'EPSG:3857', 'EPSG:4326');
                    let longitude = pws_map_osm_gps_location_standard[0];
                    let latitude = pws_map_osm_gps_location_standard[1];

                    if ((latitude <= 0 || longitude <= 0) || pws_map_is_utm(latitude, longitude)) {
                        console.log('لوکیشن به درستی یافت نشد.')
                        return;
                    }

                    $('#pws_map_location').val(JSON.stringify(pws_map_osm_gps_location_standard));
                    pws_map_osm_add_marker(pws_map_osm_gps_location);

                });

                const pws_map_osm_accuracy_feature = new Feature();
                pws_map_osm_geolocation.on('change:accuracyGeometry', function () {
                    pws_map_osm_accuracy_feature.setGeometry(pws_map_osm_geolocation.getAccuracyGeometry());
                });

                const pws_map_osm_position_feature = new Feature();
                pws_map_osm_position_feature.setStyle(
                    new Style({
                        image: new CircleStyle({
                            radius: 6,
                            fill: new Fill({
                                color: pws_map_osm_marker_color,
                            }),
                            stroke: new Stroke({
                                color: '#fff',
                                width: 2,
                            }),
                        }),
                    }),
                );
                pws_map_osm_geolocation.on('change:position', function () {
                    const pws_map_osm_live_coordinates = pws_map_osm_geolocation.getPosition();
                    pws_map_osm_position_feature.setGeometry(pws_map_osm_live_coordinates ? new Point(pws_map_osm_live_coordinates) : null);
                    pws_map_osm.setView(
                        new ol.View({
                            center: pws_map_osm_live_coordinates,
                            zoom: 6
                        })
                    );
                });
                new VectorLayer({
                    map: pws_map_osm,
                    source: new VectorSource({
                        features: [pws_map_osm_accuracy_feature, pws_map_osm_position_feature],
                    }),
                });
            }

            /**
             * Update the map current location if address got changed
             * @return void
             * */
            function pws_osm_show_location_on_map(user_input_address, zoom) {

                let coordinate = pws_map_get_province_location(user_input_address);

                if (coordinate == null) {
                    return;
                }

                const fixed_coordinate = ol.proj.transform(coordinate, 'EPSG:4326', 'EPSG:3857');
                pws_map_osm.setView(
                    new ol.View({
                        center: fixed_coordinate,
                        zoom: zoom
                    })
                );
                removeMarker();
                $("#pws_map_location").val('');
            }

            pws_map_osm.on('click', function (event) {
                if (pws_is_admin() && !pws_map_admin_editing_enabled()) {
                    return;
                }

                var pws_map_osm_selected_location = event.coordinate;
                var pws_map_osm_selected_location_standard = ol.proj.transform(pws_map_osm_selected_location, 'EPSG:3857', 'EPSG:4326');

                $('#pws_map_location').val(JSON.stringify(pws_map_osm_selected_location_standard.slice().reverse()));

                pws_map_osm_add_marker(pws_map_osm_selected_location);

                pws_map_osm.setView(
                    new ol.View({
                        center: pws_map_osm_selected_location,
                        zoom: pws_map_osm.getView().getZoom()
                    })
                );
            })

            /**
             * Handle user input address, step by step.
             * */
            $(document).ajaxComplete(function (event, xhr, settings) {

                $('#billing_state').change(function () {
                    let user_input_address = 'استان ' + $('#billing_state option:selected').text();
                    pws_osm_show_location_on_map(user_input_address, 10);
                });

                $('#shipping_state').change(function () {
                    let user_input_address = 'استان ' + $('#shipping_state option:selected').text();
                    pws_osm_show_location_on_map(user_input_address, 10);
                });

            });

        });/*End maps loop*/

        /*Append custom css*/
        var pws_osm_css_customization = `
                .pws-map__container {
                       position: relative;                   
                 }
                /* Hide the default checkbox */
                #pws-map__osm__track {
                    display: none;
                }
        
                /* Style the label to look like a checkbox */
                #pws-map__osm__track + label {
                    display: inline-block;
                    position: relative;
                    padding-left: 30px;
                    cursor: pointer;
                    font-size: 16px;
                    user-select: none;
                }
        
                /* Create the custom checkbox square */
                #pws-map__osm__track + label::before {
                    content: '';
                    position: absolute;
                    left: 0;
                    top: 50%;
                    transform: translateY(0);
                    width: 30px;
                    height: 30px;
                    border: 1px solid #555;
                    background-color: white;
                    border-radius: 10px;
                    transition: background-color 0.3s, border-color 0.3s;
                    z-index:20;
                }
        
                /* Create the GPS icon */
                #pws-map__osm__track + label::after {
                    content: '📌';
                    position: absolute;
                    z-index: 21;
                    left: 0;
                    top: 0;
                    font-size: 20px;
                    color: #555;
                    opacity: 0;
                    transition: opacity 0.3s;
                }
        
                /* Change the checkbox square and show the GPS icon when checked */
                #pws-map__osm__track:checked + label::before {
                    background-color: #32CD32;
                    border-color: #32CD32;
                }
        
                #pws-map__osm__track:checked + label::after {
                    opacity: 1;
                }
                
                .pws-map__container {
                     margin-block-end: 30px;
                }
        `;

        let pws_style_tag = $('<style></style>');
        pws_style_tag.text(pws_osm_css_customization);
        $('head').append(pws_style_tag);

        if ($("#pws_map_location").val() == 'null') {
            $('#pws-map__osm__track').prop('checked', true);
            $('#pws-map__osm__track').trigger('change');
        }

    });/*End document ready*/
}(jQuery));/*End jQuery initalize*/