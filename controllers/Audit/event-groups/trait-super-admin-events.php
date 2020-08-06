<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Super Admin Events
 * @since   1.0.0
 */

trait SuperAdminEvents
{
    /**
     * Init the Super Admin Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Templates\EventList
     */
    public function initSuperAdminEvents()
    {
        $this->setupSuperAdminEvents();
    }

    /**
     * Setup the Super Admin events
     */
    protected function setupSuperAdminEvents()
    {
        $this->event_list['super_admins'] = [
            'title'       => 'Super Admin Events',
            'group'       => 'user', // object
            'description' => 'Responsible for logging all Super Admins related activities.',

            'events' => [
                /**
                 * @see /wp-includes/capabilities.php
                 * @see do_action( 'grant_super_admin', int $user_id )
                 */
                'grant_super_admin' => [
                    'title'               => 'Grant Super Admin Privilege',
                    'action'              => 'user_role_failed',
                    'event_id'            => 5001,
                    'severity'            => 'critical',
                    'error_flag'          => true,
                    'event_successor'     => ['user', 'granted_super_admin'],

                    'screen'              => [ 'admin', 'network', ],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['manage_network_options'],

                    'description' => 'Responsible for logging the initial request to <strong>Grant Super Admin Privilege</strong> to a user. This event is used to check whether the <em>Super Admin Privilege</em> will be granted to the user. If the request is successful, then it will be replaced with the <em>Granted Super Admin</em> event. This event is very important so that even when the request failed and Super Admin Privilege was not granted, you will still be notified.',

                    /**
                     * Translation arguments
                     */
                    '_translate' => [
                        'roles' => [
                            'plural'   => 'roles',
                            'singular' => 'role',
                        ]
                    ],

                    'message' => [
                        '_main' => 'Tried to grant the Super Admin Privilege to a user, but the request was unsuccessful.',

                        '_space_start'             => '',
                        'roles'                    => ['roles'],
                        '_space_end'               => '',

                        'user_id'                  => ['object_id'],
                        'user_login'               => ['user_login'],
                        'display_name'             => ['display_name'],
                        'first_name'               => ['first_name'],
                        'last_name'                => ['last_name'],
                        'user_email'               => ['user_email'],
                        'log_counter'              => '',
                        'is_user_owner_of_account' => ['is_user_owner_of_account'],
                        'profile_url'              => ['profile_url'],
                        'user_primary_blog'        => ['primary_blog'],
                        'primary_blog_name'        => ['primary_blog_name'],
                        'source_domain'            => ['source_domain'],
                    ],

                    'event_handler' => [
                        'hook'     => 'action',
                        'num_args' => 1,
                        'priority' => 10,
                        'callback' => null,
                    ],
                ],

                /**
                 * @see /wp-includes/capabilities.php
                 * @see do_action( 'granted_super_admin', int $user_id )
                 */
                'granted_super_admin' => [
                    'title'               => 'Granted Super Admin Privilege',
                    'action'              => 'user_role_modified',
                    'event_id'            => 5002,
                    'severity'            => 'critical',

                    'screen'              => [ 'admin', 'network', ],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['manage_network_options'],

                    'description' => 'Responsible for checking whether the Super Admin Privilege was granted successfully to a user. If the Super Admin Privilege was granted successfully, then it will replace the initial <em>Grant Super Admin</em> event.',

                    /**
                     * Translation arguments
                     */
                    '_translate' => [
                        'previous_role' => [
                            'plural'   => 'all_previous_roles',
                            'singular' => 'previous_role',
                        ],
                        'new_role' => [
                            'plural'   => 'all_new_roles',
                            'singular' => 'new_role',
                        ],
                    ],

                    'message' => [
                        '_main'                    => 'Granted the Super Admin Privilege to a user.',

                        '_space_start'             => '',
                        'previous_role'            => ['role_previous'],
                        'new_role'                 => ['role_new'],
                        '_space_end'               => '',

                        'user_id'                  => ['object_id'],
                        'user_login'               => ['user_login'],
                        'display_name'             => ['display_name'],
                        'first_name'               => ['first_name'],
                        'last_name'                => ['last_name'],
                        'user_email'               => ['user_email'],
                        'log_counter'              => '',
                        'is_user_owner_of_account' => ['is_user_owner_of_account'],
                        'profile_url'              => ['profile_url'],
                        'user_primary_blog'        => ['primary_blog'],
                        'primary_blog_name'        => ['primary_blog_name'],
                        'source_domain'            => ['source_domain'],
                    ]
                ],

                /**
                 * @see /wp-includes/capabilities.php
                 * @see do_action( 'revoke_super_admin', int $user_id )
                 */
                'revoke_super_admin' => [
                    'title'               => 'Revoked Super Admin Privilege',
                    'action'              => 'user_role_failed',
                    'event_id'            => 5003,
                    'severity'            => 'critical',
                    'error_flag'          => true,
                    'event_successor'     => ['user', 'revoked_super_admin'],

                    'screen'              => [ 'admin', 'network', ],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['manage_network_options'],

                    'description' =>
                    'Responsible for logging the initial request to <strong>Revoke Super Admin Privilege</strong> from a user. This event is used to check whether the <em>Super Admin Privilege</em> will be revoked from the user. If the request is successful, then it will be replaced with the <em>Revoked Super Admin</em> event. This event is very important so that even when the request failed and Super Admin Privilege was not revoked, you will still be notified.',

                    /**
                     * Translation arguments
                     */
                    '_translate' => [
                        'roles' => [
                            'plural'   => 'roles',
                            'singular' => 'role',
                        ]
                    ],

                    'message' => [
                        '_main' => 'Tried to revoke the Super Admin Privilege from a user, but the request was unsuccessful.',

                        '_space_start'             => '',
                        'roles'                    => ['roles'],
                        '_space_end'               => '',

                        'user_id'                  => ['object_id'],
                        'user_login'               => ['user_login'],
                        'display_name'             => ['display_name'],
                        'first_name'               => ['first_name'],
                        'last_name'                => ['last_name'],
                        'user_email'               => ['user_email'],
                        'log_counter'              => '',
                        'is_user_owner_of_account' => ['is_user_owner_of_account'],
                        'profile_url'              => ['profile_url'],
                        'user_primary_blog'        => ['primary_blog'],
                        'primary_blog_name'        => ['primary_blog_name'],
                        'source_domain'            => ['source_domain'],
                    ]
                ],

                /**
                 * @see /wp-includes/capabilities.php
                 * @see do_action( 'revoked_super_admin', int $user_id )
                 */
                'revoked_super_admin' => [
                    'title'               => 'Revoked Super Admin Privilege',
                    'action'              => 'user_role_modified',
                    'event_id'            => 5004,
                    'severity'            => 'critical',

                    'screen'              => [ 'admin', 'network', ],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['manage_network_options'],

                    'description' =>
                    'Responsible for checking whether the Super Admin Privilege was revoked successfully from a user. If the Super Admin Privilege was revoked successfully, then this event will replace the initial <em>Revoke Super Admin Privilege</em> event.',

                    /**
                     * Translation arguments
                     */
                    '_translate' => [
                        'previous_role' => [
                            'plural'   => 'all_previous_roles',
                            'singular' => 'previous_role',
                        ],
                        'new_role' => [
                            'plural'   => 'all_new_roles',
                            'singular' => 'new_role',
                        ],
                    ],

                    'message' => [
                        '_main'                    => 'Revoked the Super Admin Privilege from a user.',

                        '_space_start'             => '',
                        'previous_role'            => ['role_previous'],
                        'new_role'                 => ['role_new'],
                        '_space_end'               => '',

                        'user_id'                  => ['object_id'],
                        'user_login'               => ['user_login'],
                        'display_name'             => ['display_name'],
                        'first_name'               => ['first_name'],
                        'last_name'                => ['last_name'],
                        'user_email'               => ['user_email'],
                        'log_counter'              => '',
                        'is_user_owner_of_account' => ['is_user_owner_of_account'],
                        'profile_url'              => ['profile_url'],
                        'user_primary_blog'        => ['primary_blog'],
                        'primary_blog_name'        => ['primary_blog_name'],
                        'source_domain'            => ['source_domain'],
                    ]
                ],
            ]
        ];
    }
    
}
