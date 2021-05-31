<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Page Factory Template for the Plugin Factory Controller
 * @see   \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */
trait PageFactory
{
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
        if (!$this->isServerVarSet( $variable)) return '';

        $var = wp_unslash($_SERVER[ $variable]);
        return sanitize_text_field( $url_decode ? rawurlencode_deep($var) : $var );
    }

    /**
     * Get the request method
     */
    public function getRequestMethod()
    {
        return sanitize_key($this->getServerVar('REQUEST_METHOD'));
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
        if (empty($data) || is_null($key) || '' === $key)
            return $default;

        if (is_array($data)) {
            $value = isset( $data[ $key ] ) ? $data[ $key ] : $default;
        } 
        elseif (is_object($data)) {
            $value = isset( $data->$key ) ? $data->$key : $default;
        }
        else {
            $value = $default;
        }

        return $value;
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

        if ( $add_slash ) $url = trailingslashit( $url );

        return esc_url_raw( $url, $scheme );
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
        if ( empty($page) ) return false;

        if (!is_string($page) && !is_int($page) && !is_array($page))
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
        $wildcard_target  = [];
        $wildcard_nesting = preg_match( '/\*\/([\w][\w\-]+)/', $page, $wildcard_target );

        if ( $wildcard_nesting ) {
            $wildcard_target = isset( $wildcard_target[1] ) ? $wildcard_target[1] : '';

            // Remove the wildcard nested level target from the page slug
            $page = str_replace( $wildcard_target, '', rtrim( $page, '/' ) );
        } else {
            $wildcard_target = '';
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
        if ( !is_array( $page ) || !is_string( $page ) ) 
            return false;

        if ( is_array( $page ) )
        {
            foreach ( $page as $p )
            {
                $is_page_active = $this->__isPageActive( $p, $parent );

                if ( $is_page_active ) 
                    return true;
            }
            return false;
        }
        else {
            return $this->__isPageActive( $page, $parent );
        }
    }

    /**
     * Check if a particular page screen is active.
     * 
     * It uses the {@see $_SERVER['REQUEST_URI']} value to construct the check.
     * 
     * Example: /admin/user, /admin/network/user, /admni/edit.php, etc.
     * 
     * Note: Trailing 
     * 
     * @param string|array $screen_path Specifies the page paths to look up for
     * @return bool                     True if the page path is active. Otherwise false.
     */
    public function isPageScreenActive( $screen_path )
    {
        $is_screen_active = [];
        foreach ( (array) $screen_path as $path )
        {
            $request_uri        = esc_url_raw($this->getServerVar('REQUEST_URI'));
            $request_uri_path   = $this->getVar(explode('?', $request_uri), 0, '');

            $screen_paths       = [$path, trailingslashit($path)];
            $is_screen_active[] = (int) $this->strEndsWith($request_uri_path, $screen_paths);
        }

        return in_array(1, $is_screen_active, true);
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
     * Get the WP core settings page for the registered settings
     * 
     * @param  string $setting_group Specifies the registered settings name.
     * 
     * @param  string $option_name   Specifies the option name fo the setting group.
     *                               This is optional, but useful on multisite so that 
     *                               the given option can be mapped to the correct settings 
     *                               page on the network admin dashboard.
     * 
     * @return string                The corresponding settings page for the given option.
     */
    protected function getWpCoreSettingsPage($setting_group, $option_name = '')
    {
        $setting_group = str_replace('_', '-', $setting_group);
        $setting_page  = "{$setting_group}.php";
        $option_target = empty($option_name) ? '' : '#' . $this->sanitizeOption($option_name);

        if (!$this->is_multisite)
            return sprintf(
                '%s%s',
                esc_url_raw(self_admin_url($setting_page)),
                $option_target
            );

        $event_handler    = $this->getWpCoreSettingEventHandler($option_name);

        $event_id         = $this->getEventIdBySlug($event_handler, $this->wp_core_settings_slug);
        $event_data       = $this->getEventData($event_id);

        // if (!$event_data) return '#';
        
        $is_network_setting = (
            $this->getVar($event_data, 'network', false) || is_network_admin()
        );
        
        $setting_page_url   = $is_network_setting 
            ? network_admin_url($setting_page) 
            : self_admin_url($setting_page);

        return esc_url_raw($setting_page_url);
    }
}