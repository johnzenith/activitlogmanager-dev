<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Browser Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */
trait BrowserFactory
{
    /**
     * Get the client device data
     * 
     * @see PluginFactory::sanitizeStr()
     * 
     * @param  string $device_context  Specifies the formatting context to use for the device 
     *                                 data. 'db' for database sanitize | 'display' for 
     *                                 string display. Default: 'db'
     * 
     * @return object                  A StdClass containing list of client device data
     */
    public function getClientDeviceData( $device_context = 'db' )
    {
        // Require the browser detection class if it doesn't exists
        if ( ! class_exists( '\Wolfcast\BrowserDetection' ) ) {
            require_once ALM_VENDOR_DIR . 'class-browser-detection.php';
        }
    
        $BD                     = new \Wolfcast\BrowserDetection();
    
        $browser                = $BD->getName();
        $platform               = $BD->getPlatform();
        $is_robot               = $BD->isRobot();
        $is_mobile              = $BD->isMobile();
        $user_agent             = $BD->getUserAgent();
        $browser_version        = $BD->getVersion();
        $platform_version       = $BD->getPlatformVersion(true);
        $platform_is_64_bit     = $BD->is64bitPlatform();
        $platform_version_name  = $BD->getPlatformVersion();
    
        $device_data = [
            'browser'               => $browser,
            'platform'              => $platform,
            'is_robot'              => (int) $is_robot,
            'is_mobile'             => (int) $is_mobile,
            'user_agent'            => $user_agent,
            'browser_version'       => $browser_version,
            'platform_version'      => $platform_version,
            'platform_is_64_bit'    => (int) $platform_is_64_bit,
            'platform_version_name' => $platform_version_name,
        ];
        
        $device_context = ( 'display' != $device_context ) ? 'db' : 'display';
        foreach ( $device_data as $key => $d ) {
            $device_data[ $key ] = $this->sanitizeStr( $d, $device_context );
        }
    
        return $device_data;
    }  
}