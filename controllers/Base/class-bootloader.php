<?php
namespace ALM\Controllers\Base;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Class: BootLoader
 * Load the plugin files correctly
 * 
 * @package Boot Loader
 * @since 1.0.0
 */

class BootLoader
{
    /**
     * Halt the bootloader process if a file could not be found.
     * @var bool
     * @since 1.0.0
     */
    private $halt_loader = false;
    
    /**
     * File loading sequence
     * @var array
     * @since 1.0.0
     */
    protected $file_sequences;

    /**
     * Specify the BootLoader state, whether it has been started or not.
     * @var object
     * @since 1.0.0
     */
    protected static $state = null;

    /**
     * Specifies the controller engine
     * @var object
     * @since 1.0.0
     */
    protected $controllers_engine = null;

    /**
     * Set the plugin mode. This can either be:
     *  'activation', 'deactivation', 'running', 'uninstalling', 'inactive'
     * 
     * - Inactive Mode:     Specifies that the plugin is not running due to error(s)
     * - Running Mode:      Specifies that the plugin is running successfully.
     * - Activation Mode:   Specifies that the plugin is currently being activated.
     * - Deactivation Mode: Specifies that the plugin is currently being deactivated.
     * - Uninstalling Mode: Specifies that the plugin is currently being uninstalled.
     * 
     * Basically, the plugin mode is used to autoload files
     */
    protected $__mode = '';

    /**
     * We don't want the BootLoader object to be created directly
     */
    private function __construct() {}

    /**
     * Run the BootLoader
     */
    public static function Run()
    {
        if ( null === self::$state ) {
            self::$state = new self;
            self::$state->init();
        }
    }

    /**
     * Monitor and handles activation, deactivation, uninstallation request
     */
    private function igniteInstaller()
    {
        require_once ALM_CONTROLLERS_DIR . 'base/templates/trait-file-utility-factory.php';
        require_once ALM_CONTROLLERS_DIR . 'base/templates/trait-settings-factory.php';
        require_once ALM_CONTROLLERS_DIR . 'base/templates/trait-blog-factory.php';
        require_once ALM_CONTROLLERS_DIR . 'Installer/trait-db-table-schema.php';
        require_once ALM_CONTROLLERS_DIR . 'installer/class-installer.php';
        
        $installer = new \ALM\Controllers\Installer\Installer();
        $installer->dispatchHooks();
    }

    /**
     * Initialize the BootLoader properties
     */
    private function init()
    {
        $this->loaded_sequences = [];
        $this->requireConfigFiles();

        /**
         * Run the plugin installer
         */
        $this->igniteInstaller();

        add_action( 'plugin_loaded', '\ALM\Controllers\Base\BootLoader::loadRunningProcessEarly', 10, 1 );
        add_action( 'plugins_loaded', '\ALM\Controllers\Base\BootLoader::prepareRunningModeProcess' );
    }

    /**
     * Load plugin file early.
     * This is used to resolve issues where some hooks are not reachable when using the 
     * 'plugins_loaded' action hook to initialize the plugin.
     * 
     * So to fix this, the 'plugin_loaded' action hook will be used instead to load the plugin 
     * files and then initialize all controllers and in the 'plugins_loaded' action hook.
     */
    public static function loadRunningProcessEarly( $plugin_full_path )
    {
        // Get the plugin basename
        $plugin_basename = plugin_basename( $plugin_full_path );

        /**
         * Only load the plugin files when the Activity Log Manager plugin base file 
         * 'activitylogmanager.php' is loaded.
         */
        if ( ALM_PLUGIN_BASENAME === $plugin_basename )
        {
            self::$state->createBootSequence();
            self::$state->Load();

            // Load the text domain
            alm_load_plugin_textdomain();
        }
    }

    /**
     * Prepare the plugin running mode process
     */
    public static function prepareRunningModeProcess()
    {
        self::$state->__AutoRunControllers();
        self::$state->startPluginMode();
    }

    /**
     * Run the controllers engine
     */
    private function __AutoRunControllers()
    {
        $this->controllers_engine = new \ALM\Controllers\Base\ControllersEngine;
    }

    /**
     * Start the plugin mode process
     */
    protected function startPluginMode()
    {
        /**
         * Run the hooks initializer
         */
        $this->__setupHooks();
    }

    /**
     * Setup the hooks initializer
     */
    protected function __setupHooks()
    {
        /**
         * Trigger the 'alm/init' action during the plugin initialization
         * 
         * @param object $BootLoader  Specifies the bootloader object class which provide 
         *                            access to the entire plugin.
         */
        do_action( 'alm/init', $this );
    }

    /**
     * Get the ControllersEngine Object
     * @see BootLoader::__AutoRunControllers()
     */
    public static function getControllersEngine()
    {
        return self::$state->controllers_engine;
    }

    /**
     * Get the plugin mode
     */
    private function __getMode()
    {
        if ( $this->__isActivation() )       return 'activation';
        if ( $this->__isDeactivation() )     return 'deactivation';
        if ( $this->__isUninstallation() )   return 'uninstalling';
        if ( $this->__isBootLoaderHalted() ) return 'inactive';

        return 'running';
    }

    /**
     * Check whether we are doing installation
     * @return (bool) True if the Activity Log Manager BootLoader has been halted.
     *                Although this should never happen, but we are good citizen,
     *                So let's avoid the risk.
     *                Otherwise false.
     */
    public function __isBootLoaderHalted()
    {
        return ( defined( 'ALM_HALT_BOOTLOADER' ) && ALM_HALT_BOOTLOADER );
    }

    /**
     * Check whether we are doing installation
     * @return (bool) True if the Activity Log Manager plugin is being activated. Otherwise false.
     */
    public function __isActivation()
    {
        return ( defined( 'ALM_ACTIVATING' ) && ALM_ACTIVATING );
    }

    /**
     * Check whether we are doing installation
     * @return (bool) True if the Activity Log Manager plugin is being deactivated. Otherwise false.
     */
    public function __isDeactivation()
    {
        return ( defined( 'ALM_DEACTIVATING' ) && ALM_DEACTIVATING );
    }
    
    /**
     * Check whether we are doing installation
     * @return (bool) True if the Activity Log Manager plugin is being uninstalled. Otherwise false.
     */
    public function __isUninstallation()
    {
        return ( defined( 'ALM_UNINSTALLING' ) && ALM_UNINSTALLING );
    }

    /**
     * Check whether a new blog has been inserted (activated)
     * @return bool True if a new blog is inserted. Otherwise false.
     */
    public function __isNewBlogInserted()
    {
        return ( defined( 'ALM_NEW_BLOG_INSERTED' ) && ALM_NEW_BLOG_INSERTED );
    }

    /**
     * We must load the plugin constants, php error handler and several meta files early
     */
    protected function requireConfigFiles()
    {
        require_once $this->getConfigDir() . 'constants.php';
        require_once $this->getConfigDir() . 'package-list.php';
        require_once $this->getConfigDir() . 'translation-helper.php';
        require_once $this->getConfigDir() . 'meta-info.php';
        require_once ALM_MODELS_DIR        . 'templates/trait-db-metadata.php';
        require_once ALM_MODELS_DIR        . 'templates/trait-db-query-metadata.php';
        require_once ALM_CONTROLLERS_DIR   . 'base/class-php-error-handler.php';
    }

    /**
     * Get the config directory
     */
    private function getConfigDir()
    {
        return plugin_dir_path( ALM_PLUGIN_FILE ) . 'core/config/';
    }

    /**
     * The file arguments used for creating the full file path
     */
    protected function defaultFileArgs(): array
    {
        return [
            'dir'       => '',
            'file'      => '',
            'multisite' => false,
        ];
    }

    /**
     * Parse the file arguments with supplied defaults
     * @see BootLoader::defaultFileArgs()
     * @param array  $file_args List of file arguments to used in creating the full file path
     * @return array The merged file arguments
     */
    protected function setupFileArgs( array $file_args )
    {
        return array_merge( $this->defaultFileArgs(), $file_args );
    }

    /**
     * Specifies how the plugin files should be loaded
     */
    protected function createBootSequence()
    {
        /**
         * File sequence signatures:
         * - Array keys are the signature ID
         * - Array values are the methods to load necessary files
         */
        $file_sequences = [
            'config'          => 'Config',
            'core'            => 'Core',
            'base_controller' => 'BaseController',
        ];
        
        // Add the plugin packages to the file sequences
        $this->file_sequences = array_merge( $file_sequences, __alm_get_package_list() );
    }

    /**
     * Load the registered file sequences
     */
    protected function Load()
    {
        foreach ( $this->file_sequences as $file_sequence )
        {
            $this->_require( $file_sequence );
        }
    }

    /**
     * Add a specific file sequence into the loading process
     * @param  string $file_sequence Specify the file sequence to check for
     */
    private function _require( $file_sequence )
    {
        if ( $this->fileSequenceExists( $file_sequence ) ) {
            $this->_loadFiles( $this->$file_sequence() );
        }
    }

    /**
     * Load all the files contained in the registered file sequence
     * @see BootLoader::defaultFileArgs()
     * 
     * @param array  $file_sequence_list  Specify list of file arguments to use in creating 
     *                                    and loading files in the sequence.
     */
    private function _loadFiles( array $file_sequence_list )
    {
        // Bail out the bootloader if any file could not be loaded
        if ( ! WP_DEBUG && $this->halt_loader ) return;

        // Parse the file arguments correctly when an array of file list is used
        $file_sequence_list = $this->parseFileArgs( $file_sequence_list );
        
        foreach ( $file_sequence_list as $file_args )
        {
            $file_is_ok = $this->isFileOK( $file_args );
            
            if ( 'ignore' === $file_is_ok ) continue;

            if ( $file_is_ok )
            {
                $file = $this->prepareFileName( $file_args );
                require_once( $file );
            }
            /**
             * But this should never happen, file should not be missing!
             */
            else {
                $this->halt_loader = true;

                if ( ! defined( 'ALM_HALT_BOOTLOADER' ) )
                    define( 'ALM_HALT_BOOTLOADER', true );

                if ( WP_DEBUG ) {
                    throw new \Exception( sprintf(
                        '%s does not exists.', esc_html( $this->prepareFileName( $file_args ) )
                    ) );
                }
                else {
                    /**
                     * Inform the administrator about the error
                     * 
                     * @todo
                     * Maybe send email to admin
                     */
                }
            }
        }
    }

    /**
     * Check whether or not a specific file sequence exists for loading
     * @param  string $file_sequence Specify the file sequence to check for
     * @return bool Returns true if the file sequence exists. Otherwise false.
     */
    protected function fileSequenceExists( $file_sequence )
    {
        return is_string( $file_sequence ) && method_exists( $this, $file_sequence );
    }

    /**
     * Check whether the specified file arguments is valid
     * 
     * @param  array          $file_args Specify the file arguments to used in creating the file
     * 
     * @return bool|string    Returns true if the file path is generated successfully and does exists.
     *                        Returns 'ignore' when the file exists but requires multisite.
     *                        Otherwise false.
     */
    protected function isFileOK( array $file_args )
    {
        $file_args = $this->setupFileArgs( $file_args );

        $dir       = $file_args['dir'];
        $file      = $file_args['file'];
        $multisite = $file_args['multisite'];

        // Bail out if the ignore {__ignore__} flag has been used on the filename
        if ( '__ignore__' === $file ) return 'ignore';

        if ( empty( $file ) 
        || empty( $dir ) 
        || ! is_dir( $dir ) ) return false;

        $dir     = wp_normalize_path( $dir );
        $abspath = wp_normalize_path( ABSPATH );

        $file = sanitize_file_name( $file );
        if ( ! file_exists( $dir . $file ) ) return false;

        // All dir should contain the WordPress ABSPATH
        if ( false === strpos( $dir, $abspath ) ) return false;

        // Check whether to only load the file on multisite
        if ( (bool) $multisite && ! is_multisite() ) return 'ignore';

        return true;
    }

    /**
     * Properly parse the file name by using the provided file arguments
     * @see BootLoader::_loadFiles()
     */
    protected function prepareFileName( array $file_args )
    {
        $file_args = $this->setupFileArgs( $file_args );
        return $file_args['dir'] . $file_args['file'];
    }

    /**
     * Append file arguments to the of the selected file sequence
     * @see BootLoader::defaultFileArgs()
     */
    protected function appendFileArgs( $dir, $file, $multisite = false )
    {
        /**
         * Automatically ignore files based on file name pattern
         * '-ss-': for non-multisite installation
         * '-ms-': for multisite installation
         */
        if ( is_string( $file ) )
        {
            $pattern = is_multisite() ? '-ss-' : '-ms-';

            if ( false !== strpos( $file, $pattern ) )
                $file = '__ignore__';
        }

        return [
            'dir'       => $dir,
            'file'      => $file,
            'multisite' => $multisite,
        ];
    }

    /**
     * Properly recreate the file arguments when using a file list
     * @see BootLoader::_loadFiles()
     */
    protected function parseFileArgs( $file_sequence )
    {
        $copy_file_sequence = [];
        foreach ( $file_sequence as $sequence_index => $file_args )
        {
            $file_args       = $this->setupFileArgs( $file_args );
            
            $dir             = $file_args['dir'];
            $files           = $file_args['file'];
            $multisite       = $file_args['multisite'];
            $using_wildcard  = ( is_string( $files ) && false !== strpos( $files, '*' ) );

            if ( $using_wildcard ) $files = glob( $dir . $files );

            if ( ! is_array( $files ) )
            {
                // Make sure file path is not being traversed
                $pathinfo = pathinfo( $dir . $files );
                $f        = $pathinfo['basename'];
                $dir      = wp_normalize_path( $pathinfo['dirname'] ) . '/';

                $copy_file_sequence[] = $this->appendFileArgs( $dir, $f, $multisite );
            }
            else {
                foreach ( $files as $file )
                {
                   $using_wildcard = ( is_string( $file ) && false !== strpos( $file, '*' ) );

                    if ( $using_wildcard ) $file = glob( $dir . $file );

                    if ( is_array( $file ) )
                    {
                        foreach ( $file as $f )
                        {
                            // Make sure file path is not being traversed
                            $pathinfo = pathinfo( $f );
                            $f        = $pathinfo['basename'];
                            $dir      = wp_normalize_path( $pathinfo['dirname'] ) . '/';

                            $copy_file_sequence[] = $this->appendFileArgs( $dir, $f, $multisite );
                        }
                    }
                    else {
                        // Check whether the {$dir . $file } exists
                        if ( file_exists( $dir . $file ) ) {
                            $pathinfo = pathinfo( $dir . $file );
                        } else {
                            // Make sure file path is not being traversed
                            $pathinfo = pathinfo( $file );   
                        }

                        $f   = $pathinfo['basename'];
                        $dir = wp_normalize_path( $pathinfo['dirname'] ) . '/';

                        $copy_file_sequence[] = $this->appendFileArgs( $dir, $file, $multisite );
                    }
                }
            }
        }
        return $copy_file_sequence;
    }

    /**
     * Load the config files. This is probably the first file sequence to be loaded.
     */
    protected function Config()
    {
        $config_dir = $this->getConfigDir();

        return [
            $this->appendFileArgs(
                $config_dir,
                [ 'default-settings.php', ]
            )
        ];
    }

    /**
     * Core files
     */
    private function Core()
    {
        return [
            $this->appendFileArgs( ALM_CORE_DIR, 'functions.php' ),
            $this->appendFileArgs( ALM_CORE_DIR, 'ms-functions.php', true ),
        ];
    }

    /**
     * Base Controller files
     */
    private function BaseController()
    {
        $base_controller_dir           = ALM_CONTROLLERS_DIR . 'base/';
        $base_controller_templates_dir = $base_controller_dir . 'templates/';
        
        return [
            /**
             * Base Controller Templates
             */
            $this->appendFileArgs(
                $base_controller_templates_dir,

                // Load all Traits in the Base Controller Template directory
                // The '*' is a wildcard to return all files with same pattern by glob()
                [ 'trait-*-factory.php' ]
            ),

            /**
             * The Plugin Factory Base Controller
             */
            $this->appendFileArgs(
                $base_controller_dir,
                [
                    'class-plugin-factory.php',
                    'class-controllers-engine.php',
                ]
            ),

            /**
             * Database Factory
             */
            $this->appendFileArgs(
                ALM_MODELS_DIR,
                [ 'class-db-factory.php' ]
            ),
        ];
    }

    /**
     * Free Package Controllers
     */
    private function FreePackage()
    {
        return [
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'User/',
                [
                    'class-user-manager.php',
                ]
            ),
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'Admin/templates/',
                [
                    'trait-*.php',
                ]
            ),
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'Admin/',
                [
                    'class-admin-manager.php',
                ]
            ),
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'Audit/event-handlers/',
                [
                    'trait-*-event-handlers.php'
                ]
            ),
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'Audit/event-groups/',
                [
                    'trait-*-events.php'
                ]
            ),
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'Audit/templates/',
                [
                    'trait-*.php'
                ]
            ),
            $this->appendFileArgs(
                ALM_CONTROLLERS_DIR . 'Audit/',
                [
                    'class-audit-observer.php',
                    'class-auditor.php',
                ]
            ),
        ];
    }
}
