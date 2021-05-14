<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package WordPress Core Events
 * @since   1.0.0
 */

trait WP_CoreSettingsEvents
{
    /**
     * Specifies the WP core settings slug
     * @since 1.0.0
     * @var string 
     */
    protected $wp_core_settings_slug = 'wp_core_setting';

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
    protected $wp_core_setting_event_handler = "alm_wp_%s_setting";

    /**
     * Init the WP Core Settings Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     */
    protected function initWpCoreSettingsEvents()
    {
        $this->event_list[ $this->wp_core_settings_slug ] = [
            'title'           => 'WordPress Core Settings Events',
            'events'          => [],
            'group'           => $this->wp_core_settings_slug,
            'action'          => 'setting_modified',
            'object'          => $this->wp_core_settings_slug,
            'object_id_label' => 'option',

            'description'     => alm__('Responsible for logging all WP core settings activities. It includes settings such as General, writing, Reading, Discussion, Media, Permalinks and Privacy.'),
        ];

        // Default event args
        $this->wp_core_settings_event_default_args = [
            // %s will be replaced with the option name and action type.
            // The action types are: 'created' | 'updated' | 'deleted'
            'title'               => '%s the %s setting',
            'group'               => $this->wp_core_settings_slug,
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
                'option_id'                => ['object_id'],
                'option_name'              => ['option_name'],
                'previous_value'           => ['previous_value'],
                'new_value'                => ['new_value'],
                'current_value'            => '_ignore_', // Useful when the setting is being added
                'requested_value'          => '_ignore_', // Useful for email request changes
                'settings_page'            => ['settings_page'],
                'settings_section'         => '_ignore_',
                '_space_end'               => '',
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
        // ---------------------------------------------------------------------
        //                         Options General
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['general'] = [
            'group'       => 'options_general',
            'description' => alm__('General Settings'),
            'options'     => [
                'WPLANG' => [
                    'label'       => 'Site language',
                    'event_id'    => 5251,
                    'severity'    => 'critical',
                ],
                'blogname' => [
                    'label'       => 'Site title',
                    'event_id'    => 5252,
                    'severity'    => 'notice',
                ],
                'gmt_offset' => [
                    'label'       => 'Timezone',
                    'event_id'    => 5253,
                    'severity'    => 'notice',
                ],
                'date_format' => [
                    'label'       => 'Date format',
                    'event_id'    => 5254,
                    'severity'    => 'notice',
                ],
                'time_format' => [
                    'label'       => 'Time format',
                    'event_id'    => 5255,
                    'severity'    => 'notice',
                ],
                'start_of_week' => [
                    'label'       => 'Start of week',
                    'event_id'    => 5256,
                    'severity'    => 'notice',
                ],
                // 'timezone_string', // currently not used, mapped to 'gmt_offset'
                'new_admin_email' => [
                    '_main'            => 'Requested a change of the administration email address',
                    'label'            => 'Administration email address',
                    'title'            => 'Requested a change of the administration email address',
                    'event_id'         => 5257,
                    'severity'         => 'critical',
                    '_requested_value' => true,
                ],
                'admin_email' => [
                    'label'       => 'Adminstration email address',
                    'event_id'    => 5258,
                    'severity'    => 'critical',
                ],
                'blogdescription' => [
                    'label'       => 'Tagline',
                    'event_id'    => 5259,
                    'severity'    => 'notice',
                ],
                'siteurl' => [
                    'label'       => 'WordPress address (URL)',
                    'event_id'    => 5260,
                    'severity'    => 'critical',
                    'screen'      => ['admin', 'network'],
                ],
                'home' => [
                    'label'       => 'Site address (URL)',
                    'event_id'    => 5261,
                    'severity'    => 'critical',
                ],
                'users_can_register' => [
                    'label'       => 'Membership',
                    'event_id'    => 5262,
                    'severity'    => 'critical',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'default_role' => [
                    'label'       => 'New user default role',
                    'event_id'    => 5263,
                    'severity'    => 'critical',
                ],
                'bsf_analytics_optin' => [
                    'label'       => 'Usage tracking',
                    'event_id'    => 5264,
                    'severity'    => 'critical',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
            ],
        ];

        // ---------------------------------------------------------------------------
        //                             Options Discussion
        // ---------------------------------------------------------------------------
        $this->wp_core_settings_events['discussion'] = [
            'group'       => 'options_discussion',
            'description' => alm__('Discussion Settings'),
            'options'     => [
                'default_pingback_flag' => [
                    'label'          => 'Attempt to notify any blogs linked to from the post',
                    'section'        => 'Default post settings',
                    'event_id'       => 5265,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'default_ping_status' => [
                    'label'          => 'Allow link notifications from other blogs (pingbacks and trackbacks) on new posts',
                    'section'        => 'Default post settings',
                    'event_id'       => 5266,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'default_comment_status' => [
                    'label'          => 'Allow people to submit comments on new posts',
                    'section'        => 'Default post settings',
                    'event_id'       => 5267,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'require_name_email' => [
                    'label'          => 'Comment author must fill out name and email',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5268,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'comment_registration' => [
                    'label'          => 'Users must be registered and logged in to comment',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5269,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'close_comments_for_old_posts' => [
                    'label'          => 'Automatically close comments on posts older',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5270,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'close_comments_days_old' => [
                    'label'    => 'Automatically close comments on posts older than days',
                    'section'  => 'Other comment settings',
                    'event_id' => 5271,
                    'severity' => 'notice',
                ],
                'show_comments_cookies_opt_in' => [
                    'label'          => 'Show comments cookies opt-in checkbox',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5272,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'thread_comments' => [
                    'label'          => 'Enable threaded (nested) comments',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5273,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'thread_comments_depth' => [
                    'label'          => 'Enable threaded (nested) comments levels deep',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5274,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'page_comments' => [
                    'label'          => 'Break comments into pages with',
                    'section'        => 'Other comment settings',
                    'event_id'       => 5275,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'comments_per_page' => [
                    'label'     => 'Top level comments per page',
                    'section'   => 'Other comment settings',
                    'event_id'  => 5276,
                    'severity'  => 'notice',
                ],
                'default_comments_page' => [
                    'label'     => 'Comment page displayed by default',
                    'section'   => 'Other comment settings',
                    'event_id'  => 5277,
                    'severity'  => 'notice',
                ],
                'comment_order' => [
                    'label'     => 'Comments should be displayed with',
                    'section'   => 'Other comment settings',
                    'event_id'  => 5278,
                    'severity'  => 'notice',
                ],
                'comments_notify' => [
                    'label'          => 'Email me whenever anyone posts a comment',
                    'section'        => 'Email me whenever',
                    'event_id'       => 5279,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'moderation_notify' => [
                    'label'          => 'Email me whenever a comment is held for moderation',
                    'section'        => 'Email me whenever',
                    'event_id'       => 5280,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'comment_moderation' => [
                    'label'          => 'Comment must be manually approved',
                    'section'        => 'Before a comment appears',
                    'event_id'       => 5281,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'comment_previously_approved' => [
                    'label'          => 'Comment author must have a previously approved comment',
                    'section'        => 'Before a comment appears',
                    'event_id'       => 5282,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'comment_max_links' => [
                    'label'    => 'Hold a comment in the queue if it contains links',
                    'section'  => 'Comment moderation',
                    'event_id' => 5283,
                    'severity' => 'notice',
                ],
                'moderation_keys' => [
                    'label'    => 'Comment moderation keys',
                    'section'  => 'Comment moderation',
                    'event_id' => 5284,
                    'severity' => 'notice',
                ],
                'disallowed_keys' => [
                    'label'    => 'Disallowed Comment Keys',
                    'section'  => 'Comment moderation',
                    'event_id' => 5285,
                    'severity' => 'notice',
                ],
                'show_avatars' => [
                    'label'          => 'Show avatars',
                    'section'        => 'Avatars',
                    'event_id'       => 5286,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'avatar_rating' => [
                    'label'    => 'Avatar maximum rating',
                    'section'  => 'Avatars',
                    'event_id' => 5287,
                    'severity' => 'notice',
                ],
                'avatar_default' => [
                    'label'    => 'Default avatar',
                    'section'  => 'Avatars',
                    'event_id' => 5288,
                    'severity' => 'notice',
                ],
            ]
        ];

        // ---------------------------------------------------------------------------
        //                             Options Media
        // ---------------------------------------------------------------------------
        $this->wp_core_settings_events['media'] = [
            'group'       => 'options_media',
            'description' => alm__('Media Settings'),
            'options'     => [
                'thumbnail_size_w' => [
                    'label'    => 'Thumbnail size width',
                    'section'  => 'Image sizes (thumbnail size)',
                    'event_id' => 5289,
                    'severity' => 'notice',
                ],
                'thumbnail_size_h' => [
                    'label'    => 'Thumbnail size height',
                    'section'  => 'Image sizes (thumbnail size)',
                    'event_id' => 5290,
                    'severity' => 'notice',
                ],
                'thumbnail_crop' => [
                    'label'          => 'Crop thumbnail to exact dimensions',
                    'section'        => 'Image sizes (thumbnail size)',
                    'event_id'       => 5291,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'medium_size_w' => [
                    'label'    => 'Thumbnail medium size max width',
                    'section'  => 'Image sizes (medium size)',
                    'event_id' => 5292,
                    'severity' => 'notice',
                ],
                'medium_size_h' => [
                    'label'    => 'Thumbnail medium size max height',
                    'section'  => 'Image sizes (medium size)',
                    'event_id' => 5293,
                    'severity' => 'notice',
                ],
                'large_size_w' => [
                    'label'    => 'Thumbnail large size max width',
                    'section'  => 'Image sizes (large size)',
                    'event_id' => 5294,
                    'severity' => 'notice',
                ],
                'large_size_h' => [
                    'label'    => 'Thumbnail large size max height',
                    'section'  => 'Image sizes (large size)',
                    'event_id' => 5295,
                    'severity' => 'notice',
                ],
                'uploads_use_yearmonth_folders' => [
                    'label'          => 'Organize my uploads into month- and year-based folders',
                    'section'        => 'Uploading files',
                    'event_id'       => 5296,
                    'severity'       => 'notice',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'upload_path' => [
                    'label'    => 'Store uploads in this folder',
                    'section'  => 'Uploading files',
                    'event_id' => 5297,
                    'severity' => 'notice',
                ],
                'upload_url_path' => [
                    'label'    => 'Full URL path to files',
                    'section'  => 'Uploading files',
                    'event_id' => 5298,
                    'severity' => 'notice',
                ],
                'image_default_size' => [
                    'label'    => 'Image default size',
                    'event_id' => 5299,
                    'severity' => 'notice',
                ],
                'image_default_align' => [
                    'label'    => 'Image default align',
                    'event_id' => 5300,
                    'severity' => 'notice',
                ],
                'image_default_link_type' => [
                    'label'    => 'Image default link type',
                    'event_id' => 5301,
                    'severity' => 'notice',
                ],
            ]
        ];

        // ---------------------------------------------------------------------------
        //                             Options Reading
        // ---------------------------------------------------------------------------
        $this->wp_core_settings_events['reading'] = [
            'group'       => 'options_reading',
            'description' => alm__('Reading Settings'),
            'options'     => [
                'show_on_front' => [
                    'label'    => 'Your homepage displays',
                    'event_id' => 5302,
                    'severity' => 'notice',
                ],
                'page_on_front' => [
                    'label'    => 'Homepage (Page ID)',
                    'event_id' => 5303,
                    'severity' => 'notice',
                ],
                'page_for_posts' => [
                    'label'    => 'Posts page (Page ID)',
                    'event_id' => 5304,
                    'severity' => 'notice',
                ],
                'posts_per_page' => [
                    'label'    => 'Blog pages show at most',
                    'event_id' => 5305,
                    'severity' => 'notice',
                ],
                'posts_per_rss' => [
                    'label'    => 'Syndication feeds show the most recent items',
                    'event_id' => 5306,
                    'severity' => 'notice',
                ],
                'rss_use_excerpt' => [
                    'label'    => 'For each post in a feed, includes',
                    'event_id' => 5307,
                    'severity' => 'notice',
                ],
                'blog_public' => [
                    'label'    => 'Search engine visibility',
                    'event_id' => 5308,
                    'severity' => 'critical',
                ],
                'blog_charset' => [
                    'label'    => 'Encoding for pages and feeds',
                    'event_id' => 5309,
                    'severity' => 'notice',
                ],
            ]
        ];

        // ---------------------------------------------------------------------------
        //                             Options Writing
        // ---------------------------------------------------------------------------
        $this->wp_core_settings_events['writing'] = [
            'group'       => 'options_writing',
            'description' => alm__('Writing Settings'),
            'options'     => [
                'default_category' => [
                    'label'    => 'Default post category',
                    'event_id' => 5310,
                    'severity' => 'notice',
                ],
                'default_post_format' => [
                    'label'    => 'Default post format',
                    'event_id' => 5311,
                    'severity' => 'notice',
                ],
                'default_email_category' => [
                    'label'    => 'Default mail category',
                    'event_id' => 5312,
                    'severity' => 'notice',
                ],
                'default_link_category' => [
                    'label'    => 'Default link category',
                    'event_id' => 5313,
                    'severity' => 'critical',
                ],
                'mailserver_url' => [
                    'label'    => 'Mail server',
                    'section'  => 'Post via email',
                    'event_id' => 5314,
                    'severity' => 'critical',
                ],
                'mailserver_port' => [
                    'label'    => 'Mail server port',
                    'section'  => 'Post via email',
                    'event_id' => 5315,
                    'severity' => 'critical',
                ],
                'mailserver_login' => [
                    'label'    => 'Mail server login name',
                    'section'  => 'Post via email',
                    'event_id' => 5316,
                    'severity' => 'critical',
                ],
                'mailserver_pass' => [
                    'label'    => 'Mail server password',
                    'section'  => 'Post via email',
                    'event_id' => 5317,
                    'severity' => 'critical',
                ],
                'ping_sites' => [
                    'label'    => 'Ping sites',
                    'section'  => 'Update Services',
                    'event_id' => 5318,
                    'severity' => 'critical',
                ],
                'use_smilies' => [
                    'label'    => 'Use smilies',
                    'section'  => 'Formatting',
                    'event_id' => 5319,
                    'severity' => 'notice',
                ],
                'use_balanceTags' => [
                    'label'    => 'WordPress should correct invalidly nested XHTML automatically',
                    'section'  => 'Formatting',
                    'event_id' => 5320,
                    'severity' => 'critical',
                ],
            ]
        ];

        $this->wp_core_settings_events['privacy'] = [
            'group'       => 'options_privacy',
            'description' => alm__('Privacy Settings'),
            'options'     => [
                'wp_page_for_privacy_policy' => [
                    'label'    => 'Privacy policy page',
                    'event_id' => 5321,
                    'severity' => 'notice',
                ]
            ]
        ];

        $this->wp_core_settings_events['permalinks'] = [
            'group'       => 'options_permalinks',
            'description' => alm__('Permalink Settings'),
            'options'     => [
                'permalink_structure' => [
                    'label'    => 'Permalink structure',
                    'section'  => 'Common settings',
                    'event_id' => 5322,
                    'severity' => 'notice',
                ],
                'category_base' => [
                    'label'    => 'Category base',
                    'section'  => 'Optional',
                    'event_id' => 5323,
                    'severity' => 'notice',
                ],
                'tab_base' => [
                    'label'    => 'Tag base',
                    'section'  => 'Optional',
                    'event_id' => 5324,
                    'severity' => 'notice',
                ],
            ]
        ];

        $this->wp_core_settings_events['misc']    = [];
        $this->wp_core_settings_events['options'] = [];
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
        $setting_group = str_replace('_', '-', $setting_group);
        $setting_page  = "{$setting_group}.php";
        $option_target = empty($option_name) ? '' : '#' . $this->sanitizeOption($option_name);

        if (!$this->is_multisite)
            return sprintf(
                '%s%s',
                esc_url_raw(self_admin_url($setting_page)),
                $option_target
            );

        $event_handler    = $this->getWpCoreSettingEventHandler($option_name);

        $event_id         = $this->getEventIdBySlug($event_handler, $this->wp_core_settings_slug);
        $event_data       = $this->getEventData($event_id);

        if (!$event_data) return '#';
        
        $is_network_setting = $this->getVar($event_data, 'network', false);
        
        $setting_page_url   = $is_network_setting 
            ? network_admin_url('settings.php') 
            : self_admin_url($setting_page);

        return $setting_page_url;
    }

    /**
     * Get the WP core setting (option) ID.
     * 
     * Thi is basically a wrapper around the {@see ALM/Controllers/Base/PluginFactory::getWpOptionId()}.
     * 
     * @param string $option_name  Specifies the setting (option) name whose ID should be retrieved.
     * 
     * @return int                 Returns the given option ID if found. Otherwise 0.
     */
    protected function getWpCoreOptionId($option_name = '')
    {
        if (empty($option_name))
            return 0;

        return $this->getWpOptionId($option_name);
    }

    /**
     * Get the WP core setting handler
     * 
     * @param  string $option_name Specifies the option name for the setting
     * @return string
     */
    protected function getWpCoreSettingEventHandler($option_name)
    {
        return sprintf($this->wp_core_setting_event_handler, $option_name);
    }

    /**
     * Registers the event handlers for monitoring the WP core settings changes
     */
    private function registerWpCoreSettingsEventHandlers()
    {
        $default_event_args = $this->wp_core_settings_event_default_args;

        foreach ($this->wp_core_settings_events as $setting)
        {
            if (empty($setting['options'])) continue;

            foreach ($setting['options'] as $option_name => $option_data)
            {
                if (!is_array($option_data)) continue;

                $option_label   = lcfirst($option_data['label']);
                $event_handler  = $this->getWpCoreSettingEventHandler($option_name);

                $setting_object = $this->getVar(
                    $setting, 'object', $this->event_list[ $this->wp_core_settings_slug ]['object']
                );

                $setting_group = $this->getVar(
                    $setting, 'group', $this->event_list[ $this->wp_core_settings_slug ]['group']
                );

                $wp_core_setting_event_data = [
                    'title'    => sprintf($default_event_args['title'], '%s', $option_label),
                    'group'    => $setting_group,
                    'object'   => $setting_object,
                    'event_id' => $option_data['event_id'],
                    'severity' => $option_data['severity'],
                ];

                // Setup the network flag if the setting is registered on the network settings page
                if (!empty($setting['network'])) {
                    $wp_core_setting_event_data['network'] = true;
                }

                $wp_core_setting_event_data = array_merge(
                    $default_event_args, $wp_core_setting_event_data
                );

                // Main message
                if (!empty($option_data['_main'])) {
                    $wp_core_setting_event_data['message']['_main'] = $option_data['_main'];
                }

                // Set the settings section if specified in the option data
                if (!empty($option_data['section'])) {
                    $wp_core_setting_event_data['message']['settings_section'] = $this->getVar($option_data, 'section', '_ignore_');
                }

                // If the event message is using the 'requested_value' info, let's set it up
                if (!empty($option_data['_requested_value'])) {
                    $wp_core_setting_event_data['message']['requested_value'] = ['requested_value'];
                }

                /**
                 * Add the registered WP core settings events to the event main list
                 */
                $this->event_list[$this->wp_core_settings_slug]['events'][$event_handler] = $wp_core_setting_event_data;

                // ------------------------------------------------------------------------
                //                  Bind each settings changes to the event handler
                // ------------------------------------------------------------------------

                $trigger_event_args = [
                    'value'  => null,
                    'label'  => $option_data['label'],
                    'group'  => $wp_core_setting_event_data['group'],
                ];

                // Runs whenever the setting is added for the first time
                add_action("add_option_{$option_name}", function ($option, $value) use ($trigger_event_args)
                {
                    $this->alm_wp_core_settings_changed(array_merge($trigger_event_args, [
                        'value'       => $value,
                        'option'      => $option,
                        'action_type' => 'added',
                    ]));
                }, 10, 2);

                // Runs whenever the setting is updated
                add_action("update_option_{$option_name}", function ($old_value, $value, $option) use ($trigger_event_args)
                {
                    $this->alm_wp_core_settings_changed(array_merge($trigger_event_args, [
                        'value'          => $value,
                        'option'         => $option,
                        'action_type'    => 'updated',
                        'previous_value' => $old_value,
                    ]));

                    /**
                     * Set the admin email update flag
                     */
                    if ('admin_email' === $option) {
                        $this->setConstant('ALM_WP_CORE_SETTING_ADMIN_EMAIL', 'updated');
                    }
                }, 10, 3);

                // Retrieve the option value immediately before the option is deleted
                add_action("delete_option", function ($option)
                {
                    $event_handler = $this->getWpCoreSettingEventHandler($option);
                    $event_id      = $this->getEventIdBySlug($event_handler, $this->wp_core_settings_slug);
                    $event_data    = $this->getEventData($event_id);
                    $event_group   = $this->getVar($event_data, 'group', '');

                    $this->wp_core_setting_deleted_data = [
                        'value'    => get_option($option),
                        'event_id' => $this->getWpCoreOptionId($option, $event_group)
                    ];
                });

                // Runs whenever the setting is deleted
                add_action("delete_option_{$option_name}", function ($option) use ($trigger_event_args)
                {
                    if ('new_admin_email' === $option) {
                        /**
                         * Ignore the 'new_admin_email' deletion when the 'admin_email' is updated
                         */
                        if ('updated' === $this->getConstant('ALM_WP_CORE_SETTING_ADMIN_EMAIL')) {
                            return;
                        }

                        /**
                         * Run a customize event when cancelling the 'new_admin_email' request
                         */
                        if ( ! empty( $_GET['dismiss'] ) && 'new_admin_email' === $_GET['dismiss'] )
                        {
                            $this->alm_wp_core_settings_changed(array_merge($trigger_event_args, [
                                'message_args' => [
                                    '_main'    => 'Cancelled the request to change the administration email address',
                                    'label'    => 'Administration email address',
                                    'title'    => 'Cancelled the request to change the administration email address',
                                    'severity' => 'notice',
                                ],
                                'event_id'        => $this->wp_core_setting_deleted_data['event_id'],
                                'option'          => $option,
                                'action_type'     => 'cancelled',
                                'current_value'   => $this->sanitizeOption(get_option('admin_email'), 'email'),
                                'requested_value' => $this->wp_core_setting_deleted_data['value'],
                            ]));
                            return;
                        }
                    }

                    $this->alm_wp_core_settings_changed(array_merge($trigger_event_args, [
                        'option'        => $option,
                        'event_id'      => $this->wp_core_setting_deleted_data['event_id'],
                        'action_type'   => 'deleted',
                        'current_value' => $this->wp_core_setting_deleted_data['value'],
                    ]));
                });
            }
        }
    }
}