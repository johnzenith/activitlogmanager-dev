<?php
namespace ALM\Controllers\Base\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Blog Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait BlogFactory
{
    /**
     * Specifies whether or not the site is a network installation
     * @var bool
     * @since 1.0.0
     */
    protected $is_multisite = false;

    /**
     * Specifies whether or not WordPress is running on a network
     * @var bool
     * @since 1.0.0
     */
    protected $is_network_activation = false;

    /**
     * Specifies whether the current request is on the WordPress network admin interface
     * @var bool
     * @since 1.0.0
     */
    protected $is_network_admin = false;

    /**
     * Specifies whether or not the admin screen is active
     * @var bool
     * @since 1.0.0
     */
    protected $is_admin = false;

    /**
     * Specifies whether or not the user admin screen is active
     * @var bool
     * @since 1.0.0
     */
    protected $is_user_admin = false;

    /**
     * Specifies the main site ID
     * @var int
     * @since 1.0.0
     */
    protected $main_site_ID = 0;

    /**
     * Specifies whether or not the main site is active
     * @var bool
     * @since 1.0.0
     */
    protected $is_main_site = false;

    /**
     * The current blog ID
     * @var int
     * @since 1.0.0
     */
    protected $current_blog_ID = 0;

    /**
     * Get the blog data
     * @see get_blog_details()
     * @var object
     * @since 1.0.0
     */
    protected $blog_data = null;

    /**
     * Get network data. This is only set in multi-site installation
     * @var object
     * @since 1.0.0
     */
    protected $network_data = null;

    /**
     * Setup the Blog Factory data
     * @param stdClass $cache Specifies the controller cache object
     */
    protected function  __setupBlogData( $cache = null )
    {
        $this->is_admin              = is_admin();
        $this->blog_data             = is_null( $cache ) ? $this->getBlogData() : $cache->blog_data;
        $this->is_multisite          = is_multisite();
        $this->network_data          = is_null( $cache ) ? $this->getNetworkData() : $cache->network_data;
        $this->main_site_ID          = is_null( $cache ) ? get_main_site_id() : $cache->main_site_ID;
        $this->is_main_site          = is_null( $cache ) ? is_main_site() : $cache->is_main_site;
        $this->is_user_admin         = is_user_admin();
        $this->current_blog_ID       = $this->blog_data->ID;
        $this->is_network_admin      = is_network_admin();
        
        $this->is_network_activation = $this->is_multisite ? 
            is_plugin_active_for_network( ALM_PLUGIN_BASENAME ) : false;
    }

    /**
     * Determine the blog prefix to use.
     * @return string
     */
    protected function getBlogPrefix()
    {
        return $this->wpdb->base_prefix;
    }

    /**
     * Get blog data
     * @see get_bloginfo()
     * 
     * @return object  The blog data
     */
    protected function getBlogData( $blog_id = null )
    {
        $bloginfo = [];
        $blog_id  = (int) $blog_id;

        if ( empty( $blog_id ) ) {
            $blog_id = $this->current_blog_ID;
        }

        $data = [
            'url',
            'wpurl',
            'description',
            'template_directory',
            'stylesheet_directory',
            'stylesheet_url',
            'template_uri',
            'admin_email',
            'charset',
            'html_type',
            'version',
            'language',
            'name',
        ];

        foreach ( $data as $d ) {
            $bloginfo[ $d ] = get_bloginfo( $d );
        }

        $data = [
            'date_format',
            'time_format',
            'timezone_string',
            'template',
            'start_of_week',
            'upload_path',
            'users_can_register',
        ];
        
        foreach ( $data as $d ) {
            $bloginfo[ $d ] = $this->getBlogOptionById( $blog_id, $d );
        }

        // Set the blog ID
        $bloginfo['ID'] = $blog_id;

        return (object) $bloginfo;
    }

    /**
     * Get the network data
     * @see get_network()
     * @return object The network data
     */
    protected function getNetworkData( $network = null )
    {
        if ( ! $this->is_multisite ) return false;

        return get_network( $network );
    }
    
    /**
     * Get a specific blog option. 
     * 
     * Uses the get_blog_option() on multisite
     * Uses the get_option() on a single site
     *
     * @param  string  $option   The site option to retrieved
     * @param  string  $default  Specifies default value to use if option is not set
     * @param  bool    $escape   Specifies whether or not to escape the option value.
     * 
     * @return mixed             The specified $option value
     */
    protected function getBlogOption( $option, $default = null, $escape = false )
    {
        $_options = $this->is_multisite ? 
            get_blog_option( $this->current_blog_ID, $option, $default ) 
            : 
            get_option( $option, $default );

        return ( is_scalar( $_options ) && $escape ) ? esc_attr( $escape ) : $_options;
    }

    /**
     * Get blog option by ID
     * @see BlogFactory::getBlogOption()
     */
    protected function getBlogOptionById( $blog_id = null, $option, $default = null, $escape = false )
    {
        if ( ! $this->is_multisite 
        || is_null( $blog_id ) 
        || $this->current_blog_ID == $blog_id )
        {
            $value = $this->getBlogOption( $option, $default, $escape );
        }
        else {
            $blog_id = is_null( $blog_id ) ? $this->current_blog_ID : $blog_id;
            $value   = $this->getBlogOption( $blog_id, $option, $default, $escape );
        }
        
        return $value;
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
     * @return bool|mixed             True if option was added successfully.
     *                                If option already exists, then the value is returned.
     *                                Otherwise, false is returned on failure.
     */
    protected function addBlogOption( $option, $value )
    {
        $option = sanitize_key( $option );

        // Check if the $option is empty after filtering
        if ( empty( $option ) ) return false;

        $add = $this->is_multisite ? 
            add_blog_option( $this->current_blog_ID, $option, $value )
            : 
            add_option( $option, $value );

        return $add;
    }

    /**
     * Update a specific option in the database
     * Note that option will be added if they don't exists
     *
     * @since  1.1.0
     * 
     * @param  string       $option  Option name to update.
     * 
     * @param  mixed        $value   Option value.
     * 
     * @return bool|string           True if option is updated successfully.
     *                               The string 'update-to-date' is returned if no changes 
     *                               were made. False is returned on failure.
     */
    protected function updateBlogOption( $option,  $value = '' )
    {
        $updated = $this->is_multisite ? 
            update_blog_option( $this->current_blog_ID, $option, $value ) 
            : 
            update_option( $option, $value );

        // Check whether the option is up to date.
        // False is returned if the previous and updated values are the same.
        if ( false === $updated ) {
            global $wpdb;
            return ( empty( $wpdb->last_error ) ) ? 'up-to-date' : false;
        }

        return true;
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
    protected function deleteBlogOption( $option, $single_option = '' )
    {
        // Only remove the single option from options array if it exists
        if ( ! empty( $single_option ) )
        {
            $settings = $this->getBlogOption( $option, false );

            if ( ! isset( $settings[ $single_option ] ) ) {
                $delete = false;
            } else {
                unset( $settings[ $single_option ] );
                $delete = $this->updateBlogOption( $option, $settings );

                // The alm_update_option() will return 'up-to-date'
                // if the previous and new values are the same, so check for it
                if ( 'up-to-date' === $delete ) $delete = false;
            }
        }
        else {
            $delete = $this->is_multisite ? 
                delete_blog_option( $this->current_blog_ID, $option ) : delete_option( $option );
        }

        return $delete;
    }

    /**
     * Retrieve the site name.
     * 
     * @param bool    $current_blog  Specify whether to retrieve the site name for the current blog.
     *                               Applicable only on multisite.
     * 
     * @return string                The retrieved site name.
     */
    public function getBlogName( $current_blog = true )
    {
        if ( $this->is_multisite ) {
            $site_name = $current_blog ? $this->blog_data->name : $this->network_data->name;
        } else {
            $site_name = wp_specialchars_decode( $this->blog_data->name, ENT_QUOTES );
        }

        return esc_html( $site_name );
    }

    /**
     * Get total number of sites (blogs) on the network
     * @return int
     */
    public function getSiteCount()
    {
        $blog_table = $this->wpdb->blogs;
		$site_count = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $blog_table"
        );
        return $site_count;
	}

    /**
     * Get List of sites ID
     * @return array
     */
    protected function getSitesId( $limit = '' )
    {
        // Return the current blog ID if not on 'network wide plugin activation'
        if ( ! $this->is_network_activation ) {
            return [ $this->current_blog_ID ];
        }

        $limit      = trim( $limit );
        $limit      = ( ! empty( $limit ) ) ? "LIMIT $limit;" : ';';
        $blog_table = $this->wpdb->blogs;

        $blog_sql   = "SELECT blog_id FROM $blog_table";
        $sites_ID   = $this->wpdb->get_col( $blog_sql );

        return $sites_ID;
    }
}