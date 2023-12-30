<?php
    function tw_delete_posts( string $type, $items = [] ) : void {
        if ( empty( $items) ) {
            // error_log('Empty items');
        }
        global $wpdb;
        if ( 'themes' === $type ) {
            foreach( $items as $term ) {
                $result = wp_delete_term( $term->term_id, LegoTaxonomies::THEME );
            }

        } elseif ( in_array( $type, [ 'attachment', 'unattached' ] ) ) {
            foreach( $items as $attachment_id ) {
                // error_log('DELETE : ' . $attachment_id);
                wp_delete_attachment( $attachment_id, true );
            }
            
        } elseif ( in_array( $type, [ LegoPostTypes::SET, LegoPostTypes::MINIFIG ] ) ) {
            // @TODO: delete last sync timestamp for $type
            foreach( $items as $post_id ) {
                wp_delete_post( $post_id, true );
            }
            
        } else {
            if ( ! empty( $items ) ) {
                $post_id_string = implode( "','", $items );
                $wpdb->query( "DELETE FROM $wpdb->posts WHERE ID in ('$post_id_string')" );
                $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE post_id in ('$post_id_string')" );
            }
        }
        wp_schedule_single_event( time() + 5, 'check_posts_to_delete', [ $type ] );
    }
    add_action( 'delete_custom_posts', 'tw_delete_posts', 10, 2 );


    function tw_download_selected_items( $items ) : void {
        if ( ! $items ) {
            return;
        }

        if ( ! empty( $items ) ) {
            foreach( $items as $item_post_id ) {
                $art_nr = get_post_meta( $item_post_id, '_lego_article_number', true );
                if ( ! empty( $art_nr ) ) {
                    $set_numbers[] = $art_nr;
                }
            }
            if ( isset( $set_numbers ) ) {
                $set_numbers_string    = implode( ',', $set_numbers );
                $params[ 'setNumber' ] = $set_numbers_string;
                update_user_meta( 1, 'browser_refresh', '5' );
                wp_schedule_single_event( time() + 5, 'sync_items', [ 'selected_sets', true, false, $params ] );
            }
        }
    }
    add_action( 'download_selected_items', 'tw_download_selected_items' );
    
    
    function tw_download_additional_images( $items ) : void {
        if ( ! $items ) {
            return;
        }
        
        if ( ! empty( $items ) ) {
            $counter = 1;
            foreach( $items as $item_post_id ) {
                $set_id = get_post_meta( $item_post_id, '_lego_set_id', true );
                if ( ! empty( $set_id ) ) {
                    $params[ 'setID' ]   = $set_id;
                    $params[ 'postID' ] = $item_post_id;
                    wp_schedule_single_event( time() + ( $counter * 3 ), 'sync_items', [ 'additional_images', true, false, $params ] );
                    $counter++;
                }
            }
        }
    }
    add_action( 'download_additional_images', 'tw_download_additional_images' );
    
    
    function tw_download_additional_info( $items ) : void {
        if ( ! $items ) {
            return;
        }
        
        if ( ! empty( $items ) ) {
            $counter = 1;
            foreach( $items as $item_post_id ) {
                // create array with set ids to process
                $set_id = get_post_meta( $item_post_id, '_lego_article_number', true );
                if ( ! empty( $set_id ) ) {
                    $set_ids[] = $set_id;
                }
            }
            
            $set_string               = implode( ',', $set_ids );
            $params[ 'setNumber' ]    = $set_string;
            $params[ 'extendedData' ] = 1;

            wp_schedule_single_event( time() + ( $counter * 3 ), 'sync_items', [ 'additional_info', true, false, $params ] );
        }
    }
    add_action( 'download_additional_info', 'tw_download_additional_info' );
