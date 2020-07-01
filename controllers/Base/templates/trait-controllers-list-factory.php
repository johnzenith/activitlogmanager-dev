<?php
namespace ALM\Controllers\Base\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Controllers List
 * @since 1.0.0
 */

trait ControllersList
{
    /**
     * Controllers
     */
    protected $Settings;
    protected $FileManager;

    protected $User;
    protected $Admin;
    protected $Metrics;

    protected $Auditor;
    protected $AuditObserver;
    
    protected $Location;
    
    protected $FileMonitor;
    protected $Notification;
}