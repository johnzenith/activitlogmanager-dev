<?php
namespace ALM\Controllers\Base;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Plugin Factory
 * 
 * Responsible for providing access gateway to every other controllers,
 * except for the plugin [Installer Controller] that runs in an isolated mode.
 * 
 * @since 	1.0.0
 */

use \ALM\Controllers\Base\Traits as ALM_Base_Traits;

abstract class PluginFactory
{
    /**
     * Using the Plugin Factory Controller Traits
     */
     use 
        ALM_Base_Traits\ConstantFactory,
        ALM_Base_Traits\SettingsFactory,
        ALM_Base_Traits\ControllersList,
        ALM_Base_Traits\StringFactory,
        ALM_Base_Traits\ArrayFactory,
        ALM_Base_Traits\DataParserFactory,
        ALM_Base_Traits\DateFactory,
        ALM_Base_Traits\FileUtility,
        ALM_Base_Traits\PageFactory,
        ALM_Base_Traits\BlogFactory,
        ALM_Base_Traits\TableFactory,
        ALM_Base_Traits\IP_Factory,
        \ALM\Models\Traits\DatabaseMetaData,
        \ALM\Models\Traits\DatabaseQueryMetaData;
    
    /**
     * Specify the installed plugin version
     * @var string
     * @since 1.0.0
     */
    protected $version = ALM_VERSION;

    /**
     * Specify the plugin database version
     * @var string
     * @since 1.0.0
     */
    protected $db_version = ALM_DB_VERSION;

    /**
     * All supported plugin packages
     * @var object
     * @since 1.0.0
     */
    protected $packages = null;

    /**
     * Specify the installed plugin package
     * @var string
     * @since 1.0.0
     */
    protected $package = ALM_PACKAGE;

    /**
     * Specifies whether or not the child class setup process has been fired
     * @var bool
     * @since 1.0.0
     */
    public $is_setup_fired = false;

    /**
     * Specifies the controller cache data
     * @var stdClass
     * @since 1.0.0
     */
    protected $controller_cache = null;

    /**
     * The plugin factory constructor. it doesn't do anything special.
     */
    public function __construct() { }

    /**
     * Setup the plugin factory data
     */
    public function init()
    {
        // Setup database metadata
        $this->__setupDatabaseMetadata( $this->controller_cache );

        // Initialize settings
        $this->maybeInitSettings();
        
        $this->settings     = $this->loadOptions( $this->controller_cache );
        $this->running_mode = $this->getRunningMode();

        // Setup the blog factory data
        $this->__setupBlogData( $this->controller_cache );
    }

    /**
     * Should be implemented by Child Classes
     */
    abstract public function __runSetup();

    /**
     * Run when the controller setup is completed.
     * This method is optional, so it won't be declared as abstract.
     */
    public function __runAfterSetup() { }

    /**
     * Get a specific property value.
     * 
     * @since 1.0.0
     */
    public function __get( $name )
    {
        if ( property_exists( $this, $name ) )
            return $this->$name;

        if ( WP_DEBUG ) 
            throw new \Exception( sprintf( alm__('Undefined property name: %s'), $name ) );
        
        return null;
    }

    /**
     * Update a specific property value
     * 
     *  @since 1.0.0
     */
    public function __set( $name, $value )
    {
        if ( ! property_exists( $this, $name ) ) return;
         
        // Throw an exception debug mode
        $this->__get( $name );
        
        $this->$name = $value;
    }

    /**
     * Check whether specific property is set
     * 
     * @since 1.0.0
     */
    public function __isset( $name )
    {
        if ( property_exists( $this, $name ) ) 
            return true;

        return false;
    }

    /**
     * Get the plugin packages
     */
    protected function getPackages()
    {
        return __alm_get_package_list();
    }

    /**
     * Get the WPDB Resource Handle
     * @see $wpdb
     */
    final protected function getDB()
    {
        return $this->wpdb;
    }

    /**
     * Get the WP Hasher Object
     * @see wp-includes/class-phpass.php
     * @see wp-includes/pluggable.php/wp_hash_password()
     */
    public function getHasher( $length = 8, $is_portable = true )
    {
        global $wp_hasher;

        if ( is_null( $wp_hasher ) || empty( $wp_hasher ) ) {
            $this->include( ABSPATH . WPINC . '/class-phpass.php' );
            $wp_hasher = new \PasswordHash( $length, $is_portable );
        }
        return $wp_hasher;
    }
    
    /**
     * Check whether a pluggable function exists
     * 
     * @param string $pluggable  Specify the pluggable function to check for.
     * @return bool              True if the pluggable function exists. Otherwise false.
     */
    public function isPluggable( $pluggable )
    {
        return function_exists( "alm_{$pluggable}" );
    }

    /**
     * A wrapper for the {@see get_plugin_data() function}
     */
    public function getPluginData($plugin_file, $markup = false, $translate = false)
    {
        $plugin_data = [];
        if (file_exists($plugin_file))
            $plugin_data = get_plugin_data($plugin_file, $markup, $translate);

        return $plugin_data;
    }
}
