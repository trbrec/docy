<div class="st-dsd-wrap">
    <header class="dt_dsb_header">
        <div class="st-dsb-header-inner">
            <h2><?php esc_html_e( 'Welcome to Docy!', 'docy' ); ?></h2>
            <p><?php esc_html_e( 'Verify Your Purchase of Docy WordPress Theme', 'docy' ) ?></p>
        </div><!-- /.dsd-header-inner -->
    </header>

    <div class="st-row">
        <div class="st-col st-col-6">
            <?php include_once( get_template_directory() . '/inc/admin/features.php' ); ?>
        </div><!-- /.col -->

        <div class="st-col st-col-6">
            <?php include_once( get_template_directory() . '/inc/admin/registration.php' ); ?>
        </div><!-- /.col -->
    </div><!-- /.row -->
</div><!-- /.dsd-wrap -->
