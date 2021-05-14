<?php
namespace ALM\Controllers\Audit\Events\Groups\Network;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Multisite User Events
 * @since   1.0.0
 * 
 * Basically, we will just create the multisite user event list and merge it with the 
 * existing user events
 * 
 * @see \ALM\Controllers\Audit\Events\Groups\trait-user-events.php
 */

trait UserEvents
{
    /**
     * Specifies the Multisite use events
     * @since 1.0.0
     * @var array
     */
    protected $mu_user_events = [];

    protected function initMsUserEvents()
    {
        $this->mu_user_events = [
             /**
             * Multisite Only
             * 
             * Fires after the user is marked as a SPAM user
             * 
             * @since 1.0.0
             * 
             * @see wp_insert_user()
             */
            'make_spam_user' => [
                'title'     => 'User marked as Spam',
                'action'    => 'user_modified',
                'event_id'  => 5151,
                'severity'  => 'critical',

                'screen'     => [ 'multisite' ],

                'message'  => [
                    '_main'                    => 'Marked the user (%s) as Spam.',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ]
            ],

            /**
             * Multisite Only
             * 
             * Fires after the user is marked as a HAM user
             * 
             * @since 1.0.0
             * 
             * @see wp_insert_user()
             */
            'make_ham_user' => [
                'title'     => 'User marked as Ham',
                'action'    => 'user_modified',
                'event_id'  => 5052,
                'severity'  => 'critical',

                'screen'    => ['multisite'],

                'message'  => [
                    '_main'                    => 'Marked the user (%s) as Ham.',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ]
            ],

            /**
             * Multisite Only
             * 
             * Checks and log whether the user should be added to the site.
             * 
             * @since 1.0.0
             * 
             * @see add_user_to_blog()
             */
            'can_add_user_to_blog' => [
                'title'               => 'Unable to add the user to the site',
                'action'              => 'add',
                'event_id'            => 5053,
                'severity'            => 'critical',
                'error_flag'          => true,
                'event_successor'     => ['user', 'add_user_to_blog'],

                'screen'              => ['multisite'],

                'message'  => [
                    '_main'                    => 'Tried to add the user (%s) to the site but the attempt was unsuccessful.',

                    '_space_start'             => '',
                    'failed_attempts'          => ['failed_attempts'],
                    'site_id'                  => ['blog_id'],
                    'site_name'                => ['blog_name'],
                    'site_url'                 => ['blog_url'],
                    'role_given'               => ['role_given'],
                    '_error_msg'               => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'filter',
                    'num_args' => 4,
                ],
            ],

            /**
             * Multisite Only
             * 
             * Fires immediately after an existing user is added to a site 
             * by an admin without email confirmation
             * 
             * @since 1.0.0
             * 
             * @see add_user_to_blog()
             */
            'add_user_to_blog' => [
                'title'    => 'User added to the site',
                'action'   => 'existing_user_added',
                'event_id' => 5154,
                'severity' => 'critical',
                
                'screen'   => ['multisite'],

                'message'  => [
                    '_main'                    => 'Added the user (%s) to the site without email confirmation',

                    '_space_start'             => '',
                    'site_id'                  => ['blog_id'],
                    'site_name'                => ['blog_name'],
                    'site_url'                 => ['blog_url'],
                    'role_given'               => ['role_given'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * Multisite Only
             * 
             * Fires immediately after an existing user is added to a site 
             * by an admin with email confirmation
             * 
             * @since 1.0.0
             * 
             * @see wpmu_signup_user()
             */
            'invite_user' => [
                'title'               => 'Invited the user to the site',
                'action'              => 'user_invited',
                'event_id'            => 5155,
                'severity'            => 'notice',
                
                'screen'              => ['multisite'],
                'user_state'          => 'logged_in',
                'logged_in_user_caps' => ['promote_users'],

                'message'  => [
                    '_main' => 'Invited the user (%s) to join the site with email confirmation',

                    '_space_start'              => '',
                    'site_id'                   => ['blog_id'],
                    'site_name'                 => ['blog_name'],
                    'site_url'                  => ['blog_url'],
                    'role_given'                => ['role_given'],
                    'invitation_activation_key' => ['activation_key'],
                    'user_primary_site'         => ['primary_blog'],
                    'primary_site_name'         => ['primary_blog_name'],
                    'primary_site_url'          => ['primary_blog_url'],
                    'source_domain'             => ['source_domain'],
                    '_inspect_user_role'        => '',
                    '_space_end'                => '',

                    'user_id'                   => ['object_id'],
                    'user_login'                => ['user_login'],
                    'display_name'              => ['display_name'],
                    'first_name'                => ['first_name'],
                    'last_name'                 => ['last_name'],
                    'user_email'                => ['user_email'],
                    'profile_url'               => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * Multisite Only
             * 
             * Fires after a new user is created by admin.
             * 
             * This is fired only when the new user is created without email 
             * confirmation.
             * 
             * @since 1.0.0
             * 
             * @see add_new_user_to_blog()
             */
            'alm_add_new_user_to_blog_by_admin' => [
                'title'               => 'New user created',
                'action'              => 'new_user_added',
                'event_id'            => 5156,
                'severity'            => 'critical',
                
                'screen'              => ['multisite'],
                'logged_in_user_caps' => ['create_users', 'manage_network_users'],

                'message'             => [
                    '_main' => 'Created a new user (%s) without sending an email confirmation to the user. The user has been activated and added to the site automatically.',

                    '_space_start'             => '',
                    'site_id'                  => ['blog_id'],
                    'site_name'                => ['blog_name'],
                    'site_url'                 => ['blog_url'],
                    'role_given'               => ['role_given'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'hook' => 'callback',
                ],
            ],

            /**
             * Multisite Only
             * 
             * Fires after a new user self-registers 
             * 
             * @since 1.0.0
             * 
             * @see add_new_user_to_blog()
             */
            'alm_add_new_user_to_blog_by_self' => [
                'title'               => 'New user registered',
                'action'              => 'new_user_registered',
                'event_id'            => 5157,
                'severity'            => 'critical',
                'screen'              => ['multisite'],

                'message'             => [
                    '_main' => 'New user (%s) registration successful.',

                    '_space_start'             => '',
                    'site_id'                  => ['blog_id'],
                    'site_name'                => ['blog_name'],
                    'site_url'                 => ['blog_url'],
                    'role_given'               => ['role_given'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'hook' => 'callback',
                ],
            ],

            /**
             * Multisite Only
             * 
             * Record user signup information for future activation.
             * 
             * This is fired only when the new user is created with email 
             * confirmation.
             * 
             * @since 1.0.0
             * 
             * @see wpmu_signup_user()
             */
            'after_signup_user' => [
                // 'title'               => 'New user created',
                'title'     => 'New user signup information recorded',
                'action'    => 'new_user_recorded',
                'event_id'  => 5158,
                'severity'  => 'critical',
                'screen'    => ['multisite'],

                'message'   => [
                    '_main' => 'Recorded a new user (%s) signup information for future activation. The user is not a member of the site until they confirm the request.',
                    // '_main' => 'Created a new user with email confirmation for future activation',

                    '_space_start'              => '',
                    'site_id'                   => ['blog_id'],
                    'site_name'                 => ['blog_name'],
                    'site_url'                  => ['blog_url'],
                    'role_given'                => ['role_given'],
                    'new_user_status'           => ['new_user_status'],
                    'invitation_activation_key' => ['activation_key'],
                    '_inspect_user_role'        => '',
                    '_space_end'                => '',

                    'user_id'                   => ['object_id'],
                    'user_login'                => ['user_login'],
                    'display_name'              => ['display_name'],
                    'first_name'                => ['first_name'],
                    'last_name'                 => ['last_name'],
                    'user_email'                => ['user_email'],
                    'profile_url'               => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 4,
                ],
            ],

            /**
             * Multisite Only
             * 
             * Record user signup information for future activation.
             * 
             * This is fired only when the new user self-registers with 
             * email confirmation
             * 
             * @since 1.0.0
             * 
             * @see wpmu_create_user()
             */
            'alm_after_signup_user_by_self' => [
                'title'               => 'New user signup information recorded',
                'action'              => 'new_user_signup_recorded',
                'event_id'            => 5159,
                'severity'            => 'notice',
                'screen'              => ['multisite'],

                'message'             => [
                    '_main' => 'A new user (%s) signup information has been recorded for future activation. The user is not a member of the site until they confirm the request.',
                    // '_main' => 'Created a new user with email confirmation for future activation',

                    '_space_start'              => '',
                    'site_id'                   => ['blog_id'],
                    'site_name'                 => ['blog_name'],
                    'site_url'                  => ['blog_url'],
                    'role_given'                => ['role_given'],
                    'new_user_status'           => ['new_user_status'],
                    'invitation_activation_key' => ['activation_key'],
                    '_inspect_user_role'        => '',
                    '_space_end'                => '',

                    'user_id'                   => ['object_id'],
                    'user_login'                => ['user_login'],
                    'display_name'              => ['display_name'],
                    'first_name'                => ['first_name'],
                    'last_name'                 => ['last_name'],
                    'user_email'                => ['user_email'],
                    'profile_url'               => ['profile_url'],
                ],

                'event_handler' => [
                    'hook' => 'callback',
                ],
            ],

            /**
             * Multisite Only
             * 
             * Fires immediately after a new user is activated.
             * 
             * This is fired only when the new user self-registers with 
             * email confirmation
             * 
             * @since 1.0.0
             * 
             * @see wpmu_activate_signup()
             */
            'wpmu_activate_user' => [
                'title'               => 'New user activated',
                'action'              => 'new_signup_user_activated',
                'event_id'            => 5160,
                'severity'            => 'notice',
                'screen'              => ['multisite'],

                'message'             => [
                    '_main' => 'A new user (%s) has been activated successfully. The user is now a member of the site.',

                    '_space_start'              => '',
                    'site_id'                   => ['blog_id'],
                    'site_name'                 => ['blog_name'],
                    'site_url'                  => ['blog_url'],
                    'role_given'                => ['role_given'],
                    'new_user_status'           => ['new_user_status'],
                    'user_primary_site'         => ['primary_blog'],
                    'primary_site_name'         => ['primary_blog_name'],
                    'primary_site_url'          => ['primary_blog_url'],
                    'source_domain'             => ['source_domain'],
                    '_inspect_user_role'        => '',
                    '_space_end'                => '',

                    'user_id'                   => ['object_id'],
                    'user_login'                => ['user_login'],
                    'display_name'              => ['display_name'],
                    'first_name'                => ['first_name'],
                    'last_name'                 => ['last_name'],
                    'user_email'                => ['user_email'],
                    'profile_url'               => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * Multisite Only
             * 
             * Fires before a user is removed from a site.
             * 
             * @since 1.0.0
             * 
             * @see remove_user_from_blog()
             */
            'remove_user_from_blog' => [
                'title'           => 'User removed from a site',
                'action'          => 'Removed',
                'event_id'        => 5161,
                'severity'        => 'critical',
                'screen'          => ['multisite'],
                'user_state'      => 'logged_in',

                'message'  => [
                    '_main'                    => 'Removed a user (%s) from the site',

                    '_space_start'             => '',
                    'site_id'                  => ['blog_id'],
                    'site_name'                => ['blog_name'],
                    'site_url'                 => ['blog_url'],
                    'role_given'               => ['role_given'],
                    'reassign_user_post_to'    => ['reassign_post'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * Fires immediately before a user is deleted from the network.
             * 
             * Multisite only
             * 
             * @since 1.0.0
             */
            'alm_deleted_user_from_network' => [
                'title'               => 'User deleted',
                'action'              => 'user_deleted',
                'event_id'            => 5162,
                'severity'            => 'critical',
                'screen'              => ['multisite'],
                'logged_in_user_caps' => ['delete_users'],

                'message' => [
                    '_main' => 'Deleted a user account (%s) from the network.',

                    '_space_start'             => '',
                    'deleted_user_statistics'  => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 2,
                ],
            ],
        ];
    }
}