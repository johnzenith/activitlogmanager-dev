<?php
namespace ALM\Models\Traits;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Models
 * @subpackage  Database Meta Data
 * @since       1.0.0
 */

trait DatabaseMetaData
{
    /**
     * The WPDB ($wpdb) resource handle
     * @var object
     * @since 1.0.0
     */
    protected $wpdb = null;
    
    /**
     * StdClass Object. Contains all registered database table names
     * @var object
     * @since 1.0.0
     */
    protected $tables = null;

    /**
     * Get the last inserted ID
     * @var int
     * @since 1.0.0
     */
    protected $last_insert_ID = 0;

    /**
     * Add the blog ID check on multisite and ignores check on non multisite installation
     * @var string
     * @since 1.0.0
     */
    protected $check_blog_flag = '';

    /**
     * Add the main site ID check on multisite and ignores check on non multisite installation
     * @var string
     * @since 1.0.0
     */
    protected $check_network_flag  = '';

    /**
     * Specifies the database collate
     * @see $wpdb
     */
    protected $collate = '';

    /**
     * Specifies the database base prefix
     * @see $wpdb
     */
    protected $base_prefix = '';

    /**
     * Setup database meta data
     * 
     * @since 1.0.0
     * 
     * @param stdClass $cache Specifies the controller cache object
     */
    protected function __setupDatabaseMetadata( $cache = null )
    {
        global $wpdb;
        $this->wpdb               = $wpdb;
        $this->collate            = $this->wpdb->get_charset_collate();
        $this->base_prefix        = esc_attr( $this->wpdb->base_prefix );
        // $this->last_insert_ID     = (int) $this->wpdb->insert_id;
        $this->check_blog_flag    = $this->getBlogSqlFlag();
        $this->check_network_flag = $this->getNetworkSqlFlag();

        // Setup the database tables names
        $this->tables = $this->getTableNames();
    }

    /**
     * Get the query flag to use.
     * When in global settings mode, the network flag is used, otherwise the blog flag is used.
     * 
     * @since 1.0.0
     * 
     * @return string The mode specific query flag.
     */
    protected function getBlogQueryFlag()
    {
        return $this->is_global_settings ? 
            $this->check_network_flag : $this->check_blog_flag;
    }

    /**
     * Get the plugin database table names
     * 
     * @since 1.0.0
     * 
     * @param string $table_slug Specifies the table slug to check for
     * 
     * @return string|null       The table name if found. Otherwise null.
     */
    protected function getTableName( $table_slug )
    {
        if ( empty( $this->base_prefix ) ) $this->__setupDatabaseMetadata();

        $table_slug = strtolower( str_replace( '-', '_', $table_slug ) );
        switch ( $table_slug )
        {
            case 'audits':
            case 'audit':
            case 'activity':
            case 'audit_log':
            case 'audit_logs':
            case 'activities':
            case 'activity_logs':
            case 'alm_activity_logs':
                return $this->tables->activity_logs;

            case 'option':
            case 'setting':
            case 'options':
            case 'settings':
            case 'alm_setting':
            case 'alm_settings':
                return $this->tables->settings;

            case 'file':
            case 'files':
            case 'monitor':
            case 'alm_file':
            case 'file_monitor':
            case 'alm_file_monitor':
                return $this->tables->file_monitor;
            
            default:
                if ( WP_DEBUG ) {
                    throw new \Exception(
                        sprintf(
                            alm__( 'Table: %s is not registered.' ),
                            $this->base_prefix . $table_slug
                        )
                    );
                }
                return '';
        }
    }

    /**
     * Get all database table names
     * 
     * @since 1.0.0
     * 
     * @return stdClass
     */
    protected function getTableNames()
    {
        if (empty( $this->base_prefix )) 
            $this->__setupDatabaseMetadata();

        return (object) [
            'settings'      => $this->base_prefix . 'alm_settings',
            'file_monitor'  => $this->base_prefix . 'alm_file_monitor',
            'activity_logs' => $this->base_prefix . 'alm_activity_logs',
        ];
    }

    /**
     * Get the specified blog flag
     * 
     * @since 1.0.0
     * 
     * @param int $blog_id Specifies the blog apply the sql filter on 
     * 
     * @return string
     */
    public function getBlogSqlFlag( $blog_id = null )
    {
        if ( ! $this->is_multisite ) return '';

        $blog_id = is_null( $blog_id ) ? $this->current_blog_ID : $blog_id;
        return "AND blog_id = $blog_id ";
    }

    /**
     * Get the current blog flag
     * 
     * @since 1.0.0
     * 
     * @param int $network_id Specifies the network to apply the sql filter on 
     * 
     * @return string
     */
    public function getNetworkSqlFlag( $network_id = null )
    {
        if ( ! $this->is_multisite ) return '';

        $network_id = is_null( $network_id ) ? $this->main_site_ID : $network_id;
        return "AND blog_id = $network_id ";
    }
}