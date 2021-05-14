<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Event Factory Template for the Plugin Factory Controller
 * @see   \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait EventFactory
{
    /**
     * List of event severities
     * @return array
     */
    public function getEventSeverities()
    {
        return [
            'alert'         => ['value' => 1, 'title' => 'Alert'],
            'critical'      => ['value' => 2, 'title' => 'Critical'],
            'error'         => ['value' => 3, 'title' => 'Error'],
            'warning'       => ['value' => 4, 'title' => 'Warning'],
            'notice'        => ['value' => 5, 'title' => 'Notice'],
            'info'          => ['value' => 6, 'title' => 'Informational'],
        ];
    }
}