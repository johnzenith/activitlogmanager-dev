<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Term Taxonomy Events
 * @since   1.0.0
 */

trait TermTaxonomyEvents
{
    /**
     * This method is called automatically by the 
     * {@see ALM\Controllers\Audit\Traits\EventList::registerAllEventGroups()} method
     */
    protected function initTermTaxonomyEvents()
    {
        $this->setupTermTaxonomyEvents();
    }

    /**
     * Register the term events
     */
    protected function setupTermTaxonomyEvents()
    {
        $this->event_list['term_taxonomy'] = [
            'title'           => 'Term Taxonomy Events',
            'group'           => 'term_taxonomy',
            'object'          => 'term_taxonomy',
            'description'     => alm__('Responsible for logging all term taxonomy relationship activities such as: Categories, Tags, Post Formats, etc.'),
            'object_id_label' => 'Term taxonomy ID',

            'events' => [
                /**
                 *  Fires after a term taxonomy has been saved.
                 * 
                 * @see wp_insert_term()
                 */
                'alm_term_taxonomy_saved' => [
                    'title'               => 'New %s relationship created',
                    'action'              => 'term_taxonomy_relationship_created',
                    'event_id'            => 5721,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Created a new %s %s relationship',

                        '_space_start'          => '',
                        'term_id'               => ['term_id'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        'taxonomy'              => ['taxonomy'],
                        'parent_term_ID'        => ['parent_term_ID'],
                        'parent_term_name'      => ['parent_term_name'],
                        'term_description'      => ['term_description'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 4,
                    ],
                ],

                /**
                 *  Fires after a term taxonomy has been updated.
                 * 
                 * @see wp_update_term()
                 */
                'alm_term_taxonomy_updated' => [
                    'title'               => '%s relationship updated',
                    'action'              => 'term_taxonomy_relationship_updated',
                    'event_id'            => 5722,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Changed the %s %s relationship data',

                        '_space_start'          => '',
                        'term_id'               => ['term_id'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        'taxonomy'              => ['taxonomy'],
                        'parent_term_ID'        => ['parent_term_ID'],
                        'parent_term_name'      => ['parent_term_name'],
                        'term_description'      => ['term_description'],
                        '_space_line'           => '',
                        'update_info'           => ['update_info'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 4,
                    ],
                ],

                /**
                 * Fires immediately after a term taxonomy ID is deleted.
                 * 
                 * @see wp_delete_term()
                 */
                'deleted_term_taxonomy' => [
                    'title'               => '%s relationship deleted',
                    'action'              => 'term_taxonomy_relationship_deleted',
                    'event_id'            => 5723,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Deleted the %s %s relationship data',

                        '_space_start'          => '',
                        'term_id'               => ['term_id'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        'taxonomy'              => ['taxonomy'],
                        'parent_term_ID'        => ['parent_term_ID'],
                        'parent_term_name'      => ['parent_term_name'],
                        'term_description'      => ['term_description'],
                        '_space_end'            => '',
                    ]
                ],

                /**
                 * Fires immediately after a term to delete's children are reassigned a parent.
                 * 
                 * @see wp_delete_term()
                 */
                'edited_term_taxonomies' => [
                    'title'               => 'New parent assigned to %s',
                    'action'              => 'term_taxonomy_parent_modified',
                    'event_id'            => 5724,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        '_main'                 => 'Assigned a new parent to the %s: %s',

                        '_space_start'          => '',
                        'term_id'               => ['term_id'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        'taxonomy'              => ['taxonomy'],
                        '_space_line'           => '',
                        'previous_parent_info'  => ['previous_parent_info'],
                        'new_parent_info'       => ['new_parent_info'],
                        '_space_end'            => '',
                    ]
                ],

                /**
                 * Fires immediately after an object-term relationship is added.
                 * 
                 * @see wp_set_object_terms()
                 */
                'added_term_relationship' => [
                    'title'               => '%s relationship added', // [object type]
                    'action'              => 'object_term_relationship_added',
                    'event_id'            => 5725,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        // Format: Added the (object_type) (object title) relationship to  
                        // the (term_name) (taxonomy)
                        '_main'                 => 'Added the %s (%s) relationship to the %s %s',

                        '_space_start'          => '',
                        
                        /**
                         * The 'term_obj_ID' will be transformed to the given type:
                         *  - post object becomes [Post ID], page object becomes [Page ID], etc.
                         */
                        'term_obj_ID'           => ['term_obj_ID'],

                        'term_obj_type'         => ['term_obj_type'],
                        'term_obj_title'        => ['term_obj_title'],
                        'term_obj_url'          => ['term_obj_url'],

                        '_space_line'           => '',

                        'taxonomy'              => ['taxonomy'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires after an object's terms have been set.
                 * 
                 * @see wp_set_object_terms()
                 */
                'set_object_terms' => [
                    'title'               => '%s relationship set', // [term name]
                    'action'              => 'object_term_relationship_set',
                    'event_id'            => 5726,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        // Format: Added the (object_type) (object title) relationship to  
                        // the (term_name) (taxonomy)
                        '_main'                 => 'Set the %s (%s) relationship by linking it to the %s %s',

                        '_space_start'          => '',

                        /**
                         * The 'term_obj_ID' will be transformed to the given type:
                         *  - post object becomes [Post ID], page object becomes [Page ID], etc.
                         */
                        'term_obj_ID'           => ['term_obj_ID'],

                        'term_obj_type'         => ['term_obj_type'],
                        'term_obj_title'        => ['term_obj_title'],
                        'term_obj_url'          => ['term_obj_url'],

                        '_space_line'           => '',

                        'taxonomy'              => ['taxonomy'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 6,
                    ],
                ],

                /**
                 * Fires immediately after an object-term relationship is deleted.
                 * 
                 * @see wp_remove_object_terms()
                 */
                'deleted_term_relationships' => [
                    'title'               => '%s relationship removed', // [term name]
                    'action'              => 'object_term_relationship_removed',
                    'event_id'            => 5727,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        // Format: Added the (object_type) (object title) relationship to  
                        // the (term_name) (taxonomy)
                        '_main'                 => 'Removed the %s (%s) relationship that was linked to the %s %s',

                        '_space_start'          => '',

                        /**
                         * The 'term_obj_ID' will be transformed to the given type:
                         *  - post object becomes [Post ID], page object becomes [Page ID], etc.
                         */
                        'term_obj_ID'           => ['term_obj_ID'],

                        'term_obj_type'         => ['term_obj_type'],
                        'term_obj_title'        => ['term_obj_title'],
                        'term_obj_url'          => ['term_obj_url'],

                        '_space_line'           => '',

                        'taxonomy'              => ['taxonomy'],
                        'term_name'             => ['term_name'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires immediately after a term-taxonomy relationship is updated.
                 * 
                 * But this hook will only be used to log the term taxonomy count update.
                 * 
                 * @see wp_update_term()
                 * @see _update_post_term_count()
                 * @see _update_generic_term_count()
                 */
                'edited_term_taxonomy' => [
                    'title'               => '%s count updated',
                    'action'              => 'term_count_updated',
                    'event_id'            => 5728,
                    'severity'            => 'info',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',

                    'message'             => [
                        // Changed the [Term Name] [Taxonomy] count
                        '_main'                 => 'Changed the %s %s count',

                        '_space_start'          => '',
                        'term_id'               => ['object_id'],
                        'term_name'             => ['term_name'],
                        'term_slug'             => ['term_slug'],
                        'term_taxonomy_ID'      => ['term_taxonomy_ID'],
                        'taxonomy'              => ['taxonomy'],
                        '_space_line'           => '',
                        'previous_count'        => ['previous_count'],
                        'new_count'             => ['new_count'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],
            ]
        ];
    }

    /**
     * Register the term events
     */
    protected function registerTermTaxonomyEvents()
    {
    }
}
