<?php
/**
 * Forum Settings - Redirects to Forumax Plugin
 */
CSF::createSection( 'docy_opt', array(
    'title'  => esc_html__( 'Forums', 'docy' ),
    'id'     => 'forums_opt',
    'icon'   => 'dashicons dashicons-buddicons-forums',
    'fields' => array(
        array(
            'type'    => 'notice',
            'style'   => 'warning',
            'content' => sprintf(
                __( '<strong>Forum Settings Moved!</strong><br><br>All forum settings have been moved to the <strong>Forumax</strong> plugin for a more user-friendly and modern experience.<br><br>Please go to <a href="%s" class="button button-primary">Forumax Settings</a> to configure your forum options.', 'docy' ),
                admin_url( 'admin.php?page=forumax' )
            ),
        ),
    ),
));
