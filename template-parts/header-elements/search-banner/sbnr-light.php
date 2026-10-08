<?php
$opt            = get_option( 'docy_opt', [] );
$light_bg       = docy_opt( 'sbnr_light_bg', [] );
$bg_image       = ! empty( $light_bg['background-image']['url'] ) ? 'background-image: url(' . esc_url( $light_bg['background-image']['url'] ) . ');' : '';
$bg_color       = ! empty( $light_bg['background-color'] ) ? 'background-color: ' . esc_attr( $light_bg['background-color'] ) . ';' : '';
$bg_style_value = trim( $bg_image . ' ' . $bg_color );
$bg_style_value = '' !== $bg_style_value ? $bg_style_value : 'background-image: url(' . esc_url( DOCY_DIR_IMG . '/banner-bg.png' ) . ');';

$breadcrumb_container = is_singular( 'docs' ) || is_post_type_archive( 'docs' ) ? 'custom_container' : '';
$placeholder          = ! empty( $opt['banner_search_placeholder'] ) ? $opt['banner_search_placeholder'] : '';
$is_focus_search      = $opt['is_focus_search'] ?? '';
$is_focus_search      = $is_focus_search == '1' ? 'focused-form' : '';
?>

<section class="banner-bg">
    <div class="doc_banner_area search-banner-light banner_creative1 sbnr-global bg-<?php echo esc_attr( docy_opt( 'search_banner_bg' ) ); ?>" style="<?php echo esc_attr( $bg_style_value ); ?>">
		<?php if ( docy_opt( 'is_banner_overlay' ) == '1' ) : ?>
            <div class="overlay-bg"
				<?php
				// Blur effect
				echo docy_opt( 'is_sbnr_blur' ) == '1'
					? 'style="backdrop-filter: blur(' . docy_opt( 'sbnr_blur_density', '10' ) . 'px); '
					: 'style="';

				// Gradient overlay color
				if ( docy_opt( 'sbnr_overlay_color' ) ) {
					$gradient_from = docy_opt( 'sbnr_overlay_color' )['gradient_bg_color-from'];
					$gradient_to   = docy_opt( 'sbnr_overlay_color' )['gradient_bg_color-to'];
					echo 'background: linear-gradient(to bottom, ' . esc_attr( $gradient_from ) . ', ' . esc_attr( $gradient_to ) . ');';
				}
				echo '"';
				?>>
            </div>
		<?php endif; ?>

        <div class="container">
            <div class="doc_banner_content">
				<?php
				// Title and subtitle
				include('title.php');

				if ( docy_opt( 'sbnr_search_fieldset', '1', 'is_sbnr_search' ) == '1' ) : ?>
                    <form id="ajax-search-form" action="<?php echo esc_url( site_url() ); ?>" class="header_search_form <?php echo esc_attr( $is_focus_search ) ?>">

                        <div class="header_search_form_info">                            
                            <div class="form-group">
                                <div class="input-wrapper">
                                    <label for="searchInput">
                                        <i class="icon_search"></i>
                                    </label>
                                    <input type="search" name="s" id="searchInput"  placeholder="<?php echo esc_attr( $placeholder ) ?>" autocomplete="off" value="<?php echo esc_attr( get_search_query() ); ?>">
                                    <?php 
                                    include( 'search-spinner.php' );
                                    if ( defined( 'ICL_LANGUAGE_CODE' ) ) : ?>
									<input type="hidden" name="lang" value="<?php echo esc_attr( ICL_LANGUAGE_CODE ); ?>"/>
									    <?php 
                                    endif; 
                                    ?>
                                </div>
                            </div>
                        </div>

						<?php
						// include( 'ajax-search-results.php' );
						include( 'keywords.php' );
						include( 'ajax-search-results.php' );
						?>
                    </form>
				    <?php 
                endif; 
                ?>

            </div>
        </div>
    </div>
</section>

<?php
include( 'breadcrumb.php' );
include( 'featured-video.php' );