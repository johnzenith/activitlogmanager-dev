<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Widget Events
 * @since   1.0.0
 */

trait WidgetEvents
{
    /**
     * Specifies the sidebar widgets option name
     * @var string
     * @since 1.0.0
     */
    protected $sidebar_widgets_option_name = 'sidebars_widgets';

    /**
     * Specifies the inactive widgets key
     * @var string 
     * @since 1.0.0
     */
    protected $wp_inactive_widgets_key  = 'wp_inactive_widgets';

    /**
     * Specifies the widget status
     * @var bool
     * @since 1.0.0
     */
    protected $widget_status = true;

    /**
     * Init the Widget Events
     */
    protected function initWidgetEvents()
    {
        $this->setupWidgetEvents();
        $this->registerWidgetEvents();
    }

    /**
     * Setup the widget events list
     */
    protected function setupWidgetEvents()
    {
        $this->event_list['widgets'] = [
            'title'           => 'Widget Events',
            'group'           => 'widget',
            'object'          => 'widget',
            'description'     => alm__('Responsible for logging all widget related activities.'),
            'object_id_label' => 'Widget Option ID',

            'events' => [
                /**
                 * Fires when an available widget is added to a sidebar
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_widget_added' => [
                    'title'               => 'Widget added',
                    'action'              => 'widget_added',
                    'event_id'            => 5651,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    'message' => [
                        '_main'            => 'Added a widget to the %s sidebar location',
                        'main_alt'         => 'Added a widget to the %s sidebar location from the list of inactive widgets',

                        '_space_start'     => '',
                        'widget_option_id' => ['object_id'],
                        'sidebar_id'       => ['sidebar_id'],
                        'sidebar_name'     => ['sidebar_name'],
                        'widget_id'        => ['widget_id'],
                        'widget_title'     => ['widget_title'],
                        'widget_position'  => ['widget_position'],
                        '_space_line'      => '',
                        'widget_settings'  => ['widget_settings'],
                        '_space_end'       => '',
                    ],
                ],

                /**
                 * Fires when a widget position changes
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_widget_position_changed' => [
                    'title'               => 'Widget position changed',
                    'action'              => 'widget_modified',
                    'event_id'            => 5652,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Changed the following widgets positions in the %s sidebar location, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'            => 'Changed a widget position in the %s sidebar location',

                        '_space_start'     => '',
                        'widget_option_id' => ['object_id'],
                        'widget_info'      => ['widget_info'],
                        '_space_end'       => '',
                    ],
                ],

                /**
                 * Fires when a widget is moved to a new location (sidebar)
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_widget_location_changed' => [
                    'title'               => 'Widget sidebar location changed',
                    'action'              => 'widget_modified',
                    'event_id'            => 5653,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Changed the following widgets sidebar locations, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'                    => 'Changed the sidebar location of a widget',

                        '_space_start'             => '',
                        'widget_option_id'         => ['object_id'],
                        'widget_id'                => ['widget_id'],
                        'widget_title'             => ['widget_title'],
                        '_space_line'              => '',
                        'previous_sidebar_id'      => ['previous_sidebar_id'],
                        'previous_sidebar_name'    => ['previous_sidebar_name'],
                        'previous_widget_position' => ['previous_widget_position'],
                        '_space_line'              => '',
                        'new_sidebar_id'           => ['new_sidebar_id'],
                        'new_sidebar_name'         => ['new_sidebar_name'],
                        'new_widget_position'      => ['new_widget_position'],
                        '_space_end'               => '',
                    ],
                ],

                /**
                 * Fires when a widget in a given sidebar is modified
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_widget_content_modified' => [
                    'title'                => 'Widget modified',
                    'action'               => 'widget_modified',
                    'event_id'             => 5654,
                    'severity'             => 'notice',

                    'screen'               => ['admin', 'network'],
                    'user_state'           => 'logged_in',
                    'logged_in_user_caps'  => ['edit_theme_options'],

                    'message' => [
                        '_main'            => 'Changed the content of a widget located in the %s sidebar',

                        '_space_start'     => '',
                        'widget_option_id' => ['object_id'],
                        'option_name'      => 'option_name',
                        'sidebar_id'       => ['sidebar_id'],
                        'sidebar_name'     => ['sidebar_name'],
                        'widget_id'        => ['widget_id'],
                        'widget_title'     => ['widget_title'],
                        'widget_position'  => ['widget_position'],
                        '_space_line'      => '',
                        'widget_settings'  => ['widget_settings'],
                        '_space_end'       => '',
                    ],
                ],

                /**
                 * Fires when a widget is deleted from a given sidebar
                 * 
                 * @see /wp-admin/widgets.php
                 */
                'delete_widget' => [
                    'title'               => 'Widget deleted',
                    'action'              => 'widget_deleted',
                    'event_id'            => 5655,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Deleted the following widgets, see details below',
                        ],
                    ],

                    'message' => [
                        '_main'            => 'Deleted a widget from the %s sidebar',

                        '_space_start'     => '',
                        'widget_option_id' => ['object_id'],
                        'sidebar_id'       => ['sidebar_id'],
                        'sidebar_name'     => ['sidebar_name'],
                        'widget_id'        => ['widget_id'],
                        'widget_title'     => ['widget_title'],
                        '_space_end'       => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires when a widget is set as inactive
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_widget_inactive' => [
                    'title'               => 'Widget set as inactive',
                    'action'              => 'widget_modified',
                    'event_id'            => 5656,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Moved the following widgets to the inactive widgets list, see details below:',
                        ],
                    ],

                    'message' => [
                        '_main'                    => 'Moved a widget to the inactive widgets list',

                        '_space_start'             => '',
                        'widget_option_id'         => ['object_id'],
                        'widget_id'                => ['widget_id'],
                        'widget_title'             => ['widget_title'],
                        'previous_widget_position' => ['previous_widget_position'],
                        'previous_sidebar_id'      => ['previous_sidebar_id'],
                        'previous_sidebar_name'    => ['previous_sidebar_name'],
                        '_space_end'               => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fire after adding a widget that was not previously active 
                 * but was added to the list of inactive widgets.
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_widget_inactive_no_sidebar' => [
                    'title'               => 'Widget added to the inactive widgets',
                    'action'              => 'widget_modified',
                    'event_id'            => 5657,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Added the following widgets to the inactive widgets list.',
                        ],
                    ],

                    'message' => [
                        '_main'            => 'Added a widget to the inactive widgets list',

                        '_space_start'     => '',
                        'widget_option_id' => ['object_id'],
                        'widget_info'      => ['widget_info'],
                        '_space_end'       => '',
                    ]
                ],

                /**
                 * Fire immediately after the list of inactive widgets has been cleared
                 * 
                 * @see wp_ajax_save_widget()
                 */
                'alm_inactive_widgets_cleared' => [
                    'title'               => 'Inactive widgets cleared',
                    'action'              => 'widget_modified',
                    'event_id'            => 5658,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Cleared the following widgets from the inactive widgets area.',
                        ],
                    ],

                    'message' => [
                        '_main'            => 'Cleared a widget from the inactive widgets area',

                        '_space_start'     => '',
                        'widget_option_id' => ['object_id'],
                        'widget_info'      => ['widget_info'],
                        '_space_end'       => '',
                    ]
                ],
            ]
        ];
    }

    /**
     * Register the widget custom events
     */
    protected function registerWidgetEvents()
    {
        /**
         * @see update_option()
         */
        $widget_action = sprintf('update_option_%s', $this->sidebar_widgets_option_name);
        add_action($widget_action, function($old_value, $value, $option)
        {
            global $wp_registered_widgets;
            
            $_sidebars                = $value;
            $has_new_widget           = false;
            $new_widget_counter       = 0;
            $inactive_widgets_key     = $this->wp_inactive_widgets_key;
            $inactive_widgets_cleared = false;

            /**
             * Check if the widget location has changed
             */
            foreach ((array) $value as $sidebar_id => $sidebar)
            {
                // Ignore inactive widgets
                if ($inactive_widgets_key === $sidebar_id)
                    continue;

                $_sidebars   = $value;
                $old_sidebar = (array) $this->getVar($old_value, $sidebar_id, []);

                foreach ((array) $sidebar as $widget_position => $widget_id)
                {
                    foreach ($_sidebars as $inner_sidebar_id => $widgets)
                    {
                        if (!is_array($widgets)) continue;

                        if ($inner_sidebar_id === $sidebar_id)
                            continue;

                        $old_location        = (array) $this->getVar($old_value, $inner_sidebar_id, []);
                        $old_widget_position = array_search($widget_id, $old_location, true);

                        if (false === $old_widget_position)
                            continue;

                        if ($inactive_widgets_key === $inner_sidebar_id) {
                            $inactive_widgets_cleared = true;
                        }

                        /**
                         * Prevent the {@see alm_widget_position_changed action hook} 
                         * from firing since the widget location has changed and this will 
                         * affect most widgets positions.
                         */
                        $this->widget_status = false;

                        /**
                         * Fires immediately after the widget location has changed
                         * 
                         * @since 1.0.0
                         * 
                         * @param array $widget_args List of widget info
                         * 
                         *  - @param string $widget_id           Specifies the newly added widget ID
                         * 
                         *  - @param int    $widget_position     Specifies the new widget position in 
                         *                                       the sidebar
                         * 
                         *  - @param string $sidebar_id          Specifies the new sidebar widget 
                         *                                       location
                         * 
                         *  - @param string $old_sidebar_id      Specifies the old sidebar widget 
                         *                                       location
                         */
                        do_action(
                            'alm_widget_location_changed',
                            [
                                'widget_id'           => $widget_id,
                                'sidebar_id'          => $sidebar_id,
                                'old_sidebar_id'      => $inner_sidebar_id,
                                'widget_position'     => $widget_position,
                                'old_widget_position' => $old_widget_position,
                            ]
                        );
                    }
                }
            }

            /**
             * Check for new added widget and widget content changes
             */
            foreach ((array) $value as $sidebar_id => $sidebar)
            {
                // Ignore inactive widgets
                if ($inactive_widgets_key === $sidebar_id)
                    continue;

                $old_sidebar    = (array) $this->getVar($old_value, $sidebar_id, []);

                // Get the difference
                $_sidebar       = (array) $sidebar;
                $sidebars_diff  = array_diff($_sidebar, $old_sidebar);

                $has_new_widget = count($sidebars_diff) > 0;

                // Check if a new widget has been added
                if ((empty($old_sidebar) && !empty($sidebar)) || $has_new_widget)
                {
                    foreach ($sidebars_diff as $widget_position => $widget_id)
                    {
                        // Remove the current sidebar from the $_sidebars array
                        unset($_sidebars[$sidebar_id]);

                        foreach ($_sidebars as $location_id => $location) {
                            // Ignore inactive widgets
                            if ($inactive_widgets_key === $location_id)
                                continue;

                            if (!is_array($location)) continue;

                            // Check whether the widget is actually a new one
                            if (in_array($widget_id, $location, true)) {
                                $this->widget_status = false;
                            }
                        }
                        
                        // This should not run when the widget was only moved to a new location
                        if (!$this->widget_status) 
                            continue;

                        ++$new_widget_counter;

                        // Check whether the widget was previously in the list 
                        // of inactive widgets
                        $was_widget_inactive = array_search(
                            $widget_id,
                            (array) $this->getVar($old_value, $inactive_widgets_key, []),
                            true
                        ) !== false;

                        /**
                         * Get the widget settings
                         */
                        $widget_control   = $wp_registered_widgets[$widget_id] ?? [];
 
                        $callback         = $widget_control['callback'] ?? [];
                        $widget_obj       = $callback[0] ?? [];

                        $_widget_settings = is_object($widget_obj) ? $widget_obj->get_settings() : false;

                        if ($_widget_settings) {
                            $widget_settings = current($_widget_settings);
                            if (!is_array($widget_settings)) {
                                $widget_settings = next($_widget_settings);
                            }
                        }

                        if (!$widget_settings) $widget_settings = [];

                        /**
                         * Fires immediately after a widget has been added
                         * 
                         * @since 1.0.0
                         * 
                         * @param array $widget_args Specifies list of argments containing information 
                         *                           about the new added widget.
                         * 
                         *  - @param string $widget_id       Specifies the newly added widget ID
                         * 
                         *  - @param int    $widget_position Specifies the new widget position
                         * 
                         *  - @param string $sidebar_id      Specifies the sidebar the widget was added to
                         * 
                         *  - @param bool   $widget_inactive Specifies whether or not the widget was 
                         *                                   previously in the inactive widgets list
                         * 
                         *  - @param array  $widget_settings Specifies list of settings for the widget.
                         *                                   This will be an empty array if no 
                         *                                   settings is registered for the widget.
                         *                                   
                         */
                        do_action(
                            'alm_widget_added',
                            [
                                'widget_id'           => $widget_id,
                                'sidebar_id'          => $sidebar_id,
                                'widget_position'     => $widget_position,
                                'widget_settings'     => $widget_settings,
                                'was_widget_inactive' => $was_widget_inactive,
                            ]
                        );
                    }
                }
            }

            /**
             * Check if the widget position has changed
             */
            $widget_args                 = [];
            $has_widget_changed_position = 0;

            if ($new_widget_counter < 1 && $this->widget_status)
            {
                foreach ((array) $value as $sidebar_id => $sidebar)
                {
                    // Ignore inactive widgets
                    if ($inactive_widgets_key === $sidebar_id)
                        continue;

                    if (!is_array($sidebar)) continue;

                    $old_sidebar = (array) $this->getVar($old_value, $sidebar_id, []);

                    foreach ($sidebar as $widget_position => $widget_id)
                    {
                        if (!is_array($old_sidebar)) continue;

                        $old_widget_position = array_search($widget_id, $old_sidebar, true);
                        
                        if ($old_widget_position !== $widget_position) {
                            $widget_args[] = [
                                'widget_id'           => $widget_id,
                                'sidebar_id'          => $sidebar_id,
                                'widget_position'     => $widget_position,
                                'old_widget_position' => $old_widget_position
                            ];
                        }
                    }
                }

                if (!empty($widget_args))
                {
                    /**
                     * Check that the last triggererd event is not the 'delete_widget' event,
                     * we want to avoid duplicates because when a widget is deleted, 
                     * the sidebr widgets are re-oredered.
                     */
                    $last_logged_event_id = $this->sanitizeOption($this->getLastActivityLogData(['event_id']), 'int');

                    $is_delete_widget_event = $this->getEventIdBySlug('delete_widget', 'widget') === $last_logged_event_id;

                    if ($is_delete_widget_event && !$this->isVerboseLoggingEnabled())
                        return;

                    ++$has_widget_changed_position;

                    /**
                     * Fires immediately after a widget position has changed
                     * 
                     * @since 1.0.0
                     * 
                     * @param array $widget_args Specifies list of argments containing information 
                     *                           about the changed widgets positions.
                     * 
                     *  - @param string $widget_id           Specifies the newly added widget ID
                     * 
                     *  - @param string $sidebar_id          Specifies the sidebar the widget was 
                     *                                       added to
                     * 
                     *  - @param int    $widget_position     Specifies the new widget position
                     * 
                     *  - @param int    $old_widget_position Specifies the old widget position
                     */
                    do_action('alm_widget_position_changed', $widget_args);
                }
            }

            /**
             * Check whether a widget has been added to the list of inactive widgets
             */
            $has_inactive_widgets     = 0;
            $inactive_widgets         = (array) $this->getVar($value, $inactive_widgets_key, []);
            $old_inactive_widgets     = (array) $this->getVar($old_value, $inactive_widgets_key, []);
            $widgets_without_sidebars = [];

            foreach ($inactive_widgets as $widget_id)
            {
                // Ignore old widgets that are in the inactive widgets list
                if (in_array($widget_id, $old_inactive_widgets, true))
                    continue;
                    
                $widgets_without_sidebars[$widget_id] = true;
                
                foreach ((array) $old_value as $inner_sidebar_id => $location)
                {
                    // Find the widget previous sidebar location
                    if ($inactive_widgets_key === $inner_sidebar_id)
                        continue;

                    if (empty($location) || !is_array($location))
                        continue;

                    // Make sure the widget exists in the inactive sidebar
                    // if (!in_array($widget_id, $inactive_widgets, ))

                    $widget_position = array_search($widget_id, $location, true);
                    
                    // The widget must be active in any of the sidebars
                    if (false === $widget_position)
                        continue;

                    /**
                     * Remove the widgets from the {@see $widgets_without_sidebars} array
                     */
                    unset($widgets_without_sidebars[$widget_id]);

                    ++$has_inactive_widgets;

                    /**
                     * Fire immediately after a widget is set as inactive
                     * 
                     * @since 1.0.0
                     * 
                     * @param string $widget_id       Specifies the newly added widget ID
                     * @param string $sidebar_id      Specifies the sidebar the widget was added to
                     * @param int    $widget_position Specifies the new widget position
                     */
                    do_action(
                        'alm_widget_inactive',
                        $widget_id, $inner_sidebar_id, $widget_position
                    );
                }
            }

            /**
             * Check whether the inactive widgets has been cleared
             */
            if ($new_widget_counter < 1 
            && $has_inactive_widgets < 1 
            && $has_widget_changed_position < 1  
            && !$inactive_widgets_cleared 
            //
            // The delete widget event should not trigger the 'inactive_widgets' 
            // cleared event
            && empty($this->getVar($value, $inactive_widgets_key, [])) 
            //
            // The cleared inactive widgets should not trigger when there is only 
            // one widget in the list of inactive widgets
            && count((array) $this->getVar($old_value, $inactive_widgets_key, [])) > 1 
            //
            && empty($widgets_without_sidebars) 
            && !empty($old_inactive_widgets))
            {
                /**
                 * Fires immediately after clearing the list of inactive widgets
                 * 
                 * @param array $inactive_widgets Specifies list of inactive widgets 
                 *                                that was cleared
                 */
                do_action('alm_inactive_widgets_cleared', array_values($old_inactive_widgets));
            }

            if (!empty($widgets_without_sidebars)) {
                /**
                 * Fire after adding a widget that was not previously active 
                 * but was added to the list of inactive widgets.
                 * 
                 * @since 1.0.0
                 * 
                 * @param array $widget_id Specifies list of inactive widgets IDs
                 */
                do_action('alm_widget_inactive_no_sidebar', array_keys($widgets_without_sidebars));
            }
        }, 10, 3);

        /**
         * Check for widget settings update
         */
        if (isset($_POST['id_base'], $_POST['widget-id'], $_POST['sidebar'], $_POST['multi_number']))
        {
            $widget_id_base       = $this->sanitizeStr($_POST['id_base']);
            $widget_settings_name = sprintf('update_option_widget_%s', $widget_id_base);

            /**
             * @see WP_Widget::save_settings()
             * @see update_option()
             */
            add_action($widget_settings_name, function($old_value, $value, $option) use (&$widget_id_base)
            {
                unset($old_value['_multiwidget'], $value['_multiwidget']);

                $sidebar_id   = wp_unslash($this->sanitizeOption($_POST['sidebar']));
                $multi_number = (int) $_POST['multi_number'];

                if (0 === $multi_number && isset($_POST['widget_number'])) {
                    $multi_number = (int) $_POST['widget_number'];
                }

                // $_POST['widget-id'] seems to be returning the last widgets in the 
                // inactive widgets list
                $widget_id         = sprintf('%s-%s', $widget_id_base, $multi_number);

                $_sidebars_widgets = wp_get_sidebars_widgets();
                $sidebar_widgets   = (array) $this->getVar($_sidebars_widgets, $sidebar_id, []);

                $option_name       = $option;
                $widget_position   = array_search($widget_id, $sidebar_widgets, true);
                $current_settings  = $this->getVar($value,     $multi_number, []);
                $previous_settings = $this->getVar($old_value, $multi_number, []);

                if (!is_array($previous_settings))
                    $previous_settings = [];

                if (!is_array($current_settings))
                    $current_settings = [];

                $settings_updated = $this->arrayDiffAssocRecursive($current_settings, $previous_settings);

                if (empty($settings_updated))
                    return;

                // Don't run when the widgets are added for the first time 
                if (!empty($_POST['add_new']) && !$this->isVerboseLoggingEnabled())
                    return;

                /**
                 * Fires immediately after the widget settings are updated
                 * 
                 * @since 1.0.0
                 * 
                 * @param array $widget_args Specifies list of arguments containing information 
                 *                           about the modified widget settings
                 * 
                 *  - @param string $widget_id       Specifies the widget ID
                 * 
                 *  - @param string $widget_position Specifies the widget position in the sidebar
                 * 
                 *  - @param string $sidebar_id      Specifies the sidebar of the widget
                 * 
                 *  - @param array $previous_settings Specifies an array containing the previous 
                 *                                   widget settings
                 * 
                 *  - @param array $current_settings Specifies an array containing the current 
                 *                                   widget settings
                 * 
                 *  - @param array $settings_updated Specifies an array containing only the updated  
                 *                                   widget settings
                 * 
                 *  - @param string $option_name     Specifies the widget settings option name
                 */
                do_action(
                    'alm_widget_content_modified',
                    [
                        'widget_id'         => $widget_id,
                        'sidebar_id'        => $sidebar_id,
                        'option_name'       => $option_name,
                        'widget_position'   => $widget_position,
                        'settings_updated'  => $settings_updated,
                        'current_settings'  => $current_settings,
                        'previous_settings' => $previous_settings,
                    ]
                );

            }, 10, 3);
        }
    }

    /**
     * Get the widget object data
     * @return string
     */
    public function getWidgetObjectData(array $widget_data = [])
    {
        return $widget_data;
    }

    /**
     * Stringify the widget settings
     * 
     * @param  array $_widget_settings List of widget settings
     * @return string
     */
    protected function stringifyWidgetSettings($_widget_settings)
    {
        if (empty($_widget_settings)) {
            $widget_settings = 'No settings';
        } else {
            $break_line      = $this->getEventMsgLineBreak();
            $widget_settings = $this->joinValues($_widget_settings, $break_line);
        }
        return $widget_settings;
    }
}