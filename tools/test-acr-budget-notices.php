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
foreach ( array( '4,1250 USD', 'Nessun limite interno', 'non misurano il credito', 'non sospendono le analisi' ) as $expected ) check_budget( false !== strpos( $notice, $expected ), $expected );
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

// Captured, non-sensitive provider values verified against the console on 2026-09-11.
$wallet = trb_acr_wallet_parse( array( 'amount' => 0.86, 'credit' => 4.63, 'balance' => 45.37 ), 10000 );
check_budget( 45.37 === $wallet['balance'], 'Use provider balance, never billed amount or credit.' );
foreach ( array( array(), array( 'amount'=>0, 'credit'=>0 ), array( 'balance'=>null ), array( 'balance'=>'unknown' ), array( 'balance'=>INF ), array( 'balance'=>NAN ) ) as $invalid ) {
	check_budget( null === trb_acr_wallet_parse( $invalid, 10000 ), 'Missing or invalid wallet is not zero credit.' );
}
$ok = array( 'ok'=>true, 'checked_at'=>10000 );
check_budget( 'healthy' === trb_acr_wallet_classify( $wallet, $ok, 10001, 10 ), 'Available credit must not alert.' );
check_budget( 'unknown' === trb_acr_wallet_classify( array(), $ok, 10001 ), 'No balance is unknown.' );
check_budget( 'stale' === trb_acr_wallet_classify( $wallet, $ok, 17201 ), 'Expired balances must not alert.' );
check_budget( 'stale' === trb_acr_wallet_classify( $wallet, array( 'ok'=>false, 'checked_at'=>10001 ), 10002 ), 'HTTP failure invalidates freshness, not the saved balance.' );
foreach ( array( array(0,'empty'), array(-1,'empty'), array(9.99,'low'), array(10,'healthy') ) as $case ) {
	$s = trb_acr_wallet_parse( array( 'balance'=>$case[0] ), 10000 );
	check_budget( $case[1] === trb_acr_wallet_classify( $s, $ok, 10001, 10 ), 'Provider wallet threshold boundary.' );
}
echo "Provider wallet balance and freshness passed.\n";

// No settings/database/notification access is permitted by the retired cap.
foreach (array(0, 0.068, 5, 50, 1000000) as $maximum) {
 check_budget(true === trb_resource_acr_budget_guard($maximum, 12329), 'Internal estimates cannot block paid analysis.');
}
echo "Retired internal cap cannot block release analysis.\n";
