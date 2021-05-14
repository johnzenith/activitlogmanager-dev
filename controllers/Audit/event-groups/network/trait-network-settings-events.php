<?php
namespace ALM\Controllers\Audit\Events\Groups\Network;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Network Settings Events
 * @since   1.0.0
 */

trait NetworkSettingsEvents
{
    /**
     * Init the Network Settings Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     * 
     * @see /wp-admin/network/settings.php
     */
    protected function initNetworkSettingsEvents()
    {
        // ---------------------------------------------------------------------
        //                Network Settings >>> Operational Settings
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['operational_settings'] = [
            'group'       => 'operational_settings',
            'object'      => 'network_settings',
            'network'     => true,
            'description' => alm__('Operational Settings'),
            'options'     => [
                'site_name' => [
                    'label'       => 'Network title',
                    'event_id'    => 5501,
                    'severity'    => 'notice',
                ],
                'new_admin_email' => [
                    '_main'       => 'Requested a change of the network admin email address',
                    'label'       => 'Network admin email',
                    'title'       => 'Requested a change of the network admin email address',
                    'event_id'    => 5502,
                    'severity'    => 'critical',
                ],
                'admin_email' => [
                    'label'       => 'Network admin email',
                    'event_id'    => 5503,
                    'severity'    => 'critical',
                ],
            ]
        ];

        // ---------------------------------------------------------------------
        //             Network Settings >>> Registration Settings
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['registration_settings'] = [
            'group'       => 'registration_settings',
            'object'      => 'network_settings',
            'description' => alm__('Registration Settings'),
            'options'     => [
                'registration' => [
                    'label'          => 'Allow new registrations',
                    'event_id'       => 5504,
                    'severity'       => 'critical',
                    'value_contexts' => [
                        'all'  => 'Both sites and user accounts can be registered',
                        'user' => 'User accounts may be registered',
                        'none' => 'Registration is disabled',
                        'blog' => 'Logged in users may register new sites',
                    ]
                ],
                'registrationnotification' => [
                    'label'    => 'Registration notification',
                    'event_id' => 5505,
                    'severity' => 'notice',
                ],
                'add_new_users' => [
                    'label'          => 'Add new users',
                    'event_id'       => 5506,
                    'severity'       => 'critical',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'illegal_names' => [
                    'label'    => 'Banned names',
                    'event_id' => 5507,
                    'severity' => 'critical',
                ],
                'limited_email_domains' => [
                    'label'    => 'Limited email registrations',
                    'event_id' => 5508,
                    'severity' => 'critical',
                    'field_props' => [
                        'type'    => 'textarea',
                        'newline' => true,
                        'implode' => true,
                    ]
                ],
                'banned_email_domains' => [
                    'label'      => 'Banned email domains',
                    'event_id'   => 5509,
                    'severity'   => 'critical',
                    'field_props' => [
                        'type'    => 'textarea',
                        'newline' => true,
                        'implode' => true,
                    ]
                ],
            ]
        ];

        // ---------------------------------------------------------------------
        //              Network Settings >>> New Site Settings
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['new_site_settings'] = [
            'group'       => 'new_site_settings',
            'object'      => 'network_settings',
            'description' => alm__('New Site Settings'),
            'options'     => [
                'welcome_email' => [
                    'label'      => 'Welcome email',
                    'event_id'   => 5510,
                    'severity'   => 'notice',
                    'field_props' => [
                        'type'    => 'textarea',
                    ]
                ],
                'welcome_user_email' => [
                    'label'      => 'Welcome user email',
                    'event_id'   => 5511,
                    'severity'   => 'notice',
                    'field_props' => [
                        'type'    => 'textarea',
                    ]
                ],
                'first_post' => [
                    'label'      => 'First post',
                    'event_id'   => 5512,
                    'severity'   => 'notice',
                ],
                'first_page' => [
                    'label'      => 'First page',
                    'event_id'   => 5513,
                    'severity'   => 'notice',
                ],
                'first_comment' => [
                    'label'      => 'First comment',
                    'event_id'   => 5514,
                    'severity'   => 'notice',
                ],
                'first_comment_url' => [
                    'label'      => 'First comment URL',
                    'event_id'   => 5515,
                    'severity'   => 'notice',
                ],
                'first_comment_email' => [
                    'label'      => 'First comment email',
                    'event_id'   => 5516,
                    'severity'   => 'notice',
                ],
                'first_comment_author' => [
                    'label'      => 'First comment author',
                    'event_id'   => 5517,
                    'severity'   => 'notice',
                ],
            ]
        ];

        // ---------------------------------------------------------------------
        //              Network Settings >>> Upload Settings
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['upload_settings'] = [
            'group'       => 'upload_settings',
            'object'      => 'network_settings',
            'description' => alm__('Upload Settings'),
            'options'     => [
                'upload_space_check_disabled' => [
                    'label'          => 'Limit total size of files uploaded',
                    'event_id'       => 5518,
                    'severity'       => 'critical',
                    'value_contexts' => [
                        0 => 'Disabled',
                        1 => 'Enabled',
                    ]
                ],
                'blog_upload_space' => [
                    'label'     => 'Site upload space (MB)',
                    'event_id' => 5519,
                    'severity' => 'critical'
                ],
                'upload_filetypes' => [
                    'label'      => 'Upload file types',
                    'event_id'   => 5520,
                    'severity'   => 'critical',
                ],
                'fileupload_maxk' => [
                    'label'      => 'Max upload file size',
                    'event_id'   => 5521,
                    'severity'   => 'critical',
                ],
            ]
        ];

        // ---------------------------------------------------------------------
        //                 Network Settings >>> Menu Settings
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['menu_settings'] = [
            'group'       => 'menu_settings',
            'object'      => 'network_settings',
            'description' => alm__('Menu Settings'),
            'options'     => [
                'menu_items' => [
                    'label'      => 'Enable administration menus',
                    'event_id'   => 5522,
                    'severity'   => 'notice',
                ],
            ]
        ];

        // ---------------------------------------------------------------------
        //                Network Settings >>> Language Settings
        // ---------------------------------------------------------------------
        $this->wp_core_settings_events['language_settings'] = [
            'group'       => 'language_settings',
            'object'      => 'network_settings',
            'description' => alm__('Language Settings'),
            'options'     => [
                'WPLANG' => [
                    'label'       => 'Default Language',
                    'event_id'    => 5523,
                    'severity'    => 'critical',
                ]
            ]
        ];
    }
}