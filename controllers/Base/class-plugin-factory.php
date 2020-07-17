<?php
namespace ALM\Controllers\Base;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Plugin Factory
 * 
 * Responsible for providing access gateway to every other controllers,
 * except for the plugin [Installer Controller] that runs in an isolated mode.
 * 
 * @since 	1.0.0
 */

use \ALM\Controllers\Base\Templates as ALM_Base_Templates;

abstract class PluginFactory
{
    /**
     * Using the Plugin Factory Controller Templates
     */
    use ALM_Base_Templates\SettingsFactory,
        ALM_Base_Templates\ControllersList,
        ALM_Base_Templates\FileUtility,
        ALM_Base_Templates\BlogFactory,
        ALM_Base_Templates\IP_Factory,
        \ALM\Models\Templates\DatabaseMetaData,
        \ALM\Models\Templates\DatabaseQueryMetaData;
    
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
     * Specifies the load process total time in seconds
     * 
     * This property is useful only when the PluginFactory::getLoadProcessEndTime() 
     * has been called
     * 
     * @var float
     * @since 1.0.0
     */
    protected $_load_process_total_time = 0;

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
            throw new \Exception( sprintf( "Undefined property name: %s", $name ) );
        
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
     * Get a value from an object or array
     * 
     * @since 1.0.0
     * 
     * @param array|object $data    Specifies the array or object to retrieve the value from
     * 
     * @param string       $param   Specifies the array or object key (index) to use to 
     *                              retrieve the value
     * 
     * @param mixed        $default Specifies the default value to return if key is not found
     * 
     * @return null|mixed           Returns the found array or object value. Otherwise null;
     */
    public function getVar( $data, $key, $default = null )
    {
        if ( empty( $data ) || empty( $key ) )
            return null;

        if ( is_array( $data ) ) {
            $value = isset( $data[ $key ] ) ? $data[ $key ] : $default;
        }
        elseif ( is_object( $data ) ) {
            $value = isset( $data->$key ) ? $data->$key : $default;
        }
        else {
            $value = $default;
        }

        return $value;
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
     * Get the current page script name.
     * The get_current_screen() function may not be available at some point,
     * this will return the current page script name
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getCurrentPageScriptName()
    {
        $current_page_file = explode('/', $this->getServerVar('PHP_SELF'));
        if ( empty( $current_page_file ) )
            return '';
            
        $script = end( $current_page_file );
        return basename( $script );
    }

    /**
     * Get the url scheme to use
     */
    public function getUrlScheme()
    {
        return is_ssl() ? 'https' : 'http';
    }

    /**
     * Get a page url by specifying its relative path name.
     * 
     * @param string $page_path 	Specify the relative url to use in constructing the page 
     * 								absolute url. Leave empty to use the current page path.
     * 
     * @param bool   $add_slash 	Specify whether or not to append a forward slash '/' to the 
     * 								constructed url.
     * 
     * @return string             The constructed page url.
     */
    public function getPageUrl( $page_path = '', $add_slash = true )
    {
        if ( empty( $page_path  ) ) {
            $page_path = explode( '?', $this->getServerVar( 'REQUEST_URI' ) )[0];
        }

        $scheme = $this->getUrlScheme();

        $server = preg_replace(
            '/[^\w\-\.]/', '', sanitize_text_field( $this->getServerVar( 'SERVER_NAME' ) )
        );

        $url = $scheme . '://' . $server . $page_path;

        if ( $add_slash ) $url = wp_slash( $url );

        return esc_url_raw( $url, $scheme );
    }

    /**
     * Check if the specified page is active by specifying the page ID or Slug (page path)
     * a
     * @param int|string|array $page 	    Specify the page ID or Slug or List of page IDs/slugs.
     * 									    Note: the page slug supports wildcard specification.
     * 									    Example: parent/page/*
     * 
     * @param bool 		       $parent      Specify whether or not to mark child page paths active 
     * 									    once the parent page is active.
     * 
     * @return bool Returns true when page is active. Otherwise false.
     */
    private function __isPageActive( $page = 0, $parent = false )
    {
        $page = sanitize_text_field( $page );
        if ( empty( $page ) ) return false;
        
        if ( !is_string( $page ) && !is_int( $page ) && !is_array( $page ) )
        {
            if ( WP_DEBUG ) {
                throw new \Exception( sprintf(
                    alm__( 'Argument 1 (%s) data type is invalid. Accepted types: string, int, array. Given type is: %s.' ),
                    $page, gettype( $page )
                ) );
            }
            return false;
        }

        // Check whether page slug support restricted level nesting
        $wildcard_target  = '';
        $wildcard_nesting = preg_match( '/\*\/([\w][\w\-]+)/', $page, $wildcard_target );

        if ( $wildcard_nesting ) {
            $wildcard_target = isset( $wildcard_target[1] ) ? $wildcard_target[1] : '';

            // Remove the wildcard nested level target from the page slug
            $page = str_replace( $wildcard_target, '', rtrim( $page, '/' ) );
        }

        // Check whether page slug support wildcard
        $page_len = mb_strlen( $page );
        if ( '*' === mb_substr( $page, ($page_len - 1) )
        || '*/' === mb_substr( $page, ($page_len - 2 ) ) )
        {
            $page   = preg_replace( '/(\*\/|\*)/', '', $page );
            $parent = true;
        }

        // Get the current page url
        $current_page_url = $this->getPageUrl();

        // Check whether a page slug is given
        if ( preg_match( '/[^0-9]/', $page ) )
        {
            // When getting page by path, let's add a preceding slash
            $page     = ( 0 !== strpos( $page, '/', 0 ) ) ? '/'. $page : $page;

            // Just to be sure the page slug is fine in query
            $page     = preg_replace( [ '/\-\-/', '/\;/', ], [ '-', '', ], $page );
            $get_page = get_page_by_path( $page );
        } else {
            $get_page = get_post( $page );
        }

        $page_url   = esc_url( get_the_permalink( $get_page ) );
        $check_page = ( 0 === strcmp( trailingslashit( $page_url ), trailingslashit( $current_page_url ) ) );

        // Check whether the parent page path is active within child's page url
        if ( $parent && !$check_page )
        {
            if ( false !== strpos( $current_page_url, $page ) )
            {
                $path        = '/';

                $request_uri = explode( '?', $this->getServerVar( 'REQUEST_URI' ) );
                $request_uri = isset( $request_uri[0] ) ? $request_uri[0] : '';

                $page_path   = sanitize_text_field( $request_uri );

                $server      = sanitize_key( $this->getServerVar( 'SERVER_NAME' ) );
                $site_url    = esc_url( site_url( '/' ) );
                $site_path   = preg_replace( "/.+?($server)\/?/", '', $site_url );

                $filter_site_path = '/^(\/)?(' . str_replace( '/', '\/', $site_path ) . ')(\/)?/';

                $page_path      = preg_replace( $filter_site_path, '', $page_path );
                $_page_paths    = (array) explode( '/', $page_path );
                $wildcard_level = 0;

                foreach ( $_page_paths as $p )
                {
                    $p     = sanitize_key( $p );
                    $path .= $p . '/';

                    if ( $page === $path ) {
                        if ( empty( $wildcard_target ) ) {
                            break;
                        }
                        $wildcard_level = 1;
                    }

                    // Check the wild-card nested level
                    if ( 0 < $wildcard_level )
                    {
                        // Keep building the page path
                        $page = ( 1 < $wildcard_level ) ? rtrim( $page, '/' ) . '/' . $p : $page;

                        if ( $wildcard_target === $p ) {
                            // Ignore trailing page paths
                            $wildcard_level = 0;
                        }

                        ++$wildcard_level;
                    }
                }

                $check_page = ( 0 === strcmp( ( $site_url . trim( $page, '/' ) ), ( $site_url . trim( $path, '/' ) ) ) );
            }
        }

        return $check_page;
    }

    /**
     * Wrapper for the PluginFactory::__isPageActive() method
     * @see PluginFactory::__isPageActive()
     */
    final public function isPageActive( $page = 0, $parent = false )
    {
        // $page variable can be either array or string
        if ( !is_array( $page ) || !is_string( $page ) ) return false;

        if ( is_array( $page ) )
        {
            foreach ( $page as $p )
            {
                $is_page_active = $this->__isPageActive( $p, $parent );
                if ( $is_page_active ) return true;
            }
            return false;
        }
        else {
            return $this->__isPageActive( $page, $parent );
        }
    }

    /**
     * Get the last character in a string
     * @param  string $str Specifies the string to get the last character from
     * @return string      The string last character.
     */
    public function strLastChar( $str )
    {
        if ( ! is_scalar( $str ) ) return '';
        $str = (string) $str;
        return empty( trim($str) ) ? '' : substr( $str, -1, 1 );
    }

    /**
     * Check whether a string ends with a given pattern
     * 
     * @param  string       $str              Specify string to check for given pattern
     * 
     * @param  string|array $pattern          The pattern to check for at end of string.
     *                                        This can be a string or list of string.
     * 
     * @param bool          $case_insensitive Specifies whether the check should be case 
     *                                        sensitive or not
     * 
     * @return bool                           True if the given string ends with the given pattern.
     *                                        Otherwise false.
     */
    public function strEndsWith( $str, $pattern, $case_insensitive = false )
    {
        $_pattern = (array) $pattern;
        foreach ( $_pattern as $p )
        {
            if ( ! is_array( $p ) )
            {
                $match = '/'. preg_quote( $p ) .'$/' . ($case_insensitive ? 'i' : '');;
                if ( preg_match( $match, $str ) ) 
                    return true;
            }
        }
        return false;
    }

    /**
     * Check whether a string starts with a given pattern
     * 
     * @param  string       $str              Specify string to check for given pattern
     * 
     * @param  string|array $pattern          The pattern to check for at beginning of string.
     * 
     * @param bool          $case_insensitive Specifies whether the check should be case 
     *                                        sensitive or not
     * 
     * @return bool                           True if the given string starts with the given pattern.
     *                                        Otherwise false.
     */
    public function strStartsWith( $str, $pattern, $case_insensitive = false )
    {
        $_pattern = (array) $pattern;
        foreach ( $_pattern as $p )
        {
            if ( ! is_array( $p ) )
            {
                $match = '/^'. preg_quote( $p ) .'/' . ($case_insensitive ? 'i' : '');
                if ( preg_match( $match, $str ) ) 
                    return true;
            }
        }
        return false;
    }

    /**
     * Get a page query var
     *
     * @param  string $var_name    The page query var to retrieve
     * @param  bool   $escape_var  Whether or not the page query var should be escaped
     * 
     * @return string|false        The filtered page query var if existing. Otherwise false.
     */
    public function getPageQueryVar( $var_name, $escape_var = true )
    {
        if ( ! isset( $_GET[ $var_name ] ) && ! isset( $_POST[ $var_name ] ) ) return false;

        $get_value = isset( $_POST[ $var_name ] ) ? $_POST[ $var_name ] : $_GET[ $var_name ];
        $get_value = rawurldecode( $get_value );

        return ( $escape_var ) ? esc_attr( $get_value ) : sanitize_text_field( $get_value );
    }

    
    /**
     * Appends a query string to an URL
     * 
     * @param string          $url           An absolute url to append the $query_string into.
     * 
     * @param string|Array    $query_string  The query string to append to the url. An associative
     *                                       array can be passed or a string containing the url 
     *                                       query with a key and value pair separated by the '&' 
     *                                       character: key=value&key2=value2.
     *                                       Note: Setting the url query parameter to false or null
     *                                       will remove it from the given url.
     *  
     * @param bool           $escape_url    Specify whether the formatted url should be escaped or not.
     * 
     * @return string|false                 The absolute url is returned with the query string added. 
     *                                      False is returned if the $url var is not an absolute url 
     *                                      or the $query_string var is malformed.
     */
    public function appendQueryToUrl( $url, $query_string, $escape = true )
    {
        $test_url = esc_url_raw( $url );
        if ( empty( $test_url ) || !$test_url || empty( $query_string ) ) return false;

        if ( !is_array( $query_string ) && !is_string( $query_string ) ) return false;

        $split_url   = wp_parse_url( $url );
        $query_vars  = $append_vars = [];
        
        if ( is_string( $query_string ) ) {
            parse_str( $query_string, $append_vars );
        } else {
            $append_vars = $query_string;
        }

        $url_queries = isset( $split_url['query'] ) ? $split_url['query'] : '';
        parse_str( $url_queries, $query_vars );

        // Make sure $query_vars is an array
        $query_vars = ! is_array( $query_vars ) ? [] : $query_vars;

        // Merge $query_vars and $append_vars together
        $set_query_args = array_merge( $query_vars, $append_vars );

        $url = add_query_arg( $set_query_args, explode( '?', $url )[0] );

        return $escape ? esc_url_raw( $url ) : $url;
    }

    /**
     * Get the a host from a given url
     * 
     * @param string  $url     Specify the url to get the given host from
     * @param bool    $escape  Specify whether or not to escape the host
     * 
     * @return string|false    Returns the host url on success. Otherwise false.
     */
    public function getHostFromUrl( $url, $escape = true )
    {
        $url_components = wp_parse_url( $url );

        $url_host   = isset( $url_components['host'] )   ? $url_components['host']   : '';
        $url_scheme = isset( $url_components['scheme'] ) ? $url_components['scheme'] : '';
        
        $host      = preg_replace( '/[^\w\-\.]/', '', $url_host );
        $scheme    = sanitize_key( $url_scheme );
        $check_url = esc_url_raw( "{$scheme}://{$host}" );

        if ( empty( $check_url ) || ! $check_url ) return false;

        return $check_url;
    }

    /**
     * Get the page slug from current url
     */
    public function getActivePageSlug()
    {
        $url = wp_parse_url( $this->getServerVar( 'REQUEST_URI' ) );

        if ( ! isset( $url['path'] ) ) return false;

        $url    = explode( '/', untrailingslashit( $url['path'] ) );
        $offset = count( $url ) - 1;

        $url_offset = isset( $url[ $offset ] ) ? $url[ $offset ] : '';
        return sanitize_key(  $url_offset );
    }

    /**
     * Generate a cryptographically secure numbers
     * 
     * @param int     $min     The minimum number range to used in generating the secure numbers
     * @param int     $max     The maximum number range to used in generating the secure numbers
     * @param int     $length  Specify the length of the generated secure numbers
     * 
     * @return string          Returns the generated cryptographically secure numbers on success.
     *                         An empty string is returned when $min or $max is not an integer.
     */
    public function generateSecureInt( $min = 0, $max = 9, $length = 16 )
    {
        if ( ! is_int( $min ) || ! is_int( $max ) ) return '';

        $length     = ( $length < 1 ) ? 1 : $length;
        $secure_str = '';

        for ( $i = 0; $i <= 16; $i++ ) {
            $secure_str .= random_int( $min, $max );
        }
        return $secure_str;
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
        return function_exists( "alm_{$pluggable}" ) || method_exists( $this, $pluggable );
    }

    /**
     * Get alphabet list in uppercase or lowercase
     * 
     * @param string  $alphabet  Specify the alphabet letters to return, whether lowercase or 
     *                              uppercase. Values accepted: 'lowercase' | 'uppercase'.
     *                              Note: This function returns a combination of uppercase and 
     *                              lowercase letters when the alphabet type to return is not 
     *                              specified.
     * 
     * @return string             The specified alphabet letters.
     */
    public function getAlphabets( $alphabet = '' )
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        if ( 'uppercase' === $alphabet ) return $uppercase;
        if ( 'lowercase' === $alphabet ) return $lowercase;

        return $lowercase . $uppercase;
    }

    /**
     * Sanitize string data prior to specified context
     * 
     * @see /wp-includes/default-filters.php
     * @see /wp-includes/user.php
     * 
     * @return string The sanitized string data
     */
    public function sanitizeStr( $str = '', $context = 'display' )
    {
        if ( empty( $str ) ) return $str;

        if ( ! is_string( $str ) && ! is_numeric( $str ) ) return $str;

        switch( $context )
        {
            case 'raw':
                return $str;

            case 'db':
                $str = sanitize_text_field( $str );
                $str = wp_filter_kses( $str );
                $str = _wp_specialchars( $str );
                return $str;

            case 'description':
                return esc_html( $str );

            case 'attr':
            case 'attribute':
                return esc_attr( $str );

            case 'js':
                return esc_js( $str );

            case 'user_url':
                return esc_url( $str );

            case 'url_raw':
                return esc_url_raw( $str );

            case 'display':
                if ( $this->is_admin ) {
                    // These are expensive. Run only on admin pages for defense in depth.
                    $str = sanitize_text_field( $str );
                    $str = wp_kses_data( $str );
                }
                $str = _wp_specialchars( $str );
                return $str;

            case 'textarea_save':
                return wp_filter_kses( $str );

            default:
                return _wp_specialchars( wp_kses_data( $str ) );
        }
    }

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

    /**
     * Check whether the server variable is set
     * @param  string $variable  Specifies the server variable to check for
     * @return bool              True if the server variable is set. Otherwise false.
     */
    public function isServerVarSet( $variable )
    {
        return isset( $_SERVER[ $variable ] );
    }

    /**
     * Get the server variable
     * @see PluginFactory::isServerVarSet()
     */
    public function getServerVar( $variable, $url_decode = false )
    {
        if ( ! $this->isServerVarSet( $variable ) ) return '';

        $var = wp_unslash( $_SERVER[ $variable ] );
        return sanitize_text_field( $url_decode ? rawurlencode_deep( $var ) : $var );
    }

    /**
     * Get the request method
     */
    public function getRequestMethod()
    {
        return sanitize_key( $this->getServerVar( 'REQUEST_METHOD' ) );
    }

    /**
     * Get the user referer. This will give preference to the wp_get_original_referer()
     * @see wp_get_original_referer()
     */
    public function getReferer( $autoload_referer = false, $escape = true )
    {
        $ref = wp_get_original_referer();
        if ( ! $ref || empty( $ref ) ) {
            $ref = wp_get_raw_referer();
        }

        if ( ( ! $ref || empty( $ref ) ) && $autoload_referer ) {
            $ref = $this->getPageUrl();

            // PluginFactory::getPageUrl() will always raw escape the url value
            $escape = false;
        }

        return $escape ? esc_url_raw( $ref ) : $ref;
    }

    /**
     * Recursively get the differences between two arrays
     * @param  array $array1 The new array to get difference for
     * @param  array $array2 Existing array to check on
     * @return array The differences between the two arrays which is taken from the first array.
     */
    public function arrayDiffAssocRecursive( array $array1, array $array2 )
    {
        $difference = [];
        foreach ( $array1 as $key => $value )
        {
            if ( is_array( $value ) )
            {
                if ( !isset( $array2[ $key ] ) || !is_array( $array2[ $key ] ) ) {
                    $difference[ $key ] = $value;
                }
                else {
                    $new_diff = $this->arrayDiffAssocRecursive( $value, $array2[ $key ] );
                    if ( !empty( $new_diff ) )
                        $difference[ $key ] = $new_diff;
                }
            }
            elseif ( !array_key_exists( $key, $array2 ) || $array2[ $key ] !== $value ) {
                $difference[ $key ] = $value;
            }
        }
        return $difference;
    }

    /**
     * Get list of error page codes and descriptions
     * 
     * @param int $error_page_code  Specifies the specific error page code to return
     * 
     * @return array
     */
    public function getErrorPageList( $error_page_code = null )
    {
        $error_page_code = (int) $error_page_code;

        $error_pages = [
            403 => [
                'title'       => '403 Forbidden',
                'description' => 'The server has rejected your request.',
            ],
            404 => [
                'title'       => '404 Not Found',
                'description' => 'The requested document (page) was not found on this server.',
            ],
            405 => [
                'title'       => '405 Method Not Allowed',
                'description' => 'The method specified in the Request-Line is not allowed for the specified resource.',
            ],
            408 => [
                'title'       => '408 Request Timeout',
                'description' => 'Your browser failed to send a request in the time limit allowed by the server.',
            ],
            500 => [
                'title'       => '500 Internal Server Error',
                'description' => 'The request was unsuccessful due to unexpected condition encountered by the server.',
            ],
            502 => [
                'title'       => '502 Bad Gateway',
                'description' => 'The server received an invalid response from the upstream while trying to fulfill the request.',
            ],
            504 => [
                'title'       => '504 Gateway Timeout',
                'description' => 'The upstream server failed to send a request in the time allowed by the server.',
            ],
        ];

        return isset( $error_pages[ $error_page_code ] ) ? 
            $error_pages[ $error_page_code ] : $error_pages;
    }

    /**
     * Join values from an array with a given separator character
     * @param string $data      Specifies the array data to join
     * @param string $separator Specifies the character to use to separate the array values
     */
    public function joinValues( array $data, $separator = '|' )
    {
        if ( ! is_string( $separator ) ) 
            $separator = '|';
        
        $join = '';
        foreach ( $data as $k => $v )
        {
            if ( is_array( $v ) ) {
                $this->joinValues( $v, $separator );
            } else {
                $join .= "{$k}={$v}{$separator}";
            }
        }
        return rtrim( $join, $separator );
    }

    /**
     * Check if local host sever is active
     * @return bool
     */
    public function isLocalhost()
    {
        $whitelist = [ '127.0.0.1', '::1', ];
        return in_array( $this->getServerVar('REMOTE_ADDR' ), $whitelist, true );
    }

    /**
     * Set constant
     * @see define()
     */
    public function setConstant( $name, $value )
    {
        if ( ! defined( $name ) ) 
            define( $name, $value );
    }

    /**
     * Check if a constant is defined
     * @see define()
     */
    public function getConstant( $name )
    {
        if ( defined( $name ) )
            return constant( $name );

        return false;
    }

    /**
     * Get array values recursively
     * 
     * @since 1.0.0
     * 
     * @see array_walk_recursive()
     */
    public function arrayValuesToStringRecursive( array $data )
    {
        $values = '';
        array_walk_recursive(
            $data,
            function ( $v, $k ) use ( &$values )
            {
                $values .= "$v, ";
            }
        );

        return trim( $values, ', ' );
    }

    /**
     * @todo - get the plugin date from settings table
     * Get the plugin time format
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getPluginTimeFormat()
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the WordPress load process start time
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getLoadProcessStartTime()
    {
        global $timestart;
        if ( empty( $timestart ) )
            return '';

        return @gmdate( $this->getPluginTimeFormat(), $timestart );
    }

    /**
     * Get the WordPress load process end time
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getLoadProcessEndTime()
    {
        global $timeend;

        // Update the load process end time variable 
        $this->_load_process_total_time = timer_stop();

        if ( empty( $timeend ) )
            return '';strtotime('');

        return @gmdate( $this->getPluginTimeFormat(), $timeend );
    }

    /**
     * @todo - finish this date formatter
     * Get the days equivalent from any given date, date format or timestamp
     * 
     * @since 1.0.0
     * 
     * @param string $format Specifies the date, datetime format or timestamp
     * 
     * @param bool  $future  Specifies whether to subtract from or add to the current date.
     *                       True will will add to the current date.
     *                       False will subtract from the current date.
     * 
     * @return int           The day(s) equivalent in the given date
     */
    public function getDateFormatInDays( $date, $future = false )
    {
        $days  = 0;
        $sign  = $future ? '+' : '-';
        $_date = strtolower( $date );

        switch ( $_date )
        {
            case 'last week':
                $days = 7;
                break;
            
            case 'next week':
                $days = 7;
                break;
            
            default:
                # code...
                break;
        }

        return $days;
    }

    /**
     * Get a date time by a given format.
     * Note: The format must be parsable by the PHP date() function
     * 
     * @since 1.0.0
     * 
     * @see date()
     * 
     * @param string  $date   Specifies the date to retrieve in the given format
     * 
     * @param string  $format Specifies the format to use in creating the date
     * 
     * @return string         The date in the given format. Empty string is returned 
     *                        when the format is not parsable.
     */
    public function getDateByFormat( $date = 'now', $format = '' )
    {
        if ( empty( $date ) ) 
            return '';

        $_format = empty( $format ) ? 'Y-m-d H:i:s' : $format;

        $d = new \DateTime( $date );
        return $d->format( $_format );
    }

    /**
     * Get the date time with microseconds
     * 
     * @since 1.0.0
     * 
     * @param  bool   $microseconds  Specifies whether to return the datetime with microseconds
     * @return string                The formatted datetime.
     */
    public function getDate( $microseconds = true )
    {
        $format = $microseconds ? 'Y-m-d H:i:s.u' : 'Y-m-d H:i:s';
        return $this->getDateByFormat( 'now', $format );
    }

    /**
     * @todo - Specifies the plugin time format
     * 
     * @since 1.0.0
     * 
     * @see PluginFactory::getDateByFormat()
     * 
     * Get the date time in the plugin format
     * 
     * @param  string Specifies the date to retrieve in the selected plugin datetime format
     * @return string The formatted datetime
     */
    public function getDataInPluginFormat( $datetime = 'now' )
    { 
        return $this->getDateByFormat( $datetime, $this->getPluginTimeFormat() );
    }

    /**
     * Check whether an array is a large array.
     * 
     * Large array criteria: 
     *  - array keys is not numeric
     *  - array keys is numeric and values count exceeds 3
     *  - is a multi-dimensional array 
     *  - is an object
     */
    public function isLargeArray( $data )
    {
        $is_obj = is_object( $data );
        if ( ! is_array ( $data ) && ! $is_obj ) 
            return false;
            
        if ( $is_obj ) 
            return true;        
            
        foreach ( $data as $k => $d )
        {
            // Check if keys is numeric
            if ( ! is_int( $k ) ) 
                return true;

            // Check for multi-dimensional array
            if ( is_array( $d ) ) 
                return true;
        }

        // From this point, it means array keys is numeric, check if values exceeds 3
        if ( count( $data ) > 3 ) 
            return true;

        return false;
    }

    /**
     * Parse data for DB.
     * Properly transform an array/object data before it is saved in database
     * 
     * @since 1.0.0
     * 
     * @see PluginFactory::isLargeArray()
     * 
     * @return mixed 
     */
    public function parseValueForDb( $value )
    {
        if ( ! is_array( $value ) ) 
            return $value;

        return $this->isLargeArray( $value ) ? 
            $this->serialize( $value ) : implode( ', ', $value  );
    }

    /**
     * Get vowel letters
     * 
     * @return array
     */
    public function getVowelLetters()
    {
        return ['a', 'e', 'i', 'o', 'u'];
    }
}