<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Tools Event Handlers
 * @since   1.0.0
 */
trait ToolsEvents
{
    /**
     * Data export event handler
     * 
     * @since 1.0.0
     */
    public function alm_export_data_event( $args )
    {
        $export_args      = $this->getVar($args, 'export_args',  []);

        $exported_content = $this->getVar($export_args, 'content', '');

        $break_line       = $this->getEventMsgLineBreak();
        $break_line_2     = str_repeat($break_line, 2);
        
        $event_slug       = $this->getEventSlugByEventHandlerName(__FUNCTION__);
        $event_id         = $this->getEventIdBySlug($event_slug, 'tool');
        $event_data       = $this->getEventData($event_id);
        $event_msg_args   = $this->getVar($event_data, 'message', []);

        $object_id        = 0;

        // Set the export content context
        $exported_content_title   = '';
        $exported_content_filter  = '_ignore_';
        $exported_content_context = '';

        $export_info = sprintf(
            'Export filters: none%s',
            $break_line_2
        );

        switch ($exported_content) {
        case 'all':
            $exported_content_title   = 'All content';
            $exported_content_context = 'all of the posts, pages, comments, custom fields, terms, navigation menus, and custom posts';
            break;

        default:
            $exported_content_title = sprintf(
                '%s content',
                $this->makeFieldReadable($exported_content)
            );

            $exported_content_context = strtolower($exported_content_title);
            break;
        }

        // Get the export filters
        if ($exported_content !== 'all') {
            $exported_content_filter = 'Export filters' . $break_line_2;
            foreach ($export_args as $filter => $export_arg)
            {
                // Skip the 'content' offset
                if ($filter === 'content') continue;

                if (false === $export_arg) {
                    if ($this->strEndsWith($filter, '_date')) {
                        $filter_value = 'Not specified (default: all dates)';
                    } else {
                        $filter_value = 'Not specified (default: all)';
                    }
                } else {
                    $filter_value = $this->sanitizeOption($export_arg);
                }

                $exported_content_filter .= sprintf(
                    '%s: %s%s',
                    $this->makeFieldReadable($filter),
                    $filter_value,
                    $break_line
                );
            }

            $export_info = $exported_content_filter . $break_line;        
        }

        $export_info .= sprintf(
            'Filename where the exported data was saved: %s',
            $this->sanitizeOption($this->getVar($args, 'wp_filename', 'Unknown'))
        );

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $exported_content_context);
        $this->overrideActiveEventData('message', $event_msg_args);

        // Event title
        $this->overrideActiveEventData(
            'title',
            sprintf(
                $this->getVar($event_data, 'title', '%s exported from the site'),
                $exported_content_title
            )
        );
        
        $setup_event_data = compact(
            'object_id',
            'export_info'
        );

        $this->setupEventMsgData('tool', $setup_event_data);
        $this->LogActiveEvent('tool', __METHOD__);
    }

    /**
     * Personal data export request helper
     * 
     * @see   \ALM\Controllers\Audit\Events\Handlers\ToolsEvents::registerToolsEvents()
     * @since 1.0.0
     * 
     * @return array
     */
    protected function _alm_personal_data_request_helper( $post_ID, $post, $update )
    {
        $object_id   = $this->sanitizeOption($post_ID, 'int');
        $post_type   = $this->sanitizeOption($this->getVar($post, 'post_type'));
        $post_author = $this->sanitizeOption($this->getVar($post, 'post_author', 0), 'int');

        // This is the user email
        $post_title = $this->sanitizeOption($this->getVar($post, 'post_title'));

        $post_data  = [
            'post_name'     => $this->sanitizeOption($this->getVar($post, 'post_name')),
            'post_title'    => $post_title,
            'post_status'   => $this->sanitizeOption($this->getVar($post, 'post_status')),
            'post_type'     => $post_type,
            'post_password' => $this->sanitizeOption($this->getVar($post, 'post_password')),
        ];

        $request_expiration_time = '_ignore_';
        
        $user_id                 = $post_author;
        $_current_user_id        = $post_author;
        
        $is_request_failed       = 'request-failed' === $post_data['post_status'];
        $is_request_deleted      = !empty($this->_getPreEventData('delete_post'));

        $has_email_confirmation  = isset($_REQUEST['send_confirmation_email']);
        $confirmation_email      = $has_email_confirmation ? 
            'Required' : 'Not required';

        $explain_msg = !$has_email_confirmation ? 
            'Email confirmation by the user is not required for this data export request since the email confirmation checkbox was unchecked during the time the export request was created.'
            : 
            'Email confirmation by the user is required for this data export request. So nothing will happen until the request is confirmed.';

        $user_profile_url  = $this->getWpCoreSettingsPage('export-personal-data');
        $user_profile_url .= "?s={$post_title}";

        $explain_msg .= sprintf(
            '<a href="%s" target="_blank">Click here</a> to view all personal data requests for this user (%s).',
            empty($user_profile_url) ? '#' : $user_profile_url,
            $post_title
        );

        if (!$is_request_deleted) {
            $this->explainCurrentEventMsg('tool', $explain_msg);
        }

        // Set th user data for used in the message arguments
        $this->setupUserEventArgs(
            compact('user_id', '_current_user_id')
        );

        /*
         * This request originates from the WordPress post table.
         * Let's setup the right object type.
         */
        $this->overrideActiveEventData('object',    $post_type);
        $this->overrideActiveEventData('object_id', $object_id);

        $show_expiration_time_info = (
            $has_email_confirmation 
            || $is_request_failed 
            || $is_request_deleted
        );

        // Show the request expiration time when confirmation email is set,
        // Or when the request has failed.
        if ($show_expiration_time_info && $this->user_key_expiration_time) {
            $request_expiration_time = $this->getDataInPluginFormat(
                date('Y-m-d H:i:s', $this->user_key_expiration_time)
            );
        }

        // Explain the event message for failed event.
        if ($is_request_failed && !$is_request_deleted) {
            $explain_msg = 'The system marked the request as failed because the request expiration time has elapsed.';
            $this->explainCurrentEventMsg('tool', $explain_msg);
        }

        $setup_event_data = array_merge(
            $this->getVar($this->customize_event_msg_args, 'user', []),
            compact('object_id', 'post_data', 'confirmation_email', 'request_expiration_time'),
        );

        $setup_event_data['user_id'] = $post_author;
        
        // Set the event object ID
        $this->setupEventObjectIdLabels('tool', 'User Request ID');

        return $setup_event_data;
    }

    /**
     * Create personal data export request event handler
     * 
     * @since 1.0.0
     */
    public function alm_create_personal_data_export_request_event( $post_ID, $post, $update )
    {
        $setup_event_data = $this->_alm_personal_data_request_helper(
            $post_ID, $post, $update
        );

        $this->setupEventMsgData('tool', $setup_event_data);
        $this->LogActiveEvent('tool', __METHOD__);
    }

    /**
     * Resend personal data export request event handler
     * 
     * @since 1.0.0
     */
    public function alm_resend_personal_data_export_request_event( $post_ID, $post, $update )
    {
        $setup_event_data = $this->_alm_personal_data_request_helper(
            $post_ID, $post, $update
        );

        $this->setupEventMsgData('tool', $setup_event_data);
        $this->LogActiveEvent('tool', __METHOD__);
    }

    /**
     * Completed personal data export request event handler.
     * 
     * @since 1.0.0
     */
    public function alm_personal_data_export_request_completed_event( $post_ID, $post, $update )
    {
        $setup_event_data = $this->_alm_personal_data_request_helper(
            $post_ID, $post, $update
        );

        $this->setupEventMsgData('tool', $setup_event_data);
        $this->LogActiveEvent('tool', __METHOD__);
    }

    /**
     * Personal data export request failed event handler.
     * 
     * @since 1.0.0
     */
    public function alm_personal_data_export_request_failed_event( $post_ID, $post, $update )
    {
        $setup_event_data = $this->_alm_personal_data_request_helper(
            $post_ID, $post, $update
        );

        $this->setupEventMsgData('tool', $setup_event_data);
        $this->LogActiveEvent('tool', __METHOD__);
    }

    /**
     * Personal data export request failed event handler.
     * 
     * @since 1.0.0
     */
    public function alm_personal_data_export_request_deleted_event( $post_ID, $post, $update )
    {
        $setup_event_data = $this->_alm_personal_data_request_helper(
            $post_ID, $post, $update
        );

        $this->setupEventMsgData('tool', $setup_event_data);
        $this->LogActiveEvent('tool', __METHOD__);
    }
}