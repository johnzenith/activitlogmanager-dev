<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Super Admin Event Handlers
 * @since   1.0.0
 */

trait SuperAdminEvents
{
    /**
     * Fires before granting super admin privilege to a user
     */
    public function grant_super_admin_event( $user_id )
    {
        
    }

    /**
     * Fires after granting super admin privilege to a user
     */
    public function granted_super_admin_event( $user_id )
    {
        
    }

    /**
     * Fires before revoking super admin privilege from a user
     */
    public function revoke_super_admin_event( $user_id )
    {
        
    }

    /**
     * Fires after revoking super admin privilege from a user
     */
    public function revoked_super_admin_event( $user_id )
    {
        
    }
}
