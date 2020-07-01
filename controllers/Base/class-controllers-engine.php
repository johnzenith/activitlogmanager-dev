<?php
namespace ALM\Controllers\Base;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package ControllersEngine
 * 
 * Auto run all registered controllers.
 * 
 * @since 	1.0.0
 */

class ControllersEngine
{
    use \ALM\Controllers\Base\Templates\ControllersList;

    /**
     * Plugin controllers. This will be an object type if not registered controller exists.
     * @var array|object
     * @since 1.0.0
     */
    protected $controllers = null;

    /**
     * Controller setup state
     * @var bool
     */
    protected $setup_completed = false;

    /**
     * The controllers cache storage
     * @var stdClass
     * @since 1.0.0
     */
    protected $cache_storage = null;

    /**
     * Auto run installed package controllers
     */
    public function __construct()
    {
        $this->controllers = [];

        /**
         * Create all registered controllers for the installed package
         */
        $this->CreateRegisteredControllers();
    }

    /**
     * Setup the controllers cache data
     * @param object $controller Specifies the controller to used to setup the cache.
     */
    protected function setCacheData( $controller )
    {
        if ( ! $this->isPluginFactoryInstance( $controller ) ) return;

        $cache_list = [
            'blog_data', 'network_data', 'main_site_ID', 'is_main_site', 'settings',
        ];
        
        $cache_holder = [];
        foreach ( $cache_list as $cache )
        {
            if ( property_exists( $controller, $cache ) ) 
                $cache_holder[ $cache ] = $controller->$cache;
        }

        $this->cache_storage = (object) $cache_holder;
    
        unset( $cache_holder );
    }

    /**
     * Set the controller cache data
     */
    protected function setControllerCache( $controller )
    {
        if ( ! is_null( $this->cache_storage ) ) {
            $controller->controller_cache = clone $this->cache_storage;
        }
    }

    /**
     * Check whether the controller is an instance of the Plugin Factory class
     * @param  PluginFactory $controller Specifies the controller to check for
     * @return bool
     */
    protected function isPluginFactoryInstance( $controller )
    {
        return $controller instanceof \ALM\Controllers\Base\PluginFactory;
    }

    /**
     * Auto instantiate the registered controllers
     */
    protected function CreateRegisteredControllers()
    {
        /**
         * Ignore the controller factory setup if it already exists
         */
        if ( $this->setup_completed ) return;

        foreach ( __alm_get_package_list() as $package_slug => $package )
        {
            if ( $this->isPackageRegistered( $package ) )
            {
                $package_method = $this->parsePackageName( $package );
                $this->__NewInstance( $this->$package_method() );
            }

            if ( ALM_PACKAGE === $package_slug ) break;
        }

        // Setup is completed!
        $this->setup_completed = true;

        // Cast the controller property to object type
        if ( ! empty( $this->controllers ) ) $this->controllers = (object) $this->controllers;
    }

    /**
     * Creates all the registered package controller instances
     * 
     * [Note]:
     * It is important to setup the package controller lists according to their dependency order,
     * but this shouldn't be a problem as the controller's dependency can easily be controlled 
     * by calling the $controller->__runSetup() method.
     */
    private function __NewInstance( $controllers )
    {
        if ( ! is_array( $controllers ) ) return;

        foreach ( $controllers as $controller => $controller_args )
        {
            $controller_args = array_merge( $this->getControllerDefaultArgs(), $controller_args );

            $class        = $controller_args['class'];
            $is_admin     = $controller_args['is_admin'];
            $reflection   = (bool) $controller_args['reflection'];
            $dependencies =  $controller_args['dependency'];

            // Ignore if the controller has been created already
            if ( $this->$controller instanceof $class ) continue;
            if ( $reflection && $this->$controller instanceof \ReflectionClass ) continue;

            // Only load the controller if it exists
            // And if the WP Admin interface is required, check for it
            if ( ! class_exists( $class ) || ( $is_admin && ! is_admin() ) ) continue;

            // Create the controller instance
            $class_obj         = new $class;
            $this->$controller = $reflection ? new \ReflectionClass( $class_obj ) : $class_obj;

            // Update the controller list
            $this->controllers[ $controller ] = $this->$controller;

            // Update all registered controllers if in current controller dependency list
            $this->updateRegisteredControllers(
                $controllers, $dependencies, $controller, $this->$controller
            );
            
            /**
             * Now that the controller is ready, let's fire the setup handler, but ignore if 
             * the setup has been fired already by another controller in other to fulfill the 
             * controller's dependency requirements.
             */
            if ( ! $reflection )
            {
                /**
                 * Initialize the controller.
                 * 
                 * This will provide cache data for the controllers
                 */
                if ( $this->isPluginFactoryInstance( $this->$controller ) ) 
                    $this->$controller->init();

                // Setup the controller cache
                $this->setCacheData( $this->$controller );

                // Set the controller cache if available before setup is run
                $this->setControllerCache( $this->$controller );

                // All classes extending the Plugin Factory base class always has 
                // the $is_setup_fired property set
                if ( ! property_exists( $this->$controller, 'is_setup_fired' ) )
                {
                    if ( method_exists( $this->$controller, '__runSetup' ) ) 
                        $this->$controller->__runSetup();
                }
                else {
                    if ( false === $this->$controller->is_setup_fired )
                    {
                        $this->$controller->is_setup_fired = true;
                        $this->$controller->__runSetup();
                    }
                }

                /**
                 * Fire the '__runAfterSetup' method automatically once the controller has been setup
                 */
                if ( method_exists( $this->$controller, '__runAfterSetup' ) ) 
                    $this->$controller->__runAfterSetup();
            }
            else {
                if ( $this->$controller->hasMethod('__runSetup') ) 
                    $this->$controller->getMethod('__runSetup')->invoke( $class_obj );

                if ( $this->$controller->hasMethod('__runAfterSetup') ) 
                    $this->$controller->getMethod('__runAfterSetup')->invoke( $class_obj );
            }
            
            /**
             * Fires when a specific controller setup is completed
             * @param string $controller Specifies the controller name
             */
            do_action( 'alm/controller/setup_completed', $controller );
        }

        /**
         * Fires when all controllers have been setup completely
         */
        do_action( 'alm/controllers/setup_completed' );

        /**
         * Destroy the controller cache storage
         */
        $this->cache_storage = null;
        unset( $this->cache_storage );
    }

    /**
     * Update registered controllers
     * @param array  $controllers     Specifies list of registered controllers
     * @param array  $dependencies    Specifies controllers dependency list
     * @param string $controller      Specifies the current controller
     * @param object $controller_obj  Specifies the current controller object
     */
    protected function updateRegisteredControllers( $controllers, $dependencies, $controller, &$controller_obj )
    {
        foreach ( $controllers as $c => $c_args )
        {
            // Ignore the current controller
            // Also, ignore if the controller property does not exists or not a dependency
            if ( $controller === $c 
            || ! isset( $this->controllers[ $c ] ) 
            || ! property_exists( $controller_obj, $c ) 
            || ! $this->hasDependency( $c, $dependencies ) )
            {
                continue;
            }
            
            $controller_obj->$c = $this->controllers[ $c ];
        }
    }

    /**
     * Get the controller default arguments
     */
    protected function getControllerDefaultArgs()
    {
        return [
            'class'       => '',     // The controller class
            'is_admin'    => false,  // Load in admin screen only
            'reflection'  => false,  // Create the controller using the Reflection Class
            'dependency'  => [],     // List of controllers as dependencies
        ];
    }

    /**
     * Check whether or not a controller is a dependency for another controller
     * 
     * @param string $controller    Specifies the controller to check for in the dependency list.
     * @param array  $controllers   Specifies the controller(s) dependency list
     * 
     * @return bool                 True if the controller is in the controllers dependency list.
     *                                Otherwise false.
     */
    protected function hasDependency( $controller, $controllers )
    {
        return in_array( $controller, $controllers, true );
    }

    /**
     * Check whether a package is registered
     */
    protected function isPackageRegistered( $package )
    {
        return method_exists( $this, $this->parsePackageName( $package ) );
    }

    /**
     * Properly parse the registered package name so it conform to its method 
     */
    protected function parsePackageName( $package )
    {
        return "register{$package}Controllers";
    }

    /**
     * Register Free Package Controller
     */
    private function registerFreePackageControllers()
    {
        return [
            'User' => [
                'class'      => '\ALM\Controllers\User\UserManager',
                'dependency' => [],
            ],

            'Admin' => [
                'class'      => '\ALM\Controllers\Admin\AdminManager',
                'is_admin'   => true,
                'reflection' => false,
                'dependency' => [],
            ],

            'AuditObserver' => [
                'class'      => '\ALM\Controllers\Audit\AuditObserver',
                'dependency' => [],
            ],

            'Auditor' => [ 
                'class'      => '\ALM\Controllers\Audit\Auditor',
                'dependency' => [ 'User', 'Admin', 'AuditObserver' ],
            ],

        ];
    }
}