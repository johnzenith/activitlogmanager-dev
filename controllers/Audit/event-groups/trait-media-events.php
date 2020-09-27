<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Media Events
 * @since   1.0.0
 */

trait MediaEvents
{
    /**
     * Init the Media Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     */
    protected function initMediaEvents()
    {
        $this->setupMediaEvents();
    }

    protected function setupMediaEvents()
    {
        $this->event_list['media'] = [
            'title'           => 'Media Events',
            'group'           => 'media',
            'object'          => 'attachment',
            'description'     => alm__('Responsible for logging all media related activities.'),
            'object_id_label' => 'Attachment ID',

            'events' => [
                /**
                 * Fires when media is attached or detached from a post.
                 * 
                 * @see wp_media_attach_action()
                 */
                'wp_media_attach_action' => [
                    'title'               => 'Media attached to post',
                    'action'              => 'post_modified',
                    'event_id'            => 5601,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    // 'logged_in_user_caps' => ['edit_post'], // Only possible when post is active

                    'message' => [
                        '_main'                     => 'Attached a media to a post',

                        '_space_start'              => '',
                        'attachment_id'             => ['object_id'],
                        'attachment_filename'       => ['attachment_filename'],
                        'post_id'                   => ['post_id'],
                        'post_title'                => ['post_title'],
                        'view_attachment'           => ['view_attachment'],
                        'view_post'                 => ['view_post'],
                        'view_post_in_editor'       => ['view_post_in_editor'],
                        '_space_end'                => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires when media is detached from a post.
                 * 
                 * @see wp_media_attach_action()
                 */
                'alm_wp_media_detached' => [
                    'title'               => 'Media detached from post',
                    'action'              => 'post_modified',
                    'event_id'            => 5602,
                    'severity'            => 'notice',

                    'screen'              => ['admin', 'network'],
                    'user_state'          => 'logged_in',
                    // 'logged_in_user_caps' => ['edit_post'], // Only possible when post is active

                    'message' => [
                        '_main'                     => 'Detached a media from a post',

                        '_space_start'              => '',
                        'attachment_id'             => ['object_id'],
                        'attachment_filename'       => ['attachment_filename'],
                        'post_id'                   => ['post_id'],
                        'post_title'                => ['post_title'],
                        'view_attachment'           => ['view_attachment'],
                        'view_attachment_in_editor' => ['view_attachment_in_editor'],
                        'view_post'                 => ['view_post'],
                        'view_post_in_editor'       => ['view_post_in_editor'],
                        '_space_end'                => '',
                    ],

                    'event_handler' => [
                        'hook' => 'callback',
                    ],
                ],
            ]
        ];
    }
}