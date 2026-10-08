<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Google fonts.
 *
 * @return string Google fonts URL for the theme.
 */
function docy_fonts_url(): string
{
	$fonts_url = '';
	$fonts     = [];
	$subsets   = '';

	/* Body font */
	if ( 'off' !== 'on' ) {
		$fonts[] = "Roboto:300,400,500,600,700";
	}

	if ( $fonts ) {
		$fonts_url = add_query_arg( [
			'family'  => urlencode( implode( '|', $fonts ) ),
			'subset'  => urlencode( $subsets ),
			'display' => 'swap',
		], "https://fonts.googleapis.com/css" );
	}

	return $fonts_url;
}

/**
 * Add preconnect for Google Fonts.
 *
 * @param array  $urls          URLs to print for resource hints.
 * @param string $relation_type The relation type the URLs are printed for.
 * @return array URLs to print for resource hints.
 */
function docy_resource_hints( $urls, $relation_type ) {
	if ( wp_style_is( 'docy-fonts', 'queue' ) && 'preconnect' === $relation_type ) {
		$urls[] = [
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin',
		];
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'docy_resource_hints', 10, 2 );

/**
 * Enqueue site scripts and styles
 */
function docy_scripts() {
	$opt = get_option( 'docy_opt' );

	/**
	 * Registering site's scripts and styles
	 */
	wp_register_style( 'docy-fonts', docy_fonts_url(), [], null );
	wp_register_style( 'nice-select', DOCY_DIR_VEND . '/niceselectpicker/nice-select.css' );
	wp_register_style( 'docy-font-size', DOCY_DIR_VEND . '/font-size/css/rvfs.css' );
	wp_register_style( 'bootstrap-select', DOCY_DIR_VEND . '/bootstrap/css/bootstrap-select.min.css' );
	wp_register_style( 'magnific-popup', DOCY_DIR_VEND . '/magnify-pop/magnific-popup.css' );
	wp_register_style( 'docy-blog-single', DOCY_DIR_CSS . '/blog-single.css' );
	wp_register_style( 'docy-dark-mode', DOCY_DIR_CSS . '/dark-mode.css' );

	wp_enqueue_style( 'docy-fonts' );
	wp_enqueue_style( 'bootstrap', DOCY_DIR_VEND . '/bootstrap/css/bootstrap.min.css' );
	wp_enqueue_style( 'elegant-icon', DOCY_DIR_VEND . '/elegant-icon/style.css' );
	wp_enqueue_style( 'font-awesome-6', DOCY_DIR_VEND . '/font-awesome/css/all.min.css' );
	wp_enqueue_style( 'animate', DOCY_DIR_VEND . '/animation/animate.css' );
	wp_enqueue_style( 'docy-essential', DOCY_DIR_CSS . '/essential-style.css', [], DOCY_VERSION );
	wp_enqueue_style( 'docy-main', DOCY_DIR_CSS . '/style-main.css', [], DOCY_VERSION );
	
	// wooCommerce stylesheets and scripts
	if ( class_exists( 'WooCommerce' ) ) {
		// Enqueue WooCommerce JavaScript for all WooCommerce pages
		if ( is_shop() || is_singular('product') || is_cart() || is_checkout() || is_account_page() || is_product_taxonomy() ) {
			wp_enqueue_script( 'docy-woocommerce', DOCY_DIR_JS . '/woocommerce.js', [ 'jquery' ], DOCY_VERSION, true );
			wp_localize_script( 'docy-woocommerce', 'docy_buy_now_params', [
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'checkout_url' => wc_get_checkout_url(),
				'cart_url'     => wc_get_cart_url(),
				'nonce'        => wp_create_nonce( 'docy-buy-now-nonce' )
			] );
		}
		
		// Enqueue WooCommerce styles
		if ( is_shop() || is_singular('product') || is_cart() || is_product_taxonomy() ) {
			wp_enqueue_style( 'docy-shop', DOCY_DIR_CSS . '/shop.css' );
			wp_enqueue_style( 'nice-select' );
			wp_enqueue_script( 'nice-select' );
		}
		if ( is_checkout() ) {
			wp_enqueue_style( 'docy-checkout', DOCY_DIR_CSS . '/checkout.css' );
		}
		if ( is_account_page() ) {
			wp_enqueue_style( 'docy-myaccount', DOCY_DIR_CSS . '/myaccount.css' );
		}
	}

	wp_enqueue_style( 'docy-root', get_stylesheet_uri() );

	if ( is_singular( 'post' ) ) {
		wp_enqueue_style( 'docy-responsive', DOCY_DIR_CSS . '/responsive.css', [ 'docy-blog-single' ] );

		// Article rating feature - only load if enabled in settings.
		$is_rating_enabled = isset( $opt['is_article_rating'] ) ? $opt['is_article_rating'] : '1';
		if ( '1' === $is_rating_enabled ) {
			$thank_you_text = isset( $opt['article_rating_thank_you'] ) && ! empty( $opt['article_rating_thank_you'] )
				? $opt['article_rating_thank_you']
				: esc_html__( 'Thank you for rating this article!', 'docy' );

			wp_enqueue_script( 'docy-article-rating', DOCY_DIR_JS . '/article-rating.js', [ 'jquery' ], DOCY_VERSION, true );
			wp_localize_script(
				'docy-article-rating',
				'docy_rating_params',
				[
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'docy_article_rating_nonce' ),
					'thank_you_text'  => esc_html( $thank_you_text ),
					'submitting_text' => esc_html__( 'Submitting your rating...', 'docy' ),
				]
			);
		}
	} else {
		wp_enqueue_style( 'docy-responsive', DOCY_DIR_CSS . '/responsive.css' );
	}

	if ( is_rtl() ) {
		wp_enqueue_style( 'docy-rtl', DOCY_DIR_CSS . '/rtl.css' );
	}

	$css_output = [
		'menu_item_color'            => [
			'color' => '.navbar .menu > .nav-item > .nav-link',
		],
		'banner_background_color'    => [
			'background-color' => '.titlebar',
		],
		'banner_text_color'          => [
			'color' => '.breadcrumb_text h1, .breadcrumb_text p',
		],
		'footer_pt__px'              => [
			'padding-top' => '.footer_area'
		],
		'footer_pr__px'              => [
			'padding-right' => '.footer_area'
		],
		'footer_pb__px'              => [
			'padding-bottom' => '.footer_area'
		],
		'footer_pl__px'              => [
			'padding-left' => '.footer_area'
		],
		// Footer background color
		'footer_background_color'    => [
			'background-color' => '.doc_footer_top'
		],

		/**
		 * Action Button
		 */
		'btn_background_color'       => [
			'background' => '.right-nav .nav_btn.tp_btn'
		],
		'btn_border_radius'          => [
			'border-radius' => '.right-nav .nav_btn.tp_btn'
		],
		'btn_text_color'             => [
			'color' => '.right-nav .nav_btn.tp_btn, .dark_menu .right-nav .nav_btn.tp_btn'
		],
		'btn_border_color'           => [
			'border-color' => '.right-nav .nav_btn.tp_btn'
		],
		'hover_btn_background_color' => [
			'background' => '.right-nav .nav_btn.tp_btn:hover'
		],
		'hover_btn_text_color'       => [
			'color' => '.right-nav .nav_btn.tp_btn:hover'
		],
		'hover_btn_border_color'     => [
			'border-color' => '.right-nav .nav_btn.tp_btn:hover'
		],

		/**
		 * Page Settings
		 */
		'page_padding_top__px'       => [
			'padding-top' => '.page_wrapper'
		],
		'page_padding_right__px'     => [
			'padding-right' => '.page_wrapper'
		],
		'page_padding_bottom__px'    => [
			'padding-bottom' => '.page_wrapper'
		],
		'page_padding_left__px'      => [
			'padding-left' => '.page_wrapper'
		],
	];

	Docy_helper()->dynamic_css_render( 'docy-root', $css_output );

	/**
	 * Register and enqueue theme script files
	 */
	wp_register_script( 'preloader', DOCY_DIR_JS . '/pre-loader.js', [ 'jquery' ], '1.0', true );
	wp_register_script( 'nice-select', DOCY_DIR_VEND . '/niceselectpicker/jquery.nice-select.min.js', [ 'jquery' ], '1.0', true );
	wp_register_script( 'docy-font-size', DOCY_DIR_VEND . '/font-size/js/rv-jquery-fontsize-2.0.3.js', [ 'jquery' ], '2.0.3', true );
	wp_register_script( 'bootstrap-select', DOCY_DIR_VEND . '/bootstrap/js/bootstrap-select.min.js', [ 'jquery', 'bootstrap' ], '2.0.3', true );
	wp_register_script( 'bootstrap-toc', DOCY_DIR_VEND . '/bootstrap/js/bootstrap-toc.min.js', [ 'jquery', 'bootstrap' ], '1.0.1', true );
	wp_register_script( 'magnific-popup', DOCY_DIR_VEND . '/magnify-pop/jquery.magnific-popup.min.js', [ 'jquery' ], '1.1.0', true );
	wp_register_script( 'printThis', DOCY_DIR_JS . '/printThis.js', [ 'jquery' ], '1.0.0', true );
	wp_register_script( 'docy-dark-mode', DOCY_DIR_JS . '/dark-mode.js', [ 'jquery' ], '1.0.0', true );


	if ( is_page_template( 'page-onepage.php' ) ) {
		wp_enqueue_style( 'eazydocs-frontend' );
	}

	/**
	 * JavaScripts
	 */
	wp_enqueue_script( 'bootstrap', DOCY_DIR_VEND . '/bootstrap/js/bootstrap.bundle.min.js', [ 'jquery' ], '5.1.3', true );
	wp_enqueue_script( 'wow', DOCY_DIR_VEND . '/wow/wow.min.js', [ 'jquery' ], '1.1.3', true );

	$banner_type = docy_meta('banner_type');

	if ( '1' === docy_toc('post') ) {
		wp_enqueue_script( 'anchor', DOCY_DIR_VEND . '/anchor/anchor.js', [ 'jquery' ], '5.1.3', true );
	}

	if ( is_singular( 'post' ) && 'toc' === $banner_type ) {
		wp_enqueue_script( 'docy-main', DOCY_DIR_JS . '/main.js', [ 'jquery' ], '1.0.0', true );
	} else {
		wp_enqueue_script( 'docy-main', DOCY_DIR_JS . '/main.js', [ 'jquery', 'masonry', 'imagesloaded' ], '1.0.0', true );
	}

	// Localize the script with new data
	$ajax_url              = admin_url( 'admin-ajax.php' );
	$wpml_current_language = apply_filters( 'wpml_current_language', null );
	if ( ! empty( $wpml_current_language ) ) {
		$ajax_url = add_query_arg( 'wpml_lang', $wpml_current_language, $ajax_url );
	}

	$is_doc_ajax         = $opt['is_doc_ajax'] ?? '1';
	$is_focus_by_slash   = $opt['is_focus_by_slash'] ?? '';
	
    wp_enqueue_script( 'docy-ajax-search-form', DOCY_DIR_JS . '/ajax-search-form.js', [ 'jquery' ], '1.0.0', true );
    wp_localize_script('docy-ajax-search-form', 'docy_local_object',
        [
            'ajaxurl'           => $ajax_url,
            'DOCY_DIR_CSS'      => DOCY_DIR_CSS,
            'is_doc_ajax'       => $is_doc_ajax,
            'is_focus_by_slash' => $is_focus_by_slash,
			'post_types' 		 => docy_get_modified_post_type_slugs(),			
			'post_type_modified' => docy_get_modified_post_type_slugs(true),
			'ajax_nonce' 		=> wp_create_nonce('ajax_search_nonce'),
			'get_docs_slug' 	=> docy_get_docs_slug()
        ]
    );

	global $wp_query;
	$localized_settings = [
		'ajax_url'     => admin_url( 'admin-ajax.php' ),
		'docy_nonce'   => wp_create_nonce( 'docy-nonce' ),
		'docy_parent'  => get_queried_object_id(),
		'current_page' => get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1,
		'max_page'     => $wp_query->max_num_pages,
		'first_page'   => get_pagenum_link( 1 )
	];

	wp_localize_script( 'jquery', 'DocyForum', $localized_settings );

	/**
	 * Inline Scripts
	 */
	$dynamic_js = '';

	if ( ! empty( $opt['custom_js'] ) ) {
		$dynamic_js .= $opt['custom_js'];
	}

	if ( ! empty( $opt['os_options'][0]['title'] ) && is_singular( 'docs' ) ) {
		foreach ( $opt['os_options'] as $option ) {
			$dynamic_js .= '
            if( jQuery("#mySelect").val() == "' . esc_js( sanitize_title( $option['title'] ) ) . '" ) {
                jQuery(".' . esc_js( sanitize_title( $option['title'] ) ) . '").show();
            } else {
                jQuery(".' . esc_js( sanitize_title( $option['title'] ) ) . '").hide();
            }
            jQuery("#mySelect").change(function() {
                if( jQuery("#mySelect").val() == "' . esc_js( sanitize_title( $option['title'] ) ) . '" ) {
                    jQuery(".' . esc_js( sanitize_title( $option['title'] ) ) . '").show();
                } else {
                    jQuery(".' . esc_js( sanitize_title( $option['title'] ) ) . '").hide();
                }
            })';
		}
	}

	wp_add_inline_script( 'docy-main', $dynamic_js );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

	/**
	 * AJAX commenting and frontend comment editing.
	 *
	 * Loaded only on singular views where comments are open (blog posts, docs,
	 * pages) and never on WooCommerce product review pages, which use their own
	 * template and submission flow.
	 */
	if ( is_singular() && comments_open() && ! is_singular( 'product' ) && function_exists( 'docy_is_ajax_comments_enabled' ) && docy_is_ajax_comments_enabled() ) {
		wp_enqueue_script( 'docy-comments', DOCY_DIR_JS . '/comments.js', [ 'jquery' ], DOCY_VERSION, true );

		wp_localize_script(
			'docy-comments',
			'docy_comments_params',
			[
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'edit_nonce'      => wp_create_nonce( 'docy_edit_comment' ),
				'editing_enabled' => docy_is_comment_editing_enabled() ? 1 : 0,
				'is_logged_in'    => is_user_logged_in() ? 1 : 0,
				'i18n'            => [
					'posting'        => esc_html__( 'Posting…', 'docy' ),
					'saving'         => esc_html__( 'Saving…', 'docy' ),
					'save'           => esc_html__( 'Save', 'docy' ),
					'cancel'         => esc_html__( 'Cancel', 'docy' ),
					'empty_comment'  => esc_html__( 'Please enter a comment before submitting.', 'docy' ),
					'generic_error'  => esc_html__( 'Something went wrong. Please try again.', 'docy' ),
				],
			]
		);
	}
}

add_action( 'wp_enqueue_scripts', 'docy_scripts' );

// Admin dashboard style and scripts
add_action( 'admin_enqueue_scripts', function () {
	global $pagenow;

	// Load welcome notice styles only on themes.php
	if ( 'themes.php' === $pagenow ) {
		wp_enqueue_style( 'docy-welcome-notice', DOCY_DIR_CSS . '/admin-welcome-notice.css', [], DOCY_VERSION );
	}

	// Load admin styles on specific admin pages
	$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$admin_style_pages = [ 'docy_template', 'docy_verify' ];
	
	if ( in_array( $current_page, $admin_style_pages, true ) ) {
		wp_enqueue_style( 'docy-admin', DOCY_DIR_CSS . '/admin.css', [], DOCY_VERSION );
	}

	// Load settings page styles only on the theme settings page.
	if ( 'docy-options' === $current_page ) {
		wp_enqueue_style( 'docy-admin-settings', DOCY_DIR_CSS . '/admin-settings.css', [], DOCY_VERSION );
	}

	if ( 'admin.php' === $pagenow ) {
		wp_enqueue_style( 'elegant-icon', DOCY_DIR_VEND . '/elegant-icon/style.css' );
	}

    // Admin scripts
	wp_enqueue_script( 'docy-admin', DOCY_DIR_JS . '/admin.js', [ 'jquery' ], '1.0.0', true );

	// localize the script with new data
	wp_localize_script( 'docy-admin', 'docy_admin_object',
		[
			'ajaxurl' => admin_url( 'admin-ajax.php' )
		]
	);
} );

function docy_block_editor_styles() {
    wp_enqueue_style( 'docy-block-editor-styles', get_template_directory_uri() . '/style-editor.css' );
}

add_action('enqueue_block_editor_assets', 'docy_block_editor_styles');