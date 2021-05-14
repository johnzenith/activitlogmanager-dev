<?php
namespace ALM\Controllers\Audit\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Event Handlers
 * @since   1.0.0
 */

use \ALM\Controllers\Audit\Traits          as ALM_AuditTraits;
use \ALM\Controllers\Audit\Events\Handlers as ALM_EventHandlers;

/**
 * Non-Multisite Event Handlers
 * 
 * SS => Single Site
 */
trait EventHandlers_SS
{
    use ALM_EventHandlers\UserEvents,
        ALM_EventHandlers\PluginEvents,
        ALM_EventHandlers\ThemeEvents,
        ALM_EventHandlers\MediaEvents,
        ALM_EventHandlers\WidgetEvents,
        ALM_EventHandlers\NavMenuEvents,
        ALM_EventHandlers\TermEvents,
        ALM_EventHandlers\TermTaxonomyEvents,
        ALM_EventHandlers\WP_CoreSettingsEvents;
}

/**
 * Multisite Event Handlers
 */
if ( is_multisite() ) {

    trait EventHandlers_MS
    {
        use ALM_EventHandlers\Network\SuperAdminEvents,
            ALM_EventHandlers\Network\UserEvents;
    }

} else {
    trait EventHandlers_MS { }
}

/**
 * Merge the registered event handlers
 */
trait EventHandlers {
    use
        ALM_AuditTraits\EventHandlers_MS,
        ALM_AuditTraits\EventHandlers_SS;
}