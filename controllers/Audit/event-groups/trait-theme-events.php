<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Theme Events
 * @since   1.0.0
 */

trait ThemeEvents
{
    /**
     * Init the Theme Events.
     * This method is called automatically.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     */
    protected function initThemeEvents()
    {
        $this->setupThemeEvents();
    }

    protected function setupThemeEvents() {
        $this->event_list['plugins'] = [
            'title'           => 'Theme Events',
            'group'           => 'theme', // object
            'object_id_label' => 'Option ID',

            'description'     => alm__('Responsible for logging all themes related activities such as theme activation, deactivation, installation, uninstallation, upgrade, upload and the front-end theme editor'),

            'events' => [
            ]
        ];
    }
}