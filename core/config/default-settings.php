<?php
// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package     Activity Log Manager
 * @subpackage  Default Settings
 * @since       Version 1.0.0
 */

/**
 * Get the plugin default running modes
 * @return array
 */
function alm_get_default_running_modes()
{
	return [
		'normal'  => 1,
		'super'   => 1,
		'stealth' => 1,
	];
}

/**
 * Correctly parse custom modes and default modes together
 * 
 * @param array $custom_modes Specifies list of custom modes to parse into the default 
 * 							  running modes
 * 
 * @return array The parsed running modes
 */
function alm_parse_running_modes( array $custom_modes = [] )
{
	/**
     * Custom mode limit is set to 3,
     * this will never change due to data length integrity.
     * 
     * Only retrieve the top three custom mode if by chance it exceeds the custom mode limit.
     */
	$splice_modes        = [];
    $count_modes         = count( $custom_modes );
    $default_modes       = alm_get_default_running_modes();
    $count_default_modes = count( $default_modes );
    
    // Total modes = default mode +  3 custom modes
    $total_modes = ALM_CUSTOM_MODE_LIMIT + $count_default_modes;
    
    if ( ($count_modes + $count_default_modes) > $total_modes )
    {
        $count = 0;
        foreach ( $custom_modes as $m => $v )
        {
            // Ignore default modes
            if (isset( $default_modes[ $m ] )) {
                $splice_modes[ $m ] = $v;
            }
            else {
                if ($count >= 3) break;

                $splice_modes[ $m ] = $v;
                ++$count;
            }
		}
	}
	else {
		foreach ( $custom_modes as $m => $v ) {
			$splice_modes[ $m ] = $v;
		}
	}

    return array_merge( $default_modes, $splice_modes );
}

/**
 * Set and return the plugin running modes
 * 
 * @see alm_parse_running_modes()
 * 
 * @param array  $modes  Specifies the running modes to update
 * @param mixed  $value  Specifies the the running modes values
 * 
 * @return array List of updated running modes for the plugins
 */
function alm_set_running_modes( array $modes = [], $value = 1 )
{
    $updated_modes = [];
	$running_modes = alm_parse_running_modes( $modes );
    
    foreach ( $running_modes as $mode => $ignore_val )
    {
        $use_value = is_null( $value ) ? $ignore_val : $value;
        $updated_modes[ $mode ] = $use_value;
    }
    
    return $updated_modes;
}

/**
 * Get the plugin global settings
 * @return array List of plugin global settings
 */
function alm_get_global_settings()
{
	$settings = [];

	$settings['version']    = ALM_VERSION;
	$settings['db_version'] = ALM_DB_VERSION;

	// Set the user id that installed the plugin
	$settings['plugin_admin_user'] = [];

	// Specifies the running mode for the plugin (what mode to run the plugin on)
	$settings['running_modes'] = alm_set_running_modes();

	// List of running modes default behaviors
	$settings['mode_default_behaviors'] = [
		'hide_plugin'		     => 0,
		'suspend_notifications'  => 0,
		'suppress_admin_notices' => 0,
	];

	// Specifies the plugin current running mode
	$settings['network_running_mode'] = ALM_DEFAULT_RUNNING_MODE;

	// Specifies whether to allow other site administrators or plugin editors 
	// To control the blog specific running mode.
	// This can either be 'strict', 'flexible' or 'off'

	//  'off'     : Indicates that mode cannot be change by other site admins and plugin editors
	// 				This will also allow the admin to decide what mode to set on all other sites.
	//  'strict'  : Indicates that the global mode controls all other site modes.
	//  'flexible': Indicates that site administrator and plugin editors can modify blog mode
	$settings['running_mode_state'] = alm_set_running_modes([], 'strict');

	// This is used only when the 'running_mode_state' is set to 'off'
	// It will replace blog specific 'blog_running_mode' setting
	$settings['force_blog_running_mode'] = alm_set_running_modes([], ALM_DEFAULT_RUNNING_MODE);

	// Specifies the maximum number of custom running modes to create
	$settings['max_custom_mode_limit'] = ALM_CUSTOM_MODE_LIMIT;

	// Specifies whether to enable or disable the file monitor
	$settings['file_monitor'] = alm_set_running_modes();

	// Specifies whether or not to explain the event message
	$settings['explain_event_msg'] = alm_set_running_modes([], 0);

	if ( is_multisite() )
	{
		// Specify whether or not we can update other blogs on the network
		// with the current blog settings
		$settings['update_across_network'] = alm_set_running_modes([], 0);
	}

	return $settings;
}

/**
 * Activity Log Manager Default settings
 * 
 * Values are are mapped to the plugin running modes like so:
 * 
 * 		'example_value' => [
 * 			'normal'  => 1,
 * 			'super'  => 1,
 * 			'stealth' => 0,
 * 		];
 * 
 * 		The above 'example_value' will be 1 when the plugin is on 'normal' or 'super'.
 * 		On [stealth mode], the 'example_value' will be 0. That's the logic!
 * 
 * @return array List of plugin settings
 */
function alm_get_default_settings()
{
	$settings     = [];
	$is_multisite = function_exists( 'is_multisite' ) && is_multisite();

	// Specifies whether or not to delete all plugin data when uninstalling
	$settings['can_delete_db_tables'] = 0;

	// Specifies whether the settings is refreshed (reset)
	$settings['is_settings_refreshed'] = 0;

	// Set the plugin user for specific blogs
	$settings['plugin_admin_user'] = [];

	// Specifies whether internal IP address can be filter or not
	// If enabled, then private and reserve IP ranges will be filtered too
	$settings['filter_internal_ip'] = alm_set_running_modes();

	// Specifies whether reverse-proxy/firewall fix should be apply for IP address
	$settings['reverse_proxy_fix'] = alm_set_running_modes();

	// Specifies whether to log the referer
	$settings['log_referer'] = alm_set_running_modes();

	// Specifies the plugin current running mode
	// This is determine by the super admin
	// Also, super admin can decide whether to allow administrators or plugin editors
	// To change the running mode, or control it globally.
	$settings['blog_running_mode'] = ALM_DEFAULT_RUNNING_MODE;

	// Specifies custom running mode for the plugin
	// this will be merged into the plugin running modes
	// Note: array keys are used as the mode slug, so numerical keys are not ideal.
	$settings['custom_modes'] = [];

	// Specifies whether PHP errors logging is enabled
	$settings['php_errors'] = alm_set_running_modes();

	// Specifies whether php errors backtrace is enabled
	$settings['php_errors_backtrace'] = alm_set_running_modes();

	// Specifies whether the failed login attempted password should be logged
	$settings['log_failed_login_attempted_password'] = alm_set_running_modes();

	// Specifies whether to aggregate log or not
	$settings['log_aggregation'] = alm_set_running_modes();

	// Specifies whether to allow verbose logging or not
	// However, some event object log cannot be simplified.
	// For example, the posts, comments, terms, taxomies events are logged in 
	// verbose form.
	$settings['verbose_logging'] = alm_set_running_modes([]);

	// Specifies whether error pages request logging is enabled
	$settings['error_pages'] = alm_set_running_modes();

	// Specifies whether dashboard widgets are enabled
	$settings['log_dashboard_widgets'] = alm_set_running_modes();

	// Specifies whether to log menus activities
	$settings['log_menus'] = alm_set_running_modes();

	// Specifies the maximum logs to display on the admin dashboard widgets
	$settings['dashboard_widgets_max_log'] = alm_set_running_modes();

	// Specifies whether real time admin notification is turned on
	// Auto disable real time notification in stealth mode
	$settings['admin_real_time_notification'] = alm_set_running_modes(
		[ 'stealth' => 0 ],
		null
	);

	/**
	 * Specifies the number of days to update the error log counter 
	 * for a given event before creating a new one.
	 * 
	 * For example, if set to 30, it simply means that the failed event log will be 
	 * incremented within the last 30 days.
	 * 
	 * Note: The failed error log increment for a given event is cleared whenever the 
	 * event successor for that failed event is logged by the user/ip that triggers it.
	 * 
	 * Default: 0 (Disabled. This means the log will be incremented until the event 
	 * 			  successor for that failed event is logged by the user/ip that triggers it).
	 */
	$settings['failed_event_log_increment_limit'] = alm_set_running_modes( [], 0 );

	// Specifies the maximum logs to keep
	$log_max = 10000;
	$settings['log_max'] = alm_set_running_modes(
		[], $log_max
	);

	// Specifies the default log auto prune interval
	$prune_unit   = 6;
	$prune_period = 'months';
	$settings['log_pruning'] 	    = alm_set_running_modes();
	$settings['log_pruning_limit']  = alm_set_running_modes( [], $prune_unit );
	$settings['log_pruning_unit']   = alm_set_running_modes( [], $log_max );
	$settings['log_pruning_period'] = alm_set_running_modes( [], $prune_period );

	// Specifies the next auto prune date
	$prune_interval = $prune_unit .' '. $prune_period;
	$prune_date 	= new \DateTime( $prune_interval );

	$settings['log_prune_next_date'] = alm_set_running_modes(
		[], $prune_date->format('Y-m-d H:i:s')
	);

	/**
	 * Specifies excluded events (logs)
	 */
	$settings['log_excluded_urls'] 			= alm_set_running_modes([], '');

	$settings['log_excluded_event_ids'] 	= alm_set_running_modes([], '');

	$settings['log_excluded_term_ids'] 	    = alm_set_running_modes([], '');
	$settings['log_excluded_term_slugs'] 	= alm_set_running_modes([], '');
	$settings['log_excluded_term_names'] 	= alm_set_running_modes([], '');

	$settings['log_excluded_taxonomies'] 	= alm_set_running_modes([], '');
	$settings['log_excluded_taxonomy_ids'] 	= alm_set_running_modes([], '');

	$settings['log_excluded_object_ids']	= alm_set_running_modes([], '');
	$settings['log_excluded_object_slugs']	= alm_set_running_modes([], '');

	$settings['log_excluded_post_ids'] 	 	= alm_set_running_modes([], '');
	$settings['log_excluded_post_names'] 	= alm_set_running_modes([], '');
	$settings['log_excluded_post_types'] 	= alm_set_running_modes([], '');

	$settings['log_excluded_link_ids']   	= alm_set_running_modes([], '');

	$settings['log_excluded_user_ids'] 	    = alm_set_running_modes([], '');
	$settings['log_excluded_user_roles'] 	= alm_set_running_modes([], '');
	$settings['log_excluded_user_logins'] 	= alm_set_running_modes([], '');
	$settings['log_excluded_user_emails'] 	= alm_set_running_modes([], '');

	$settings['log_excluded_client_ips'] 	= alm_set_running_modes([], '');

	// Running modes to exclude
	$settings['log_excluded_running_modes'] = alm_set_running_modes([], '');

	// Error pages like 404, 403, 401, etc.
	$settings['log_excluded_error_pages'] 	= alm_set_running_modes([], '');

	// List of third party plugins to exclude from log
	$settings['log_excluded_third_party_plugins'] = alm_set_running_modes([], '');


	/**
	 * Specifies event ID or event name notifications to disable
	 */
	$settings['log_notification_excluded_event_ids']  = alm_set_running_modes([], [
		'sms'   => [],
		'email' => [],
	]);

	/**
	 * Suspend all notifications
	 */
	$settings['log_notification_suspend_all'] = alm_set_running_modes([], 0);

	// Whether the real time notification popup should be disable when the 
	// notification is disabled as well
	$settings['log_notification_excluded_affect_real_time_popup'] = alm_set_running_modes([], []);


	/**
	 * Specifies the generated log limit for the error page codes
	 */
	$error_page_limit = [];
	$error_page_codes = [ 403, 404, 405, 408, 500, 502, 504 ];

	foreach ( $error_page_codes as $code ) {
		$error_page_limit[ $code ] = 15;
	}

	$settings['log_error_page_limit'] = alm_set_running_modes(
		[], $error_page_limit
	);


	/**
	 * Restrict Access
	 */

	// Specifies whether to only allow the admin user or allow other users as well
	// 'only_me' will restrict every other users
	// 'custom' will enable you to add other users
	$settings['log_viewer_mode']	    =  alm_set_running_modes(
		[], 'custom'
	);

	$settings['log_viewer_user_ids']    =  alm_set_running_modes([], '');
	$settings['log_viewer_client_ips']  =  alm_set_running_modes([], '');
	$settings['log_viewer_user_roles']  =  alm_set_running_modes([], '');
	$settings['log_viewer_user_logins'] =  alm_set_running_modes([], '');
	$settings['log_viewer_user_emails'] =  alm_set_running_modes([], '');

	// Works like the 'log_viewer_mode'
	$settings['log_editor_mode']	    =  alm_set_running_modes(
		[], 'custom'
	);
	$settings['log_editor_user_ids'] 	=  alm_set_running_modes([], '');
	$settings['log_editor_client_ips'] 	=  alm_set_running_modes([], '');
	$settings['log_editor_user_roles'] 	=  alm_set_running_modes([], '');
	$settings['log_editor_user_logins'] =  alm_set_running_modes([], '');
	$settings['log_editor_user_emails'] =  alm_set_running_modes([], '');

	/**
	 * Core settings
	 */
	$settings['log_core_events']   = alm_set_running_modes();
	$settings['log_core_settings'] = alm_set_running_modes();

	/**
	 * Comments
	 */
	$settings['log_comments'] = alm_set_running_modes();

	/**
	 * Database Events
	 */
	$settings['log_db_events'] = alm_set_running_modes();

	/**
	 * Log custom fields
	 */
	$settings['log_custom_fields'] = alm_set_running_modes();

	/**
	 * User Login Page
	 */
	$settings['log_user_login_page'] = alm_set_running_modes();

	/**
	 * User Registration Page
	 */
	$settings['log_user_signup_page'] = alm_set_running_modes();

	/**
	 * User Password Recovery Page
	 */
	$settings['log_password_recovery_page'] = alm_set_running_modes();

	/**
	 * User Profile Page
	 */
	$settings['log_user_profile'] = alm_set_running_modes();

	/**
	 * Log terms, taxonomies
	 */
	$settings['log_terms']    = alm_set_running_modes();
	$settings['log_taxonomies'] = alm_set_running_modes();

	/**
	 * WordPress Media Uploader
	 */
	$settings['log_media_uploader'] = alm_set_running_modes();

	/**
	 * Log Themes and Plugins Events
	 */
	$settings['log_theme_events']  = alm_set_running_modes();
	$settings['log_plugin_events'] = alm_set_running_modes();

	/**
	 * Blok editor and Post editor
	 */
	$settings['log_post_editor']  = alm_set_running_modes();
	$settings['log_block_editor'] = alm_set_running_modes();

	/**
	 * Bot and Crawlers
	 */
	$settings['log_bot_and_crawlers'] = alm_set_running_modes();

	/**
	 * Session Management
	 */
	$settings['sessions_management'] = alm_set_running_modes([], [
		'auto_logout_email'    => 0, // Whether to send the user an email after logging them out
		'sessions_per_user'    => 0, // Whether to enable multiple login sessions for users
		'live_sessions_update' => 1, // Whether to use AJAX to update the sessions list
	]);

	/**
	 * Date and Time settings
	 */
	$settings['timezone']    = alm_set_running_modes([], '');
	$settings['date_format'] = alm_set_running_modes([], '');
	$settings['time_format'] = alm_set_running_modes([], '');

	/**
	 * Third party plugin
	 */
	$settings['third_party_plugins'] = alm_set_running_modes();

	// Specifies list of client IP Addresses to blacklist due to spam related traffic
	// from the IP Address
	$settings['blacklist_ips']	       = alm_set_running_modes([], '');
	$settings['blacklist_ip_settings'] = alm_set_running_modes([], [
		'lock_limit'  => 0, // 0 means forever
		'lock_period' => 'months', // days, months, years
	]);

	// Multisite global settings
	if ( $is_multisite )
	{
	    // When set to 1 on any blog, then the blog will ignore the 
	    // $settings['update_across_network'] option
		$settings['ignore_network_update'] = alm_set_running_modes([], 0);

		// Log network changes
		$settings['log_network_changes'] = alm_set_running_modes([], 1);
	}

	return $settings;
}