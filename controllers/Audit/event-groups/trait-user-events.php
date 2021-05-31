<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package User Events
 * @since   1.0.0
 */

trait UserEvents
{
    /**
     * User data aggregation
     * @var array
     * @since 1.0.0
     */
    protected $user_data_ref          = [];
    protected $user_data_aggregation  = [];
    protected $_user_profile_metadata = [];

    /**
     * Specifies the user's roles and capabilities prior to update
     * @var array
     * @since 1.0.0
     */
    protected $old_user_caps = [];

    /**
     * User event data statistics
     * @var string
     * @since 1.0.0
     */
    protected $user_data_statistics = '';

    /**
     * Holds the user profile data to be updated
     * @see 'user_profile_update_errors' action hook
     * @var stdClass
     * @since 1.0.0
     */
    protected $user_profile_data_to_update = null;

    /**
     * Holds log related user data
     * @var array
     * @since 1.0.0
     */
    protected $_user_event_log_bin = [];

    /**
     * User profile meta field name
     * @var string
     * @since 1.0.0
     */
    protected $user_profile_meta_field_name         = 'alm_user_meta';
    protected $user_profile_meta_field_val_splitter = '__&__';

    /**
     * Get the user table fields
     * 
     * @since 1.0.0
     * 
     * @return array
     */
    protected function getUserTableFields()
    {
        return [
            'user_url'        => 'User URL',
            'user_pass'       => 'Password',
            'user_email'      => 'Email',
            'user_login'      => 'Login Name',
            'user_status'     => 'User Status',
            'display_name'    => 'Display Name',
            'user_nicename'   => 'Nice Name',
            'user_registered' => 'Registration Date',
        ];
    }

    /**
     * Init the user events
     * This method is called automatically.
     * 
     * @since 1.0.0
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList
     */
    protected function initUserEvents()
    {
        $this->setupUserEvents();
        $this->setUserCustomFieldEventsId();

        add_action('personal_options_update',  [ $this, 'setupUserDashboardPageConstant' ]);

        add_action('personal_options_update',  [ $this, 'setupUserMetadataPriorToUpdate' ]);
        add_action('edit_user_profile_update', [ $this, 'setupUserMetadataPriorToUpdate' ]);

        add_action('show_user_profile', [ $this, 'setupUserProfileMetadataFields' ]);
        add_action('edit_user_profile', [ $this, 'setupUserProfileMetadataFields' ]);

        add_filter('alm/event/msg/field/info', [ $this, 'customizeUserEventMsgFieldInfo' ], 10, 4);
        add_filter('alm/event/message/db',     [ $this, 'customizeUserEventMsgArgs'      ], 10, 3);

        add_filter('wp_pre_insert_user_data', [ $this, 'setupUserProfileAggregationFlag' ], 10, 3);
        add_filter('insert_user_meta',        [ $this, 'setupUserProfileMetadataAggregationFlag' ], 10, 3);

        add_filter('alm/event/user/custom_field/ignore', [ $this, '__ignorableUserMetaFields' ], 10, 3);
        
        // Password reset event setup
        add_action('retrieve_password',     [ $this, 'setupUserPasswordResetFlag'    ], 10);
        add_action('retrieve_password_key', [ $this, 'setupUserPasswordResetKeyFlag' ], 10, 2);
        
        // User deletion flag setup
        add_action('delete_user', [ $this, 'setupUserDeletionFlag' ], 10, 2);

        // Track the failed attempted password if allowed to
        $this->maybeTrackFailedLoginAttemptedPassword();

        // Multisite events
        if ($this->is_multisite)
            $this->addMultisiteEventFlags();
    }

    /**
     * Add multisite event flags
     * 
     * @since 1.0.0
     */
    protected function addMultisiteEventFlags()
    {
        /**
         * Fires immediately after a new user is created.
         */
        add_action('wpmu_new_user', function () {
            $this->setConstant('ALM_MS_NEW_USER_CREATED', true);
        });

        // Setup the delete user flag on multisite
        add_action('wpmu_delete_user', function () {
            $this->setConstant('ALM_MS_USER_DELETE_USER', true);
        });
        add_action('wpmu_delete_user', [ $this, 'setupUserDeletionFlag' ], 10, 2);
    }

    /**
     * Setup the user event customizable arguments list
     * 
     * @since 1.0.0
     * 
     * @param array  $user_args     The user arguments provided by the event callback function
     * 
     * @param string $context_state Specifies whether the event is pre-fired or post-fired.
     *                              Something like add_user_meta (pre) and added_user_meta (post).
     *                              If it's pre-fired, it will setup the context related data such as: 
     *                              previous value, intended value, etc.
     */
    protected function setupUserEventArgs(array $user_args = [], $context_state = 'post')
    {
        if (empty($user_args)) 
            return;

        $arg_list = [];

        $defaults = [
            'object_id'                 => 0,
            'meta_id'                   => '',
            'meta_key'                  => '',
            'wp_error'                  => false,
            '_error_msg'                => '',
            'meta_value'                => '',
            'meta_value_current'        => '',
            'meta_value_intended'       => '',
            'meta_value_requested'      => '',
            'was_custom_field_added'    => 0,
            'was_custom_field_deleted'  => 0,
            'was_custom_field_updated'  => 0,
            'is_user_owner_of_account'  => 0,
        ];

        $user_args = array_merge($defaults, $user_args);

        /**
         * Setup necessary user event argument vars
         */
        extract($user_args);

        /**
         * Use the 'user_id' if set
         */
        if (!empty($user_id) && ((int) $user_id) > 0) {
            $object_id = $user_id; 
        }

        // Use the $_current_user_id variable if set
        if (isset($_current_user_id) && $_current_user_id > 0) {
            $current_user_id = $_current_user_id;
        } else {
            $current_user_id = $this->User->getCurrentUserId();
        }

        /**
         * We need to be sure that the current logged in user is same with current user, 
         * and this is because WordPress provides ways to setup a different current user 
         * from the logged in user.
         * 
         * Note: During user registration, the user ID (object ID) is not available, 
         * we have to bail out
         */
        if (isset($user_obj) && is_object($user_obj))
        {
            // User object is available without the user ID
            $user = $user_obj;
        }
        else {
            if (!empty( $this->current_user_data) 
            && $object_id == $this->current_user_data->ID)
            {
                $user = $this->current_user_data;
            } else {
                $user = get_userdata($object_id);
            }
        }

        $arg_list['meta_id']             = $meta_id;
        $arg_list['meta_key']            = $meta_key;
        $arg_list['meta_value']          = $meta_value;
        $arg_list['meta_value_new']      = $meta_value;
        $arg_list['meta_value_current']  = $meta_value;
        $arg_list['meta_value_intended'] = $meta_value;

        if ( 'pre' == $context_state )
        {
            // Pre update state
        }
        else {
            $arg_list['was_custom_field_updated'] = ( $this->metadata_value_previous != $meta_value );
        }

        // WP_Error
        if ( isset($user->role) ) {
            $user_role = $user->role;
        } else {
            $user_role = $this->User->getUserRoles($object_id, true);
        }

        if ( $current_user_id > 0 ) {
            // $is_user_owner_of_account = $current_user_id == $object_id;
            $is_user_owner_of_account = $current_user_id == $this->User->getCurrentUserId() ? 1 : 0;
        } else {
            $is_user_owner_of_account = 0;
        }

        $nickname                             = $this->getVar($user, 'nickname', '');
        $last_name                            = $this->getVar($user, 'last_name', '');
        $first_name                           = $this->getVar($user, 'first_name', '');
        $user_login                           = $this->getVar($user, 'user_login', '');
        $user_email                           = $this->getVar($user, 'user_email', '');
        $display_name                         = $this->getVar($user, 'display_name', '');
        
        $arg_list['roles']                    = $user_role;
        $arg_list['nickname']                 = $this->sanitizeOption( $nickname );
        $arg_list['is_ready']                 = true;
        $arg_list['object_id']                = $object_id;
        $arg_list['last_name']                = $this->sanitizeOption( $last_name );
        $arg_list['_error_msg']               = $_error_msg;
        $arg_list['user_email']               = $this->sanitizeOption( $user_email, 'email' );
        $arg_list['user_login']               = $this->sanitizeOption( $user_login, 'username' );
        $arg_list['first_name']               = $this->sanitizeOption( $first_name );
        $arg_list['profile_url']              = $this->User->getUserProfileEditUrl( $object_id );
        $arg_list['display_name']             = $this->sanitizeOption( $display_name );
        $arg_list['is_user_owner_of_account'] = $is_user_owner_of_account;

        $existing_args = [];
        if ( isset( $this->customize_event_mg_args[' user'] ) ) {
            $existing_args = array_merge( $this->customize_event_msg_args['user'], $arg_list );
        } else {
            $existing_args = $arg_list;
        }

        if ( ! empty( $this->metadata_value_previous ) ) {
            $existing_args['meta_value_previous'] = $this->metadata_value_previous;
        }

        $user_msg_args = array_merge( $user_args, $existing_args );

        /**
         * On multisite, setup the blog id, blog name and blog url if not available
         */
        if ( $this->is_multisite )
        {
            if ( ! isset( $user_msg_args['blog_id'] ) ) {
                $user_msg_args['blog_id'] = get_current_blog_id();
            }

            $blog_id = $user_msg_args['blog_id'];

            if ( ! isset( $user_msg_args['blog_name'] ) ) {
                $user_msg_args['blog_name'] = $this->sanitizeOption( get_blog_option( $blog_id, 'blogname', '' ) );
            }
            
            if ( ! isset( $user_msg_args['blog_url'] ) ) {
                $user_msg_args['blog_url'] = $this->sanitizeOption( get_blog_option( $blog_id, 'siteurl', '' ), 'url' );
            }

            // Don't setup the user primary blog data if the ignore flag is set
            if ( !isset( $user_msg_args['_ignore_auto_setup'] ) )
            {
                if ( $object_id > 0 )
                {
                    if ( ! isset( $user_msg_args['primary_blog'] ) ) {
                        $user_msg_args['primary_blog'] = $this->sanitizeOption( get_user_meta( $object_id, 'primary_blog', true ) );
                    }

                    if ( ! isset( $user_msg_args['source_domain'] ) ) {
                        $user_msg_args['source_domain'] = $this->sanitizeOption( get_user_meta( $object_id, 'source_domain', true ) );
                    }
                    
                    if ( ! isset( $user_msg_args['primary_blog_url'] ) ) {
                        $user_msg_args['primary_blog_url'] = $this->sanitizeOption( get_blog_option($user_msg_args['primary_blog'], 'siteurl', '' ) );
                    }

                    if ( ! isset( $user_msg_args['primary_blog_name'] ) ) {
                        $user_msg_args['primary_blog_name'] = $this->sanitizeOption( get_blog_option( $user_msg_args['primary_blog'], 'blogname', '' ) );
                    }
                } else {
                    $user_msg_args['primary_blog']      = 0;
                    $user_msg_args['source_domain']     = '';
                    $user_msg_args['primary_blog_url']  = '';
                    $user_msg_args['primary_blog_name'] = '';
                }
            }
        }

        $this->customize_event_msg_args['user'] = $user_msg_args;
    }

    /**
     * Check whether we are currently aggregating the user profile data
     * 
     * @since 1.0.0
     * 
     * @return bool
     */
    protected function isUserProfileDataAggregationActive()
    {
        return !$this->isVerboseLoggingEnabled() 
            && ( false != $this->getConstant('ALM_IS_USER_PROFILE_UPDATE_AGGREGATION') );
    }

    /**
     * Append the updated/created user profile data
     * 
     * @since 1.0.0
     */
    protected function appendUpdatedUserProfileData( $field, array $values = [] )
    {
        $defaults = [
            'new'       => '',
            'previous'  => '',
            'current'   => '',
        ];

        $this->user_data_aggregation[ $field ] = array_merge( $defaults, $values );
    }

    /**
     * @todo - Remove this [the admin color is saved using ajax, thats why it's value is not present]
     * 
     * 
     * 
     * We observed some user custom meta update behaviors that were rather unusual,
     * noticeably the 'admin_color' user meta field. We couldn't retrieve the previous 
     * meta values for these fields during profile aggregation update, so we have to 
     * find a way to get the previous values.
     * 
     * @since 1.0.0
     */
    public function setupUserProfileMetadataFields( $user )
    {
        $fields = [ 'admin_color', ];
        $values = '';
        $split  = $this->user_profile_meta_field_val_splitter;

        foreach ( $fields as $field )
        {
            if ( isset( $user->$field ) ) {
                $value   = esc_attr( $user->$field );
                $values .= "{$field}={$value}{$split}";
            }
        }
        
        echo sprintf(
            '<input type="hidden" name="%s" value="%s">',
            $this->user_profile_meta_field_name,
            rtrim( esc_attr($values), $split )
        );
    }

    /**
     * Setup the user dashboard page constant
     * 
     * @since 1.0.0
     * 
     * @see 'personal_options_update' action hook
     */
    public function setupUserDashboardPageConstant( $user_id )
    {
        /**
         * If the site is using a customized user dashboard and IS_PROFILE_PAGE
         * constant is not defined, we have to set it up
         */
        if ( ! $this->getConstant('IS_PROFILE_PAGE') )
            $this->setConstant('ALM_IS_PROFILE_PAGE');

        // Setup the page constant
        $this->setConstant('ALM_IS_USER_PROFILE_UPDATE_AGGREGATION', 'update');
    }

    /**
     * Check whether a user is currently viewing their own profile
     * @return bool
     */
    public function isUserPersonalProfileActive()
    {
        return ( $this->getConstant('IS_PROFILE_PAGE') || $this->getConstant('ALM_IS_PROFILE_PAGE') );
    }

    /**
     * Setup the user metadata prior to update
     * 
     * @since 1.0.0
     */
    public function setupUserMetadataPriorToUpdate( $user_id )
    {
        if ( ! $this->isLogAggregatable() ) 
            return;

        /**
         * We need the user metadata prior to the profile update
         */
        if ( empty( $this->_user_profile_metadata ) ) 
            $this->_user_profile_metadata = get_user_meta( $user_id );
    }

    /**
     * Setup the user profile update aggregation flag.
     * 
     * @since 1.0.0
     * 
     * Filters user data before the record is created or updated.
     * 
     * @see 'wp_pre_insert_user_data' filter
     */
    public function setupUserProfileAggregationFlag( $data, $update, $ID )
    {
        if ( ! $this->isLogAggregatable() ) 
            return;

        $update_type = $update ? 'update' : 'create';
        $this->setConstant('ALM_IS_USER_PROFILE_UPDATE_AGGREGATION', $update_type );

        return $data;
    }

    /**
     * Setup the user metadata update aggregation flag.
     * 
     * @since 1.0.0
     * 
     * Filters a user's meta values and keys immediately after the user is created or updated
     * and before any user meta is inserted or updated.
     * 
     * @see 'insert_user_meta' filter
     */
    public function setupUserProfileMetadataAggregationFlag( $meta, $user, $update )
    {
        if ( ! $this->isLogAggregatable() ) 
            return;
        
        $this->setConstant('ALM_IS_USER_PROFILE_METADATA_AGGREGATION', true);

        return $meta;
    }

    /**
     * Setup the user password reset flag
     * 
     * @since 1.0.0
     * 
     * @see get_password_reset_key()
     */
    public function setupUserPasswordResetFlag( $user_login )
    {
        $this->setConstant( 'ALM_USER_PASSWORD_RESET_STARTED', true );
    }
    
    /**
     * Setup the user password reset key flag for retrieving the 
     * password reset key
     * 
     * @since 1.0.0
     * 
     * @see get_password_reset_key()
     */
    public function setupUserPasswordResetKeyFlag( $user_login, $key )
    {
        $this->setConstant( 'ALM_USER_PASSWORD_RESET_KEY', $key );
    }

    /**
     * Setup the user deletion flag 
     * 
     * @since 1.0.0
     * 
     * @see wp_delete_user()
     * @see wpmu_delete_user()
     * 
     * @todo Get the deleted post, links and metadata
     * @todo if post was reassigned, show the statistics update
     */
    public function setupUserDeletionFlag( $user_id, $reassign = null )
    {
        $this->setConstant( 'ALM_USER_DELETE_USER', $user_id );

        /**
         * Keep reference to some user data
         */
        $user_data = $this->User->getUserData($user_id);

        if ( empty($this->getVar($user_data, 'ID')) ) 
            return;

        $this->user_data_ref = (object) [
            'roles'        => (array) $user_data->roles,
            'nickname'     => $this->sanitizeOption($user_data->nickname),
            'user_login'   => $this->sanitizeOption($user_data->user_login, 'username'),
            'user_email'   => $this->sanitizeOption($user_data->user_email, 'email'),
            'last_name'    => $this->sanitizeOption($user_data->last_name),
            'first_name'   => $this->sanitizeOption($user_data->first_name),
            'display_name' => $this->sanitizeOption($user_data->display_name),
        ];

        /**
         * Get a summary of all deleted user posts, links, metadata
         */ 
        $reassign = (int) $reassign;

        /**
         * Get the list of post types to delete with a user
         * This works only on non-multisite installation
         */
        if ( !$this->is_multisite )
        {
            $_post_types_to_delete = [];

            add_filter('post_types_to_delete_with_user',
                function( $post_types_to_delete, $id ) use ( &$_post_types_to_delete )
                {
                    $_post_types_to_delete = $post_types_to_delete;
                    return $post_types_to_delete;
                },
                10, 2
            );

            $_post_types_to_delete = implode( "', '", $_post_types_to_delete );
        }

        $this->user_data_statistics = '';
    }

    /**
     * Track the failed login attempted password if allowed to
     */
    protected function maybeTrackFailedLoginAttemptedPassword()
    {
        if ($this->canLogFailedLoginAttemptedPassword()) {
            add_filter('authenticate', function($user, $username, $password) {
                $this->_user_event_log_bin['failed_login_attempted_password'] = $password;
                return $user;
            }, 10, 3);
        } else {
            $this->_user_event_log_bin['failed_login_attempted_password'] = str_repeat('*', 8);
        }
    }

    /**
     * Setup the users events
     * 
     * @since 1.0.0
     */
    protected function setupUserEvents()
    {
        $this->event_list['users'] = [
            'title'           => 'Users',
            'group'           => 'user', // object
            'object_id_label' => 'User ID',

            'description'     => alm__('Responsible for logging users related activities which includes: <strong>user roles and capabilities changes</strong>, <strong>user login name, display name, email and password changes</strong>, <strong>custom fields changes such as: first name, last name, nickname, etc.</strong>, <strong>user login, failed login attempts, user registration, password recovery and user logout</strong>.')
        ];

        $events = [
            /**
             * Will check for user metadata fields changes.
             * 
             * @since 1.0.0
             * 
             * @see add_metadata()
             * @see update_metadata()
             * @see delete_metadata()
             * 
             * @see get_user_meta()
             * 
             * The three listed functions above fires the following hooks:
             * 
             *    1. add_user_meta
             * 	  2. added_user_meta
             * 	  3. update_user_meta
             * 	  4. updated_user_meta
             * 	  5. delete_user_meta
             * 	  6. deleted_user_meta
             *
             * Meta events arguments can also be used to override existing event arguments.
            */
            'add_user_meta' => [
                'title'    => 'User profile update initiated',
                'action'   => 'create',
                'disable'  => false,
                'event_id' => 5051, // Specific meta fields (keys) can override this
                'severity' => 'notice',

                /**
                 * If the meta key is null, then it will be set to 
                 * the user meta key that is currently being added.
                 */
                'meta_key' => null,
                
                /**
                 * Event message arguments.
                 * 
                 * Message can auto set a standalone event code by using the '_event_id' argument:
                 *     [
                 *         ** this will override the global group event code
                 *         ** useful for custom fields such as: first name, last name, etc.
                 * 
                 *         '_event_id' => 1001,
                 *     ]
                 * 
                 * Note: when a message starts with an underscore (_) character, it implies that 
                 * the message argument has a special meaning and should be treated as such.
                 */
                'message' => [
                    '_main' => 'Tried to add a new custom field to a user profile (%s), but the request was unsuccessful.',

                    '_space_start'             => '',
                    'meta_key'                 => ['meta_key'],
                    'meta_value'               => ['meta_value'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'log_counter'              => $this->getLogCounterInfo(),
                    'custom_field_added'       => ['custom_field_added'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * @since 1.0.0
             */
            'added_user_meta' => [
                'title'    => 'User profile updated',
                'action'   => 'created',
                'event_id' => 5052, // Specific meta fields (keys) can override this
                'severity' => 'notice',

                'message' => [
                    '_main'                    => 'Added a new custom field to a user profile (%s).',

                    '_space_start'             => '',
                    'meta_key'                 => ['meta_key'],
                    'meta_value'               => ['meta_value'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 4,
                ],
            ],

            /**
             * @since 1.0.0
             */
            'update_user_meta' => [
                'title'    => 'User profile update triggered',
                'action'   => 'failed',
                'event_id' => 5053, // Specific meta fields (keys) can override this
                'disable'  => false,
                'severity' => 'notice',

                'message'  => [
                    '_main' => 'Tried to update a custom field on a user profile (%s), but the request was unsuccessful.',

                    '_space_start'             => '',
                    'meta_key'                 => [ 'meta_key' ],
                    'meta_value_intended'      => ['meta_value_intended'],
                    'meta_value'               => ['meta_value', 'current'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'log_counter'              => $this->getLogCounterInfo(),
                    'custom_field_updated'     => ['custom_field_updated'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 4,
                ],
            ],

            /**
             * @since 1.0.0
             */
            'updated_user_meta' => [
                'title'    => 'User profile updated',
                'action'   => 'modified',
                'event_id' => 5054, // Specific meta fields (keys) can override this
                'severity' => 'notice',

                'message'  => [
                    '_main'                    => 'Updated a custom field on a user profile (%s).',

                    '_space_start'             => '',
                    'meta_key'                 => ['meta_key'],
                    'meta_value_previous'      => ['meta_value', 'previous'],
                    'meta_value'               => ['meta_value', 'new'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'custom_field_updated'     => ['custom_field_updated'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 4,
                ],
            ],

            /**
             * @since 1.0.0
             */
            'delete_user_meta' => [
                'title'    => 'User profile modification triggered',
                'action'   => 'delete',
                'event_id' => 5055, // Specific meta fields (keys) can override this
                'disable'  => false,
                'severity' => 'notice',

                'message'  => [
                    '_main' => 'Tried to delete a custom field from a user profile (%s), but the request was unsuccessful.',

                    '_space_start'             => '',
                    'meta_key'                 => ['meta_key'],
                    'meta_value'               => ['meta_value'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'log_counter'              => $this->getLogCounterInfo(),
                    'custom_field_deleted'     => ['custom_field_deleted'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 4,
                ],
            ],

            /**
             * @since 1.0.0
             */
            'deleted_user_meta' => [
                'title'    => 'User profile modified',
                'action'   => 'deleted',
                'event_id' => 5056, // Specific meta fields (keys) can override this
                'severity' => 'notice',

                'message'  => [
                    '_main'                    => 'Deleted a custom field from a user profile (%s).',

                    '_space_start'             => '',
                    'meta_key'                 => ['meta_key'],
                    'meta_value'               => ['meta_value'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'custom_field_added'       => ['custom_field_added'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 4,
                ],
            ],

            /**
             * Fires after the 'About the User' settings table on the 'Edit User' screen.
             * 
             * @since 1.0.0
             * @todo
             */
            'edit_user_profile' => [
                'title'               => 'Viewed another user profile',
                'action'              => 'viewed',
                'event_id'            => 5057,
                'severity'            => 'info',
                'is_aggregatable'     => true,

                'screen'              => ['admin', 'network',],
                'user_state'          => 'logged_in',
                'logged_in_user_caps' => ['edit_user', 'edit_users'],

                'message'  => [
                    '_main' => 'Viewed another user profile (%s).',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],
            ],

            /**
             * Fires after the 'About the User' settings table on the 'Edit User' screen.
             * The action only fires if the current user is editing their own profile.
             * 
             * @since 1.0.0
             */
            'show_user_profile' => [
                'title'               => 'Viewed own user profile',
                'action'              => 'viewed',
                'event_id'            => 5058,
                'severity'            => 'info',
                'is_aggregatable'     => true,

                'screen'              => ['admin', 'network',],
                'user_state'          => 'logged_in',
                'logged_in_user_caps' => ['edit_user', 'edit_users'],

                'message'  => [
                    '_main' => 'Viewed own user profile (%s).',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],
            ],

            /**
             * Opened the 'Edit User' screen and triggered the profile update
             * Fires when a user is editing another user
             * 
             * @since 1.0.0
             */
            'edit_user_profile_update' => [
                'title'              => 'User profile update triggered',
                'action'             => 'triggered',
                'event_id'           => 5059,
                'severity'           => 'notice',
                'is_aggregatable'    => true,

                'screen'              => [ 'admin', 'network', ],
                'user_state'          => 'logged_in',
                'logged_in_user_caps' => ['edit_user', 'edit_users'],

                'message'  => [
                    '_main' => 'Triggered the user profile (%s) update by using the edit user screen.' . $this->explainEventMsg(
                        ' (The Edit User screen is accessible and used by a user who has the <strong>edit_users or edit_user</strong> capability to be able to edit other users on the site. This event occurs when a user is editing the profile of another user.)'
                    ),
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],
            ],

            /**
             * Errors returned while trying to update a user
             * 
             * @since 1.0.0
             */
            'user_profile_update_errors' => [
                'title'           => 'User profile update error',
                'action'          => 'user_update_failed',
                'event_id'        => 5060,
                'severity'        => 'notice',
                'error_flag'      => true,
                'event_successor' => ['user', 'profile_update'],

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Tried to update the user profile (%s) but it was unsuccessful due to errors that occurred while processing the request.',
                    
                    '_space_start'             => '',
                    '_error_msg'               => '',
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'log_counter'              => $this->getLogCounterInfo(),
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * This is an alias of the WordPress 'user_profile_update_errors' 
             * action hook.
             * 
             * It is triggered when it has been confirmed that the user profile 
             * errors is not for updating the new user, but creating a new user request
             * 
             * @since 1.0.0
             */
            'alm_user_profile_update_errors' => [
                'title'               => 'New user creation error',
                'action'              => 'user_creation_failed',
                'event_id'            => 5061,
                'severity'            => 'notice',
                'error_flag'          => true,
                'event_successor'     => ['user', 'edit_user_created_user'],

                'user_state'          => 'logged_in',
                'logged_in_user_caps' => [ 'edit_users' ],

                'message'  => [
                    '_main' => 'Tried to create a new user but it was unsuccessful due to errors that occurred while processing the request.',
                    
                    '_space_start'     => '',
                    '_error_msg'       => '',
                    '_space_end'       => '',
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Fires after a new user has been created
             * 
             * @since 1.0.0
             */
            'edit_user_created_user' => [
                'title'               => 'Created a new user',
                'action'              => 'user_created',
                'event_id'            => 5062,
                'severity'            => 'critical',

                'screen'              => [ 'admin', 'network', ],
                'user_state'          => 'logged_in',
                'logged_in_user_caps' => [ 'edit_users' ],

                'message'  => [
                    '_main' => 'Created a new user (%s).',
                    
                    '_space_start'             => '',

                    // Will contain all updated user profile data
                    'user_profile_data'        => ['user_profile_data'],

                    '_inspect_user_role'       => '',
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires immediately after a new user registered successfully.
             * 
             * @since 1.0.0
             */
            // 'user_register' => [
            'register_new_user' => [
                'title'    => 'New user registration',
                'action'   => 'user_registered',
                'event_id' => 5063,
                'severity' => 'notice',

                'user_state' => 'both',

                'message'  => [
                    '_main' => 'New user (%s) registration successful',
                    
                    '_space_start'             => '',

                    // Will contain all updated user profile data
                    'user_profile_data'        => ['user_profile_data'],

                    '_inspect_user_role'       => '',
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                ],
            ],

            /**
             * User registration errors
             * 
             * @since 1.0.0
             */
            'register_post' => [
                'title'    => 'New user registration error',
                'action'   => 'user_registration_failed',
                'event_id' => 5064,
                'severity' => 'notice',

                'user_state' => 'both',

                'message'  => [
                    '_main' => 'New user registration error',
                    
                    '_space_start' => '',
                    '_error_msg'   => '',
                    '_space_end'   => '',
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * Fires immediately after an existing user is updated.
             * 
             * @since 1.0.0
             */
            'profile_update' => [
                'title'           => 'User profile updated',
                'action'          => 'modified',
                'event_id'        => 5065,
                'severity'        => 'notice',
                'is_aggregatable' => true,

                'user_state'      => 'logged_in',

                'message' => [
                    '_main' => 'Updated the user profile (%s). See the changes below:',
                    
                    '_space_start'             => '',

                    // Will contain all updated user profile data
                    'user_profile_data'        => ['user_profile_data'],

                    '_inspect_user_role'       => '',
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Profile update alias for display_name
             * 
             * @since 1.0.0
             */
            'alm_profile_update_display_name' => [
                'title'    => 'User display name updated',
                'action'   => 'modified',
                'event_id' => 5066,
                'severity' => 'notice',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Changed the display name of the user (%s)',
                    
                    '_space_start'             => '',
                    'display_name_previous'    => ['display_name', 'previous'],
                    'display_name_new'         => ['display_name', 'new'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Profile pre-update alias for user_email.
             * This is used to monitor the initial request to change the user email 
             * before it is confirmed. During this event, the new email has not been 
             * activated.
             * 
             * @since 1.0.0
             */
            'alm_profile_update_pre_user_email' => [
                'title'    => 'User email address update requested',
                'action'   => 'requested',
                'event_id' => 5067,
                'severity' => 'critical',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Requested a change of the user (%s) email address (%s)',

                    '_space_start'             => '',
                    'user_email_requested'     => ['user_email', 'requested'],
                    'user_email_current'       => ['user_email', 'current'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],
            
            /**
             * Profile update alias for user_email (when it has been confirmed)
             * 
             * @since 1.0.0
             */
            'alm_profile_update_user_email' => [
                'title'    => 'User email updated',
                'action'   => 'modified',
                'event_id' => 5068,
                'severity' => 'critical',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Changed the user (%s) email address',

                    '_space_start'             => '',
                    'user_email_previous'      => ['user_email', 'previous'],
                    'user_email_new'           => ['user_email', 'new'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Profile update cancellation alias for change of user_email.
             * This is used to monitor the change of user email cancellation.
             * 
             * @since 1.0.0
             */
            'alm_profile_update_cancelled_user_email' => [
                'title'    => 'User email address update request cancelled',
                'action'   => 'cancelled',
                'event_id' => 5069,
                'severity' => 'notice',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Cancelled the request to change the email address of the user (%s)',

                    '_space_start'             => '',
                    'user_email_requested'     => ['user_email', 'requested'],
                    'user_email_current'       => ['user_email', 'current'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Profile update alias for user_nicename
             * 
             * @since 1.0.0
             */
            'alm_profile_update_user_nicename' => [
                'title'    => 'User nice name updated',
                'action'   => 'modified',
                'event_id' => 5070,
                'severity' => 'notice',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Changed the nice name of th user (%s)',

                    '_space_start'             => '',
                    'user_nice_name_previous'  => ['user_nicename', 'previous'],
                    'user_nice_name_new'       => ['user_nicename', 'new'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Profile update alias for user_url
             * 
             * @since 1.0.0
             */
            'alm_profile_update_user_url' => [
                'title'    => 'User website url updated',
                'action'   => 'modified',
                'event_id' => 5071,
                'severity' => 'notice',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Changed the website URL of the user (%s)',

                    '_space_start'             => '',
                    'user_url_previous'        => ['user_url_previous'],
                    'user_url_new'             => ['user_url_new'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Profile update alias for user_status
             * 
             * @since 1.0.0
             */
            'alm_profile_update_user_status' => [
                'title'    => 'User status updated',
                'action'   => 'modified',
                'event_id' => 5072,
                'severity' => 'notice',

                'user_state' => 'logged_in',

                'message'  => [
                    '_main' => 'Changed the status of the user (%s)',

                    '_space_start'             => '',
                    'user_status_previous'     => ['user_status', 'previous'],
                    'user_status_new'          => ['user_status', 'new'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * Profile update alias for user_pass (user password).
             * 
             * Fired when a user with the 'edit_users' capability is changing 
             * the password of another user.
             * 
             * @since 1.0.0
             */
            'alm_profile_update_user_pass' => [
                'title'    => 'User password updated',
                'action'   => 'modified',
                'event_id' => 5073,
                'severity' => 'critical',

                'logged_in_user_caps' => [ 'edit_users' ],

                'message'  => [
                    '_main' => 'Changed the password of the user (%s)',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 3,
                ],
            ],

            /**
             * User password reset.
             * 
             * Fires before errors are returned from a password reset request.
             * 
             * @since 1.0.0
             * 
             * @see retrieve_password()
             */
            'lostpassword_post' => [
                'title'           => 'User password reset request failed',
                'action'          => 'password_reset_failed',
                'event_id'        => 5074,
                'severity'        => 'critical',
                'error_flag'      => true,
                'event_successor' => ['user', 'alm_retrieve_password_successfully'],

                'message'  => [
                    '_main' => 'Tried to initiate a request for resetting the user (%s) password but failed.',

                    '_space_start'             => '',
                    '_error_msg'               => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'log_counter'              => $this->getLogCounterInfo(),
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * User password reset.
             * 
             * Filters whether to allow a password to be reset.
             * 
             * Used to check whether password reset is allowed on the user or not
             * 
             * @since 1.0.0
             * 
             * @see get_password_reset_key()
             * @see retrieve_password action hook
             * @see allow_password_reset filter hook
             */
            'allow_password_reset' => [
                'title'           => 'User password reset not allowed',
                'action'          => 'password_reset_failed',
                'event_id'        => 5075,
                'severity'        => 'critical',
                'error_flag'      => true,
                'event_successor' => ['user', 'alm_retrieve_password_successfully'],

                'message'  => [
                    '_main' => 'Tried to initiate a request for resetting the user (%s) password but failed because password reset is disabled on the user account',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'log_counter'              => $this->getLogCounterInfo(),
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'filter',
                    'num_args' => 2,
                ],
            ],

            /**
             * User password reset.
             * 
             * Fires when a password reset key is generated and saved successfully.
             * 
             * @since 1.0.0
             * 
             * @see get_password_reset_key()
             * @see retrieve_password action hook
             * @see retrieve_password_key action hook
             * @see profile_update action hook
             * 
             * Used to check whether password reset request was successful and 
             * confirmation has been sent.
             * 
             * #Event Process
             * 
             * 1. Listen for password reset key {@see retrieve_password} action
             * 2. Setup the password reset constant flag
             * 3. Listen for the profile update {@see profile_update} action
             * 4. Check if the user_activation_key was updated successfully.
             * 5. Trigger the {@see alm_retrieve_password_successfully} event.
             */
            'alm_retrieve_password_successfully' => [
                'title'    => 'User password reset request initiated',
                'action'   => 'password_reset_initiated',
                'event_id' => 5076,
                'severity' => 'critical',

                'message'  => [
                    '_main' => 'Initiated a password reset request on the user account (%s)',

                    '_space_start'             => '',

                    // Holds the user_activation_key
                    'password_reset_key'       => ['password_reset_key'],
                    'password_expiration_time' => ['password_expiration_time'],
                    'password_reset_url'       => ['password_reset_url'],

                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 1,
                ],
            ],

            /**
             * Fires when the password reset key was retrieved successfully 
             * but the user_activation_key column in the user table could not 
             * be updated
             * 
             * @see alm_retrieve_password_successfully
             */
            'alm_retrieve_password_unsuccessful' => [
                'title'           => 'User password reset request unsuccessful',
                'action'          => 'password_reset_failed',
                'event_id'        => 5077,
                'severity'        => 'critical',
                'error_flag'      => true,
                'event_successor' => ['user', 'alm_retrieve_password_successfully'],

                'message'  => [
                    '_main' => 'Initiated a password reset request which could not be completed because the system was unable to save the generated password reset key on the user account (%s).',

                    '_space_start'                 => '',
                    // Holds the user_activation_key
                    'generated_password_reset_key' => ['password_reset_key'],
                    'password_reset_url'           => ['password_reset_url'],
                    '_space_end'                   => '',

                    'user_id'                      => ['object_id'],
                    'user_login'                   => ['user_login'],
                    'display_name'                 => ['display_name'],
                    'roles'                        => ['roles'],
                    'first_name'                   => ['first_name'],
                    'last_name'                    => ['last_name'],
                    'user_email'                   => ['user_email'],
                    'log_counter'                  => $this->getLogCounterInfo(),
                    'profile_url'                  => ['profile_url'],
                    'user_primary_site'            => ['primary_blog'],
                    'primary_site_name'            => ['primary_blog_name'],
                    'primary_site_url'             => ['primary_blog_url'],
                    'source_domain'                => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 1,
                ],
            ],

            /**
             * Fires after the user's password is reset.
             * 
             * @since 1.0.0
             * 
             * @see reset_password()
             */
            'after_password_reset' => [
                'title'    => 'User password reset successfully',
                'action'   => 'password_reset',
                'event_id' => 5078,
                'severity' => 'critical',

                
                'message'  => [
                    '_main' => 'Reset the user (%s) password successfully.',
                    
                    '_space_start'             => '',
                    'password_reset_url'       => ['password_reset_url'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires after a user login has failed.
             * 
             * @since 1.0.0
             * 
             * @see wp_authenticate()
             */
            'wp_login_failed' => [
                'title'           => 'Login attempt failed',
                'action'          => 'login_failed',
                'event_id'        => 5079,
                'severity'        => 'critical',
                'error_flag'      => true,
                'event_successor' => ['user', 'wp_login'],

                'message'  => [
                    '_main'                    => 'Login attempt failed.',

                    '_space_start'             => '',
                    'failed_attempts'          => ['failed_attempts'],
                    'user_login'               => ['user_login'],
                    'user_email'               => ['user_email'],
                    'attempted_password'       => ['attempted_password'],
                    'login_url'                => ['login_url'],
                    '_error_msg'               => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'profile_url'              => ['profile_url'],
                    'user_account_exists'      => ['user_account_exists'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires after a user has logged-in.
             * 
             * @since 1.0.0
             * 
             * @see wp_authenticate()
             */
            'wp_login' => [
                'title'    => 'User logged in',
                'action'   => 'logged_in',
                'event_id' => 5080,
                'severity' => 'notice',

                'message'  => [
                    '_main'                    => 'Logged in successfully.',

                    '_space_start'             => '',
                    'login_url'                => ['login_url'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires after a user has logged-out
             * 
             * @since 1.0.0
             * 
             * @see wp_authenticate()
             */
            'wp_logout' => [
                'title'      => 'User logged out',
                'action'     => 'logged_out',
                'event_id'   => 5081,
                'severity'   => 'notice',

                'user_state' => 'logged_in',

                'message'    => [
                    '_main'                    => 'Logged out successfully.',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ]
            ],

            /**
             * Fires immediately before a user is deleted from the database.
             * 
             * @since 1.0.0
             */
            'deleted_user' => [
                'title'               => 'User deleted',
                'action'              => 'user_deleted',
                'event_id'            => 5082,
                'severity'            => 'critical',
                'logged_in_user_caps' => ['delete_users'],

                'message' => [
                    '_main' => 'Deleted a user account (%s) from the site.',

                    '_space_start'             => '',
                    'deleted_user_statistics'  => '',
                    'site_id'                  => ['blog_id'],
                    'site_name'                => ['blog_name'],
                    'site_url'                 => ['blog_url'],
                    'reassign_user_post_to'    => ['reassign_post'],
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'roles'                    => ['roles'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires after the user's role has changed.
             * 
             * @since 1.0.0
             */
            'set_user_role' => [
                'title'    => 'User role changed',
                'action'   => 'user_role_modified',
                'event_id' => 5083,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_role' => [
                        'plural'      => 'all_previous_roles',
                        'singular'    => 'previous_role',

                        // Specifies the character to check for before pluralizing 
                        // the string. Basically, this is the character used to join the 
                        // array elements, same as the one used in the implode() function
                        'plural_char' => ', ',
                    ],
                    'all_new_role' => [
                        'plural'   => 'all_new_roles',
                        'singular' => 'new_role',
                    ]
                ],

                'message'  => [
                    '_main'                    => 'Changed the role of the user (%s).',

                    '_space_start'             => '',
                    'all_previous_role'        => ['role_previous'],
                    'all_new_role'             => ['role_new'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',
                    
                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 3,
                ],
            ],

            /**
             * Fires immediately after the user has been given a new role.
             * 
             * @since 1.0.0
             */
            'add_user_role' => [
                'title'    => 'User given a new role',
                'action'   => 'user_role_modified',
                'event_id' => 5084,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_role' => [
                        'plural'   => 'all_previous_roles',
                        'singular' => 'previous_role',
                    ],
                    'all_new_role' => [
                        'plural'   => 'all_new_roles',
                        'singular' => 'new_role',
                    ]
                ],

                'message'  => [
                    '_main'                    => 'Added a new role to the user (%s)',

                    '_space_start'             => '',
                    'added_role'               => ['added_role'],
                    'all_previous_role'        => ['role_previous'],
                    'all_new_role'             => ['role_new'],
                    'role_info'                => ['role_info'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires immediately after a given role has been removed from a user
             * 
             * @since 1.0.0
             */
            'remove_user_role' => [
                'title'    => 'Role removed from user',
                'action'   => 'user_role_modified',
                'event_id' => 5085,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_role' => [
                        'plural'   => 'all_previous_roles',
                        'singular' => 'previous_role',
                    ],
                    'all_new_role' => [
                        'plural'   => 'all_new_roles',
                        'singular' => 'new_role',
                    ]
                ],

                'message'  => [
                    '_main'                    => 'Removed a role from the user (%s)',

                    '_space_start'             => '',
                    'removed_role'             => ['removed_role'],
                    'all_previous_role'        => ['role_previous'],
                    'all_new_role'             => ['role_new'],
                    '_inspect_user_role'       => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires immediately after the user has been given a new capability.
             *
             * @since 1.0.0
             */
            'alm_add_user_cap_event' => [
                'title'    => 'User given a new capability',
                'action'   => 'user_capability_modified',
                'event_id' => 5086,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_capability' => [
                        'plural'   => 'all_previous_capabilities',
                        'singular' => 'previous_capability',
                    ],
                    'all_new_capability' => [
                        'plural'   => 'all_new_capabilities',
                        'singular' => 'new_capability',
                    ],
                    'added_capability' => [
                        'plural'   => 'added_capabilities',
                        'singular' => 'added_capability',
                    ],
                ],

                'message'  => [
                    '_main' => 'Added a new capability to the user (%s) and granted the user access to use it',

                    '_space_start'             => '',
                    'added_capability'         => ['added_capability'],
                    'all_previous_capability'  => ['capability_previous'],
                    'all_new_capability'       => ['capability_new'],
                    '_inspect_user_capability' => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires immediately after the user has been given a new capability 
             * but access was denied
             * 
             * @since 1.0.0
             */
            'alm_add_user_cap_denied_event' => [
                'title'    => 'User given new capability but grant access denied',
                'action'   => 'user_capability_modified',
                'event_id' => 5087,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_capability' => [
                        'plural'   => 'all_previous_capabilities',
                        'singular' => 'previous_capability',
                    ],
                    'all_new_capability' => [
                        'plural'   => 'all_new_capabilities',
                        'singular' => 'new_capability',
                    ],
                    'added_capability' => [
                        'plural'   => 'added_capabilities',
                        'singular' => 'added_capability',
                    ],
                ],

                'message'  => [
                    '_main' => 'Added a new capability to the user (%s) but denied the user access from using it.' . $this->explainEventMsg(
                        ' (This means that the capability exists on the user account but the user cannot use it just yet because grant access was denied).'
                    ),

                    '_space_start'             => '',
                    'added_capability'         => ['added_capability'],
                    'all_previous_capability'  => ['capability_previous'],
                    'all_new_capability'       => ['capability_new'],
                    '_inspect_user_capability' => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 2,
                ],
            ],
            
            /**
             * Fires immediately after a given capability is removed from the user
             * 
             * @since 1.0.0
             */
            'alm_remove_user_cap_event' => [
                'title'    => 'User capability removed',
                'action'   => 'user_capability_modified',
                'event_id' => 5088,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_capability' => [
                        'plural'   => 'all_previous_capabilities',
                        'singular' => 'previous_capability',
                    ],
                    'all_capability_new' => [
                        'plural'   => 'all_new_capabilities',
                        'singular' => 'new_capability',
                    ],
                    'removed_capability' => [
                        'plural'   => 'removed_capabilities',
                        'singular' => 'removed_capability',
                    ],
                ],

                'message'  => [
                    '_main'                    => 'Removed a capability from the user (%s)',

                    '_space_start'             => '',
                    'removed_capability'       => ['removed_capability'],
                    'all_previous_capability'  => ['capability_previous'],
                    'all_new_capability'       => ['capability_new'],
                    '_inspect_user_capability' => '',
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 2,
                ],
            ],

            /**
             * Fires immediately after all the user capabilities have been removed
             * 
             * @since 1.0.0
             */
            'alm_remove_all_user_cap_event' => [
                'title'    => 'All capabilities removed from user',
                'action'   => 'user_capability_modified',
                'event_id' => 5089,
                'severity' => 'critical',

                /**
                 * Ignore the user capability meta field
                 */
                'ignore_meta_fields' => [
                    $this->getBlogPrefix() . 'capabilities',
                ],

                /**
                 * Translation arguments
                 */
                '_translate' => [
                    'all_previous_capability' => [
                        'plural'   => 'all_previous_capabilities',
                        'singular' => 'previous_capability',
                    ],
                ],

                'message'  => [
                    '_main'                    => 'Removed all capabilities from the user (%s)',

                    '_space_start'             => '',
                    'all_previous_capability'  => ['capability_previous'],
                    'new_capability'           => ['capability_new'],
                    '_space_end'               => '',

                    'user_id'                  => ['object_id'],
                    'user_login'               => ['user_login'],
                    'display_name'             => ['display_name'],
                    'first_name'               => ['first_name'],
                    'last_name'                => ['last_name'],
                    'user_email'               => ['user_email'],
                    'is_user_owner_of_account' => ['is_user_owner_of_account'],
                    'profile_url'              => ['profile_url'],
                    'user_primary_site'        => ['primary_blog'],
                    'primary_site_name'        => ['primary_blog_name'],
                    'primary_site_url'         => ['primary_blog_url'],
                    'source_domain'            => ['source_domain'],
                ],

                'event_handler' => [
                    'hook'     => 'callback',
                    'num_args' => 2,
                ],
            ],
        ];

        // Make sure multisite events are sync correctly
        if ($this->is_multisite && property_exists($this, 'mu_user_events')) {
            $events = array_merge($events, $this->mu_user_events);
        }

        $this->event_list['users']['events'] = $events;
    }

    /**
     * Get the customized (well known) user custom fields
     * 
     * @since 1.0.0
     * 
     * @return array
     * 
     * @todo
     * Add the user custom fields description (what it is used for)
     */
    protected function getCustomizedUserCustomFields()
    {
        $blog_prefix       = $this->getBlogPrefix();

        $admin_color       = 'admin_color';
        $user_settings     = $blog_prefix . 'user-settings';
        $user_capabilities = $blog_prefix . 'capabilities';

        return [
            'use_ssl' => [
                '_title'      => 'Use SSL',
                '_event_id'   => 5101,
                'description' => alm__('Specifies whether to force SSL (Secure Socket Layer) on the user&#8217;s admin area. If enabled, the user admin area will be loaded on https.'),
            ],
            'locale' => [
                '_event_id' => 5102,
            ],
            'nickname' => [
                '_event_id' => 5103,
            ],
            'last_name' => [
                '_event_id' => 5104,
            ],
            'first_name' => [
                '_event_id'  => 5105,
            ],
            $admin_color => [
                '_title'      => 'Admin color',
                '_event_id'   => 5106,
                'description' => alm__('Specifies the color scheme for a user&#8217;s admin screen'),
            ],
            'description' => [
                '_event_id' => 5107,
            ],
            'rich_editing' => [
                '_event_id'   => 5108,
                'description' => alm__('Specifies whether to enable the rich-editor for the user.'),
            ],
            $user_capabilities => [
                '_title'          => 'Capabilities',
                '_event_id'       => 5109,
                '_title_singular' => 'Capability',
            ],
            $user_settings => [
                '_title'          => 'User settings',
                '_event_id'       => 5110,
                '_title_singular' => 'User setting',
            ],
            'comment_shortcuts' => [
                '_event_id'   => 5111,
                'description' => alm__('Specifies whether to enable keyboard shortcuts for the user.'),
            ],
            'show_welcome_panel' => [
                '_event_id' => 5112,
            ],
            'syntax_highlighting' => [
                '_event_id'   => 5113,
                'description' => alm__('Specifies whether to enable the rich code editor for the user.'),
            ],
            'show_admin_bar_front' => [
                '_event_id'   => 5114,
                'description' => alm__('Specifies whether to show the admin bar on the front end for the user.'),
            ],
            'primary_blog' => [
                '_event_id'   => 5115,
                'description' => alm__('Specifies the user primary blog on a multisite installation'),
            ],
            'source_domain' => [
                '_event_id'   => 5116,
                'description' => alm__('Specifies the user primary blog domain on a multisite installation'),
            ],
        ];
    }

    /**
     * Set the custom user field event ID
     * @see \ALM\Controllers\Audit\Traits\EventList::createMainEventList()
     * 
     * @since 1.0.0
     */
    public function setUserCustomFieldEventsId()
    {
        $custom_field_list = $this->getCustomizedUserCustomFields();
        foreach ( $custom_field_list as $custom_field => $field_args )
        {
            if ( ! isset( $field_args['_event_id'] ) ) 
                continue;
            
            $event_hook_namespace = "user_{$custom_field}";
            $this->event_slug_list[ $event_hook_namespace ] = $field_args['_event_id'];
        }
    }

    /**
     * Customize the user event message field info.
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList::getEventMsgInfo()
     * 
     * @since 1.0.0
     */
    public function customizeUserEventMsgFieldInfo( $info, $event, $field, $context )
    {
        $info = '';
        
        /**
         * Format the user table fields
         */
        if ('email' == $field) 
            $field = 'user_email';

        // Properly parse the context
        if ( $this->strEndsWith( $field, $this->getEventFieldContexts() ) 
        && empty( $context ) )
        {
            $split_field = explode( '_', $field );
            $context     = end( $split_field );
        }

        $user_table_fields = $this->getUserTableFields();
        if ( isset( $user_table_fields[ $field ] ) )
        {
            $field_title = $user_table_fields[ $field ];
            $field_name  = rtrim( "{$field}_" . $context, '_' );
            $field_label = ucfirst( trim( "$context $field_title" ) );
            $info        = "{$field_label}: " . $this->getEventMsgArg( $event, $field_name, '', true );
        }
        else {
            switch ( $field )
            {
                case 'first_name':
                case 'last_name':
                    $format_field = ucfirst( str_replace( [ '_', '-' ], ' ', $field ) );
                    $info = $format_field . ': ' . $this->getEventMsgArg( $event, $field );
                    break;
                
                case 'profile_url':
                    $info = $this->getEventMsgArg( $event, 'profile_url' );
                    break;

                case 'view_user_caps':
                    $info = $this->getEventMsgArg( $event, 'view_user_caps', '', true );
                    break;
                
                case 'is_user_owner_of_account':
                    $info = 'Is user owner of account: ' . $this->getEventMsgArg( $event, 'is_user_owner_of_account' );
                    break;

                case 'meta_key':
                    $info = 'Custom field: ' . $this->getEventMsgArg( $event, 'meta_key' );
                    break;
                
                case 'meta_value':
                    $info        = empty( $context ) ? 'Custom' : ucfirst( $context ) . ' custom';
                    $info       .= ' field value';
                    $meta_field  = rtrim( 'meta_value_' . $context, '_' );

                    $info = "$info: " . $this->getEventMsgArg( $event, $meta_field, '', true );
                break;
                
                case 'meta_value_intended':
                    $info = 'The intended value the user wanted to update the custom field with, but failed: ' . $this->getEventMsgArg( $event, 'meta_value_intended' );
                break;

                case 'custom_field_added':
                    $info = 'Was custom field added?: ' . $this->getEventMsgArg( $event, 'was_custom_field_added' );
                    break;
                
                case 'custom_field_updated':
                    $info = 'Was custom field updated?: ' . $this->getEventMsgArg( $event, 'was_custom_field_updated' );
                    break;
                
                case 'custom_field_deleted':
                    $info = 'Was custom field deleted?: ' . $this->getEventMsgArg( $event, 'was_custom_field_deleted' );
                    break;

                case 'roles':
                    $info       = empty( $context ) ? 'Role' : ucfirst( $context ) . ' role';
                    $field      = rtrim( 'roles_' . $context, '_' );
                    $user_roles = (array) $this->getEventMsgArg( 'user', 'roles', [] );


                    $roles = empty( $context ) ? 
                        $user_roles : (array) $this->getEventMsgArg( $event, $field );

                    if ( empty( $roles ) ) {
                        $info = sprintf( "$info: %s", 'None' );
                    }
                    else {
                        // Pluralize the 'role' placeholder text
                        $user_roles_label = count( $roles ) > 1 ? $info . 's' : $info;

                        /**
                         * @todo
                         * Remember to run translation on the user roles, but probably when 
                         * displaying the logs
                         */
                        // $roles = array_map( 'alm_translate_user_role', $roles );

                        $info = "$user_roles_label: " . $this->parseValueForDb($roles);
                    }
                    break;

                case $this->getBlogPrefix() . 'capabilities':
                    $caps      = (array) $this->getEventMsgArg( $event, $field );
                    $main_caps = array_keys( $caps );
                    $added_cap = end( $main_caps );

                    $info = "Capability: $added_cap";
                    break;
                
                default:
                    // $info = $this->getEventMsgArg( $event, $field, '', true );
                    return $info;
            }
        }

        return $info;
    }

    /**
     * Get the ignorable user meta fields
     * @return array
     */
    public function getIgnorableUserMetaFields()
    {
        $blog_prefix = $this->getBlogPrefix();
        $list = [
            /**
             * The user session tokens (session_tokens) meta field is used for login operations.
             * The data contained in it is too sensitive to log, and since we are keeping a log 
             * of the user who logged-in, admins will be able to view all user-login events.
             * 
             * For debugging and development purposes, we will keep a reference of the 
             * session tokens connected to the user login event. 
             */
            'session_tokens',

            /**
             * Holds the time the user settings was updated.
             * It is being ignored because the 'user_settings' meta field 
             * event always shows the time the settings was updated.
             */
            $blog_prefix . 'user-settings-time',

            /**
             * Default password nag
             * 
             * Used for changing the user's password.
             * 
             * For example: when a new user is registered, the 'default_password_nag' 
             * is used to determine whether or not the new user needs to update the 
             * default password.
             * 
             * So since we're monitoring new user registration and password reset,
             * there's no need to keep log whenever the 'default_password_nag' user meta 
             * field gets updated.
             */
            'default_password_nag',

            /**
             * User level has been replaced with and Role and Capability.
             * All changes to a user role or caps are recoded in the "roles" 
             * and "{$wpdb->blog_prefix}_capabilities" user meta
             */
            $blog_prefix . 'user_level',
        ];
        
        /**
         * The '_new_email' user meta field is used for handling user email update request.
         * We are using the main user 'user_email' column in the users table to 
         * handle the changes, which is also connected to the '_new_email' meta field.
         * 
         * So during update, we have to ignore it in other to avoid duplicated event,
         * except when it is being deleted.
         */
        if ( '_new_email' != $this->getConstant('ALM_USER_META_DELETED') ) {
            $list[] = '_new_email';
        }

        return $list;
    }

    /**
     * Create list of user meta fields to ignore.
     * 
     * This is not applicable when verbose logging (@see PluginFactory::isVerboseLoggingEnabled()) 
     * is enabled.
     * 
     * @since 1.0.0
     */
    public function __ignorableUserMetaFields( $ignore, $meta_id, $meta_key )
    {
        /**
         * Disable this functionality if verbose logging is enabled
         */
        if ($this->isVerboseLoggingEnabled()) return false;

        /**
         * Bail the user metadata field if we are currently aggregating user metadata
         */
        if ( $this->getConstant('ALM_IS_USER_PROFILE_METADATA_AGGREGATION') ) 
            return true;

        /**
         * Bail if the global log aggregation constant is set
         */
        if (!empty( $this->getConstant('ALM_ALLOW_LOG_AGGREGATION'))) 
            return true;

        /**
         * We have to make sure already aggregated user metadata are not fired individually.
         * 
         * However, will we be specific about what admin screen locations to perform the 
         * log aggregation on.
         */
        $user_screens = [
            '/wp-admin/users.php',
            '/wp-admin/user-new.php',
            '/wp-admin/profile.php',
            '/wp-admin/network/users.php',
            '/wp-admin/network/user-new.php',
        ];

        $user_custom_fields = $this->getCustomizedUserCustomFields();

        if ($this->isLogAggregatable() 
        && isset( $user_custom_fields[ $meta_key ]) 
        && $this->isPageScreenActive($user_screens) ) 
            return true;

        return $this->canIgnoreUserMetaStrictly( $meta_key );
    }

    /**
     * Ignore user meta fields strictly. Will prevent any user meta event from 
     * performing any operation, including profile update aggregation.
     * 
     * @since 1.0.0
     * 
     * @return bool
     */
    public function canIgnoreUserMetaStrictly( $meta_key )
    {
        /**
         * Disable this functionality if verbose logging is enabled
         */
        if ($this->isVerboseLoggingEnabled()) return false;

        return in_array( $meta_key, $this->getIgnorableUserMetaFields(), true );
    }

    /**
     * Customize the user event messages arguments which is used to build the log message
     * 
     * @see \ALM\Controllers\Audit\Traits\EventList::generateEventMessageForDb()
     * 
     * @since 1.0.0
     */
    public function customizeUserEventMsgArgs( $raw_msg_args, $event, $event_data )
    {
        $msg   = '';
        $field = $this->getEventMsgArg($event, 'meta_key');

        // Bail the raw message arguments if the meta key is empty
        if (empty( $field ))
            return $raw_msg_args;

        $action = $this->_getActiveEventData('action');

        // The untransformed event message arguments
        $msg_args = $raw_msg_args;
            
        $blog_prefix                 = $this->getBlogPrefix();
        $old_user_data               = '';
        $updated_user_data           = '';
        $user_settings_key           = $blog_prefix . 'user-settings';
        $custom_field_list           = $this->getCustomizedUserCustomFields();
        $user_settings_state         = '';
        $has_custom_field_customizer = isset($custom_field_list[$field]);

        $field_title                 = $field;
        $is_user_settings_field      = false;

        if ($has_custom_field_customizer)
        { 
            // Skip the first name or last name event message fields when they are active
            if (in_array( $field, ['first_name', 'last_name'], true )) 
                unset( $msg_args[ $field ] );

            $field_title = $this->getVar(
                $custom_field_list, '_title', $this->makeFieldReadable($field)
            );

            $context                = []; // Context of the event message
            $field_target           = $field_title;
            $is_user_settings_field = $user_settings_key === $field;

            if ( $is_user_settings_field )
            {
                $field_target                = $user_settings_key;
                $old_user_settings           = $this->getEventMsgArg( $event, 'meta_value_previous' );
                $new_user_settings           = $this->getEventMsgArg( $event, 'meta_value' );
                $_old_user_settings          = [];
                $_new_user_settings          = [];
                $has_custom_field_customizer = false; // Don't format the meta key

                if ( !empty( $new_user_settings ) && is_string( $new_user_settings ) )
                {
                    parse_str( $old_user_settings, $_old_user_settings );
                    parse_str( $new_user_settings, $_new_user_settings );

                    $updated_user_settings = $this->arrayDiffAssocRecursive(
                        $_new_user_settings,
                        $_old_user_settings
                    );

                    // If the updated settings is still empty, 
                    // Let's interchange the array position and check again
                    if ( empty( $updated_user_settings ) )
                    {
                        $updated_user_settings = $this->arrayDiffAssocRecursive(
                            $_old_user_settings,
                            $_new_user_settings
                        );
                    }

                    if ( empty( $updated_user_settings ) ) {
                        $user_settings_state = '';
                    }
                    else {
                        $user_settings_state = 'updated';

                        foreach ( $updated_user_settings as $key => $value ) {
                            $updated_user_data .= "$key = $value, ";
                        }

                        $updated_user_data = rtrim( $updated_user_data, ', ' );
                        
                        foreach ( $_old_user_settings as $key => $value ) {
                            if ( isset( $updated_user_settings[ $key ] ) ) {
                                $old_user_data .= "$key = $value, ";
                            }
                        }

                        $old_user_data = rtrim( $old_user_data, ', ' );

                        unset( $value, $key );
                    }
                }
                else {
                    $user_settings_state = !empty( $old_user_settings ) ? 'deleted' : '';
                }
            }
        }
        else {
            switch ( $field )
            {
                case $blog_prefix . 'user_level':
                    $field_target = 'User level ';
                    break;

                default:
                    $field_target = "{$field_title} ";
                    break;
            }
        }

        // Append 'custom' to the field target
        $field_target .= $has_custom_field_customizer ? '' : ' custom';

        if ( 'create' == $action )
        {
            $msg = "Tried to add the $field_target field to a user (%s), but the request was unsuccessful.";
        }
        elseif ( 'created' == $action )
        {
            $msg = "Added the $field_target field to a user (%s).";
        }
        elseif ( 'modify' == $action )
        {
            $msg   = "Tried to update a user (%s) $field_target field value, but the request was unsuccessful.";
            $context = [ 'intended', 'current' ];
        }
        elseif ( 'modified' == $action )
        {
            $msg     = "Changed the user (%s) $field_target field.";
            $context = [ 'previous', 'new' ];

            if ( $is_user_settings_field && '' === $user_settings_state  ) {
                $msg  = 'Triggered an update on the user (%s) settings custom field without making any changes.';

                $msg .= $this->explainEventMsg(
                    ' (The update was triggered without modifying the previous user settings value).'
                );
            }

            if ( $is_user_settings_field && 'deleted' == $user_settings_state ) {
                $msg = 'Deleted the user-settings custom field from the user (%s).';
            }
        }
        elseif ( 'delete' == $action )
        {
            $msg = "Tried to delete the $field_target field from a user (%s), but the request was unsuccessful.";
        }
        elseif ( 'deleted' == $action )
        {
            $msg = "Deleted the $field_target field from a user (%s).";
        }
        else {
            // Do nothing
        }
        
        $msg_args['meta_key'] = 'User custom field key: ' . $field;
        
        // Apply the event message context to previous and new values
        if (!empty($context) && !empty($msg))
        {
            $msg_args['meta_value'] = $this->formatMsgField($event, $field, $context[1]);

            if ($is_user_settings_field)
            {
                $old_user_data                   = empty($old_user_data)     ? '' : $old_user_data;
                $updated_user_data               = empty($updated_user_data) ? '' : $updated_user_data;

                $msg_args['meta_value']          = $updated_user_data;
                $msg_args['meta_value_previous'] = $old_user_data;
            }

            // Update the meta value context field
            foreach ($context as $c)
            {
                $meta_value_key = "meta_value_{$c}";

                /**
                 * Bail the user settings meta key previous value context
                 */
                if ('previous' === $c && $is_user_settings_field)
                    continue;

                if (isset($msg_args[$meta_value_key])) {
                    $msg_args[ $meta_value_key ] = $this->formatMsgField(
                        $event, $field, $c, $has_custom_field_customizer
                    );
                }
            }
        }

        if (!empty($msg)) {
            $msg_args['_main']           = $msg;
            $msg_args['_main_processed'] = true;
        }

        return $msg_args;
    }

    /**
     * Aggregate user meta fields (newly created or updated)
     * @return string Aggregated user meta fields
     */
    protected function __aggregateUserMetaFields()
    {
        $updated_str = '';
        if (!$this->isUserProfileDataAggregationActive())
            return $updated_str;

        $_new_val                    = '';
        $line_break                  = $this->getEventMsgLineBreak();
        $blog_prefix                 = $this->getBlogPrefix();
        $update_type                 = $this->getConstant('ALM_IS_USER_PROFILE_UPDATE_AGGREGATION');
        $cap_meta_field              = $blog_prefix . 'capabilities';
        $customized_user_meta_fields = $this->getCustomizedUserCustomFields();

        foreach ($this->user_data_aggregation as $field => $value)
        {
            $is_cap_field    = $cap_meta_field == $field;
            $use_field_title = $this->getVar($value, 'title');

            /**
             * If the requested field value is set, then it means this is 
             * a pre-update request that requires confirmation before the 
             * changes will be committed
             */
            $field_has_confirmation = isset($value['requested']);
            if ( $field_has_confirmation )
            {
                $new_val      = $value['current'];
                $previous_val = $value['requested'];
            }
            else {
                $new_val      = $value['new'];
                $previous_val = $value['previous'];
            }

            // Maybe previous user metadata is set but empty, so we have to retrieve it
            if ( '' === $previous_val 
            && isset( $this->_user_profile_metadata[ $field ] ) 
            && is_array( $this->_user_profile_metadata[ $field ] ) )
            {
                // Just incase there's more than one value in the array
                $previous_val = $this->_user_profile_metadata[ $field ];
            }

            $previous_val = $this->parseValueForDb($previous_val, $is_cap_field);

            // The custom field key
            $updated_str .= sprintf('Custom field key: %s', $field);

            // Line break;
            $updated_str .= $line_break;
            
            /**
             * Don't customize any user meta fields that is not part of the 
             * default customized user meta field list
             */
            $is_user_field                 = array_key_exists($field, $this->getUserTableFields());
            $is_customized_user_meta_field = isset($customized_user_meta_fields[$field]);

            if ($is_user_field || $is_customized_user_meta_field)
            {
                if (!$is_user_field 
                && isset( $customized_user_meta_fields[ $field ]['_title'] ))
                {
                    $field_label = $customized_user_meta_fields[ $field ]['_title'];

                    if ( is_array( $value ) 
                    && count( $value ) < 2 
                    && isset( $customized_user_meta_fields[ $field ]['_title_singular']) )
                    {
                        $field_label = $customized_user_meta_fields[ $field ]['_title_singular'];
                    }
                }
                else {
                    $field_label = str_replace($this->getBlogPrefix(), '', $field);
                    $field_label = strtolower($this->makeFieldReadable($field_label));
                }
            }
            else {
                $field_label = $field;
            }

            if ( ! empty( $use_field_title ) ) {
                $field_label = $use_field_title;
            }

            if ( 'update' == $update_type ) {
                $previous_field_label  = $field_has_confirmation ? 'Requested' : 'Previous';
                $updated_str          .= "{$previous_field_label} {$field_label}: $previous_val";
                $updated_str          .= $line_break; // Line break;
            }

            // New val may be an array/object
            $_new_val = $this->parseValueForDb($new_val, $is_cap_field);

            $new_field_label = ( 'create' == $update_type ) ? 
                $field_label 
                : 
                ( $field_has_confirmation ? 'Current ' : 'New ' ) . $field_label;

            $updated_str .= $new_field_label . ': ' . $_new_val;
            $updated_str .= $line_break; // Line break;
        }

        return $updated_str;
    }   

    /**
     * Determines whether the current updated user profile fields requires confirmation 
     * before the changes are committed.
     * 
     * @since 1.0.0
     * 
     * @param string  $user_field Specifies the updated user field
     * @param WP_User $new_user_data Specifies the updated user data
     * @param WP_User $new_user_data Specifies the old user data before the update
     * 
     * @return bool True if the updated user field requires confirmation. Otherwise false.
     */
    protected function userProfileFieldUpdateRequiresConfirmation( $user_field, $new_user_data, $old_user_data )
    {
        /**
         * Ignore if user is not editing their own profile, since this is 
         * applicable only when the current user is editing their own profile.
         */
        if ( ! $this->isUserPersonalProfileActive() ) 
            return false;

        // Bail out all fields by default
        $is_confirmation_required = false;

        /**
         * @todo
         * Updating the [user_login] field is not allowed by default,
         * but some plugins may enable such a feature, so we may have to 
         * check for this in third party user-related plugins we support.
         */

        /**
         * Check user email field
         */
        if ('user_email' == $user_field)
        {
            $current_user_email   = $this->getVar( $old_user_data, $user_field );
            $requested_user_email = $this->getVar(
                get_user_meta( $new_user_data->ID, '_new_email', true ), 'newemail', ''
            );
            
            $is_confirmation_required = (0 !== strcasecmp( $requested_user_email, $current_user_email ));
        }

        /**
         * Filters whether the a user field requires confirmation.
         * 
         * @since 1.0.0
         * 
         * Accepts 4 parameters:
         * 
         * @param bool    $confirm       Whether confirmation is required before updating 
         *                               user profile field
         * 
         * @param string  $user_field    Specifies the updated user field
         * 
         * @param WP_User $new_user_data Specifies the updated user data
         * 
         * @param WP_User $new_user_data Specifies the old user data before the update
         */
        $is_confirmation_required = apply_filters(
            'alm/user/profile/field/update/has_confirmation',
            $is_confirmation_required,
            $user_field,
            $new_user_data,
            $old_user_data
        );

        return $is_confirmation_required;
    }

    /**
     * Enable the user active event log aggregation
     * 
     * @since 1.0.0
     * 
     * @param mixed $flag_value Specifies the value to use to setup the aggregation flag.
     *                          Default: true
     */
    public function setupUserLogAggregationFlag( $flag_value = true )
    {
        $this->setConstant('ALM_ALLOW_LOG_AGGREGATION', $flag_value);
    }

    /**
     * Check whether the add new user admin screen is active.
     * Works on multisite and non-multisite installation
     * 
     * @since 1.0.0
     * 
     * @param bool $bail_ajax Determine whether to bail the check if doing ajax request
     * 
     * @return bool
     */
    public function isAddNewUserAdminScreen( $bail_ajax = true )
    {
        if ( wp_doing_ajax() && $bail_ajax )  return true;
        if ( !$this->is_admin ) return false;

        $screen = $this->getCurrentPageScriptName();
        return ('user-new.php' == $screen);
    }













    /**
     * @todo
     * Todo events
     */
    protected function __setupUserEvents()
    {
        $this->event_list[] = [
            'roles' => [
                'title'   => [
                    'plural'   => 'Roles',
                    'singular' => 'Role',
                ],
                'events'  => [
                    'role_deleted',
                    'role_created',

                    /**
                     * This will check whether any new capability has been added or removed from a specific role.
                     */
                    'role_updated',
                ],
            ],

            /**
             * Terms in WordPress may include:
             * 	1. Tag (post_tag)
             * 	2. Link (link_category)
             * 	3. Category (category)
             * 	4. Post Format (post_format)
             * 	5. Navigation Menu (nav_menu)
             * 
             * @link https://developer.wordpress.org/reference/functions/get_the_terms/
             * 
             * @link https://developer.wordpress.org/reference/functions/wp_insert_term/
             * 
             * @link https://developer.wordpress.org/reference/functions/get_the_tags/
             */
            'terms' => [ // done
                'term_created',
                'term_updated',
                'term_moved',
                'term_deleted',

                /**
                 * Errors associated with the terms during its creation time.
                 * This will check if the terms insertion failed, update failed, deletion failed and 
                 * term already exists (duplication).
                 */
                'terms_error',
            ],

            'posts' => [
                'post_moved',
                'post_error',

                /**
                 * For post views statistics
                 */
                'post_viewed',

                'post_created',
                'post_updated',
                'post_deleted',
                'post_trashed',
                'post_untrashed',

                /**
                 * Both Gutenberg and Classic Editor
                 */
                'post_opened_in_editor',
            ],

            'themes' => [
                /**
                 * Create UI to disable the theme editor
                 */
                'theme_editor',

                /**
                 * Monitor whether someone is trying to access the theme editor even when it is disabled 
                 */
                'theme_editor_disabled',

                'theme_updated',
                'theme_switched',
                'theme_activated',
                'theme_installed',
                'theme_deactivated',
                'theme_uninstalled',
            ],

            'plugins' => [
                /**
                 * Create UI to disable the plugin editor
                 */
                'plugin_editor',

                /**
                 * Monitor whether someone is trying to access the plugin editor even when it is disabled 
                 */
                'plugin_editor_disabled',

                'plugin_updated',
                'plugin_activated',
                'plugin_installed',
                'plugin_deactivated',
                'plugin_uninstalled',
            ],

            'widgets' => [
                'widget_order',
                'widget_active',
                'widget_deleted',
                'widget_created',

                // Only for active widgets
                'widget_updated',

                'widget_inactive',
            ],

            'database' => [
                'db_error',
                'db_table_created',
                'db_table_deleted',

                /**
                 * This is used to check whether the database table was altered,
                 * like renaming table name, index,
                 * prefix, etc.
                 */
                'db_table_altered',
            ],

            'wp_core_updates' => [
                'wp_updated',
            ],

            'comments' => [
                'comment_created',
                'comment_spammed',
                'comment_updated',
                'comment_deleted',
                'comment_trashed',
                'comment_approved',
                'comment_untrashed',
                'comment_unspammed',
                'comment_unapproved',
            ],

            'php_errors' => [
                'php_error',
                'php_exception',
            ],

            'customizer' => [
                /**
                 * This two events will be aggregated
                 */
                'customizer_opened',
                'customizer_closed',
            ],

            /**
             * @link https://developer.wordpress.org/reference/functions/get_taxonomies/
             */
            'taxonomies' => [ // done
                'taxonomy_created',
                'taxonomy_updated',
                'taxonomy_deleted',
            ],

            'error_pages' => [
                'error_page_403',
                'error_page_404',
                'error_page_405',
                'error_page_408',
                'error_page_500',
                'error_page_502',
                'error_page_504',
            ],

            'file_monitor' => [
                'file_error',
                'file_deleted',

                // Uploaded or created files
                'file_created',

                'file_updated',

                /**
                 * Sometimes we may check file headers and see whether it is actually the correct file type,
                 * this event will log such entry.
                 */
                'file_invalid',
            ],

            'custom_fields' => [
                'custom_field_created',
                'custom_field_updated',
                'custom_field_deleted',
            ],

            'media_library' => [
                /**
                 *  When new files are uploaded using the Media Library.
                 * This will trigger the file monitor and log how the file was uploaded.
                 */
                'media_created',

                /**
                 * Log event when media meta data is updated.
                 * 
                 * Also, If the real media file on the server directory is modified, then this will trigger 
                 * the file monitor and log how the file was updated.
                 */
                'media_updated',

                /**
                 * This will trigger the file monitor event instead and log how the file was deleted.
                 */
                'media_deleted',
            ],

            'attachments' => [
                'attachment_moved',
                'attachment_cloned',
                'attachment_created',
                'attachment_updated',
                'attachment_deleted',
            ],

            'wp_core_settings' => [
                'media',
                'privacy',
                'general',
                'writing',
                'reading',
                'discussion',
                'permalinks',
            ],

            'wp_core_tools' => [
                'import',
                'export',
                'install',
            ],

            'network' => [
                'network_installation',
            ],

            'links_manager' => [],
            'link_manager_enabled' => [

            ],

            /**
             * @todo set_theme_mod()
             */
            'theme_mod' => [],
        ];
    }
}