<?php
namespace ALM\Controllers\Audit\Events\Groups;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Tools Events
 * @since   1.0.0
 */
trait ToolsEvents
{
    /**
     * Specifies the user request expiration time limit.
     * 
     * @var   int 
     * @since 1.0.0
     */
    protected $user_key_expiration_time = 0;

    /**
     * This method is called automatically by the 
     * {@see ALM\Controllers\Audit\Traits\EventList::registerAllEventGroups()} method
     */
    protected function initToolsEvents()
    {
        $this->setupToolsEvents();
        $this->registerToolsEvents();
    }

    /**
     * Register the tools events
     */
    protected function setupToolsEvents()
    {
        $this->event_list['tool'] = [
            'title'           => 'Tool Events',
            'group'           => 'tool',
            'object'          => 'tool',
            'description'     => alm__('Responsible for logging all tool related activities such as import, export, etc.'),
            'object_id_label' => 'Tool ID',

            /**
             * @todo
             * 
             * Events:
             * - duplicated request entry
             * - failed request entry
             */
            'events' => [
                /**
                 * Fires after the site data have been exported successfully
                 * 
                 * @see   ABSPATH . 'wp-admin/export.php'
                 * @since 1.0.0 
                 */
                'alm_export_data' => [
                    'title'               => '%s exported',
                    'action'              => 'data_exported',
                    'event_id'            => 5801,
                    'severity'            => 'critical',

                    'screen'              => ['admin'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['export'],

                    'message'             => [
                        '_main'                 => 'Exported %s from the site',

                        '_space_start'          => '',
                        'export_info'           => ['export_info'],
                        '_space_end'            => '',
                    ],

                    'event_handler' => [
                        'num_args' => 1,
                    ],
                ],

                /**
                 * Fires after creating the request to export personal data.
                 * 
                 * @see   ABSPATH . 'wp-admin/export-personal-data.php'
                 * @since 1.0.0 
                 */
                'alm_create_personal_data_export_request' => [
                    'title'               => 'Personal data export request created',
                    'action'              => 'personal_data_export_request_created',
                    'event_id'            => 5802,
                    'severity'            => 'notice',

                    'screen'              => ['admin'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['export_others_personal_data'],

                    'message'             => [
                        '_main'                     => 'Created the personal data export request for the user: %s.',

                        '_space_start'              => '',
                        'user_request_id'           => ['object_id'],
                        'confirmation_email'        => ['confirmation_email'],
                        'request_expiration_time'   => ['request_expiration_time'],
                        'user_id'                   => ['user_id'],
                        'user_login'                => ['user_login'],
                        'display_name'              => ['display_name'],
                        'roles'                     => ['roles'],
                        'first_name'                => ['first_name'],
                        'last_name'                 => ['last_name'],
                        'user_email'                => ['user_email'],
                        'profile_url'               => ['profile_url'],
                        'is_user_owner_of_account'  => ['is_user_owner_of_account'],
                        'user_primary_site'         => ['primary_blog'],
                        'primary_site_name'         => ['primary_blog_name'],
                        'primary_site_url'          => ['primary_blog_url'],
                        'source_domain'             => ['source_domain'],
                        '_space_end'                => '',

                        // 'post_data'                 => ['post_data'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires after the personal data export request is resent.
                 * 
                 * @see   ABSPATH . 'wp-admin/export-personal-data.php'
                 * @since 1.0.0
                 */
                'alm_resend_personal_data_export_request' => [
                    'title'               => 'Personal data export request resent',
                    'action'              => 'personal_data_export_request_resent',
                    'event_id'            => 5803,
                    'severity'            => 'notice',

                    'screen'              => ['admin'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['export_others_personal_data'],

                    /**
                     * Translation arguments
                     */
                    '_translate' => [
                        '_main' => [
                            'plural' => 'Resent the email confirmation request for exporting the personal data of the following users.',
                        ],
                    ],

                    'message'             => [
                        '_main'                     => 'Resent the email confirmation request for exporting the personal data of the user: %s.',

                        '_space_start'              => '',
                        'user_request_id'           => ['object_id'],
                        'request_expiration_time'   => ['request_expiration_time'],
                        'user_id'                   => ['user_id'],
                        'user_login'                => ['user_login'],
                        'display_name'              => ['display_name'],
                        'roles'                     => ['roles'],
                        'first_name'                => ['first_name'],
                        'last_name'                 => ['last_name'],
                        'user_email'                => ['user_email'],
                        'profile_url'               => ['profile_url'],
                        'is_user_owner_of_account'  => ['is_user_owner_of_account'],
                        'user_primary_site'         => ['primary_blog'],
                        'primary_site_name'         => ['primary_blog_name'],
                        'primary_site_url'          => ['primary_blog_url'],
                        'source_domain'             => ['source_domain'],
                        '_space_end'                => '',

                        // 'post_data'                 => ['post_data'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires after the personal data export request is completed.
                 * 
                 * @see   ABSPATH . 'wp-admin/export-personal-data.php'
                 * @since 1.0.0
                 */
                'alm_personal_data_export_request_completed' => [
                    'title'               => 'Personal data export request completed',
                    'action'              => 'personal_data_export_request_completed',
                    'event_id'            => 5804,
                    'severity'            => 'notice',

                    'screen'              => ['admin'],
                    'user_state'          => 'both',
                    'logged_in_user_caps' => ['export_others_personal_data'],

                    /**
                     * Translation arguments
                     */
                    '_translate' => [],

                    'message'             => [
                        '_main'                     => 'Marked the request for exporting the personal data of the user: (%s) as completed.',

                        '_space_start'              => '',
                        'user_request_id'           => ['object_id'],
                        'request_expiration_time'   => ['request_expiration_time'],
                        'user_id'                   => ['user_id'],
                        'user_login'                => ['user_login'],
                        'display_name'              => ['display_name'],
                        'roles'                     => ['roles'],
                        'first_name'                => ['first_name'],
                        'last_name'                 => ['last_name'],
                        'user_email'                => ['user_email'],
                        'profile_url'               => ['profile_url'],
                        'is_user_owner_of_account'  => ['is_user_owner_of_account'],
                        'user_primary_site'         => ['primary_blog'],
                        'primary_site_name'         => ['primary_blog_name'],
                        'primary_site_url'          => ['primary_blog_url'],
                        'source_domain'             => ['source_domain'],
                        '_space_end'                => '',

                        // 'post_data'                 => ['post_data'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],
                
                /**
                 * Fires after the personal data export request is failed.
                 * 
                 * @see   ABSPATH . 'wp-admin/export-personal-data.php'
                 * @since 1.0.0
                 */
                'alm_personal_data_export_request_failed' => [
                    'title'               => 'Personal data export request failed',
                    'action'              => 'personal_data_export_request_failed',
                    'event_id'            => 5805,
                    'severity'            => 'notice',

                    'is_system_event'    => true,

                    'screen'              => ['admin'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['export_others_personal_data'],

                    /**
                     * Translation arguments
                     */
                    '_translate' => [],

                    'message'             => [
                        '_main'                     => 'Marked the request for exporting the personal data of the user: (%s) as failed.',

                        '_space_start'              => '',
                        'user_request_id'           => ['object_id'],
                        'request_expiration_time'   => ['request_expiration_time'],
                        'user_id'                   => ['user_id'],
                        'user_login'                => ['user_login'],
                        'display_name'              => ['display_name'],
                        'roles'                     => ['roles'],
                        'first_name'                => ['first_name'],
                        'last_name'                 => ['last_name'],
                        'user_email'                => ['user_email'],
                        'profile_url'               => ['profile_url'],
                        'is_user_owner_of_account'  => ['is_user_owner_of_account'],
                        'user_primary_site'         => ['primary_blog'],
                        'primary_site_name'         => ['primary_blog_name'],
                        'primary_site_url'          => ['primary_blog_url'],
                        'source_domain'             => ['source_domain'],
                        '_space_end'                => '',

                        // 'post_data'                 => ['post_data'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires after the personal data export request is failed.
                 * 
                 * @see   ABSPATH . 'wp-admin/export-personal-data.php'
                 * @since 1.0.0
                 */
                 'alm_personal_data_export_request_deleted' => [
                    'title'               => 'Personal data export request deleted',
                    'action'              => 'personal_data_export_request_deleted',
                    'event_id'            => 5806,
                    'severity'            => 'notice',

                    'is_system_event'    => false,

                    'screen'              => ['admin'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['export_others_personal_data'],

                    /**
                     * Translation arguments
                     */
                    '_translate' => [],

                    'message'             => [
                        '_main'                     => 'Deleted the request for exporting the personal data of the user: (%s).',

                        '_space_start'              => '',
                        'user_request_id'           => ['object_id'],
                        'request_expiration_time'   => ['request_expiration_time'],
                        'user_id'                   => ['user_id'],
                        'user_login'                => ['user_login'],
                        'display_name'              => ['display_name'],
                        'roles'                     => ['roles'],
                        'first_name'                => ['first_name'],
                        'last_name'                 => ['last_name'],
                        'user_email'                => ['user_email'],
                        'profile_url'               => ['profile_url'],
                        'is_user_owner_of_account'  => ['is_user_owner_of_account'],
                        'user_primary_site'         => ['primary_blog'],
                        'primary_site_name'         => ['primary_blog_name'],
                        'primary_site_url'          => ['primary_blog_url'],
                        'source_domain'             => ['source_domain'],
                        '_space_end'                => '',

                        // 'post_data'                 => ['post_data'],
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],

                /**
                 * Fires after the personal data of a user is downloaded.
                 * 
                 * @see   ABSPATH . 'wp-admin/export-personal-data.php'
                 * @since 1.0.0
                 */
                'alm_personal_data_export_file_downloaded' => [
                    'title'               => 'Personal data export file downloaded',
                    'action'              => 'personal_data_export_file_downloaded',
                    'event_id'            => 5807,
                    'severity'            => 'notice',

                    'is_system_event'    => false,

                    'screen'              => ['admin'],
                    'user_state'          => 'logged_in',
                    'logged_in_user_caps' => ['export_others_personal_data'],

                    /**
                     * Translation arguments
                     */
                    '_translate' => [],

                    'message'             => [
                        '_main'                     => 'Downloaded the personal data export file of the user (%s) generated from the %s page.',

                        '_space_start'              => '',
                        'user_request_id'           => ['object_id'],
                        'send_as_email'             => ['send_as_email'],
                        'export_file_url'           => ['export_file_url'],
                        '_space_line'               => '',
                        'user_id'                   => ['user_id'],
                        'user_login'                => ['user_login'],
                        'display_name'              => ['display_name'],
                        'roles'                     => ['roles'],
                        'first_name'                => ['first_name'],
                        'last_name'                 => ['last_name'],
                        'user_email'                => ['user_email'],
                        'profile_url'               => ['profile_url'],
                        'is_user_owner_of_account'  => ['is_user_owner_of_account'],
                        'user_primary_site'         => ['primary_blog'],
                        'primary_site_name'         => ['primary_blog_name'],
                        'primary_site_url'          => ['primary_blog_url'],
                        'source_domain'             => ['source_domain'],
                        '_space_end'                => '',
                    ],

                    'event_handler' => [
                        'num_args' => 3,
                    ],
                ],
            ]
        ];
    }

    /**
     * Check whether the post type is actually a user privacy request
     * 
     * @param array $post The post data
     *
     * @return bool
     */
    public function isPostUserPrivacyRequest( $post ) {
        /**
         * Ignore if the privacy action is invalid
         */
        $action_name = $this->sanitizeOption($this->getVar($post, 'post_name', ''));
    
        return in_array($action_name, _wp_privacy_action_request_types(), true);
    }
     

    /*
     * Register the widget custom events
     * 
     * @see   export_wp()
     * @since 1.0.0
     */
    protected function registerToolsEvents()
    {
        // Listen for the export event when the $_GET['download'] var is set
        if (isset($_GET['download'])) 
        {
            add_action(
                'export_wp',
                function ( $args ) {
                    /**
                     * Hook into the export wp filename filter to ensure that the
                     * export actually happened.
                     * 
                     * @see the_generator()
                     */
                    add_filter(
                        'export_wp_filename',
                        function ( $wp_filename, $sitename, $date ) use ( $args ) {

                            /**
                             * Trigger the data export event
                             * 
                             * @since 1.0.0
                             */
                            do_action(
                                'alm_export_data',
                                [
                                    'date'        => $date,
                                    'sitename'    => $sitename,
                                    'export_args' => $args,
                                    'wp_filename' => $wp_filename,
                                ]
                            );

                            return $wp_filename;
                        },
                        10,
                        3
                    );
                }
            );
        }

        /**
         * Get the user request expiration time.
         * 
         * @see _wp_personal_data_cleanup_requests()
         * 
         * statuses: 'request-pending' | 'request-confirmed'
         */
        $this->user_key_expiration_time = (int) apply_filters(
            'user_request_key_expiration', DAY_IN_SECONDS
        );

        $self = &$this;
        
        // Listen for the export personal data creation request
        $post_type = 'user_request';
        add_action(
            "save_post_{$post_type}",
            function ( $post_ID, $post, $update ) use (&$self) {              
                /**
                 * Ignore if the privacy action is invalid
                 */
                if (!$self->isPostUserPrivacyRequest($post))
                    return;

                $self->maybeSetPostTypeEventWatch();

                if (!empty($_POST)) {
                    // Bulk action
                    $action = sanitize_key(wp_unslash($self->getVar($_POST, 'action', '')));

                    if (isset($_POST['privacy_action_email_retry'])) {
                        $action = 'resend';
                    }

                    switch ( $action ) {
                        case 'resend':
                            /*
                             * Fires when the personal data export request is resent
                             */
                            $target_event = 'alm_resend_personal_data_export_request';
                            break;

                        case 'completed':
                            /*
                             * Fires when the personal data export request is completed.
                             */
                            $target_event = 'alm_personal_data_export_request_completed';
                            break;

                        default:
                            $target_event = false;
                            break;
                    }

                    /*
                     * Fire the target event.
                     */
                    if ($target_event) {
                        return do_action(
                            $target_event,
                            $post_ID, $post, $update
                        );
                    }
                }

                /*
                 * When creating a request that requires email confirmation,
                 * let's fire this just once.
                 */
                if (isset($_REQUEST['send_confirmation_email'])) {
                    if (!$update) return;
                }

                $post_status = $self->getVar($post, 'post_status', '');

                switch ($post_status) {
                    case 'request-pending':
                        $target_event = 'alm_create_personal_data_export_request';
                        break;
                        
                    case 'request-completed':
                        $target_event = 'alm_personal_data_export_request_completed';
                        break;

                    case 'request-failed':
                        $target_event = 'alm_personal_data_export_request_failed';
                        break;

                    default:
                        $target_event = false;
                        break;
                }

                if ($target_event) {
                    do_action(
                        $target_event,
                        $post_ID, $post, $update
                    );
                }
            },
            10,
            3
        );

        // Listen for the data export delete request.
        add_action(
            'deleted_post',
            function ($post_ID) use (&$post_type, &$self) {
                $post = $self->_getPreEventData('delete_post');
                
                if ($self->getVar($post, 'post_type') !== $post_type) {
                    return;
                }
                
                /**
                 * Ignore if the privacy action is invalid
                 */
                if (!$self->isPostUserPrivacyRequest($post)) return;

                $self->maybeSetPostTypeEventWatch();

                return do_action(
                    'alm_personal_data_export_request_deleted',
                    $post_ID, $post, false
                );
            },
            10,
            3
        );

        // Listen for the {download personal data request}
        add_action(
            "wp_privacy_personal_data_export_file",
            function ( $request_id ) {
                $post = get_post($request_id, 'ARRAY_A');
                if (empty($post)) return;

                do_action(
                    'alm_personal_data_export_file_downloaded',
                    $request_id, $post
                );
            },
            99,
            7
        );
    }

    /**
     * Get the personal data export page url.
     * 
     * @since 1.0.0
     * 
     * @param string $search If specified, the search query will be added to the 
     *                       returned url.
     * 
     * @return string The personal data export page url.
     */
    public function getPersonalDataExportPageUrl( $search = '' )
    {
        $personal_data_export_page_url  = $this->getWpCoreSettingsPage('export-personal-data');
        $personal_data_export_page_url .= !empty($search) ? "?s={$search}" : '';

        return $personal_data_export_page_url;
    }
}