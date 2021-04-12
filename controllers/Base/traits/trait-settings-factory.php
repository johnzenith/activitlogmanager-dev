<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Settings Base Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait SettingsFactory
{
    /**
     * Plugin settings
     * @var array
     * @since 1.0.0
     */
    protected $settings = null;
    
    /**
     * WordPress Installed Version
     * @var string
     * @since 1.0.0
     */
    protected $wp_version = '';
    
    /**
     * PHP Installed Version
     * @var string
     * @since 1.0.0
     */
    protected $php_version = '';
    
    /**
     * Plugin file
     * @var string
     * @since 1.0.0
     */
    protected $plugin_file = ALM_PLUGIN_FILE;
    
    /**
     * Plugin file name
     * @var string
     * @since 1.0.0
     */
    protected $plugin_file_name = ALM_PLUGIN_BASENAME;
    
    /**
     * Plugin option name
     * @var string
     * @since 1.0.0
     */
    protected $option_name = ALM_OPTION_NAME;

    /**
     * Plugin global option name
     * @var string
     * @since 1.0.0
     */
    protected $global_option_name = ALM_GLOBAL_OPTION_NAME;
    
    /**
     * Plugin Title (Product Name)
     * @var string
     * @since 1.0.0
     */
    protected $plugin_product_name = ALM_PLUGIN_PRODUCT_NAME;
    
    /**
     * Specifies whether the global settings mode is active
     * @var bool
     * @since 1.0.0
     */
    protected $is_global_settings = false;

    /**
     * Specifies the plugin current running mode
     * @var string
     * @since 1.0.0
     */
    protected $running_mode = '';

    /**
     * Set the network mode
     * @var string
     * @since 1.0.0
     */
    protected $network_mode = null;

    /**
     * Set the blog mode
     * @var string
     * @since 1.0.0
     */
    protected $blog_mode = null;

    /**
     * Maybe we have to initialize the settings
     */
    protected function maybeInitSettings()
    {
        if ( is_null($this->network_mode))
            $this->network_mode = $this->getNetworkMode();

        if ( is_null($this->blog_mode))
            $this->blog_mode = $this->getBlogMode();
    }

    /**
     * Load the plugin settings. Default settings are merged with the settings 
     * saved in database.
     * 
     * @since 1.0.0
     * 
     * @param stdClass $cache Specifies the controller cache object
     * 
     * @return array   List of plugin settings
     */
    protected function loadOptions( $cache = null )
    {
        return is_null( $cache ) ? $this->getSettings() : $cache->settings;
    }

    /**
     * Get the default running mode
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getDefaultRunningMode()
    {
        return ALM_DEFAULT_RUNNING_MODE;
    }

    /**
     * Get the plugin custom modes
     * @return array
     */
    public function getCustomModes()
    {
        $modes        = alm_get_default_running_modes();
        $custom_modes = (array) $this->getSetting('custom_modes');

        // Sanitize the custom modes
        $_custom_modes = [];
        foreach ( $custom_modes as $mode => $active )
        {
            if ( isset( $modes[ $mode ] ) ) continue;
            if ( ! $active || empty( $active ) ) continue; // We don't like null values

            $_custom_modes[ $mode ] = $active;
        }

        /**
         * Filters the custom modes
         * @param array  Available custom modes
         * @return array The filtered custom modes
         */
        $_custom_modes = apply_filters( 'alm/modes/custom', $_custom_modes );

        return $_custom_modes;
    }

    /**
     * Get all plugin running modes
     * @return array
     */
    public function getRunningModes()
    {
        $custom_modes = $this->getCustomModes();

        return alm_parse_running_modes( $custom_modes );
    }

    /**
     * Get the network running mode global setting
     * 
     * On non-multisite installation, the network is equivalent to the blog
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getNetworkMode()
    {
        if ( ! $this->is_multisite )
            return is_null( $this->blog_mode ) ? $this->getBlogMode() : $this->blog_mode;

        $network_running_mode = $this->getGlobalSetting('network_running_mode');

        if ( empty ( $network_running_mode ) )
            $network_running_mode = $this->getDefaultRunningMode();

        /**
         * Filters the network running mode value
         * 
         * @param  string $network_running_mode Specifies the network running mode
         * 
         * @return string The filtered network running mode. If an empty string is returned, 
         *                then it will be set to the default running mode.
         */
        $network_running_mode = apply_filters('alm/mode/network', $network_running_mode);

        if ( empty( $network_running_mode ) )
            $network_running_mode = $this->getDefaultRunningMode();

        return $network_running_mode;
    }

    /**
     * Get the running mode state global setting
     * @return string
     */
    public function getRunningModeState()
    {
        $running_mode_state = $this->getGlobalSetting('running_mode_state');
        
        if ( empty( $running_mode_state ) )
            $running_mode_state = ALM_DEFAULT_RUNNING_MODE_STATE;

        /**
         * Filters the running mode state
         * 
         * @param  string $running_mode_state Specifies the plugin running mode state
         * 
         * @return string The filtered running mode state. Returning an empty string will 
         *                set the running mode state to the default value.
         */
        $running_mode_state = apply_filters( 'alm/mode/state', $running_mode_state );
        
        if ( ! in_array( $running_mode_state, $this->getRunningModeStates(), true ) ) 
            $running_mode_state = ALM_DEFAULT_RUNNING_MODE_STATE;

        return $running_mode_state;
    }

    /**
     * Get the plugin running mode states
     * @return array
     */
    public function getRunningModeStates()
    {
        return [ 'off', 'strict', 'flexible', ];
    }

    /**
     * Get the blog running mode
     * @return string
     */
    public function getBlogMode()
    {
        // if ( $this->is_main_site )
        //     return $this->network_mode;

        $blog_mode     = $this->getSetting('blog_running_mode');
        $running_modes = $this->getRunningModes();

        if ( ! in_array( $blog_mode, $running_modes, true ) )
            $blog_mode = $this->getDefaultRunningMode();

        /**
         * Filters the blog running mode
         * 
         * @param string $blog_mode Specifies the blog running
         * 
         * @return string The filtered blog mode. 
         *                The mode will be set to default if not valid.
         */
        $blog_mode = apply_filters( 'alm/mode/blog', $blog_mode );

        if ( ! in_array( $blog_mode, $running_modes ) )
            $blog_mode = $this->getDefaultRunningMode();

        return $blog_mode;
    }

    /**
     * Get the forced blog mode state.
     * 
     * This setting is only used when the running mode state is set to 'off',
     * Which will override all other blogs running mode, except the main site.
     * 
     * @since 1.0.0
     */
    public function getForcedBlogMode()
    {
        $force_blog_running_mode = $this->getGlobalSetting('force_blog_running_mode');

        if ( empty( $force_blog_running_mode ) )
            $force_blog_running_mode = $this->getDefaultRunningMode();

        /**
         * Filters the mode to force all other blogs into.
         * The 'forced mode' is not used on the main site.
         * 
         * @param string $force_blog_running_mode Specifies the mode to force all other blog into.
         *                                        Not applicable on the main site.
         * 
         * @return string The filtered mode to force all other blog into.
         *                If returned mode is not valid, then it will be set to the default.
         */
        $force_blog_running_mode = apply_filters( 'alm/mode/force', $force_blog_running_mode );

        if ( ! in_array ( $force_blog_running_mode, $this->getRunningModes(), true ) ) 
            $force_blog_running_mode = $this->getDefaultRunningMode();

        return $force_blog_running_mode;
    }

    /**
     * Get the plugin running mode
     * 
     * @since 1.0.0
     * 
     * @return string
     */
    public function getRunningMode()
    {    
        $running_mode_state   = $this->getRunningModeState();
        $plugin_running_modes = $this->getRunningModes();
        
        /**
         * Bail out here if not running multisite or 
         * When the running mode state is set to 'flexible'
         */
        if (
            ! $this->is_multisite
            || ( ! $this->is_main_site && 'flexible' === $running_mode_state ) 
        ) return $this->blog_mode;

        if ( 'off' === $running_mode_state ) {
            $force_blog_running_mode = $this->getForcedBlogMode();

            if ( ! in_array( $force_blog_running_mode, $plugin_running_modes, true ) )
                $plugin_running_modes = $this->getDefaultRunningMode();
        }

        return apply_filters( 'alm/mode/blog', $this->network_mode );
    }

    /**
     * Get the admin email address
     * @return string
     */
    protected function getAdminEmail()
    {
        return $this->sanitizeOption(get_option('admin_email'), 'email');
    }

    /**
     * Set the global settings flag
     * @param  $flag_state  Specifies whether to enable the global settings flag
     */
    protected function isGlobalSettings( $flag_state = true )
    {
        $this->is_global_settings = $flag_state;
    }

    /**
     * Determines whether the cached settings values should be re-updated
     * Before retrieving it.
     */
    public function updateSettingsCache()
    {
        $this->settings = $this->getSettings(true);
    }

    /**
     * Serialize an option value prior to saving to saving it to the database
     * @see SettingsFactory::addOption()
     * @see SettingsFactory::__getOption()
     * 
     * @return mixed  The serialized option value on success. Otherwise the value is 
     *                returned unformatted.
     */
    protected function serialize( $value )
    {
        if (is_null($value)) return $value;
        
        if (is_array($value) || is_object($value))
            return serialize($value);
        
        return $value;
    }

    /**
     * Unserialize an option value prior to saving to returning its value from database
     * @see SettingsFactory::addOption()
     * @see SettingsFactory::__getOption()
     * 
     * @return mixed  The unserialize option value on success. Otherwise the value is 
     *                returned unformatted.
     */
    protected function unserialize( $value )
    {
        if (is_array($value) || is_object($value) || is_null($value))
            return $value;

        if (is_serialized($value))
        {
            return ( WP_DEBUG ) ?
                unserialize($value) 
                :
                // This should be fine even without compressing the error, if any.
                @unserialize($value);
        }
        return $value;
    }

    /**
     * Get all plugin settings
     * 
     * @since 1.0.0
     * 
     * @param  bool  $force_update Specifies whether to ignore the cached settings values
     * 
     * @return array
     */
    public function getSettings( $force_update = false )
    {
        if ( ! is_null( $this->settings ) && ! $force_update )
            return $this->settings;
        
        $defaults  = alm_get_default_settings();
        $settings  = $this->getOption( $this->__getOptionName() );
        $settings  = ! is_array( $settings ) ? [] : $settings;

        $settings = array_merge( $defaults, $settings );

        $settings['__global'] = ( isset( $settings['__global'] ) && ! $force_update ) ? 
            $settings['__global'] : [];

        return $settings;
    }

    /**
     * Get a specific setting from the plugin settings array
     * 
     * @see SettingsFactory::sanitizeOption()
     * 
     * @param  string 	    $setting  Specifies the plugin setting to retrieve.
     * 
     * @param  bool|string  $escape   Specifies whether or not to escape the setting's value.
     *                                For type sanitization, sets this to the 
     *                                corresponding type: 'int', 'bool', 'float', etc.
     * 
     * @param  bool         $strict   Specifies whether or not to do strict type sanitization.
     * 
     * @return mixed|null  	  	      Returns the specified setting value on success.
     * 								  Otherwise null.
     */
    public function getSetting( $setting, $escape = false, $strict = true )
    {
        // Retrieve the running mode setting
        $value = isset( $this->settings[ $setting ][ $this->running_mode ] ) ? 
            $this->settings[ $setting ][ $this->running_mode ] : false;

        // If the running mode option fails, then it maybe a standalone setting
        if ( false === $value )
        {
            if ( ! isset( $this->settings[ $setting ] ) )
                return null;

            $value = $this->settings[ $setting ];
        }

        if ( ! is_scalar( $value ) ) return $value;

        if ( ! is_string( $escape ) ) 
            return ( $escape ) ? esc_attr( $value ) : sanitize_text_field( $value );

        return $this->sanitizeOption( $value, $escape, $strict );
    }

    /**
     * Get the global setting
     * @see SettingsFactory::__getOption()
     */
    public function getGlobalSetting( $setting, $escape = false, $strict = true )
    {
        if ( isset( $this->settings['__global'][ $setting ][ $this->running_mode ] ) ) 
            return $this->settings['__global'][ $setting ][ $this->running_mode ];
        
        if ( isset( $this->settings['__global'][ $setting ] ) ) 
            return $this->settings['__global'][ $setting ];

        // Enable global settings mode
        $this->isGlobalSettings();

        $global_settings = $this->__getOption( $this->__getGlobalOptionName(), [] );

        if ( is_null( $global_settings ) )
            $global_settings = [];

        $global_settings = array_merge( alm_get_global_settings(), $global_settings );

        $this->settings['__global'] = $global_settings;

        $value = isset( $global_settings[ $setting ][ $this->running_mode ] ) ? 
            $global_settings[ $setting ][ $this->running_mode ] : null;

        if ( ! is_scalar( $value ) ) return $value;

        if ( ! is_string( $escape ) ) {
            $_value = ( $escape ) ? esc_attr( $value ) : sanitize_text_field( $value );
        } else {
            $_value = $this->sanitizeOption( $value, $escape, $strict );
        }

        // Set the global settings cache
        $this->settings['__global'][ $setting ] = $_value;

        // Disabled global settings mode
        $this->isGlobalSettings( false );

        return $_value;
    }

    /**
     * Get the plugin option name
     * @return string
     */
    public function __getOptionName()
    {
        return $this->option_name;
    }

    /**
     * Get the global option name
     * @return string
     */
    public function __getGlobalOptionName()
    {
        return ALM_GLOBAL_OPTION_NAME;
    }

    /**
     * Get a specific option.
     * 
     * @see \ALM\Models\Traits\DatabaseQueryMetaData
     *
     * @param  string  $option   The blog option to retrieve
     * 
     * @param  mixed   $default  Specifies the default value to return if value is not found.
     * 
     * @return mixed             The specified $option value on success. The default value (null)
     *                           is returned if specified and the option is not found.
     *                           False is returned on failure.
     */
    public function __getOption( $option, $default = null )
    {
        $blog_flag      = $this->getBlogQueryFlag();
        $settings_table = $this->tables->settings;

        $value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "
                    SELECT option_value 
                    FROM $settings_table  
                    WHERE option_name = %s 
                    $blog_flag;
                ",
                sanitize_key( $option )
            )
        );

        if ( $this->isLastQueryOK() 
        && ! $this->isLastQueryResultEmpty('select') )
        {
            return $default;
        }

        // Maybe we still need to return the default
        if ( is_null( $value ) ) return $default;

        return $this->unserialize( $value );
    }

    /**
     * Check if option exists
     * @see SettingsFactory::addOption()
     */
    public function optionExists( $option )
    {
        if ( ! is_string( $option ) ) return false;

        $blog_flag      = $this->getBlogQueryFlag();
        $settings_table = $this->tables->settings;

        return (bool) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "
                    SELECT COUNT(*) 
                    FROM $settings_table 
                    WHERE option_name = %s 
                    $blog_flag;
                ",
                sanitize_key( $option )
            )
        );
    }

    /**
	 * Check whether the global settings exists
     * 
     * @since 1.0.0
     * 
	 * @return bool
	 */
	protected function globalOptionExists()
	{
        $this->isGlobalSettings(); // Enable global settings mode
        $installed = $this->optionExists( $this->global_option_name );
        $this->isGlobalSettings( false ); // Disabled global settings mode
        return $installed;
	}

    /**
     * Add a specific option to the database options table
     *
     * @since  1.1.0
     * 
     * @param  string       $option   The specific option to add to database.
     *                                It must conform with sanitize_key()
     * 
     * @param  mixed        $value    The option value
     * 
     * @param  string       $format   Specifies the value type.
     *                                '%s' for string, '%d' for integer, and '%f' for float.
     * 
     * @return bool                   True if option was added successfully or already exists.
     *                                False is returned on failure.
     */
    public function addOption( $option, $value, $format = '%s' )
    {
        $option = sanitize_key( $option );
        $format = ( ! is_string( $format ) ) ? '%s' : $format;

        // Check if the $option is empty after filtering
        if ( empty( $option ) ) return false;
        
        // Return option if option already exists
        if ( $this->optionExists( $option ) ) return true;

        /**
         * Note: We are using the blog ID to maintain data integrity.
         * This will also make the plugin data portable across networks,
         * host transfer and upgrade.
         */
        if ( $this->is_global_settings ) {
            $blog_id = $this->main_site_ID;
        }
        else {
            $blog_id = ( 0 >= $this->current_blog_ID ) ? 
            get_current_blog_id() : $this->current_blog_ID;
        }

        $data = [
            'option_name'  => $option,
            'option_value' => $this->serialize( $value ),
        ];

        if ( $this->is_multisite ) {
            $data['blog_id'] = $blog_id;
        }
            
        return (bool) $this->wpdb->insert(
            $this->tables->settings, $data
        );
    }

    /**
     * Get a specific option from database options table.
     * 
     * Array of options could easily be retrieved by specifying the $option as option name 
     * and $single_option as option index in the array. >>> $option[ $single_option ]
     *
     * @since  1.1.0
     * 
     * @param string         $option          The specific option to get.
     * 
     * @param string         $single_option   A single option to get if we have an array of options.
     *                                        Set to false to discard it or leave empty.
     * 
     * @param bool           $escape          Whether to escape the single value or not
     * 
     * @return mixed|false                    Returns the given option if available, or the option 
     *                                        value from the array index if $single_option is set.
     *                                        False is returned if the option does not exists.
     */
    public function getOption( $option = null, $single_option = '', $escape = false )
    {
        // Bail out if the single option is not a string
        $single_option_empty = empty( $single_option );
        if ( ! $single_option_empty && ! is_string( $single_option ) )
        {
            throw new \Exception( sprintf(
                alm_esc_html__(
                    'The single option ($single_option) parameter must be a scalar type. The type given is %s',
                    gettype( $single_option )
                )
            ) );
        }

        $option   = is_null( $option ) ? $this->__getOptionName() : $option;
        $_options = false;

        // Some how if $option is empty, then just return null
        if ( empty( $option ) ) return null;

        $_options = $this->__getOption( $option );

        // Return the data at this point if $single_option is false or still empty
        if ( $single_option_empty ) 
            return ( $escape && is_scalar($_options) ) ? esc_attr( $_options ) : $_options;

        // If option is not set, return null
        if ( ! isset( $_options[ $single_option ] ) ) return false;

        $the_option = $_options[ $single_option ];

        return ( $escape && is_scalar( $the_option ) ) ? esc_attr( $the_option ) : $the_option;
    }

    /**
     * Update a specific option in the database
     * Note that option will be added if they don't exists
     *
     * @since  1.1.0
     * 
     * @param  string       $option  Option name to update.
     * 						         If set to null it is default to ALM_OPTION_NAME.
     * 
     * @param  mixed        $value   Option value.
     * 
     * @param  string       $format  Specifies the value type.
     *                               '%s' for string, '%d' for integer, and '%f' for float.
     * 
     * @return bool|string           True if option is updated successfully.
     *                               The string 'update-to-date' is returned if no changes 
     *                               were made. False is returned on failure.
     */
    public function updateOption( $option = null,  $value = '', $format = '%s' )
    {
        if ( is_null( $option ) ) $option = $this->__getOptionName();

        $data         = [ 'option_value' => $this->serialize( $value ) ];
        $where        = [ 'option_name' => $option ];
        $format       = ( ! is_string( $format ) ) ? '%s' : $format;
        $where_format = [ '%s' ];

        if ( $this->is_multisite )
        {
            if ( $this->is_global_settings ) {
                $blog_id = $this->main_site_ID;
            }
            else {
                $blog_id = ( 0 >= $this->current_blog_ID ) ? 
                    get_current_blog_id() : $this->current_blog_ID;
            }
            
            $where_format[]   = '%d';
            $where['blog_id'] = $blog_id;
        }

        $updated = (bool) $this->wpdb->update(
            $this->tables->settings,
            $data,
            $where,
            $format,
            $where_format
        );

        // Check whether the option is up to date.
        // False is returned if the previous and updated values are the same.
        if ( false === $updated ) {
            return $this->isLastQueryOK();
        }

        return $updated;
    }

    /**
     * Delete a specific option from the database
     *
     * @since  1.1.0
     *
     * @param string  $option          The option to remove from the database.
     * @param string  $single_option   A single option to remove if we have an array of options
     * 
     * @return bool                    True if option was deleted successfully. Otherwise false.
     */
    public function deleteOption( $option, $single_option = '' )
    {
        // Only remove the single option from options array if it exists
        if ( is_string( $option ) && ! empty( $single_option ) )
        {
            $settings = $this->getOption( $option, false );

            if ( ! isset( $settings[ $single_option ] ) ) {
                $delete = false;
            }
            else {
                unset( $settings[ $single_option ] );
                $delete = $this->updateOption( $option, $settings );

                // The alm_update_option() will return 'up-to-date'
                // if the previous and new values are the same, so check for it
                if ( 'up-to-date' === $delete ) $delete = false;
            }
        }
        else {
            $where        = [ 'option_name' => $option ];
            $where_format = [ '%s' ];

            if ( $this->is_multisite )
            {
                if ( $this->is_global_settings ) {
                    $blog_id = $this->main_site_ID;
                }
                else {
                        $blog_id = ( 0 >= $this->current_blog_ID ) ? 
                            get_current_blog_id() : $this->current_blog_ID;
                }
                
                $where_format[]   = '%d';
                $where['blog_id'] = $blog_id;
            }

            $delete = (bool) $this->wpdb->delete(
                $this->tables->settings,
                $where,
                $where_format
            );
        }

        return $delete;
    }

    /**
     * @see filter: 'alm_sanitize_option'
     */
    public function sanitizeOption( $value, $data_type = 'string', $strict = true )
    {
        // Do nothing if the $value is not scalar
        if (!is_scalar($value)) return $value;

        // Do nothing if the value is string and is empty
        if ( '' === $value ) return $value;

        $int_helper = function ($val) {
            return preg_replace( "#[^0-9]#", '', $val );
        };

        switch ( $data_type )
        {
            case 'bool':
            case 'boolean':
                return (bool) $value;

            case 'email':
                return sanitize_email( $value );

            case 'int':
            case 'integer':
                $v = $int_helper( $value );
                settype($v, 'int');
                return $v;

            case 'absint':
                return absint( $int_helper( $value ) );

            case 'float':
                if ( $strict ) return (float) $value;

                // Remove non-numeric characters excluding dot (.)
                $float = preg_replace( "#[^0-9\.]#", '', $value );

                // Remove reoccurrence of two or more dots: ..
                $float = preg_replace( "/[\.]+/", '.', $float );
                return $float;

            case 'hex':
                // Remove non-numeric characters excluding dot (.)
                $hex = preg_replace( "#[0-9A-Za-z]#", '', $value );
                return $hex;

            case 'key':
            case 'option_name':
                return sanitize_key( $value );

            case 'file_path':
                $path = wp_normalize_path( wp_strip_all_tags( $value, true ) );
                $path = preg_replace( "/[\<\>\%\=\?\$]/", '', $path );
                return $path;

            case 'filename':
                return sanitize_file_name( $value );

            case 'html_class':
                return sanitize_html_class( $value );

            case 'url':
            case 'url_raw':
                return esc_url_raw( $value );

            case 'url_display':
                return esc_url( $value );

            case 'username':
                return sanitize_user( $value, false );

            case 'textarea':
                return sanitize_textarea_field( $value );

            case 'string':
                return wp_kses_stripslashes( $value );

            default:
                return sanitize_text_field( $value );
        }
    }


    // ---------------------------------------------------------------------------
    //                              Settings Helper
    // ---------------------------------------------------------------------------


    /**
     * @todo - retrieve the mode from the db table
     * Check whether the plugin is running in super mode
     * @return bool
     */
    public function isSuperMode()
    {
        return true;
    }

    /**
     * @todo - retrieve the mode from the db table
     * Check whether the plugin is running in super mode
     * @return bool
     */
    public function isStealthMode()
    {
        return false;
    }
    
    /**
     * @todo - retrieve the mode from the db table
     * Check whether the plugin is running in super mode
     * @return bool
     */
    public function isNormalMode()
    {
        return false;
    }
    
    /**
     * Check whether or not a specific option is set
     * @return bool True if the specified setting is enabled. Otherwise false.
     */
    public function isSettingEnabled( $setting )
    {
        return ( 1 === $this->getSetting( $setting, 'int' ) );
    }

    /**
     * Check whether we can update all other blog's settings on the network 
     * by using the current blog settings.
     * 
     * @see SettingsFactory::isSettingEnabled()
     */
    public function updateSettingsAcrossNetwork()
    {
        if ( ! $this->is_multisite ) return false;
        return $this->isSettingEnabled( 'update_across_network', 'int' );
    }

    /**
     * Check whether the plugin settings is refreshed (reset)
     * 
     * @see SettingsFactory::isSettingEnabled()
     */
    public function isSettingsRefreshed()
    {
        return $this->getSetting( 'is_settings_refreshed', 'int' );
    }

    /**
     * Check whether internal IP address ranges are filterable.
     * When enabled, the private and reserved flags will be addd to the IP flag options.
     * 
     *  @see SettingsFactory::isSettingEnabled()
     */
    public function isInternalIpFilterable()
    {
        return $this->getSetting( 'filter_internal_ip', 'int' );
    }

    /**
     * Fix IP addresses running behind a proxy server
     */
    public function isIpProxyFixEnabled()
    {
        return $this->getSetting( 'reverse_proxy_fix', 'int' );
    }

    /**
     * Check whether the user/client referer can be logged
     * @return bool
     */
    public function canLogReferer()
    {
        return $this->isSettingEnabled('log_referer');
    }

    /**
     * Check whether event aggregation is allowed.
     * 
     * If enabled, we will try to aggregate the log data where possible to reduce 
     * the number of logs per event.
     * 
     * @return bool
     */
    public function isLogAggregatable()
    {
        return $this->isSettingEnabled('log_aggregation');
    }

    /**
     * Check whether verbose login is allowed.
     * 
     * If enabled, we will explicitly log all events that are triggered,
     * no matter the context.
     * 
     * Note: This is the recommended behavior for audit logs
     * 
     * @return bool
     */
    public function isVerboseLoggingEnabled()
    {
        return false;
        return $this->isSettingEnabled('verbose_logging');
    }

    /**
     * Check whether the event message can be explained
     * @return bool
     */
    public function canExplainEventMsg()
    {
        return $this->getGlobalSetting( 'explain_event_msg', 'bool' );
    }

    /**
     * Get all excluded event IDs
     * @return array Returns all excluded event IDs
     */
    public function getExcludedEventIds()
    {
        return (array) $this->getSetting( 'log_excluded_event_ids' );
    }

    /**
     * Check whether an event is excluded
     * 
     * @param  string $event_id   Specifies the event ID
     * @param  string $event_name Specifies the event name (event action/filter hook)
     * @param  array  $event      Specifies the event arguments list
     * 
     * @return bool               Returns true if event is excluded. Otherwise false.
     */
    public function isEventIdExcluded( $event_id, $event_name, $event = [] )
    {
        $disable = false;

        if ( in_array( $event_id, $this->getExcludedEventIds(), true ) ) 
            $disable = true;

        /**
         * Filters to determine whether or not to disable the event
         * 
         * @param bool $disable Specifies whether or not to disable the event
         * @param  string $event_id   Specifies the event ID
         * @param  string $event_name Specifies the event name (event action/filter hook)
         * @param  array  $event      Specifies the event arguments list
         * 
         * @return bool               Set to true to disable the event. Otherwise false.
         */
        return apply_filters( 'alm/event/disable', $disable, $event_id, $event_name, $event );
    }

    /**
     * Get excluded event notifications IDs
     * @return array An array containing the event IDs that have been excluded from 
     *               sending 'sms' or/and 'email' notifications.
     */
    public function getExcludedEventNotificationIds()
    {
        $excluded_notifications = $this->getSetting('log_notification_excluded_event_ids');
        
        $excluded_sms = isset( $excluded_notifications['sms'] ) ? 
            $excluded_notifications['sms'] : [];

        if ( ! is_array( $excluded_sms ) )
            $excluded_sms = [];

        /**
         * Filters the event IDs excluded from sending sms notification 
         * 
         * @param array  $excluded_sms Specifies event IDs excluded from sending sms notifications
         * 
         * @return array The filtered event IDs to exclude sms notification from
         */
        $excluded_sms = apply_filters( 'alm/event/id/notifications/excluded/sms', $excluded_sms );

        $excluded_emails = isset( $excluded_notifications['emails'] ) ? 
            $excluded_notifications['emails'] : [];

        if ( ! is_array( $excluded_emails ) )
            $excluded_emails = [];

        /**
         * Filters the event IDs excluded from sending email notification
         * 
         * @param array  $excluded_emails Specifies event IDs excluded from sending 
         *                                email notifications
         * 
         * @return array The filtered event IDs to exclude email notification from
         */
        $excluded_emails = apply_filters( 'alm/event/id/notifications/excluded/email', $excluded_emails );

        return [
            'sms'   => $excluded_sms,
            'email' => $excluded_emails,
        ];
    }

    /**
     * Get notification states
     * 
     * @since 1.0.0
     * 
     * @param  string $event_id   Specifies the event ID
     * @param  string $event_name Specifies the event name (event action/filter hook)
     * @param  array  $event      Specifies the event arguments list
     * 
     * @return array              Returns an associative containing 'sms' and 'email' which will 
     *                            evaluate to being enabled if either the 'sms' or 'email' 
     *                            value is 1.
     *                            If the array is empty, we will assume the settings is fresh,
     *                            and will enable both sms and email notifications.
     */
    public function getEventNotificationState( $event_id, $hook, $_event )
    {
        $excluded_notifications = $this->getExcludedEventNotificationIds();

        $notification_state = [
            'sms'   => 1,
            'email' => 1,
        ];

        if ( in_array( $event_id, $excluded_notifications['sms'], true ) )
            $notification_state['sms'] = 0;

        if ( in_array( $event_id, $excluded_notifications['email'], true ) )
            $notification_state['email'] = 0;

        return $notification_state;
    }

    /**
     * Get the failed event log increment limit.
     * This is default to 0 (0 days), meaning it is disabled.
     * 
     * @since 1.0.0
     * 
     * @param bool $day_label Specifies whether to add the 'day' string to 
     *                        the limit.
     *                        Note: if set to 0, then it has no effect.
     * 
     * @return int|string
     */
    public function getEventFailedLogIncrementLimit( $day_label = true )
    {
        $limit = (int) $this->getSetting('failed_event_log_increment_limit');

        // Prefix the limit by a minus sign
        if ($limit !== 0 && strpos($limit, '-') === false)
            $limit = "-{$limit}";

        return $day_label ? "$limit day" : $limit;
    }

    /**
     * Check whether we can log the failed login attempted password
     */
    public function canLogFailedLoginAttemptedPassword()
    {
        return $this->getSetting('log_failed_login_attempted_password', 'bool');
    }
}
