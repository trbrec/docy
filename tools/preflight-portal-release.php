<?php
/** Read-only CRM ownership check before changing any portal runtime. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
ini_set( 'display_errors', '0' );
set_exception_handler( static function() { fwrite( STDERR, "Canonical CRM preflight unconfirmed.\n" ); exit( 1 ); } );
require __DIR__ . '/crm-module-source-guard.php';
$bundle = dirname( __DIR__ ); $crm = '/home/customer/www/crm.trbrec.com/public_html';
$targets = array( $crm . '/app/GiuliaSupport.php' => $bundle . '/integrations/giulia/GiuliaSupport.php', $crm . '/assets/onboarding.css' => $bundle . '/integrations/onboarding/crm/onboarding.css' );
foreach ( glob( $bundle . '/integrations/onboarding/crm/*.php' ) as $file ) $targets[$crm . '/app/' . basename( $file )] = $file;
trb_crm_module_source_guard( $targets );
echo "Canonical CRM modules verified before portal runtime changes.\n";
