<?php
    include 'cron-jobs.php';
    
    /**
     * Action to trigger when sync == done
     *
     * @return void
     */
    function tw_sync_done( string $type = '' ) : void {
        if ( $type ) {
            $option_key = tw_get_option_key( $type );
            if ( 'additional_images' !== $type ) {
                update_option( sprintf( '%s_%s', TwConstants::META_LAST_SYNC, $option_key ), time() );
            }
            delete_option( sprintf( TwConstants::META_START_AT_PAGE, $option_key ) );
            delete_option( sprintf( '%s_%s', TwConstants::META_TOTAL, $option_key ) );
            delete_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ) );
        }
        error_log('Twitch sync = done');
        delete_user_meta( 1, 'browser_refresh' );
    }
    add_action( 'psx_sync_done', 'tw_sync_done' );
    
    
    /**
     * Update term meta
     *
     * @param $term_id
     * @param $data
     * @param $type
     *
     * @return void
     */
    function tw_add_term_meta( $term_id, $data, $type ) {
        if ( in_array( $type, [ 'themes', 'subthemes' ] ) ) {
            if ( ! empty( $data[ 'yearFrom' ] ) ) {
                update_term_meta( $term_id, '_year_from', $data[ 'yearFrom' ] );
            }
            if ( ! empty( $data[ 'yearTo' ] ) ) {
                update_term_meta( $term_id, '_year_to', $data[ 'yearTo' ] );
            }
        }
        if ( 'subthemes' === $type ) {
            if ( ! empty( $data[ 'theme' ] ) ) {
                // @TODO: maybe store term_id ?
                update_term_meta( $term_id, '_parent_theme', $data[ 'theme' ] );
            }
        }
    }
    add_action( 'lego_add_term_meta', 'tw_add_term_meta', 10, 3 );
    
    
    /**
     * Download image
     *
     * @param string $image_url
     * @param int $post_id
     *
     * @return void
     */
    function tw_download_image( string $image_url, int $post_id, $gallery = false, $alternate = false ) : void {
        if ( ! $image_url || ! $post_id ) {
            return;
        }
        
        $post_type  = get_post_type( $post_id );
        $folder     = LegoPostTypes::SET === $post_type ? 'sets' : 'minifigs';
        $folder     = LegoPostTypes::FIGURE === $post_type ? 'figures' : $folder;
        $upload_dir = wp_upload_dir();
        if ( ! file_exists( sprintf( '%s/%s', $upload_dir[ 'basedir' ], $folder ) ) ) {
            mkdir( sprintf( '%s/%s', $upload_dir[ 'basedir' ], $folder ), 0755 );
        }
        
        $file_name = basename( $image_url );
        if ( LegoPostTypes::MINIFIG == $post_type ) {
            $file_name = str_replace( '_large', '', $file_name );
        }
        $store_here = sprintf( '%s/%s/%s', $upload_dir[ 'basedir' ], $folder, str_replace( '%20', '-', $file_name ));

        if ( file_exists( $store_here ) ) {
            $file_url      = $upload_dir[ 'baseurl' ] . '/sets/' . str_replace( '%20', '-', $file_name );
            $attachment_id = attachment_url_to_postid( $file_url );
            
            if ( 0 < $attachment_id ) {
                // file exists and in media gallery
                if ( $alternate ) {
                    update_post_meta( $post_id, '_lego_rebrickable_image', $attachment_id );
                } elseif ( $gallery ) {
                    do_action( 'add_to_gallery', $post_id, $attachment_id );
                } else {
                    $existing_image_id = get_post_meta( $post_id, '_thumbnail_id', true );
                    if ( $existing_image_id !== $attachment_id ) {
                        set_post_thumbnail( $post_id, $attachment_id );
                    }
                }
                switch( $post_type ) {
                    case LegoPostTypes::SET:
                        do_action( 'delete_transients', LegoPostTypes::SET, $post_id );
                        break;
                    case LegoPostTypes::MINIFIG:
                    case LegoPostTypes::FIGURE:
                        do_action( 'delete_transients', LegoPostTypes::MINIFIG, $post_id );
                        break;
                }
                
            } else {
                // file exists but not in media gallery
                do_action( 'import_image', $store_here, $post_id, $gallery );
            }
        
        } else {
            $file_handle = fopen( $store_here, 'w' );
            $handle      = curl_init();
        
            if ( $file_handle === false ) {
                sync_log( sprintf( 'Bestand "%s" kon niet worden aangemaakt voor post %d.', $image_url, $post_id ) );
        
                return;
            }
        
            curl_setopt_array( $handle, [
                CURLOPT_URL  => $image_url,
                CURLOPT_FILE => $file_handle,
            ] );
        
            curl_exec( $handle );
            curl_close( $handle );
            fclose( $file_handle );
            
            // @TODO: check if file size is not 0;
        
            do_action( 'import_image', $store_here, $post_id, $gallery );
        }
    }
    add_action( 'download_image', 'tw_download_image', 10, 4 );
    
    
    /**
     * Import image
     *
     * @param string $image_url
     * @param int $post_id
     *
     * @return void
     */
    function tw_import_image( string $image_url, int $post_id, $alternate = false, $gallery = false ) : void {
        if ( ! $image_url || ! $post_id ) {
            return;
        }
        
        $file_name     = basename( $image_url );
        $file_type     = wp_check_filetype( $file_name, null );
        $wp_upload_dir = wp_upload_dir();
        
        $attachment = [
            'guid'           => $wp_upload_dir[ 'url' ] . '/' . $file_name,
            'post_mime_type' => $file_type[ 'type' ],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
            'post_author'    => get_current_user_id(),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $attach_id  = wp_insert_attachment( $attachment, $image_url, $post_id );

        if ( ! is_wp_error( $attach_id ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attach_data = wp_generate_attachment_metadata( $attach_id, $image_url );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            if ( $gallery ) {
                do_action( 'add_to_gallery', $post_id, $attach_id );
            } else {
                set_post_thumbnail( $post_id, $attach_id );
                delete_post_meta( $post_id, 'no_image' );
            }
            
            $post_type = get_post_type( $post_id );
            switch( $post_type ) {
                case LegoPostTypes::SET :
                    do_action( 'delete_transients', LegoPostTypes::SET, $post_id );
                    break;
                case LegoPostTypes::MINIFIG :
                    do_action( 'delete_transients', LegoPostTypes::MINIFIG, $post_id );
                    break;
            }
        } else {
            error_log(sprint_f('Error: %s', print_r(is_wp_error($attach_id),true)));
        }
    }
    add_action( 'import_image', 'tw_import_image', 10, 4 );
    
    
    /**
     * Add an image to a gallery
     *
     * @param $post_id
     * @param $image_id
     *
     * @return void
     */
    function tw_add_to_gallery( $post_id, $image_id ) : void {
        if ( ! $image_id || ! $post_id ) {
            return;
        }
        
        $existing_gallery = get_field( 'lego_gallery', $post_id );
        if ( is_array( $existing_gallery ) ) {
            $new_gallery = array_merge( $existing_gallery, [ $image_id ] );
        } else {
            $new_gallery = [ $image_id ];
        }
        if ( isset( $new_gallery ) ) {
            update_field( 'lego_gallery', $new_gallery, $post_id );
        }
    }
    add_action( 'add_to_gallery', 'tw_add_to_gallery', 10, 2 );
    
    
    /**
     * Checks if a Twitch sync is active
     *
     * @return void
     */
    function tw_check_sync_in_progress() {
        $screen    = get_current_screen();
        $page      = false;
        $sync_meta = false;
        $types     = tw_get_types();
        
        foreach( $types as $type ) {
            $option_key = tw_get_option_key( $type );
            $sync_meta  = get_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ) );
            $page       = get_option( sprintf( TwConstants::META_START_AT_PAGE, $option_key ) );
            if ( ! empty( $sync_meta ) || ! empty( $page ) ) {
                break;
            }
        }
        
        if ( $sync_meta || $page ) {
            if ( isset( $screen->id ) && 'toplevel_page_sync-deals' === $screen->id ) {
                echo sprintf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    "There is a (BrickSet) sync in progress. You can't start a new one before it's finished."
                );
            } else {
                echo sprintf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    "A (BrickSet) sync is in progress, so if you're expecting updates, they could be coming."
                );
            }
        }
    }
    add_action( 'admin_notices', 'tw_check_sync_in_progress' );
