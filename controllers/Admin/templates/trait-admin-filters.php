<?php
namespace ALM\Controllers\Admin\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Admin Filters Handler
 * @since 1.0.0 
 */

trait AdminFilters
{
    /**
     * Register Admin Filters
     */
    public function registerAdminFilters()
    {
        // Add action links in the plugin activation menu
        add_filter( $this->getPluginActionLinkFilter(), [ $this, 'actionLinks' ], 10, 4 );

        add_filter( 'set-screen-option', [ $this, 'setScreenOptions' ], 10, 3 );
        add_filter( 'admin_footer_text', [ $this, 'rateUsFooterText' ] );
        add_filter( 'screen_settings',   [ $this, 'addScreenOptions' ], 10, 2 );
    }

    /**
     * Get plugin action link filter to use
     */
    protected function getPluginActionLinkFilter()
    {
        $network_admin_prefix = $this->is_network_admin ? 'network_admin_' : '';
        return $network_admin_prefix .'plugin_action_links_'. $this->plugin_file_name;
    }

    /**
     * Add action links in the WordPress plugin list
     */
    public function actionLinks( $links )
    {
        $created_links = [
            $this->top_menu_slug => sprintf(
                '<a href="%1$s">%2$s</a>',
                $this->getAdminPageUrl( $this->top_menu_slug, [], true, true ),
                alm__('Log Viewer')
            ),
            'settings' => sprintf(
                '<a href="%1$s">%2$s</a>',
                $this->getAdminPageUrl( $this->top_menu_slug . '_settings', [], true, true ),
                alm__( 'Settings' )
            ),
            'upgrade' => sprintf(
                '<a href="%1$s">%2$s</a>',
                '#',
                alm__( 'Upgrade' )
                
            ),
        ];

        // Add the created links to existing ones 
        $links = wp_parse_args( $links, $created_links );
        return $links;
    }

    /**
     * Set screen options
     */
    public function setScreenOptions( $status, $option, $value )
    {
        return true;
    }

    /**
     * Rate Us
     */
    public function rateUsFooterText( $footer_text = '' )
    {
        if ( ! $this->isPluginMenuPage() ) return $footer_text;

        $text = sprintf(
            alm__( 'Enjoyed <strong>%1$s</strong>? Please leave us a <a href="%2$s" target="_blank">★★★★★</a> rating. We are thankful and really appreciate your awesome support %3$s.' ),
            $this->plugin_product_name,
            __alm_plugin_site_url( '/review/' ),
            '<span class="dashicons dashicons-heart"></span>'

        );
        return '<span class="alm-admin-footer-text">' . $text . '</span>';
    }
    
    /**
     * Add plugin specific screen option
     */
    public function addScreenOptions( $screen_options, $screen )
    {
        return $screen_options;
    }
}