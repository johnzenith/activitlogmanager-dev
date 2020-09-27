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

        $event_slug       = preg_replace('/(_event)$/', '', __FUNCTION__);
        $event_id         = $this->getEventIdBySlug($event_slug, 'widget');
        $event_data       = $this->getEventData($event_id);
        $event_msg_args   = $this->getVar($event_data, 'message', []);

        $widget_id        = $this->getVar($widget_args, 'widget_id', '');
        $sidebar_id       = $this->getVar($widget_args, 'sidebar_id', '');
        $widget_inactive  = $this->getVar($widget_args, 'widget_inactive', false);
        $widget_position  = $this->getVar($widget_args, 'widget_position', '');

        $object_id        = $this->getWpOptionId($this->sidebar_widgets_option_name);
        $sidebar          = $this->getVar($wp_registered_sidebars, $sidebar_id, []);
        $widget_info      = $this->getVar($wp_registered_widgets, $widget_id, []);

        $sidebar_name    = $this->getVar($sidebar, 'name', $sidebar_id);

        $custom_title     = ucwords(preg_replace('/\-[\d]+$/', '', $widget_id), '-');
        $widget_title     = $this->getVar($widget_info, 'name', $custom_title);

        $widget_position += $widget_position; // Array index starts at 0.

        // Format the main event message
        $msg_key  = $widget_inactive ? 'main_alt': '_main';
        $main_msg = $this->getVar($event_msg_args, $msg_key, '');

        if (empty($main_msg)) return;

        $format_main_msg = sprintf($main_msg, $sidebar_name);

        $this->setupEventMsgData('widget', compact(
            'object_id',
            'widget_id',
            'sidebar_id',
            'sidebar_name',
            'widget_title',
            'widget_position'
        ));

        $this->LogActiveEvent('widget', __METHOD__);
    }

    /**
     * @see alm_widget_position_changed action hook
     */
    public function alm_widget_position_changed_event($widget_args)
    {
        $widget_id           = $this->getVar($widget_args, 'widget_id');
        $sidebar_id          = $this->getVar($widget_args, 'sidebar_id');
        $widget_position     = $this->getVar($widget_args, 'widget_position');
        $old_widget_position = $this->getVar($widget_args, 'old_widget_position');

        $sidebars           = wp_get_sidebars_widgets();
        $sidebar            = $this->getVar($sidebars, $sidebar_id, []);

        var_dump($widget_id, $sidebar_id);
    }

    /**
     * @see alm_widget_location_changed action hook
     */
    public function alm_widget_location_changed_event($widget_args)
    {
        $sidebars            = wp_get_sidebars_widgets();

        $widget_id           = $this->getVar($widget_args, 'widget_id');
        $sidebar_id          = $this->getVar($widget_args, 'sidebar_id');
        $widget_position     = $this->getVar($widget_args, 'widget_position');
        $old_sidebar_id      = $this->getVar($widget_args, 'old_sidebar_id');
        $old_widget_position = $this->getVar($widget_args, 'old_widget_position');

        // $sidebar      = isset( $sidebars[ $sidebar_id ] ) ? $sidebars[ $sidebar_id ] : array();
        var_dump($widget_id, $sidebar_id, $old_sidebar_id, $widget_position, $old_widget_position);
    }

    /**
     * Fires immediately after a widget has been marked for deletion.
     */
    public function delete_widget_event($widget_id, $sidebar_id, $id_base)
    {
        var_dump($widget_id);
    }

    /**
     * Fires immediately after a widget has been set as inactive
     */
    public function alm_widget_inactive_event($widget_id, $sidebar_id, $widget_position)
    {
        var_dump($widget_id, $sidebar_id, $widget_position);
    }

    /**
     * Fires immediately after a widget has been set as inactive
     */
    public function alm_widget_inactive_no_sidebar_event($widget_ids)
    {
        var_dump($widget_ids);
    }

    /**
     * Fires immediately after the list of inactive widgets has beeen cleared
     */
    public function alm_inactive_widgets_cleared_event($widget_ids)
    {
        var_dump($widget_ids);
    }

    /**
     * Fires immediately after the widget settings are saved
     */
    public function alm_widget_content_modified_event($previous_settings, $current_settings, $option_name)
    {
        var_dump($previous_settings, $current_settings, $option_name);exit;
    }
}