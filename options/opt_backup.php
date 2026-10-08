<?php
CSF::createSection($prefix, array(
    'title'            => esc_html__( 'Backup', 'docy' ),
    'id'               => 'docy_backup',
    'icon'             => 'dashicons dashicons-editor-textcolor',
));

CSF::createSection($prefix, array(
    'parent'            => 'docy_backup',
    'title'            => esc_html__( 'Export / Import', 'docy' ),
    'id'               => 'docy_export_import',
    'fields'           => array(
        array(
            'title'     => esc_html__( 'Backup', 'docy' ),
            'type'      => 'backup',
        )
    )
));