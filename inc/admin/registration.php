<?php
$register = new Docy_register_theme;
$purchase_code_status = trim( get_option( 'docy_purchase_code_status' ) );
if ( $purchase_code_status == 'valid' ) {
    $license_status = 'success';
} elseif ( $purchase_code_status == 'invalid' ) {
    $license_status = 'failed';
} elseif ( $purchase_code_status == '' ) {
    $license_status = '';
}

?>
<div class="st-dsb-box st-theme-register-box">

	<div class="st-box-head <?php echo esc_attr($license_status) ?>">
        <?php $register->messages(); ?>
	</div><!-- /.dsd-box-head -->

	<?php $register->form(); ?>
	
	<div class="st-box-foot">
		<a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e( 'Where can find purchase code!', 'docy' ); ?>
        </a>
	</div><!-- /.dsd-box-foot -->

</div><!-- /.dsd-register-box -->