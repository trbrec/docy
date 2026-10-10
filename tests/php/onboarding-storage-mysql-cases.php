<?php
/** Native registration binding and activation failures; no real contract. */
update_option( 'trb_candidate_onboarding_enabled', true );
$qaRegister = array( 'action' => 'register', 'password' => 'Synthetic-password-for-QA-only-32', 'repeat_password' => 'Synthetic-password-for-QA-only-32' );
$wpdb->query( "CREATE TRIGGER qa_reject_registration BEFORE INSERT ON {$wpdb->usermeta} FOR EACH ROW BEGIN IF NEW.meta_key='_trb_onboarding_practice' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic account binding rejection'; END IF; END" );
try {
    qa_check( qa_mu_request( $qaRegister, '/onboarding/public' )['status'] === 503, 'A partially bound registration was acknowledged.' );
    qa_check( !$wpdb->get_var( "SELECT ID FROM {$wpdb->users} WHERE user_email='qa-onboarding@example.invalid'" ), 'Failed onboarding registration left an orphan account that prevents retry.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_registration' ); }
$qaRegistered = qa_mu_request( $qaRegister, '/onboarding/public' );
qa_check( $qaRegistered['status'] === 409, 'Unconfirmed CRM activation was presented as a completed registration.' );
$qaOnboardingUser = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} WHERE user_email='qa-onboarding@example.invalid'" );
qa_check( $qaOnboardingUser > 0, 'Registration could not recover after binding storage was restored.' );
qa_mu_request( $qaRegister, '/onboarding/public' );
qa_check( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email='qa-onboarding@example.invalid'" ) === 1, 'Registration retry created a duplicate account.' );
$qaActivation = array( 'action' => 'activate_account', 'portal_user_id' => $qaOnboardingUser, 'practice_id' => str_repeat( 'c', 32 ), 'email' => 'qa-onboarding@example.invalid', 'owner_approved' => true, 'signed' => true, 'signed_pcloud_file_id' => 'synthetic-archive-proof', 'group_code' => 'TRB', 'contract_number' => 'TRB-QA-NONVALIDO', 'contract_term' => '10/10/2026 - INFINITO', 'details' => array( 'billing' => array( 'country' => 'Tunisia', 'city' => 'Tunisi', 'street' => 'Via fittizia', 'phone' => '+21620123456' ) ) );
clean_user_cache( $qaOnboardingUser ); wp_cache_delete( $qaOnboardingUser, 'user_meta' );
$qaBeforeActivation = get_user_meta( $qaOnboardingUser );
$wpdb->query( "CREATE TRIGGER qa_reject_activation BEFORE INSERT ON {$wpdb->usermeta} FOR EACH ROW BEGIN IF NEW.meta_key='_trb_artist_contract_term' AND NEW.user_id={$qaOnboardingUser} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic activation rejection'; END IF; END" );
try {
    qa_check( qa_mu_request( $qaActivation, '/onboarding/private' )['status'] === 503, 'Rejected onboarding activation was acknowledged.' );
    clean_user_cache( $qaOnboardingUser ); wp_cache_delete( $qaOnboardingUser, 'user_meta' );
    qa_check( get_user_meta( $qaOnboardingUser ) === $qaBeforeActivation, 'Failed activation partially changed the synthetic account.' );
} finally { $wpdb->query( 'DROP TRIGGER qa_reject_activation' ); }
$qaActivatedAccount = qa_mu_request( $qaActivation, '/onboarding/private' );
qa_check( true === ( $qaActivatedAccount['data']['activated'] ?? false ), 'Onboarding activation could not recover after the SQL failure.' );
qa_check( true === ( qa_mu_request( $qaActivation, '/onboarding/private' )['data']['activated'] ?? false ), 'Identical onboarding activation retry failed.' );
clean_user_cache( $qaOnboardingUser ); wp_cache_delete( $qaOnboardingUser, 'user_meta' );
qa_check( get_user_meta( $qaOnboardingUser, '_trb_onboarding_stage', true ) === 'active', 'Onboarding did not persist its final activation stage.' );
update_option( 'trb_candidate_onboarding_enabled', false );
