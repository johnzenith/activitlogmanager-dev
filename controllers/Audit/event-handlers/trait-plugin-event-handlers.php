<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Plugin Event Handlers
 * @since   1.0.0
 */

trait PluginEvents
{
    /**
     * Fires after a plugin is activated
     * 
     * @since 1.0.0
     * @see ALM\Controllers\Audit\Events\Groups\PluginEvents::_registerPluginEvents()
     */
    public function alm_plugin_activated_event($old_plugins, $new_plugins)
    {
        /**
         * The object ID represents the 'active_plugins' option ID in the 
         * {@see WordPress options table} on single site or 
         * {@see WordPress sitemeta table} on multisite
         */
        $table_prefix = $this->getBlogPrefix();
        $field        = 'option_id';
        $table        = $table_prefix . 'options';
        $option_name  = 'active_plugins';

        if ( $this->is_network_admin ) {
            $field       = 'meta_id';
            $table       = $table_prefix . 'sitemeta';
            $option_name = 'active_sitewide_plugins';
        }

        $object_id = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT $field FROM $table WHERE option_name = %s",
            $option_name
        ));

        $_count_object = count($new_plugins);

        $plugin_info = '';

        $this->setupUserEventArgs(compact('object_id',  '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }
}