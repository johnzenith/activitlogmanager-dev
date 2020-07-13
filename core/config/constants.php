<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Activity Log Manager
 * @subpackage  Plugin Constants
 * @since       Version 1.0.0
 */

define( 'ALM_PLUGIN_PRODUCT_NAME', 'Activity Log Manager' );
define( 'ALM_PACKAGE',             'package_enterprise' );

define( 'ALM_VERSION',             '1.0.0' );
define( 'ALM_DB_VERSION',          '1.0.0' );
define( 'ALM_MINIMUM_WP_VERSION',  '5.0' );
define( 'ALM_MINIMUM_PHP_VERSION', '5.6' );

define( 'ALM_DEFAULT_RUNNING_MODE',       'normal' );
define( 'ALM_DEFAULT_RUNNING_MODE_STATE', 'flexible' );

define( 'ALM_OPTION_NAME',          'alm_options' );
define( 'ALM_GLOBAL_OPTION_NAME',   'alm_global_options' );
define( 'ALM_UPDATE_SETTINGS_SLUG', 'alm-update-settings' );

define( 'ALM_CUSTOM_MODE_LIMIT', 3 );

$plugin_file = wp_normalize_path( ALM_PLUGIN_FILE );
define( 'ALM_PLUGIN_DIR', 		plugin_dir_path( $plugin_file ) );
define( 'ALM_PLUGIN_URL', 		plugin_dir_url( $plugin_file ) );
define( 'ALM_PLUGIN_BASENAME', plugin_basename( $plugin_file ) );

define( 'ALM_CORE_DIR',        ALM_PLUGIN_DIR . 'core/' );
define( 'ALM_USER_DIR',        ALM_PLUGIN_DIR . 'user/' );
define( 'ALM_INC_DIR',         ALM_PLUGIN_DIR . 'inc/' );
define( 'ALM_ADMIN_DIR',       ALM_PLUGIN_DIR . 'admin/' );
define( 'ALM_VIEWS_DIR',       ALM_PLUGIN_DIR . 'views/' );
define( 'ALM_MODELS_DIR',      ALM_PLUGIN_DIR . 'models/' );
define( 'ALM_VENDOR_DIR',      ALM_PLUGIN_DIR . 'vendor-lib/' );
define( 'ALM_DOMAIN_DIR',      ALM_PLUGIN_DIR . 'languages/' );
define( 'ALM_PACKAGES_DIR',    ALM_PLUGIN_DIR . 'packages/' );
define( 'ALM_CORE_CONFIG_DIR', ALM_CORE_DIR   . 'config/' );
define( 'ALM_CONTROLLERS_DIR', ALM_PLUGIN_DIR . 'controllers/' );

// define( 'ALM_LIST_TABLES_DIR', ALM_CONTROLLERS_DIR . 'list-tables/' );

define( 'ALM_ASSETS_DIR',      ALM_PLUGIN_DIR . 'assets/' );
define( 'ALM_ASSETS_URL',      ALM_PLUGIN_URL . 'assets/' );

// Time constants
define( 'ALM_MONTH_IN_SECONDS', WEEK_IN_SECONDS * 4.345 );
define( 'ALM_YEAR_IN_SECONDS',  WEEK_IN_SECONDS * 52.143 );

unset( $plugin_file );