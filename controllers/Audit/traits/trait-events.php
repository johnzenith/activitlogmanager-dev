<?php
namespace ALM\Controllers\Audit\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Auditable Events
 * @since   1.0.0
 */

use \ALM\Controllers\Audit\Traits        as ALM_Events;
use \ALM\Controllers\Audit\Events\Groups as ALM_EventGroups;

/**
 * Non-Multisite Events
 * 
 * SS => Single Site
 */
trait AuditableEvents_SS
{
    use
        ALM_EventGroups\UserEvents,
        ALM_EventGroups\PluginEvents,
        ALM_EventGroups\ThemeEvents,
        ALM_EventGroups\MediaEvents,
        ALM_EventGroups\WidgetEvents,
        ALM_EventGroups\NavMenuEvents,
        ALM_EventGroups\TermEvents,
        ALM_EventGroups\TermTaxonomyEvents,
        ALM_EventGroups\WP_CoreSettingsEvents,
        ALM_Events\EventConditionalParser,
        ALM_Events\EventGlobalHelpers,
        ALM_Events\EventList;
}

/**
 * Multisite Events
 *
 * MS => Multi Site
 */
if ( is_multisite() ) {

    trait AuditableEvents_MS
    {
        use
            ALM_EventGroups\Network\SuperAdminEvents,
            ALM_EventGroups\Network\UserEvents;
    }

} else {
    trait AuditableEvents_MS { }
}

/**
 * Merge the registered events
 */
trait AuditableEvents
{
    use
        ALM_Events\AuditableEvents_MS,
        ALM_Events\AuditableEvents_SS;
}