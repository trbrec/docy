<?php
CSF::createSection( $prefix, array(
    'title'            => esc_html__( 'Custom Code', 'docy' ),
    'id'               => 'custom_code_opt',
    'icon'             => 'dashicons dashicons-editor-code',
    'fields'           => array(
	    array(
		    'id'            => 'custom_css',
		    'type'          => 'code_editor',
		    'title'         => esc_html__( 'CSS Code', 'docy' ),
		    'subtitle'      => esc_html__( 'Paste/write your CSS code here.', 'docy' ),
		    'settings'      => array(
			    'theme'  => 'mbo',
			    'mode'   => 'css',
		    ),
	    ),
	    array(
		    'id'            => 'custom_js',
		    'type'          => 'code_editor',
		    'title'         => esc_html__( 'JS Code', 'docy' ),
		    'subtitle'      => esc_html__( 'Paste/write your JS code here.', 'docy' ),
		    'settings'      => array(
			    'theme'  => 'mbo',
			    'mode'   => 'javascript',
		    ),
		    'default'       => "jQuery(document).ready(function(){\n\n});"
	    ),
    )
));