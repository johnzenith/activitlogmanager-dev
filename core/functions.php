<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package    Activity Log Manager
 * @subpackage Core Functions
 * @since 	   1.0.0
 */

/**
 * Register a new event group or add an event to an existing event group.
 * 
 * This should be called within the {@see alm/event/group/register action hook}, 
 * but it is not a requirement, except if you want to access the default event 
 * groups.
 * 
 * @since 1.0.0
 * 
 * @see \ALM\Controllers\Audit\Traits::__setupEventList()
 * @see \ALM\Controllers\Audit\Traits::registerEventGroups()
 */
function alm_register_event_group( array $args = [] )
{
    if ( empty( $args ) ) 
        return;

    static $auditor = null;
    
    if ( is_null( $auditor ) ) 
        $auditor = apply_filters( 'alm/controller/get/auditor', '' );

    $auditor->addEventGroups( $args );
}