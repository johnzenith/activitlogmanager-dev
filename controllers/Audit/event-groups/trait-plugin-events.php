<?php
namespace ALM\Controllers\Audit\Events\Groups;

use \WP_Error;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

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
     * Specifies the plugin activation event status, whether we need to trigger the event.
     * @since 1.0.0
     * @var array
     */
    protected $plugin_activation_event = [];

    /**
     * Specifies the current plugin data
     * @see get_plugin_data()
     * @since 1.1.0
     * @var array
     */
    protected $current_plugin_data = [];

    /**
     * Holds the plugin previous data before an upgrade.
     * Useful when upgrading a plugin with a zip package.
     * @since 1.0.0
     * @var array
     */
    protected $previous_plugin_info = [];

    /**
     * Holds the plugins' version before an upgrade/update process is run.
     * Plugins' basename is used as the key and version as value.
     * @since 1.0.0
     * @var array
     */
    protected $plugin_versions = [];

    /**
     * Holds information about the plugins to update during an upgrade/update action
     * @since 1.0.0
     * @var array
     */
    protected $plugins_to_update = [];

    /**
     * Init the Plugin Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     */
    protected function initPluginEvents()
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
            // exit(var_dump($option));

            $new_plugins = $self->unserialize($value);
            if (empty($new_plugins) || !is_countable($new_plugins))
                return;

            $plugin_event                 = 'alm_plugin_activated';
            $self->activated_plugins_list = array_merge($self->activated_plugins_list, $new_plugins);

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
         * Fires whenever a plugin is deleted on a specific blog (single site)
         */
        add_action('delete_option_active_plugins', function() use ($self)
        {
            $plugin_event = 'alm_plugin_deleted';

            // Trigger the current event
            do_action($plugin_event, $self->activated_plugins_list);
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

                $plugin_event                 = 'alm_plugin_activated';
                $self->activated_plugins_list = array_merge($self->activated_plugins_list, $new_plugins);

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
             * Listen for the plugin network deletion event on multisite
             */
            add_action('delete_site_option_active_sitewide_plugins', function() use (&$self)
            {
                $plugin_event = 'alm_plugin_deleted';

                // Trigger the current event
                do_action($plugin_event, $self->activated_plugins_list);
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

            $bail  = false;
            $page  = $self->getVar($url_components, 'path');
            $query = $self->getVar($url_components, 'query');

            if (false === strpos($page, 'plugins.php') || empty($query)) {
                $bail = true;
            }

            $args = [];
            parse_str($query, $args);

            $plugin = urldecode_deep(wp_unslash($self->getVar($args, 'plugin')));
            if (empty($plugin)) {
                $bail = true;
            }

            $has_error   = false;
            $error_nonce = $self->getVar($args, '_error_nonce');
            
            // Check whether a fatal error was triggered during the plugin activation
            if (!empty($self->getVar($args, 'charsout')) 
            && !empty($self->getVar($args, 'plugin_status')))
            {
                $has_error = true;
            }

            if (empty($self->activated_plugins_list)) {
                $active_plugins = $self->is_network_admin ?
                    get_site_option('active_plugins', []) : get_option('active_plugins', []);

                $self->activated_plugins_list = (array) $active_plugins;
            }

            // During plugin activation, WordPress performs a redirect with 
            // '_error_nonce' query var
            if ($bail || (!$has_error && !empty($error_nonce))) {
                // Fire the plugin activation hook
                $self->maybeFirePluginActivationEvent();

                return $location; 
            }

            if (!$has_error || empty($self->getVar($args, 'error')))
                return $location;

            // Fire the plugin activation hook.
            // This is applicable when activating list of selected plugins
            $self->maybeFirePluginActivationEvent();

            $plugin = $self->sanitizeOption($plugin);

            // Check whether the plugin has been activated with errors
            if ( 0 === (int) did_action('alm_plugin_activated_with_error') 
                && 
                (
                    in_array($plugin, $self->activated_plugins_list, true) 
                    || isset($self->activated_plugins_list[$plugin])
                )
            ) {
                do_action('alm_plugin_activated_with_error', $plugin);
            }

            // Check whether the plugin could not be activated successfully
            if ( 0 === (int) did_action('alm_plugin_activation_failed') 
            && (
                    !in_array($plugin, $self->activated_plugins_list, true)
                    && !isset($self->activated_plugins_list[$plugin])
                )
            ) {
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
            $self->current_plugin_data[$plugin_file] = $this->getPluginData($plugin_path, true);
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
            $event = $deleted ? 'alm_plugin_deleted' : 'alm_plugin_deletion_failed';
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

                $action = $self->getVar($hook_extra, 'action');
                $package = $self->getVar($options, 'package');

            /**
             * Get the plugin data before it is upgraded by using an uploaded zip package
             */
            if (!empty($package)) {
                $package_file = pathinfo(wp_normalize_path($package), PATHINFO_FILENAME);

                $destination  = wp_normalize_path(trailingslashit(WP_PLUGIN_DIR) . $package_file);
                $plugin_data  = $this->getPluginDataByRemoteDestination($destination);
                $plugin_name  = $this->getVar($plugin_data, 'Name', basename($package));

                $this->previous_plugin_info[$plugin_name] = $plugin_data;
            }

            /**
             * Currently, the $hook_extra arguments may not contain the $type and $action 
             * data for the plugin upgrade, let's bail out
             */
            $bail_plugin_action = true;
            if (!$bail_plugin_action && !$self->isPluginUpdateActionValid($action))
                return $options;

            $plugin_basename = $self->getVar($hook_extra, 'plugin');
            if (empty($plugin_basename)) return $options;

            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_basename;
            $plugin_data = $this->getPluginData($plugin_file);

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
             * 
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

            $upgrader_skin   = $this->getVar($upgrader, 'skin');
            $wp_error        = $this->getVar($upgrader_skin, 'result');

            $new_plugin_data = $this->getVar($upgrader, 'new_plugin_data', []);
            if (empty($new_plugin_data)) {
                $new_plugin_data = '_ignore_';
            }

            $_placeholder_values   = [];
            $installed_plugin_data = '_ignore_';
            if (is_wp_error($wp_error)) {
                $folder                = $wp_error->get_error_data('folder_exists');
                $installed_plugin_data = $this->getPluginDataByRemoteDestination($folder);

                if (empty($installed_plugin_data)) {
                    $installed_plugin_data = 'Not available';
                } else {
                    $_placeholder_values = $this->getVar($installed_plugin_data, 'Name', '');

                    if (empty($_placeholder_values)) {
                        $_placeholder_values = [];
                    } else {
                        $_placeholder_values = [ $_placeholder_values ];
                    }
                }
            }
            
            $errors          = (array) $this->getVar($wp_error, 'errors', []);
            $error_data      = (array) $this->getVar($wp_error, 'error_data', []);
            $error_list      = [];

            if (is_wp_error($wp_error)) {
                foreach ($errors as $error_code => $error) {
                    if (!empty($error_code) && is_array($error)) {
                        $error_list[$error_code] = implode( ', ', $error ) . '.';
                    }
                }

                foreach ($error_data as $error_code => $data) {
                    if (!empty($error_code) 
                    && !empty($data) 
                    && is_string($data) 
                    && isset($error_list[$error_code]))
                    {
                        $error_list[$error_code] .= ' ' . $data;

                        // Get the plugin data
                        $assume_plugin_path = wp_normalize_path($data);
                        if ($assume_plugin_path && false !== strpos($data, WP_PLUGIN_DIR)) {
                            $lookup_plugin_data = $this->getPluginDataByRemoteDestination($assume_plugin_path);

                            if (!empty($lookup_plugin_data)) {
                                $plugin_alt_basename = $this->getVar($lookup_plugin_data, 'Name');

                                $this->current_plugin_data[$plugin_alt_basename] = $lookup_plugin_data;
                            }
                        }
                    }
                }
            }

            $error_info = implode( $this->getEventMsgErrorChar(), $error_list );

            // Replace occurrences of multiple periods '..'
            $error_info = str_replace('..', '.', $error_info);

            if (strlen($error_info) <= 6) {
                $error_info = 'Not available';
            }

            do_action('alm_plugin_installation_failed', [
                'api'                   => $api,
                'file_upload'           => $file_upload,
                'hook_extra'            => $hook_extra, 
                'error_info'            => $error_info, 
                'new_plugin_data'       => $new_plugin_data,
                '_placeholder_values'   => $_placeholder_values,
                'installed_plugin_data' => $installed_plugin_data,
            ]);
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
            $plugin_event                 = 'alm_plugin_activated';
            $activated_plugins            = array_merge($this->activated_plugins_list, $new_plugins);
            $this->activated_plugins_list = $activated_plugins;

            if (!empty($this->getVar($_POST, 'checked', []))) {
                /**
                 * Setup the plugin activation event args
                 */
                $this->plugin_activation_event = [
                    'args'  => [$new_plugins, $old_plugins],
                    'event' => $plugin_event,
                ];

                return;
            }            
        }
        else {
            $plugin_event = 'alm_plugin_deactivated';
        }

        do_action($plugin_event, $new_plugins, $old_plugins);
    }

    /**
     * Fire the plugin activation event hook if available
     */
    protected function maybeFirePluginActivationEvent()
    {
        if (empty($this->plugin_activation_event))
            return;

        $args             = $this->getVar($this->plugin_activation_event, 'args', []);
        $plugins          = isset($_POST['checked']) ? (array) wp_unslash($_POST['checked']) : array();
        $new_plugins      = [];
        $old_plugins      = [];
        $last_plugin      = '';
        $activation_event = $this->getVar($this->plugin_activation_event, 'event', '_alm_no_action');

        // Bail out if the {args} variable which contains the new plugin data is empty
        if (empty($args)) 
            return;

        if (!empty($plugins)) {
            $last_plugin = end($plugins);
        }

        // When activating list of selected plugins, make sure the plugin activation 
        // event is fired just once
        if (empty($last_plugin)) {
            if (0 === did_action($activation_event)) {
                $new_plugins = $this->getVar($args, 0, []);
                $old_plugins = $this->getVar($args, 1, []);
            }
        }
        else {            
            $last_plugin  = urldecode($last_plugin);
            $old_plugins  = $this->getVar($args, 1, []);

            if (in_array($last_plugin, $this->activated_plugins_list, true) 
            || isset($this->activated_plugins_list[$last_plugin]))
            {
                // Get the newly activated plugins
                $new_plugins = [];
                foreach ($plugins as $plugin)
                {
                    if (isset($this->activated_plugins_list[$plugin]) 
                    || in_array($plugin, $this->activated_plugins_list, true))
                    {
                        if ($this->is_network_admin) {
                            $new_plugins[$plugin] = $this->getVar($this->activated_plugins_list, $plugin, 1);

                            // Unset the plugin from the previous activated plugin list
                            unset($old_plugins[$plugin]);
                        } else {
                            $new_plugins[] = $plugin;

                            // Unset the plugin from the previous activated plugin list
                            $old_plugin_key = array_search($plugin, $plugins);
                            unset($old_plugins[$old_plugin_key]);
                        }
                    }
                }
            }
        }

        // Only fire new plugin activation event when a new plugin is available
        if (empty($new_plugins)) return;

        do_action( $activation_event, $new_plugins, $old_plugins );
    }

    /**
     * Get list of valid plugin event actions
     * @see /wp-admin/update.php
     * @return array
     */
    public function pluginEventActions()
    {
        return [
            'update-selected',
            'upgrade-plugin',
            'activate-plugin',
            'install-plugin',
            'upload-plugin',
        ];
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
            'title'           => 'Plugins Events',
            'group'           => 'plugin', // object
            'object_id_label' => 'Option ID',

            'description'     => alm__('Responsible for logging all plugins related activities such as plugin activation, deactivation, installation, uninstallation, upgrade, upload and the front-end plugin editor'),

            'events' => [
                /**
                 * Fires when the plugin activation fails
                 * 
                 * @since 1.0.0
                 */
                'alm_plugin_activation_failed' => [
                    'title'               => 'Plugin Activation Failed',
                    'action'              => 'plugin_activation_failed',
                    'event_id'            => 5201,
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
                            'plural' => 'Tried to activate the following plugins on the site but the attempt was unsuccessful because it triggered a fatal error, see details below:',

                            'plural_network' => 'Tried to activate the following plugins on all site on the network but the attempt was unsuccessful because it triggered a fatal error, see details below:',

                            'singular_network' => 'Tried to activate a plugin (%s) on all sites on the network but the attempt was unsuccessful because it triggered a fatal error, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Tried to activate a plugin (%s) on the site but the attempt was unsuccessful because it triggered a fatal error, see details below:',

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
                    'title'               => 'Plugin Activated With Error',
                    'action'              => 'plugin_activated_with_error',
                    'event_id'            => 5202,
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
                            'plural'           => 'Activated the following plugins on the site which triggers some errors during the process, see details below:',
                            'plural_network'   => 'Activated the following plugins on all sites on the network which triggers some errors during the process, see details below:',
                            'singular_network' => 'Activated a plugin (%s) on all sites on the network which triggers some errors during the process, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Activated a plugin (%s) on the site which triggers some errors during the process, see details below',

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
                    'event_id'            => 5203,
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
                            /**
                             * @todo Add context string to the site (the site name/title)
                             * 
                             * Example: 'Activated a plugin (%s) on the site [(%s)], see details below:'
                             */
                            'plural'           => 'Activated the following plugins on the site, see details below:',
                            'plural_network'   => 'Activated the following plugins on all sites on the network, see details below:',
                            'singular_network' => 'Activated a plugin (%s) on all sites on the network, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Activated a plugin (%s) on the site, see details below:',

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
                    'event_id'            => 5204,
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
                            'plural'           => 'Deactivated the following plugins from the site, see details below:',
                            'plural_network'   => 'Deactivated the following plugins from all sites on the network, see details below:',
                            'singular_network' => 'Deactivated a plugin (%s) from all sites on the network, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Deactivated a plugin (%s) from the site, see details below:',

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
                    'event_id'            => 5205,
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
                            'plural'           => 'Deleted the following plugins from the site, see details below:',
                            'plural_network'   => 'Deleted the following plugins from all sites on the network, see details below:',
                            'singular_network' => 'Deleted a plugin (%s) from all sites on the network, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Deleted a plugin (%s) from the site, see details below:',

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
                    'event_id'            => 5206,
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
                            'plural'           => 'Tried to delete the following plugins from the site but the attempt was unsuccessful, see details below:',
                            'plural_network'   => 'Tried to delete the following plugins from all sites on the network but the attempt was unsuccessful, see details below:',
                            'singular_network' => 'Tried to delete a plugin (%s) from all sites on the network but the attempt was unsuccessful, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Tried to delete a plugin (%s) from the site but the attempt was unsuccessful, see details below:',

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
                    'event_id'            => 5207,
                    'severity'            => 'critical',
                    
                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['update_plugins', 'install_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Updated the following plugins, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'         => 'Updated a plugin (%s), see details below:',

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
                    'event_id'            => 5208,
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
                            'plural' => 'Tried to update the following plugins but the attempt was unsuccessful, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main' => 'Tried to update a plugin (%s) but the attempt was unsuccessful, see details below:',

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
                 * @see /wp-admin/wp-includes/class-plugin-upgrader.php
                 */
                'alm_plugin_installed' => [
                    'title'               => 'Plugin Installed',
                    'action'              => 'plugin_installed',
                    'event_id'            => 5209,
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
                        '_main'              => 'Installed a plugin (%s), see details below',

                        '_space_start'       => '',
                        '_count_object'      => ['_count_object'],
                        'total_count'        => '_ignore_',
                        'installation_type'  => ['installation_type'],
                        'plugin_location'    => ['package_location'],
                        'plugin_info'        => ['plugin_info'],
                        '_space_end'         => '',

                        'site_id'            => ['blog_id'],
                        'site_name'          => ['blog_name'],
                        'site_url'           => ['blog_url'],
                        'network_ID'         => ['network_id'],
                        'network_name'       => ['network_name'],
                    ],
                ],

                /**
                 *  Fires after the plugin installation failed
                 * 
                 * @since 1.0.0
                 * 
                 * @see /wp-admin/wp-includes/class-plugin-upgrader.php
                 */
                'alm_plugin_installation_failed' => [
                    'title'               => 'Plugin Installation Failed',
                    'action'              => 'plugin_installation_failed',
                    'event_id'            => 5210,
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
                            'plural' => 'Tried to install the following plugins but the attempt was unsuccessful, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main' => 'Tried to install a plugin (%s) but the attempt was unsuccessful, see details below:',

                        '_space_start'             => '',
                        '_count_object'            => ['_count_object'],
                        'installation_type'        => ['installation_type'],
                        'plugin_location'          => ['package_location'],
                        'installation_request_url' => ['installation_request_url'],
                        'error_info'               => ['error_info'],
                        'installed_plugin_data'    => ['installed_plugin_data'],
                        'attempted_plugin_data'    => ['attempted_plugin_data'],
                        '_space_end'               => '',

                        'site_id'                  => ['blog_id'],
                        'site_name'                => ['blog_name'],
                        'site_url'                 => ['blog_url'],
                        'network_ID'               => ['network_id'],
                        'network_name'             => ['network_name'],
                    ],
                ],

                /**
                 * Fires when the upgrader has successfully overwritten a currently installed
			     * plugin or theme with an uploaded zip package.
                 * 
                 * @since 1.0.0
                 */
                'upgrader_overwrote_package' => [
                    'title'               => 'Plugin overwritten successfully', // Plural: Plugins Updated
                    'action'              => 'plugin_updated', // Plural: plugins_updated
                    'event_id'            => 5211,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['update_plugins', 'install_plugins'],

                    'wp_transient'        => ['update_plugins'],
                    'wp_site_transient'   => ['update_plugins'],

                    'message' => [
                        '_main'         => 'successfully overwritten a currently installed plugin (%s) with an uploaded zip package, see details below:',

                        '_space_start'          => '',
                        'uploaded_package'      => ['uploaded_package'],
                        'previous_plugin_info'  => ['previous_plugin_info'],
                        'new_plugin_info'       => ['new_plugin_info'],
                        '_space_end'            => '',

                        'site_id'               => ['blog_id'],
                        'site_name'             => ['blog_name'],
                        'site_url'              => ['blog_url'],
                        'network_ID'            => ['network_id'],
                        'network_name'          => ['network_name'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the plugin data arguments
     * @return array
     */
    protected function getPluginDataArgs()
    {
        return ['Title', 'Version', 'Author', 'RequiresWP', 'RequiresPHP'];
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

        $plugin_data = $this->getPluginData($plugin_path, true);
        if (empty($plugin_data)) return $bailer;

        // Retrieve the plugin dir
        if (false !== strpos($plugin_file, '/'))
            $plugin_path = wp_normalize_path(pathinfo($plugin_path, PATHINFO_DIRNAME));

        // Setup the plugin object data var
        $this->current_plugin_data[$plugin_file] = &$plugin_data;

        $data_args   = $this->getPluginDataArgs();
        $plugin_info = '';

        foreach ($data_args as $info) {
            $data    = $this->getVar($plugin_data, $info, '');
            $no_data = empty($data);

            // Fallback to the plugin name if title is not given
            if ($no_data && 'Title' === $info)
                $data = $this->getVar($plugin_data, 'Name', '');

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
    protected function getPluginEventObjectId($field_key = '', $field_value = '')
    {
        $option_name = $this->is_network_admin ? 'active_sitewide_plugins' : 'active_plugins';
        return $this->getWpOptionId($option_name);
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
     * 
     * @param  string  $use_dir Specifies a plugin fallback file whose data should be 
     *                          retrieved if the $plugins var is empty.
     * 
     * @return string           The specified plugins info.
     */
    protected function getPluginEventObjectInfo(array $plugins, $use_file = null)
    {
        if (empty($plugins) && !is_null($use_file) && file_exists($use_file)) {
            $basename      = basename($use_file);
            $plugin_dir    = rtrim($use_file, '/') . '/';
            $expected_file = $plugin_dir . $basename;

            if (!is_file($expected_file)) {
                // Maybe we have to use the first occurrence of the underscore or dash character
                $basename_path = (false !== strpos($basename, '_')) ?
                    explode('_', $basename) : explode('-', $basename);

                $basename      = current($basename_path);
                $expected_file = $plugin_dir . $basename . '.php';
            }

            $_plugin_basename = plugin_basename($expected_file);
            $plugins[$_plugin_basename] = $_plugin_basename;
        }

        $repeater    = (count($plugins) > 1) ? 2 : 1;
        $line_break  = str_repeat($this->getEventMsgLineBreak(), $repeater);
        $plugin_info = '';

        foreach ($this->parseSelectedPluginsArray($plugins) as $new_plugin) {
            $plugin_info .= $this->getPluginInfo($new_plugin) . $line_break;
        }

        return rtrim($plugin_info, $line_break);
    }

    /**
     * Parse selected plugins array properly
     * @param  array $plugins Specifies list of plugins to parse
     * @return array          The parse plugins array
     */
    protected function parseSelectedPluginsArray($plugins)
    {
        $selected_plugins = [];
        if ($this->is_network_admin) {
            foreach ($plugins as $new_plugin => $timestamp) {
                /**
                 * Make sure that the $timestamp var is not the actual plugin basename
                 */
                if (false !== strpos($timestamp, '.php'))
                    $new_plugin = $timestamp;
                    
                $selected_plugins[] = $new_plugin;
            }
        } else {
            foreach ($plugins as $index => $new_plugin) {
                /**
                 * Make sure that the $index var is not the actual plugin basename
                 */
                if (false !== strpos($index, '.php'))
                    $new_plugin = $index;
                    
                $selected_plugins[] = $new_plugin;
            }
        }

        return $selected_plugins;
    }

    /**
     * Check whether the plugin is being upgraded/updated
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

    /**
     * Get the Plugin Title or Name given the plugin data object
     * @param  object $plugin_data Specifies the plugin data object
     * @return string              The plugin Title or Name. Unknown is returned on failure.
     */
    public function getPluginNameFromObj($plugin_data)
    {
        return $this->sanitizeOption($this->getVar(
            $plugin_data,
            'Title',
            // Use Name if Title is empty
            $this->getVar($plugin_data, 'Name', 'Unknown')
        ));
    }

    /**
     * Get the plugin data given th remote destination
     * @param  string $destination Specifies the remote destination for the plugin
     * @return array               The plugin data on success. Otherwise empty array.
     */
    public function getPluginDataByRemoteDestination($destination)
    {
        $destination = trailingslashit(wp_normalize_path($destination));

        if (!$destination) return [];

        $folder      = ltrim(substr($destination, strlen(WP_PLUGIN_DIR)), '/');
        $all_plugins = get_plugins();

        foreach ($all_plugins as $plugin => $plugin_data) {
            if (strrpos($plugin, $folder) !== 0) {
                continue;
            }
            return $plugin_data;
        }
        return [];
    }
}