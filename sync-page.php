<?php
    /*
     * Output for the 'dashboard page'
     */
    function tw_sync_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html( __( 'Sorry, you do not have sufficient permissions to access this page.', 'psinside' ) ) );
        }
        
        $selected_type = ( isset( $_GET[ 'type' ] ) ) ? $_GET[ 'type' ] : false;
        $item_id       = ( isset( $_GET[ 'item_id' ] ) ) ? $_GET[ 'item_id' ] : false;
        $show_id       = $item_id ? true : false;
        $page          = false;
        $sync_meta     = false;
        $themes        = [];
        $subthemes     = [];
        
        if ( $selected_type ) {
            $option_key = tw_get_option_key( $type );
            $sync_meta  = get_option( sprintf( '%s_%s', TwConstants::META_PROCESS_DATA, $option_key ) );
            $page       = get_option( sprintf( TwConstants::META_START_AT_PAGE, $option_key ) );
        }
        
        $sync_types = [
            TwConstants::POST_TYPE_GAME => 'Games',
            // 'themes'                    => 'Themes',
            // 'additional_images'         => 'Additional images',
            'force'                     => 'Force',
        ];
        
        $delete_types = [
            TwConstants::POST_TYPE_GAME => 'Games',
            // 'themes'                    => 'Themes',
        ];
        ?>

        <div class="wrap twitch">

            <h1>Twitch Sync</h1>

            <?php
                if ( function_exists( 'bp_show_error_messages' ) ) {
                    bp_show_error_messages();
                }
            ?>

            <h3>Sync items</h3>
            <form action="" method="post">
                <input name="twitch_sync_nonce" type="hidden" value="<?php echo wp_create_nonce('twitch-sync-nonce'); ?>"/>
                
                <table class="twitch__sync">
                    <thead>
                    <tr>
                        <th>
                            Type
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach( $sync_types as $key => $label ) { ?>
                        <tr>
                            <td>
                                <label>
                                    <?php if ( 'force' == $key ) { ?>
                                        <input type="checkbox" name="twitch_force" value="1"> <?php echo $label; ?>
                                    <?php } else { ?>
                                        <input type="radio" name="twitch_type" value="<?php echo $key; ?>"> <?php echo $label; ?>
                                    <?php } ?>
                                </label>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?php if ( ! $sync_meta ) { ?>
                <input type="submit" class="button button-primary" value="<?php esc_html_e( 'Sync', 'lego' ); ?>"/>
            <?php } ?>
            </form>
            
            <?php if ( current_user_can( 'manage_options' ) ) { ?>
                <form action="" method="post">
                    <input name="twitch_delete_nonce" type="hidden" value="<?php echo wp_create_nonce('twitch-delete-nonce'); ?>"/>
                    <h3>
                        <?php esc_html_e('Delete items', 'lego'); ?>
                    </h3>

                    <p>
                        <?php esc_html_e('Here you can easily remove data.', 'lego'); ?>
                    </p>
            
                    <?php foreach( $delete_types as $key => $label ) { ?>
                        <div class="sync-type">
                            <label>
                                <input type="radio" name="lego_delete" value="<?php echo $key; ?>"> <?php echo $label; ?>
                            </label>
                        </div>
                    <?php } ?>

                    <div class="sync-type hidden">
                        <label>
                            <input type="radio" name="lego_delete" value="lego_set"> Sets
                        </label>
                    </div>

                    <div class="sync-type hidden">
                        <label>
                            <input type="radio" name="lego_delete" value="lego_minifig"> Minifigs
                        </label>
                    </div>

                    <div class="sync-type hidden">
                        <label>
                            <input type="radio" name="lego_delete" value="lego_figure"> Figures
                        </label>
                    </div>

                    <div class="sync-type hidden">
                        <label>
                            <input type="radio" name="lego_delete" value="themes"> Themes
                        </label>
                    </div>

                    <div class="sync-type hidden">
                        <label>
                            <input type="radio" name="lego_delete" value="attachment"> Media
                        </label>
                    </div>

                    <input type="submit" class="button button-primary" value="<?php esc_html_e('Delete items', 'lego' ); ?>"/>

                </form>
            <?php } ?>
        </div>
    <?php }
