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
     * Holds the plugins' version before an upgrade/update process is run.
     * Plugins' basename is used as the key and version as value.
     * @since 1.0.0
     * @var array
     */
    protected $plugin_versions = [];

    /**
     * Holds the plugins to update during an upgrade/update action
     * @since 1.0.0
     * @var array
     */
    protected $plugins_to_update = [];

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
        add_action('add_option_active_plugins', function($option, $value) use (&$self)
        {
            $new_plugins = $self->unserialize($value);
            if (empty($new_plugins) || !is_countable($new_plugins))
                return;

            $plugin_event = 'alm_plugin_activated';

            // Trigger the current event
            do_action($plugin_event, $new_plugins, []);
        }, 10, 2);

        /**
         * Get the activated plugins before the option is deleted
         */
        add_action('delete_option', function($option) use (&$self)
        {
            if ('active_plugins' != $option) return;
            $self->activated_plugins_list = (array) get_option('active_plugins', []);
        });

        /**
         * Fires whenever a plugin is deactivated on a specific blog (single site)
         */
        add_action('delete_option_active_plugins', function($option) use ($self)
        {
            if (empty($new_plugins) || !is_countable($new_plugins)) return;

            $plugin_event = 'alm_plugin_deactivated';

            // Trigger the current event
            do_action($plugin_event, [], $self->activated_plugins_list);
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
            add_action('pre_delete_site_option_active_sitewide_plugins', function($option) use (&$self)
            {
                $self->activated_plugins_list = (array) get_site_option($option, []);
            }, 10, 2);

            /**
             * Listen for the plugin network activation event on multisite
             */
            add_action('add_site_option_active_sitewide_plugins',
            function($option, $value, $network_id) use (&$self)
            {
                $new_plugins = $self->unserialize($value);
                if (empty($new_plugins) || !is_countable($new_plugins))
                    return;

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
            add_action('delete_site_option_active_sitewide_plugins', function() use (&$self)
            {
                if (empty($new_plugins) || !is_countable($new_plugins))
                    return;

            $plugin_event = 'alm_plugin_deactivated';

            // Trigger the current event
            do_action($plugin_event, [], $self->activated_plugins_list);
            }, 10, 2);
        }

        /**
         * Check whether the plugin could not be activated and trigger the failed plugin 
         * activation event
         * 
         * @see /wp-admin/plugins.php
         * @see activate_plugin()
         * @see wp_redirect()
         */
        add_filter('wp_redirect', function($location) use (&$self)
        {
            if (!$self->is_admin && !$self->is_network_admin)
                return $location;
                
            $url_components = wp_parse_url($location);

            $page  = $self->getVar($url_components, 'path');
            $query = $self->getVar($url_components, 'query');

            if (false === strpos($page, 'plugins.php') || empty($query))
                return $location;

            $args = [];
            parse_str($query, $args);

            $plugin = urldecode_deep(wp_unslash($self->getVar($args, 'plugin')));
            if (empty($plugin)) return $location;

            $has_error   = false;
            $error_nonce = $self->getVar($args, '_error_nonce');
            
            // Check whether a fatal error was triggered during the plugin activation
            if (!empty($error_nonce)) {
                $has_error = true;
            }
            elseif (!empty($self->getVar($args, 'charsout')) 
            && !empty($self->getVar($args, 'plugin_status')))
            {
                $has_error = true;
            }

            if (!$has_error || empty($self->getVar($args, 'error')))
                return $location;

            $plugin = $self->sanitizeOption($plugin);

            // Check whether the plugin has been activated
            if ( empty($error_nonce) 
                &&
                (in_array($plugin, $self->activated_plugins_list, true)) 
                || isset($self->activated_plugins_list[$plugin])
            ) {
                do_action('alm_plugin_activated_with_error', $plugin);
            } else {
                do_action('alm_plugin_activation_failed', $plugin);
            }

            return $location;
        }, 10, 2);

        /**
         * Setup the plugin data before it is deleted
         */
        add_action('delete_plugin', function($plugin_file) use (&$self)
        {
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
            $self->current_plugin_data = get_plugin_data( $plugin_path, true, false);
        });

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

        /**
         * Get the plugin version before upgrading/updating
         * @see upgrader_package_options filter hook
         */
        add_filter('upgrader_package_options', function($options) use (&$self)
        {
            $hook_extra = $self->getVar($options, 'hook_extra', []);
            if (empty($hook_extra))
                return $options;

            $type   = $self->getVar($hook_extra, 'type');
            $action = $self->getVar($hook_extra, 'action');

            if ('plugin' != $type) return $options;

            if (!$self->isPluginUpdateActionValid($action))
                return $options;

            $plugin_basename = $self->getVar($hook_extra, 'plugin');
            if (empty($plugin_basename)) return $options;

            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_basename;
            $plugin_data = get_plugin_data($plugin_file, false, false);

            // Set the plugin version
            $self->plugin_versions[$plugin_basename] = $self->sanitizeOption($self->getVar($plugin_data, 'Version', 'Unknown'));

            // Also get the plugins to update
            $update_plugins = get_site_transient('update_plugins');
            $response = $self->getVar($update_plugins, 'response');

            if (empty($response)) return $options;

            foreach ((array) $response as $_plugin_file => $_plugin_data) {
                $self->plugins_to_update[$_plugin_file] = $_plugin_data;
            }

            return $options;
        });

        /**
         * A simple hack to check for plugin installation/update errors
         * @see /wp-admin/update.php
         */
        add_action('admin_footer', function() use (&$self)
        {
            if (!isset($_REQUEST['action'])) return;

            global $upgrader, $api, $file_upload, $url, $plugin, $plugins;
            
            if (!is_object($api))
                $api = [];

            if (!is_object($file_upload))
                $file_upload = [];

            if (!is_object($upgrader)) return;

            $action = $self->sanitizeOption(wp_unslash($_REQUEST['action']));
            if (!in_array($action, $self->pluginEventActions(), true))
                return;

            $skin    = $self->getVar($upgrader, 'skin');
            $strings = $self->getVar($upgrader, 'strings');
            $options = $self->getVar($upgrader, 'options');

            $type    = $self->getVar($options,  'type');
            $title   = $self->getVar($options,  'title');
            $result  = $self->getVar($skin,     'result');
            $url_alt = $self->getVar($options,  'url');

            /**
             * Check whether the plugin installation response is successful or not.
             * An array specifies that the result is valid.
             */
            if ($self->isPluginUpgraderResultValid($upgrader))
                return;

            $action = current(explode('-', sanitize_text_field(wp_unslash($_REQUEST['action']))));

            if (!empty($plugins) && is_array($plugins)) {
                $plugins = array_map('sanitize_text_field', $plugins);
            } else {
                if (!empty($plugin)) {
                    $plugins = [$plugin];
                } else {
                    $plugins = [];
                }
            }

            $hook_extra = compact('url', 'url_alt', 'type', 'title', 'plugins', 'action');

            /**
             * Let's be specific about the plugin installation error.
             */
            if (in_array($action, ['update-selected', 'upgrade-plugin'], true))
            {
                do_action('alm_upgrader_process_failed', $upgrader, $hook_extra);
                return;
            }

            do_action('alm_plugin_installation_failed', $api, $file_upload, $hook_extra);
        });
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
     * Get list of valid plugin event actions
     * @see /wp-admin/update.php
     * @return array
     */
    public function pluginEventActions()
    {
        return ['update-selected', 'upgrade-plugin', 'activate-plugin', 'install-plugin', 'upload-plugin'];
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
     * Setup the plugin events
     */
    protected function setupPluginEvents()
    {
        $this->event_list['plugins'] = [
            'title'       => 'Plugins Events',
            'group'       => 'plugin', // object

            'description' => alm__('Responsible for logging all plugins related activities such as plugin activation, deactivation, installation, uninstallation, upgrade, upload and the front-end plugin editor'),

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

                    'error_flag'          => true,
                    'event_successor'     => ['plugin', 'alm_plugin_activated'],

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
                            'plural' => 'Tried to activate the following plugins on the site but the attempt was unsuccessful because it triggered a fatal error, see details below',

                            'plural_network' => 'Tried to activate the following plugins on all site on the network but the attempt was unsuccessful because it triggered a fatal error, see details below',

                            'singular_network' => 'Tried to activate a plugin on all sites on the network but the attempt was unsuccessful because it triggered a fatal error, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Tried to activate a plugin on the site but the attempt was unsuccessful because it triggered a fatal error, see details below',

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

                    'error_flag'          => true,
                    'event_successor'     => ['plugin', 'alm_plugin_activated'],

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
                    'logged_in_user_caps' => ['delete_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_options'          => [ 'uninstall_plugins'],
                    'wp_site_transient'   => ['update_plugins'],
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
                 */
                'alm_plugin_deletion_failed' => [
                    'title'               => 'Plugin Deletion Failed',
                    'action'              => 'plugin_deletion_failed',
                    'event_id'            => 5076,
                    'severity'            => 'critical',

                    'error_flag'          => true,
                    'event_successor'     => ['plugin', 'alm_plugin_deleted'],

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['delete_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_options'          => [ 'uninstall_plugins'],
                    'wp_site_transient'   => ['update_plugins'],
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

                /**
                 *  Fires immediately after upgrading the plugin(s)
                 * 
                 * @since 1.0.0
                 */
                'upgrader_process_complete' => [
                    'title'               => 'Plugin Updated', // Plural: Plugins Updated
                    'action'              => 'plugin_updated', // Plural: plugins_updated
                    'event_id'            => 5077,
                    'severity'            => 'critical',
                    
                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['update_plugins', 'install_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Updated the following plugins, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Updated a plugin, see details below',

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
                 *  Fires immediately after the plugin(s) update could not be completed
                 * 
                 * @since 1.0.0
                 */
                'alm_upgrader_process_failed' => [
                    'title'               => 'Plugin Update Failed',
                    'action'              => 'plugin_updated_failed',
                    'event_id'            => 5078,
                    'severity'            => 'error',

                    'error_flag'          => true,
                    'event_successor'     => ['plugin', 'upgrader_process_complete'],
                    
                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['update_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Tried to update the following plugins but the attempt was unsuccessful, see details below',
                        ],
                    ],

                    'message' => [
                        '_main' => 'Tried to update a plugin but the attempt was unsuccessful, see details below',

                        '_space_start'              => '',
                        '_count_object'             => ['_count_object'],
                        'total_count'               => '_ignore_',
                        'installation_request_url'  => ['installation_request_url'],
                        'plugin_info'               => ['plugin_info'],
                        '_space_end'                => '',

                        'site_id'                   => ['blog_id'],
                        'site_name'                 => ['blog_name '],
                        'site_url'                  => ['blog_url'],
                        'network_ID'                => ['network_id'],
                        'network_name'              => ['network_name'],
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],

                /**
                 *  Fires after the plugin installation has completed
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-admin/wp-includes/class-wp-plugin-upgrader.php
                 */
                'alm_plugin_installed' => [
                    'title'               => 'Plugin Installed',
                    'action'              => 'plugin_installed',
                    'event_id'            => 5079,
                    'severity'            => 'critical',
                    
                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['install_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Installed the following plugins, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Installed a plugin, see details below',

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
                 *  Fires after the plugin installation failed
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-admin/wp-includes/class-wp-plugin-upgrader.php
                 */
                'alm_plugin_installation_failed' => [
                    'title'               => 'Plugin Installation Failed',
                    'action'              => 'plugin_installation_failed',
                    'event_id'            => 5080,
                    'severity'            => 'critical',

                    'error_flag'          => true,
                    'event_successor'     => ['plugin', 'alm_plugin_installed'],
                    
                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['install_plugins', 'upload_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Tried to install the following plugins but the attempt was unsuccessful, see details below',
                        ],
                    ],

                    'message' => [
                        '_main' => 'Tried to install a plugin but the attempt was unsuccessful, see details below',

                        '_space_start'             => '',
                        '_count_object'            => ['_count_object'],
                        'installation_type'        => ['installation_type'],
                        'package_location'         => ['package_location'],
                        'installation_request_url' => ['installation_request_url'],
                        '_space_end'               => '',

                        'site_id'                  => ['blog_id'],
                        'site_name'                => ['blog_name'],
                        'site_url'                 => ['blog_url'],
                        'network_ID'               => ['network_id'],
                        'network_name'             => ['network_name'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 *  Fires after the plugin upload has completed
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-admin/wp-includes/class-wp-plugin-upgrader.php
                 */
                'alm_plugin_uploaded' => [
                    'title'               => 'Plugin Uploaded',
                    'action'              => 'plugin_uploaded',
                    'event_id'            => 5081,
                    'severity'            => 'critical',
                    
                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['upload_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Uploaded the following plugins, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Uploaded a plugin, see details below',

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

        $plugin_data = get_plugin_data($plugin_path, true, false);
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
                /**
                 * Make sure that the $timestamp var is not the actual plugin basename
                 */
                if (false !== strpos($timestamp, '.php'))
                    $new_plugin = $timestamp;

                $plugin_info .= $this->getPluginInfo($new_plugin) . $line_break;
            }
        } else {
            foreach ($plugins as $index => $new_plugin) {
                /**
                 * Make sure that the $index var is not the actual plugin basename
                 */
                if (false !== strpos($index, '.php'))
                    $new_plugin = $index;

                $plugin_info .= $this->getPluginInfo($new_plugin) . $line_break;
            }
        }
        return $plugin_info;
    }

    /**
     * Check whether the plugin is being upgrader/updated
     * @param  string $action Specifies the action to check for
     * @return bool
     */
    public function isPluginUpdateActionValid($action = '')
    {
        return in_array($action, ['update', 'upgrade'], true);
    }

    /**
     * Check whether the plugin upgrader result is valid
     * @return bool
     */
    public function isPluginUpgraderResultValid($plugin_upgrader)
    {
        $result = $this->getVar($plugin_upgrader, 'result', false);
        return (!empty($result) && is_array($result));
    }

    /**
     * Get the plugin request url used for upgrading, updating or installing 
     * the plugin
     * @return string
     */
    protected function getPluginRequestUrl($hook_extra)
    {
        $url                      = $this->getVar($hook_extra, 'url', '');
        $url_alt                  = $this->getVar($hook_extra, 'url_alt', '#');
        $request_url              = empty($url) ? $url_alt : $url;

        $installation_request_url = (
            false  === strpos($request_url, 'http://') 
            && false === strpos($request_url, 'https://')) 
            ? 
            esc_url_raw(self_admin_url($url)) : esc_url_raw($request_url);

        return $installation_request_url;
    }
}