<?php
/** Explicit properties preserve the existing PHP object API on PHP 8.2+. */
function trb_signature_property_patch( $source, $class, array $properties ) {
    $anchor = '/(^[\t ]*(?:(?:final|abstract)\s+)?class\s+' . preg_quote( $class, '/' ) . '(?:\s+extends\s+[a-zA-Z0-9_\\\\]+)?(?:\s+implements\s+[a-zA-Z0-9_, \\\\]+)?\s*\{)/m';
    $declarations = '';
    foreach ( $properties as $property ) {
        if ( ! preg_match( '/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $property ) ) throw new RuntimeException( 'Invalid property manifest.' );
        if ( ! preg_match( '/\b(?:public|protected|private)\s+\$' . preg_quote( $property, '/' ) . '\b/', $source ) ) $declarations .= "\n    public \$" . $property . ';';
    }
    if ( '' === $declarations ) return $source;
    $patched = preg_replace( $anchor, '$1' . $declarations . "\n", $source, 1, $count );
    if ( 1 !== $count ) throw new RuntimeException( 'Unrecognized signature model.' );
    return $patched;
}

function trb_signature_compatibility_manifest() {
    return array(
        'e-signature-business-add-ons/esig-active-campaign/admin/esig-active-campaign-admin.php' => array( 'class' => 'ESIG_ACTIVE_CAMPAIGN_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => '2ddff47e751f9b9ebafa04e28749617c5085fd3dc8ad9554fc44b2cd775804b0' ),
        'e-signature-business-add-ons/esig-add-custom-message/admin/esig-add-custom-message.php' => array( 'class' => 'ESIG_CUSTOM_MESSAGE', 'properties' => array( 'plugin_slug' ), 'baseline' => 'acd8fe8429734cf0df1fe1f508944006b83fc709bca8fa961081450b93ce4868' ),
        'e-signature-business-add-ons/esig-add-templates/admin/esig-at-admin.php' => array( 'class' => 'ESIG_AT_Admin', 'properties' => array( 'documents_table', 'plugin_slug' ), 'baseline' => '70fa17dd8dbf2984b2a8e9c7becf27a9ee64e51ca947fbffae9bb7c16a0809bf' ),
        'e-signature-business-add-ons/esig-assign-signer-order/admin/esig-assign-approval-signer-admin.php' => array( 'class' => 'ESIG_ASSIGN_APPROVAL_SIGNER_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => '32a817a778686650a3ed5c1ca14d5c376fa9b0bad484cab0f5d572964e8b3d4b' ),
        'e-signature-business-add-ons/esig-assign-signer-order/admin/esig-assign-signer-order-admin.php' => array( 'class' => 'ESIG_ASSIGN_ORDER_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => '249e2de745b8f42a547762a4b96b6fb029d8a2440c281583ba1b49addb61935d' ),
        'e-signature-business-add-ons/esig-attach-pdf-to-email/includes/esig-pdf-to-email-admin.php' => array( 'class' => 'ESIG_PDF_TO_EMAIL_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => '8e6eb12bf1e2975fdc281649096d50fb4589b209f6be488b640ff09132eb1a37' ),
        'e-signature-business-add-ons/esig-auto-add-my-signature/admin/esig-aams-admin.php' => array( 'class' => 'ESIG_AAMS_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => 'e950f9edb8be10018793bde9a39e4c7f8f54e7fbc46ef63166c0022b8e4d96f6' ),
        'e-signature-business-add-ons/esig-auto-register-user/admin/esig-auto-user-register-admin.php' => array( 'class' => 'ESIG_AUTO_REGISTER_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => 'eaee074391a8602356fbafafbcbe5a8ca5344567be115d9b07d191b5edb307f6' ),
        'e-signature-business-add-ons/esig-dropbox-sync/admin/esig-ds-admin.php' => array( 'class' => 'ESIG_DS_Admin', 'properties' => array( 'document', 'invitation', 'plugin_slug', 'signature', 'user' ), 'baseline' => '4c8a477a105ae13df92a55628d396109d91f7337ad071b9a9dcdbd43efb53020' ),
        'e-signature-business-add-ons/esig-signing-reminders/admin/esig-reminders-admin.php' => array( 'class' => 'ESIG_REMINDERS_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => 'd9deddc2321e5dab1e08dbd82efe2a13d8a2c484f63ab71b9c4ea63c20504ea7' ),
        'e-signature-business-add-ons/esig-stand-alone-docs/public/esig-sad.php' => array( 'class' => 'ESIG_SAD', 'properties' => array( 'doctable' ), 'baseline' => 'db3e05163429e813a4fbcade9e3ff49b02f894307cebce00efbbb95144f4bb17' ),
        'e-signature-business-add-ons/esig-unlimited-sender-roles/admin/esig-usr-admin.php' => array( 'class' => 'ESIG_USR_ADMIN', 'properties' => array( 'plugin_slug' ), 'baseline' => 'ec9cf1322a18f74bf1b8c44004c282f949e58f7487584d1453aaa5e763f71f19' ),
        'e-signature-business-add-ons/esig-upload-logo-and-branding/admin/esig-logo-branding-admin.php' => array( 'class' => 'ESIG_LOGO_BRANDING_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => '8148bd221197ff31385df1dc06a0df5c3b4c149ec9e354393be1638544ab7720' ),
        'e-signature-business-add-ons/esig-url-redirect-after-signing/admin/esig-url-admin.php' => array( 'class' => 'ESIG_URL_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => '56638942dfc639dccfb4f3f68f573357b660b7f3b1bf99f7b12f39d9ad5fdb58' ),
        'e-signature/add-ons/esig-document-activity-notifications/admin/esig-dan-admin.php' => array( 'class' => 'ESIG_DAN_Admin', 'properties' => array( 'plugin_slug' ), 'baseline' => 'e01862290e63a8bb422667a13de05a197d9249ef343423df80a261b386df8b3b' ),
        'e-signature/add-ons/esig-save-as-pdf/admin/esig-pdf-admin.php' => array( 'class' => 'ESIG_PDF_Admin', 'properties' => array( 'document', 'invitation', 'plugin_slug', 'signature', 'user' ), 'baseline' => 'a6ba7a6df9e9a6c17952b57c6fdc8e0ed6e5763ce7e4ec4b77d7e2c25c010754' ),
        'e-signature/e-signature.php' => array( 'class' => 'WP_E_Digital_Signature', 'properties' => array( 'common', 'document', 'email', 'invite', 'meta', 'notice', 'setting', 'shortcode', 'signature', 'signer', 'user', 'validation', 'view' ), 'baseline' => '50673811ac725649225bbfa1557daf978d8fd78c1d15486cf8e98fa6c7f3e6dd' ),
        'e-signature/includes/Esign_core_load.php' => array( 'class' => 'Esign_core_load', 'properties' => array( 'General', 'esigrole' ), 'baseline' => 'f19da181f26838e8b343c486b5c9ca67f36d864ffc3bb5e58a75b27443510a20' ),
        'e-signature/models/Addon.php' => array( 'class' => 'WP_E_Addon', 'properties' => array( 'document', 'settings' ), 'baseline' => '308c32f82e7f7c974c9926dec09ed27e6a2bfdef6d900817484594ea21338161' ),
        'e-signature/models/Common.php' => array( 'class' => 'WP_E_Common', 'properties' => array( 'document', 'item_plugshortname', 'settings' ), 'baseline' => '9cb3059844f11df674cc3bf095f77c402c495ab55e0160614dffd95cd53db466' ),
        'e-signature/models/Document.php' => array( 'class' => 'WP_E_Document', 'properties' => array( 'documentsSignaturesTable', 'eventsTable', 'invite', 'settings', 'signature', 'user', 'usertable', 'validation' ), 'baseline' => 'b5bbd7bb167538f96be8769b77be411685edaf8791cbaad9fc72641681058e42' ),
        'e-signature/models/Esigrole.php' => array( 'class' => 'WP_E_Esigrole', 'properties' => array( 'settings', 'user' ), 'baseline' => '01c04ebb3879413d202240914313fe3873c972b3da8a919917e6c078aac9778f' ),
        'e-signature/models/General.php' => array( 'class' => 'WP_E_General', 'properties' => array( 'item_pluginname', 'item_plugshortname', 'license_active', 'license_key', 'output_key', 'settings', 'update' ), 'baseline' => 'fa53e9a4c03312ef649bb23eecbd9d0c175fb2f189d956ebca992ad5628eb0e5' ),
        'e-signature/models/Signature.php' => array( 'class' => 'WP_E_Signature', 'properties' => array( 'joinTable' ), 'baseline' => 'dece6a1f455e94605ca7ad956a7d7351c88398b8ebf2293afc23bd72a2e64599' ),
        'e-signature/models/User.php' => array( 'class' => 'WP_E_User', 'properties' => array( 'table', 'signature', 'settings', 'signer' ), 'baseline' => '959a001352e5b450a5112c5c898295b7a026d3b09b55a69c97e8524ed3a8bdaf' ),
    );
}
