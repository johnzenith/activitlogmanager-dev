<?php
namespace ALM\Controllers\Admin\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Admin Actions Handler
 * @since 1.0.0 
 */
trait AdminActions
{
    /**
     * Register Admin Actions
     */
    public function registerAdminActions()
    {
        add_action('current_screen', [$this, 'customizeCurrentScreenOptions']);
    }
    
    /**
     * Make it possible to add customized content to screen options
     */
    public function customizeCurrentScreenOptions( $screen )
    {
        // Only add the 'Display Box' screen option on the plugin menu page
        if (!$this->isPluginMenuPage()) return false;

        /**
         * Add plugin screen options
         */
        $screen->add_option( 'columns', [
            'default' => 'first,second,third',
            'label'   => alm__('Toggle Audit Log Columns'),
            'options' => 'audit_log_columns_' . $this->top_menu_slug,
        ]);
    }
}