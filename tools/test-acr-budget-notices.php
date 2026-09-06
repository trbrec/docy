<?php
define( 'ABSPATH', __DIR__ . '/' );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function number_format_i18n( $number, $decimals = 0 ) { return number_format( $number, $decimals, ',', '.' ); }
require dirname( __DIR__ ) . '/inc/trb-resource-monitor.php';
function check_budget( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
foreach ( array( 0, 3.765, 4, 4.125, 5.5, 50 ) as $spent ) {
	check_budget( ! trb_resource_acr_budget_alert_required( $spent, 5 ), 'Internal estimates must never trigger wallet alerts.' );
	trb_resource_acr_thresholds( $spent, 5 ); // Must not access the notification queue.
}
$notice = trb_resource_acr_budget_notice( 4.125, 5 );
foreach ( array( '4,1250 USD', '5,00 USD', 'Limite interno', 'non misurano il credito' ) as $expected ) check_budget( false !== strpos( $notice, $expected ), $expected );
check_budget( false === strpos( $notice, '82,5%' ) && false === strpos( $notice, 'residuo' ), 'Do not imply estimated reservations measure the wallet.' );
check_budget( trb_resource_pcloud_quota_valid( array( 'result'=>0, 'quota'=>1000, 'usedquota'=>500 ) ), 'Valid provider quota.' );
check_budget( trb_resource_pcloud_quota_valid( array( 'result'=>0, 'quota'=>1000, 'usedquota'=>1200 ) ), 'Overquota is a real capacity condition.' );
foreach ( array(
	array( 'quota'=>1000, 'usedquota'=>500 ),
	array( 'result'=>1022, 'quota'=>1000, 'usedquota'=>500 ),
	array( 'result'=>0, 'quota'=>0, 'usedquota'=>0 ),
	array( 'result'=>0, 'quota'=>1000, 'usedquota'=>-1 ),
	array( 'result'=>0, 'quota'=>'unknown', 'usedquota'=>5 ),
	array( 'result'=>0, 'quota'=>INF, 'usedquota'=>5 ),
	array( 'result'=>0, 'quota'=>1000, 'usedquota'=>NAN )
) as $data ) check_budget( ! trb_resource_pcloud_quota_valid( $data ), 'Reject unavailable, failed or malformed quota.' );
echo "Provider accounting boundaries passed.\n";
