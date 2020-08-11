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
     * Fires after a plugin is activation failed
     * 
     * @since 1.0.0
     */
    public function alm_plugin_activation_failed_event($plugin = '')
    {
        if (empty($plugin)) return;

        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
        if (false !== strpos($plugin_path, '/'))
            $plugin_path = pathinfo($plugin_path, PATHINFO_DIRNAME);

        $plugin_path   = wp_normalize_path($plugin_path);
        $plugin_info   = $this->getPluginInfo($plugin_path);
        $_count_object = 1;

        /**
         * @todo
         * Retrieve the plugin error and save it to the [new_content] log column
         */
        $this->setupPluginEventArgs(compact('_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after a plugin has been activated with errors
     * 
     * @since 1.0.0
     */
    public function alm_plugin_activated_with_error_event($plugins)
    {
        if (!is_array($plugins) && is_string($plugins))
            $plugins = [$plugins];

        if (!is_array($plugins) || empty($plugins))
            return;

        $total_count   = '_ignore_';
        $_count_object = count($plugins);

        if ($_count_object > 1 )
            $total_count = sprintf('Plugin Activation Count: %d', $_count_object);

        $object_id   = $this->getPluginEventObjectId();
        $repeater    = ($_count_object > 1) ? 2 : 1;
        $line_break  = str_repeat($this->getEventMsgLineBreak(), $repeater);
        $plugin_info = '';

        foreach ($plugins as $plugin) {
            $plugin_info .= $this->getPluginInfo($plugin) . $line_break;
        }

        /**
         * @todo
         * Retrieve the plugin error and save it to the [new_content] log column
         */

        $this->setupPluginEventArgs(compact('object_id', 'total_count', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after a plugin is activated
     * 
     * @since 1.0.0
     * @see ALM\Controllers\Audit\Events\Groups\PluginEvents::_registerPluginEvents()
     */
    public function alm_plugin_activated_event($new_plugins, $old_plugins)
    {
        if (!is_array($old_plugins))
            $old_plugins = [];

        if (!is_array($new_plugins) || empty($new_plugins))
            return;

        // Setup the activated plugins list to bail out forward request 
        // to retrieve the activated plugins
        $this->activated_plugins_list = $new_plugins;

        $count_plugins = count($new_plugins);

        // Get the activated plugins
        if ($count_plugins > 1) {
            $diff = $this->is_network_admin ? 
                $this->arrayDiffAssocRecursive($new_plugins, $old_plugins) 
                :
                // $this->arrayDiffAssocRecursive(
                //     array_flip($new_plugins), array_flip($old_plugins)
                // );
                array_diff($new_plugins, $old_plugins);

            // Flip the back the differences if not on network admin
            // if (!$this->is_network_admin)
            //     $diff = array_flip($diff);
        } else {
            $diff = $new_plugins;
        }

        $diff          = (array) $diff;
        $total_count   = '_ignore_';
        $_count_object = count($diff);

        if ($_count_object > 1 )
            $total_count = sprintf('Plugin Activation Count: %d', $_count_object);

        $object_id   = $this->getPluginEventObjectId();
        $plugin_info = $this->getPluginEventObjectInfo($diff);

        $this->setupPluginEventArgs(compact('object_id', 'total_count', '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after a plugin is deactivated
     * 
     * @since 1.0.0
     * @see ALM\Controllers\Audit\Events\Groups\PluginEvents::_registerPluginEvents()
     */
    public function alm_plugin_deactivated_event($new_plugins, $old_plugins)
    {
        if ( !is_array($new_plugins))
            $new_plugins = [];    

        if (!is_array($old_plugins) || empty($old_plugins))
            return;

        $count_plugins = count($old_plugins) - count($new_plugins);

        // Get the activated plugins
        $diff = $this->is_network_admin ? 
            $this->arrayDiffAssocRecursive($old_plugins, $new_plugins)
            :
            array_diff($old_plugins, $new_plugins);

        $diff          = (array) $diff;
        $total_count   = '_ignore_';
        $_count_object = count($diff);

        if ($_count_object > 1)
            $total_count = sprintf('Plugin Deactivation Count: %d', $_count_object);

        $object_id   = $this->getPluginEventObjectId();
        $plugin_info = $this->getPluginEventObjectInfo($diff);

        $this->setupPluginEventArgs(compact('object_id', 'total_count', '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after a plugin has been deleted
     * 
     * @since 1.0.0
     */
    public function alm_plugin_deleted_event($plugin_file)
    {
        $total_count   = '_ignore_';
        $_count_object = 1;

        // We may aggregate plugin deletion event in the future
        if ($_count_object > 1)
            $total_count = sprintf('Plugin Deletion Count: %d', $_count_object);

        $object_id   = $this->getUninstalledPluginsOptionId();
        $plugin_info = $this->getPluginEventObjectInfo($plugin_file);

        $this->setupPluginEventArgs(compact('object_id', 'total_count', '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after an unsuccessful plugin deletion attempt
     * 
     * @since 1.0.0
     */
    public function alm_plugin_deletion_failed_event($plugin_file)
    {
        $total_count   = '_ignore_';
        $_count_object = 1;

        // We may aggregate plugin deletion event in the future
        if ($_count_object > 1)
            $total_count = sprintf('Plugin Deletion Attempt Count: %d', $_count_object);

        $object_id   = $this->getUninstalledPluginsOptionId();
        $plugin_info = $this->getPluginEventObjectInfo($plugin_file);

        $this->setupPluginEventArgs(compact('object_id', 'total_count', '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }
}