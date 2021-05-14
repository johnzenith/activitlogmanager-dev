<?php
/**
 * @package   Activity Log Manager
 * @version   1.0
 * @author    ViewPact
 * @copyright 2021 ViewPact Team 
 * @license   GPL-2.0+ [http://www.gnu.org/licenses/gpl-2.0.txt]
 * @link      https://activitylogmanager.com
 *
 * Plugin Name: Activity Log Manager
 * Plugin URI:  https://activitylogmanager.com
 * Description: <strong>Never leave any site activity untraced</strong>. See what your users are doing, control who is logged in, monitor content modifications, watch file integrity status, and <strong>revert the changes you don't like!</strong>. Activity Log Manager enables you to view all recorded activities on your site and get instant notifications as activity unfolds so you can easily troubleshoot your site and prevent malicious attack before they become a security problem. To get started, install the plugin, run the setup wizard and take charge!
 * Version:     1.0
 * Author:      ViewPact Team
 * Author URI:  https://viewpact.com
 * Text Domain: activitylogmanager
 * Domain Path: /assets/languages/
 */

// Make sure this plugin file is not accessed directly
if ( !defined('ABSPATH') || !function_exists('add_action') ) {
    exit('!!!');
}

/**
 * @todo - use the 'ALM_PRO' constant to specify a pro version of the plugin 
 */

define( 'ALM_PLUGIN_FILE', __FILE__);
require_once plugin_dir_path(ALM_PLUGIN_FILE) . 'core/bootstrap.php';
