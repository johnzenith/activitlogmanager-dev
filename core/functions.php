<?php
// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package    Activity Log Manager
 * @subpackage Core Functions
 * @since 	   1.0.0
 */

/**
 * A small error inspector
 */
function alm__error_inspector(array $args, $error_file_location = '/errors/error.html') {
    ob_start();
    ?>
<pre>
<?php var_dump( $args ); ?>
</pre>
    <?php
    $content = ob_end_clean();

    file_put_contents( $error_file_location, $content );
}

/**
 * Register a new event group or add an event to an existing event group.
 * 
 * This should be called within the {@see alm/event/group/register action hook}, 
 * but it is not a requirement, except if you want to access the default event 
 * groups.
 * 
 * @since 1.0.0
 * 
 * @see \ALM\Controllers\Audit\Traits::__setupEventList()
 * @see \ALM\Controllers\Audit\Traits::registerEventGroups()
 */
function alm_register_event_group( array $args = [] )
{
    if (empty($args)) return;

    static $auditor = null;
    
    if ( is_null( $auditor ) ) 
        $auditor = apply_filters( 'alm/controller/get/auditor', '' );

    $auditor->addEventGroups( $args );
}