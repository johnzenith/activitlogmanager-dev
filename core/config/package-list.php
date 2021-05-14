<?php
// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package     Activity Log Manager
 * @subpackage  Supported Package List
 * @since       Version 1.0.0
 */

/**
 * List supported plugin packages
 * 
 * @since 1.0.0
 */
function __alm_get_package_list()
{
    return [
        'package_free'       => 'FreePackage',
        'package_standard'   => 'StandardPackage',
        'package_pro'        => 'ProPackage',
        'package_business'   => 'BusinessPackage',
        'package_enterprise' => 'EnterprisePackage',
        'package_lifetime'   => 'LifetimePackage',
    ];
}