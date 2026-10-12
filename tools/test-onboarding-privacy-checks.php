<?php
require __DIR__ . '/onboarding-privacy-checks.php';
$template = file_get_contents( dirname( __DIR__ ) . '/inc/trb-candidate-onboarding-page.php' );
if ( ! trb_onboarding_instant_payment_copy_present( $template ) ) throw new RuntimeException( 'The current reviewed portal payment notice is rejected.' );
$checks = 1;
foreach ( array( '', '<p>PayPal oppure carta di credito o debito per pagamenti online.</p>', '<p id="payment-methods">Usa il bonifico.</p>', '<p id="payment-methods">PayPal e carta di credito per pagamenti online.</p>', '<p id="payment-methods">PayPal e carta di credito o debito per pagamenti online o bonifico.</p>' ) as $html ) {
    if ( trb_onboarding_instant_payment_copy_present( $html ) ) throw new RuntimeException( 'Incomplete or obsolete payment notice accepted.' );
    $checks++;
}
if ( ! trb_onboarding_instant_payment_copy_present( '<p id="payment-methods">PayPal oppure una <strong>carta</strong> di credito o debito per i pagamenti online.</p>' ) ) throw new RuntimeException( 'Visible notice markup must not invalidate the check.' );
echo 'PASS ' . ( $checks + 1 ) . " payment notice checks against the real portal template, incomplete copy and former bank-transfer instructions.\n";
