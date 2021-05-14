<?php
// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package     Activity Log Manager
 * @subpackage  Meta Info
 * @since       Version 1.0.0
 */

/**
 * Get the Activity Log Manager plugin site url
 * 
 * @since 1.0.0
 */
function __alm_plugin_site_url( $path = '' )
{
    return esc_url_raw( 'https://www.activitylogmanager.com' . $path );
}

/**
 * Get plugin support team url
 * 
 * @since 1.0.0
 */
function __alm_support_team_url()
{
    return __alm_plugin_site_url( '/support' );
}