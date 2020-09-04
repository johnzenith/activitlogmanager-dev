<?php
namespace ALM\Controllers\Audit\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Multisite Events
 * @since   1.0.0
 */

use \ALM\Controllers\Audit\Templates     as ALM_Events;
use \ALM\Controllers\Audit\Events\Groups as ALM_EventGroups;

trait AuditableEvents
{
    use
        ALM_EventGroups\SuperAdminEvents,
        ALM_EventGroups\UserEvents,
        ALM_EventGroups\PluginEvents,
        ALM_EventGroups\WP_CoreSettingsEvents,
        ALM_Events\EventConditionalParser,
        ALM_Events\EventList;
}
