<?php
namespace ALM\Controllers\Admin\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Admin Menu Page Builder
 * @since 1.0.0 
 */

trait MenuPageBuilder
{
    /**
     * Specifies the capability required to access the plugin menu page
     * @var string
     * @since 1.0.0
     */
    private $menu_capability = 'manage_options';

    /**
     * Plugin top menu slug
     * @var string
     * @since 1.0.0
     */
    protected $top_menu_slug = 'alm';

    /**
     * Plugin sub-menus
     * @var array
     * @since 1.0.0
     */
    protected $sub_menus = [];

    /**
     * Setup the menu page builder
     */
    public function setupAdminMenus()
    {
        // Add the plugin menu to existing WordPress administration menus
        add_action( 'admin_menu', [ $this, 'create' ] );

        // Enable WordPress custom menu order
        add_filter( 'custom_menu_order', '__return_true', 101 );

        // Put the plugin menu first
        add_filter( 'menu_order', [ $this, 'changeMenuOrder' ], 101 );
    }

    /**
     * Change the WordPress administration menu order.
     * This will make the plugin menu to be shown immediately after the 
     * Dashboard menu on the list.
     * 
     * @see filter 'menu_order'
     */
    public function changeMenuOrder( $menu_order )
    {
        $plugin_menu_pos = (int) array_search( $this->top_menu_slug, $menu_order, true );
        $flip_menu_order = array_flip( $menu_order );
        
        // Before will unset the plugin menu position,
        // We must be sure that we are dealing with the plugin menu
        if ( $plugin_menu_pos 
        && isset( $flip_menu_order[ $this->top_menu_slug ] )  
        && $this->top_menu_slug === $menu_order[ $plugin_menu_pos ] )
        {
            unset( $menu_order[ $plugin_menu_pos ] );
        }
        
        // Unset the dashboard menu 'index.php'
        $dashboard_index = array_search( 'index.php', $menu_order, true );
        unset( $menu_order[ $dashboard_index ] );

        return array_merge( ['index.php'], [ $this->top_menu_slug ], $menu_order );
    }

    /**
     * Create the plugin menu pages
     */
    public function create()
    {
        $current_page_hook = ''; // for loading "load-$hook" action

        add_menu_page( 
            alm__( $this->plugin_product_name ),
            alm__( $this->plugin_product_name ),
            $this->menu_capability,
            $this->top_menu_slug,
            [ $this, 'renderMenuPage' ],
            $this->menuIcon()
        );

        // Set the sub-menu pages
        $this->sub_menus = apply_filters( 'alm/admin/menu/add', $this->getSubMenus() );

        foreach ( $this->sub_menus as $_sub_menu_slug => $sub_menu )
        {
            $callback      = isset( $sub_menu['callback'] ) ? $sub_menu['callback'] : '';
            $_callback     = is_callable( $callback ) ? $callback : function() {};

            $sub_menu_slug = preg_replace( "/[^a-z\-\_\=\&]/", '', $_sub_menu_slug );

            $submenu_page = add_submenu_page(
                $this->top_menu_slug,
                $sub_menu[ 'page_title' ],
                $sub_menu[ 'menu_title' ],
                $this->menu_capability,
                $sub_menu_slug,
                $_callback
            );

            // Make sure it is called only on the current menu page
            if ( $this->maybeIsMenuPage( $sub_menu_slug ) ) {
                $current_page_hook = $submenu_page;
            }
        }

        if ( ! empty( $current_page_hook ) ) {
            $this->screen_ID = $current_page_hook;

            add_action(
                "load-{$current_page_hook}",
                [ $this, 'menuPageHookLoader' ]
            );
        }
    }

    /**
     * Load actions when in plugin menu page
     */
    public function menuPageHookLoader()
    {
        /**
         * Trigger the plugin admin init action hook
         */
        do_action( 'alm/admin/init' );

        // Add help tabs
        $this->addHelpTabs();

        // Admin notices
        add_action( 'admin_notices', [ $this, 'addAdminNotices' ]);
        add_action( 'admin_enqueue_scripts', [ $this, 'loadScripts' ]);

        // Fires after menu page is loaded
        do_action( 'alm/admin/menu/loaded' );
    }

    /**
     * Get the plugin submenus list
     * @return array
     */
    public function getSubMenus()
    {
        return [
            $this->top_menu_slug => [
                'page_title' => alm__( 'Log Viewer' ),
                'menu_title' => alm__( 'Log Viewer' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_logged_in_users' => [
                'page_title' => alm__( 'Logged In Users' ),
                'menu_title' => alm__( 'Logged In Users' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_settings' => [
                'page_title' => alm__( 'Settings' ),
                'menu_title' => alm__( 'Settings' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_reports' => [
                'page_title' => alm__( 'Reports' ),
                'menu_title' => alm__( 'Reports' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_metrics' => [
                'page_title' => alm__( 'Metrics' ),
                'menu_title' => alm__( 'Metrics' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_my_account' => [
                'page_title' => alm__( 'My Account' ),
                'menu_title' => alm__( 'My Account' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_get_started' => [
                'page_title' => alm__( 'Get Started' ),
                'menu_title' => alm__( 'Get Started' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_help' => [
                'page_title' => alm__( 'Get Help' ),
                'menu_title' => alm__( 'Get Help' ),
                'callback'   => '',
            ],

            $this->top_menu_slug . '_contact' => [
                'page_title' => alm__( 'Contact' ),
                'menu_title' => alm__( 'Contact' ),
                'callback'   => '',
            ],
        ];
    }

    /**
     * Get the page query var
     * @return string
     */
    public function getPageVar()
    {
        return sanitize_key( $_GET['page'] );
    }

    /**
     * Check whether a given plugin menu slug is currently active
     * 
     * @param  string $menu_slug  Specifies the menu slug check for
     * 
     * @return bool               True if the plugin menu slug (page) is currently active.
     *                            Otherwise false.
     */
    public function maybeIsMenuPage( $menu_slug = '' )
    {
        if ( empty( $menu_slug ) || ! $this->isPluginMenuPage() ) {
            return false;
        }
 
        $page = $this->getPageVar();
        return strcmp( $menu_slug, $page ) === 0;
    }

    /**
     * Check whether the plugin menu page hook is active
     * @return bool
     */
    public function hasMenuPageHook()
    {
        if ( ! $this->isPluginMenuPage() ) return '';

        $hook_suffix   = $this->getPageHookSuffix();
        $page_hook     = stackauth_admin_get_menu_page_hook();
        $is_page_valid = strcmp( $hook_suffix, $page_hook ) === 0;

        if ( ! $is_page_valid ) return '';

        return $page_hook;
    }

    /**
     * Set the plugin menu icon
     */
    protected function menuIcon()
    {
        return ' dashicons-money';
    }
    
    /**
     * Check whether the specified page is path of the plugin menu pages
     * 
     * @param  string $page The menu page name to check for.
     * @return bool 	    True if page exists. False otherwise.    
     */
    public function isPluginMenuPage( $page = '' )
    {
        if ( ! isset( $_GET['page'] ) && empty( $page ) ) return false;

        $current_page = ( empty( $page ) ) ? $_GET['page'] : $page;
        $current_page = sanitize_key( $current_page );
        $menu_pages   = array_keys( $this->sub_menus );

        if ( ! in_array( $current_page, $menu_pages, true ) ) return false;
        
        return true;
    }

    /**
     * Get the current page hook suffix (Screen ID)
     * @return string
     */
    public function getPageHookSuffix()
    {
        return get_current_screen()->id;
    }

    /**
     * Get the menu page hook suffix.
     * 
     * @param  string $menu_page  The menu page name as passed to the 'alm/admin/menu/add' 
     *                            filter hook
     * 
     * @return string             The menu page hook suffix on success.
     *                            Empty string is returned on failure.
     */
    public function getMenuPageHook( $menu_page = '' )
    {
        if ( ! $this->isPluginMenuPage( $menu_page ) ) return '';

        $hook_suffix = $this->getPageHookSuffix();

        $page_hook   = empty( trim( $menu_page ) ) ? $_GET['page'] : $menu_page;
        $page_hook   = sanitize_key( $page_hook );

        // Test for the top level menu page
        if ( 0 === strpos( $hook_suffix, 'toplevel_page' ) 
        && 0 === strcmp( $this->top_menu_slug, $page_hook ) )
        {
            $page_hook = 'toplevel_page_' . $this->top_menu_slug;
        }
        else {
            /**
             * Test for sub menu pages
             * @todo 
             * var_dump() and inspect this
             */
            $page_hook = $this->top_menu_slug . '_page_' . $page_hook;
        }
        return $page_hook;
    }

    /**
     * Get screen ID or page hook suffix from menu page names.
     *
     * @param array $menu_page_names  An array of menu page names to get the corresponding  
     *                                screen ID or page hook suffix for.
     * 
     * @return array An array containing the corresponding screen IDs or menu page hooks.
     */
    public function getScreenIdFromMenuPageName( array $menu_page_names = [] )
    {
        $screen_ids = [];

        foreach ( $menu_page_names as $menu_page_index => $menu_page_name )
        {
            $screen_id = $this->getMenuPageHook( $menu_page_name );

            if ( empty( $screen_id ) ) {
                continue;
            } else {
                $screen_ids[ $menu_page_name ] = $screen_id;
            }
        }
        return $screen_ids;
    }

    /**
     * Check whether the top level menu page is active
     * @return bool
     */
    public function isTopLevelMenuPage()
    {
        if ( ! $this->isPluginMenuPage() ) return false;

        $top_level_page_slug = 'toplevel_page_' . $this->top_menu_slug;

        $is_top_level_page = ( 
            strcmp( $top_level_page_slug, $this->getPageHookSuffix() ) == 0 
            && strcmp( $this->top_menu_slug, $this->getPageVar() == 0 )
        );

        return $is_top_level_page;
    }

    /**
     * Check if the admin nonce settings is valid
     * @param bool  
     */
    public function isSettingsNonceValid( $nonce = 'alm-update-settings' )
    {
        $check_nonce = check_admin_referer( $nonce, 'update_settings_nonce' );
        return $check_nonce;
    }

    /**
     * Display the menu page
     */
    public function renderMenuPage()
    {

    }

    /**
     * Help Tabs
     */
    protected function addHelpTabs()
    {
        $menu_pages   = array_keys( $this->sub_menus );
        $screen       = get_current_screen();
        $help_tab_id  = $this->getPageVar();

        foreach ( $menu_pages as $menu_slug => $menu )
        {
            switch ( $help_tab_id )
            {
                case "$help_tab_id":
                case 'alm': // Check for parent page
                    $help_text = '<p>'. alm__( 'Unique Content' ) .'</p>';
                    break;
                
                default:
                    $help_text = '<p>'. alm__( 'Help tab for the review' ) .'</p>';
                    break;
            }
        }

        $screen->add_help_tab([
            'id'      => $help_tab_id,
            'title'   => alm__( 'Help Tab' ),
            'content' => $help_text,
        ]);

        $screen->set_help_sidebar( $help_text );

        $screen->set_help_sidebar(
            '<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
            '<p>' . __( '<a href="https://wordpress.org/support/article/managing-plugins/">Documentation on Managing Plugins</a>' ) . '</p>' .
            '<p>' . __( '<a href="https://wordpress.org/support/">Support</a>' ) . '</p>'
        );

        $screen->set_screen_reader_content([
            'heading_views'      => __( 'Filter plugins list' ),
            'heading_pagination' => __( 'Plugins list navigation' ),
            'heading_list'       => __( 'Plugins list' ),
        ]);

    }

    /**
     * Load scripts
     */
    public function loadScripts()
    {
        $hook_suffix = $this->getPageHookSuffix();

        // Load the post scripts [Cached Version]
        // wp_enqueue_script('post');
        wp_enqueue_script( 'common' );
        wp_enqueue_script( 'wp-lists' );
        wp_enqueue_script( 'postbox' );

        wp_enqueue_script(
            'alm_main_script',
            ALM_ASSETS_URL . 'js/admin.js',
            [ 'jquery', 'wp-color-picker' ],
            fileatime( ALM_ASSETS_DIR . 'js/admin.js' ),
            true
        );

        $ajax_file = '/admin-ajax.php';
        $ajax_url  = $this->is_multisite ? network_admin_url( $ajax_file ) : admin_url( $ajax_file );

        // Admin script localization
        $script_args                          = [];
        $script_args['ajax_url']              = esc_url( $ajax_url );
        $script_args['admin_url']             = esc_url( home_url( '/wp-admin/' ));
        $script_args['plugin_name']           = $this->plugin_product_name;
        $script_args['page_hook_suffix']      = $hook_suffix;

        $script_args['unsaved_settings_text'] = alm__('You have unsaved changes, the data you have entered maybe lost if you leave this page.' );

        wp_localize_script( 'alm_main_script', 'ALM_ADMIN', $script_args );

        // Add the color picker css file       
        wp_enqueue_style( 'wp-color-picker' );

        wp_enqueue_style(
            'alm_admin_stylesheet',
            ALM_ASSETS_URL . 'css/admin-styles.css',
            [],
            fileatime( ALM_ASSETS_DIR . 'css/admin-styles.css' ),
            false
        );
    }

    /**
     * Admin notices
     */
    public function addAdminNotices()
    {
        
    }
}