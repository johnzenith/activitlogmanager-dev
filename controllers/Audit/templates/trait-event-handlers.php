<?php
namespace ALM\Controllers\Audit\Templates;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Event Handlers
 * @since   1.0.0
 */

use \ALM\Controllers\Audit\Events\Handlers as ALM_EventHandlers;

trait EventHandlers
{
    use ALM_EventHandlers\SuperAdminEvents,
        ALM_EventHandlers\UserEvents;
}