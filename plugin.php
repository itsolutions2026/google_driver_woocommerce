<?php

/**
 * Plugin Name: Integrate Google Drive (PRO)
 * Plugin URI:  https://softlabbd.com/integrate-google-drive
 * Description: Seamless Google Drive integration for WordPress, allowing you to embed, share, play, and download documents and media files directly from Google Drive to your WordPress site.
 * Version:     1.3.3
 * Update URI: https://api.freemius.com
 * Author:      SoftLab
 * Author URI:  https://softlabbd.com/
 * Text Domain: integrate-google-drive
 * Domain Path: /languages/
 *
 * @fs_premium_only /assets/vendor/slick, /assets/js/statistics.js, /assets/vendor/chart.js, /includes/integrations/templates, /includes/integrations/woocommerce, /includes/integrations/tutor.php, /includes/integrations/class-fluentforms.php, /includes/integrations/class-ninjaforms.php, /includes/integrations/class-edd.php, /includes/integrations/class-acf.php, /includes/integrations/class-gravityforms.php, /includes/integrations/class-wpforms.php,  /includes/integrations/class-formidableforms.php
 */
// don't call the file directly
if (!defined('ABSPATH')) {
    wp_die(__('You can\'t access this page', 'integrate-google-drive'));
}

/** define constants */
define('IGD_VERSION', '1.3.3');
define('IGD_FILE', __FILE__);
define('IGD_PATH', dirname(IGD_FILE));
define('IGD_INCLUDES', IGD_PATH . '/includes');
define('IGD_URL', plugins_url('', IGD_FILE));
define('IGD_ASSETS', IGD_URL . '/assets');
define('IGD_CACHE_DIR', WP_CONTENT_DIR . '/integrate-google-drive-cache');
define('IGD_CACHE_URL', content_url() . '/integrate-google-drive-cache');
//check min-php version

if (version_compare(PHP_VERSION, '7.0', '<=')) {
    deactivate_plugins(plugin_basename(IGD_FILE));
    $notice = sprintf('Unsupported PHP version. %1$s requires WordPress version %2$s or greater. Please update your PHP to the latest version.', '<strong>Google Drive to WordPress</strong>', '<strong>5.6.0</strong>');
    wp_die($notice);
}

// check min-wp version

if (!version_compare(get_bloginfo('version'), '5.0', '>=')) {
    deactivate_plugins(plugin_basename(IGD_FILE));
    $notice = sprintf('Unsupported WordPress version. %1$s requires WordPress version %2$s or greater. Please update your WordPress to the latest version.', '<strong>Google Drive to WordPress</strong>', '<strong>5.0</strong>');
    wp_die($notice);
}

//Include the base plugin file.
include_once IGD_INCLUDES . '/base.php';