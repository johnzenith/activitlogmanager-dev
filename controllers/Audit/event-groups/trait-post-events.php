<?php

namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Posts Events
 * @since   1.0.0
 */

trait PostEvents
{
    /**
     * Init the Post Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     */
    protected function initPostEvents()
    {
        $this->setupPostEvents();
    }

    protected function setupPostEvents() {
        $this->event_list['post'] = [
            'title'           => 'Post Events',
            'group'           => 'post',
            'object'          => 'post',
            'description'     => alm__('Responsible for logging all post types related activities.'),
            'object_id_label' => 'Post ID',

            'events' => [
                /**
                 * Fires once a post has been added to the sticky list
                 * 
                 * @see sticky_post()
                 */
                'post_stuck' => [
                    // Added the [post title] [post type] to the sticky list
                    'title'               => 'Added the %s %s to the sticky list',

                    'action'              => 'post_modified',
                    'event_id'            => 5801,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    // 'logged_in_user_caps' => ['edit_post'], // Only possible when post is active

                    'wp_options'          => ['sticky_posts'],
                    'wp_site_options'     => ['sticky_posts'],

                    'message' => [
                        // Added the [post title] [post_type] to the sticky list
                        '_main'                     => 'Added the %s %s to the sticky list',

                        '_space_start'              => '',
                        'post_id'                   => ['object_id'],
                        'post_slug'                 => ['post_slug'],
                        'post_status'               => ['post_status'],
                        'post_title'                => ['post_title'],
                        'view_post'                 => ['view_post'],
                        'view_post_in_editor'       => ['view_post_in_editor'],
                        '_space_end'                => '',
                    ],
                ],

                /**
                 * Fires once a post has been removed from the sticky list
                 * 
                 * @see unstick_post()
                 */
                'post_unstuck' => [
                    // Added the [post title] [post type] to the sticky list
                    'title'               => 'Removed the %s %s from the sticky list',

                    'action'              => 'post_modified',
                    'event_id'            => 5802,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    // 'logged_in_user_caps' => ['edit_post'], // Only possible when post is active

                    'wp_options'          => ['sticky_posts'],
                    'wp_site_options'     => ['sticky_posts'],

                    'message' => [
                        // Added the [post title] [post_type] to the sticky list
                        '_main'                     => 'Removed the %s %s from the sticky list',

                        '_space_start'              => '',
                        'post_id'                   => ['object_id'],
                        'post_slug'                 => ['post_slug'],
                        'post_status'               => ['post_status'],
                        'post_title'                => ['post_title'],
                        'view_post'                 => ['view_post'],
                        'view_post_in_editor'       => ['view_post_in_editor'],
                        '_space_end'                => '',
                    ],
                ]
            ],
        ];
    }
}
