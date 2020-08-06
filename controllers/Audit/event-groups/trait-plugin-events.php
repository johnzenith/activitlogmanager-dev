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
         * Run when plugins are activated on a specific blog (single site)
         */
        add_action('add_option_active_plugins', function($option, $value) use (&$self)
        {
            $new_plugins = $this->unserialize($value);
            if (empty($new_plugins) || !is_countable($new_plugins)) return;

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
            $this->activated_plugins_list = get_option('active_plugins', []);
        });

        /**
         * Run when plugins are deactivated on a specific blog (single site)
         */
        add_action('delete_option_active_plugins', function($option) use (&$self)
        {
            if (empty($new_plugins) || !is_countable($new_plugins)) return;

            $plugin_event = 'alm_plugin_deactivated';

            // Trigger the current event
            do_action($plugin_event, [], $this->activated_plugins_list);
        }, 10, 2);
        
        /**
         * Run when plugins are activated or deactivated on a specific blog (single site)
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
                $this->activated_plugins_list = get_site_option($option, []);
            }, 10, 2);

            /**
             * Listen for the plugin network activation event on multisite
             */
            add_action('add_site_option_active_sitewide_plugins',
            function($option, $value, $network_id) use (&$self)
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
            add_action('delete_site_option_active_sitewide_plugins', function() use (&$self)
            {
                if (empty($new_plugins) || !is_countable($new_plugins)) return;

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
         */
        if ( $this->pagenow == 'plugins.php' 
        && isset($_GET['charsout'], $_GET['error'], $_GET['plugin'], $_GET['plugin_status']) )
        {
            do_action(
                'alm_plugin_activation_failed',
                $this->sanitizeOption(wp_unslash($_GET['plugin']))
            );
        }
    }

    /**
     * Correclty add the plugin event during activation/deactivaiton of plugins
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
     * Setup the Super Admin events
     */
    protected function setupPluginEvents()
    {
        $this->event_list['plugins'] = [
            'title'       => 'Plugins Events',
            'group'       => 'plugin', // object

            'description' => alm__('Responsible for logging all plugins related activities such as plugin activation, deactivation, installation, uninstallation and the front-end plugin editor'),

            'events' => [
                 'alm_plugin_activation_failed' => [
                    'title'               => 'Plugin Activation Failed',
                    'action'              => 'plugin_activation_failed',
                    'event_id'            => 5071,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['activate_plugins'],

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
                        'plugin_info'   => ['plugin_info'],
                        '_count_object' => ['_count_object'],
                        '_space_end'    => '',

                        'network_ID'    => ['network_id'],
                        'network_title' => ['network_title'],
                    ],

                    'event_handler' => [
                        'hook'     => 'action',
                        'num_args' => 2,
                        'priority' => 10,
                        'callback' => null,
                    ],
                ],

                /**
                 * @see /wp-includes/plugins.php
                 * @see register_activation_hook()
                 */
                'alm_plugin_activated' => [
                    'title'               => 'Plugin Activated',
                    'action'              => 'plugin_activated',
                    'event_id'            => 5072,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'pagenow'             => 'plugins.php',
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['activate_plugins'],

                    'wp_options'      => [
                        'recently_activated',
                        'active_plugins',
                    ],
                    'wp_site_options' => [
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
                        'plugin_info'   => ['plugin_info'],
                        '_count_object' => ['_count_object'],
                        '_space_end'    => '',

                        'network_ID'    => ['network_id'],
                        'network_title' => ['network_title'],
                    ],

                    'event_handler' => [
                        'hook'     => 'action',
                        'num_args' => 2,
                        'priority' => 10,
                        'callback' => null,
                    ],
                ],
            ],
        ];
    }
}