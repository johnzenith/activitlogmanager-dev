<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Widget Event Handlers
 * @since   1.0.0
 */

trait WidgetEvents
{
    /**
     * @see alm_widget_added action hook
     */
    public function alm_widget_added_event($widget_args)
    {
        global $wp_registered_widgets, $wp_registered_sidebars;

        $event_slug       = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id         = $this->getEventIdBySlug($event_slug, 'widget');
        $event_data       = $this->getEventData($event_id);
        $event_msg_args   = $this->getVar($event_data, 'message', []);

        $widget_id        = $this->getVar($widget_args, 'widget_id', '');
        $sidebar_id       = $this->getVar($widget_args, 'sidebar_id', '');
        $widget_inactive  = $this->getVar($widget_args, 'widget_inactive', false);
        $widget_position  = (int) $this->getVar($widget_args, 'widget_position', '');
        $widget_settings  = $this->getVar($widget_args, 'widget_settings', []);

        $object_id        = $this->getWpOptionId($this->sidebar_widgets_option_name);

        $sidebar          = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
        $widget_data      = $this->getVar($wp_registered_widgets, $widget_id, []);

        $sidebar_name     = $this->getVar($sidebar, 'name', $sidebar_id);

        $custom_title     = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
        $widget_title     = $this->getVar($widget_data, 'name', $custom_title);

        $widget_position += 1; // Array index starts at 0.

        // Format the main event message
        $msg_key  = $widget_inactive ? 'main_alt': '_main';
        $main_msg = $this->getVar($event_msg_args, $msg_key, '');

        if (empty($main_msg)) return;

        $event_msg_args['_main'] = sprintf($main_msg, $sidebar_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        // Setup the widget object data
        $obj_data = $this->getWidgetObjectData(array_merge(
            $widget_args,
            [
                'sidebar_name' => $sidebar_name,
                'widget_title' => $widget_title,
            ]
        ));

        // Stringify the widget settings array
        $widget_settings = $this->stringifyWidgetSettings($widget_settings);

        $this->setupEventMsgData('widget', compact(
            'obj_data',
            'object_id',
            'widget_id',
            'sidebar_id',
            'sidebar_name',
            'widget_title',
            'widget_position',
            'widget_settings'
        ));

        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * @see alm_widget_position_changed action hook
     */
    public function alm_widget_position_changed_event($widget_args)
    {
        global $wp_registered_widgets, $wp_registered_sidebars;

        $event_slug         = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id           = $this->getEventIdBySlug($event_slug, 'widget');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);
        $event_translations = $this->getVar($event_data, '_translate', []);

        $obj_data           = [];
        $object_id          = $this->getWpOptionId($this->sidebar_widgets_option_name);
        $break_line         = $this->getEventMsgLineBreak();
        $widget_info        = '';
        $break_line_2       = str_repeat($break_line, 2);
        $the_sidebar_name   = '';

        foreach ($widget_args as $widget_arg)
        {
            $widget_id           = $this->getVar($widget_arg, 'widget_id');
            $sidebar_id          = $this->getVar($widget_arg, 'sidebar_id');
            $widget_position     = $this->getVar($widget_arg, 'widget_position') + 1;
            $old_widget_position = $this->getVar($widget_arg, 'old_widget_position') + 1;

            if (empty($widget_id) || empty($sidebar_id))
                continue;

            $sidebar             = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
            $widget_data         = $this->getVar($wp_registered_widgets, $widget_id, []);

            $sidebar_name        = $this->getVar($sidebar, 'name', $sidebar_id);

            $custom_title        = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
            $widget_title        = $this->getVar($widget_data, 'name', $custom_title);

            $widget_info        .= sprintf('Sidebar ID: %s%s',   $sidebar_id,   $break_line);
            $widget_info        .= sprintf('Sidebar name: %s%s', $sidebar_name, $break_line);
            $widget_info        .= sprintf('Widget ID: %s%s',    $widget_id,    $break_line);
            $widget_info        .= sprintf('Widget title: %s%s', $widget_title, $break_line);
            
            $widget_info        .= sprintf('Widget old position: %s%s', $widget_position, $break_line);
            
            $widget_info        .= sprintf(
                'Widget new position: %s%s',
                $old_widget_position,
                $break_line_2
            );

            if (empty($the_sidebar_id)) {
                $the_sidebar_name = $sidebar_name;
            }

            // Setup the widget object data
            $obj_data[$widget_id] = $this->getWidgetObjectData([
                'sidebar_id'   => $sidebar_id,
                'widget_id'    => $widget_id,
                'sidebar_name' => $sidebar_name,
                'widget_title' => $widget_title,
            ]);
        }

        $widget_info = rtrim($widget_info, $break_line_2);

        // Setup the main message
        $pluralize_main_msg = $this->getVar($event_translations, '_main', '');

        if (count($obj_data) > 1) {
            $event_msg_args['_main'] = $this->getVar($pluralize_main_msg, 'plural');
        }

        $event_msg_args['_main'] = sprintf( $event_msg_args['_main'], $the_sidebar_name );

        $this->overrideActiveEventData('message', $event_msg_args);

        $this->setupEventMsgData('widget', compact(
            'obj_data',
            'object_id',
            'widget_info'
        ));

        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * @see alm_widget_location_changed action hook
     */
    public function alm_widget_location_changed_event($widget_args)
    {
        global $wp_registered_widgets, $wp_registered_sidebars;

        $widget_id                = $this->getVar($widget_args, 'widget_id');
        $sidebar_id               = $this->getVar($widget_args, 'sidebar_id');
        $new_widget_position      = $this->getVar($widget_args, 'widget_position') + 1;
        $previous_sidebar_id      = $this->getVar($widget_args, 'old_sidebar_id');
        $previous_widget_position = $this->getVar($widget_args, 'old_widget_position') + 1;

        $object_id                = $this->getWpOptionId($this->sidebar_widgets_option_name);

        $sidebar                  = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
        $old_sidebar              = $this->getVar($wp_registered_sidebars, $previous_sidebar_id, []);

        $widget_data              = $this->getVar($wp_registered_widgets, $widget_id, []);

        $sidebar_name             = $this->getVar($sidebar, 'name', $sidebar_id);
        $previous_sidebar_name    = $this->getVar($old_sidebar, 'name', $previous_sidebar_id);

        $custom_title             = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
        $widget_title             = $this->getVar($widget_data, 'name', $custom_title);

        $new_sidebar_id           = $sidebar_id;
        $new_sidebar_name         = $sidebar_name;

        $setup_event_data         = compact(
            'object_id',
            'widget_id',
            'widget_title',
            'new_sidebar_id',
            'new_sidebar_name',
            'new_widget_position',
            'previous_sidebar_id',
            'previous_sidebar_name',
            'previous_widget_position'
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('widget', $setup_event_data);
        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * Fires immediately after a widget has been marked for deletion.
     */
    public function delete_widget_event($widget_id, $sidebar_id, $id_base)
    {
        global $wp_registered_widgets, $wp_registered_sidebars;

        $event_slug         = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id           = $this->getEventIdBySlug($event_slug, 'widget');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);

        $object_id          = $this->getWpOptionId($this->sidebar_widgets_option_name);

        $sidebar            = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
        $widget_data        = $this->getVar($wp_registered_widgets, $widget_id, []);

        $sidebar_name       = $this->getVar($sidebar, 'name', $sidebar_id);

        // Auto get name
        switch($sidebar_id) {
            case $this->wp_inactive_widgets_key:
                $sidebar_name = 'Inactive Widgets';
                break;
        }

        $custom_title       = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
        $widget_title       = $this->getVar($widget_data, 'name', $custom_title);

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $sidebar_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $setup_event_data   = compact(
            'object_id',
            'widget_id',
            'sidebar_id',
            'widget_title',
            'sidebar_name',
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('widget', $setup_event_data);
        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * Fires immediately after a widget has been set as inactive
     */
    public function alm_widget_inactive_event($widget_id, $sidebar_id, $widget_position)
    {
        global $wp_registered_widgets, $wp_registered_sidebars;

        $object_id                = $this->getWpOptionId($this->sidebar_widgets_option_name);

        $sidebar                  = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
        $widget_data              = $this->getVar($wp_registered_widgets, $widget_id, []);

        $sidebar_name             = $this->getVar($sidebar, 'name', $sidebar_id);

        $custom_title             = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
        $widget_title             = $this->getVar($widget_data, 'name', $custom_title);

        $previous_sidebar_id      = $sidebar_id;
        $previous_sidebar_name    = $sidebar_name;
        $previous_widget_position = $widget_position;

        $setup_event_data   = compact(
            'object_id',
            'widget_id',
            'widget_title',
            'previous_sidebar_id',
            'previous_sidebar_name',
            'previous_widget_position'
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('widget', $setup_event_data);
        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * Inactive widgets event helper
     * 
     * @see 
     */
    protected function _inactiveWidgetEventHelper($widget_ids, $callback_name, $is_widget_cleared = false)
    {
        global $wp_registered_widgets;

        $event_slug         = $this->getEventSlugByEventHandlerName($callback_name);
        $event_id           = $this->getEventIdBySlug($event_slug, 'widget');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);
        $event_translations = $this->getVar($event_data, '_translate', []);

        $obj_data           = [];
        $object_id          = $this->getWpOptionId($this->sidebar_widgets_option_name);
        $break_line         = $this->getEventMsgLineBreak();
        $break_line_2       = str_repeat($break_line, 2);

        $widget_info        = '';

        foreach ($widget_ids as $widget_id) {
            $widget_data  = $this->getVar($wp_registered_widgets, $widget_id, []);

            $custom_title = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
            $widget_title = $this->getVar($widget_data, 'name', $custom_title);

            $widget_info .= sprintf('Widget ID: %s%s',    $widget_id,    $break_line);
            $widget_info .= sprintf('Widget title: %s%s', $widget_title, $break_line);

            if (!$is_widget_cleared) {
                $widget_info .= sprintf(
                    'Sidebar name: %s%s',
                    'None',
                    $break_line_2
                );
            } else {
                $widget_info .= $break_line;
            }

            // Setup the widget object data
            $obj_data[$widget_id] = $this->getWidgetObjectData([
                'widget_id'    => $widget_id,
                'widget_title' => $widget_title,
            ]);
        }

        $widget_info = rtrim($widget_info, $break_line_2);

        // Setup the main message
        $pluralize_main_msg = $this->getVar($event_translations, '_main', '');

        if (count($obj_data) > 1) {
            $event_msg_args['_main'] = $this->getVar($pluralize_main_msg, 'plural');
        }

        $this->overrideActiveEventData('message', $event_msg_args);

        $this->setupEventMsgData('widget', compact(
            'obj_data',
            'object_id',
            'widget_info'
        ));
    }

    /**
     * Fires immediately after a widget has been set as inactive
     */
    public function alm_widget_inactive_no_sidebar_event($widget_ids)
    {
        $this->_inactiveWidgetEventHelper($widget_ids, __FUNCTION__);
        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * Fires immediately after the list of inactive widgets has beeen cleared
     */
    public function alm_inactive_widgets_cleared_event($widget_ids)
    {
        $this->_inactiveWidgetEventHelper($widget_ids, __FUNCTION__, true);
        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * Fires immediately after the widget settings are saved
     */
    public function alm_widget_content_modified_event($widget_args)
    {
        global $wp_registered_widgets, $wp_registered_sidebars;

        $event_slug         = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id           = $this->getEventIdBySlug($event_slug, 'widget');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);

        $widget_id          = $this->getVar($widget_args, 'widget_id');
        $sidebar_id         = $this->getVar($widget_args, 'sidebar_id');
        $option_name        = $this->getVar($widget_args, 'option_name');
        $settings_updated   = $this->getVar($widget_args, 'settings_updated');
        $current_settings   = $this->getVar($widget_args, 'current_settings');
        $previous_settings  = $this->getVar($widget_args, 'previous_settings');
        
        $object_id          = $this->getWpOptionId($option_name);

        $sidebar            = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
        $widget_data        = $this->getVar($wp_registered_widgets, $widget_id, []);

        $sidebar_name       = $this->getVar($sidebar, 'name', $sidebar_id);

        $custom_title       = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
        $widget_title       = $this->getVar($widget_data, 'name', $custom_title);

        $widget_position    = $this->getVar($widget_args, 'widget_position');
        if (!is_int($widget_position)) {
            $widget_position = 'Unknown';
        } else {
            $widget_position += 1;
        }
        
        // Stringify the widget settings array
        $break_line       = $this->getEventMsgLineBreak();
        $break_line_2     = str_repeat($break_line, 2);
        $widget_settings  = '';

        foreach ($settings_updated as $setting_name => $_widget_setting)
        {
            $widget_settings .= sprintf(
                'Previous %s: %s%s',
                $this->sanitizeOption($setting_name),
                $this->sanitizeOption($this->getVar($previous_settings, $setting_name)),
                $break_line
            );

            $widget_settings .= sprintf(
                'New %s: %s%s',
                $this->sanitizeOption($setting_name),
                $this->sanitizeOption($this->getVar($current_settings, $setting_name)),
                $break_line_2
            );
        }

        $widget_settings = rtrim($widget_settings, $break_line_2);

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $sidebar_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $setup_event_data = compact(
            'object_id',
            'widget_id',
            'sidebar_id',
            'option_name',
            'sidebar_name',
            'widget_title',
            'widget_position',
            'widget_settings'
        );

        // Setup the widget object data
        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('widget', $setup_event_data);
        $this->LogActiveEvent('widget', __METHOD__);
    }
}