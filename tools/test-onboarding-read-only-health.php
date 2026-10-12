<?php
require __DIR__ . '/onboarding-read-only-health.php';
function health_check( bool $condition ): void { if ( ! $condition ) throw new RuntimeException( 'Health retry contract failed.' ); }
$calls = 0; $waits = array();
$result = trb_onboarding_read_only_health( 'crm_onboarding_health', static function( array $payload ) use ( &$calls ): array {
    health_check( $payload === array( 'action' => 'crm_onboarding_health' ) );
    if ( ++$calls === 1 ) throw new RuntimeException( 'Timed out', 0 );
    return array( 'success' => true, 'version' => '2026.2' );
}, static function( int $seconds ) use ( &$waits ): void { $waits[] = $seconds; } );
health_check( $calls === 2 && $waits === array( 2 ) && $result['version'] === '2026.2' );
foreach ( array( 401, 403, 404, 200 ) as $status ) {
    $calls = 0;
    try {
        trb_onboarding_read_only_health( 'crm_onboarding_health', static function() use ( &$calls, $status ): array { $calls++; throw new RuntimeException( 'Refused', $status ); }, static function(): void { throw new LogicException( 'Unexpected retry' ); } );
        throw new LogicException( 'Unconfirmed response accepted' );
    } catch ( RuntimeException $error ) { health_check( $calls === 1 && $error->getCode() === $status ); }
}
$calls = 0; $waits = array();
try {
    trb_onboarding_read_only_health( 'crm_onboarding_drive_health', static function() use ( &$calls ): array { $calls++; throw new RuntimeException( 'Unavailable', 503 ); }, static function( int $seconds ) use ( &$waits ): void { $waits[] = $seconds; } );
    throw new LogicException( 'Persistent failure accepted' );
} catch ( RuntimeException $error ) { health_check( $calls === 3 && $waits === array( 2, 4 ) && $error->getCode() === 503 ); }
foreach ( array( 'crm_onboarding_signature', 'crm_onboarding_document', 'crm_onboarding_signed_archive', 'crm_candidate_mail', 'activate_account' ) as $action ) {
    $calls = 0;
    try {
        trb_onboarding_read_only_health( $action, static function() use ( &$calls ): array { $calls++; return array(); } );
        throw new LogicException( 'Mutation permitted' );
    } catch ( InvalidArgumentException $error ) { health_check( $calls === 0 ); }
}
$calls = 0;
$result = trb_onboarding_read_only_health( 'crm_candidate_mail_health', static function() use ( &$calls ): array { $calls++; return array( 'success' => true, 'transport' => 'invalid' ); } );
health_check( $calls === 1 && $result['transport'] === 'invalid' );
echo "Health checks retry only transient transport failures, stop after three attempts and reject mutation commands.\n";
