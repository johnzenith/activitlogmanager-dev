<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package WordPress Core Events
 * @since   1.0.0
 */

trait WP_CoreSettingsEvents
{
    /**
     * Specifies the WP core settings events list.
     * @since 1.0.0
     * @var array
     */
    protected $wp_core_settings_events = [];

    /**
     * Specifies the default WP core settings event arguments
     * @since 1.0.0
     * @var array
     */
    protected $wp_core_settings_event_default_args = [];

    /**
     * Holds the setting data prior to deletion
     * @since 1.0.0
     * @see get_option()
     * @var mixed
     */
    protected $wp_core_setting_deleted_data = false;

    /**
     * Specifies the setting event namespace handler
     * @since 1.0.0
     * @var string
     */
    protected $wp_core_setting_event_handler = "alm_wp_%s_setting_changed";

    /**
     * Init the WP Core Settings Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Templates\EventList
     */
    public function initWpCoreSettingsEvents()
    {
        $this->event_list['wp_core_settings'] = [
            'title'       => 'WordPress Core Settings Events',
            'group'       => 'wp_core_settings',
            'object'      => 'wp_core_settings',

            'description' => alm__('Responsible for logging all WP core settings activities. It includes settings such as General, writing, Reading, Discussion, Media, Permalinks and Privacy.'),

            'events' => []
        ];

        // Default event args
        $this->wp_core_settings_event_default_args = [
            // %s will be replaced with the option name and action type.
            // The action types are: 'created' | 'updated' | 'deleted'
            'title'               => '%s the %s setting',
            'action'              => '%s', // Action type
            'event_id'            => 0, // Must be set by each WP core setting instance
            'severity'            => 'notice',

            'screen'              => ['admin', 'network'],
            'user_state'          => 'logged_in',
            'logged_in_user_caps' => ['manage_options', 'manage_network_options'],

            /**
             * Options we need to ignore in other to avoid duplicated event references
             */
            'wp_options'      => ['adminhash'],
            'wp_site_options' => ['network_admin_hash'],

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
                /**
                 * The '%s' strings will be replaced with the action type and 
                 *  corresponding WP option's name
                 */
                '_main'                    => '%s the %s setting',

                '_space_start'             => '',
                'option_name'              => ['option_name'],
                'previous_value'           => ['previous_value'],
                'new_value'                => ['new_value'],
                'current_value'            => '_ignore_', // Useful when the setting is being added
                'settings_page'            => ['settings_page'],
                '_space_end'               => '',

                'user_id'                  => ['object_id'],
                'user_login'               => ['user_login'],
                'display_name'             => ['display_name'],
                'first_name'               => ['first_name'],
                'last_name'                => ['last_name'],
                'user_email'               => ['user_email'],
                'log_counter'              => '',
                'profile_url'              => ['profile_url'],
                'user_primary_blog'        => ['primary_blog'],
                'primary_blog_name'        => ['primary_blog_name'],
                'source_domain'            => ['source_domain'],
            ],

            'event_handler' => [
                'hook' => 'callback',
            ],
        ];

        /**
         * Register the WP core settings and dispatch the settings event handlers
         */
        $this->registerWpCoreSettings();
        $this->registerWpCoreSettingsEventHandlers();
    }

    /**
     * Register the WP core settings instances
     * 
     * @see /wp-admin/options.php
     */
    private function registerWpCoreSettings()
    {
        $new_admin_email_msg = 'Requested a change of administration email address';

        $this->wp_core_settings_events = [
            // ---------------------------------------------------------------------
            //                         Options General
            // ---------------------------------------------------------------------
            'general' => [
                'group'       => 'options_general',
                'description' => alm__('General Settings'),
                'options'     => [
                    'WPLANG' => [
                        'label'       => 'Site language',
                        'event_id'    => 5081,
                        'severity'    => 'critical',
                    ],
                    'blogname' => [
                        'label'       => 'Site title',
                        'event_id'    => 5082,
                        'severity'    => 'notice',
                    ],
                    'gmt_offset' => [
                        'label'       => 'Timezone',
                        'event_id'    => 5083,
                        'severity'    => 'notice',
                    ],
                    'date_format' => [
                        'label'       => 'Date format',
                        'event_id'    => 5084,
                        'severity'    => 'notice',
                    ],
                    'time_format' => [
                        'label'       => 'Time format',
                        'event_id'    => 5085,
                        'severity'    => 'notice',
                    ],
                    'start_of_week' => [
                        'label'       => 'Start of week',
                        'event_id'    => 5086,
                        'severity'    => 'notice',
                    ],
                    // 'timezone_string', // currently not used, mapped to 'gmt_offset'
                    'new_admin_email' => [
                        '_main'       => $new_admin_email_msg,
                        'label'       => 'Administration email address',
                        'title'       => $new_admin_email_msg,
                        'event_id'    => 5087,
                        'severity'    => 'critical',
                    ],
                    'admin_email' => [
                        'label'       => 'Adminstration email address',
                        'event_id'    => 5088,
                        'severity'    => 'critical',
                    ],
                    'blogdescription' => [
                        'label'       => 'Tagline',
                        'event_id'    => 5089,
                        'severity'    => 'notice',
                    ],
                    'siteurl' => [
                        'label'       => 'WordPress address (URL)',
                        'event_id'    => 5090,
                        'severity'    => 'critical',
                        'screen'      => ['admin', 'network'],
                    ],
                    'home' => [
                        'label'       => 'Site address (URL)',
                        'event_id'    => 5091,
                        'severity'    => 'critical',
                    ],
                    'users_can_register' => [
                        'label'       => 'Membership',
                        'event_id'    => 5092,
                        'severity'    => 'critical',
                    ],
                    'default_role' => [
                        'label'       => 'New user default role',
                        'event_id'    => 5093,
                        'severity'    => 'critical',
                    ],
                    'bsf_analytics_optin' => [
                        'label'       => 'Usage tracking',
                        'event_id'    => 5093,
                        'severity'    => 'critical',
                    ],
                ],
            ],

            // ---------------------------------------------------------------------------
            //                             Options Discussion
            // ---------------------------------------------------------------------------
            'discussion' => [
                'default_pingback_flag',
                'default_ping_status',
                'default_comment_status',
                'comments_notify',
                'moderation_notify',
                'comment_moderation',
                'require_name_email',
                'comment_previously_approved',
                'comment_max_links',
                'moderation_keys',
                'disallowed_keys',
                'show_avatars',
                'avatar_rating',
                'avatar_default',
                'close_comments_for_old_posts',
                'close_comments_days_old',
                'thread_comments',
                'thread_comments_depth',
                'page_comments',
                'comments_per_page',
                'default_comments_page',
                'comment_order',
                'comment_registration',
                'show_comments_cookies_opt_in',
            ],

            // ---------------------------------------------------------------------------
            //                             Options Media
            // ---------------------------------------------------------------------------
            'media'      => [
                'thumbnail_size_w',
                'thumbnail_size_h',
                'thumbnail_crop',
                'medium_size_w',
                'medium_size_h',
                'large_size_w',
                'large_size_h',
                'image_default_size',
                'image_default_align',
                'image_default_link_type',
            ],

            // ---------------------------------------------------------------------------
            //                             Options Reading
            // ---------------------------------------------------------------------------
            'reading'    => [
                'posts_per_page',
                'posts_per_rss',
                'rss_use_excerpt',
                'show_on_front',
                'page_on_front',
                'page_for_posts',
                'blog_public',
            ],

            // ---------------------------------------------------------------------------
            //                             Options Writing
            // ---------------------------------------------------------------------------
            'writing'    => [
                'default_category',
                'default_email_category',
                'default_link_category',
                'default_post_format',
            ],
            
            'misc'      => [],
            'options'   => [],
            'privacy'   => [],
        ];

        $mail_options = ['mailserver_url', 'mailserver_port', 'mailserver_login', 'mailserver_pass'];

        if (!in_array(get_option('blog_charset'), ['utf8', 'utf-8', 'UTF8', 'UTF-8'], true)) {
            $this->wp_core_settings_events['reading'][] = 'blog_charset';
        }

        if (get_site_option('initial_db_version') < 32453) {
            $this->wp_core_settings_events['writing'][] = 'use_smilies';
            $this->wp_core_settings_events['writing'][] = 'use_balanceTags';
        }

        // -------------------------------------------------------------------------------
        //                          Non-Multisite Options
        // -------------------------------------------------------------------------------
        if (!$this->is_multisite)
        {
            $this->wp_core_settings_events['writing']   = array_merge($this->wp_core_settings_events['writing'], $mail_options);
            $this->wp_core_settings_events['writing'][] = 'ping_sites';

            $this->wp_core_settings_events['media'][] = 'uploads_use_yearmonth_folders';

            /*
            * If upload_url_path is not the default (empty),
            * or upload_path is not the default ('wp-content/uploads' or empty),
            * they can be edited, otherwise they're locked.
            */
            if (get_option('upload_url_path') || (get_option('upload_path') != 'wp-content/uploads' && get_option('upload_path'))) {
                $this->wp_core_settings_events['media'][] = 'upload_path';
                $this->wp_core_settings_events['media'][] = 'upload_url_path';
            }
        }
        else {
            if (apply_filters('enable_post_by_email_configuration', true)) {
                $this->wp_core_settings_events['writing'] = array_merge($this->wp_core_settings_events['writing'], $mail_options);
            }
        }
    }

    /**
     * Get the WP core settings page for the registered settings
     * 
     * @param  string $setting_group Specifies the registered settings name.
     * 
     * @param  string $option_name   Specifies the option name fo the setting group.
     *                               This is optional, but useful on multisite so that 
     *                               the given option can be mapped to the correct settings 
     *                               page on the network admin dashboard.
     * 
     * @return string                The corresponding settings page for the given option.
     */
    protected function getWpCoreSettingsPage($setting_group, $option_name = '')
    {
        $option_name = str_replace('_', '_', $option_name);

        if (!$this->is_multisite) {
            return esc_url_raw(self_admin_url("{$setting_group}.php#option_name"));
        }
    }

    /**
     * Registers the event handlers for monitoring the WP core settings changes
     */
    private function registerWpCoreSettingsEventHandlers()
    {
        $default_event_args = $this->wp_core_settings_event_default_args;

        foreach ($this->wp_core_settings_events as $setting)
        {
            if (empty($setting['options']))
                continue;

            foreach ($setting['options'] as $option_name => $option_data)
            {
                $option_label  = lcfirst($option_data['label']);
                $event_handler = sprintf($this->wp_core_setting_event_handler, $option_name);

                $wp_core_setting_event_data = [
                    'title'    => sprintf($default_event_args['title'], '%s', $option_label),
                    'object'   => $setting['group'],
                    'event_id' => $option_data['event_id'],
                    'severity' => $option_data['severity'],
                ];

                $wp_core_setting_event_data = array_merge(
                    $default_event_args, $wp_core_setting_event_data
                );

                if (!empty($option_data['_main'])) {
                    $wp_core_setting_event_data['message']['_main'] = $option_data['_main'];
                }

                /**
                 * Add the event to the event list
                 */
                $this->event_list['wp_core_settings']['events'][$event_handler] = $wp_core_setting_event_data;

                // ------------------------------------------------------------------------
                //                  Bind the option to the event handler
                // ------------------------------------------------------------------------

                // Run when the setting is added for the first time
                add_action("add_option_{$option_name}", function ($option, $value) use ($option_data)
                {
                    $this->alm_wp_core_settings_changed([
                        'value'       => $value,
                        'label'       => $option_data['label'],
                        'option'      => $option,
                        'action_type' => 'added'
                    ]);
                });

                // Run when the setting is updated
                add_action("update_option_{$option_name}", function ($old_value, $value, $option) use ($option_data)
                {
                    $this->alm_wp_core_settings_changed([
                        'value'          => $value,
                        'label'          => $option_data['label'],
                        'option'         => $option,
                        'action_type'    => 'updated',
                        'previous_value' => $old_value,
                    ]);
                });

                // Retrieve the option value immediately before the option is deleted
                add_action("delete_option_{$option_name}", function ($option) {
                    $this->wp_core_setting_deleted_data = get_option($option);
                });

                // Run when the setting is deleted
                add_action("delete_option_{$option_name}", function ($option) use ($option_data)
                {
                    $this->alm_wp_core_settings_changed([
                        'value'       => null,
                        'label'       => $option_data['label'],
                        'option'      => $option,
                        'action_type' => 'deleted',
                    ]);
                });
            }
        }
    }
}