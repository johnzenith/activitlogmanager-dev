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

        $object_id     = $this->getPluginEventObjectId();
        $plugin_info   = $this->getPluginInfo($plugin);
        $_count_object = 1;

        /**
         * @todo
         * Maybe we should retrieve the plugin error and save it to 
         * the [new_content] log column
         */
        $this->setupPluginEventArgs(compact('object_id', '_count_object', 'plugin_info'));
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

        if ($_count_object > 1 ) { 
            $total_count = sprintf('Plugins Activation Count: %d', $_count_object);

            $this->overrideActiveEventData('title', 'Plugins Activated With Errors');
            $this->overrideActiveEventData('action', 'plugins_activated_with_errors');
        }

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
                array_diff($new_plugins, $old_plugins);
        } else {
            $diff = $new_plugins;
        }

        $diff          = (array) $diff;
        $total_count   = '_ignore_';
        $_count_object = count($diff);

        if ($_count_object > 1 ) {
            $total_count = sprintf('Plugins Activation Count: %d', $_count_object);

            $this->overrideActiveEventData('title', 'Plugins Activated');
            $this->overrideActiveEventData('action', 'plugins_activated');
        }

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

        // Get the activated plugins
        $diff = $this->is_network_admin ? 
            $this->arrayDiffAssocRecursive($old_plugins, $new_plugins)
            :
            array_diff($old_plugins, $new_plugins);

        $diff          = (array) $diff;
        $total_count   = '_ignore_';
        $_count_object = count($diff);

        if ($_count_object > 1) {
            $total_count = sprintf('Plugins Deactivation Count: %d', $_count_object);

            $this->overrideActiveEventData('title', 'Plugins Deactivated');
            $this->overrideActiveEventData('action', 'plugin_deactivated');
        }

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
    public function alm_plugin_deleted_event($plugins)
    {
        if (!is_array($plugins) && is_string($plugins))
            $plugins = [$plugins];

        if (!is_array($plugins) || empty($plugins))
            $plugins = [];

        $total_count   = '_ignore_';
        $_count_object = count($plugins);

        if ($_count_object > 1) {
            $total_count = sprintf('Plugins Deletion Count: %d', $_count_object);

            $this->overrideActiveEventData('title', 'Plugins Deleted');
            $this->overrideActiveEventData('action', 'plugins_deleted');
        }

        $object_id   = $this->getUninstalledPluginsOptionId();
        $plugin_info = $this->getPluginEventObjectInfo($plugins);

        if (empty(trim($plugin_info)))
            $plugin_info = 'Not available. No plugin information could be retrieved from the request';

        $this->setupPluginEventArgs(compact('object_id', 'total_count', '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after an unsuccessful plugin deletion attempt
     * 
     * @since 1.0.0
     */
    public function alm_plugin_deletion_failed_event($plugins)
    {
        if (!is_array($plugins) && is_string($plugins))
            $plugins = [$plugins];

        if (!is_array($plugins) || empty($plugins))
            $plugins = [];

        $total_count   = '_ignore_';
        $_count_object = count($plugins);

        if ($_count_object > 1) {
            $total_count = sprintf('Plugins Deletion Attempt Count: %d', $_count_object);

            $this->overrideActiveEventData('title', 'Plugins Deleted');
            $this->overrideActiveEventData('action', 'plugins_deleted');
        }

        $object_id   = $this->getUninstalledPluginsOptionId();
        $plugin_info = $this->getPluginEventObjectInfo($plugins);

        if (empty(trim($plugin_info)))
            $plugin_info = 'Not available. No plugin information could be retrieved from the request';

        $this->setupPluginEventArgs(compact('object_id', 'total_count', '_count_object', 'plugin_info'));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Container for the plugin upgrade/update event
     * 
     * @since 1.0.0
     */
    protected function alm_upgrader_process_wrapper($plugin_upgrader, $hook_extra, $is_failed_event = false)
    {
        $plugins       = (array) $this->getVar($hook_extra, 'plugins', []);
        $total_count   = '_ignore_';
        $_count_object = count($plugins);

        if ($_count_object > 1) {
            $total_count = sprintf(
                '%s: %d',
                ($is_failed_event ? 'Plugins Failed Update Count' : 'Plugins Update Count'),
                $_count_object
            );

            $this->overrideActiveEventData(
                'title',
                $is_failed_event ? 'Plugins Update Failed' : 'Plugins Updated'
            );

            $this->overrideActiveEventData(
                'action',
                $is_failed_event ? 'plugins_update_failed' : 'plugins_updated'
            );
        }

        $object_id   = $this->getPluginEventObjectId();
        $line_break  = $this->getEventMsgLineBreak();
        $plugin_info = '';

        $data_args   = array_merge(
            ['Title', 'Version', 'Author'],
            $this->isSuperMode() ? ['RequiresWP', 'RequiresPHP'] : []
        );

        foreach ($plugins as $plugin)
        {
            if (false === strpos($plugin, '.php'))
                continue;

            $plugin_file = $this->sanitizeOption($plugin);
            $plugin_path = wp_normalize_path(WP_PLUGIN_DIR . '/' . $plugin_file);
            $basename    = basename($plugin_file, '.php');

            $plugin_data = $this->getPluginData($plugin_path, true);

            // Retrieve the plugin dir
            if (false !== strpos($plugin_file, '/'))
                $plugin_path = wp_normalize_path(pathinfo($plugin_path, PATHINFO_DIRNAME));

            // Setup the plugin object data var
            $plugin_name = $this->getVar($plugin_data, 'Name', $basename);
            $this->current_plugin_data[$plugin_name] = &$plugin_data;

            foreach ($data_args as $info)
            {
                $data    = $this->getVar($plugin_data, $info, '');
                $no_data = empty($data);

                if ('Version' === $info)
                {
                    $the_plugin_version = (!$is_failed_event) ? 
                        $this->getVar($this->plugin_versions, $plugin, 'Unknown') 
                        : 
                        $this->getVar(
                            $this->getVar($this->plugins_to_update, $plugin, []),
                            'new_version',
                            'Unknown'
                        );

                    $plugin_info .= sprintf(
                        '%s: %s%s',
                        ($is_failed_event ? 'Attempted Update Version' : 'Previous Version'),
                        $the_plugin_version,
                        $line_break
                    );

                    $info = 'Current Version';
                }

                // Fallback to the plugin name if title is not given
                if ($no_data && 'Title' === $info)
                    $data = $plugin_name;

                if ($no_data && in_array($info, ['RequiresWP', 'RequiresPHP'], true))
                    continue;

                if ($no_data)
                    $data = 'Unknown';

                if (!$no_data)
                    $data = wp_kses($data, $this->getEventMsgHtmlList());

                $plugin_info .= sprintf('Plugin %s: %s%s', $info, $data, $line_break);

                if ('Title' === $info) {
                    if (is_plugin_active_for_network($plugin)) {
                        $plugin_active_label = 'Yes (activated across the network)';
                    } else {
                        $plugin_active_label = is_plugin_active($plugin) ? 'Yes' : 'No';
                    }

                    $plugin_info .= sprintf(
                        'Is Plugin Activated?: %s%s',
                        $plugin_active_label,
                        $line_break
                    );

                    $plugin_info .= sprintf(
                        'Plugin Location: %s%s',
                        esc_html($plugin_path),
                        $line_break
                    );
                }
            }
        }

        $installation_request_url = $this->getPluginRequestUrl($hook_extra);

        $this->setupPluginEventArgs(compact(
            'object_id',
            'total_count',
            '_count_object',
            'plugin_info',
            'installation_request_url'
        ));
    }

    /**
     * Fires after successfully updating a plugin
     * 
     * @see PluginEvents::alm_upgrader_process_wrapper()
     */
    public function upgrader_process_complete_event($plugin_upgrader, $hook_extra)
    {
        $skin               = $this->getVar($plugin_upgrader, 'skin', '');
        $type               = $this->getVar($plugin_upgrader, 'type', '');
        $action             = $this->getVar($hook_extra, 'action');
        $result             = $this->getVar($skin, 'result', '');
        $options            = $this->getVar($skin, 'options', []);
        $plugins            = (array) $this->getVar($hook_extra, 'plugins', []);
        $remote_destination = $this->getVar($result, 'remote_destination', '');

        $hook_extra['_alm_vars'] = compact('type', 'options', 'remote_destination');

        // Set the $skin data as the new content to save along the logged data
        $this->overrideActiveEventData('_new_content', $this->serialize($skin));
        
        if (!$this->isPluginUpdateActionValid($action) 
        && (is_array($result) && !empty($result))) {
            /**
             * Fire the plugin installation event if action equals 'install'
             */
            if ('install' === $action)
                do_action('alm_plugin_installed', $hook_extra);
            
            return;
        }

        /**
         * Verify the plugin update status
         */
        $failed_updates = [];
        foreach ($plugins as $plugin)
        {
            $update_version = 
            $this->getVar(
                $this->getVar($this->plugins_to_update, $plugin, []),
                'new_version',
                'Unknown'
            );

            if ('Unknown' === $update_version)
                continue;

            // Get the plugin current version
            $current_version = 
            $this->getVar(
                $this->getPluginData($plugin, false, false),
                'Version',
                'Unknown'
            );

            if (('Unknown' === $current_version || $current_version != $update_version) 
            && !$this->isPluginUpgraderResultValid($plugin_upgrader))
                $failed_updates[] = $plugin;
        }

        /**
         * WordPress will still fire the {@see 'upgrader_process_complete'} action 
         * even when the update did not complete. So we have to check if the plugin action 
         * was executed successfully.
         */
        if (!empty($failed_updates)) {
            $hook_extra['plugins'] = $failed_updates;

            do_action('alm_upgrader_process_failed', $plugin_upgrader, $hook_extra);
            return;
        }

        // Bail out if not updating/upgrading
        if (!in_array($type, ['update', 'upgrade'], true))
            return;

        $this->alm_upgrader_process_wrapper($plugin_upgrader, $hook_extra, false);
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after the plugin update request failed
     * 
     * @since 1.0.0
     * @see PluginEvents::alm_upgrader_process_wrapper()
     */
    public function alm_upgrader_process_failed_event($plugin_upgrader, $hook_extra)
    {
        $this->alm_upgrader_process_wrapper($plugin_upgrader, $hook_extra, true);
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after a plugin has been installed
     * 
     * @since 1.0.0
     * 
     * @see PluginEvents::upgrader_process_complete_event()
     */
    public function alm_plugin_installed_event($hook_extra)
    {
        $alm_vars    = $this->getVar($hook_extra, '_alm_vars', []);

        $action      = $this->sanitizeOption(wp_unslash($this->getVar($_GET, 'action', '')));
        $options     = $this->getVar($alm_vars, 'options', []);
        $plugins     = (array) $this->getVar($hook_extra, 'plugins', []);
        $type        = $this->getVar($options, 'type', 'web');
        $destination = $this->getVar($alm_vars, 'remote_destination', 'Unknown');

        if (!is_array($plugins) && is_string($plugins))
            $plugins = [$plugins];

        // Allow this event to run even when no plugin is found
        if (!is_array($plugins) || empty($plugins))
            $plugins = [];

        $total_count   = '_ignore_';
        $_count_object = count($plugins);

        if ($_count_object > 1) {
            $total_count = sprintf('Plugins Installation Count: %d', $_count_object);

            $this->overrideActiveEventData('title', 'Plugins Installed');
            $this->overrideActiveEventData('action', 'plugins_installed');
        }

        $object_id   = 0; // Plugins installation not tide to any option in the database
        $plugin_info = $this->getPluginEventObjectInfo($plugins, $destination);

        if (empty(trim($plugin_info)))
            $plugin_info = 'Not available. No plugin information could be retrieved from the request';

        $is_web_download   = 'web' === $type;
        $installation_type = $is_web_download ? 'Web Download' : 'File Upload';

        $package_location  = $destination;

        $this->setupPluginEventArgs(compact(
            'object_id',
            'total_count',
            '_count_object',
            'plugin_info',
            'installation_type',
            'package_location',
        ));
        $this->LogActiveEvent('plugin', __METHOD__);
    }

    /**
     * Fires after a plugin installation failed
     * 
     * @since 1.0.0
     */
    public function alm_plugin_installation_failed_event($api, $file_upload, $hook_extra)
    {
        $type              = $this->getVar($hook_extra, 'type');
        $is_web_download   = 'web' === $type || is_object($api);

        // WordPress may support multiple files upload in the future.
        // Then we will have to count the plugins and update the $_count_object var
        $_count_object     = 1;

        $installation_type = $is_web_download ? 'Web Download' : 'File Upload';

        $package_location  = $is_web_download ? 
            $this->getVar($api, 'download_link', 'Unknown') 
            : 
            $this->getVar($file_upload, 'package', 'Unknown');

        if ('Unknown' != $package_location) {
            $package_location = urldecode_deep($package_location);

            if (!$is_web_download)
                $package_location = wp_normalize_path($package_location);
        }
        
        $installation_request_url = $this->getPluginRequestUrl($hook_extra);
        
        $this->setupPluginEventArgs(compact(
            'installation_request_url', 'installation_type', 'package_location', '_count_object',
        ));
        $this->LogActiveEvent('plugin', __METHOD__);
    }
}