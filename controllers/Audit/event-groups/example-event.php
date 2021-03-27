<?php

namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

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

    protected function setupPostEvents()
    {
        $this->event_list['post'] = [
            'title'           => 'Post Events',
            'group'           => 'post',
            'object'          => 'post',
            'description'     => alm__('Responsible for logging all post types related activities.'),
            'object_id_label' => 'Post ID',

            'events' => [],
        ];
    }
}
