<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Navigation Menus Event Handlers
 * @since   1.0.0
 */

trait NavMenuEvents
{
    /**
     * Fires after a navigation menu is successfully created.
     */
    public function wp_create_nav_menu_event($menu_id, $menu_data)
    {
        $event_slug         = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id           = $this->getEventIdBySlug($event_slug, 'nav_menu');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);

        $object_id         = $menu_id;
        $nav_menu_obj      = wp_get_nav_menu_object($menu_id);

        $taxonomy          = $this->getVar($nav_menu_obj, 'taxonomy');
        $menu_name         = $this->getVar($nav_menu_obj, 'name');
        $menu_slug         = $this->getVar($nav_menu_obj, 'slug');
        $menu_parent       = $this->getVar($nav_menu_obj, 'parent');
        $description       = $this->getVar($nav_menu_obj, 'description');

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $menu_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $setup_event_data = compact(
            'object_id',
            'taxonomy',
            'menu_name',
            'menu_slug',
            'menu_parent',
            'description',
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('nav_menu', $setup_event_data);
        $this->LogActiveEvent('nav_menu', __METHOD__);
    }

    /**
     * Fires after a navigation menu has been successfully updated.
     * 
     * Note: The 'wp_update_nav_menu' action {@see wp_nav_menu_update_menu_items()} is not 
     * passed a second parameter, so we have to bail out where necessary.
     */
    public function wp_update_nav_menu_event($menu_id, $menu_data = null)
    {
        if (empty($menu_data))
            return;

        $event_slug       = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id         = $this->getEventIdBySlug($event_slug, 'nav_menu');
        $event_data       = $this->getEventData($event_id);
        $event_msg_args   = $this->getVar($event_data, 'message', []);

        $break_line       = $this->getEventMsgLineBreak();
        $break_line_2     = str_repeat($break_line, 2);

        $object_id        = $menu_id;
        $nav_menu_obj     = wp_get_nav_menu_object($menu_id);

        $obj_data         = [];
        $taxonomy         = $this->getVar($nav_menu_obj, 'taxonomy');
        $menu_info        = '';
        $menu_name        = $this->getVar($nav_menu_obj, 'name');
        $menu_slug        = $this->getVar($nav_menu_obj, 'slug');
        $menu_parent      = $this->getVar($nav_menu_obj, 'parent');
        $description      = $this->getVar($nav_menu_obj, 'description');

        $menu_data_labels = [
            'menu-name' => 'Menu name',
        ];

        $nav_menu_keys = [
            'menu-name' => 'name',
        ];

        foreach ($menu_data as $key => $value)
        {
            $nav_menu_key = $this->getVar($nav_menu_keys, $key);
            if (empty($nav_menu_key)) continue;

            $_key       = $this->sanitizeOption($key);
            $prev_value = $this->getVar($this->wp_nav_menu_data_before_update, $nav_menu_key);

            $new_val    = $this->sanitizeOption($value);
            $prev_val   = $this->sanitizeOption($prev_value);

            if ($prev_val !== $new_val) {
                $new_key  = $this->getVar($menu_data_labels, $key, $_key);
                $prev_key = $new_key;

                $menu_info .= sprintf(
                    'Previous %s: %s%s',
                    $prev_key,
                    $prev_val,
                    $break_line
                );

                $menu_info .= sprintf(
                    'New %s: %s%s',
                    $new_key,
                    $new_val,
                    $break_line_2
                );

                $obj_data["previous_{$prev_key}"] = $prev_val;
                $obj_data["new_{$new_key}"]       = $new_val;
            } else {
                $obj_data[$_key] = $new_val;
            }
        }

        $menu_info .= $this->getNavMenuSettings($menu_id);

        // Maybe the 'save menu' button was clicked without making any changes
        if (empty($menu_info)) {
            // $menu_info = 'Update was triggered but the settings were unchanged';
            return;
        }

        // Event main message
        $use_menu_name = $this->getVar($this->wp_nav_menu_data_before_update, 'name');
        if (empty($use_menu_name)) {
            $use_menu_name = $menu_name;
        }
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $use_menu_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $this->setupEventMsgData('nav_menu', compact(
            'obj_data',
            'object_id',
            'menu_info'
        ));

        $this->LogActiveEvent('nav_menu', __METHOD__);
    }

    /**
     * Fires after a navigation menu has been successfully deleted.
     */
    public function wp_delete_nav_menu_event($menu_id)
    {
        $event_slug         = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id           = $this->getEventIdBySlug($event_slug, 'nav_menu');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);

        $object_id          = $menu_id;
        $nav_menu_obj       = $this->wp_nav_menu_data_before_update;

        $taxonomy           = $this->getVar($nav_menu_obj, 'taxonomy');
        $menu_name          = $this->getVar($nav_menu_obj, 'name');
        $menu_slug          = $this->getVar($nav_menu_obj, 'slug');
        $menu_parent        = $this->getVar($nav_menu_obj, 'parent');
        $description        = $this->getVar($nav_menu_obj, 'description');
        
        $previous_locations = array_keys($this->previous_menu_locations);
        $menu_locations     = empty($previous_locations) ? 'None' : implode(', ', $previous_locations);

        $auto_add_pages     = $this->can_auto_add_top_level_pages_to_menu ? 'Yes' : 'No';

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $menu_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $setup_event_data = compact(
            'object_id',
            'taxonomy',
            'menu_name',
            'menu_slug',
            'menu_parent',
            'description',
            'auto_add_pages',
            'menu_locations',
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('nav_menu', $setup_event_data);
        $this->LogActiveEvent('nav_menu', __METHOD__);
    }

    /**
     * Update the nav menu item args if the supplied default is empty
     * @param  array $args The nav menu item data
     * @return array       The updated nav menu item data
     */
    protected function _maybeUpdateNavMenuItemArgs($args)
    {
        $object_id = $this->getVar($args, 'menu-item-object-id', 0);
        if (empty($object_id)) return $args;

        $item_type           = $this->getVar($args, 'menu-item-type');
        $item_url            = $this->getVar($args, 'menu-item-url');
        $item_title          = $this->getVar($args, 'menu-item-title');
        $item_object         = $this->getVar($args, 'menu-item-object');

        $is_item_url_empty   = empty($item_url);
        $is_item_title_empty = empty($item_title);

        if ('custom' === $item_type) return $args;

        switch ($item_type) {
            case 'taxonomy':
                if ($is_item_title_empty)
                    $args['menu-item-title'] = get_term_field('name', $object_id, $item_object, 'raw');

                if ($is_item_url_empty)
                    $args['menu-item-url'] = get_term_link($object_id, $item_object);

                break;

            case 'post_type':
                if ($is_item_title_empty)
                    $args['menu-item-title'] = $this->getVar(get_post($object_id), 'post_title');

                if (empty($is_item_url_empty)) 
                    $args['menu-item-url'] = get_the_permalink($object_id);

                break;

            case 'post_type_archive':
                if ($is_item_title_empty) {
                    $archive_labels          = $this->getVar(get_post_type_object($object_id), 'labels');
                    $args['menu-item-title'] = $this->getVar($archive_labels, 'post_title');
                }

                if (empty($is_item_url_empty))
                    $args['menu-item-url'] = get_post_type_archive_link($item_object);

                break;
            
            default:
                // Nothing
                break;
        }

        if (!$args['menu-item-url'])   $args['menu-item-url']   = '';
        if (!$args['menu-item-title']) $args['menu-item-title'] = '';

        return $args;
    }

    /**
     * Nav menu item helper
     * 
     * @see wp_add_nav_menu_item action hook
     * @see wp_update_nav_menu_item action hook
     * 
     * @return bool True if the nav menu item helper is setup successfully. Otherwise false.
     */
    protected function _runNavMenuItemHelper($menu_id, $menu_item_db_id, $args, $event_handler, $is_update = false)
    {
        $menu_id_from_post_req   = $this->sanitizeOption($this->getVar($_POST, 'menu', 0));
        $menu_item_in_draft_mode = false;

        if (0 === $menu_id && $menu_id_from_post_req > 0) {
            $menu_id = $menu_id_from_post_req;

            $menu_item_in_draft_mode = true;
        }

        if (0 === $menu_id) return;

        $event_slug         = $this->getEventSlugByEventHandlerName($event_handler);
        $event_id           = $this->getEventIdBySlug($event_slug, 'nav_menu');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);
        $setup_event_data   = [];

        $break_line         = $this->getEventMsgLineBreak();
        $break_line_2       = str_repeat($break_line, 2);

        $all_menu_locations = get_nav_menu_locations();

        $nav_menu_obj       = wp_get_nav_menu_object($menu_id);

        if (!$nav_menu_obj) return;

        $object_id          = $menu_id;

        // Update the args if default is empty
        $args               = $this->_maybeUpdateNavMenuItemArgs($args);

        $item_ID            = $this->sanitizeOption($menu_item_db_id, 'int');
        $item_url           = $this->sanitizeOption($this->getVar($args, 'menu-item-url'),   'url');
        $item_type          = $this->sanitizeOption($this->getVar($args, 'menu-item-type'));
        $item_title         = $this->sanitizeOption($this->getVar($args, 'menu-item-title'));
        $item_object        = $this->sanitizeOption($this->getVar($args, 'menu-item-object', 'Unknown'));
        $item_object_id    = $this->sanitizeOption($this->getVar($args, 'menu-item-object-id', 0), 'int');

        // Use the original object url if the url is empty
        // When dealing with Category type, an 'empty term' error is thrown
        if (empty($item_url) || is_wp_error($item_url)) {
            $item_url = $this->getNavMenuItemUrl($item_object_id, $item_object);
        }

        // We don't want nullable or false values to be set as the item url
        if (empty($item_url)) $item_url = '';

        $nav_menu_obj       = wp_get_nav_menu_object($menu_id);

        $taxonomy           = $this->getVar($nav_menu_obj, 'taxonomy');
        $menu_name          = $this->getVar($nav_menu_obj, 'name');
        $menu_slug          = $this->getVar($nav_menu_obj, 'slug');
        $menu_parent        = $this->getVar($nav_menu_obj, 'parent');

        /**
         * The menu locations are not updated until the request is reloaded 
         * on the menu page.
         */
        $previous_locations = array_keys($this->previous_menu_locations);
        $menu_locations     = empty($previous_locations) ? 'None' : implode(', ', $previous_locations);

        // Get the meu locations
        if (empty($previous_locations)) {
            $join_menu_locations = [];
            foreach ($all_menu_locations as $the_menu_name => $the_menu_location) {
                if ($the_menu_location == $menu_id) {
                    $join_menu_locations[] = $this->sanitizeOption($the_menu_name);
                }
            }

            if (!empty($join_menu_locations)) {
                $menu_locations = implode(', ', $join_menu_locations);
            }
        }

        if (!$is_update) {
            // Event main message
            $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $item_title, $menu_name);
        }
        else {
            // Event main message
            $item_info                  = '';
            $event_msg_args['_main']    = sprintf($event_msg_args['_main'], $menu_name, $item_title);

            $prev_menu_item_metadata    = $this->_getPreEventData($item_object_id);
            $menu_title_from_post_req   = $this->getVar($_POST, 'menu-item-title', []);
            $menu_data_from_wp_post_obj = $this->_getPreEventData('post');
            
            $prev_menu_position         = $this->getVar($menu_data_from_wp_post_obj, 'menu_order', 0);
            $_prev_menu_position        = $this->sanitizeOption($prev_menu_position, 'int');
            $prev_menu_item_title       = $this->getVar($menu_data_from_wp_post_obj, 'post_title');

           foreach ($args as $key => $value)
            {
                // All menu item post metadata is prefixed with an underscore '_'
                // Also, replace dashes with underscore
                $menu_item_meta_key = '_' . str_replace( '-', '_', $key);

                // Properly setup the menu item parent key
                if ('menu-item-parent-id' === $key) {
                    $menu_item_meta_key = '_menu_item_menu_item_parent';
                }

                $the_prev_menu_item_key_value = $this->getVar($prev_menu_item_metadata, $menu_item_meta_key, '');

                // Check if the menu item has been updated
                $is_menu_item_updated = isset($prev_menu_item_metadata[$menu_item_meta_key]);

                /**
                 * Prevent non-scalar values from going through.
                 * 
                 * Note: All updated menu item will still be logged using 
                 * the {@see update_post_meta()}
                 */
                if (is_countable($the_prev_menu_item_key_value))
                    continue;

                switch($key) {
                    case 'menu-item-title':
                        $clean_value = $this->sanitizeOption($value);

                        // We have to make sure the menu title has been updated
                        $the_menu_item_title_from_post_req = $this->sanitizeOption($this->getVar(
                            $menu_title_from_post_req, $item_ID, ''
                        ));

                        if ($value != $prev_menu_item_title 
                        && $clean_value !== $the_menu_item_title_from_post_req)
                        {
                            // Get the meu item post meta info.
                            $item_info .= $this->getNavMenuItemPostMetaInfo($item_object_id, $menu_item_meta_key);
                            $item_info .= $break_line;

                            $item_info .= sprintf(
                                'Previous menu item title: %s%s',
                                $this->sanitizeOption($prev_menu_item_title),
                                $break_line
                            );

                            $item_info .= sprintf(
                                'New menu item title: %s%s',
                                $clean_value,
                                $break_line_2
                            );
                        }
                        break;

                    case 'menu-item-position':
                        $_value = $this->sanitizeOption($value, 'int');

                        if ($_value > 0 
                        && $_prev_menu_position > 0 
                        && $_value != $_prev_menu_position)
                        {
                            // Get the meu item post meta info.
                            $item_info .= $this->getNavMenuItemPostMetaInfo($item_object_id, $menu_item_meta_key);
                            $item_info .= $break_line;

                            $item_info .= sprintf(
                                'Previous menu item position: %s%s',
                                $_prev_menu_position,
                                $break_line
                            );

                            $item_info .= sprintf(
                                'New menu item position: %s%s',
                                $_value,
                                $break_line_2
                            );
                        }
                        break;

                    case 'menu-item-url':
                        // Menu URL changes is only applicable on "custom" links
                        if ($is_menu_item_updated && 'custom' === $item_type)
                        {
                            $_value             = $this->sanitizeOption($value, 'url');
                            $prev_menu_item_url = $this->sanitizeOption( $the_prev_menu_item_key_value, 'url');
                            
                            if ($_value != $prev_menu_item_url) {
                                // Get the meu item post meta info.
                                $item_info .= $this->getNavMenuItemPostMetaInfo($item_object_id, $menu_item_meta_key);
                                $item_info .= $break_line;

                                $item_info .= sprintf(
                                    'Previous menu item url: %s%s',
                                    $prev_menu_item_url,
                                    $break_line
                                );

                                $item_info .= sprintf(
                                    'New menu item url: %s%s',
                                    $_value,
                                    $break_line_2
                                );
                            }
                        }
                        break;

                    case 'menu-item-parent-id':
                        if ($is_menu_item_updated)
                        {
                            $_value                 = $this->sanitizeOption($value, 'int');
                            $_prev_menu_item_parent = $this->sanitizeOption($the_prev_menu_item_key_value, 'int');
                            
                            if ($_prev_menu_item_parent != $_value)
                            {
                                // Get the meu item post meta info.
                                $item_info .= $this->getNavMenuItemPostMetaInfo($item_object_id, $menu_item_meta_key);
                                $item_info .= $break_line;

                                $prev_menu_parent_info = sprintf(
                                    '%s (Parent ID: %d)',
                                    $this->getVar(get_post($_prev_menu_item_parent), 'post_title', 'Unknown'),
                                    $_prev_menu_item_parent
                                );

                                $item_info .= sprintf(
                                    'Previous menu item parent: %s%s',
                                    ($_prev_menu_item_parent < 1) ? 'None' : $prev_menu_parent_info,
                                    $break_line
                                );

                                $new_menu_parent_info = sprintf(
                                    '%s (Parent ID: %d)',
                                    $this->getVar(get_post($_value), 'post_title', 'Unknown'),
                                    $_value
                                );

                                $item_info .= sprintf(
                                    'New menu item parent: %s%s',
                                    ($_value < 1) ? 'None' : $new_menu_parent_info,
                                    $break_line_2
                                );
                            }
                        }
                        break;

                    default:
                        if ($is_menu_item_updated && $this->isLogAggregatable())
                        {
                            $_value = is_countable($value) ? 
                                implode(', ', $value) : $this->sanitizeOption($value);

                            $prev_menu_item_value = $this->sanitizeOption($the_prev_menu_item_key_value);

                            $menu_item_label = strtolower($this->sanitizeOption($this->makeFieldReadable($key)));
                            
                            if ($_value != $prev_menu_item_value) {
                                // Get the meu item post meta info.
                                $item_info .= $this->getNavMenuItemPostMetaInfo($item_object_id, $menu_item_meta_key);
                                $item_info .= $break_line;

                                $item_info .= sprintf(
                                    'Previous %s: %s%s',
                                    $menu_item_label,
                                    $prev_menu_item_value,
                                    $break_line
                                );

                                $item_info .= sprintf(
                                    'New %s: %s%s',
                                    $menu_item_label,
                                    $_value,
                                    $break_line_2
                                );
                            }
                        }
                        break;
                }
            }

            $item_info = $this->rtrim($item_info, $break_line_2);

            if (empty($item_info)) return false;

            $menu_item_info   = $item_info;
            $setup_event_data = compact('menu_item_info');
        }

        $this->overrideActiveEventData('message', $event_msg_args);

        $menu_item_ID     = $item_ID;
        $menu_item_url    = $item_url;
        $menu_item_title  = $item_title;

        // Append a 'link' text to the item object if the object type is 'custom'
        if ('custom' === $item_object) {
            $item_object .= ' link';
        }

        $menu_item_type      = $item_object;
        $menu_item_object    = $item_object;
        $menu_item_object_ID = $item_object_id;

        // Set the menu item draft mode info
        $menu_item_in_draft_mode = '_ignore_';
        if ($menu_item_in_draft_mode) {
            $menu_item_in_draft_mode = sprintf(
                'Note: the menu item (%s) is currently in draft mode and will not be visible in the %s menu unless the settings are saved. You will see the updated event log for the menu item once the settings are saved.',
                $menu_item_title,
                $menu_name
            );
        }
        
        $setup_event_data = array_merge($setup_event_data, compact(
            'taxonomy',
            'object_id',
            'menu_name',
            'menu_slug',
            'menu_parent',
            'menu_item_ID',
            'menu_item_url',
            'menu_item_type',
            'menu_locations',
            'menu_item_title',
            'menu_item_object',
            'menu_item_object_ID',
            'menu_item_in_draft_mode'
        ));

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('nav_menu', $setup_event_data);

        return true;
    }

    /**
     * Fires after a navigation menu item has been added.
     */
    public function wp_add_nav_menu_item_event($menu_id, $menu_item_db_id, $args)
    {
        /**
         * Set the nav menu item creation flag.
         * 
         * We are doing this because the {@see 'wp_update_nav_menu_item'} action 
         * is called both when the menu item is added and updated.
         */
        $this->setConstant('ALM_WP_ADD_NAV_MENU_ITEM', true);

        if ($this->_runNavMenuItemHelper($menu_id, $menu_item_db_id, $args, __FUNCTION__)) 
            $this->LogActiveEvent('nav_menu', __METHOD__);
    }

    /**
     * Fires after a navigation menu item has been updated.
     */
    public function wp_update_nav_menu_item_event($menu_id, $menu_item_db_id, $args)
    {
        /**
         * Bail out if the nav menu item creation flag is set.
         */
        if (true === $this->getConstant('ALM_WP_ADD_NAV_MENU_ITEM'))
            return;

        $event_handler = $this->getConstant('ALM_FORCE_NAV_MENU_ITEM_ADDED_STATE') ?
        'wp_add_nav_menu_item_event' : __METHOD__;

        if ($this->_runNavMenuItemHelper($menu_id, $menu_item_db_id, $args, __FUNCTION__, true))
            $this->LogActiveEvent('nav_menu', $event_handler);
    }

    /**
     * Fires after a navigation menu item has been deleted.
     */
    public function alm_menu_item_deleted_event($menu_item_ID, $post)
    {
        $menu_id            = $this->sanitizeOption($this->getVar($_POST, 'menu', 0), 'int');

        $event_slug         = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id           = $this->getEventIdBySlug($event_slug, 'nav_menu');
        $event_data         = $this->getEventData($event_id);
        $event_msg_args     = $this->getVar($event_data, 'message', []);

        $object_id          = $menu_id;
        $nav_menu_obj       = wp_get_nav_menu_object($menu_id);
        $all_menu_locations = get_nav_menu_locations();

        $join_menu_locations = [];
        foreach ($all_menu_locations as $the_menu_name => $the_menu_location) {
            if ($the_menu_location == $menu_id) {
                $join_menu_locations[] = $this->sanitizeOption($the_menu_name);
            }
        }

        if (!empty($join_menu_locations)) {
            $menu_locations = implode(', ', $join_menu_locations);
        } else {
            $menu_locations = 'None';
        }

        $menu_item_object   = $this->sanitizeOption(get_post_meta($menu_item_ID, '_menu_item_type', true));
        $menu_item_url      = $this->getVar($this->wp_nav_menu_item_data_before_deletion, $menu_item_ID);
        $menu_item_type     = $menu_item_object;
        $menu_item_title    = $this->sanitizeOption($this->getVar($post, 'post_title', 'Unknown'));

        $taxonomy           = $this->getVar($nav_menu_obj, 'taxonomy');
        $menu_name          = $this->getVar($nav_menu_obj, 'name');
        $menu_slug          = $this->getVar($nav_menu_obj, 'slug');

        $setup_event_data   = compact(
            'menu_item_ID',
            'menu_item_title',
            'menu_item_object',
            'menu_item_type',
            'menu_item_url',
            'taxonomy',
            'object_id',
            'menu_name',
            'menu_slug',
            'menu_locations'
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $menu_item_title, $menu_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $this->setupEventMsgData('nav_menu', $setup_event_data);
        $this->LogActiveEvent('nav_menu', __METHOD__);
    }

    /**
     * Fires immediately after the nav menu location is updated
     */
    public function alm_menu_location_updated_event($menu_id, $new_location, $old_location)
    {
        $event_slug                = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id                  = $this->getEventIdBySlug($event_slug, 'nav_menu');
        $event_data                = $this->getEventData($event_id);
        $event_msg_args            = $this->getVar($event_data, 'message', []);

        $object_id                 = $menu_id;
        $nav_menu_obj              = wp_get_nav_menu_object($menu_id);

        $taxonomy                  = $this->getVar($nav_menu_obj, 'taxonomy');
        $menu_name                 = $this->getVar($nav_menu_obj, 'name');
        $menu_slug                 = $this->getVar($nav_menu_obj, 'slug');
        $menu_parent               = $this->getVar($nav_menu_obj, 'parent');

        $new_display_location      = empty($new_location) ? 'None' : implode(', ', $new_location);
        $previous_display_location = empty($old_location) ? 'None' : implode(', ', $old_location);

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $menu_name);

        $this->overrideActiveEventData('message', $event_msg_args);

        $setup_event_data = compact(
            'object_id',
            'taxonomy',
            'menu_name',
            'menu_slug',
            'menu_parent',
            'previous_display_location',
            'new_display_location'
        );

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('nav_menu', $setup_event_data);
        $this->LogActiveEvent('nav_menu', __METHOD__);
    }
}