<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
                 * @package Plugin Events
                 * @since   1.0.0
                 */
trait PluginEvents
{
    /**
                 * Specifies list of activated plugins.
     * This is used to retrieve all activated plugins prior to option deletion.
     * 
     * @since 1.0.0
     * @var array
     */
    protected $activated_plugins_list = [];

    /**
     * Specifies the current plugin data
     * @see get_plugin_data()
     * @since 1.1.0
     * @var array
     */
    protected $current_plugin_data = [];

    /**
     * Init the Plugin Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Templates\EventList
     */
    public function initPluginEvents()
    {
        $this->setupPluginEvents();
        $this->_registerPluginEvents();
    }

    /**
     * Register the custom plugin events
     */
    protected function _registerPluginEvents()
    {
        $self = &$this;
        
        /**
         * Fires whenever a plugin is activated on a specific blog (single site)
         */
        add_action('add_option_active_plugins', function($option, $value)
        {
            $new_plugins = $this->unserialize($value);
            if (empty($new_plugins) || !is_countable($new_plugins))
                return;

            $plugin_event = 'alm_plugin_activated';

            // Trigger the current event
            do_action($plugin_event, $new_plugins, []);
        }, 10, 2);

        /**
         * Get the activated plugins before the option is deleted
         */
        add_action('delete_option', function($option)
        {
            if ('active_plugins' != $option) return;
            $this->activated_plugins_list = (array) get_option('active_plugins', []);
        });

        /**
         * Fires whenever a plugin is deactivated on a specific blog (single site)
         */
        add_action('delete_option_active_plugins', function($option)
        {
            if (empty($new_plugins) || !is_countable($new_plugins)) return;

            $plugin_event = 'alm_plugin_deactivated';

            // Trigger the current event
            do_action($plugin_event, [], $this->activated_plugins_list);
        }, 10, 2);
        
        /**
         * Fires whenever a plugin is activated or deactivated on a specific 
         * blog (single site)
         */
        add_action('update_option_active_plugins', function($old_value, $value, $option) use (&$self)
        {
            $self->_addPluginEvent($value, $old_value);
        }, 10, 3);

        /**
         * Multisite only events
         */
        if ($this->is_multisite) 
        {
            /**
             * Get all activated site-wide plugins before the option is deleted
             */
            add_action('pre_delete_site_option_active_sitewide_plugins', function($option)
            {
                $this->activated_plugins_list = (array) get_site_option($option, []);
            }, 10, 2);

            /**
             * Listen for the plugin network activation event on multisite
             */
            add_action('add_site_option_active_sitewide_plugins',
            function($option, $value, $network_id)
            {
                $new_plugins = $this->unserialize($value);
                if (empty($new_plugins) || !is_countable($new_plugins)) return;

                $plugin_event = 'alm_plugin_activated';

                // Trigger the current event
                do_action($plugin_event, $new_plugins, []);
            }, 10, 3);

            /**
             * Listen for the plugin network activation/deactivation event on multisite
             */
            add_action('update_site_option_active_sitewide_plugins',
            function($option, $value, $old_value, $network_id) use (&$self)
            {
                $self->_addPluginEvent($value, $old_value);
            }, 10, 4);

            /**
             * Listen for the plugin network deactivation event on multisite
             */
            add_action('delete_site_option_active_sitewide_plugins', function()
            {
                if (empty($new_plugins) || !is_countable($new_plugins))
                    return;

            $plugin_event = 'alm_plugin_deactivated';

            // Trigger the current event
            do_action($plugin_event, [], $this->activated_plugins_list);
            }, 10, 2);
        }

        /**
         * Check whether the plugin could not be activated and trigger the failed plugin 
         * activation event
         * 
         * @see /wp-admin/plugins.php
         * @see wp_redirect()
         */
        add_filter('wp_redirect', function($location) use (&$self)
        {
            if (!$this->is_admin && !$this->is_network_admin)
                return $location;
                
            $url_components = wp_parse_url($location);

            $page  = $self->getVar($url_components, 'path');
            $query = $self->getVar($url_components, 'query');

            if (false === strpos($page, 'plugins.php') || empty($query))
                return $location;

            $args = [];
            parse_str($query, $args);

            $plugin = $self->getVar($args, 'plugin');
            if (empty($plugin)) return $location;

            if (empty($self->getVar($args, 'charsout')) 
            || empty($self->getVar($args, 'error')) 
            || empty($self->getVar($args, 'plugin_status')))
                return $location;

            $plugin = $self->sanitizeOption($plugin);

            // Check whether the plugin has been activated
            if (
                (in_array($plugin, $this->activated_plugins_list, true)) 
                || isset($this->activated_plugins_list[$plugin])
            ) {
                do_action('alm_plugin_activated_with_error', $plugin);
            } else {
                do_action('alm_plugin_activation_failed', $plugin);
            }

            return $location;
        }, 10, 2);

        /**
         * Fires immediately after a plugin deletion attempt.
         * This triggers the right event, checking whether the 
         * plugin deletion was successful.
         * 
         * @since 1.0.0
         */
        add_action('deleted_plugin', function($plugin_file, $deleted)
        {
            if ($deleted) {
                $event = 'alm_plugin_deleted';
            } else {
                $event = 'alm_plugin_deletion_failed';
            }
            do_action($event, $plugin_file);
        }, 10, 2);
    }

    /**
     * Correctly add the plugin event during activation/deactivation of plugins
     * @see update_option()
     * @see update_site_option()
     */
    protected function _addPluginEvent($_new_plugins, $_old_plugins)
    {
        $old_plugins = $this->unserialize($_old_plugins);
        $new_plugins = $this->unserialize($_new_plugins);

        if (!is_countable($old_plugins) || !is_countable($new_plugins))
            return;

        if ($old_plugins < $new_plugins) {
            $plugin_event = 'alm_plugin_activated';
        } else {
            $plugin_event = 'alm_plugin_deactivated';
        }
        do_action($plugin_event, $new_plugins, $old_plugins);
    }

    /**
     * Setup the plugin event customizable arguments list
     * 
     * @since 1.0.0
     * 
     * @param array $args The plugin arguments provided by the event callback function
     */
    protected function setupPluginEventArgs(array $args = [])
    {
        if (empty($args))
            return;

        $defaults = [
            'blog_id'       => '',
            'is_ready'      => true,
            'wp_error'      => false,
            'blog_url'      => '',
            'object_id'     => 0,
            'blog_name'     => '',
            '_error_msg'    => '',
            'network_id'    => '',
            'plugin_info'   => '',
            'object_data'   => [],
            'network_name'  => '',
            '_count_object' => 0,
        ];

        $args = array_merge($defaults, $args);

        if (empty($this->getVar($args, 'network_name')))
            $args['network_name'] = $this->getCurrentNetworkName();

        if (empty($this->getVar($args, 'blog_id')))
            $args['blog_id'] = $this->current_blog_ID;

        if (empty($this->getVar($args, 'blog_name')))
            $args['blog_name'] = $this->getBlogName();

        if (empty($this->getVar($args, 'blog_url')))
            $args['blog_url'] = $this->sanitizeOption(
                $this->getVar($this->blog_data, 'url'),
                'url'
            );

        if (empty($this->getVar($args, 'network_id')))
            $args['network_id'] = $this->getVar($this->network_data, 'id', $this->current_network_ID);

        $this->customize_event_msg_args['plugin'] = $args;
    }

    /**
     * Setup the Super Admin events
     */
    protected function setupPluginEvents()
    {
        $this->event_list['plugins'] = [
            'title'       => 'Plugins Events',
            'group'       => 'plugin', // object

            'description' => alm__('Responsible for logging all plugins related activities such as plugin activation, deactivation, installation, uninstallation and the front-end plugin editor'),

            'events' => [
                /**
                 * Fires when the plugin activation fails
                 * 
                 * @since 1.0.0
                 */
                'alm_plugin_activation_failed' => [
                    'title'               => 'Plugin Activation Failed',
                    'action'              => 'plugin_activation_failed',
                    'event_id'            => 5071,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['activate_plugins', 'activate_plugin'],

                    /**
                     * Translation arguments
                     * 
                     * For the message, we have to specify the {@see '_count_object'} message  
                     * argument which is used to determine whether the message is singular if 1 
                     * or plural if greater than 1.
                     * 
                     * To use a translation only on the network screens, suffix the 'plural' or 
                     * 'singular' argument with the 'network' string
                     */
                    '_translate' => [
                        '_main' => [
                            'plural' => 'Tried to activate the following plugins on the site but the operation was unsuccessful, see details below',

                            'plural_network' => 'Tried to activate the following plugins on all site on the network but the operation was unsuccessful, see details below',

                            'singular_network' => 'Tried to activate a plugin on all sites on the network but the operation was unsuccessful, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Tried to activate a plugin on the site but the operation was unsuccessful, see details below',

                        '_space_start'  => '',
                        '_count_object' => ['_count_object'],
                        'plugin_info'   => ['plugin_info'],
                        '_space_end'    => '',

                        'site_id'       => ['blog_id'],
                        'site_name'     => ['blog_name'],
                        'site_url'      => ['blog_url'],
                        'network_ID'    => ['network_id'],
                        'network_name'  => ['network_name'],
                    ],
                ],

                /**
                 * Fires after a plugin has been activated with errors
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-includes/plugins.php
                 * @see register_activation_hook()
                 */
                'alm_plugin_activated_with_error' => [
                    'title'               => 'Plugin Activated With Errors',
                    'action'              => 'plugin_activated_with_errors',
                    'event_id'            => 5072,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['activate_plugins', 'activate_plugin'],

                    'wp_options' => [
                        'active_plugins',
                        'recently_activated',
                    ],
                    'wp_site_options' => [
                        'recently_activated',
                        'active_sitewide_plugins',
                    ],

                    '_translate' => [
                        '_main' => [
                            'plural'           => 'Activated the following plugins on the site which triggers some errors during the process, see details below',
                            'plural_network'   => 'Activated the following plugins on all sites on the network which triggers some errors during the process, see details below',
                            'singular_network' => 'Activated a plugin on all sites on the network which triggers some errors during the process, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Activated a plugin on the site which triggers some errors during the process, see details below',

                        '_space_start'  => '',
                        '_count_object' => ['_count_object'],
                        'total_count'   => '_ignore_',
                        'plugin_info'   => ['plugin_info'],
                        '_space_end'    => '',

                        'site_id'       => ['blog_id'],
                        'site_name'     => ['blog_name'],
                        'site_url'      => ['blog_url'],
                        'network_ID'    => ['network_id'],
                        'network_name'  => ['network_name'],
                    ],
                ],

                /**
                 * Fires after a plugin has been activated
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-includes/plugins.php
                 * @see register_activation_hook()
                 */
                'alm_plugin_activated' => [
                    'title'               => 'Plugin Activated',
                    'action'              => 'plugin_activated',
                    'event_id'            => 5073,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['activate_plugins', 'activate_plugin'],

                    'wp_options' => [
                        'active_plugins',
                        'recently_activated',
                    ],
                    'wp_site_options' => [
                        'recently_activated',
                        'active_sitewide_plugins',
                    ],

                    '_translate' => [
                        '_main' => [
                            'plural'           => 'Activated the following plugins on the site, see details below',
                            'plural_network'   => 'Activated the following plugins on all sites on the network, see details below',
                            'singular_network' => 'Activated a plugin on all sites on the network, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Activated a plugin on the site, see details below',

                        '_space_start'  => '',
                        '_count_object' => ['_count_object'],
                        'total_count'   => '_ignore_',
                        'plugin_info'   => ['plugin_info'],
                        '_space_end'    => '',

                        'site_id'       => ['blog_id'],
                        'site_name'     => ['blog_name'],
                        'site_url'      => ['blog_url'],
                        'network_ID'    => ['network_id'],
                        'network_name'  => ['network_name'],
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],

                /**
                 * Fires after a plugin has been deactivated
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-includes/plugins.php
                 * @see register_deactivation_hook()
                 */
                'alm_plugin_deactivated' => [
                    'title'               => 'Plugin Deactivated',
                    'action'              => 'plugin_deactivated',
                    'event_id'            => 5074,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['deactivate_plugins', 'deactivate_plugin'],

                    'wp_options' => [
                        'active_plugins',
                        'recently_activated',
                    ],
                    'wp_site_options' => [
                        'recently_activated',
                        'active_sitewide_plugins',
                    ],

                    '_translate' => [
                        '_main' => [
                            'plural'           => 'Deactivated the following plugins from the site, see details below',
                            'plural_network'   => 'Deactivated the following plugins from all sites on the network, see details below',
                            'singular_network' => 'Deactivated a plugin from all sites on the network, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Deactivated a plugin from the site, see details below',

                        '_space_start'  => '',
                        '_count_object' => ['_count_object'],
                        'total_count'   => '_ignore_',
                        'plugin_info'   => ['plugin_info'],
                        '_space_end'    => '',

                        'site_id'       => ['blog_id'],
                        'site_name'     => ['blog_name'],
                        'site_url'      => ['blog_url'],
                        'network_ID'    => ['network_id'],
                        'network_name'  => ['network_name'],
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],

                /**
                 *  Fires in uninstall_plugin() immediately before the plugin is uninstalled.
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-includes/plugins.php
                 * @see delete_plugins()
                 */
                'alm_plugin_deleted' => [
                    'title'               => 'Plugin Deleted',
                    'action'              => 'plugin_deleted',
                    'event_id'            => 5075,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['delete_plugins', 'delete_plugin'],

                    'transient'           => ['update_plugins'],
                    'wp_options'          => [ 'uninstall_plugins'],
                    'site_transient'      => ['update_plugins'],
                    'wp_site_options'     => ['uninstall_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural'           => 'Deleted the following plugins from the site, see details below',
                            'plural_network'   => 'Deleted the following plugins from all sites on the network, see details below',
                            'singular_network' => 'Deleted a plugin from all sites on the network, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Deleted a plugin from the site, see details below',

                        '_space_start'  => '',
                        '_count_object' => ['_count_object'],
                        'total_count'   => '_ignore_',
                        'plugin_info'   => ['plugin_info'],
                        '_space_end'    => '',

                        'site_id'       => ['blog_id'],
                        'site_name'     => ['blog_name'],
                        'site_url'      => ['blog_url'],
                        'network_ID'    => ['network_id'],
                        'network_name'  => ['network_name'],
                    ],
                ],

                /**
                 *  Fires immediately after an unsuccessful plugin deletion attempt
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-includes/plugins.php
                 * @see delete_plugins()
                 */
                'alm_plugin_deletion_failed' => [
                    'title'               => 'Plugin Deletion Failed',
                    'action'              => 'plugin_deletion_failed',
                    'event_id'            => 5076,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['delete_plugins', 'delete_plugin'],

                    'transient'           => ['update_plugins'],
                    'wp_options'          => [ 'uninstall_plugins'],
                    'site_transient'      => ['update_plugins'],
                    'wp_site_options'     => ['uninstall_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural'           => 'Tried to delete the following plugins from the site but the attempt was unsuccessful, see details below',
                            'plural_network'   => 'Tried to delete the following plugins from all sites on the network but the attempt was unsuccessful, see details below',
                            'singular_network' => 'Tried to delete a plugin from all sites on the network but the attempt was unsuccessful, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Tried to delete a plugin from the site but the attempt was unsuccessful, see details below',

                        '_space_start'  => '',
                        '_count_object' => ['_count_object'],
                        'total_count'   => '_ignore_',
                        'plugin_info'   => ['plugin_info'],
                        '_space_end'    => '',

                        'site_id'       => ['blog_id'],
                        'site_name'     => ['blog_name'],
                        'site_url'      => ['blog_url'],
                        'network_ID'    => ['network_id'],
                        'network_name'  => ['network_name'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the plugin info after activation/deactivation
     * @return string
     */
    protected function getPluginInfo($plugin_file = '')
    {
        $line_break = $this->getEventMsgLineBreak();
        if ( empty($plugin_file))
            return sprintf(
                'Plugin Name: Unknown%sPlugin Location: Unknown',
                $line_break
            );

        $plugin_file = $this->sanitizeOption($plugin_file);
        $plugin_path = wp_normalize_path(WP_PLUGIN_DIR . '/' . $plugin_file);
        $basename    = basename($plugin_file, '.php');
        $bailer      = sprintf('Plugin Name: %s', $this->sanitizeOption($basename));

        if (!file_exists($plugin_path))
            return sprintf(
                '%s%sPlugin Location: Unknown',
                $bailer, $line_break
            );

        $plugin_data = get_plugin_data($plugin_path);
        if (empty($plugin_data)) return $bailer;

        // Retrieve the plugin dir
        if (false !== strpos($plugin_file, '/'))
            $plugin_path = wp_normalize_path(pathinfo($plugin_path, PATHINFO_DIRNAME));

        // Setup the plugin object data var
        $this->current_plugin_data = &$plugin_data;

        $self        = &$this;
        $plugin_info = '';

        $data_args   = array_merge(
            ['Title', 'Version', 'Author'],
            $this->isSuperMode() ? ['RequiresWP', 'RequiresPHP'] : []
        );

        foreach ($data_args as $info ) {
            $data    = $self->getVar($plugin_data, $info, '');
            $no_data = empty($data);

            // Fallback to the plugin name if title is not given
            if ($no_data && 'Title' === $info )
                $data = $self->getVar($plugin_data, 'Name', '');

            if ($no_data && in_array($info, ['RequiresWP', 'RequiresPHP'], true))
                continue;

            if ($no_data)
                $data = 'Unknown';

            if (!$no_data)
                $data = wp_kses($data, $this->getEventMsgHtmlList());

            $plugin_info .= sprintf('Plugin %s: %s%s', $info, $data, $line_break);

            if ('Title' === $info )
                $plugin_info .= sprintf(
                    'Plugin Location: %s%s', esc_html($plugin_path),
                    $line_break
                );
        }

        return $this->rtrim($plugin_info, $line_break);
    }

    /**
     * Get the plugin event object ID
     * 
     * The object ID represents the 'active_plugins' option ID in the
     * 
     * {@see WordPress options table} on single site or 
     * {@see WordPress sitemeta table} on multisite
     * 
     * @return int
     */
    protected function getPluginEventObjectId()
    {
        $table_prefix = $this->getBlogPrefix();

        $field        = 'option_id';
        $table        = $table_prefix . 'options';
        $field_key    = 'option_name';
        $field_value  = 'active_plugins';

        if ($this->is_network_admin) {
            $field       = 'meta_id';
            $table       = $table_prefix . 'sitemeta';
            $field_key   = 'meta_key';
            $field_value = 'active_sitewide_plugins';

            $object_id = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT $field FROM $table WHERE $field_key = %s AND site_id = %d LIMIT 1",
                $field_value,
                $this->main_site_ID
            ));
        } else {
            $object_id = (int) $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT $field FROM $table WHERE $field_key = %s LIMIT 1",
                $field_value
            ));
        }
        return $object_id;
    }

    /**
     * Get the uninstalled plugins option ID
     * @return int
     */
    protected function getUninstalledPluginsOptionId()
    {
        $table_prefix = $this->getBlogPrefix();
        $table        = $table_prefix . 'options';
        
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT option_id FROM $table WHERE option_name = %s LIMIT 1",
            'uninstall_plugins',
        ));
    }

    /**
     * Get the plugin event info
     * 
     * @param  array   $plugins List of plugin's basename whose info should be retrieved
     * @return string           The specified plugins info.
     */
    protected function getPluginEventObjectInfo(array $plugins)
    {
        $repeater    = (count($plugins) > 1) ? 2 : 1;
        $line_break  = str_repeat($this->getEventMsgLineBreak(), $repeater);
        $plugin_info = '';

        if ($this->is_network_admin) {
            foreach ($plugins as $new_plugin => $timestamp) {
                $plugin_info .= $this->getPluginInfo($new_plugin) . $line_break;
            }
        } else {
            foreach ($plugins as $new_plugin) {
                $plugin_info .= $this->getPluginInfo($new_plugin) . $line_break;
            }
        }
        return $plugin_info;
    }
}