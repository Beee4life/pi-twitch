<?php

    /*
     * Map all meta data
     */
    function tw_map_data( array $data, string $type = '', bool $force = false, $params = [] ) : array {
        // echo '<pre>'; var_dump($params); echo '</pre>'; exit;
        $mapped_data = [];

        if ( ! $data ) {
            error_log('No data. Unable to import. Skipping.');

            return [];
        }

        if ( ! $type ) {
            error_log("No type defined, so it can't be mapped to a certain type.");
            return [];
        }
        
        if ( ! isset( $data[ 'id' ] ) ) {
            if ( isset( $data[ 'name' ] ) ) {
                error_log( sprintf( 'No id. Unable to process %s. Skipping.', $data[ 'name' ] ) );
            } else {
                error_log( 'No id. Unable to process. Skipping.' );
            }
        }
        if ( ! isset( $data[ 'name' ] ) ) {
            error_log( sprintf( 'No name. Unable to process id %d. Skipping.', $data[ 'id' ] ) );
        }

        $item_type = tw_get_item_type( $type );
        
        if ( in_array( $item_type, [ TwConstants::POST_TYPE_GAME ] ) ) {
            $post_id = tw_item_exists( $data['id'], '_original_id', TwConstants::POST_TYPE_GAME );
            if ( 0 === $post_id ) {
                $post_id = tw_create_item( $data, TwConstants::POST_TYPE_GAME );
            }
            
            if ( 0 < $post_id ) {
                [ $acf_data, $add_meta, $remove_acf_fields, $remove_meta, $taxonomies ] = tw_map_game_data( $data, $post_id, $type );

                $mapped_data[ $post_id ] = [
                    'type'        => $item_type,
                    'acf_data'    => $acf_data,
                    'meta_data'   => $add_meta,
                    'remove_acf'  => $remove_acf_fields,
                    'remove_meta' => $remove_meta,
                    'taxonomies'  => $taxonomies,
                ];
            }
        
        } else {
            error_log('No type catches the mapping');
        }

        return $mapped_data;
    }


    function tw_map_game_data( array $item, $post_id, $type = TwConstants::POST_TYPE_GAME ) : array {
        $add_acf_data                  = [];
        $remove_acf_data               = [];
        $remove_meta_data              = [];
        $taxonomies                    = [];
        $add_meta_data[ 'game_name' ] = $item[ 'name' ];
        
        // this is an acf example. I don't know which fields you want in acf and which not
        if ( ! empty( $item[ 'some_index' ] ) ) {
            $add_acf_data[ 'some_index' ] = $item[ 'some_index' ];
        } else {
            $remove_acf_data[] = 'some_index';
        }
        
        if ( ! empty( $item[ 'first_release_date' ] ) ) {
            $add_meta_data[ 'first_release_date' ] = $item[ 'first_release_date' ];
        } else {
            $remove_meta_data[] = 'first_release_date';
        }
        
        if ( ! empty( $item[ 'summary' ] ) ) {
            $add_meta_data[ 'summary' ] = $item[ 'summary' ];
        } else {
            $remove_meta_data[] = 'summary';
        }
        
        if ( ! empty( $item[ 'url' ] ) ) {
            $add_meta_data[ 'twitch_item_url' ] = $item[ 'url' ];
        } else {
            $remove_meta_data[] = 'twitch_item_url';
        }
        
        if ( ! empty( $item[ 'age_ratings' ] ) ) {
            $add_meta_data[ 'age_ratings' ] = $item[ 'age_ratings' ];
        } else {
            $remove_meta_data[] = 'age_ratings';
        }
        
        if ( ! empty( $item[ 'platforms' ] ) ) {
            $add_meta_data[ 'platforms' ] = $item[ 'platforms' ];
        } else {
            $remove_meta_data[] = 'platforms';
        }
        
        if ( ! empty( $item[ 'artworks' ] ) ) {
            $add_meta_data[ 'artworks' ] = $item[ 'artworks' ];
        } else {
            $remove_meta_data[] = 'artworks';
        }
        
        if ( ! empty( $item[ 'screenshots' ] ) ) {
            $add_meta_data[ 'screenshots' ] = $item[ 'screenshots' ];
        } else {
            $remove_meta_data[] = 'screenshots';
        }
        
        if ( ! empty( $item[ 'version_parent' ] ) ) {
            $add_meta_data[ 'version_parent' ] = $item[ 'version_parent' ];
        } else {
            $remove_meta_data[] = 'version_parent';
        }
        
        if ( ! empty( $item[ 'version_title' ] ) ) {
            $add_meta_data[ 'version_title' ] = $item[ 'version_title' ];
        } else {
            $remove_meta_data[] = 'version_title';
        }
        
        if ( ! empty( $item[ 'release_dates' ] ) ) {
            $add_meta_data[ 'release_dates' ] = $item[ 'release_dates' ];
        } else {
            $remove_meta_data[] = 'release_dates';
        }
        
        if ( ! empty( $item[ 'tags' ] ) ) {
            $add_meta_data[ 'tags' ] = $item[ 'tags' ];
        } else {
            $remove_meta_data[] = 'tags';
        }
        
        if ( ! empty( $item[ 'updated_at' ] ) ) {
            $add_meta_data[ 'updated_at' ] = $item[ 'updated_at' ];
        } else {
            $remove_meta_data[] = 'updated_at';
        }
        
        /* Taxonomy data */
        /*
         * theme
         * themeGroup
         * category
         */
        
        if ( isset( $item[ 'category' ] ) && 0 < $item[ 'category' ] ) {
            $add_meta_data[ 'category_id' ] = $item[ 'category' ];
            $existing_term                   = tw_find_term( 'your_taxonomy', $item[ 'category' ] );
            
            if ( $existing_term instanceof WP_Term ) {
                $taxonomies[ 'your_taxonomy' ] = $child_term->term_id;
            }
        } else {
            $remove_meta_data[] = 'category_id';
        }
        
        ksort($add_meta_data);

        return [ $add_acf_data, $add_meta_data, $remove_acf_data, $remove_meta_data, $taxonomies ];
    }
