<?php
    /*
    Plugin Name: Sync with Twitch
    Description: Plugin to pull data from Twitch
    Version:     1.0.0
    Author:      Beee
    Author URI:  https://berryplasman.com
    */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    if ( ! class_exists( 'PiTwitch' ) ) :

        class PiTwitch {
            public function __construct() {

                // register_deactivation_hook( __FILE__,   [ $this, 'plugin_deactivation' ] );
                
                add_action( 'admin_init',       [ $this, 'tw_form_handling' ] );
                add_action( 'admin_init',       [ $this, 'tw_enqueue_admin_css' ] );
                add_action( 'sync_items',       [ $this, 'tw_sync_items' ], 10, 4 );
                add_action( 'admin_menu',       [ $this, 'tw_add_admin_pages' ] );

                include_once 'constants.php';
                include_once 'actions.php';
                include_once 'functions.php';
                include_once 'map-data.php';
                // delete_user_meta( 1, 'browser_refresh' );

                $this->api = tw_get_api_info();
                
                // add_action( 'admin_init',       [ $this, 'test_sync' ] );
            }


            public function test_sync() {
                $type = TwConstants::POST_TYPE_GAME;
                $this->tw_sync_items( $type );
            }


            public function plugin_deactivation() {
                $types = tw_get_types();
                foreach( $types as $type ) {
                    $option_key = tw_get_option_key( $type );
                    delete_option( sprintf( '%s_%s', TwConstants::META_LAST_SYNC, $option_key ) );
                    delete_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ) );
                    delete_option( sprintf( '%s_%s', TwConstants::META_TOTAL, $option_key ) );
                    delete_option( sprintf( TwConstants::META_START_AT_PAGE, $option_key ) );
                }
            }
            

            public function tw_enqueue_admin_css() {
                wp_register_style( 'ls-admin', plugins_url( 'assets/admin.css', __FILE__ ), false, '1.0' );
                wp_enqueue_style( 'ls-admin' );
            }


            public function tw_add_admin_pages() {
                include_once 'sync-page.php';
                add_menu_page( 'Sync', 'Sync',  'manage_options', 'tw-sync', 'tw_sync_page', 'dashicons-update', 101 );
                
                // include_once 'status-page.php';
                // add_submenu_page(
                //     'lego-sync',
                //     'Status',
                //     'Status',
                //     'manage_options',
                //     'lego-sync-status',
                //     'ls_status_page'
                // );
            }
            
            public function tw_sync_items( string $type, bool $force = false, $page = false, $params = [] ) : void {
                if ( $type ) {
                    $option_key = tw_get_option_key( $type );
                    // echo '<pre>'; var_dump($option_key); echo '</pre>'; exit;
                    // echo '<pre>'; var_dump(sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key )); echo '</pre>'; exit;
                    $data       = get_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ) );
                    // echo '<pre>'; var_dump($data); echo '</pre>'; exit;

                    if ( ! $data ) {
                        if ( ! $page ) {
                            $page = get_option( sprintf( TwConstants::META_START_AT_PAGE, $option_key ) );
                        }
                        
                        $api_url = $this->determine_endpoint( $type, $force );
                        // echo '<pre>'; var_dump($api_url); echo '</pre>'; exit;
                        if ( $api_url ) {
                            $data = $this->pull_data( $api_url, $option_key, $page, $force, false, $params );
                            // echo '<pre>'; var_dump($data); echo '</pre>'; exit;
                        }
                    }

                    if ( ! empty( $data ) ) {
                        $next_page = false;
                        $offset    = 100;
                        $process   = array_slice( (array) $data, 0, $offset );
                        $remaining = array_slice( (array) $data, $offset );
                        
                        if ( ! empty( $remaining ) ) {
                            update_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ), $remaining );
                            wp_schedule_single_event( time() + 5, 'sync_items', [ $type, $force, $page, $params ] );
                        } else {
                            delete_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ) );
                            $next_page = tw_has_more_data( $option_key, $page );
                            if ( $next_page ) {
                                error_log(sprintf('$next_page %s has more data', $next_page));
                            }
                        }
                        // echo '<pre>'; var_dump($process); echo '</pre>'; exit;
                        foreach ( $process as $item ) {
                            $mapped_data = tw_map_data( (array) $item, $type, $force, $params );
                            // echo '<pre>'; var_dump($mapped_data); echo '</pre>'; exit;
                            if ( ! empty( $mapped_data ) ) {
                                $this->store_meta_data( $mapped_data, $type );
                                
                            } elseif ( ! in_array( $type, [ 'themes', 'subthemes' ] ) ) {
                                error_log( sprintf( 'Empty mapped data for %s', $type) );
                            }
                        }
                        
                        if ( ! empty( $remaining ) ) {
                            error_log( 'Schedule new job for remaining data' );
                            wp_schedule_single_event( time() + 5, 'sync_items', [ $type, $force, $page, $params ] );
                        
                        } else {
                            if ( $next_page ) {
                                error_log(sprintf( 'Schedule new job for page %d', $next_page ));
                                wp_schedule_single_event( time() + 5, 'sync_items', [ $type, $force, $next_page, $params ] );
                            } else {
                                if ( 'additional_images' == $type ) {
                                    if ( isset( $params[ 'postID' ] ) ) {
                                        error_log(sprintf( 'Done with %d -> %s', $params[ 'postID' ], get_the_title( $params[ 'postID' ] ) ) );
                                    }
                                    wp_schedule_single_event( time() + 7, 'check_additional_images' );
                                } else {
                                    error_log('No more $remaining');
                                    do_action( 'psx_sync_done', $type );
                                }
                            }
                        }
                        
                    } else {
                        error_log('Empty $data');
                        do_action( 'psx_sync_done', $option_key );
                    }
                }
            }


            public function pull_data( $api_url, $type = false, $page = false, $force = false, $article_number = false, $params = [] ) {
                $data = $this->get_content( $api_url, $type, $page, $force, $article_number, $params );

                if ( empty( $data ) ) {
                    // return if no data is present, and it's not a raw output
                    return [];
                }

                return $data;
            }


            public function store_meta_data( $data = [], $type = '' ) : void {
                if ( empty( $data ) || empty( $type ) ) {
                    return;
                }

                if ( in_array( $type, [ TwConstants::POST_TYPE_GAME ] ) ) {
                    foreach( $data as $post_id => $values ) {
                        // echo '<pre>'; var_dump($values); echo '</pre>'; exit;
                        if ( isset( $values[ 'meta_data' ] ) ) {
                            foreach( $values[ 'meta_data' ] as $meta_key => $meta_value ) {
                                $existing = get_post_meta( $post_id, '_' . $meta_key, true );
                                // echo '<pre>'; var_dump($existing); echo '</pre>'; exit;
                                if ( $meta_value != $existing ) {
                                    update_post_meta( $post_id, '_' . $meta_key, $meta_value );
                                }
                            }
                        }
                        if ( isset( $values[ 'acf_data' ] ) ) {
                            foreach( $values[ 'acf_data' ] as $acf_field_name => $acf_value ) {
                                $existing = get_field( $post_id, $acf_field_name );
                                if ( $acf_value != $existing ) {
                                    update_field( $post_id, $acf_field_name, $acf_value );
                                }
                            }
                        }
                        if ( isset( $values[ 'remove_acf' ] ) ) {
                            foreach( $values[ 'remove_acf' ] as $field_name ) {
                                delete_field( $field_name, $post_id );
                            }
                        }
                        if ( isset( $values[ 'remove_meta' ] ) ) {
                            foreach( $values[ 'remove_meta' ] as $meta_key ) {
                                delete_post_meta( $post_id, '_' . $meta_key );
                            }
                        }
                        if ( isset( $values[ 'taxonomies' ] ) ) {
                            foreach( $values[ 'taxonomies' ] as $taxonomy => $term_values ) {
                                if ( in_array( $taxonomy, [ LegoTaxonomies::PACKAGE, LegoTaxonomies::TAG, LegoTaxonomies::THEME ] ) ) {
                                    wp_set_object_terms( $post_id, $term_values, $taxonomy );
                                }
                            }
                        }
                        if ( in_array( $type, [ TwConstants::POST_TYPE_GAME ] ) ) {
                            // do_action( 'delete_transients', TwConstants::POST_TYPE_GAME, $post_id );
                        }
                    }
                }
            }


            public function determine_endpoint( $type = false, $force = false, $page = false, $set_number = false ) {
                $api_vars = [];
                $endpoint = '';
                $url      = $this->api[ 'url' ];

                if ( $type ) {
                    switch( $type ) {
                        case 'additional_info':
                        case 'selected_sets':
                        case 'single_set':
                        case 'sets':
                            $endpoint = '/getSets';
                            break;
                        case TwConstants::POST_TYPE_GAME:
                            $endpoint = '/games';
                            break;
                        case 'minifigs':
                            $endpoint = '/minifigs';
                            break;
                        case 'additional_images':
                            $endpoint = '/getAdditionalImages';
                            break;
                        case 'themes':
                            $endpoint = '/getThemes';
                            break;
                        case 'subthemes':
                            $endpoint = '/getSubthemes';
                            break;
                        case 'status':
                            $endpoint = '/getKeyUsageStats';
                            break;
                        case 'check_hash':
                            $endpoint = '/checkUserHash';
                            break;
                        default:
                            $endpoint = false;
                    }
                }
                if ( $set_number ) {
                    $endpoint = sprintf( '%s/%s', $endpoint, $set_number );
                }
                if ( $endpoint ) {
                    $url = sprintf( '%s%s%s', $url, $endpoint, $this->build_query_string( $api_vars ) );

                    return $url;
                }

                return false;
            }
            
            
            public function get_oauth_token( bool $force = false ) : string {
                $transient_name = 'playstation_oauth_token_' . md5( json_encode( $this->api ) );
                $oauth_token    = get_transient( $transient_name );
                
                if ( false === $oauth_token || true === $force ) {
                    // https://id.twitch.tv/oauth2/token?client_id=abcdefg12345&client_secret=hijklmn67890&grant_type=client_credentials
                    $api_info      = $this->api;
                    // $token_headers = [ 'x-psn-store-locale-override: ru-UA' ];
                    $post_fields   = [
                        'client_id'     => $api_info[ 'client_id' ],
                        'client_secret' => $api_info[ 'client_secret' ],
                        'grant_type'    => 'client_credentials',
                    ];
                    $query         = http_build_query( $post_fields );
                    // echo '<pre>'; var_dump($query); echo '</pre>'; exit;
                    
                    $api_url = add_query_arg( $post_fields, $api_info[ 'token_url' ] );
                    // echo '<pre>'; var_dump($api_url); echo '</pre>'; exit;
                    $curl    = curl_init();
                    
                    curl_setopt_array($curl, [
                        CURLOPT_URL            => $api_url,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        // CURLOPT_POSTFIELDS     => $query,
                        CURLOPT_RETURNTRANSFER => true,
                    ]);
                    
                    $curl_response = curl_exec($curl);
                    // echo '<pre>'; var_dump(json_decode($curl_response, true)); echo '</pre>'; exit;
                    $response_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                    // echo '<pre>'; var_dump($response_code); echo '</pre>'; exit;
                    curl_close($curl);
                    
                    if ($curl_response) {
                        if (200 === $response_code) {
                            $oauth_array = json_decode( $curl_response, true );
                            // echo '<pre>'; var_dump($oauth_array); echo '</pre>'; exit;
                            if ( isset( $oauth_array[ 'access_token' ] ) ) {
                                $oauth_token = $oauth_array[ 'access_token' ];
                                set_transient($transient_name, $oauth_token, DAY_IN_SECONDS);
                            }
                        } else {
                            error_log(sprintf('Error code %d on retrieving token', $response_code));
                        }
                    } else {
                        error_log('Error retrieving oAuth token');
                    }
                }
                
                return $oauth_token;
            }
            
            
            public function check_user_hash($hash): bool {
                if ( $hash ) {
                    $credentials = $this->api;
                    $api_url     = $this->determine_endpoint( 'check_hash' );
                    
                    if ( $api_url ) {
                        $curl = curl_init();
                        $token_headers = [
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ];
                        $post_fields = [
                            'apiKey' => $credentials[ 'api_key' ],
                            'userHash' => $hash,
                        ];
                        $params = tw_get_curl_params( 'check_hash', true, [] );
                        // error_log(print_r($params,true));
                        $post_fields = array_merge( $post_fields, $params );
                        // error_log(print_r($post_fields,true));
                        $query = http_build_query( $post_fields );
                        // error_log($query);
                        
                        curl_setopt_array( $curl, [
                            CURLOPT_URL            => $api_url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING       => '',
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_CUSTOMREQUEST  => 'POST',
                            CURLOPT_POSTFIELDS     => $query,
                            CURLOPT_HTTPHEADER     => $token_headers,
                        ] );
                        
                        $curl_response = curl_exec( $curl );
                        // error_log(print_r($curl_response, true));
                        $response_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
                        // error_log(print_r($response_code, true));
                        curl_close( $curl );
                        
                        if ( isset( $response_code ) ) {
                            if ( 200 === $response_code ) {
                                $decoded_output = json_decode( $curl_response );
                                // error_log(print_r($decoded_output,true));
                                return true;
                                
                            } else {
                                error_log( sprintf('Error ("%s") on "%s": "%s"', $response_code, $api_url, $curl_response) );
                            }
                        }
                    }
                }
                
                return false;
            }
            
            
            /**
             * Build a query string from $api_vars
             *
             * PHP's built in `http_build_query` does this, but it escapes characters
             * the Coachview API expects such as `>` and `=`.
             *
             * This method builds the values as is by simply casting the key/value pairs as strings.
             */
            public function build_query_string(array $api_vars): string {
                $pairs = [];

                ksort( $api_vars );
                if ( ! count( $api_vars ) ) {
                    return '';
                }

                foreach ( $api_vars as $key => $value ) {
                    $pairs[] = sprintf( '%s=%s', $key, $value );
                }

                return sprintf( '?%s', implode( '&', $pairs ) );
            }


            public function get_content( string $api_url, $type = false, $page_number = false, $force = false, $article_number = false, $args = [] ) {
                // echo '<pre>'; var_dump($type); echo '</pre>'; exit;
                $credentials = $this->api;
                // echo '<pre>'; var_dump($credentials); echo '</pre>'; exit;
                $oauth2_token = $this->get_oauth_token();
                // echo '<pre>'; var_dump($oauth2_token); echo '</pre>'; exit;
                if ( ! $oauth2_token ) {
                    // throw new RuntimeException('Unable to connect to CoachView - could not obtain OAuth2 token');
                    return false;
                }
                
                if ( $api_url && $oauth2_token ) {
                    $authorization_header = 'Bearer ' . $oauth2_token;
                    $curl                 = curl_init();
                    $post_fields          = tw_get_curl_params( $type, $force, $args );
                    $token_headers        = [
                        sprintf( 'Client-ID: %s', $credentials[ 'client_id' ]),
                        sprintf( 'Authorization: Bearer %s', $oauth2_token),
                        'Content-Type: application/json',
                    ];
                    if ( $post_fields ) {
                        // echo '<pre>'; var_dump($token_headers); echo '</pre>'; exit;
                        // echo '<pre>'; var_dump($post_fields); echo '</pre>'; exit;
                        curl_setopt_array( $curl, [
                            CURLOPT_URL            => $api_url,
                            CURLOPT_ENCODING       => '',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_MAXREDIRS      => 10,
                            CURLOPT_TIMEOUT        => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_CUSTOMREQUEST  => 'POST',
                            CURLOPT_POSTFIELDS     => $post_fields,
                            CURLOPT_HTTPHEADER     => $token_headers,
                        ] );
    
                        $curl_response = curl_exec( $curl );
                        // echo '<pre>'; var_dump(json_decode( $curl_response )); echo '</pre>'; exit;
                        $response_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
                        // error_log(sprintf('Respsonse code: %d',$response_code));
                        curl_close( $curl );
    
                        if ( isset( $response_code ) ) {
                            if ( 200 === $response_code ) {
                                $decoded_output = json_decode( $curl_response );
        
                                return $decoded_output;
                            } else {
                                error_log( sprintf('Error ("%s") on "%s": "%s"', $response_code, $api_url, $curl_response) );
                                return [];
                            }
                        }
                    }
                }

                return [];
            }


            public function tw_form_handling() {
                // sync form
                if ( isset( $_POST[ 'twitch_sync_nonce' ] ) ) {
                    if ( ! wp_verify_nonce( $_POST[ 'twitch_sync_nonce' ], 'twitch-sync-nonce' ) ) {
                        if ( function_exists( 'bp_show_error_messages' ) ) {
                            bp_errors()->add( 'error_nonce', __( 'Oops.', 'psinside' ) );
                        }
                        return;
                    } elseif ( empty( $_POST[ 'twitch_type' ] ) ) {
                        bp_errors()->add( 'error_type', __( "You didn't select a type", 'psinside' ) );
                        return;
                    } elseif ( ! empty( $_POST[ 'twitch_type' ] ) ) {
                        $params = [];
                        $force  = isset( $_POST[ 'twitch_force' ] ) ? true : false;
                        $theme  = false;
                        $type   = $_POST[ 'twitch_type' ];
                        echo '<pre>'; var_dump($_POST); echo '</pre>'; exit;
                        $schedule_event = wp_schedule_single_event( time() + 5, 'sync_items', [ $type, $force, false, $params ], true );

                        if ( is_wp_error( $schedule_event ) ) {
                            error_log(sprintf( 'Error scheduling event for %s', $type ) );
                        } elseif ( function_exists( 'bp_show_error_messages' ) ) {
                            update_user_meta( 1, 'browser_refresh', '5' );
                            bp_errors()->add( 'success_schedule_sync', __( 'Your sync has been scheduled.', 'psinside' ) );
                            return;
                        }
                    }
                }
                
                // delete items form
                if ( isset( $_POST[ 'twitch_delete_nonce' ] ) ) {
                    if ( ! wp_verify_nonce( $_POST[ 'twitch_delete_nonce' ], 'twitch-delete-nonce' ) ) {
                        if ( function_exists( 'bp_show_error_messages' ) ) {
                            bp_errors()->add( 'error_nonce', __( 'Oops.', 'psinside' ) );
                            retrun;
                        }
                    } else {
                        if ( isset( $_POST[ 'lego_delete' ] ) ) {
                            update_user_meta( 1, 'browser_refresh', '5' );
                            do_action( 'check_posts_to_delete', $_POST[ 'lego_delete' ] );
                            
                            $schedule_event = wp_schedule_single_event( time() + 3, 'check_posts_to_delete', [ $_POST[ 'lego_delete' ] ] );
                            if ( is_wp_error( $schedule_event ) ) {
                                error_log(sprintf( 'Error scheduling event for %s', $_POST[ 'lego_delete' ] ) );
                            } elseif ( function_exists( 'bp_show_error_messages' ) ) {
                                bp_errors()->add( 'success_schedule_sync', __( 'Deletion of items has been scheduled.', 'psinside' ) );
                            }
                        }
                    }
                }
            }
            
            
            /**
             * The 'one' true instance
             *
             * @return PiTwitch|mixed
             */
            public static function get_instance() {
                static $instance;

                if ( null === $instance ) {
                    $instance = new self();
                }

                return $instance;
            }
        }

        PiTwitch::get_instance();
    endif;
