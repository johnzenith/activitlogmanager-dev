<?php
namespace ALM\Controllers\Installer;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Class: Installer
 * Handles installation, activation, deactivation and uninstallation of plugin.
 * 
 * @package Installer
 * @since 1.0.0
 */

use \ALM\Controllers\Base\Templates as ALM_Base_Templates;

class Installer
{
	use ALM_Base_Templates\SettingsFactory,
		ALM_Base_Templates\BlogFactory,
		ALM_Base_Templates\FileUtility,
		\ALM\Models\Templates\DatabaseMetaData,
		\ALM\Models\Templates\DatabaseQueryMetaData,
		\ALM\Controllers\Installer\DB\TableSchema;

	/**
	 * Plugin Options
	 * @var array
	 * @since 1.0.0
	 */
	private $options = null;

	/**
	 * The current user ID
	 * @var int
	 */
	var $current_user_ID = 0;

	/**
	 * Constructor.
	 * Setup the Installer data
	 */
	public function __construct()
	{
		global $wp_version;

		$this->wp_version      = $wp_version;
		$this->php_version     = PHP_VERSION;
		$this->current_user_ID = get_current_user_id();

		// Setup the blog factory data
        $this->__setupBlogData();

		// Setup the database metadata
		$this->__setupDatabaseMetadata();
	}

	/**
	 * Dispatch installer processes
	 */
	public function dispatchHooks()
	{
		register_activation_hook(
			$this->plugin_file, [ $this, 'Activate' ]
		);
		register_deactivation_hook(
			$this->plugin_file, [ $this, 'Deactivate' ]
		);
		add_action(
			'plugins_loaded', [ $this, 'registerNewBlogInsertionHook' ]
		);
	}

	/**
	 * Activation Handler
	 */
	public function Activate()
	{
		if ( ! defined('ALM_NEW_BLOG_INSERTED') ) define('ALM_ACTIVATING', true);
		
		do_action( 'alm/activate' );
		
        // We are good citizens, if a user cannot activate plugins, just don't do it
        $this->userCanActivatePlugins();

        // Make sure the plugin is installed on a required WordPress version
		$this->activationHelperRequirement();
		
		// Run activation handlers
		$this->createDatabaseTables();

		if ( ! function_exists('alm_get_default_settings') )
			require_once( ALM_CORE_DIR . 'config/default-settings.php' );

		$this->__installGlobalSettings();
		$this->installDefaultOptions();
		
		do_action( 'alm/activated' );
		
        flush_rewrite_rules();
	}

	/**
	 * Only allow users with the necessary capabilities to activate the plugin
	 */
    private function userCanActivatePlugins()
    {
        if ( current_user_can( 'activate_plugins' ) ) return true;

        // Stop the activation process
        $message = alm__( 'Hi there! Looks like you are not allowed to do this, plugin activation could not be completed.' );

        $title = alm__( 'Plugin Activation Canceled' );
        $args  = [ 'back_link' => true ];

        wp_die( $message, $title, $args );
	}
	
	/**
	 * Only allow users with the necessary capabilities to deactivate the plugin
	 */
    private function userCanDeactivatePlugins()
    {
        if ( current_user_can( 'deactivate_plugins' ) ) return true;

        // Stop the deactivation process
        $message = alm__( 'Hi there! Looks like you are not allowed to do this, plugin deactivation could not be completed.' );

        $title = alm__( 'Plugin Deactivation Canceled' );
        $args  = [ 'back_link' => true ];

        wp_die( $message, $title, $args );
	}
	
	/**
	 * Check whether the current WP installation meets the plugin version requirements.
	 * PHP Version       : 5.6+
	 * WordPress Version : 5.0+
	 */
    private function hasRequiredVersion()
    {
        return ( 
			version_compare( $this->wp_version, ALM_MINIMUM_WP_VERSION, '>=' ) 
			&& version_compare( $this->php_version, ALM_MINIMUM_PHP_VERSION, '>=' )
		);
    }

    /**
     * Terminates the plugin activation process if version requirements failed
	 * 
     * @param   bool    $can_deactivate   Specifies whether the plugin should be deactivated
	 * 
     * @return  string 					  Returns 'yes' if the plugin installation requirement 
	 * 									  is okay. Otherwise false.
     */
    private function activationHelperRequirement( $can_deactivate = true )
    {
        if ( $this->hasRequiredVersion() ) return 'yes';

        if ( $can_deactivate ) $this->__forceDeactivation();

        $helper_title   = alm__( 'WordPress Installation is Incompatible' );
        $helper_args    = [ 'back_link' => true ];

		$helper_message = '<strong>'. $this->plugin_product_name . 
			sprintf( 
				alm_esc_html__( '%s requires WordPress %s+ and PHP %s+. Your current server setup is running WordPress %s and PHP %s.' ),
				ALM_VERSION,
				ALM_MINIMUM_WP_VERSION,
				ALM_MINIMUM_PHP_VERSION,
				$this->wp_version,
				$this->php_version
			) 
			.'</strong> ';

        $helper_message .= sprintf(
			alm__( 'Please follow this link to <a href="%s">Upgrade WordPress</a> to a current or supported version. <hr>Want to reach %s, feel free to contact our <a href="%s">Support Team</a>.' ),
			ALM_PLUGIN_PRODUCT_NAME,
            'https://codex.wordpress.org/Upgrading_WordPress',
            __alm_support_team_url()
        );

        wp_die( $helper_message, $helper_title, $helper_args );
	}

    private function installDefaultOptions()
    {

        $is_general_options_installed = (int) $this->getOption( 
			$this->option_name, 'is_general_options_installed'
		);

        if ( 1 === $is_general_options_installed ) return true;

        $this->options = alm_get_default_settings();

        // Set the general option state
		$this->options['is_general_options_installed'] = 1;
		
		// Save the plugin user doing the installation
		$this->options['plugin_admin_user'] = [
			'id' => $this->current_user_ID
		];

        // Save the option
        $this->addOption( $this->option_name, $this->options );
	}
	
	/**
	 * Install the plugin global settings. This is run just once.
	 */
	private function __installGlobalSettings()
	{
		if ( $this->globalOptionExists() ) return;
		
		$global_settings = alm_get_global_settings();

		// Save the plugin user doing the installation
		$global_settings['plugin_admin_user'] = [
			'id' => $this->current_user_ID
		];

		$global_settings_data = [
			'blog_id'      => $this->main_site_ID,
			'option_name'  => $this->__getGlobalOptionName(),
			'option_value' => $this->serialize( $global_settings ),
		];

		$this->wpdb->insert(
			$this->tables->settings,
			$global_settings_data
		);
	}

    /**
     * Provide plugin deactivation functionality
     */
    public function Deactivate()
    {
		define('ALM_DEACTIVATING', true );
		do_action( 'alm/deactivate' );
		
        // Make sure the user has what it takes to deactivate this plugin
        $this->userCanDeactivatePlugins();

        do_action( 'alm/deactivated' );
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstallation handler
     */
    public function Uninstall()
    {
        define( 'ALM_UNINSTALLING', true );

        $this->options 		  = $this->getOption( $this->option_name );
        $can_delete_settings  = 1 === (int) $this->options['can_delete_settings'];
        $can_delete_db_tables = 1 === (int) $this->options['can_delete_db_tables'];

        /**
         * Uninstall Hooks: 'alm/uninstall'. Fires before plugin is uninstalled.
         *
         * @since 1.0.0 
         *
         * @param bool $can_delete_settings  Whether or not plugin's settings should be deleted
         */
        do_action( 'alm/uninstall', $can_delete_settings, $can_delete_db_tables );

		if ( $can_delete_settings ) $this->deleteOption( $this->option_name );
		
        if ( $can_delete_db_tables ) $this->__deleteDBTables();

		/**
		 * Delete plugin custom tables
		 * @todo Delete the plugin custom tables
		 */

        flush_rewrite_rules();
	}

	/**
	 * Force plugin deactivation. Typically, this is used to ensure that the plugin is fully
	 * activated only when all the activation processes has been ran successfully.
	 * Normally called in failed plugin activation attempt.
	 */
	private function __forceDeactivation()
	{
		deactivate_plugins( $this->plugin_file, true, $this->is_multisite );
	}

	/**
	 * Delete the plugin database tables during uninstallation
	 */
	private function __deleteDBTables()
	{
		if ( ! is_array( $this->tables ) ) return;

		$tables 	= (array) $this->tables;
		$table_list = preg_replace( '/[\w ]+/', '', implode( ', ', $tables ) );

		$this->wpdb->query( "DROP TABLE IF EXISTS $table_list" );
	}

	/**
	 * New blog activation hook
	 */
	public function registerNewBlogInsertionHook()
	{
		// Run the activation process when a new blog is created on network
        add_action(
			'wp_insert_site',
			[ '\ALM\Controllers\Installer\Installer', 'RunOnNewBlogInsertion' ]
		);
	}

	/**
     * Create a wrapper around the 'Activate' method and run it when a new blog is created
     * @see \ALM\Controllers\Installer\Installer::Activation()
     */
    public function RunOnNewBlogInsertion( $site_args )
    {
        // Run it only when the plugin is active on network
        if ( ! $this->is_network_activation ) return false;

        // array( $new_site->id, $user_id, $new_site->domain, $new_site->path, $new_site->network_id, $meta )

        define( 'ALM_NEW_BLOG_INSERTED', true );

        switch_to_blog( $site_args->id );
        $this->Activation;
        restore_current_blog();
    }
}
