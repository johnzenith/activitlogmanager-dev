<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Activity Log Manager
 * @subpackage  Supported Package List
 * @since       Version 1.0.0
 */

/**
 * List supported plugin packages
 */
function __alm_get_package_list()
{
    return [
        'package_free'       => 'FreePackage',
        'package_basic'      => 'BasicPackage',
        'package_pro'        => 'ProPackage',
        'package_developer'  => 'DeveloperPackage',
        'package_enterprise' => 'EnterprisePackage',
    ];
}