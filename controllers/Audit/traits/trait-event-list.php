<?php
namespace ALM\Controllers\Audit\Traits;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Event List
 * @since   1.0.0
 */

trait EventList
{
    /**
     * Auditable events list
     * @var array
     * @since 1.0.0
     */
    protected $event_list = [];

    /**
     * Specifies arguments that will be used to customize the current event message.
     * @var array
     * @since 1.0.0
     */
    protected $customize_event_msg_args = [];

    /**
     * Specifies stateful metadata value that is based on a predefined context
     * @var string
     * @since 1.0.0
     */
    protected $metadata_value_current  = '';
    protected $metadata_value_deleted  = '';
    protected $metadata_value_intended = '';
    protected $metadata_value_previous = '';

    /**
     * Determine whether the current user ID that triggers the event is same as 
     * the targeted user ID referenced in the event.
     * @var bool
     * @since 1.0.0
     */
    protected $is_current_user_and_event_target_user_the_same = false;

    /**
     * Aggregated event groups
     * @var array
     * @since 1.0.0
     */
    protected $aggregated_event_groups = [];

    /**
     * Main event list
     * @var array
     * @since 1.0.0
     */
    protected $main_event_list = [];

    /**
     * Event Slug List
     * @var array
     * @since 1.0.0
     */
    protected $event_slug_list = [];

    /**
     * Event ID List
     * @var array
     * @since 1.0.0
     */
    protected $event_id_list = [];

    /**
     * Event object ID labels
     * @var array
     * @since 1.0.0
     */
    protected $event_object_id_labels = [];

    /**
     * Specifies list of meta fields to ignore if a specific event is triggered 
     * in other to prevent duplicated event log reference
     * @var array
     * @since 1.0.0
     */
    protected $ignorable_meta_field_event_list = [];

    /**
     * Specifies whether the event is pre-disabled. This is done by checking whether the 
     * 'disabled' event data argument is set True upon registration.
     * @var bool
     * @since 1.0.0
     */
    protected $_is_event_pre_disabled = false;

    /**
     * Specifies the event data that was actively triggered
     * @var array
     * @since 1.0.0
     */
    protected $active_event = [];

    /**
     * Specifies list of data to override in the active event
     * @var array
     * @since 1.0.0
     */
    protected $active_event_alt = [];

    /**
     * Specifies the active event ID
     * @var int
     * @since 1.0.0
     */
    protected $active_event_ID = 0;

    /**
     * Specifies the active event slug
     * @var string
     * @since 1.0.0
     */
    protected $active_event_slug = '';

    /**
     * Specifies whether or not the active event is loggable
     * @var bool
     * @since 1.0.0
     */
    protected $is_active_event_loggable = false;

    /**
     * Specifies list of events that was not executed successfully.
     * This is used to make sure we have a reference to any failed that never 
     * reached its successful state.
     * 
     * A good example would be the {@see update_user_meta} and {@see updated_user_meta}
     * 
     * If the 'updated_user_meta' was not fired, then it certainly means that the event 
     * was not successful. 
     * 
     * @var array
     * @since 1.0.0
     */
    protected $maybe_trigger_failed_events = [];

    /**
     * Pre-event data before update
     * @since 1.0.0
     * @var array
     * 
     * The object data are referenced with their respective object name or type:
     * 
     *  [
     *      'post'     => WP_POST,
     *      'term'     => WP_Term,
     *      'taxonomy' => WP_Taxonomy,
     *      ...
     *  ]
     */
    protected $_pre_event_data = [];

    /**
     * Specifies list of custom event handlers
     * @see EventList::canBailOutWithCustomEventHandler()
     */
    protected $custom_event_handler_list = [];

    /**
     * Setup the Event List
     */
    protected function __setupEventList()
    {   
        /**
         * We want to make sure the global event helper is available to every other 
         * event instances so that registered event can make used of the 
         * global event data.
         */
        $this->_registerGlobalEventHelpers();

        $this->initEventConditionalArgs();
        $this->registerAllEventGroups();

        /**
         * A simple hack to retrieve the Auditor controller by using the filter hook.
         * This should only be used in context where the Auditor object is unaccessible.
         */
        add_filter( 'alm/controller/get/auditor', [ $this, 'retrieveAuditorObj' ]);

        /**
         * Fires before the event group is normalize
         * 
         * @since 1.0.0
         * 
         * Both parameters are passed by reference
         * 
         * @param array   $event_groups  Specifies the event groups list with existing events
         * @param Auditor $auditor       The Auditor object
         */
        do_action_ref_array( 'alm/event/group/register', [ &$this->event_list, &$this ]);

        $this->normalizeEventGroupList();

        /**
         * Filters the main event message.
         * 
         * This filter is document in:
         * (@see /activitylogmanager/controllers/Audit/Traits/trait-event-list.php)
         * 
         * This will prepend the logged-in user role to the event message.
         * 
         * @since 1.0.0
         */
        add_filter( 'alm/event/message/save/main', [ $this, 'customizeEventMainMsg' ], 10, 3 );

        /**
         * Format the event message field info
         * @see EventList::getEventMsgInfo()
         */
        add_filter('alm/event/msg/field/info', function($info, $event, $field, $context)
        {
            // Bail out if the info has been formatted
            if ('' !== $info) return $info;

            $data  = $this->getEventMsgArg($event, $field, '', true);
            $label = $this->makeFieldReadable($field);

            // Bail if the field info has been ignored
            if ($this->isEventMsgFieldInfoIgnorable($data))
                return $data; // Returning the '_ignore_' flag

            // Retrieve the object ID label if available
            if ('object_id' === $field) {
                $label = $this->getVar($this->event_object_id_labels, $event, 'object_id');
            }

            if (is_null($data)) $data = 'Null';

            return sprintf('%s: %s', $label, $data);
        }, 98, 4);
    }

    /**
     * Retrieve the Auditor object
     * 
     * @since 1.0.0
     * 
     * @see apply_filters_ref_array()
     */
    public function retrieveAuditorObj( $auditor = '' )
    {
        return $this;
    }

    /**
     * Get the event list
     * 
     * @since 1.0.0
     * 
     * @return array
     */
    public function getEventsList()
    {
        ksort($this->main_event_list, SORT_NUMERIC);
        return $this->main_event_list;
    }

    /**
     * Initialize the event groups
     * 
     * @since 1.0.0
     * 
     * @see \ALM\Controllers\Audit\event-groups\trait-*-events.php
     * @see EventList::getDefaultEventArgs()
     * 
     * This will auto get all traits (trait files) in the 
     * /controllers/audit/event-groups/ directory, with the pattern: trait-*-events.php
     * 
     * The event group file names are constructed as: trait-[event-group-name]-events.php
     * We are solely interested in the 'event-group-name' form each file name, which we will 
     * then transform into the corresponding event group method as defined in the trait file.
     * 
     * For example: the 'event-group-name' is transformed to => initEventGroupName()
     * The 'init' prefix before the event group name is part of the method name.
     */
    protected function registerAllEventGroups()
    {
        /**
         * This is just a simple hack to get all available event groups.
         * The files have been loaded already, we are not loading it here.
         */
        if ($this->is_multisite) {
            $files = glob(ALM_CONTROLLERS_DIR . 'Audit/event-groups/{network/,trait}*-events.php', GLOB_BRACE);
        } else {
            $files = glob(ALM_CONTROLLERS_DIR . 'Audit/event-groups/trait-*-events.php');
        }

        foreach ( $files as $file )
        {
            // Strip out 'trait-' and '.php' from the file name
            $event_group = ucfirst(str_replace([ 'trait-', '.php', ], '', basename($file)));

            // Transform the event group into actual method
            $event_group_method = 'init' . str_replace('-', '', ucwords( $event_group, '-' ));
            
            // Initialize the event groups
            if (method_exists($this, $event_group_method))
                $this->$event_group_method();
        }

        /**
         * This filter allows you to register a new event group.
         * It is expected to return an array containing events to watch for in the group.
         * 
         * @since 1.0.0
         * 
         * @see \ALM\Controllers\Audit\event-groups\trait-*-events.php for valid arguments 
         * 
         * @param array $event_groups Specifies the event groups list with existing events
         * 
         * Example:
         * 
         * add_filter( 'alm/event/groups', 'add_new_event_group );
         * 
         * function add_new_event_group( $event_list )
         * {
         *      $event_list['group_slug'] = [
         *          'title'       => 'New Group Title',
         *          'object'      => 'post', // group object type: post, comment, user, etc.
         *          'description' => 'Group description. What type of activities is being logged.',
         * 
         *          'events' => [
         *           
         *              // action hook name, filter hook name, or a php callback function
         *              'event_hook_name' => [
         *                  'title'         => '',
         *                  'action'        => '',
         *                  'event_id'      => 0, // required,
         *                  'severity'      => '',
         *                  'description'   => '',
         * 
         *                  'message'       => [
         *                      '_main' => '', // required
         *                  ],
         * 
         *                  'event_handler' => [
         *                      'hook'     => 'action', // 'action' | 'filter' | 'callback'
         *                      'num_args' => 2,
         *                      'priority' => 10,
         *                      'callback' => 'callback_function',
         *                  ]
         *              ]
         *          ]
         *      ];
         * 
         *      return $event_list;
         * }
         */
        $this->event_list = apply_filters( 'alm/event/groups', $this->event_list );
    }

    /**
     * Setup the custom event handler list
     * 
     * @param array $event Specifies the event data
     */
    protected function setupCustomEventHandlerList(array $event = [])
    {
        $custom_event_handler = $this->getVar($event, 'bail_event_handler');

        if (empty($custom_event_handler) || !is_array($custom_event_handler)) 
            return;

        $event_type  = $this->getVar($custom_event_handler, 'event_type');
        $event_group = $this->getVar($custom_event_handler, 'event_group');

        if (empty($event_type) 
        || empty($event_group) 
        || !is_string($event_type) 
        || !is_string($event_group) )
            return;

        if (!isset($this->custom_event_handler_list[ $event_group ]))
            $this->custom_event_handler_list[ $event_group ] = [];

        $this->custom_event_handler_list[ $event_group ][ $event_type ] = $event_type;
    }

    /**
     * Custom event handlers may be registered for an existing event. So we need 
     * to make sure that the custom handlers and exiting event are not fired together.
     * 
     * For example, the {@see 'after_delete_post'} action hook is fired whenever 
     * any post data is deleted. In this case, if we register any custom handlers 
     * by using the 'after_delete_post' action hook, let's say we created an 
     * 'alm_menu_item_deleted' action hook that will fire whenever a menu item is 
     * deleted (that is, the 'alm_menu_item_deleted' action hook should fire 
     * when the post type 'nav_menu_item' is deleted). 
     * 
     * So we must bail out from the original event hook to prevent duplicated event.
     * 
     * Also, for usage with {@see set_theme_mod()} and the likes, you should specify 
     * the  option name as $event_group and $event_type_to_bail as the targeted
     *  option key.
     * 
     * @param string $event_group        Specifies the event group to bail corresponding 
     *                                   event for.
     * 
     * @param string $event_type_to_bail Specifies the targeted event type. Basically, this 
     *                                   is the type of term, post type, etc.
     * 
     * @return bool                      Returns true if current event should be bailed out 
     *                                   because a custom handler already exists for logging 
     *                                   the event. Otherwise false.
     */
    protected function canBailOutWithCustomEventHandler($event_group, $event_type_to_bail = '')
    {
        if (empty($event_type_to_bail) || is_countable($event_type_to_bail)) 
            return false;

        $custom_handlers = $this->getVar($this->custom_event_handler_list, $event_group);

        return isset($custom_handlers[ $event_type_to_bail ]);
    }

    /**
     * Customized the event main message
     * 
     * @see alm/event/message/save/main filter
     */
    public function customizeEventMainMsg($msg, $event_group, $event_data)
    {
        $object_id = $this->getEventMsgArg($event_group, 'object_id', 0);

        /**
         * Format the event message with the specified placeholders: (%s)
         */
        if ( false !== strpos($msg, '(%s)') ) {
            switch($event_group) {
                case 'user':
                    $username = $this->getVar(
                        $this->User->getUserData($object_id, true),
                        'user_login'
                    );

                    // Will raw escape the url
                    $user_profile_url = $this->User->getUserProfileEditUrl($object_id);

                    $user_profile = sprintf(
                        '<a href="%s">%s</a>',
                        empty($user_profile_url) ? '#' : $user_profile_url,
                        $username
                    );

                    $msg = sprintf($msg, $user_profile);
                    break;
            }
        }

        /**
         * Make a fallback for the user login event.
         * 
         * @see {is_user_logged_in()} maybe false until after the page has reloaded 
         * from the login screen.
         */
        $user_roles = [];
        if ('user' === $event_group) {
            $user_id    = $this->User->current_user_ID || $object_id;
            $user_roles = $this->User->getUserRoles($user_id, true);
        }

        $is_user_role_empty = empty($user_roles);
        if ( $is_user_role_empty && !is_user_logged_in() ) 
            return $msg;

        $role_desc  = '';
        $user_roles = $is_user_role_empty ? $this->User->getCurrentUserRoles() : $user_roles;

        // If the user roles is empty, let's refresh the user data
        if (empty($user_roles)) {
            $this->User->refreshCurrentUserData();
            $user_roles = $this->User->getCurrentUserRoles();
        }

        if (in_array('super_admin', $user_roles, true)) {
            $role_desc = 'A Super Admin';
        }
        elseif (in_array('administrator', $user_roles, true)) {
            $role_desc = 'An Administrator';
        }
        else {
            // If user role starts with any vowel letters, then this will be 'An'
            $role_prefix = 'A';

            // Get the user role
            foreach( $user_roles as $user_role ) {
                if (in_array($user_role, ['super_admin', 'administrator']))
                    continue;

                // Custom user role may include the underscore or dash character
                $delimiter = (false === strpos($user_role, '-' )) ? '_' : '-';
                
                // If the user role start with any vowels, then prefix it with 'An'
                if ($this->strStartsWith($user_role, $this->getVowelLetters(), true))
                    $role_prefix = 'An';

                $role_desc = $role_prefix .' '. ucfirst(ucwords($user_role, $delimiter));
            }
        }

        // This should never happen, but we have to bail out if it does
        if (empty($role_desc)) {
            return sprintf('A user without a role %s', $msg);
        }

        // If the message starts with 'A ' or 'An,
        // then let's replace it with an empty string
        if ($this->strStartsWith($msg, ['a ', 'an '], true))
            $msg = trim(substr($msg, 2));

        // If the message starts with 'User ',
        // then let's replace it with an empty string
        if ($this->strStartsWith($msg, ['user '], true))
            $msg = trim(substr($msg, 4));
        
        // Transform the first message character to lowercase
        $first_char = strtolower(substr($msg, 0, 1));

        // Remove the first character from the message
        $remove_first_char = substr($msg, 1);

        $_msg = $first_char . $remove_first_char;

        return "{$role_desc} {$_msg}";
    }

    /**
     * Add a new event group or add a new event to an exiting event group.
     * 
     * This is used outside the Auditor controller class to allow registration 
     * of new event groups.
     * 
     * @since 1.0.0
     * 
     * @see EventList::registerAllEventGroups()
     */
    public function addEventGroups( array $args = [] )
    {
        $this->event_list = array_merge_recursive( $this->event_list, $args );
    }

    /**
     * Get the default event arguments
     * @return array
     */
    public function getDefaultEventArgs()
    {
        $args = [
            'title'           => '',
            'group'           => '',
            'action'          => '',
            'object'          => '', // post, comment, user, term, etc.
            'message'         => [],
            'event_id'        => 0,
            'severity'        => 'notice',
            'object_id_label' => 'Object ID', // Object labels such as: User ID, Post ID, etc.
            
            // Set to true to completely disable the event
            'disable' => false,

            /**
             * Specifies list of option's events to ignore.
             * 
             * This is useful if custom events are being registered for a 
             * specific option.
             */
            'wp_options'      => [],
            'wp_site_options' => [], // multisite only

            /**
             * Specifies list of post meta event to ignore.
             * This is useful in cases where the event has been handled individually.
             * 
             * @todo
             * include the {@see 'object'} when building the post meta list
             * ============================================================
             * 'post_meta' => [
             *      'post_meta_key' => ['object1', 'object2', 'object etc', ]
             * ]
             * ============================================================
             */
            'wp_post_meta'    => [],

            /**
             * Specifies whether to enabled/disabled notifications
             * notification => [
             *     'sms'   => false,
             *     'email' => true,
             * ],
             */
            'notification'  => [
                'sms'   => true,
                'email' => true,
            ],

            /**
             * Specifies whether the event can be aggregated
             * 
             * Aggregate all events that having the aggregate flag turned on.
             * 
             * The {@see 'error_flag'} is used to specify event that needs to be 
             * incremented such as failed login attempts, invalid password reset 
             * request, etc.
             * 
             * Note: Post, Page, etc. cannot be aggregated because of data type length,
             * an overflow error maybe be raised.
             * 
             * ------------------------------------------------------------------------
             *                      Aggregation Algorithm
             * ------------------------------------------------------------------------
             * #1. Verify the user id, object id, event id, and IP address if they 
             *     are the same 
             * #2. Map the event timestamp to all text data:
             *     ( user_data, object_data, metadata )
             */
            'error_flag'      => false,
            'is_aggregatable' => false,

            /**
             * Take actions
             * 
             * Specifies actions that can be performed when the [Take Action] 
             * button is clicked.
             * 
             * The [Take Action] button is displayed along with the event log data 
             * on the frontend.
             * 
             * This is a multi-dimensional array that describes how the button will 
             * be created.
             * 
             * Example:
             * 
             * 'take_actions' => [
             *      [
             *          'id'          => 'unique-btn-id',
             *          'type'        => 'button', // 'button' | 'link'
             *          'href'        => '#', // Only used if 'type' is set to 'link'
             *          'class'       => 'btn-class',
             *          'label'       => alm__('First action'),
             *          'custom_attr' => [
             *              '_target'          => 'blank',
             *              'data-first-attr'  => 'first value',
             *              'data-second-attr' => 'second value',
             *          ]
             *      ],
             * ]
             */
            'take_actions' => [],

            'event_handler' => [
                /**
                 * 'action, 'filter', or 'callback'.
                 * 
                 * Note: when set to 'callback', it indicates that this is a valid php function
                 * or class method, that will be triggered whenever the event occurs.
                 */
                'hook'     => 'action',

                'priority' => 10,
                'callback' => null,
                'num_args' => 1,
            ],
        ];

        /**
         * Merge the event conditional arguments
         */
        $args = array_merge( $args, $this->event_conditional_args );

        return $args;
    }

    /**
     * Get event field contexts.
     * This is used to determine whether a field is created, updated, etc
     * 
     * @since 1.0.0
     * 
     * @return array
     */
    protected function getEventFieldContexts()
    {
        return ['new', 'previous', 'intended', 'current', 'requested'];
    }

    /**
     * Check whether event message argument exists
     * 
     * @see EventList::getEventMsgArg()
     * 
     * @param  string $event Specifies the event to check for.
     * 
     * @param  string $arg   Specifies the event message field to check for. If set to null, 
     *                       or empty string, then only the event ($event) will be checked for.
     * 
     * @return bool          True if the event exists. Otherwise false.
     */
    protected function eventMsgArgExists( $event, $arg = null )
    {
        if ( ! isset( $this->customize_event_msg_args[ $event ] ) )
            return false;

        if ( is_null( $arg ) || '' === $arg )
            return true;

        if ( ! isset( $this->customize_event_msg_args[ $event ][ $arg ] ) )
            return false;

        return true;
    }

    /**
     * Get list of all event message arguments that are can be used in 
     * generating the event message
     */
    protected function getTransformableSpecialMsgArgs()
    {
        return [
            '_event_id', '_main', '_meta_key', '_meta_value', '_space_start', '_space_end', '_error_msg', '_user_avatar',
        ];
    }

    /**
     * Get the active event main message
     * 
     * @see Event::getEventMsgArg()
     * @see ALM\Controllers\Audit\Auditor::_getActiveEventData()
     */
    protected function getActiveEventMsg($event_group = '')
    {
        $main_msg      = $this->_getActiveEventData('message', '_main');
        $_count_object = $this->getEventMsgArg($event_group, '_count_object', 1);

        if (!is_scalar($_count_object))
            $_count_object = 1;

        $translation_type = $_count_object > 1 ? 'plural' : 'singular';

        $msg = $this->getVar(
            $this->_getActiveEventData('_translate', '_main'),
            $translation_type,
            $main_msg
        );

        if ($this->is_network_admin) {
            $translation_type .= '_network';
            $msg = $this->getVar(
                $this->_getActiveEventData('_translate', '_main'),
                $translation_type,
                $msg
            );
        }

        /**
         * Maybe we should format the event message with the given paceholders values
         */
        $placeholder_values = $this->getEventMsgArg($event_group, '_placeholder_values', '');

        if (!is_array($placeholder_values))
            $placeholder_values = [];

        if (false !== strpos($msg, ' (%s)') && !empty($placeholder_values)) {
            $msg = sprintf($msg, ...$placeholder_values);
        } else {
            $msg = str_replace(' (%s)', '', $msg);
        }

        return $msg;
    }

    /**
     * Check whether the event message can be ignored
     * 
     * @param  string $info The event message field data
     * 
     * @return bool   Returns true if the message field should be ignored.
     *                Otherwise false.
     */
    public function isEventMsgFieldInfoIgnorable($info)
    {
        if (!is_string($info)) return false;

        return ('_ignore_' === $info || $this->strEndsWith($info, '_ignore_'));
    }

    /**
     * Generate event message before it is saved to the database
     * 
     * @see EventList::getEventMsgInfo()
     * 
     * @param string $event_group   Specifies the event group which the message belongs to.
     * 
     * @param array  $message_args  Specifies list of event message arguments to used in 
     *                              generating the message. 
     *                              This should contain the event info field and context,
     *                              if needed.
     * 
     * @param array  $event_data    Specifies the event data which the message belongs to.
     * 
     * @return string               The generated event message.
     */
    protected function generateEventMessageForDb( $event_group, array $message_args = [], array $event_data = [] )
    {
        if (empty( $message_args )) 
            return '';

        /**
         * Filter the event message arguments before it is saved to database
         * 
         * @since 1.0.0
         *
         * @param array  $message_args  Specifies list of event message arguments to used in 
         *                              generating the message. 
         *                              This should contain the event info field and context,
         *                              if needed.
         *
         * @param string $event_group   Specifies the event group which the message belongs to.
         * 
         * @param array  $event_data    Specifies the event data which the message belongs to.
         * 
         * @return array                The filtered message list
         */
        $_message_args = apply_filters( 'alm/event/message/db', $message_args, $event_group, $event_data );

        $list             = '';
        $site_details     = ['blog_id', 'site_id', 'site_url', 'blog_url', 'blog_name', 'site_name'];
        $special_msg_args = $this->getTransformableSpecialMsgArgs();

        foreach ( $_message_args as $msg_name => $message_arg )
        {
            /**
             * On multisite, we may not need to log the following message arguments:
             * {@see blog_id, blog_name, blog_url}
             * 
             * This is because the blog_id, blog_name and blog_url have
             * specific columns in the event log
             */
            if ($this->is_multisite) {
                if (in_array($msg_name, $site_details, true))
                {
                    /**
                     * Filters whether to log the site details along with the message data.
                     * 
                     * @since 1.0.0
                     * 
                     * @param bool   $site_details  Specifies whether or not to log the site details 
                     *                              along with the message data.
                     *                              Note: The 'blog_id', 'blog_name', and 'blog_url' 
                     *                              columns does exists in the event log table.
                     *                              Default: false
                     * 
                     * @param array  $message_args  Specifies list of event message arguments to used in 
                     *                              generating the message. 
                     *                              This should contain the event info field and context,
                     *                              if needed.
                     *
                     * @param string $event_group   Specifies the event group which the message belongs to.
                     * 
                     * @param array  $event_data    Specifies the event data which the message belongs to.
                     */
                    $log_site_details_with_msg = apply_filters(
                        'alm_event/message/db/log/site_details',
                        false, $message_args, $event_group, $event_data
                    );
                    if (!$log_site_details_with_msg)
                        continue;
                }
            }

            // Ignore the message if $message_arg var is set to '_ignore_'
            if ( $this->isEventMsgFieldInfoIgnorable($message_arg) )
                continue;
            
            $is_metadata    = $this->strStartsWith( $msg_name, 'meta_' );
            $is_traversable = is_array( $message_arg );

            // Ignore special fields which always starts with an underscore '_' character
            $is_special_field = $this->strStartsWith($msg_name, '_');
            if ($is_special_field)
            {
                // First thing first, let's retrieve the main message
                if ('_main' == $msg_name)
                {
                    $info = isset($_message_args['_main_processed']) ? 
                        $_message_args['_main'] : $this->getActiveEventMsg($event_group);
                        
                    /**
                     * Filters the main event message before saving to database
                     * 
                     * @since 1.0.0
                     * 
                     * @param string $msg         Specifies the main message to display for the event
                     * @param string $event_group Specifies the event group which the message belongs to.
                     * @param array  $event_data  Specifies the event data which the message belongs to.
                     */
                    $info = apply_filters(
                        'alm/event/message/save/main',
                        $info, $event_group, $event_data
                    );
                }
                else {
                    if (!in_array($msg_name, $special_msg_args, true)) 
                        continue;

                    if ( $this->strStartsWith($msg_name, ['_space_', '_inspect_']) )
                    {
                        $info = $msg_name;
                    }
                    elseif ( '_error_msg' == $msg_name ) {
                        $info = $this->getEventMsgArg( $event_group, '_error_msg' );

                        if (empty($info)) continue;
                    }
                    else {
                        if (!$is_traversable)
                        {
                            if ($is_metadata) {
                                $info = $message_arg;
                            } else {
                                continue;
                            }
                        } 
                        else {
                            $info = $this->getEventMsgInfo( $event_group, ...$message_arg );
                        }
                    }
                }
            }
            else {
                if (!$is_traversable)
                {
                    if ($is_metadata) {
                        $info = $message_arg;
                    }
                    else {
                        // Check whether the message exists
                        $assumed_field = ( '' == $message_arg ) ? $msg_name : $message_arg;
                        $lookup_msg    = $this->getEventMsgInfo( $event_group, $assumed_field );

                        if ( empty( $lookup_msg ) ) 
                            continue;

                        $info = $lookup_msg;
                    }
                }
                else {
                    $info = $this->getEventMsgInfo( $event_group, ...$message_arg );
                }
            }

            // Properly parse object and array values
            $info = $this->parseValueForDb($info);

            /**
             * If the info is equal '_ignore_', then we should ignore it.
             */
            if (!is_serialized($info, true) 
            && $this->isEventMsgFieldInfoIgnorable($info))
                continue;

            /**
             * Pluralize the event message name if needed.
             * 
             * Note: special fields starting with an underscore character are ignored
             */
            $_msg_name = $msg_name;
            if ( !$is_special_field 
            && isset($event_data['_translate'][$msg_name]) 
            && 0 === $this->getEventMsgArg($event_group, '_count_object', 0))
            {  
                $pluralize_str = $this->getVar($event_data['_translate'][$msg_name], 'plural_char', ', ');
                
                if ( false !== strpos($info, $pluralize_str) ) {
                    $plural    = $this->getVar($event_data['_translate'][$msg_name], 'plural', $msg_name);
                    $_msg_name = $plural;
                } else {
                    $singular = $this->getVar($event_data['_translate'][$msg_name], 'singular', '');
                    if ( !empty($singular) )
                        $_msg_name = $singular;
                }
            }
            
            $list .= $_msg_name . '=' . $info . $this->getEventMsgSeparatorChar();
        }

        return $this->rtrim( $list, $this->getEventMsgSeparatorChar() );
    }

    /**
     * Generate the displayable event message after it has been retrieved from database.
     * This will first convert the message string to an an event message array, which is then
     * passed to the event message display filter
     * 
     * @param  string       $msg        Specifies the event message
     * @param  object|null  $event_obj  Specifies the wpdb event object retrieved from database.
     * @return string                   The formatted event message ready for display
     */
    protected function generateEventMessageForDisplay( $msg = '', $event_obj = null )
    {
        if ( empty( $msg ) ) return $msg;
        
        $_msg     = wp_kses( $msg, $this->getEventMsgHtmlList() );
        $msg_list = explode( $this->getEventMsgSeparatorChar(), $_msg );

        $_msg_list = [];
        foreach ( $msg_list as $list )
        {
            if ( false === strpos( $list, '=' ) ) continue;

            $split = explode( '=', $list );
            if ( count( $split ) > 1 ) {
                $_msg_list[ $_msg_list[0] ] = $_msg_list[1];
            }
        }

        $msg_str = '<div class="alm-event-msg-wrapper">';
        foreach ( $_msg_list as $field => $the_msg )
        {
            // Skip the message if null
            if ( is_null( $the_msg ) ) continue;

            switch ( $field )
            {
                case '_main':
                    $main_msg_filter = apply_filters(
                        'alm/event/message/display/main',
                        $this->formatMainMsgTarget( $the_msg ),
                        $event_obj
                    );

                    $msg_str .= sprintf(
                        '<div class="alm-event-main-msg">%s</div>',
                        $main_msg_filter
                    );
                break;

                case '_space_start':
                    $msg_str .= '<div class="alm-event-msg-target-start">';
                break;

                case '_space_end':
                    $msg_str .= '</div>';
                break;
                
                default:
                    $msg_str .= sprintf(
                        '<span class="alm-event-msg-str">%s</span>',
                        $the_msg
                    );
                    break;
            }
        }
        $msg_str .= '</div><!-- .alm-event-msg-wrapper -->';

        /**
         * Filters the event message list prior to display
         * 
         * @see EventList::generateEventMessageForDb()
         * 
         * @param string      $msg_str    Specifies the displayable event message string  
         * 
         * @param array       $msg_list   Specifies the event message list
         * 
         * @param object|null $event_obj  Specifies the wpdb event object retrieved from database.
         * 
         * @return string     The filtered event message ready for display
         */
        return apply_filters( 'alm/event/message/display', $msg_str, $_msg_list, $event_obj );
    }

    /**
     * Filters the tags allowed in the event message prior to display
     * @return array The allowable tag list
     */
    protected function getEventMsgHtmlList()
    {
        return [
            '<hr>'   => [ 'class' => [] ],
            'span'   => [ 'class' => [] ],
            'p'      => [ 'class' => [] ],
            'div'    => [ 'class' => [] ],
            'strong' => [ 'class' => [] ],
            'small'  => [ 'class' => [] ],
            'em'     => [ 'class' => [] ],
            'u'      => [ 'class' => [] ],
            'a'      => [ 'href'  => [], 'class' => [], 'title' => [] ]
        ];
    }

    /**
     * Get the event hooks
     * 
     * @param string $hook  Specifies the event hook to check for
     * 
     * @return bool         True if the event hook is either 'filter' or 'action'.
     *                      True is also returned if the hook is an empty string.
     *                      False is returned otherwise.
     */
    public function isEventHookValid( $hook = '' )
    {
        if ( empty( $hook ) ) return true;
        return in_array( $hook, ['action', 'filter'], true );
    }

    /**
     * Check whether the customized event message argument is ready
     * @param  string  $event   Specifies the event message arguments to check for
     * @return bool
     */
    protected function isEventMsgArgReady( $event )
    {
        return ( 
            isset( $this->customize_event_msg_args[ $event ] ) 
            && isset( $this->customize_event_msg_args[ $event ][ 'is_ready' ] ) 
            && true === $this->customize_event_msg_args[ $event ][ 'is_ready' ]
        );
    }

    /**
     * Setup default event message data
     * 
     * @since 1.0.0
     * 
     * @param string $event_group Specifies the event group to setup the message data for.
     * 
     * @param array  $extra_data  Specifies list of extra data to merge with the event 
     *                            message data.
     * 
     * @return array The setup event message data.
     */
    protected function setupEventMsgData($event_group, array $extra_data)
    {
        $this->customize_event_msg_args[$event_group] = array_merge(
            [
                'is_ready'     => true,
                'network_name' => $this->getCurrentNetworkName(),
                'blog_id'      => $this->current_blog_ID,
                'user_id'      => $this->User->current_user_ID,
                'blog_name'    => $this->getBlogName(),
                'network_id'   => $this->getVar($this->network_data, 'id', $this->current_network_ID),
                'blog_url'     => $this->sanitizeOption( $this->getVar($this->blog_data, 'url'), 'url' ),
                'object_data'  => [],
            ],
            $extra_data
        );

        return $this->customize_event_msg_args[$event_group];
    }

    /**
     * Get the customized event message argument value
     * 
     * @since 1.0.0
     * 
     * @param  string  $event     Specifies the event the field argument belongs to
     * @param  string  $arg       Specifies the event message field argument to get
     * @param  mixed   $default   Specifies default value to use if the event argument is not set
     * @param  string  $to_scalar Specifies whether to properly parse array/object values
     * @return mixed              The customized event argument value
     */
    protected function getEventMsgArg( $event, $arg, $default = '', $to_scalar = false )
    {
        if (!$this->isEventMsgArgReady($event)) 
            return $default;

        if (!$this->eventMsgArgExists($event, $arg)) 
            return $default;
        
        $data = $this->customize_event_msg_args[ $event ][ $arg ];
        if ( $to_scalar ) {
            $data = $this->parseValueForDb($data);
        }

        return $data;
    }

    /**
     * Get the event object ID
     * @return int
     */
    protected function getEventMsgObjectId( $event )
    {
        return $this->sanitizeOption($this->getEventMsgArg( $event, 'object_id' ), 'int');
    }

    /**
     * Add the log counter info to the log message
     * @return string
     */
    protected function getLogCounterInfo()
    {
        return 'Number of failed request: ###LOG_COUNTER###';
    }

    /**
     * Get a formatted event field info. This is used to build and customized 
     * the event message.
     * 
     * @see EventList::generateEventMessageForDb()
     * 
     * @param  string  $event    Specifies the event which the field belongs to
     * 
     * @param  string  $field    Specifies the field info to get.
     * 
     * @param  string  $context  Specifies the event field info context. This is used to 
     *                           determine what type of field value we are dealing with.
     * 
     * @return string            The formatted event field info.
     */
    protected function getEventMsgInfo( $event, $field, $context = '' )
    {
        $info = '';

        /**
         * Bail out the event info if the event message arguments is not ready yet
         */
        if (!$this->eventMsgArgExists($event, $field)) 
            return $info;

        /**
         * Filters the event message argument info
         * 
         * @param  string  $info    Specifies the formatted event message  info
         * @param  string  $event   Specifies the event which the field belongs to
         * @param  string  $field   Specifies the event message argument
         * @param  string  $context Specifies the event message argument context
         * 
         * @return string  The filtered event message argument info
         */
        return apply_filters( 'alm/event/msg/field/info', $info, $event, $field, $context );
    }

    /**
     * Format the main message target for the event
     * @return string
     */
    public function formatMainMsgTarget( $msg )
    {
        if (!is_string($msg)) return $msg;

        return preg_replace(
            '/\-{3}([\w ]+)\-{3}/', // strip out the 3 dashes from the event message target
            "<strong class=\"alm-msg-target\">$1</strong>",
            $msg
        );
    }

    /**
     * Make an event message field more readable
     * @param  string Specifies the event message field to format
     * @return string The formatted event message field
     */
    public function makeFieldReadable( $field )
    {
        return ucfirst( str_replace( [ '_', '-' ], ' ', $field ) );
    }

    /**
     * Format an event message field with a given context, if provided.
     * @see EventList::getEventMsgInfo()
     * @see EventList::generateEventMessageForDb()
     */
    public function formatMsgField( $event, $field, $context = '', $format = true )
    {
        $label            = $format ? $this->makeFieldReadable( $field ) : $field;
        $info             = empty( $context ) ? $label : ucfirst( $context ) . ' ' . strtolower( $label );
        $meta_field       = preg_replace( '/\_$/', '', 'meta_value_' . $context );

        $formatted_field  = $this->getEventMsgArg( $event, $meta_field );
        $_formatted_field = $this->parseValueForDb( $formatted_field );

        return "$info: " . $_formatted_field;
    }

    /**
     * Check whether a given event is valid.
     * @param array $event  Specifies the event data to validate
     * @return bool         True if the event is valid. Otherwise false.
     */
    protected function isEventValid( array $event ) 
    {
        foreach ( $event as $k => $e )
        {
            $is_scalar = is_scalar( $e );
            if ( $is_scalar && false === $e ) continue;

            if ( empty( $e ) 
            && in_array( $k, ['message', 'event_handler'], true ) ) 
                return false;
        }

        $event_handler = $event['event_handler'];

        if ( ! is_null( $event_handler['callback'] ) 
            && ! is_callable( $event_handler['callback'] ) 
        ) return false;

        return true;
    }

    /**
     * Normalize the event group list
     */
    public function normalizeEventGroupList()
    {
        $default_args = [
            'title'       => '',
            'group'       => '',
            'object'      => '',
            'events'      => [],

            // Whether this event is a WP_Term object.
            // This is used to prevent duplicated monitoring on terms events
            'is_term'     => false,

            // Specifies the taxonomy name for the event group.
            // This is used to prevent duplicated monitoring on taxonomies events
            'taxonomy'    => false,

            'description' => '',
        ];

        foreach ( $this->event_list as $group_key => $event_list )
        {
            $group           = $event_list['group'];
            $events          = $event_list['events'];
            $event_groups    = array_merge($default_args, $event_list);
            $object_id_label = $this->getVar($event_list, 'object_id_label', 'object_id');

            if ( empty( $events ) ) continue;

            $this->setupEventObjectIdLabels($group, $object_id_label);
            $this->createMainEventList($group, $events, $event_list);
            $this->aggregateEventGroups($group_key, $event_groups);
        }
    }

    /**
     * Setup the event object ID label
     * 
     * @param string $group  Specifies the event group
     * 
     * @param string $label  Specifies the event object ID label.
     *                       Example: User ID, Post ID, Page ID, etc.
     */
    protected function setupEventObjectIdLabels($group, $label)
    {
        $this->event_object_id_labels[$group] = $label;
    }

    /**
     * Aggregate the event groups
     * @param string $group_key  Specifies the event group key as used in the array
     * @param array  $event_list Specifies the event group list
     */
    protected function aggregateEventGroups( $group_key, array $event_list )
    {
        $title       = $event_list['title'];
        $group       = $event_list['group'];
        $object      = $event_list['object'];
        $is_term     = $event_list['is_term'];
        $taxonomy    = $event_list['taxonomy'];
        $description = $event_list['description'];

        if ( empty( $title ) || empty( $group ) || empty( $description ) )
            return;

        if (empty($object)) $object = $group;

        $this->aggregated_event_groups[ $group_key ] = [
            'title'       => $title,
            'group'       => $group,
            'object'      => $object,
            'is_term'     => $is_term,
            'taxonomy'    => $taxonomy,
            'description' => $description,
        ];
    }

    /**
     * Create the main event list
     * @param string $group      Specifies the event group
     * @param array  $events     Specifies the events associated with the group
     * @param array  $group_args Specifies associated arguments for the given event group
     * 
     * @todo
     * 1. check if event is disabled
     * 
     * ------------------------------------------------------------------------------
     * How events are disabled
     * ------------------------------------------------------------------------------
     * 1. Get all excluded events
     * 2. Check if the event is in any exclude event list
     * 3. If event is listed in the exclude event list, 
     *    then set the event 'disable' argument to true
     * 
     * ------------------------------------------------------------------------------
     * How disabled events are treated
     * ------------------------------------------------------------------------------
     * All registered events are passed to the {@see Auditor Observer Controller}, 
     * which will then perform the  final step, whether to watch the event or not.
     * If the [disable] event argument is set and evaluates to true, then the 
     * Auditor watcher will ignore such event. Simple!
     * 
     * ------------------------------------------------------------------------------
     * How event notifications are turned off
     * ------------------------------------------------------------------------------
     * 1. Get all event IDs with disabled notifications turned on
     * 2. Set the 'notification' argument for those events to false
     * 3. The Event Notification Handler will then ignore sending notifications if the event 
     *    'notification' argument is set to false.
     * 4. If the notification argument is an array, then it will contain just 2 fields:
     *      'sms'   => true|false,
     *      'email' => true|false,
     * 5. If the 'sms' or 'email' field is set to false, then the notification for such
     *    channel will be ignored for the given event.
     */
    protected function createMainEventList( $group, array $events, array $group_args = [] )
    {
        $defaults      = $this->getDefaultEventArgs();
        $event_handler = $defaults['event_handler'];

        /**
         * Filters the event groups list
         * 
         * @param  array  $events Specifies the events associated with the group
         * 
         * @param  string $group  Specifies the event group
         * 
         * @return array          The filtered event groups list. Returning an empty array will 
         *                        all events from the group.
         */
        $event_list = apply_filters( 'alm/event/group', $events, $group );

        foreach ( $event_list as $hook => $event )
        {
            if (!is_array($event)) continue;

            $_event                  = array_merge($defaults, $event);
            $_event['event_handler'] = array_merge($event_handler, (array) $_event['event_handler']);

            if (empty($_event['group'])) 
                $_event['group'] = $this->getVar($group_args, 'group', $group);

            if (empty($_event['object'])) 
                $_event['object'] = $this->getVar($group_args, 'object', $group);

            if (!$this->isEventValid($_event)) continue;

            $event_id = $_event['event_id'];

            /**
             * Use the message '_event_id' argument if set
             */
            if (isset($_event['message']['_event_id']) 
            && is_string($_event['message']['_event_id']) 
            && strlen($_event['message']['_event_id']) >= 4)
            {
                $event_id = $_event['message']['_event_id'];
            }

            // If the event ID is not valid, then we have to skip that event
            // Note: maximum event length is 5
            if (!is_int($event_id) || 0 >= $event_id || 4 > strlen($event_id)) 
                continue;

            $_event['event'] = $hook;

            /**
             * Everything looks good at this point, let's disable all excluded events
             */
            $this->_is_event_pre_disabled = (true === (bool) $_event['disable']);
            
            /**
             * Filters the event pre-disabled state
             * 
             * @param bool   $pre_disabled Specifies whether the event is pre-disabled.
             * @param string $event_id     Specifies the event ID
             * @param string $event_name   Specifies the event name (event action/filter hook)
             * @param array  $event        Specifies the event arguments list
             */
            $is_event_pre_disabled = apply_filters(
                'alm/event/disabled/pre',
                $this->_is_event_pre_disabled, $event_id, $hook, $_event 
            );

            if ( $is_event_pre_disabled ) 
                $_event['disable'] = true;
            
            if (true !== $_event['disable'] && !$this->preIsEventValid($event_id, $hook, $_event)) 
                $_event['disable'] = true;

            if ( !$_event['disable'] )
            {
                /**
                 * If the event specifies any meta field to be ignored, add it to the 
                 * ignorable meta field list, but only when the event has not been disabled
                 */
                $ignore_meta_fields = $this->getVar($_event, 'ignore_meta_fields');
                if ( is_array($ignore_meta_fields) && !empty($ignore_meta_fields) ) {
                    /**
                     * Simplify the array list to one dimensional array
                     */
                    $counter = 0;
                    foreach ($ignore_meta_fields as $meta_field )
                    {
                        $meta_field_target = "{$event_id}_{$meta_field}";
                        if ( !isset($this->ignorable_meta_field_event_list[ $meta_field_target ]) ) {
                            ++$counter;
                            $this->ignorable_meta_field_event_list[ $meta_field_target ] = $meta_field;
                        }
                    }
                }

                /**
                 * Setup the custom event handler list
                 */
                $this->setupCustomEventHandlerList($_event);
            }

            /**
             * Set the event notification state
             */
            $_event['notification'] = $this->getEventNotificationState( $event_id, $hook, $_event );

            /**
             * Note: Precedence is giving to the first registered event if the 
             * given hook is already registered
             */

            // Note: we could have used an array to hold all the event IDs, but we don't want to.
            // Reason is because we want to enforce the usage of unique event IDs, no duplicates.
            if ( ! isset( $this->main_event_list[ $event_id ] ) ) 
                $this->main_event_list[ $event_id ] = $_event;

            /**
             * Namespace the event slug list with the event group name, since the 
             * event hook name may appear more than once given the event ID.
             * For example: there's the comment and user meta fields, having the
             * First name (first_name) and Last name (last_name) hooks respectively.
             */
            $event_hook_namespace = "{$group}_{$hook}";
            if ( ! isset( $this->event_slug_list[ $event_hook_namespace ] ) ) 
                $this->event_slug_list[ $event_hook_namespace ] = $event_id;

            // This is used to segregate all registered events
            if ( ! isset( $this->event_id_list[ $group ][ $event_id ] ) ) 
                $this->event_id_list[ $group ][ $event_id ] = $hook;
        }
    }

    /**
     * Get the event slug (hook name) by specifying either the 'event ID'
     * 
     * @since 1.0.0
     * 
     * @param int $event_id        Specifies the event ID to retrieve corresponding slug for.
     * 
     * @param string $default_slug Specifies the default slug to used if the specified ID is not 
     *                             connected to any slug.
     * 
     * @return string              Returns the event hook name (slug) if found.
     *                             Otherwise an empty string.
     */
    public function getEventSlugById($event_id, $default_slug = '')
    {
        if (!is_scalar($event_id))
            return '';

        $_id            = $this->sanitizeOption($event_id, 'int');
        $flip_slug_list = array_flip($this->event_slug_list);
        
        if (!isset($flip_slug_list[$_id]))
            return is_string($default_slug) ? $default_slug : '';

        return $flip_slug_list[$_id];
    }

    /**
     * Get the event hook name or ID by specifying either the 'event ID' 
     * or 'event hook name (slug)'
     * 
     * @since 1.0.0
     * 
     * @param string $event_hook  Specifies the event hook to retrieve corresponding ID for.
     * 
     * @param string $event_group  Specifies the event group the for the slug. Can be omitted if 
     *                             the event hook is specified together with the event group
     * 
     * @return int                 Returns the corresponding event ID if found.
     *                             Otherwise 0.
     */
    public function getEventIdBySlug( $event_hook, $event_group = '' )
    {
        $event_hook_namespace = ltrim( "{$event_group}_{$event_hook}", '_' );

        if (!isset($this->event_slug_list[$event_hook_namespace]))
            return 0;

        return $this->event_slug_list[$event_hook_namespace];
    }

    /**
     * Get the event slug by using the event handler name
     * 
     * @param string $event_handler_name Specifies the event handler name. This is 
     *                                   equivalent to {@see __FUNCTION__ constant} 
     *                                   when called inside the event handler function.
     *                                   Also, this return the expected event handler name 
     *                                   even if the {@see __METHOD__ constant} is used.
     * 
     * @return string                    Returns the event handler name.
     */
    public function getEventSlugByEventHandlerName($event_handler_name = '')
    {
        if (empty($event_handler_name)) return '';

        // Maybe the __METHOD__ constant has been used
        $split_event_handler_name = explode('::', $event_handler_name);
        $_event_handler_name      = end($split_event_handler_name);

        return preg_replace('/(_event)$/', '', $_event_handler_name);
    }

    /**
     * Get the event data given the event ID
     * 
     * @since 1.0.0
     * 
     * @param int $event_id Specifies the event ID
     * 
     * @return array|false  Returns an array containing the event data on success.
     *                      Otherwise false.
     */
    public function getEventData($event_id)
    {
        return $this->getVar($this->main_event_list, $event_id, false);
    }

    /**
     * Explain the event message if allowed
     * 
     * @since 1.0.0
     */
    protected function explainEventMsg( $msg = '' )
    {
        return $this->canExplainEventMsg() ? $msg : '';
    }

    /**
     * Setup the active event data override
     * 
     * @since 1.0.0
     * 
     * @param string $field  Specifies the active event field to override
     * 
     * @param mixed  $value  Specifies the value to replace the specified 
     *                       active event field value with
     */
    protected function overrideActiveEventData( $field, $value )
    {
        $this->active_event_alt[ $field ] = $value;
    }

    /**
     * Check whether we should ignore a specific meta field if an event is active.
     * 
     * Note: This is only applicable when the {@see $this->isVerboseLoggingEnabled()} 
     * settings helper returns false.
     * 
     * @since 1.0.0
     * 
     * @param string $meta_field Specifies the meta field to check for
     * @return bool              Returns true if meta field should be ignored. Otherwise false.
     */
    public function isActiveMetaFieldEventIgnorable( $meta_field = '' )
    {
        return !$this->isVerboseLoggingEnabled() 
            && isset($this->ignorable_meta_field_event_list[ $meta_field ]);
    }

    /**
     * Determine whether the active event has an error flag.
     * The error flag is used to check if we can increment the log counter 
     * based on the event context.
     * 
     * This also requires the {@see event_successor} argument to be setup.
     * The event successor argument is used to detect the event that should 
     * fire if the error has not occurred.
     * It could be the event ID or an array containing the event group 
     * and slug for the error flag
     * 
     * This is useful for things like failed login attempts, generated 404 error 
     * page request, too many password reset requests, etc.
     * 
     * @since 1.0.0
     * 
     * @return bool True if the active event has error flag. Otherwise false.
     */
    public function activeEventHasErrorFlag()
    {
        return (
            (bool) $this->getVar( $this->active_event, 'error_flag' ) 
            && 
            ( 
                (int) $this->getVar( $this->active_event, 'event_successor' ) > 0 
                || 
                ( 
                    is_array( $this->getVar( $this->active_event, 'event_successor' ) ) 
                    && count( $this->getVar($this->active_event, 'event_successor') ) > 1 
                )
            ) 
        );
    }

    /**
     * Determine whether the active event has an an aggregation flag.
     * The aggregation flag is used to check if we can increment the log counter 
     * based on the event context.
     * 
     * Ths is useful for things like page views, download counter, ect.`
     */
    public function activeEventHasAggregationFlag()
    {
        return (bool) (
            $this->getVar( $this->active_event, 'is_aggregatable' )
            && $this->isLogAggregatable()
        );
    }

    /**
     * Determine whether we need to update the active event log counter rather 
     * than creating a new log.
     * 
     * This is used in conjunction with {@see EventList::activeEventHasErrorFlag()}
     * 
     * @since 1.0.0
     * 
     * @param int $user_id    Specifies the user ID that triggers the event
     * 
     * @param int $object_id  Specifies the event target ID. 
     *                        This can be a user ID, post ID, term ID, etc.
     * 
     * @return bool True if we should increment the log counter for the active event.
     *              Otherwise false.
     */
    protected function isActiveEventLogIncrementValid( $user_id = 0, $object_id = 0 )
    {
        if (
            !($this->activeEventHasErrorFlag() || $this->isLogAggregatable()) 
            && !$this->activeEventHasAggregationFlag()
        ) {
            return false;
        }

        // Check the error event successor
        // This is only applicable to event having the error flag
        $event_ID                   = $this->active_event_ID;
        $failed_log_increment_limit = 0;

        if (!$this->activeEventHasAggregationFlag())
        {
            $event_successor = $this->getVar( $this->active_event, 'event_successor' );
            if ( is_array( $event_successor ) ) {
                $event_successor_slug  = $event_successor[1];
                $event_successor_group = $event_successor[0];

                // Lookup the event successor ID
                $event_ID = $this->getEventIdBySlug( $event_successor_slug, $event_successor_group );
            }
            else {
                $event_ID = $this->sanitizeOption($this->getVar( $this->active_event, 'event_successor' ), 'int');
            }

            if ($event_ID < 1)
                return false;

            if ($event_ID === $this->active_event_ID)
                return false;

            /**
             * @todo
             * For failed login attempts, create a button to allow admins to reset the log 
             * counter. If the attempted login request is made on a non-existing user account, 
             * specify it and let the admin user take an action.
             */
            $failed_log_increment_limit = $this->getEventFailedLogIncrementLimit(false);
        }

        /**
         * Get the most recent (last inserted) error log data for the 
         * event successor
         */

        /**
         * Filters the active event error/aggregatable log data fields to return from the query.
         * 
         * The filtered fields list is expected to contain the 'event_id' and 
         * 'log_counter' fields
         * 
         * @since 1.0.0
         * 
         * @param array  $selected_fields Specifies list of fields to return in the query
         * @param int    $active_event_id Specifies the active event ID
         * @param int    $user_id         Specifies the user ID
         * @param string $object_id       Specifies the event object ID
         */
        $selected_active_event_aggregatable_fields = apply_filters(
            'alm/event/log/update/selected_fields',
            [
                'log_id', 'event_id', 'log_status', 'log_counter', 'event_action_trigger', 'user_data', 'object_data', 'metadata', 'message',
            ],
            $this->active_event_ID,
            $user_id,
            $object_id
        );
        
        // Start building the failed log or aggregatable log increment query
        $this->DB
            ->reset()
            ->select( $selected_active_event_aggregatable_fields )
            ->from( $this->tables->activity_logs )
            ->where()
            ->isBlog( $this->current_blog_ID                )
            // ->and( 'user_id', $user_id,               '='   )
            ->and( 'event_id',  $this->active_event_ID, '=' )
            ->and( 'object_id', $object_id,             '=' )
            ->and( 'source_ip', $this->getTopLevelIp(), '=' );

            // Check if the failed log increment limit is enabled
            if ($failed_log_increment_limit !== 0) {
                $log_range = "{$failed_log_increment_limit} day";

                $this->DB
                ->and()
                ->dateRange( 'created_at', $log_range, 'now' );
            }

            $this->active_event_error_log_data = 
                $this->DB
                ->orderBy( 'log_id' )
                ->isDesc()
                ->limit(1)
                ->isResultArray()
                ->getRow();

        if (empty( $this->active_event_error_log_data ))
            return false;

        if ($this->activeEventHasAggregationFlag()) 
            return true;

        // Get the event ID for the most recent event successor
        // error log counter
        $max_error_log_counter_event_id = (int) $this->getVar(
            $this->active_event_error_log_data, 'log_id'
        );

        if ($max_error_log_counter_event_id < 1) 
            return false;

        // Get the most recent event successor ID
        $event_successor_id = (int) $this->DB
            ->reset()
            ->select( 'log_id' )
            ->from( $this->tables->activity_logs )
            ->where()
            ->isBlog( $this->current_blog_ID                )
            // ->and( 'user_id', $user_id,                 '=' )
            ->and( 'event_id',  $event_ID,              '=' )
            ->and( 'object_id', $object_id,             '=' )
            ->and( 'source_ip', $this->getTopLevelIp(), '=' )
            ->orderBy( 'log_id' )
            ->isDesc()
            ->limit(1)
            ->getVar();

        // If the event successor ID is greater than the error log counter,
        // then the error log counter should be created
        return ($max_error_log_counter_event_id > $event_successor_id);
    }

    /**
     * Get the active event log counter increment
     * 
     * @since 1.0.0
     * 
     * @return int
     */
    public function getActiveEventLogCounterIncrement()
    {
        return 1 + (int) $this->getVar( $this->active_event_error_log_data, 'log_counter', 0 );
    }

    /**
     * Get the event ID and Slug from the registered method name
     * 
     * Note: The event ID or slug can be passed as the value 
     * for the {@see $event_group} parameter.
     * 
     * @since 1.0.0
     * 
     * @see EventList::LogActiveEvent()
     * 
     * @return array|false Returns an associative array containing the event slug 
     *                     and ID on success. Otherwise false.
     */
    protected function getEventHookInfo($event_group = '', $method_name = '')
    {
        if (empty($event_group))
            return false;

        /**
         * Maybe the event slug has been specified
         */
        $event_id = $this->getEventIdBySlug($event_group);
        if (0 !== $event_id) {
            return [
                'ID'   => $event_id,
                'slug' => $event_group,
            ];
        }

        /**
         * Maybe the event ID has been specified
         */
        $event_data = $this->getEventData($event_group);
        if ($event_data) {
            return [
                'ID'   => $event_group,
                'slug' => $this->getEventSlugById($event_data),
            ];
        }

        /**
         * Auto get the event ID and slug if it's possible
         */

        if (empty($method_name))
            return false;

        $split_method_name = explode('::', $method_name);
        if (empty($split_method_name))
            return false;

        $_method_name = end($split_method_name);

        $event_hook           = preg_replace('/_event$/', '', $_method_name);
        $event_hook_namespace = "{$event_group}_{$event_hook}";

        if (!isset($this->event_slug_list[$event_hook_namespace]))
            return false;

        $event_id = $this->event_slug_list[$event_hook_namespace];

        if (!isset($this->main_event_list[$event_id]))
            return false;

        // We have to prefix the event with the event group name
        $event_slug = $this->getEventSlugById( $event_id, $event_hook_namespace );

        return [
            'ID'   => $event_id,
            'slug' => $event_slug,
        ];
    }

    /**
     * Clear the failed event data if the event successor was triggered
     * 
     * @since 1.0.0
     * 
     * @param string $event_group Specifies which group the even belongs to.
     * 
     * @param string $event_slug Specifies the event slug whose failed event 
     *                           should be cleared.
     */
    protected function clearFailedEventData($event_group, $event_slug)
    {
        $event_namespace = $event_group . '_' . $event_slug;
        if ( !isset($this->maybe_trigger_failed_events[$event_namespace]) )
            return;

        unset($this->maybe_trigger_failed_events[$event_namespace]);
    }

    /**
     * Log all failed events
     * 
     * @see EventList::clearFailedEventData()
     */
    public function triggerFailedEvents()
    {
        try {
            if ( empty($this->maybe_trigger_failed_events) )
                return;

            foreach ( $this->maybe_trigger_failed_events as $event )
            {
                if ( !is_array($event) ) continue;

                $event_group = $this->getVar($event, 'event_group');

                // Setup the event data
                $setup_handler = sprintf('setup%sEventArgs', ucfirst($event_group));
                if (!method_exists($this, $setup_handler)) continue;

                $this->$setup_handler($this->getVar($event, 'event_args'));
                $this->LogActiveEvent($event_group, $this->getVar($event, 'method'));
            }
        }
        // This should never happen, but just in case
        catch (\Exception $e) {
            if (WP_DEBUG) {
                throw new \Exception( sprintf(
                    alm__('%s'), esc_html($e->getMessage())
                ) );
            }
        }
    }

    /**
     * Setup and log the active triggered event data
     * 
     * @since 1.0.0
     * 
     * @param string $event_group Specifies which group the even belongs to.
     * 
     * @param string $method_name Specifies the event handler where the event is triggered.
     *                            Note: If the event handler is declared in a class, you can use 
     *                            the __METHOD__  constant or __FUNCTION__ constant if not. 
     *                            To allow automatic pulling, the class method or 
     *                            function used as the event handler should be declared 
     *                            semantically as the event slug.
     *                            For example, the 'set_user_role' event is triggered when 
     *                            changing a user's role, so the event handler should be 
     *                            declared as 'set_user_role_event' in other to use the 
     *                            automatic pulling behavior.
     */
    protected function LogActiveEvent( $event_group, $method_name = __METHOD__ )
    {
        $event_hook_info = $this->getEventHookInfo($event_group, $method_name);
        if (!$event_hook_info) return;

        $this->active_event_ID   = $this->getVar($event_hook_info, 'ID');
        $this->active_event_slug = $this->getVar($event_hook_info, 'slug');

        // Setup the active event
        $this->active_event = $this->getEventData($this->active_event_ID);

        /**
         * Lookup metadata event ID/Slug
         */
        $meta_key = $this->getEventMsgArg( $event_group, 'meta_key' );
        if ( ! empty( $meta_key ) && is_string( $meta_key ) )
        {
            // Check whether the metadata event exists
            $metadata_event_ID = $this->getEventIdBySlug( $meta_key, $event_group );

            if ( $metadata_event_ID > 0 ) {
                $this->active_event_ID   = $metadata_event_ID;
                $this->active_event_slug = $this->getEventSlugById( $metadata_event_ID );
            }
        }

        /**
         * We can now successfully log the event if allowed to.
         * This is used to automatically log the prepared (active) event data.
         * 
         * The {@see EventList::Log()} method can be called manually, without specifying the 
         * {@see $is_active_event_loggable} property.
         * 
         * Note: {@see $is_active_event_loggable} property is set to false by default,
         * So you must set it to true to log the active event data automatically.
         * 
         * @see 'alm/event/active/loggable' filter
         */

        /**
         * Filters the active event data loggable state
         * 
         * @since 1.0.0
         * 
         * @param bool $is_loggable     Specifies whether the active event data can be logged 
         *                              automatically. Default to true.
         * 
         * @param int  $event_ID        Specifies the active event ID
         * 
         * @param string $event_slug    Specifies the active event slug
         * 
         * @param array  $event_data    Specifies the active event data
         */
        $is_active_event_loggable = apply_filters(
            'alm/event/active/loggable',
            true, $this->active_event_ID, $this->active_event_slug, $this->active_event 
        );

        if ( $is_active_event_loggable )
            $this->Log();
    }

    /**
     * Log all failed events
     * 
     * This uses the {@see 'wp_redirect' filter} to check whether failed events 
     * are available before page reload
     */
    protected function _logFailedEvents()
    {
        if ( !$this->is_admin ) {
            add_action('template_redirect', [$this, 'triggerFailedEvents']);
        } else {
            $_self = &$this;
            add_filter('wp_redirect', function($location) use (&$_self)
            {
                $_self->triggerFailedEvents();
                return $location;
            }, 10, 2);
        }
    }
}