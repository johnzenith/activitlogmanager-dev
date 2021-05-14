<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

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

    protected $DB;

    protected $User;
    protected $Admin;
    protected $Metrics;

    protected $Auditor;
    protected $AuditObserver;
    
    protected $Location;
    
    protected $FileMonitor;
    protected $Notification;
}