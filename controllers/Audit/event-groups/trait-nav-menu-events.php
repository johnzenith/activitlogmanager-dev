<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Navigation Menu Events
 * @since   1.0.0
 */

trait NavMenuEvents
{
    /**
     * Holds the previous menu locations before it is updated
     * @var array
     * @since 1.0.0
     * 
     * @see get_nav_menu_locations()
     */
    protected $previous_menu_locations = [];

    /**
     * Determines whether the menu locations has been updated
     * @var bool
     * @since 1.0.0
     */
    protected $is_menu_locations_updated = false;

    /**
     * Holds the given term data before it is updated
     * @var WP_Term|Null
     * @since 1.0.0
     */
    protected $wp_nav_menu_data_before_update = null;

    /**
     * Holds the nav menu item meta data before they are updated
     * @var array
     * @since 1.0.0
     */
    protected $wp_nav_menu_item_data_before_update = [];

    /**
     * Holds the nav menu item meta data before they are updated
     * @var array
     * @since 1.0.0
     */
    protected $wp_nav_menu_item_data_before_deletion = [];

    /**
     * Determines whether top level pages can be added to the menu.
     * This is used to get the 'Auto add pages' option before the menu is deleted.
     * 
     * @var bool
     * @since 1.0.0
     */
    protected $can_auto_add_top_level_pages_to_menu = true;

    /**
     * Init the menu events
     */
    protected function initNavMenuEvents()
    {
        $this->setupNavMenuEvents();
        $this->registerNavMenusEvents();
    }

    /**
     * Get the nav menu item post type
     * @return string The nav menu item post type
     */
    public function getNavMenuItemPostType()
    {
        return 'nav_menu_item';
    }

    /**
     * Setup the nav menu events
     */
    protected function setupNavMenuEvents()
    {
        $bail_term_event = [
            'event_group' => 'term',
            'event_type'  => 'nav_menu', // term type [taxonomy]
        ];

        $this->event_list['nav_menus'] = [
            'title'           => 'Menus Events',
            'group'           => 'nav_menu',
            'object'          => 'nav_menu',
            'taxonomy'        => 'nav_menu',
            'is_term'         => true,
            'description'     => alm__('Responsible for logging all menus related activities.'),
            'object_id_label' => 'Menu ID',

            'events' => [
                /**
                 * Fires after a menu is successfully created.
                 * 
                 * @see wp_update_nav_menu_object()
                 */
                'wp_create_nav_menu' => [
                    'title'                => 'Menu created',
                    'action'               => 'nav_menu_created',
                    'event_id'             => 5671,
                    'severity'             => 'notice',

                    'screen'               => ['admin', 'network'],
                    'user_state'           => 'logged_in',
                    'logged_in_user_caps'  => ['edit_theme_options'],

                    'bail_event_handler'  => $bail_term_event,

                    'message' => [
                        '_main'              => 'Created a new menu: %s',

                        '_space_start'       => '',
                        'menu_ID'            => ['object_id'],
                        'menu_name'          => ['menu_name'],
                        'menu_slug'          => ['menu_slug'],
                        'taxonomy'           => ['taxonomy'],
                        'description'        => ['description'],
                        '_space_end'         => '',
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],

                /**
                 * Fires after a menu has been successfully updated.
                 * 
                 * @see wp_update_nav_menu_object()
                 */
                'wp_update_nav_menu' => [
                    'title'               => 'Menu updated',
                    'action'              => 'nav_menu_modified',
                    'event_id'            => 5672,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    'bail_event_handler'  => $bail_term_event,

                    'message' => [
                        '_main'              => 'Changed the %s menu',

                        '_space_start'       => '',
                        'menu_ID'            => ['object_id'],
                        'menu_info'          => ['menu_info'],
                        '_space_end'         => '',
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],

                /**
                 * Fires after a menu has been successfully deleted.
                 * 
                 * @see wp_delete_nav_menu()
                 */
                'wp_delete_nav_menu' => [
                    'title'               => 'Menu deleted',
                    'action'              => 'nav_menu_deleted',
                    'event_id'            => 5673,
                    'severity'            => 'critical',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    'bail_event_handler'  => $bail_term_event,

                    'message' => [
                        '_main'              => 'Deleted the %s menu',

                        '_space_start'       => '',
                        'menu_ID'            => ['object_id'],
                        'menu_name'          => ['menu_name'],
                        'menu_slug'          => ['menu_slug'],
                        'taxonomy'           => ['taxonomy'],
                        'description'        => ['description'],
                        'menu_locations'     => ['menu_locations'],
                        'auto_add_pages'     => ['auto_add_pages'],
                        '_space_end'         => '',
                    ],
                ],

                /**
                 * Fires immediately after a new menu item has been added.
                 * 
                 * @see wp_update_nav_menu_item()
                 */
                'wp_add_nav_menu_item' => [
                    'title'               => 'Menu item added',
                    'action'              => 'nav_menu_item_added',
                    'event_id'            => 5674,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    'bail_event_handler'  => $bail_term_event,

                    'message' => [
                        '_main'                   => 'Added a new menu item (%s) to the %s menu',

                        '_space_start'            => '',
                        'menu_item_ID'            => ['menu_item_ID'],
                        'menu_item_object_ID'     => ['menu_item_object_ID'],
                        'menu_item_object'        => ['menu_item_object'],
                        'menu_item_type'          => ['menu_item_type'],
                        'menu_item_title'         => ['menu_item_title'],
                        'menu_item_url'           => ['menu_item_url'],
                        '_space_line'             => '',
                        'menu_item_in_draft_mode' => ['menu_item_in_draft_mode'],
                        '_space_line'             => '',
                        'menu_ID'                 => ['object_id'],
                        'menu_name'               => ['menu_name'],
                        'menu_slug'               => ['menu_slug'],
                        'taxonomy'                => ['taxonomy'],
                        'menu_locations'          => ['menu_locations'],
                        '_space_end'              => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires immediately after a new menu item has been updated.
                 * 
                 * @see wp_update_nav_menu_item()
                 */
                'wp_update_nav_menu_item' => [
                    'title'               => 'Menu item updated',
                    'action'              => 'nav_menu_item_modified',
                    'event_id'            => 5675,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    'wp_post_meta' => $this->_getNavMenuItemPostMetaFields(),

                    'bail_event_handler'  => $bail_term_event,

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Updated the %s menu items',
                        ],
                    ],

                    'message' => [
                        '_main'               => 'Updated the %s menu item (%s)',

                        '_space_start'        => '',
                        'menu_item_ID'        => ['menu_item_ID'],
                        'menu_item_title'     => ['menu_item_title'],
                        'menu_item_object_ID' => ['menu_item_object_ID'],
                        'menu_item_object'    => ['menu_item_object'],
                        'menu_item_type'      => ['menu_item_type'],
                        'menu_item_url'       => ['menu_item_url'],
                        'menu_item_info'      => ['menu_item_info'],
                        '_space_line'         => '',
                        'menu_ID'             => ['object_id'],
                        'menu_name'           => ['menu_name'],
                        'menu_slug'           => ['menu_slug'],
                        'taxonomy'            => ['taxonomy'],
                        'menu_locations'      => ['menu_locations'],
                        '_space_end'          => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires immediately after a new menu item has been deleted.
                 * 
                 * @see wp_delete_post()
                 */
                'alm_menu_item_deleted' => [
                    'title'               => 'Menu item deleted',
                    'action'              => 'nav_menu_item_deleted',
                    'event_id'            => 5676,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_theme_options'],

                    'wp_post_meta' => $this->_getNavMenuItemPostMetaFields(),

                    'bail_event_handler' => $bail_term_event,

                    '_translate' => [
                        '_main' => [
                            'plural' => 'Deleted the following %s menu items from the %s menu',
                        ],
                    ],

                    'message' => [
                        '_main'               => 'Deleted the (%s) menu item from the %s menu',

                        '_space_start'        => '',
                        'menu_item_ID'        => ['menu_item_ID'],
                        'menu_item_title'     => ['menu_item_title'],
                        'menu_item_object_ID' => ['menu_item_object_ID'],
                        'menu_item_object'    => ['menu_item_object'],
                        'menu_item_type'      => ['menu_item_type'],
                        'menu_item_parent'    => ['menu_item_parent'],
                        'menu_item_url'       => ['menu_item_url'],
                        '_space_line'         => '',
                        'menu_ID'             => ['object_id'],
                        'menu_name'           => ['menu_name'],
                        'menu_slug'           => ['menu_slug'],
                        'taxonomy'            => ['taxonomy'],
                        'menu_locations'      => ['menu_locations'],
                        '_space_end'          => '',
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],

                'alm_menu_location_updated' => [
                    'title'                => 'Menu location updated',
                    'action'               => 'nav_menu_location_modified',
                    'event_id'             => 5677,
                    'severity'             => 'notice',

                    'screen'               => ['admin', 'network'],
                    'user_state'           => 'logged_in',
                    'logged_in_user_caps'  => ['edit_theme_options'],

                    'bail_event_handler' => [
                        'event_group' => sprintf('theme_mods_%s', $this->current_theme),
                        'event_type'  => 'nav_menu_locations', // theme mod option name
                    ],

                    'message' => [
                        '_main'                     => 'Changed the %s menu display location',

                        '_space_start'              => '',
                        'menu_ID'                   => ['object_id'],
                        'menu_name'                 => ['menu_name'],
                        'menu_slug'                 => ['menu_slug'],
                        'taxonomy'                  => ['taxonomy'],
                        '_space_line'               => '',
                        'previous_display_location' => ['previous_display_location'],
                        'new_display_location'      => ['new_display_location'],
                        '_space_end'                => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],
            ]
        ];
    }

    /**
     * Register the nav menu events (actions)
     */
    protected function registerNavMenusEvents()
    {
        // Get the menu locations before it is updated
        add_action('update_option', function($option, $old_value, $value)
        {
            $theme = $this->current_theme;
            if ($option === "theme_mods_$theme") {
                $this->previous_menu_locations   = $this->getVar($old_value, 'nav_menu_locations', []);
                $this->is_menu_locations_updated = true;
            }
        }, 10, 3);

        // Get the term data before it is updated
        add_action('edit_terms', function($term_id, $taxonomy)
        {
            if ('nav_menu' === $taxonomy) {
                $this->wp_nav_menu_data_before_update = wp_get_nav_menu_object($term_id);
            }
        }, 10, 2);

        // Get the term data before it is deleted
        add_action('pre_delete_term', function ($term_id, $taxonomy)
        {
            if ('nav_menu' === $taxonomy) {
                $this->wp_nav_menu_data_before_update = wp_get_nav_menu_object($term_id);

                $menu      = $this->wp_nav_menu_data_before_update;
                $locations = get_nav_menu_locations();

                foreach ($locations as $location => $menu_id) {
                    if ($menu_id == $menu->term_id) {
                        $this->previous_menu_locations[ $location ] = $menu_id;
                    }
                }

                $auto_add = get_option('nav_menu_options');

                if (!isset($auto_add['auto_add'])) {
                    $auto_add = false;
                } elseif (false !== array_search($menu_id, $auto_add['auto_add'], true)) {
                    $auto_add = true;
                } else {
                    $auto_add = false;
                }

                $this->can_auto_add_top_level_pages_to_menu = $auto_add;
            }
        }, 10, 2);

        // Get the nav menu item data before it is updated
        add_action('update_postmeta', function($meta_id, $object_id, $meta_key, $meta_value)
        {
            if (in_array($meta_key, $this->_getNavMenuItemPostMetaFields(), true)) {
                // Remove the first character if it starts with an underscore
                $_meta_key = preg_replace('/^_+/', '', $meta_key);

                $_meta_key = str_replace('_', '-', $meta_value);

                $this->wp_nav_menu_item_data_before_update[$_meta_key] = $meta_value;
            }
        }, 10, 4);

        /**
         * Fires immediately before a post is deleted from the database.
         * We want to retrieve the menu item url before it is deleted.
         */
        add_action('delete_post', function($post_id)
        {
            $item_object   = get_post_meta($post_id, '_menu_item_type', true);
            $menu_item_url = $this->getNavMenuItemUrl($post_id, $item_object);
            
            if (empty($menu_item_url))
                $menu_item_url = '';

            $this->wp_nav_menu_item_data_before_deletion[$post_id] = $menu_item_url;
        }, 10, 2);

        /**
         * Fires when a nav menu item is deleted
         */
        add_action('after_delete_post', function($post_id, $post)
        {
            // The menu item has been deleted
            // So using the is_nav_menu_item($post_id) function wont' work
            if ($this->getNavMenuItemPostType() == $this->getVar($post, 'post_type', '')) {
                do_action('alm_menu_item_deleted', $post_id, $post);
            }
        }, 10, 2);

        /**
         * Register the nav menu location update event
         */
        $theme_mods_option = 'update_option_theme_mods_' . $this->current_theme;
        add_action($theme_mods_option, function($old_value, $value, $option)
        {
            $self                 = $this;
            $option_name          = 'nav_menu_locations';
            $new_location         = $this->getVar($value, $option_name, []);
            $previous_location    = $this->getVar($old_value, $option_name, []);
            $nav_menu_selected_id = $this->sanitizeOption($this->getVar($_REQUEST, 'menu', 0), 'int');

            // Using a closure to normalize the nav menu location(s)
            $normalize_location = function($locations) use (&$self, &$nav_menu_selected_id)
            {
                $valid_location       = [];
                $registered_locations = get_registered_nav_menus();

                foreach ( $locations as $location => $menu_id ) {
                    $_menu_id = $self->sanitizeOption($menu_id, 'int');

                    if ($_menu_id === $nav_menu_selected_id) {
                        $description      = $this->getVar($registered_locations, $location, $location);
                        $valid_location[] = empty($description) ? $location : $description;
                    }
                }

                if (empty($valid_location)) {
                    $set_location = [];
                } else {
                    $set_location = $valid_location;
                }
                return $set_location;
            };

            $_new_location      = $normalize_location($new_location);
            $_previous_location = $normalize_location($previous_location);

            /**
             * Fires immediately after updating the nav menu location
             * 
             * @param int $nav_menu_selected_id Specifies the nav menu ID
             * @param array $new_location Specifies the new menu location
             * @param array $old_location Specifies the old menu location
             */
            do_action(
                'alm_menu_location_updated',
                $nav_menu_selected_id,
                $_new_location,
                $_previous_location, 
            );
        }, 10, 3);
    }

    /**
     * Get the nav menu settings
     * 
     * @since 1.0.0
     * 
     * @param WP_Term $nav_menu_obj Specifies the term object or term ID
     * @param bool    $is_update    Specifies the context to use in retrieving the settings
     * 
     * @return string  Returns the menu settings as string if found. Otherwise empty string.
     */
    protected function getNavMenuSettings($nav_menu_obj, $is_update = true)
    {
        $no_settings_text = 'None';
        if (!is_object($nav_menu_obj) || empty($nav_menu_obj)) {
            if (is_int($nav_menu_obj)) {
                $nav_menu_obj = wp_get_nav_menu_object($nav_menu_obj);
            }

            if (!$nav_menu_obj)
                return $no_settings_text;
        }
            
        $menu_id = (int) $this->getVar($nav_menu_obj, 'term_id', 0);

        if (0 === $menu_id)
            return $no_settings_text;

        $auto_add           = get_option('nav_menu_options');
        $locations          = get_registered_nav_menus();
        $break_line         = $this->getEventMsgLineBreak();
        $break_line_2       = str_repeat($break_line, 2);
        $menu_settings      = '';
        $menu_locations     = get_nav_menu_locations();
        $_menu_locations    = $this->previous_menu_locations;
        $display_locations  = '';

        if (isset($_POST['save_menu'], $_POST['menu-locations'])) {
            $new_menu_locations = array_map('absint', $_POST['menu-locations']);
            $menu_locations     = array_merge($menu_locations, $new_menu_locations);
        }

        if (!isset($auto_add['auto_add'])) {
            $auto_add = false;
        } elseif (false !== array_search($menu_id, $auto_add['auto_add'], true)) {
            $auto_add = true;
        } else {
            $auto_add = false;
        }

        // Auto add top-level pages setting
        if ($is_update) {
            if (isset($_POST['save_menu'], $_POST['auto-add-pages'])) {
                $new_auto_add = 1 === (int) $_POST['auto-add-pages'];
            } else {
                $new_auto_add = false;
            }

            if ($new_auto_add !== $auto_add) {
                $menu_settings .= sprintf(
                    'Previous auto add top-level pages: %s%s',
                    $auto_add ? 'Yes' : 'No',
                    $break_line
                );

                $menu_settings .= sprintf(
                    'New auto add top-level pages: %s%s',
                    !empty($new_auto_add) ? 'Yes' : 'No',
                    $break_line_2
                );
            }
        }
        else {
            $menu_settings .= sprintf(
                'Auto add top-level pages: %s%s',
                $auto_add ? 'Yes' : 'No',
                $break_line_2
            );
        }

        // Previous display location
        $previous_display_location = '';
        if ($is_update) {
            foreach ($locations as $location => $description) {
                $_menu_location = $this->getVar($_menu_locations, $location);
                $checked        = $menu_id === $_menu_location;

                if (!$checked || empty($_menu_location))
                    continue;

                $previous_display_location .= $description . ', ';
            }

            $previous_display_location = $this->rtrim($previous_display_location, ', ');
        }

        $display_locations = sprintf(
            'Display location: %s',
            empty($previous_display_location) ? 'None' : $previous_display_location,
        );

        if (empty($menu_settings)) return '';

        $menu_settings .= $display_locations;
        $menu_settings  = $this->rtrim($menu_settings, $break_line_2);

        return $menu_settings;
    }

    /**
     * Get the nav menu item url
     * 
     * @param int    $item_ID     Specifies the nav menu item ID
     * @param string $item_object Specifies the nav menu item object type
     * 
     * @return string|false       Returns the nav menu item url on success. Otherwise false.
     */
    public function getNavMenuItemUrl($item_ID, $item_object = '')
    {
        switch($item_object) {
            case 'category':
                $item_url = get_category_link($item_ID);
                break;

            case 'custom':
                $item_url = get_post_meta($item_ID, '_menu_item_url', true);
                break;
            
            default:
            $item_url = get_the_permalink($item_ID);
                break;
        }

        if (is_wp_error($item_url)) 
            $item_url = '';

        return $this->sanitizeOption($item_url, 'url');
    }

    /**
     * Get the nav menu item post meta fields
     * @return array
     */
    protected function _getNavMenuItemPostMetaFields()
    {
        return [
            '_menu_item_type',
            '_menu_item_menu_item_parent',
            '_menu_item_object_id',
            '_menu_item_object',
            '_menu_item_target',
            '_menu_item_classes',
            '_menu_item_xfn',
            '_menu_item_url',
            '_menu_item_orphaned',
        ];
    }

    /**
     * Get the nav menu item post meta info.
     * 
     * @param  int   $meta_id  Specifies the nav menu item meta iD
     * @param string $meta_key Specifies the nav menu item meta key
     * 
     * @return string          Returns the nav menu item post meta info when on super mode.
     */
    protected function getNavMenuItemPostMetaInfo($meta_id, $meta_key)
    {
        $meta_id_info  = sprintf('Connected post meta ID: %s', $meta_id);
        $meta_key_info = sprintf('Connected post meta key: %s', $meta_key);

        return $meta_id_info . $this->getEventMsgLineBreak() . $meta_key_info;
    }
}