<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Activity Log Manager
 * @subpackage  Meta Info
 * @since       Version 1.0.0
 */

/**
 * Get the Activity Log Manager plugin site url
 */
function __alm_plugin_site_url( $path = '' )
{
    return esc_url_raw( 'https://www.activitylogmanager.com' . $path );
}

/**
 * Get plugin support team url
 */
function __alm_support_team_url()
{
    return __alm_plugin_site_url( '/support' );
}