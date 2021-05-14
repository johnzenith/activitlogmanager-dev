<?php
namespace ALM\Controllers\Audit\Events\Handlers\Network;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Super Admin Event Handlers
 * @since   1.0.0
 */

trait SuperAdminEvents
{
    /**
     * Fires before granting super admin privilege to a user
     */
    public function grant_super_admin_event( $object_id )
    {
        $event_hook_info = $this->getEventHookInfo('user', __METHOD__);
        if ( !$event_hook_info ) return;

        $user       = $this->User->getUserData($object_id, true);
        $event_slug = $event_hook_info['slug'];

        $user->get_role_caps();

        $roles = $this->parseValueForDb($user->roles);

        $this->maybe_trigger_failed_events[$event_slug] = [
            'method'      => __METHOD__,
            'event_args'  => compact('object_id', 'roles'),
            'event_group' => 'user',
        ];
    }

    /**
     * Fires after granting super admin privilege to a user
     */
    public function granted_super_admin_event( $object_id )
    {
        // Clear the 'grant_super_admin' event
        $this->clearFailedEventData('user', 'grant_super_admin');

        $role = 'super_admin';
        $user = $this->User->getUserData($object_id, true);

        $user->get_role_caps();

        $user_roles = $user->roles;

        if (!in_array($role, $user_roles, true))
            $user_roles[] = $role;

        $role_new      = $this->parseValueForDb($user_roles);

        $prev_roles    = empty($user_roles) ? '' : array_diff($user_roles, [$role]);
        $role_previous = $this->parseValueForDb($prev_roles);

        $no_role_found = 'None';
        if ('' === $role_previous) {
            $role_previous = $no_role_found;
        }

        $this->setupUserEventArgs(compact('object_id', 'role_previous', 'role_new'));
        $this->LogActiveEvent('user', __METHOD__);
    }

    /**
     * Fires before revoking super admin privilege from a user
     */
    public function revoke_super_admin_event( $object_id )
    {
        $event_hook_info = $this->getEventHookInfo('user', __METHOD__);
        if (!$event_hook_info) return;

        $user       = $this->User->getUserData($object_id, true);
        $event_slug = $event_hook_info['slug'];

        $user->get_role_caps();

        $roles = $this->parseValueForDb($user->roles);

        $this->maybe_trigger_failed_events[$event_slug] = [
            'method'      => __METHOD__,
            'event_args'  => compact('object_id', 'roles'),
            'event_group' => 'user',
        ];
    }

    /**
     * Fires after revoking super admin privilege from a user
     */
    public function revoked_super_admin_event( $object_id )
    {
        // Clear the 'grant_super_admin' event
        $this->clearFailedEventData('user', 'revoke_super_admin');

        $role = 'super_admin';
        $user = $this->User->getUserData($object_id, true);

        $user->get_role_caps();

        $user_roles = $user->roles;

        // Make sure the removed role is not in the new user roles array
        if (in_array($role, $user_roles, true)) {
            $all_roles = array_flip($user_roles);
            unset($all_roles[$role]);

            $user_roles = $all_roles;
        }

        $role_new      = $this->parseValueForDb($user_roles);

        $prev_roles    = array_merge($user_roles, [$role]);
        $role_previous = $this->parseValueForDb($prev_roles);

        $no_role_found = 'None';
        if ('' === $role_new) {
            $role_new = $no_role_found;
        }

        $this->setupUserEventArgs(compact('object_id', 'role_previous', 'role_new'));
        $this->LogActiveEvent('user', __METHOD__);
    }
}
