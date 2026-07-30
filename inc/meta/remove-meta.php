<?php
/**
 * Remove specified ACF fields and field groups if they conflict with fields created by Codestar.
 * 
 * This function checks if certain Advanced Custom Fields (ACF) fields or field groups exist based on their unique IDs.
 * If a field or field group in ACF shares the same ID as one defined in Codestar, the function deletes the ACF version 
 * to avoid redundancy or potential conflicts within the WordPress environment.
 *
 * $group_ids : An array of ACF group IDs to delete, representing different groups of fields that may overlap with Codestar.
 * 
 * This function is hooked into the 'init' action to run during the WordPress initialization phase.
 */
function docy_remove_acf_fields_if_exists_in_codestar() {

    // Remove group fields by grup ids
    $group_ids = [
        'group_5eac41e5865c2', // Options :: Page
        'group_5e975bbf223f3', // Options :: Doc
        'group_5f8ad4615c8b5', // Video Options
        'group_60607941281c8', // Video
        'group_5f319c4fe34bc', // Sign up / Sign in Page Options
        'group_5f4585b114170', // Page Settings
        'group_613707f8e618c', // Options :: Post
        'group_5ead4ea38b97c', // Action Button
        'group_5eac41061f4f2', // Banner
        'group_5f2cd61a20a16', // footer
        'group_5eac5bbccc311'  // header
    ];

    // Loop through each group ID and delete it if it exists
    foreach ( $group_ids as $group_id ) {
        if ( function_exists( 'acf_get_field_group' ) && acf_get_field_group( $group_id ) ) {
            acf_delete_field_group( $group_id );
        }
    }
}
add_action('init', 'docy_remove_acf_fields_if_exists_in_codestar');