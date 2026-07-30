<?php
defined( 'ABSPATH' ) || exit;

// Theme settings options
$opt                = get_option( 'docy_opt', [] );
$preloader_quotes   = ! empty( $opt['preloader_quotes'] ) ? $opt['preloader_quotes'] : '';
$preloader_pages    = ! empty( $opt['preloader_pages'] ) ? $opt['preloader_pages'] : '';
$preloader_page_ids = ! empty( $opt['preloader_page_ids'] ) ? $opt['preloader_page_ids'] : '';

if ( 'specific_pages' === $preloader_pages ) {
    $preloader_pages = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) $preloader_page_ids ) ) ) );
    if ( ! in_array( get_the_ID(), $preloader_pages, true ) ) {
        return;
    }
}

wp_enqueue_script('preloader');
?>

<div id="preloader">
    <div id="ctn-preloader" class="ctn-preloader">
        <div class="round_spinner">
            <div class="spinner"></div>
            <?php 
            if ( !empty($opt['preloader_logo']['url']) ) : 
                ?>
                <div class="text">
                    <?php
                    printf(
                        '<img src="%s" alt="%s">',
                        esc_url( $opt['preloader_logo']['url'] ),
                        esc_attr( $opt['logo_title'] ?? '' )
                    );

                    if ( !empty($opt['logo_title']) ) :
                        ?>
                        <h4> <?php echo esc_html($opt['logo_title']) ?> </h4>
                        <?php                    
                    endif;
                    ?>
                </div>
                <?php
            endif;
            ?>
        </div>
        <?php 
        echo sprintf( '<%s class="%s">%s</%s>', 'h2', 'head', esc_html( docy_opt( 'preloader_title' ) ), 'h2' );
        
        if ( ! empty( $preloader_quotes ) && is_array( $preloader_quotes ) ) : 
            $preloader_quote    = $preloader_quotes[ wp_rand( 0, count( $preloader_quotes ) - 1 ) ]; 
            $quotes             = $preloader_quote['pre-quote'] ?? '';            
            echo wp_kses_post( wpautop( $quotes ) );            
        endif;
        ?>
    </div>
</div>