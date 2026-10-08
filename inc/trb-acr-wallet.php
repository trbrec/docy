<?php
/** Provider wallet: current-bill.data.balance, never amount or credit. */
if ( ! defined( 'ABSPATH' ) ) exit;

function trb_acr_wallet_parse( $bill, $now ) {
	if ( ! is_array( $bill ) || ! isset( $bill['balance'] ) || ! is_numeric( $bill['balance'] ) || ! is_finite( (float) $bill['balance'] ) ) return null;
	return array( 'balance' => (float) $bill['balance'], 'currency' => 'USD', 'checked_at' => (int) $now, 'source' => 'ACRCloud current-bill.data.balance' );
}

function trb_acr_wallet_classify( $snapshot, $attempt, $now, $threshold = 10 ) {
	if ( ! is_array( $snapshot ) || ! isset( $snapshot['balance'], $snapshot['checked_at'] ) || ! is_numeric( $snapshot['balance'] ) || ! is_finite( (float) $snapshot['balance'] ) ) return 'unknown';
	$age = (int) $now - (int) $snapshot['checked_at'];
	if ( $age < 0 || $age > 7200 || ( empty( $attempt['ok'] ) && (int) ( $attempt['checked_at'] ?? 0 ) >= (int) $snapshot['checked_at'] ) ) return 'stale';
	if ( (float) $snapshot['balance'] <= 0 ) return 'empty';
	return (float) $snapshot['balance'] < max( 0, (float) $threshold ) ? 'low' : 'healthy';
}

function trb_acr_wallet_state() {
	return trb_acr_wallet_classify( get_option( 'trb_acr_wallet_snapshot', array() ), get_option( 'trb_acr_wallet_attempt', array() ), time(), trb_resource_settings()['acr_wallet_low_usd'] );
}

function trb_acr_wallet_message() {
	$snapshot = get_option( 'trb_acr_wallet_snapshot', array() );
	return 'Saldo disponibile comunicato da ACRCloud: ' . number_format_i18n( $snapshot['balance'] ?? 0, 2 ) . ' USD. Verificato il ' . wp_date( 'd/m/Y H:i', $snapshot['checked_at'] ?? time() ) . '. Il saldo è distinto dalla fattura corrente e dal limite interno mensile del portale.';
}

function trb_acr_wallet_record( $bill ) {
	$snapshot = trb_acr_wallet_parse( $bill, time() );
	if ( ! $snapshot ) return;
	update_option( 'trb_acr_wallet_snapshot', $snapshot, false );
	update_option( 'trb_acr_wallet_attempt', array( 'checked_at' => time(), 'ok' => true ), false );
	$state = trb_acr_wallet_state();
	$previous = get_option( 'trb_acr_wallet_alert_state', 'healthy' );
	if ( in_array( $state, array( 'low', 'empty' ), true ) && $state !== $previous ) {
		$episode = (int) get_option( 'trb_acr_wallet_alert_episode', 0 ) + 1;
		update_option( 'trb_acr_wallet_alert_episode', $episode, false );
		trb_resource_queue_email( 'acr-wallet-' . $state . '-' . $episode, 'empty' === $state ? 'ACRCloud: credito disponibile esaurito' : 'ACRCloud: credito disponibile basso', trb_acr_wallet_message(), 'empty' === $state );
	}
	update_option( 'trb_acr_wallet_alert_state', $state, false );
}

function trb_acr_wallet_notice() {
	$snapshot = get_option( 'trb_acr_wallet_snapshot', array() );
	$state = trb_acr_wallet_state();
	if ( 'unknown' === $state ) return 'Saldo non sincronizzato. Nessun esaurimento viene dedotto dalle stime interne.';
	return ( 'stale' === $state ? 'Ultimo saldo noto, aggiornamento non confermato: ' : 'Saldo disponibile: ' ) . number_format_i18n( $snapshot['balance'], 2 ) . ' USD · lettura ACRCloud del ' . wp_date( 'd/m/Y H:i', $snapshot['checked_at'] ) . '. Aggiornamento automatico ogni ora.';
}

add_action( 'init', static function() {
	if ( ! wp_next_scheduled( 'trb_acr_wallet_refresh' ) ) wp_schedule_event( time() + 60, 'hourly', 'trb_acr_wallet_refresh' );
} );
add_action( 'trb_acr_wallet_refresh', 'trb_resource_acr_current_bill' );
