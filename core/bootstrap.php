<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package    Activity Log Manager
 * @subpackage Bootstrap Loader
 * @since 	   1.0.0
 */

require_once plugin_dir_path( ALM_PLUGIN_FILE ) . 'controllers/Base/class-bootloader.php';
\ALM\Controllers\Base\BootLoader::Run();