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
                'value'           => null,
                'label'           => '',
                'group'           => '',
                'option'          => '',
                'event_id'        => null,
                'action_type'     => '',
                'message_args'    => [],
                'current_value'   => '_ignore_',
                'previous_value'  => null,
                'requested_value' => '_ignore_',
            ],
            $data
        );

        if (empty($data['option']) || empty($data['action_type']) || empty($data['group']))
            return;
        
        $new_value          = $data['value'];
        $option_name        = $data['option'];
        $action_type        = $data['action_type'];
        $option_label       = $data['label'];
        $current_value      = $data['current_value'];
        $previous_value     = $data['previous_value'];
        $requested_value    = $data['requested_value'];

        $event_slug         = $option_name;
        $event_group        = $data['group'];
        $event_handler      = $this->getWpCoreSettingEventHandler($option_name);

        $event_id           = $this->getEventIdBySlug($event_handler, $this->wp_core_settings_slug);
        $event_data         = $this->getEventData($event_id);

        if (!$event_data) return;

        $object_id          = $this->getWpCoreOptionId($option_name, $event_group);

        $event_msg_args     = array_merge( $event_data['message'], $data['message_args'] );
        $event_msg          = $event_msg_args['_main'];
        $event_title        = $event_data['title'];
        $event_namespace    = preg_replace('/\:\:(.+)$/', '::' . $event_handler, __METHOD__);

        $settings_page      = $this->getWpCoreSettingsPage($event_group, $option_name);

        $is_requested_value = is_array($this->getVar($event_msg_args, 'requested_value'));

        if ('updated' === $action_type) {
            $msg_label = 'Changed';
        } else {
            $msg_label = ucfirst($action_type);
        }

        if (false !== strpos($event_title, '%s')) {
            $event_title = sprintf($event_title, $msg_label);
        }

        if (false !== strpos($event_msg, '%s')) {
            $event_msg_args['_main'] = sprintf($event_msg, $msg_label, lcfirst($option_label));
        }

        if (in_array($action_type, ['added', 'deleted', 'cancelled'], true)) {
            $current_value                    = 'added' === $action_type ? $new_value : $current_value;
            $event_msg_args['new_value']      = '_ignore_';
            $event_msg_args['current_value']  = ['current_value'];
            $event_msg_args['previous_value'] = '_ignore_';
        }

        // Mainly for 'new_admin_email'
        $current_email = '';
        if ( 'updated' === $action_type && $is_requested_value )
        {
            $current_email   = $this->getAdminEmail();
            $requested_value = $new_value;

            $event_msg_args['new_value']      = '_ignore_';
            $event_msg_args['current_value']  = ['current_email'];

            if ($current_email == $previous_value) {
                $event_msg_args['previous_value'] = '_ignore_';
            }
        }
        elseif ( ('added' === $action_type && $is_requested_value) 
        || 'cancelled' === $action_type )
        {
            if (!empty($data['event_id'])) {
                $object_id = $data['event_id'];
            }

            $requested_value                   = 'cancelled' === $action_type ? $requested_value : $current_value;
            $current_email                     = $this->getAdminEmail();
            $event_msg_args['current_value']   = ['current_email'];
            $event_msg_args['requested_value'] = ['requested_value'];
        }
        else {
            $event_msg_args['requested_value'] = '_ignore_';
        }

        $this->overrideActiveEventData('action',  $action_type);
        $this->overrideActiveEventData('title',   $event_title);
        $this->overrideActiveEventData('message', $event_msg_args);

        $this->setupEventMsgData($event_group, compact(
            'object_id',
            'new_value',
            'option_name',
            'settings_page',
            'current_email',
            'current_value',
            'previous_value',
            'requested_value'
        ));
        $this->LogActiveEvent($this->wp_core_settings_slug, $event_namespace);
    }
}