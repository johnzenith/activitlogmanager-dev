<?php
namespace ALM\Controllers\Audit\Templates;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Event List
 * @since   1.0.0
 */

use \ALM\Controllers\Audit\Events\Groups as ALM_EventGroups;

trait EventList
{
    use ALM_EventGroups\SuperAdminEvents,
        ALM_EventGroups\UserEvents,
        \ALM\Controllers\Audit\Templates\EventConditionalParser;

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
     * Setup the Event List
     */
    protected function __setupEventList()
    {   
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
         * /activitylogmanager/controllers/Audit/Templates/trait-event-list.php
         * 
         * This will prepend the logged-in user role to the event message
         * 
         * @since 1.0.0
         */
        add_filter( 'alm/event/message/save/main', [ $this, 'customizeEventMainMsg' ], 10, 3 );
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
        // var_dump( $this->main_event_list );

        // echo '<pre>';
        // print_r( wp_get_user_contact_methods( $this->User->current_user_data ) );

        // wp_die();
        
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
     * This will auto get all trait files in the /controllers/audit/event-groups/ directory,
     * With the pattern: trait-*-events.php
     * 
     * The event group file names are constructed as: trait-event-group-name-events.php
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
        $files = glob( ALM_CONTROLLERS_DIR . 'Audit/event-groups/trait-*-events.php' );

        // Initialize the event groups
        foreach ( $files as $file )
        {
            // Strip out 'trait-' and '.php' from the file name
            $event_group = ucfirst( str_replace( [ 'trait-', '.php', ], '', basename( $file ) ) );

            // Transform the event group into actual method
            $event_group_method = 'init' . str_replace( '-', '', ucwords( $event_group, '-' ) );
            
            if ( method_exists( $this, $event_group_method ) ) {
                $this->$event_group_method();
            }
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
     * Customized the event main message
     * 
     * @see alm/event/message/save/main filter
     */
    public function customizeEventMainMsg($msg, $event_group, $event_data)
    {
        if ( !is_user_logged_in() ) return $msg;

        $role_desc  = '';
        $user_roles = $this->User->getCurrentUserRoles();

        if ( in_array('super_admin', $user_roles, true) ) {
            $role_desc = 'A Super Admin';
        }
        elseif ( in_array('administrator', $user_roles, true) ) {
            $role_desc = 'An Administrator';
        }
        else {
            // If user role starts with any vowel letters, then this will be 'An'
            $role_prefix = 'A';

            // Get the user role
            foreach( $user_roles as $user_role ) {
                if ( in_array($user_role, ['super_admin', 'administrator']) )
                    continue;

                // Custom user role may include the underscore or dash character
                $delimiter  = (false === strpos($user_role, '-' )) ? '_' : '-';
                
                // If the user role start with any vowels, then prefix it with 'An'
                
                if ( $this->strStartsWith($user_role, $this->getVowelLetters(), true) )
                    $role_prefix = 'An';

                $role_desc = $role_prefix .' '. ucfirst( ucwords($user_role, $delimiter) );
            }
        }

        // This should never happen, but we have to bail out if it does
        if ( empty($role_desc) ) return $msg;

        // If the message starts with "A ", let's replace it with an empty string
        if ( $this->strStartsWith($msg, 'A ', true) )
            $msg = substr($msg, 2);
        
        // Transform the first message character to lowercase
        $first_char = strtolower( substr($msg, 0, 1) );

        // Remove the first char from the message
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
            'title'    => '',
            'group'    => '',
            'action'   => '',
            'object'   => '', // post, comment, user, term, etc.
            'message'  => [],
            'event_id' => 0,
            'severity' => 'notice',
            
            // Set to true to completely disable the event
            'disable' => false,

            /**
             * Specifies enabled/disabled notifications
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
             * an overflow error can be raised.
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
            '_event_id', '_main', '_meta_key', '_meta_value', '_space_start', '_space_end', '_error_msg', '_user_avatar'
        ];
    }

    /**
     * Get the event message character separator
     * @return string
     */
    protected function getEventMsgSeparatorChar()
    {
        return '|||';
    }

    /**
     * Get the event message error character
     * @return string
     */
    protected function getEventMsgErrorChar()
    {
        return '___error___';
    }

    /**
     * Get the event message line break character
     */
    public function getEventMsgLineBreak()
    {
        return '___break___';
    }

    /**
     * Get the event log data update identifier
     */
    public function getEventLogUpdateIdentifier()
    {
        $updated_at = $this->getDate();
        return "----------[{$updated_at}]----------";
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
        if ( empty( $message_args ) ) 
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
        $special_msg_args = $this->getTransformableSpecialMsgArgs();

        foreach ( $_message_args as $msg_name => $message_arg )
        {
            $is_metadata    = $this->strStartsWith( $msg_name, 'meta_' );
            $is_traversable = is_array( $message_arg );

            // Ignore special fields which always starts with an underscore '_' character
            if ( $this->strStartsWith( $msg_name, '_' ) )
            {
                // First thing first, let's retrieve the main message
                if ( '_main' == $msg_name )
                {
                    $info = isset( $_message_args['_main_processed'] ) ? 
                        $_message_args['_main'] 
                        : 
                        $this->_getActiveEventData( 'message', '_main' );

                    /**
                     * Filters the main event message before it is saved to database
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
                    if ( ! in_array( $msg_name, $special_msg_args, true ) ) 
                        continue;

                    if ( $this->strStartsWith( $msg_name, '_space_' ) ) {
                        $info = $msg_name;
                    }
                    elseif ( '_error_msg' == $msg_name )
                    {
                        $info = $this->getEventMsgArg( $event_group, '_error_msg' );

                        if ( empty( $info ) ) continue;
                    }
                    else {
                        if ( ! $is_traversable )
                        {
                            if ( $is_metadata ) {
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
                if ( ! $is_traversable )
                {
                    if ( $is_metadata ) {
                        $info = $message_arg;
                    }
                    else {
                        // Check whether the message exists
                        $assumed_field = ( '' == $message_arg ) ? $msg_name : $message_arg;
                        $lookup_msg    = $this->getEventMsgInfo( $event_group, $assumed_field );

                        if ( empty( $lookup_msg ) ) 
                            continue;
                    }
                }
                else {
                    $info = $this->getEventMsgInfo( $event_group, ...$message_arg );
                }
            }

            // Properly parse object and array values
            $info = $this->parseValueForDb( $info );
            
            $list .= $msg_name . '=' . $info . $this->getEventMsgSeparatorChar();
        }

        return rtrim( $list, $this->getEventMsgSeparatorChar() );
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
     * Get the customized event message argument value
     * 
     * @since 1.0.0
     * 
     * @param  string  $event     Specifies the event the field argument belongs to
     * @param  string  $arg       Specifies the event message field argument to get
     * @param  mixed   $default   Specifies default value to use if the event argument is not set
     * @param string   $to_scalar Specifies whether to properly parse array/object values
     * @return mixed              The customized event argument value
     */
    protected function getEventMsgArg( $event, $arg, $default = '', $to_scalar = false )
    {
        if ( ! $this->isEventMsgArgReady( $event ) ) return $default;
        if ( ! $this->eventMsgArgExists( $event, $arg ) ) return $default;

        $data = $this->customize_event_msg_args[ $event ][ $arg ];
        if ( $to_scalar ) {
            $data = $this->parseValueForDb( $data );
        }
        return $data;
    }

    /**
     * Get the event object ID
     * @return int
     */
    protected function getEventMsgObjectId( $event )
    {
        return (int) $this->getEventMsgArg( $event, 'object_id' );
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
        if ( ! $this->eventMsgArgExists( $event, $field ) ) 
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
        if ( ! is_string( $msg ) ) return $msg;

        return preg_replace(
            '/\-{3}([\w ]+)\-{3}/', // trip out the 3 dashes from the event message target
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
    public function formatMsgField( $event, $field, $context = '' )
    {
        $label            = $this->makeFieldReadable( $field );
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
            'events'      => [],
            'description' => '',
        ];

        foreach ( $this->event_list as $group_key => $event_list )
        {
            $event_groups = array_merge( $default_args, $event_list );

            $group  = $event_list['group'];
            $events = $event_list['events'];

            if ( empty( $events ) ) continue;
            if ( empty( $events ) ) continue;

            $this->createMainEventList( $group, $events );
            $this->aggregateEventGroups( $group_key, $event_groups );
        }
    }

    /**
     * Aggregate the event groups
     * @param string $group_key  Specifies the event group key as used in the array
     * @param array  $event_list Specifies the event group list
     */
    protected function aggregateEventGroups( $group_key, array $event_list )
    {
        $title       = $event_list['title'];
        $group       = $event_list['title'];
        $description = $event_list['description'];

        if ( empty( $title ) || empty( $group ) || empty( $description ) ) return;

        $this->aggregated_event_groups[ $group_key ] = [
            'title'       => $title,
            'group'       => $group,
            'object'      => $group,
            'description' => $description,
        ];
    }

    /**
     * Create the main event list
     * @param string $group  Specifies the event group
     * @param array  $events Specifies the events associated with the group
     * 
     * @todo
     * 1. check if event is disabled
     * 
     * -----------------------
     * How events are disabled
     * -----------------------
     * 1. Get all excluded events
     * 2. Check if the event is in any exclude event list
     * 3. If event is listed in the exclude event list, then set the event 'disable' argument to true
     * 
     * --------------------------------
     * How disabled events are treated
     * --------------------------------
     * All registered events are passed to the Auditor Observer controller, which will then perform the 
     * Final step, whether to watch the event or not.
     * If the [disable] event argument is set and evaluates to true, then the Auditor watcher will
     * Ignore such event. Simple!
     * 
     * ---------------------------------------
     * How event notifications are turned off
     * ---------------------------------------
     * 1. Get all event IDs with disabled notifications turned on
     * 2. Set the 'notification' argument for those events to true;
     * 3. The Event Notification Handler will then ignore sending notifications if the event 
     *    'notification' argument is set to true. Cool!
     */
    protected function createMainEventList( $group, array $events )
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
            if ( ! is_array( $event ) ) continue;

            $_event                  = array_merge( $defaults, $event );
            $_event['event_handler'] = array_merge( $event_handler, (array) $_event['event_handler'] );

            if ( empty( $_event['group'] ) ) 
                $_event['group'] = $group;

            if ( empty( $_event['object'] ) ) 
                $_event['object'] = $group;

            if ( ! $this->isEventValid( $_event ) ) continue;

            $event_id = $_event['event_id'];

            /**
             * Use the message '_event_id' argument if set
             */
            if ( isset( $_event['message']['_event_id'] ) 
            && strlen( $_event['message']['_event_id'] ) >= 4 )
            {
                $event_id = $_event['message']['_event_id'];
            }

            // If the event ID is not valid, then we have to skip that event
            if ( ! is_int($event_id) || 0 >= $event_id || 4 > strlen($event_id) ) 
                continue;

            $_event['event'] = $hook;

            /**
             * Everything looks good at this point, let's disable all excluded events
             */
            $this->_is_event_pre_disabled = ( true === (bool) $_event['disable'] );
            
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
            
            if ( true !== $_event['disable'] && !$this->preIsEventValid( $event_id, $hook, $_event ) ) 
                $_event['disable'] = true;

            /**
             * Set the event notification state
             */
            $_event['notification'] = $this->getEventNotificationState( $event_id, $hook, $_event );

            /**
             * Note: Precedence is giving to the first event if hook is already registered
             */

            // Note: we could used an array to hold all the event IDs, but we don't want to.
            // Reason is because we want to enforce the usage of unique event ID, no duplicates.
            if ( ! isset( $this->main_event_list[ $event_id ] ) ) 
                $this->main_event_list[ $event_id ] = $_event;

            /**
             * Namespace the event slug list with the event group name
             * Since event hook name may appear more than once given the event ID
             * For example: there's the comment and user meta fields, having the
             * First name, last name hooks.
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
    public function getEventSlugById( $event_id, $default_slug = '' )
    {
        if ( ! is_scalar( $event_id ) ) 
            return '';

        $_id            = $this->sanitizeOption( $event_id, 'int' );
        $flip_slug_list = array_flip( $this->event_slug_list );

        if ( ! isset( $flip_slug_list[ $event_id ] ) ) 
            return is_string( $default_slug ) ? $default_slug : '';

        return $flip_slug_list[ $event_id ];
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
     *                             Otherwise 0 is returned.
     */
    public function getEventIdBySlug( $event_hook, $event_group = '' )
    {
        $event_hook_namespace = ltrim( "{$event_group}_{$event_hook}", '_' );

        if ( ! isset( $this->event_slug_list[ $event_hook_namespace ] ) ) 
            return 0;

        return $this->event_slug_list[ $event_hook_namespace ];
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
        if ( ! $this->activeEventHasErrorFlag() ) 
            return false;

        $event_successor = $this->getVar( $this->active_event, 'event_successor' );
        if ( is_array( $event_successor ) ) {
            $event_successor_slug  = $event_successor[1];
            $event_successor_group = $event_successor[0];

            // Lookup the event successor ID
            $event_ID = $this->getEventIdBySlug( $event_successor_slug, $event_successor_group );
        }
        else {
            $event_ID = (int) $this->getVar( $this->active_event, 'event_successor' );
        }

        if ( $event_ID < 1 )
            return false;

        if ( $event_ID == $this->active_event_ID )
            return false;

        /**
         * Get the most recent (last inserted) error log data for the 
         * event successor
         */

        /**
         * Filters the active event error log data fields to return from the query.
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
        $selected_active_event_error_fields = apply_filters(
            'alm/event/log/update/selected_fields',
            [ 
                'log_id', 'event_id', 'log_status', 'log_counter', 'event_action_trigger', 'user_data', 'object_data', 'metadata', 'message',
            ],
            $this->active_event_ID,
            $user_id,
            $object_id
        );

        $this->active_event_error_log_data = $this->DB
            ->reset()
            ->select( $selected_active_event_error_fields )
            ->from( $this->tables->activity_logs )
            ->where()
            ->isBlog( $this->current_blog_ID                )
            // ->and( 'user_id', $user_id,               '='   )
            ->and( 'event_id',  $this->active_event_ID, '=' )
            ->and( 'object_id', $object_id,             '=' )
            ->and( 'source_ip', $this->getTopLevelIp(), '=' )
            ->and()
            ->dateRange( 'created_at', $this->getEventLogIncrementLimit(), 'now' )
            ->orderBy( 'log_id' )
            ->isDesc()
            ->limit(1)
            ->isResultArray()
            ->getRow();

        if ( empty( $this->active_event_error_log_data ) )
            return false;

        // Get the event ID for the most recent event successor
        // error log counter
        $max_error_log_counter_event_id = (int) $this->getVar(
            $this->active_event_error_log_data, 'log_id'
        );

        if ( $max_error_log_counter_event_id < 1 ) 
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
        return ( $max_error_log_counter_event_id > $event_successor_id );
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
     * Setup and log the active triggered event data
     * 
     * @since 1.0.0
     * 
     * @param string $event_group Specifies which group the even belongs to.
     * 
     * @param string $method_name Specifies the class handler method that triggered the event.
     *                            In a class, this is equivalent, to the __METHOD__ constant.
     *                            Note: For correct functionality, this should be specified 
     *                            in the class where it is being called.
     */
    protected function LogActiveEvent( $event_group, $method_name = __METHOD__ )
    {
        $split_method_name = explode( '::', $method_name );
        if ( empty( $split_method_name ) )
            return;

        $_method_name = end( $split_method_name );

        $event_hook           = preg_replace( '/_event$/', '', $_method_name );
        $event_hook_namespace = "{$event_group}_{$event_hook}";

        if ( ! isset( $this->event_slug_list[ $event_hook_namespace ] ) )
            return;

        $event_id = $this->event_slug_list[ $event_hook_namespace ];

        if ( ! isset( $this->main_event_list[ $event_id ] ) )
            return;

        // Setup the active event
        $this->active_event      = $this->main_event_list[ $event_id ];

        $this->active_event_ID   = $event_id;

        // We have to prefix the event with the event group name
        $this->active_event_slug = $this->getEventSlugById(
            $this->active_event_ID, $event_hook 
        );

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
         * This is used to automatically log the prepared active event data.
         * 
         * The Log() method can be called manually, without specifying the 
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
}