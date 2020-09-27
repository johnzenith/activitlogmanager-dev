<?php
namespace ALM\Controllers\Admin;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Class: Admin Manager
 * @since 1.0.0
 */

use \ALM\Controllers\Admin\Traits as ALM_AdminTraits;

class AdminManager extends \ALM\Controllers\Base\PluginFactory 
{
    use ALM_AdminTraits\MenuPageBuilder,
        ALM_AdminTraits\AdminFilters,
        ALM_AdminTraits\AdminActions;

    /**
     * Specifies list of admin user ID as keys and user login names as values
     * @var array|null
     * @since 1.0.0
     */
    protected $admin_list = null;

    /**
     * Setup the Admin Manager class. This is called in the PluginFactory Base Controller
     */
    public function __runSetup()
    {
        $this->is_setup_fired = true;

        // Setup the admin menu
        $this->setupAdminMenus();

        // Register admin plugin filters
        $this->registerAdminFilters();

        // Register admin plugin actions
        $this->registerAdminActions();
    }

    /**
     * Get the admin page url with the current url query arguments
     *
     * @since  1.1.0
     * 
     * @param string  $page        The page slug
     * @param array   $extra_args  An associative array of queries to add to the existing page
     * @param bool    $escape_url  Whether the created page url should be escaped
     * @param bool    $admin_url   Whether to use admin_url() even when on network
     * 
     * @return string The created page url
     */
    public function getAdminPageUrl(
        $page       = null, 
        $extra_args = [],
        $escape_url = true,
        $admin_url  = false
    )
    {
        if ( empty( $page ) && isset( $_GET['page'] ) ) {
            $page_args = [];
            wp_parse_str( $_SERVER['QUERY_STRING'], $page_args );
        }
        else {
            $page_args = [ 'page' => $page ];
        }

        $page_args  = array_merge( $extra_args, (array) $page_args );

        if ( is_network_admin() && !$admin_url ) {
            $admin_url = network_admin_url( 'admin.php' );
        } else {
            $admin_url = admin_url( 'admin.php' );
        }

        $create_url = add_query_arg(
            $page_args, $admin_url
        );

        if ( $escape_url ) {
            $create_url = esc_url( $create_url );
        }

        return $create_url;
    }

    /**
	 * Get list of admin user login names
     * @return array
	 */
    protected function getAdmins()
    {
        if ( ! is_null( $this->admin_list ) ) return $this->admin_list;

        $this->admin_list = [];

        if ( ! $this->is_multisite )
        {
            $data = get_users([
                'role'   => 'administrator',
                'fields' => [ 'user_login' ]
            ]);
            
            foreach ( $data as $admin ) {
                $this->admin_list[ $admin->ID ] = $admin->user_login;
            }
            return $this->admin_list;
        }

        $cap_key        = $this->getBlogPrefix() . 'capabilities';
        $user_table     = $this->wpdb->users;
        $usermeta_table = $this->wpdb->usermeta;

        $this->admin_list = $this->wpdb->get_col(
            "
                SELECT DISTINCT CAST( u.user_login AS CHAR ) 
                FROM $user_table AS u 
                INNER JOIN $usermeta_table AS um 
                ON u.ID = um.user_id 
                WHERE um.meta_key = '$cap_key' 
                AND CAST( um.meta_value AS CHAR ) LIKE '%administrator%' 
            "
        );
    }

    /**
     * Get the admin screen columns
     * @return int
     */
    public function getScreenColumns()
    {
        return (int) get_current_screen()->get_columns();
    }

    /**
     * Get the current screen pagination option name (screen per page option name)
     * @return string
     */
    public function getScreenPaginationOptionName()
    {
        return $this->getMenuPageHook() . '_per_page';
    }

    /**
     * Admin notices markup
     *
     * @param  string  $notice_text     Specifies the notice text to display.
     * 
     * @param  string  $type            Specifies the type of notice to display.
     *                                  Accepts: 'success' | 'warning' | 'error'.
     * 
     * @param  string  $add_text_class  Specifies css classes to use to format the notice text.
     * 
     * @return string                   The formatted admin notice text markup
     */
    public function noticeMarkup( $notice_text = '', $type = 'warning', $add_text_class = '' )
    {
        // Escape the notice text
        $notice_text = wp_kses(
            $notice_text,
            [
                '<hr>'   => [ 'class' => [], 'style' => [] ],
                'span'   => [ 'class' => [], 'style' => [] ],
                'p'      => [ 'class' => [], 'style' => [] ],
                'strong' => [ 'class' => [], 'style' => [] ],
                'small'  => [ 'class' => [], 'style' => [] ],
                'em'     => [ 'class' => [], 'style' => [] ],
                'u'      => [ 'class' => [], 'style' => [] ],
                'a'      => [ 'href'  => [], 'class' => [], 'style' => [], 'title' => [] ]
            ]
        );

        switch ( $type )
        {
            case 'success':
            case 'updated':
                $notice_class = 'notice-success';
                break;

            case 'error':
            case 'failed':
                $notice_class = 'notice-error';
                break;
            
            default:
                $notice_class = 'notice-warning';
                break;
        }

        $notice_class      .= ' notice is-dismissible alm-admin-notices';
        $notice_id          = 'alm-notice-' . uniqid();

        $notices_text_class = ( empty( $add_text_class ) ) ? 'text-wrapper' : 'text-wrapper '. ltrim( $add_text_class );

        $notice_text_markup = '<div id="'. esc_attr( $notice_id ) .'" class="'. esc_attr( $notice_class ) .'"><p class="'. esc_attr( $notices_text_class ) .'">'. $notice_text .'</p></div>';

        return $notice_text_markup;
    }

    /**
     * Inline notice text markup
     * 
     * @param  string  $notice_text  The notice text to display.
     * 
     * @param  string  $type         The type of notice to display.
     *                               Accepts: 'success' | 'warning' | 'error'.
     * 
     * @return string The formatted notice text markup
     */
    public function noticeMarkupInline( $notice_text = '', $type = 'warning' )
    {
        if ( empty( $notice_text ) ) return '';

        // Escape the notice text
        $notice_text = wp_kses(
            $notice_text,
            [
                'span'   => [ 'style' => [] ],
                'p'      => [ 'style' => [] ],
                'strong' => [ 'style' => [] ],
                'em'     => [ 'style' => [] ],
                'a'      => [ 'href' => [], 'class' => [], 'style' => [], 'title' => [] ]
            ]
        );

        // Set the notice class
        switch ( $type ) {
            case 'success':
                $notice_class = 'notice-success';
                break;
            case 'error':
                $notice_class = 'notice-error';
                break;
            
            default:
                $notice_class = 'notice-warning';
                break;
        }

        $notice_class  .= ' alm-notice';

        $notice_markup  = '<div class="'. $notice_class .'">';
        $notice_markup .= '<p><strong><span class="alm-inline-notice-text">'. $notice_text . '</span></strong></p>';
        $notice_markup .= '</div>';

        return $notice_markup;
    }
}