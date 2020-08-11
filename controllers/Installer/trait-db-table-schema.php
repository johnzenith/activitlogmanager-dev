<?php
namespace ALM\Controllers\Installer\DB;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Activity Log Manager
 * @subpackage  Database Table Schema
 * @since       Version 1.0.0
 */

trait TableSchema
{
    private function createDatabaseTables()
    {
        $this->wpdb->show_errors();
        // Include the WP table creation handler script
        $this->include( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Create the tables
        $tables = $this->__getTablesCreationSQL();
        foreach ( $tables as $table_name => $table_sql )
        {
            dbDelta( $table_sql ); // Executes the table sql query

            if ( ! empty( $this->wpdb->last_error ?? '' ) )
            {
                // Stop the activation process
                $message = sprintf(
                    alm__( 'Hi there! An error occurred during the plugin activation process. The plugin database table: <strong>%s</strong> could not be created correctly. <hr> Please contact our <a href="%s">Support Team</a> for assistance.' ),
                    esc_html( $table_name ),
                    __alm_plugin_site_url()
                );

                $title = alm__( 'Plugin Activation Error' );
                $args  = [ 'back_link' => true ];

                wp_die( $message, $title, $args );
            }
        }
    }

    /**
     * The all tables creation sql
     * @return array  List of create tables sql
     */
    private function __getTablesCreationSQL()
    {
        /**
         * Activity Logs
         * 
         * ------------------------------
         * Column Info
         * ------------------------------
         * 
         * {@see event_action_trigger} column
         * The 'event_action_trigger' column is used to register custom event that should fire once 
         * an event severity (such as critical, high, error, etc.) reached a specified frequency 
         * (the 'log_counter' column) on the same user after a certain period of time.
         * 
         * This can be used to:
         * 
         *  1. Block out a user after too many password reset.
         * 
         *  2. Lock out the login page for a specific user or IP Address
         * 
         *  3. Block a user or IP address after generating two many error page (404, 404, 500, etc.) requests.
         * 
         * You can also customize a message to display to the visitor once the event is triggered, 
         * or send the user an email message with a description of what happened and 
         * why their account was locked.
         * 
         * Event Integrity here simply means that changes to existing objects doesn't affect 
         * the logged data in the database table.
         */
        $blog_index   = '';
        $blog_column  = '';
        $site_columns = '';
        $site_indexes = '';

        if ( $this->is_multisite ) {
            $site_columns  = "blog_name varchar(190) NOT NULL DEFAULT '',";
            $blog_column   = 'blog_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,';

            $site_indexes  = 'KEY blog_name ( blog_name ),';
            $blog_index    = 'KEY blog_id ( blog_id ),';

            $site_columns .= $blog_column;
            $site_indexes .= $blog_index;

            $site_columns .= "blog_url varchar(255) NOT NULL DEFAULT '',";
            $site_indexes .= 'KEY blog_url ( blog_url ),';
        }
        
        $activity_logs = $this->getTableName( 'activity_logs' );
        $tables[ $activity_logs ] = "CREATE TABLE IF NOT EXISTS $activity_logs ( 
            log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            event_slug varchar(60) NOT NUll DEFAULT '',
            $site_columns 
            user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            object_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            user_data longtext NOT NULL DEFAULT '',
            object_data longtext NOT NULL DEFAULT '',
            user_login varchar(60) NOT NULL DEFAULT '',
            user_role varchar(200) NOT NULL DEFAULT '',
            first_name varchar(90) NOT NULL DEFAULT '',
            last_name varchar(90) NOT NULL DEFAULT '',
            severity varchar(20) NOT NULL DEFAULT '',
            event_group varchar(60) NOT NULL DEFAULT '',
            event_object varchar(60) NOT NULL DEFAULT '',
            event_action varchar(60) NOT NULL DEFAULT '',
            event_title varchar(200) NOT NULL DEFAULT '',
            event_action_trigger varchar(60) NOT NULL DEFAULT '',
            log_counter bigint(20) NOT NULL DEFAULT 1,
            log_status varchar(20) NOT NULL DEFAULT '',
            message longtext NOT NUll DEFAULT '',
            source_ip varchar(60) NOT NULL DEFAULT '0.0.0.0',
            referer_url varchar(1000) NOT NULL DEFAULT '',
            browser varchar(30) NOT NULL DEFAULT '',
            platform varchar(30) NOT NULL DEFAULT '',
            is_robot tinyint(1) NOT NULL DEFAULT 0,
            is_mobile tinyint(1) NOT NULL DEFAULT 0,
            previous_content longtext NOT NULL,
            new_content longtext NOT NULL,
            metadata longtext NOT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at varchar(60) NOT NULL DEFAULT '00-00-00 00:00:00.0000',
            updated_at varchar(60) NOT NULL DEFAULT '00-00-00 00:00:00.0000',
            PRIMARY KEY ( log_id ),
            KEY event_id ( event_id ),
            KEY event_slug ( event_slug ),
            $site_indexes 
            KEY object_id ( object_id ),
            KEY user_id ( user_id ),
            KEY severity ( severity ),
            KEY first_name ( first_name ),
            KEY last_name ( last_name ),
            KEY user_login ( user_login ),
            KEY user_role ( user_role ),
            KEY event_action ( event_action ),
            KEY event_title ( event_title ),
            KEY source_ip ( source_ip ),
            KEY browser ( browser ),
            KEY platform ( platform ),
            KEY is_mobile ( is_mobile ),
            KEY is_robot ( is_robot ),
            KEY is_read ( is_read ),
            KEY created_at ( created_at ),
            KEY updated_at ( updated_at ),
            KEY log_counter ( log_counter ),
            KEY log_status ( log_status ),
            KEY event_group ( event_group ),
            KEY event_action_trigger ( event_action_trigger ),
            FULLTEXT KEY user_and_object_search ( user_data, object_data ),
            FULLTEXT KEY user_search ( user_data ),
            FULLTEXT KEY object_search ( object_data ),
            FULLTEXT KEY metadata_search ( metadata ),
            FULLTEXT KEY message_search ( message )
        ) $this->collate;";

        // Settings
        $settings_table = $this->getTableName('settings');
        $tables[ $settings_table ] = "CREATE TABLE IF NOT EXISTS $settings_table ( 
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            $blog_column 
            option_name varchar(191) NOT NULL,
            option_value longtext NOT NULL,
            created_at varchar(60) NOT NULL DEFAULT '00-00-00 00:00:00.0000',
            updated_at varchar(60) NOT NULL DEFAULT '00-00-00 00:00:00.0000',
            PRIMARY KEY ( id ),
            $blog_index 
            KEY option_name ( option_name ),
            KEY created_at ( created_at ),
            KEY updated_at ( updated_at )
        ) $this->collate;";

        // File Monitor table
        $file_monitor = $this->getTableName('file_monitor');
        $tables[ $file_monitor ] = "CREATE TABLE IF NOT EXISTS $file_monitor ( 
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            dir_path text NOT NULL DEFAULT '',
            dir_group varchar(90) NOT NULL DEFAULT '',
            basename varchar(200) NOT NULL DEFAULT '',
            file_hash varchar(60) NOT NULL DEFAULT '',
            perm varchar(4) NOT NULL DEFAULT '',
            size bigint(30) NOT NULL DEFAULT 0,
            last_modified varchar(30) NOT NULL DEFAULT '',
            is_valid tinyint(1) NOT NULL DEFAULT 0,
            integrity_check tinyint(1) NOT NULL DEFAULT 0,
            file_info longtext NOT NULL,
            created_at varchar(60) NOT NULL DEFAULT '00-00-00 00:00:00.0000',
            updated_at varchar(60) NOT NULL DEFAULT '00-00-00 00:00:00.0000',
            PRIMARY KEY ( id ),
            KEY file_hash ( file_hash ),
            KEY is_valid ( is_valid ),
            KEY integrity_check ( integrity_check ),
            KEY perm ( perm ),
            KEY size ( size ),
            KEY basename ( basename ),
            KEY dir_group ( dir_group ),
            KEY last_modified ( last_modified ),
            KEY created_at ( created_at ),
            KEY updated_at ( updated_at ),
            FULLTEXT KEY file_search_index ( dir_path, file_info )
        ) $this->collate;";

        return $tables;
    }
}