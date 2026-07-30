<?php
namespace docy\inc;

class Admin {

    private string $menu_slug = 'docy_template';

    /**
     * Snapshot of the theme's submenu items, captured before Freemius may wipe them.
     *
     * @var array
     */
    private array $submenu_snapshot = [];

    public function __construct() {

        // Register the parent menu early (before priority 10) so the Codestar
        // framework can attach the "docy-options" settings submenu to an
        // existing parent. Registering the parent later than CSF breaks the
        // submenu's page-hook registration and blocks access to the settings page.
        add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );
        add_action( 'admin_head', array( $this, 'custom_admin_css' ) );

        // Keep Freemius' activation state honest when a spider-themes.net license
        // is active. Runs before Freemius builds its menu (WP_FS__LOWEST_PRIORITY)
        // so the Account page is registered under the Docy menu. See method docs.
        add_action( 'admin_menu', array( $this, 'sync_freemius_license_state' ), 0 );

        // Show the Freemius "Account" submenu only while a spider-themes.net license
        // is active. By default Freemius shows it for any registered user, so it would
        // linger after the license is deactivated. See method docs.
        if ( function_exists( 'docy_fs' ) ) {
            \docy_fs()->add_filter( 'is_submenu_visible', array( $this, 'filter_account_submenu_visibility' ), 10, 2 );
        }

        /**
         * Guard the theme's submenu against Freemius' activation-mode menu takeover.
         *
         * On an unregistered premium install Freemius runs in "activation mode"
         * (forced by its `require_license_activation` storage flag — anonymous mode
         * does NOT switch it off). At admin_menu priority WP_FS__LOWEST_PRIORITY it
         * calls remove_all_submenu_items(), emptying $submenu['docy_template']. That
         * empties the parent's submenu, so WordPress can no longer resolve the parent
         * of pages like docy_verify / docy-options, the page-hook lookup mismatches,
         * and user_can_access_admin_page() denies access ("Sorry, you are not allowed
         * to access this page.").
         *
         * The theme ships its own unified Register/Verify page for both ThemeForest
         * and spider-themes.net licenses, so we keep our menu intact: snapshot the
         * fully-built submenu just after it is registered (priority 11, after CSF at
         * 10), then restore it just after Freemius runs. Owner-agnostic (captures the
         * theme's items and CSF's option tabs alike) and a no-op on registered sites,
         * where Freemius leaves the submenu populated.
         */
        add_action( 'admin_menu', array( $this, 'snapshot_submenu' ), 11 );

        $restore_priority = ( defined( 'WP_FS__LOWEST_PRIORITY' ) ? WP_FS__LOWEST_PRIORITY : 999999999 ) + 1;
        add_action( 'admin_menu', array( $this, 'restore_submenu' ), $restore_priority );
    }

    /**
     * Clear Freemius' stale "require license activation" flag when a spider-themes.net
     * license is already active, so the Account page appears under the Docy menu.
     *
     * When a Freemius (spider-themes.net) license is active, license activation is no
     * longer required — but Freemius may leave its `require_license_activation` storage
     * flag set to true. While that flag is true, is_activation_mode() stays true even
     * for a paying site, so Freemius (a) never collects/embeds its Account submenu and
     * (b) wipes the Docy menu to show the opt-in screen instead. Clearing the flag (the
     * same way Freemius clears it internally) lets Freemius exit activation mode, add
     * its Account page under `docy_template`, and leave the theme's own submenu intact.
     *
     * Only acts when actually licensed via Freemius, so unlicensed and Envato-only
     * installs are unaffected. Runs on admin_menu priority 0 — before Freemius builds
     * the menu at WP_FS__LOWEST_PRIORITY — and is a cheap no-op once the flag is clear.
     *
     * @return void
     */
    public function sync_freemius_license_state() {
        if ( ! function_exists( 'docy_fs' ) ) {
            return;
        }

        $fs = \docy_fs();

        if ( ! ( $fs->is_paying() || $fs->can_use_premium_code() ) ) {
            return;
        }

        $storage = $fs->get_storage();

        if ( isset( $storage->require_license_activation ) && true === $storage->require_license_activation ) {
            $storage->require_license_activation = false;
        }
    }

    /**
     * Show the Freemius "Account" submenu only while a spider-themes.net license is active.
     *
     * Freemius shows its Account page for any registered user by default, so it would
     * remain under the Docy menu after the license is deactivated (Freemius keeps the
     * user registered on deactivation). Hiding it whenever the site is not licensed via
     * Freemius keeps the menu in step with the license state. Other Freemius submenu
     * items are left untouched.
     *
     * @param bool   $is_visible Whether Freemius intends to show the item.
     * @param string $menu_id    The submenu item id (e.g. 'account', 'pricing').
     * @return bool
     */
    public function filter_account_submenu_visibility( $is_visible, $menu_id ) {
        if ( 'account' !== $menu_id || ! function_exists( 'docy_fs' ) ) {
            return $is_visible;
        }

        $fs = \docy_fs();

        return ( $fs->is_paying() || $fs->can_use_premium_code() );
    }

    /**
     * Capture the theme's submenu items before Freemius can remove them.
     *
     * @return void
     */
    public function snapshot_submenu() {
        global $submenu;

        if ( ! empty( $submenu[ $this->menu_slug ] ) ) {
            $this->submenu_snapshot = $submenu[ $this->menu_slug ];
        }
    }

    /**
     * Restore the theme's submenu items if Freemius wiped them in activation mode.
     *
     * @return void
     */
    public function restore_submenu() {
        global $submenu;

        if ( ! empty( $this->submenu_snapshot ) && empty( $submenu[ $this->menu_slug ] ) ) {
            $submenu[ $this->menu_slug ] = $this->submenu_snapshot;
        }
    }

    public function admin_menu() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $menu_slug  = $this->menu_slug;
        $capability = 'manage_options';

        // Add a top-level menu page
        add_menu_page(
                esc_html__( 'Docy', 'docy' ),           // Page title
                esc_html__( 'Docy', 'docy' ),     // Menu title
                $capability,                     // Capability
                $menu_slug,                      // Menu slug
                array( $this, 'docy_welcome_page' ),    // Callback function
                'dashicons-feedback',                 // Dashicons icon
                2                                    // Position
        );


        // Add the Dashboard Submenu.
        add_submenu_page(
                $menu_slug,
                esc_html__( 'Docy', 'docy' ),
                esc_html__( 'Welcome', 'docy' ),
                $capability,
                $menu_slug,
                array( $this, 'docy_welcome_page' )
        );

        // Add the Register/Verify Submenu
        add_submenu_page(
                $menu_slug,
                esc_html__( 'Register/Verify', 'docy' ),
                esc_html__( 'Register/Verify', 'docy' ),
                $capability,
                'docy_verify',
                [ $this, 'register_verify_page' ]
        );

        // Add the Header Template Submenu
        if ( post_type_exists( 'docy_header' ) ) {
            add_submenu_page(
                    $menu_slug,
                    esc_html__( 'Headers', 'docy' ),
                    esc_html__( 'Headers', 'docy' ),
                    $capability,
                    'edit.php?post_type=docy_header',
                    false
            );
        }

        // Add the Footers Template Submenu
        if ( post_type_exists( 'docy_footer' ) ) {
            add_submenu_page(
                    $menu_slug,
                    esc_html__( 'Footers', 'docy' ),
                    esc_html__( 'Footers', 'docy' ),
                    $capability,
                    'edit.php?post_type=docy_footer',
                    false
            );
        }

    }

    /**
     * Render the Docy Welcome page.
     *
     * Includes a comprehensive template with hero section, setup wizard,
     * quick actions, resources, and system status information.
     *
     * @since 2.0.0
     * @return void
     */
    public function docy_welcome_page() {
        include_once get_template_directory() . '/inc/admin/templates/welcome-page.php';
    }

    public function register_verify_page() {
        // Include the modern register-verify page template.
        include_once get_template_directory() . '/inc/admin/templates/register-verify-page.php';
    }

    public function custom_admin_css() {
        // Inject custom CSS to hide the top-level menu item and admin notices on the welcome page
        echo '<style>
            .toplevel_page_docy_template .notice, div.fs-notice.success, div.fs-notice.updated {
                display: none !important;
            }
        </style>';
    }

}

new Admin();