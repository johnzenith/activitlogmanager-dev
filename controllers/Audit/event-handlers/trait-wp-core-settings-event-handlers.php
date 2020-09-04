<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package WP Core Settings Event Handlers
 * @since   1.0.0
 */

trait WP_CoreSettingsEvents
{
    /**
     * Monitor the settings changes
     */
    public function alm_wp_core_settings_changed($data)
    {
        $data = array_merge(
            [
                'value'          => null,
                'label'          => '',
                'option'         => '',
                'action_type'    => '',
                'previous_value' => null,
            ],
            $data
        );

        if (empty($data['option']) || empty($data['action_type']))
            return;
        
        $new_value        = $data['value'];
        $option_name      = $data['option'];
        $action_type      = $data['action_type'];
        $option_label     = $data['label'];
        $previous_value   = $data['action_type'];

        $event_slug       = __FUNCTION__;
        $event_group      = 'wp_core_settings';

        $event_id         = $this->getEventIdBySlug($event_slug, $event_group);
        if (!isset($this->main_event_list[$event_id]))
            return;

        $event_data       = $this->main_event_list[$event_id];
        $event_msg_args   = $event_data['message'];
        $event_msg        = $event_msg_args['_main'];
        $event_title      = $event_data['title'];
        $event_handler    = sprintf($this->wp_core_setting_event_handler, $option_name);
        $event_namespace  = str_replace($event_slug, $event_handler, __METHOD__);

        $settings_page    = $this->getWpCoreSettingsPage($event_data['object'], $option_name);

        if ('updated' === $action_type) {
            $msg_label = 'Changed';
        } else {
            $msg_label = ucfirst($action_type);
        }

        if (false !== strpos($event_title, '%s')) {
            $event_title = sprintf($event_title, $msg_label);
        }

        if (false !== strpos($event_msg, '%s')) {
            $event_msg_args['_main'] = sprintf($event_msg, $msg_label, $option_label);
        }

        if ('added' === $action_type) {
            $event_msg_args['new_value']      = '_ignore_';
            $event_msg_args['current_value']  = ['current_value'];
            $event_msg_args['previous_value'] = '_ignore_';
        }

        $this->overrideActiveEventData('action',  $action_type);
        $this->overrideActiveEventData('title',   $event_title);
        $this->overrideActiveEventData('message', $event_msg_args);

        $this->setupUserEventArgs(compact(
            'object_id',
            'new_value',
            'option_name',
            'settings_page',
            'current_value',
            'previous_value'
        ));
        $this->LogActiveEvent($event_group, $event_namespace);
    }
}