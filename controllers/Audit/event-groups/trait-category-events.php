<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Category Events
 * @since   1.0.0
 */

trait CategoryEvents
{
    /**
     * This method is called automatically by the 
     * {@see ALM\Controllers\Audit\Traits\EventList::registerAllEventGroups()} method
     */
    protected function initCategoryEvents()
    {
        // $this->setupCategoryEvents();
    }

    /**
     * Register the category events
     */
    protected function setupCategoryEvents()
    {
        $category_taxonomy = get_taxonomy('category');

        $this->event_list['category'] = [
            'title'           => 'Category Events',
            'group'           => 'category',
            'object'          => 'category',
            'description'     => alm__('Responsible for logging all category related activities.'),
            'object_id_label' => 'Category ID',

            'events' => [
                /**
                 * Fires when media is attached or detached from a post.
                 * 
                 * @see wp_media_attach_action()
                 */
                'alm_insert_category' => [
                    'title'               => 'Category created',
                    'action'              => 'category_created',
                    'event_id'            => 5701,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['edit_post'],

                    'message' => [
                        '_main'                     => 'Created a new category (%s)',

                        '_space_start'              => '',
                        'category_id'               => ['object_id'],
                        'category_name'             => ['category_name'],
                        'category_slug'             => ['category_slug'],
                        'category_parent'           => ['category_parent'],
                        'category_alias_of'         => ['category_alias_of'],
                        'taxonomy'                  => ['taxonomy'],
                        'description'               => ['description'],
                        '_space_end'                => '',
                    ],

                    'event_handler' => [
                        'num_args' => 2,
                    ],
                ],
            ]
        ];
    }

    /**
     * Register the category events
     */
    protected function registerCategoryEvents()
    {
        /**
         * Fires after a new term is created for a specific taxonomy.
         */
        add_action('create_category', function($term_id, $tt_id) {
            do_action('alm_insert_category', $term_id, $tt_id);
        }, 10, 2);
    }
}
