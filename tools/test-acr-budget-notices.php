<?php
define( 'ABSPATH', __DIR__ . '/' );
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function number_format_i18n( $number, $decimals = 0 ) { return number_format( $number, $decimals, ',', '.' ); }
require dirname( __DIR__ ) . '/inc/trb-resource-monitor.php';
function check_budget( $condition, $message ) { if ( ! $condition ) throw new RuntimeException( $message ); }
foreach ( array( 0, 2.5, 3.765, 4 ) as $spent ) {
	check_budget( ! trb_resource_acr_budget_alert_required( $spent, 5 ), 'No consumption alert up to and including 80%.' );
	trb_resource_acr_thresholds( $spent, 5 ); // Must return before accessing the queue/database.
}
check_budget( trb_resource_acr_budget_alert_required( 4.0001, 5 ), 'Alert immediately above 80%.' );
check_budget( trb_resource_acr_budget_alert_required( 5.5, 5 ), 'Over-budget still alerts.' );
check_budget( ! trb_resource_acr_budget_alert_required( 1, 0 ), 'No division by zero.' );
$notice = trb_resource_acr_budget_notice( 4.1, 5 );
foreach ( array( '0,90 USD', '5,00 USD', '82,0%', 'prudenziale', 'non è verificato' ) as $expected ) check_budget( false !== strpos( $notice, $expected ), $expected );
check_budget( false !== strpos( trb_resource_acr_budget_notice( 6, 5 ), '0,00 USD' ), 'Residual must not become negative.' );
echo "ACR budget notification boundaries passed.\n";
