<?php

    /*
     * This file contains 'static' functions
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    function tw_get_types() : array {
        $types = [
            TwConstants::POST_TYPE_GAME,
            'theme',
            'additional_images',
        ];

        return $types;
    }

    function tw_get_api_info() : array {
        $credentials = [
            'limit'          => 20,
            'url'            => getenv( 'TW_API_URL' ),
            'api_key'        => getenv( 'TW_API_KEY' ),
            'client_id'      => getenv( 'TW_CLIENT_ID' ),
            'client_secret'  => getenv( 'TW_CLIENT_SECRET' ),
            'username'       => getenv( 'TW_USERNAME' ),
            'password'       => getenv( 'TW_PASSWORD' ),
            'token_url'      => getenv( 'TW_TOKEN_URL' ),
        ];

        return $credentials;
    }


    function tw_create_item( array $data, $post_type = LegoPostTypes::SET ) {
        if ( ! $data ) {
            return false;
        }
        
        $post_data = [
            'post_type'    => $post_type,
            'post_title'   => $data[ 'name' ],
            'post_status'  => 'pending',
            'post_author'  => '1',
        ];
        $post_id = wp_insert_post( $post_data );

        if ( ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, '_original_id', $data['id'] );
            
            return $post_id;
        }

        return false;
    }


    function tw_get_option_key( $type = '' ) {
        if ( empty( $type ) ) {
            return '';
        }

        switch( $type ) {
            case TwConstants::POST_TYPE_GAME :
                $key = 'games';
                break;
            case 'theme' :
            case 'themes' :
                $key = 'themes';
                break;
            default:
                $key = false;
        }

        return $key;
    }

    function tw_has_more_data( $type, $page ) {
        if ( $type ) {
            $page        = ! $page ? 1 : $page;
            $option_type = tw_get_option_key( $type );
            $credentials = tw_get_api_info();
            $total       = get_option( sprintf( '%s_%s', TwConstants::META_TOTAL, $option_type ), 1 );
            
            if ( 'sets' === $type ) {
                $pages = ( $total / $credentials[ 'limit' ] ) - $page;
                if ( 0 < $pages ) {
                    $next_page = (int) $page + 1;
                    update_option( sprintf( TwConstants::META_START_AT_PAGE, $type ), $next_page );
                    
                    return $next_page;
                }
            }
        }
        
        return false;
    }
    
    
    // create term
    function tw_insert_term( $label, $taxonomy, $args = [] ) {
        if ( $label ) {
            $new_term_args = [];
            $new_term      = wp_insert_term( $label, $taxonomy, $args );
            
            if ( 'development' === WP_ENV ) {
                if ( ! is_wp_error( $new_term ) ) {
                    error_log( sprintf( 'Term "%s" inserted', $label ) );
                } else {
                    error_log( sprintf( 'Term %s not inserted', $label ) );
                    error_log( print_r( $new_term, true ) );
                }
            }
        }
    }

    
    function tw_get_curl_params( $type, $force = false, $params = [] ) {
        $param_array = [];

        if ( 'games' === $type ) {
            $param_array[ 'limit' ]  = tw_get_api_info()[ 'limit' ];
            $param_array[ 'fields' ] = '*';

            if ( ! $force ) {
                $last_sync_timestamp = get_option( sprintf( '%s_%s', TwConstants::META_LAST_SYNC, $type ) );
                
                if ( ! empty( $last_sync_timestamp ) ) {
                    $last_sync_date = gmdate( 'Y-m-d', (int) $last_sync_timestamp );
                    if ( $last_sync_date ) {
                        // $param_array[ 'updatedSince' ] = $last_sync_date;
                    }
                }
            }
        }
        if ( ! empty( $param_array ) ) {
            $body = '';
            foreach( $param_array as $key => $value ) {
                $body .= sprintf( '%s %s; ', $key, $value );
            }
        }
        if ( isset( $body ) ) {
            return trim( $body );
        }
        
        return false;
    }
    
    
    function tw_get_item_type( $type ) {
        if ( ! $type ) {
            return false;
        }
        
        switch( $type ) {
            case TwConstants::POST_TYPE_GAME:
                $item_type = TwConstants::POST_TYPE_GAME;
                break;
        }
        
        if ( isset( $item_type ) ) {
            return $item_type;
        }
        
        return false;
    }
    
    
    function tw_find_term( $taxonomy, $name, $parent_id = false, $theme_id = false ) {
        if ( ! $taxonomy || ! $name ) {
            return false;
        }
        
        if ( $parent_id ) {
            $term_args = [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'parent'     => $parent_id,
                'name'       => $name,
            ];
            $terms = get_terms( $term_args );

            if ( ! empty( $terms ) ) {
                $existing_term = $terms[ 0 ];
            } else {
                $existing_term = false;
            }
        } elseif ( $theme_id ) {
            $existing_term = get_term_by( 'term_id', $theme_id, $taxonomy );
        } else {
            $existing_term = get_term_by( 'name', $name, $taxonomy );
        }
        
        if ( $existing_term instanceof WP_Term ) {
            $existing_term->link = get_term_link( $existing_term, $taxonomy );
            return $existing_term;
        }

        return false;
    }
    
    
    function tw_item_exists( string $value, $key = '_original_id', $post_type = TwConstants::POST_TYPE_GAME ) : int {
        if ( $post_type ) {
            $args = [
                'post_type'      => $post_type,
                'post_status'    => [ 'publish', 'draft', 'future', 'pending' ],
                'posts_per_page' => 1,
                'fields'         => 'ids',
            ];
            if ( $value ) {
                $args[ 'meta_query' ] = [
                    [
                        'key'   => $key,
                        'value' => $value,
                    ],
                ];
            }
            $posts = get_posts( $args );
            
            if ( isset( $posts[ 0 ] ) ) {
                return $posts[ 0 ];
            }
        }
        
        return 0;
    }
