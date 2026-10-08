<?php
/**
 * Docy Welcome Page Template
 *
 * Modern, premium welcome page design with glassmorphism effects,
 * smooth animations, and comprehensive theme information.
 *
 * @package Docy
 * @since   2.0.0
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Get theme data.
$theme = wp_get_theme('docy');
$theme_version = $theme->get('Version');
$wp_version = get_bloginfo('version');

// Get theme options.
$opt = get_option('docy_opt', []);

// Determine if the One Click Demo Import plugin is active. The demo importer
// lives under Appearance → Import Demo Data only when OCDI is active; otherwise
// the user must install/activate it via the TGM plugin installer first.
if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
$ocdi_active = is_plugin_active('one-click-demo-import/one-click-demo-import.php');
$import_demo_url = $ocdi_active
    ? admin_url('themes.php?page=one-click-demo-import')
    : admin_url('themes.php?page=tgmpa-install-plugins');
$import_demo_desc = $ocdi_active
    ? esc_html__('Import demo content to get started quickly.', 'docy')
    : esc_html__('Install and activate One Click Demo Import, then import a demo.', 'docy');

// Quick stats.
$docs_count = wp_count_posts('docs')->publish ?? 0;
$posts_count = wp_count_posts('post')->publish ?? 0;
$pages_count = wp_count_posts('page')->publish ?? 0;
$topics_count = post_type_exists('topic') ? (wp_count_posts('topic')->publish ?? 0) : 0;

// Setup checklist items.
$setup_items = [
    [
        'id' => 'theme_activated',
        'title' => esc_html__('Theme Activated', 'docy'),
        'description' => esc_html__('Docy theme is active and running.', 'docy'),
        'completed' => true,
        'url' => '',
    ],
    [
        'id' => 'required_plugins',
        'title' => esc_html__('Install Required Plugins', 'docy'),
        'description' => esc_html__('Install Docy Core and other required plugins.', 'docy'),
        'completed' => class_exists('DocyCore') || defined('DOCY_CORE_VERSION'),
        'url' => admin_url('themes.php?page=tgmpa-install-plugins'),
    ],
    [
        'id' => 'import_demo',
        'title' => esc_html__('Import Demo Content', 'docy'),
        'description' => $import_demo_desc,
        'completed' => (bool) get_option('docy_demo_imported', false),
        'url' => $import_demo_url,
    ],
    [
        'id' => 'theme_settings',
        'title' => esc_html__('Configure Theme Settings', 'docy'),
        'description' => esc_html__('Customize your theme appearance and options.', 'docy'),
        'completed' => !empty($opt),
        'url' => admin_url('admin.php?page=docy-options'),
    ],
    [
        'id' => 'create_docs',
        'title' => esc_html__('Create Documentation', 'docy'),
        'description' => esc_html__('Start adding your documentation articles.', 'docy'),
        'completed' => $docs_count > 0,
        'url' => admin_url('edit.php?post_type=docs'),
    ],
];

// Calculate setup progress.
$completed_count = count(array_filter($setup_items, fn($item) => $item['completed']));
$total_count = count($setup_items);
$progress = ($total_count > 0) ? round(($completed_count / $total_count) * 100) : 0;

// Quick actions.
$quick_actions = [
    [
        'icon' => 'dashicons-admin-appearance',
        'label' => esc_html__('Customize', 'docy'),
        'url' => admin_url('customize.php'),
    ],
    [
        'icon' => 'dashicons-admin-plugins',
        'label' => esc_html__('Plugins', 'docy'),
        'url' => admin_url('themes.php?page=tgmpa-install-plugins'),
    ],
    [
        'icon' => 'dashicons-admin-generic',
        'label' => esc_html__('Settings', 'docy'),
        'url' => admin_url('admin.php?page=docy-options'),
    ],
    [
        'icon' => 'dashicons-editor-help',
        'label' => esc_html__('Docs', 'docy'),
        'url' => 'https://helpdesk.spider-themes.net/docs/docy-wordpress-theme/',
        'external' => true,
    ],
    [
        'icon' => 'dashicons-format-video',
        'label' => esc_html__('Tutorials', 'docy'),
        'url' => 'https://www.youtube.com/playlist?list=PLeCjxMdg411XsM8AxV4My_MlU483jUlYI',
        'external' => true,
    ],
    [
        'icon' => 'dashicons-sos',
        'label' => esc_html__('Support', 'docy'),
        'url' => 'https://helpdesk.spider-themes.net/forums/forum/docy-wordpress/',
        'external' => true,
    ],
];

// Resources.
$resources = [
    [
        'icon' => 'dashicons-book',
        'title' => esc_html__('Documentation', 'docy'),
        'description' => esc_html__('Complete guides, tutorials, and best practices.', 'docy'),
        'url' => 'https://helpdesk.spider-themes.net/docs/docy-wordpress-theme/',
        'button' => esc_html__('Read Docs', 'docy'),
    ],
    [
        'icon' => 'dashicons-video-alt3',
        'title' => esc_html__('Video Tutorials', 'docy'),
        'description' => esc_html__('Step-by-step video guides for all features.', 'docy'),
        'url' => 'https://www.youtube.com/playlist?list=PLeCjxMdg411XsM8AxV4My_MlU483jUlYI',
        'button' => esc_html__('Watch Videos', 'docy'),
    ],
    [
        'icon' => 'dashicons-businessman',
        'title' => esc_html__('Premium Support', 'docy'),
        'description' => esc_html__('Get help from our dedicated support team.', 'docy'),
        'url' => 'https://helpdesk.spider-themes.net/forums/forum/docy-wordpress/',
        'button' => esc_html__('Get Support', 'docy'),
    ],
    [
        'icon' => 'dashicons-star-filled',
        'title' => esc_html__('Rate & Review', 'docy'),
        'description' => esc_html__('Love Docy? Share your experience with others!', 'docy'),
        'url' => 'https://themeforest.net/item/docy-documentation-and-forum-wordpress-theme/reviews/31370838',
        'button' => esc_html__('Leave Review', 'docy'),
    ],
];

// System status checks.
$php_version_required = '7.4';
$wp_version_required = '5.6';
$memory_limit = wp_convert_hr_to_bytes(WP_MEMORY_LIMIT);
$memory_required = 67108864; // 64MB in bytes.

$system_status = [
    [
        'label' => esc_html__('PHP Version', 'docy'),
        'current' => PHP_VERSION,
        'required' => $php_version_required,
        'passed' => version_compare(PHP_VERSION, $php_version_required, '>='),
    ],
    [
        'label' => esc_html__('WordPress Version', 'docy'),
        'current' => $wp_version,
        'required' => $wp_version_required,
        'passed' => version_compare($wp_version, $wp_version_required, '>='),
    ],
    [
        'label' => esc_html__('Memory Limit', 'docy'),
        'current' => WP_MEMORY_LIMIT,
        'required' => '64M',
        'passed' => $memory_limit >= $memory_required,
    ],
];
?>

<div class="wrap docy-welcome-wrap">
    <!-- Hero Section -->
    <div class="docy-welcome-hero">
        <div class="docy-hero-bg"></div>
        <div class="docy-hero-content">
            <div class="docy-hero-text">
                <span class="docy-hero-badge">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Premium Theme', 'docy'); ?>
                </span>
                <h1 class="docy-hero-title">
                    <?php esc_html_e('Welcome to Docy', 'docy'); ?>
                    <span class="docy-version-badge">v
                        <?php echo esc_html($theme_version); ?>
                    </span>
                </h1>
                <p class="docy-hero-description">
                    <?php esc_html_e('The ultimate WordPress theme for creating stunning documentation, knowledge bases, and helpdesk websites. Packed with powerful features and seamless plugin integrations.', 'docy'); ?>
                </p>
            </div>
            <div class="docy-hero-stats">
                <?php if (post_type_exists('docs')): ?>
                    <div class="docy-stat-card">
                        <span class="docy-stat-icon dashicons dashicons-media-document"></span>
                        <span class="docy-stat-value">
                            <?php echo esc_html(number_format_i18n($docs_count)); ?>
                        </span>
                        <span class="docy-stat-label">
                            <?php esc_html_e('Docs', 'docy'); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="docy-stat-card">
                    <span class="docy-stat-icon dashicons dashicons-admin-post"></span>
                    <span class="docy-stat-value">
                        <?php echo esc_html(number_format_i18n($posts_count)); ?>
                    </span>
                    <span class="docy-stat-label">
                        <?php esc_html_e('Posts', 'docy'); ?>
                    </span>
                </div>
                <div class="docy-stat-card">
                    <span class="docy-stat-icon dashicons dashicons-admin-page"></span>
                    <span class="docy-stat-value">
                        <?php echo esc_html(number_format_i18n($pages_count)); ?>
                    </span>
                    <span class="docy-stat-label">
                        <?php esc_html_e('Pages', 'docy'); ?>
                    </span>
                </div>
                <?php if (post_type_exists('topic') && $topics_count > 0): ?>
                    <div class="docy-stat-card">
                        <span class="docy-stat-icon dashicons dashicons-format-chat"></span>
                        <span class="docy-stat-value">
                            <?php echo esc_html(number_format_i18n($topics_count)); ?>
                        </span>
                        <span class="docy-stat-label">
                            <?php esc_html_e('Topics', 'docy'); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Setup Progress -->
    <?php if ($progress < 100): ?>
        <div class="docy-setup-progress">
            <div class="docy-progress-header">
                <h3>
                    <span class="dashicons dashicons-flag"></span>
                    <?php esc_html_e('Getting Started', 'docy'); ?>
                </h3>
                <span class="docy-progress-text">
                    <?php
                    /* translators: %1$d: completed steps, %2$d: total steps */
                    printf(esc_html__('%1$d of %2$d steps completed', 'docy'), $completed_count, $total_count);
                    ?>
                </span>
            </div>
            <div class="docy-progress-bar">
                <div class="docy-progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Grid -->
    <div class="docy-welcome-grid">
        <!-- Left Column: Setup & Quick Actions -->
        <div class="docy-welcome-column docy-column-main">
            <!-- Setup Checklist -->
            <div class="docy-card docy-setup-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-yes-alt"></span>
                    <h2>
                        <?php esc_html_e('Setup Checklist', 'docy'); ?>
                    </h2>
                </div>
                <div class="docy-card-content">
                    <ul class="docy-setup-list">
                        <?php foreach ($setup_items as $item): ?>
                            <li class="docy-setup-item <?php echo $item['completed'] ? 'completed' : ''; ?>">
                                <?php if (!empty($item['url']) && !$item['completed']): ?>
                                    <a href="<?php echo esc_url($item['url']); ?>" class="docy-setup-link">
                                    <?php endif; ?>

                                    <span class="docy-setup-check">
                                        <?php if ($item['completed']): ?>
                                            <span class="dashicons dashicons-yes"></span>
                                        <?php else: ?>
                                            <span class="docy-check-empty"></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="docy-setup-content">
                                        <span class="docy-setup-title">
                                            <?php echo esc_html($item['title']); ?>
                                        </span>
                                        <span class="docy-setup-desc">
                                            <?php echo esc_html($item['description']); ?>
                                        </span>
                                    </span>
                                    <?php if (!empty($item['url']) && !$item['completed']): ?>
                                        <span class="docy-setup-arrow dashicons dashicons-arrow-right-alt2"></span>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Video Tutorial -->
            <div class="docy-card docy-video-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-video-alt3"></span>
                    <h2>
                        <?php esc_html_e('Getting Started Video', 'docy'); ?>
                    </h2>
                </div>
                <div class="docy-card-content">
                    <div class="docy-video-wrapper">
                        <iframe src="https://www.youtube.com/embed/eEaDZ4d7oQU"
                            title="<?php esc_attr_e('Docy Theme Tutorial', 'docy'); ?>" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </div>
                    <a href="https://www.youtube.com/playlist?list=PLeCjxMdg411XsM8AxV4My_MlU483jUlYI" target="_blank"
                        rel="noopener noreferrer" class="docy-btn docy-btn-outline">
                        <span class="dashicons dashicons-playlist-video"></span>
                        <?php esc_html_e('View All Tutorials', 'docy'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column: Quick Actions & Resources -->
        <div class="docy-welcome-column docy-column-sidebar">
            <!-- Quick Actions -->
            <div class="docy-card docy-actions-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-superhero"></span>
                    <h2>
                        <?php esc_html_e('Quick Actions', 'docy'); ?>
                    </h2>
                </div>
                <div class="docy-card-content">
                    <div class="docy-quick-actions">
                        <?php foreach ($quick_actions as $action): ?>
                            <a href="<?php echo esc_url($action['url']); ?>" class="docy-action-item" <?php echo isset($action['external']) && $action['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <span class="docy-action-icon dashicons <?php echo esc_attr($action['icon']); ?>"></span>
                                <span class="docy-action-label">
                                    <?php echo esc_html($action['label']); ?>
                                </span>
                                <?php if (isset($action['external']) && $action['external']): ?>
                                    <span class="docy-external-badge dashicons dashicons-external"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Resources -->
            <div class="docy-card docy-resources-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-portfolio"></span>
                    <h2>
                        <?php esc_html_e('Resources', 'docy'); ?>
                    </h2>
                </div>
                <div class="docy-card-content">
                    <div class="docy-resources-list">
                        <?php foreach ($resources as $resource): ?>
                            <a href="<?php echo esc_url($resource['url']); ?>" target="_blank" rel="noopener noreferrer"
                                class="docy-resource-item">
                                <span class="docy-resource-icon dashicons <?php echo esc_attr($resource['icon']); ?>"></span>
                                <span class="docy-resource-content">
                                    <span class="docy-resource-title">
                                        <?php echo esc_html($resource['title']); ?>
                                    </span>
                                    <span class="docy-resource-desc">
                                        <?php echo esc_html($resource['description']); ?>
                                    </span>
                                </span>
                                <span class="docy-resource-arrow dashicons dashicons-arrow-right-alt2"></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="docy-card docy-system-card">
                <div class="docy-card-header">
                    <span class="docy-card-icon dashicons dashicons-dashboard"></span>
                    <h2>
                        <?php esc_html_e('System Status', 'docy'); ?>
                    </h2>
                </div>
                <div class="docy-card-content">
                    <ul class="docy-system-list">
                        <?php foreach ($system_status as $status): ?>
                            <li class="docy-system-item <?php echo $status['passed'] ? 'passed' : 'failed'; ?>">
                                <span class="docy-system-indicator">
                                    <span
                                        class="dashicons <?php echo $status['passed'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                                </span>
                                <span class="docy-system-info">
                                    <span class="docy-system-label">
                                        <?php echo esc_html($status['label']); ?>
                                    </span>
                                    <span class="docy-system-value">
                                        <?php echo esc_html($status['current']); ?>
                                        <span class="docy-system-required">
                                            <?php
                                            /* translators: %s: required version */
                                            printf(esc_html__('(Required: %s+)', 'docy'), esc_html($status['required']));
                                            ?>
                                        </span>
                                    </span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="docy-welcome-footer">
        <div class="docy-footer-info">
            <span class="docy-theme-info">
                <strong>
                    <?php esc_html_e('Docy Theme', 'docy'); ?>
                </strong> v
                <?php echo esc_html($theme_version); ?>
                <?php esc_html_e('by', 'docy'); ?>
                <a href="https://spider-themes.net" target="_blank" rel="noopener noreferrer">Spider Themes</a>
            </span>
        </div>
        <div class="docy-footer-links">
            <a href="https://themeforest.net/item/docy-documentation-and-forum-wordpress-theme/31370838#item-description__changelog"
                target="_blank" rel="noopener noreferrer">
                <span class="dashicons dashicons-editor-ul"></span>
                <?php esc_html_e('Changelog', 'docy'); ?>
            </a>
            <a href="https://spider-themes.net" target="_blank" rel="noopener noreferrer">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <?php esc_html_e('Our Website', 'docy'); ?>
            </a>
        </div>
    </div>
</div>