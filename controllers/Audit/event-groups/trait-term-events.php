<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Term Events
 * @since   1.0.0
 */

trait TermEvents
{
    /**
     * Default taxonomies in WordPress:
     * 
     * category
     * post_tag
     * post_format
     * link_category
     */
    protected $default_taxonomies = [
        // Taxonomy                // Object type
        'category'          => 'post',
        'post_tag'          => 'post',
        'nav_menu'          => 'nav_menu_item',
        'post_format'       => 'post_format',
        'link_category'     => 'link_category',
    ];

    /**
     * This method is called automatically by the 
     * {@see ALM\Controllers\Audit\Traits\EventList::registerAllEventGroups()} method
     */
    protected function initTermEvents()
    {
        $this->setupTermEvents();
    }

    /**
     * Register the term events
     */
    protected function setupTermEvents()
    {
        $this->event_list['term'] = [
            'title'           => 'Term Events',
            'group'           => 'term',
            'object'          => 'term',
            'description'     => alm__('Responsible for logging all term related activities such as: Categories, Tags, Post Formats, etc.'),
            'object_id_label' => 'Term ID',

            'events' => [
                /**
                 * Fires after a term has been saved, and the term cache has been cleared.
                 * 
                 * @see wp_insert_term()
                 */
                'saved_term' => [
                    'title'               => 'New %s created',
                    'action'              => 'term_created',
                    'event_id'            => 5701,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Created a new %s: %s',

                        '_space_start'          => '',
                        'term_id'               => ['object_id'],
                        'term_name'             => ['term_name'],
                        'term_slug'             => ['term_slug'],
                        'term_group'            => ['term_group'],
                        'taxonomy'              => ['taxonomy'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 4,
                    ],
                ],

                /**
                 * Fires after a term has been updated, and the term cache has been cleared.
                 * 
                 * @see wp_update_term()
                 */
                'alm_term_updated' => [
                    'title'               => '%s updated',
                    'action'              => 'term_updated',
                    'event_id'            => 5702,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Changed the %s: %s',

                        '_space_start'          => '',
                        'term_id'               => ['object_id'],
                        'term_name'             => ['term_name'],
                        'term_slug'             => ['term_slug'],
                        'term_group'            => ['term_group'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        'taxonomy'              => ['taxonomy'],
                        '_space_line'           => '',
                        'update_info'           => ['update_info'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 4,
                    ],
                ],

                /**
                 * Fires after a term is deleted from the database and the cache is cleaned.
                 * 
                 * @see wp_delete_term()
                 */
                'delete_term' => [
                    'title'               => '%s deleted',
                    'action'              => 'term_deleted',
                    'event_id'            => 5703,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Deleted the %s: %s',

                        '_space_start'          => '',
                        'term_id'               => ['object_id'],
                        'term_name'             => ['term_name'],
                        'term_slug'             => ['term_slug'],
                        'term_group'            => ['term_group'],
                        'taxonomy'              => ['taxonomy'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],

                        /**
                         * @see wp_set_object_terms()
                         * 
                         * This is used to get related post, link, etc. to a term and taxonomy.
                         * 
                         * Term Object Info:
                         *  - object_type
                         *  - object_id
                         *  - object_title
                         */
                        // 'term_object_info'      => ['term_object_info'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 5,
                    ],
                ],
            ]
        ];
    }

    /**
     * Register the term events
     */
    protected function registerTermEvents()
    {
        
    }
}
