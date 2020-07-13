<?php
namespace ALM\Controllers\User;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * User Base Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base
 * @since 1.0.0
 */
class UserManager extends \ALM\Controllers\Base\PluginFactory
{
    /**
     * Current User ID
     * @var int
     * @since 1.0.0
     */
	protected $current_user_ID = 0;

	/**
     * Current User Login
     * @var string
     * @since 1.0.0
     */
	protected $current_user_login = '';

	/**
     * Current User Email
     * @var string
     * @since 1.0.0
     */
	protected $current_user_email = '';

	/**
	 * Current User Data
	 * @var object
	 * @since 1.0.0
	 */
	protected $current_user_data = null;
	
	/**
	 * Specifies whether the current logged user is an administrator
	 * @var bool
	 */
	protected $is_administrator = false;

	/**
	 * Specifies whether the current logged in user is a super admin
	 * @var bool
	 */
	protected $is_super_admin = false;

	/**
	 * WP_Roles object
	 * @var object
	 */
	protected $wp_roles = null;
    
    /**
     * Setup the User Manager class. This is called during the initialization process
     */
    public function __runSetup()
    {
		global $wp_roles;
		$this->wp_roles           = $wp_roles;

		$this->is_super_admin     = $this->isSuperAdmin();
        $this->current_user_ID    = get_current_user_id();
		$this->current_user_data  = $this->getUserData( $this->current_user_ID );

		$this->is_administrator   = user_can( $this->current_user_ID, 'delete_users' );

		$this->current_user_login = isset( $this->current_user_data->user_login ) ? 
			$this->sanitizeOption( $this->current_user_data->user_login, 'username' ) : '';

		$this->current_user_email = isset( $this->current_user_data->user_email ) ? 
			$this->sanitizeOption( $this->current_user_data->user_login, 'email' ) : '';

		/**
		 * Setup the current user ID flag.
		 * 
		 * Used for retrieving the user ID in context where the current user ID is 
		 * unreachable
		 */
		$this->setConstant( 'ALM_CURRENT_USER_ID', $this->current_user_ID );
	}
	
	/**
	 * Check whether the current logged in user is a super admin
	 * @var bool
	 */
	public function isSuperAdmin()
	{
		if ( ! $this->is_multisite ) return false;
		return function_exists( 'is_super_admin' ) && is_super_admin();
	}

	/**
	 * Get the current user ID
	 * @return int
	 */
	public function getCurrentUserId()
	{
		$user_id = $this->current_user_ID;
		if ( $user_id > 0 ) 
			return $user_id;

		return (int) $this->getConstant( 'ALM_CURRENT_USER_ID' );
	}

	/**
	 * Check if a user is granted a specific privilege
	 * 
	 * @param  array    $caps       Specified capabilities to check for on single site and multi site.
	 *                              If a single array element is set, it is used on both single and 
	 * 								multi site.
	 * 
	 *                              #Example Usage:
	 * 
	 *                              $caps = [
	 *                                  'single|single_site' => 'cap on single-site',
	 *                                  'multi|multi_site'   => 'cap on multi-site',
	 *                              ];
	 * 
	 *                              #Alternatively:
	 * 
	 *                              The first array index is assigned to single site and vice versa.
	 *                              $caps = [ 'cap on single site', 'cap on multi site' ];
	 *                              $caps[0] is for single site.
	 *                              $caps[1] is for multisite site.
	 * 
	 * @param  int      $user_id    The user ID to check permission on.
	 * 
	 * @param  int      $blog_id    The specified blog to check the user on.
	 * 
	 * @param mixed     $args       Optional further arguments, typically starting with an object ID.
	 * 
	 * @return bool                 True if user can perform action. False otherwise.
	 */
	public function canPerformAction(
		$caps    = [],
		$user_id = null,
		$blog_id = null,
		...$args
	)
	{
		$user_id 			 = is_null( $user_id ) ? $this->current_user_ID : $user_id;
		$is_caps_traversable = is_array( $caps );
		
		if ( $is_caps_traversable )
		{
			$single_site_cap = isset( $caps['single'] ) ? $caps['single'] : '';

			$single_site_cap = ! empty( $single_site_cap ) ? 
				$single_site_cap : ( isset( $caps['single_site'] ) ? $caps['single_site'] : '' );

			$single_site_cap = ! empty( $single_site_cap ) ? 
				$single_site_cap : ( isset( $caps['singlesite'] ) ? $caps['singlesite'] : '' );
			
			// Fallback to numeric array index
			$single_site_cap = empty( $single_site_cap ) && isset( $caps[0] ) ? 
				$caps[0] : $single_site_cap;
		}
		else {
			$single_site_cap = $caps;
		}

		// Bail out if no capability is specified
		if ( empty( $single_site_cap ) ) 
			return false;

		if ( ! $this->is_multisite 
		|| is_null( $blog_id ) 
		|| $this->current_blog_ID == $blog_id )
		{
			$has_cap = user_can( $user_id, $single_site_cap, ...$args );
		}
		else {
			if ( $is_caps_traversable )
			{
				$multi_site_cap = isset( $caps['multi'] ) ? $caps['multi'] : '';

				$multi_site_cap = ! empty( $multi_site_cap ) ? 
					$multi_site_cap : ( isset( $caps['multi_site'] ) ? $caps['multi_site'] : '' );

				$multi_site_cap = ! empty( $multi_site_cap ) ? 
					$multi_site_cap : ( isset( $caps['multisite'] ) ? $caps['multisite'] : '' );

				// Fallback to numeric array index
				$multi_site_cap = empty( $multi_site_cap ) && isset( $caps[1] ) ? 
					$caps[1] : $multi_site_cap;

				// Use the single site cap if multisite cap is still empty
				$multi_site_cap = empty( $multi_site_cap ) ? $single_site_cap : $multi_site_cap;
			}
			else {
				$multi_site_cap = $caps;
			}

			// Bail out if no capability is specified
			if ( empty( $multi_site_cap ) ) 
				return false;

			switch_to_blog( $blog_id );
			$has_cap = user_can( $user_id, $multi_site_cap, ...$args );
			restore_current_blog();
		}
		return $has_cap;
	}

	/**
	 * Get the user data
	 * @see get_userdata()
	 */
	public function getUserData( $user_id = 0, $refresh = false )
	{
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) 
			$user_id = $this->current_user_ID;

		if ( $this->current_user_ID == $user_id 
		&& ! is_null( $this->current_user_data ) 
		&& ! $refresh )
		{
			$user_data = $this->current_user_data;
		}
		else {
			$user_data = get_userdata( $user_id );
		}

		if ( $user_data instanceof \WP_User ) 
			return $user_data;

		// We have to manually setup the user data
		return wp_set_current_user( $user_id );
	}

	/**
	 * Refresh user data
	 * @see UserFactory::getUserData()
	 */
	public function refreshCurrentUserData( $user_id = 0 )
	{
		$user_id  = absint( $user_id );
		$_user_id = ( $user_id < 1 ) ? $this->current_user_ID : $user_id;
		$this->current_user_data = $this->getUserData( $_user_id, true );
	}

	/**
	 * Get user Info
	 * 
	 * @param  int     $user_id  Specify the user ID
	 * @param  string  $info     Specifies the user info to retrieve
	 * @param  bool    $escape   Specifies whether or not to escape the user info
	 * 
	 * @return string 		     The specified user info if found. Otherwise empty string.
	 */
	public function getUserInfo( $user_id = 0, $info = 'display_name', $escape = true )
	{
		$user_id   = absint( $user_id );
		$_user_id  = ( $user_id > 0 ) ? $user_id : $this->current_user_ID;
		$user_data = $this->getUserData( $_user_id );
		$data      = isset( $user_data->$info ) ? $user_data->$info : '';

		$is_data_scalar = is_scalar( $data );

		if ( $is_data_scalar ) {
			$using_sanitize_flag = is_string( $escape );
			$sanitize 			 = $using_sanitize_flag ? $escape : '';
			$data 				 = $this->sanitizeOption( $data, $sanitize );

			if (  $using_sanitize_flag ) 
				return $data;
		}

		return ( $escape && is_bool( $escape ) && ! empty( $data ) && $is_data_scalar ) ? 
			esc_html( $data ) : $data;
	}

	/**
     * Get the admin profile edit page url
     * @param  int     $user_id  Specifies the user ID whose profile edit url should be returned.
     * @return string  The user profile edit url
     */
    public function getUserProfileEditUrl( $user_id = 0 )
    {
		$user_id = absint( $user_id );
		if ( absint( $user_id ) < 1 ) 
			$user_id = $this->current_user_ID;

		$admin_path = 'user-edit.php?user_id='. $user_id;

		if ( $user_id == $this->current_user_ID ) 
			$admin_path = 'profile.php';

        return esc_url_raw( admin_url( $admin_path ) );
	}
	
	/**
	 * Get the current logged in user roles
	 * @return array
	 */
	public function getCurrentUserRoles()
	{
		$roles = $this->current_user_data->roles;

		if ( $this->is_multisite )
		{
			// Bail administrator
			if ( $this->is_administrator && ! in_array( 'administrator', $roles, true ) )
				$roles[] = 'administrator';
		}
		else {
			// Bail super admin
			if ( $this->is_super_admin ) 
				$roles[] = 'super_admin';
		}
		
		return $roles;
	}

	/**
	 * Get a user roles
	 * @param int|WP_User Specifies the user whose roles should be retrieved
	 * @return array      List of user roles
	 */
	public function getUserRoles( $user = 0 )
	{
		if ( ! is_int( $user ) && ! is_object( $user ) ) 
			return [];
		
		if ( $user instanceof \WP_User && isset( $user->roles ) )
		{
			$roles = $user->roles;
		}
		else {
			$user = (int) $user;

			if ( $user === $this->current_user_ID )
				return $this->getCurrentUserRoles();

			$_roles = get_userdata( $user );
			if ( ! isset( $_roles->roles ) ) 
				return [];

			$roles = $_roles->roles;
		}
		return $roles;
	}

	/**
	 * Get additional user capabilities
	 */
	public function getAdditionalUserCaps( $user_id = 0 )
	{
		$user   = $this->getUserData( $user_id );
		$output = '';
		foreach ( $user->caps as $cap => $value )
		{
			if ( ! $this->wp_roles->is_role( $cap ) ) {
				if ( '' != $output ) {
					$output .= ', ';
				}

				if ( $value ) {
					$output .= $cap;
				} else {
					/* translators: %s: Capability name. */
					$output .= sprintf( alm__( 'Denied: %s' ), $cap );
				}
			}
		}
	}
}