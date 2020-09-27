<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Media Event Handlers
 * @since   1.0.0
 */

trait MediaEvents
{
    /**
     * Specifies the event group name
     * @var string
     */
    protected $media_event_group_name = 'media';

    /**
     * Fires when media is attached to a post.
     */
    public function wp_media_attach_action_event($action, $attachment_id, $parent_id)
    {
        $post_id                   = $parent_id;
        $object_id                 = $attachment_id;

        $post_title                = $this->sanitizeOption(get_the_title($post_id));
        $view_post                 = esc_url_raw(get_the_permalink($post_id));
        $view_post_in_editor       = esc_url_raw(get_edit_post_link($post_id));

        $upload_dir_args           = wp_get_upload_dir();
        $attachment_data           = get_post($attachment_id);

        $upload_dir                = $this->getVar($upload_dir_args, 'path', '') . '/';
        $upload_url                = $this->getVar($upload_dir_args, 'url', '');
        $attachment_filename       = $this->getVar($attachment_data, 'post_title', 'No title');
        
        $view_attachment           = esc_url_raw(get_the_permalink($attachment_id));
        $view_attachment_in_editor = esc_url_raw(get_edit_post_link($attachment_id));

        if (empty($attachment_caption)) {
            $attachment_caption = '_ignore_';
        }

        $this->setupEventMsgData($this->media_event_group_name, compact(
            'post_id',
            'object_id',
            'view_post',
            'post_title',
            'view_attachment',
            'attachment_filename',
            'view_post_in_editor',
            'view_attachment_in_editor'
        ));

        if ('detach' === $action) {
            $this->alm_wp_media_detached_event();
            return;
        }

        $this->LogActiveEvent($this->media_event_group_name, __METHOD__);
    }

    /**
     * Fires when media is detached from a post.
     */
    public function alm_wp_media_detached_event()
    {
        $this->LogActiveEvent($this->media_event_group_name, __METHOD__);
    }
}