<?php
/** CLI installation checks only. Never retry a document, signature or payment command. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }

function trb_onboarding_read_only_health( string $action, callable $transport, ?callable $wait = null ): array {
    if ( ! in_array( $action, array( 'crm_onboarding_drive_health', 'crm_candidate_mail_health', 'crm_onboarding_health' ), true ) ) {
        throw new InvalidArgumentException( 'Only read-only health commands may be retried.' );
    }
    $wait = $wait ?? static function( int $seconds ): void { sleep( $seconds ); };
    for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
        try {
            return $transport( array( 'action' => $action ) );
        } catch ( RuntimeException $error ) {
            if ( 3 === $attempt || ! in_array( (int) $error->getCode(), array( 0, 408, 425, 429, 500, 502, 503, 504 ), true ) ) {
                throw $error;
            }
            $wait( 2 * $attempt );
        }
    }
    throw new RuntimeException( 'Read-only health check not confirmed.' );
}
