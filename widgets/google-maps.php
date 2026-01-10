<?php
namespace GPSC\Widgets;

if (!defined('ABSPATH')) exit;

use Elementor\Controls_Manager;

/**
 * Google Maps Widget
 */
class Google_Maps_Widget extends Base_Widget {

    public function get_name() {
        return 'gps-google-maps';
    }

    public function get_title() {
        return __('Google Maps', 'gps-courses');
    }

    public function get_icon() {
        return 'eicon-google-maps';
    }

    public function get_script_depends() {
        return ['google-maps'];
    }

    protected function register_controls() {
        // Content Section
        $this->start_controls_section(
            'section_content',
            [
                'label' => __('Map Settings', 'gps-courses'),
            ]
        );

        $this->add_control(
            'address',
            [
                'label' => __('Address', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => '6320 Sugarloaf Parkway, Duluth, GA 30097',
                'placeholder' => __('Enter address', 'gps-courses'),
                'description' => __('Enter the full address or use event venue', 'gps-courses'),
            ]
        );

        $this->add_control(
            'use_event_venue',
            [
                'label' => __('Use Current Event Venue', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
                'description' => __('Override address with current event venue', 'gps-courses'),
            ]
        );

        $this->add_control(
            'latitude',
            [
                'label' => __('Latitude', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Optional', 'gps-courses'),
            ]
        );

        $this->add_control(
            'longitude',
            [
                'label' => __('Longitude', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Optional', 'gps-courses'),
            ]
        );

        $this->add_control(
            'zoom',
            [
                'label' => __('Zoom Level', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'size' => 14,
                ],
            ]
        );

        $this->add_responsive_control(
            'height',
            [
                'label' => __('Height', 'gps-courses'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range' => [
                    'px' => [
                        'min' => 200,
                        'max' => 1000,
                    ],
                    'vh' => [
                        'min' => 20,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'size' => 400,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .gps-google-map' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'marker_title',
            [
                'label' => __('Marker Title', 'gps-courses'),
                'type' => Controls_Manager::TEXT,
                'default' => __('GPS Dental Training', 'gps-courses'),
            ]
        );

        $this->add_control(
            'show_marker',
            [
                'label' => __('Show Marker', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_info_window',
            [
                'label' => __('Show Info Window', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
                'condition' => [
                    'show_marker' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => __('Map Style', 'gps-courses'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'map_type',
            [
                'label' => __('Map Type', 'gps-courses'),
                'type' => Controls_Manager::SELECT,
                'default' => 'roadmap',
                'options' => [
                    'roadmap' => __('Roadmap', 'gps-courses'),
                    'satellite' => __('Satellite', 'gps-courses'),
                    'hybrid' => __('Hybrid', 'gps-courses'),
                    'terrain' => __('Terrain', 'gps-courses'),
                ],
            ]
        );

        $this->add_control(
            'map_style',
            [
                'label' => __('Custom Style (JSON)', 'gps-courses'),
                'type' => Controls_Manager::TEXTAREA,
                'placeholder' => __('Paste Google Maps style JSON here', 'gps-courses'),
                'description' => __('Get custom styles from https://snazzymaps.com/', 'gps-courses'),
            ]
        );

        $this->add_control(
            'disable_ui',
            [
                'label' => __('Disable UI Controls', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'draggable',
            [
                'label' => __('Draggable', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'scrollwheel',
            [
                'label' => __('Scroll Wheel Zoom', 'gps-courses'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'gps-courses'),
                'label_off' => __('No', 'gps-courses'),
                'default' => 'no',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get address
        $address = $settings['address'];

        // Override with event venue if enabled
        if ($settings['use_event_venue'] === 'yes') {
            $event_id = get_the_ID();
            if ($event_id && get_post_type($event_id) === 'gps_event') {
                $venue = get_post_meta($event_id, '_gps_venue', true);
                if ($venue) {
                    $address = $venue;
                }
            }
        }

        // Check if Google Maps API key is set
        $api_key = get_option('gps_google_maps_api_key', '');
        if (empty($api_key)) {
            echo '<div class="gps-map-notice">';
            echo '<p>' . __('Please set Google Maps API key in GPS Courses settings.', 'gps-courses') . '</p>';
            echo '</div>';
            return;
        }

        $map_id = 'gps-map-' . $this->get_id();

        // Map settings
        $map_settings = [
            'address' => $address,
            'latitude' => $settings['latitude'],
            'longitude' => $settings['longitude'],
            'zoom' => (int) $settings['zoom']['size'],
            'mapType' => $settings['map_type'],
            'markerTitle' => $settings['marker_title'],
            'showMarker' => $settings['show_marker'] === 'yes',
            'showInfoWindow' => $settings['show_info_window'] === 'yes',
            'disableUI' => $settings['disable_ui'] === 'yes',
            'draggable' => $settings['draggable'] === 'yes',
            'scrollwheel' => $settings['scrollwheel'] === 'yes',
            'styles' => !empty($settings['map_style']) ? json_decode($settings['map_style'], true) : [],
        ];

        ?>
        <div class="gps-google-map-wrapper">
            <div id="<?php echo esc_attr($map_id); ?>"
                 class="gps-google-map"
                 data-settings="<?php echo esc_attr(wp_json_encode($map_settings)); ?>">
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                initGPSMap_<?php echo esc_js($this->get_id()); ?>();
            } else {
                console.error('Google Maps API not loaded');
            }

            function initGPSMap_<?php echo esc_js($this->get_id()); ?>() {
                var settings = <?php echo wp_json_encode($map_settings); ?>;
                var mapElement = document.getElementById('<?php echo esc_js($map_id); ?>');

                // Geocode address if no coordinates provided
                if (!settings.latitude || !settings.longitude) {
                    var geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ 'address': settings.address }, function(results, status) {
                        if (status === 'OK') {
                            createMap(results[0].geometry.location);
                        } else {
                            console.error('Geocode failed: ' + status);
                        }
                    });
                } else {
                    var location = new google.maps.LatLng(
                        parseFloat(settings.latitude),
                        parseFloat(settings.longitude)
                    );
                    createMap(location);
                }

                function createMap(location) {
                    var mapOptions = {
                        center: location,
                        zoom: settings.zoom,
                        mapTypeId: google.maps.MapTypeId[settings.mapType.toUpperCase()],
                        disableDefaultUI: settings.disableUI,
                        draggable: settings.draggable,
                        scrollwheel: settings.scrollwheel,
                        styles: settings.styles
                    };

                    var map = new google.maps.Map(mapElement, mapOptions);

                    // Add marker
                    if (settings.showMarker) {
                        var marker = new google.maps.Marker({
                            position: location,
                            map: map,
                            title: settings.markerTitle
                        });

                        // Add info window
                        if (settings.showInfoWindow) {
                            var infoWindow = new google.maps.InfoWindow({
                                content: '<div style="padding: 10px;"><strong>' + settings.markerTitle + '</strong><br>' + settings.address + '</div>'
                            });
                            infoWindow.open(map, marker);

                            marker.addListener('click', function() {
                                infoWindow.open(map, marker);
                            });
                        }
                    }
                }
            }
        });
        </script>
        <?php
    }
}
