<?php
/**
 * Docy Register/Verify Page Template
 *
 * Modern, premium design with glassmorphism effects,
 * smooth animations, and comprehensive license management.
 *
 * @package Docy
 * @since   2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get theme data.
$theme         = wp_get_theme( 'docy' );
$theme_version = $theme->get( 'Version' );

// Unified license status across ThemeForest (Envato) and spider-themes.net (Freemius).
$license_source = function_exists( 'docy_license_source' ) ? docy_license_source() : '';
$is_verified    = ( '' !== $license_source );

// Envato (ThemeForest) details.
$purchase_code_status = get_option( 'docy_purchase_code_status', '' );
$purchase_code        = get_option( 'docy_purchase_code', '' );
$buyer_name           = get_option( 'docy_buyer', '' );

// Freemius (spider-themes.net) details.
$fs             = function_exists( 'docy_fs' ) ? docy_fs() : null;
$fs_account_url = $fs ? $fs->get_account_url() : '';
$fs_plan_name   = ( $fs && 'freemius' === $license_source ) ? $fs->get_plan_name() : '';

// Determine status class.
if ( $is_verified ) {
    $status_class = 'verified';
    $status_icon  = 'dashicons-yes-alt';
    $status_text  = esc_html__( 'License Verified', 'docy' );
} elseif ( 'invalid' === $purchase_code_status ) {
    $status_class = 'invalid';
    $status_icon  = 'dashicons-warning';
    $status_text  = esc_html__( 'License Invalid', 'docy' );
} else {
    $status_class = 'pending';
    $status_icon  = 'dashicons-clock';
    $status_text  = esc_html__( 'Awaiting Verification', 'docy' );
}

// Features unlocked with license.
$premium_features = [
    [
        'icon'        => 'dashicons-download',
        'title'       => esc_html__( 'One-Click Demo Import', 'docy' ),
        'description' => esc_html__( 'Import beautiful pre-built layouts instantly to kickstart your website design.', 'docy' ),
    ],
    [
        'icon'        => 'dashicons-plugins-checked',
        'title'       => esc_html__( 'Premium Plugins Included', 'docy' ),
        'description' => esc_html__( 'Get access to premium and exclusive plugins bundled with your purchase.', 'docy' ),
    ],
    [
        'icon'        => 'dashicons-update',
        'title'       => esc_html__( 'Automatic Updates', 'docy' ),
        'description' => esc_html__( 'Receive seamless theme updates with new features, improvements, and security patches.', 'docy' ),
    ],
    [
        'icon'        => 'dashicons-businessman',
        'title'       => esc_html__( 'Priority Support', 'docy' ),
        'description' => esc_html__( 'Get fast, dedicated support from our expert team when you need help.', 'docy' ),
    ],
];

// Quick resources.
$resources = [
    [
        'icon'        => 'dashicons-book',
        'title'       => esc_html__( 'Documentation', 'docy' ),
        'description' => esc_html__( 'Step-by-step guides and tutorials', 'docy' ),
        'url'         => 'https://helpdesk.spider-themes.net/docs/docy-wordpress-theme/',
        'color'       => 'blue',
    ],
    [
        'icon'        => 'dashicons-video-alt3',
        'title'       => esc_html__( 'Video Tutorials', 'docy' ),
        'description' => esc_html__( 'Learn with visual walkthroughs', 'docy' ),
        'url'         => 'https://www.youtube.com/playlist?list=PLeCjxMdg411XsM8AxV4My_MlU483jUlYI',
        'color'       => 'red',
    ],
    [
        'icon'        => 'dashicons-sos',
        'title'       => esc_html__( 'Support Forum', 'docy' ),
        'description' => esc_html__( 'Get help from our team', 'docy' ),
        'url'         => 'https://helpdesk.spider-themes.net/forums/forum/docy-wordpress/',
        'color'       => 'green',
    ],
];
?>

<div class="wrap docy-verify-wrap">
    <!-- Hero Section -->
    <div class="docy-verify-hero">
        <div class="docy-hero-bg"></div>
        <div class="docy-hero-content">
            <div class="docy-hero-text">
                <span class="docy-hero-badge">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e( 'License Management', 'docy' ); ?>
                </span>
                <h1 class="docy-hero-title">
                    <?php esc_html_e( 'Register Your License', 'docy' ); ?>
                </h1>
                <p class="docy-hero-description">
                    <?php esc_html_e( 'Activate your Docy theme to unlock premium features, automatic updates, and dedicated support. Bought on ThemeForest? Use your purchase code. Bought on spider-themes.net? Use your license key.', 'docy' ); ?>
                </p>
            </div>
            <div class="docy-hero-status">
                <div class="docy-status-card <?php echo esc_attr( $status_class ); ?>">
                    <span class="docy-status-icon dashicons <?php echo esc_attr( $status_icon ); ?>"></span>
                    <span class="docy-status-text"><?php echo esc_html( $status_text ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="docy-verify-grid">
        <!-- Left Column: License Form -->
        <div class="docy-verify-column docy-column-main">
            <!-- License Card -->
            <div class="docy-card docy-license-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-admin-network"></span>
                    <h2><?php esc_html_e( 'License Verification', 'docy' ); ?></h2>
                </div>
                <div class="docy-card-content">
                    <?php if ( $is_verified ) : ?>
                        <!-- Verified State -->
                        <div class="docy-license-verified">
                            <div class="docy-verified-badge">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span><?php esc_html_e( 'Your license is active and verified', 'docy' ); ?></span>
                            </div>

                            <div class="docy-license-details">
                                <?php if ( 'envato' === $license_source ) : ?>
                                    <div class="docy-detail-row">
                                        <span class="docy-detail-label"><?php esc_html_e( 'License Source', 'docy' ); ?></span>
                                        <span class="docy-detail-value"><?php esc_html_e( 'ThemeForest (Envato)', 'docy' ); ?></span>
                                    </div>
                                    <div class="docy-detail-row">
                                        <span class="docy-detail-label"><?php esc_html_e( 'Purchase Code', 'docy' ); ?></span>
                                        <span class="docy-detail-value docy-license-key">
                                            <code><?php echo esc_html( substr( $purchase_code, 0, 8 ) . '••••••••' . substr( $purchase_code, -8 ) ); ?></code>
                                            <button type="button" class="docy-copy-btn" data-copy="<?php echo esc_attr( $purchase_code ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'docy' ); ?>">
                                                <span class="dashicons dashicons-clipboard"></span>
                                            </button>
                                        </span>
                                    </div>
                                    <?php if ( $buyer_name ) : ?>
                                        <div class="docy-detail-row">
                                            <span class="docy-detail-label"><?php esc_html_e( 'Licensed To', 'docy' ); ?></span>
                                            <span class="docy-detail-value"><?php echo esc_html( $buyer_name ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <div class="docy-detail-row">
                                        <span class="docy-detail-label"><?php esc_html_e( 'License Source', 'docy' ); ?></span>
                                        <span class="docy-detail-value"><?php esc_html_e( 'spider-themes.net', 'docy' ); ?></span>
                                    </div>
                                    <?php if ( $fs_plan_name ) : ?>
                                        <div class="docy-detail-row">
                                            <span class="docy-detail-label"><?php esc_html_e( 'Plan', 'docy' ); ?></span>
                                            <span class="docy-detail-value"><?php echo esc_html( ucfirst( $fs_plan_name ) ); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="docy-detail-row">
                                    <span class="docy-detail-label"><?php esc_html_e( 'Theme Version', 'docy' ); ?></span>
                                    <span class="docy-detail-value"><?php echo esc_html( $theme_version ); ?></span>
                                </div>
                                <div class="docy-detail-row">
                                    <span class="docy-detail-label"><?php esc_html_e( 'Status', 'docy' ); ?></span>
                                    <span class="docy-detail-value docy-status-active">
                                        <span class="docy-status-dot"></span>
                                        <?php esc_html_e( 'Active', 'docy' ); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="docy-license-actions">
                                <?php if ( $is_verified ) : ?>
                                    <button type="button" class="docy-btn docy-btn-danger-outline docy-reset-license" id="docy-reset-license-btn">
                                        <span class="dashicons dashicons-dismiss"></span>
                                        <?php esc_html_e( 'Deactivate License', 'docy' ); ?>
                                    </button>
                                <?php endif; ?>
                                <?php if ( 'freemius' === $license_source && $fs_account_url ) : ?>
                                    <a href="<?php echo esc_url( $fs_account_url ); ?>" class="docy-btn docy-btn-primary" style="margin-left: 10px;">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <?php esc_html_e( 'Manage License', 'docy' ); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <!-- Hidden nonce for modal -->
                            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'docy_verify_nonce' ) ); ?>">
                        </div>
                    <?php else : ?>
                        <!-- Not Verified State: choose your platform -->
                        <div class="docy-license-form-wrapper">
                            <p class="docy-form-intro">
                                <?php esc_html_e( 'Select where you purchased Docy, then activate to unlock all premium features.', 'docy' ); ?>
                            </p>

                            <!-- Platform tabs -->
                            <div class="docy-license-tabs" role="tablist">
                                <button type="button" class="docy-license-tab active" data-tab="envato" role="tab" aria-selected="true">
                                    <span class="dashicons dashicons-cart"></span>
                                    <?php esc_html_e( 'ThemeForest', 'docy' ); ?>
                                </button>
                                <button type="button" class="docy-license-tab" data-tab="freemius" role="tab" aria-selected="false">
                                    <span class="dashicons dashicons-admin-site-alt3"></span>
                                    <?php esc_html_e( 'spider-themes.net', 'docy' ); ?>
                                </button>
                            </div>

                            <!-- Tab 1: ThemeForest purchase code -->
                            <div class="docy-license-panel active" data-panel="envato">
                                <form id="verify-docy-license-form" class="docy-verify-form">
                                    <div class="docy-form-group">
                                        <label for="docy_purchase_code"><?php esc_html_e( 'Purchase Code', 'docy' ); ?></label>
                                        <div class="docy-input-wrapper">
                                            <span class="docy-input-icon dashicons dashicons-admin-network"></span>
                                            <input
                                                type="text"
                                                id="docy_purchase_code"
                                                name="docy_purchase_code"
                                                value="<?php echo esc_attr( $purchase_code ); ?>"
                                                placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'docy_verify_nonce' ) ); ?>">

                                    <button type="submit" class="docy-btn docy-btn-primary docy-btn-full" id="toggle-license-button">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e( 'Verify Purchase Code', 'docy' ); ?>
                                        <span class="docy-verify-preloader"></span>
                                    </button>

                                    <div id="show-result" class="docy-form-result"></div>
                                </form>

                                <div class="docy-form-help">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <a href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e( 'Where can I find my purchase code?', 'docy' ); ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Tab 2: spider-themes.net license key (Freemius) -->
                            <div class="docy-license-panel" data-panel="freemius" hidden>
                                <form id="activate-docy-fs-form" class="docy-verify-form">
                                    <div class="docy-form-group">
                                        <label for="docy_license_key"><?php esc_html_e( 'License Key', 'docy' ); ?></label>
                                        <div class="docy-input-wrapper">
                                            <span class="docy-input-icon dashicons dashicons-admin-network"></span>
                                            <input
                                                type="text"
                                                id="docy_license_key"
                                                name="docy_license_key"
                                                placeholder="<?php esc_attr_e( 'Enter your license key', 'docy' ); ?>"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'docy_verify_nonce' ) ); ?>">

                                    <button type="submit" class="docy-btn docy-btn-primary docy-btn-full" id="activate-fs-button">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e( 'Activate License Key', 'docy' ); ?>
                                        <span class="docy-verify-preloader"></span>
                                    </button>

                                    <div id="fs-show-result" class="docy-form-result"></div>
                                </form>

                                <div class="docy-form-help">
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <a href="https://spider-themes.net/account/" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e( 'Where can I find my license key?', 'docy' ); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Features Card -->
            <div class="docy-card docy-features-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-star-filled"></span>
                    <h2><?php esc_html_e( 'Premium Features Included', 'docy' ); ?></h2>
                </div>
                <div class="docy-card-content">
                    <div class="docy-features-grid">
                        <?php foreach ( $premium_features as $feature ) : ?>
                            <div class="docy-feature-item <?php echo $is_verified ? 'unlocked' : 'locked'; ?>">
                                <span class="docy-feature-icon dashicons <?php echo esc_attr( $feature['icon'] ); ?>"></span>
                                <div class="docy-feature-content">
                                    <h4>
                                        <?php echo esc_html( $feature['title'] ); ?>
                                        <?php if ( $is_verified ) : ?>
                                            <span class="docy-unlocked-badge dashicons dashicons-yes"></span>
                                        <?php else : ?>
                                            <span class="docy-locked-badge dashicons dashicons-lock"></span>
                                        <?php endif; ?>
                                    </h4>
                                    <p><?php echo esc_html( $feature['description'] ); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Resources & Info -->
        <div class="docy-verify-column docy-column-sidebar">
            <!-- Quick Resources -->
            <div class="docy-card docy-resources-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-admin-links"></span>
                    <h2><?php esc_html_e( 'Quick Resources', 'docy' ); ?></h2>
                </div>
                <div class="docy-card-content">
                    <div class="docy-resources-list">
                        <?php foreach ( $resources as $resource ) : ?>
                            <a href="<?php echo esc_url( $resource['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="docy-resource-item docy-resource-<?php echo esc_attr( $resource['color'] ); ?>">
                                <span class="docy-resource-icon dashicons <?php echo esc_attr( $resource['icon'] ); ?>"></span>
                                <span class="docy-resource-content">
                                    <span class="docy-resource-title"><?php echo esc_html( $resource['title'] ); ?></span>
                                    <span class="docy-resource-desc"><?php echo esc_html( $resource['description'] ); ?></span>
                                </span>
                                <span class="docy-resource-arrow dashicons dashicons-arrow-right-alt2"></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- FAQ Card -->
            <div class="docy-card docy-faq-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-editor-help"></span>
                    <h2><?php esc_html_e( 'Common Questions', 'docy' ); ?></h2>
                </div>
                <div class="docy-card-content">
                    <div class="docy-faq-list">
                        <details class="docy-faq-item">
                            <summary>
                                <span class="docy-faq-icon dashicons dashicons-arrow-right-alt2"></span>
                                <?php esc_html_e( 'Where do I find my purchase code?', 'docy' ); ?>
                            </summary>
                            <div class="docy-faq-answer">
                                <p><?php esc_html_e( 'Log in to your ThemeForest account, go to Downloads, find Docy theme, and click "License certificate & purchase code". Your purchase code will be displayed there.', 'docy' ); ?></p>
                            </div>
                        </details>
                        <details class="docy-faq-item">
                            <summary>
                                <span class="docy-faq-icon dashicons dashicons-arrow-right-alt2"></span>
                                <?php esc_html_e( 'I bought on spider-themes.net — what do I use?', 'docy' ); ?>
                            </summary>
                            <div class="docy-faq-answer">
                                <p><?php esc_html_e( 'Use the "spider-themes.net" tab and enter your license key. You can find it in the order confirmation email or under your account on spider-themes.net. Both purchase sources unlock the exact same premium features.', 'docy' ); ?></p>
                            </div>
                        </details>
                        <details class="docy-faq-item">
                            <summary>
                                <span class="docy-faq-icon dashicons dashicons-arrow-right-alt2"></span>
                                <?php esc_html_e( 'Can I use one license on multiple sites?', 'docy' ); ?>
                            </summary>
                            <div class="docy-faq-answer">
                                <p><?php esc_html_e( 'Each Regular License is valid for a single end product (one website). For multiple sites, you need to purchase additional licenses.', 'docy' ); ?></p>
                            </div>
                        </details>
                        <details class="docy-faq-item">
                            <summary>
                                <span class="docy-faq-icon dashicons dashicons-arrow-right-alt2"></span>
                                <?php esc_html_e( 'What if verification fails?', 'docy' ); ?>
                            </summary>
                            <div class="docy-faq-answer">
                                <p><?php esc_html_e( 'Double-check your purchase code for typos. If issues persist, contact our support team with your purchase details, and we\'ll help resolve it.', 'docy' ); ?></p>
                            </div>
                        </details>
                        <details class="docy-faq-item">
                            <summary>
                                <span class="docy-faq-icon dashicons dashicons-arrow-right-alt2"></span>
                                <?php esc_html_e( 'How do I transfer my license?', 'docy' ); ?>
                            </summary>
                            <div class="docy-faq-answer">
                                <p><?php esc_html_e( 'First, deactivate the license on the current site. Then activate it on your new site using the same purchase code. Your license remains valid for the new domain.', 'docy' ); ?></p>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            <!-- Need More Help -->
            <div class="docy-card docy-help-card">
                <div class="docy-help-bg">
                    <div class="docy-help-shape docy-help-shape-1"></div>
                    <div class="docy-help-shape docy-help-shape-2"></div>
                    <div class="docy-help-shape docy-help-shape-3"></div>
                </div>
                <div class="docy-help-content">
                    <div class="docy-help-icon-wrapper">
                        <span class="docy-help-icon dashicons dashicons-format-chat"></span>
                        <span class="docy-help-icon-ring"></span>
                    </div>
                    <h3><?php esc_html_e( 'Need More Help?', 'docy' ); ?></h3>
                    <p><?php esc_html_e( 'Our dedicated support team is here to help you succeed with your documentation site.', 'docy' ); ?></p>
                    <div class="docy-help-actions">
                        <a href="https://helpdesk.spider-themes.net/forums/forum/docy-wordpress/" target="_blank" rel="noopener noreferrer" class="docy-btn docy-btn-white">
                            <span class="dashicons dashicons-sos"></span>
                            <?php esc_html_e( 'Contact Support', 'docy' ); ?>
                        </a>
                    </div>
                    <div class="docy-help-stats">
                        <div class="docy-help-stat">
                            <span class="docy-help-stat-value">&lt;24h</span>
                            <span class="docy-help-stat-label"><?php esc_html_e( 'Response Time', 'docy' ); ?></span>
                        </div>
                        <div class="docy-help-stat-divider"></div>
                        <div class="docy-help-stat">
                            <span class="docy-help-stat-value">5★</span>
                            <span class="docy-help-stat-label"><?php esc_html_e( 'Support Rating', 'docy' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="docy-verify-footer">
        <div class="docy-footer-info">
            <span class="docy-theme-info">
                <strong><?php esc_html_e( 'Docy Theme', 'docy' ); ?></strong> v<?php echo esc_html( $theme_version ); ?>
                <?php esc_html_e( 'by', 'docy' ); ?>
                <a href="https://spider-themes.net" target="_blank" rel="noopener noreferrer">Spider Themes</a>
            </span>
        </div>
        <div class="docy-footer-links">
            <a href="https://themeforest.net/item/docy-documentation-and-forum-wordpress-theme/31370838" target="_blank" rel="noopener noreferrer">
                <span class="dashicons dashicons-cart"></span>
                <?php esc_html_e( 'ThemeForest', 'docy' ); ?>
            </a>
            <a href="https://themeforest.net/item/docy-documentation-and-forum-wordpress-theme/31370838#item-description__changelog" target="_blank" rel="noopener noreferrer">
                <span class="dashicons dashicons-editor-ul"></span>
                <?php esc_html_e( 'Changelog', 'docy' ); ?>
            </a>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div id="docy-reset-modal" class="docy-modal" style="display: none;">
    <div class="docy-modal-overlay"></div>
    <div class="docy-modal-content">
        <div class="docy-modal-header">
            <span class="docy-modal-icon dashicons dashicons-warning"></span>
            <h3><?php esc_html_e( 'Deactivate License?', 'docy' ); ?></h3>
        </div>
        <div class="docy-modal-body">
            <p><?php esc_html_e( 'Are you sure you want to deactivate this license? You will lose access to:', 'docy' ); ?></p>
            <ul>
                <li><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'One-click demo imports', 'docy' ); ?></li>
                <li><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Automatic theme updates', 'docy' ); ?></li>
                <li><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Premium plugin access', 'docy' ); ?></li>
                <li><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Priority support', 'docy' ); ?></li>
            </ul>
            <p class="docy-modal-note">
                <?php esc_html_e( 'You can re-activate later using your purchase code.', 'docy' ); ?>
            </p>
        </div>
        <div class="docy-modal-footer">
            <button type="button" class="docy-btn docy-btn-secondary docy-modal-cancel">
                <?php esc_html_e( 'Cancel', 'docy' ); ?>
            </button>
            <button type="button" class="docy-btn docy-btn-danger docy-modal-confirm" id="docy-confirm-reset">
                <span class="dashicons dashicons-dismiss"></span>
                <?php esc_html_e( 'Yes, Deactivate', 'docy' ); ?>
                <span class="docy-verify-preloader"></span>
            </button>
        </div>
    </div>
</div>