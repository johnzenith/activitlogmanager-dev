<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('You are not allowed to do this on your own.');

/**
 * @package Term Event Handlers
 * @since   1.0.0
 */

trait TermEvents
{
    /**
     * Prepare the saved term data for logging
     * 
     * @see TermEvents::save_term_event()
     * 
     * @return bool|void Returns true if the term data has been changed during update.
     *                   Otherwise void.
     */
    private function _saveTermEventHelper($args)
    {
        $tt_id            = $this->getVar($args, 'tt_id', 0);
        $update           = (bool) $this->getVar($args, 'update', false); 
        $delete           = (bool) $this->getVar($args, 'delete', false);
        $term_id          = $this->getVar($args, 'term_id', 0);
        $taxonomy         = $this->getVar($args, 'taxonomy', '');
        $object_ids       = $this->getVar($args, 'object_ids', []);
        $deleted_term     = $this->getVar($args, 'deleted_term', []);
        $event_handler    = $this->getVar($args, 'event_handler', '');

        $event_slug       = $this->getEventSlugByEventHandlerName($event_handler);
        $event_id         = $this->getEventIdBySlug($event_slug, 'term');
        $event_data       = $this->getEventData($event_id);
        $event_msg_args   = $this->getVar($event_data, 'message', []);

        $break_line       = $this->getEventMsgLineBreak();
        $break_line_2     = str_repeat($break_line, 2);

        if ($delete) {
            $term_data = $deleted_term;
        } else {
            $term_data = $update ? 
                $this->_getPreEventData('wp_insert_term_data') 
                : 
                $this->_getPreEventData('wp_update_term_data');
                
            if (empty($term_data))
                $term_data = get_term($term_id, $taxonomy);
        }

        // Get the previous term data when updating
        $update_info      = '';
        $prev_term_data   = $update ? $this->_getPreEventData('term') : [];
        
        $object_id        = $term_id;
        $term_name        = $this->sanitizeOption($this->getVar($term_data, 'name', 'term'));
        $term_slug        = $this->sanitizeOption($this->getVar($term_data, 'slug', 'Unknown'));
        $term_group       = $this->sanitizeOption($this->getVar($term_data, 'term_group', 0));
        $term_taxonomy_ID = $tt_id;
        $setup_event_data = [];

        // Format the event message arguments
        $msg_args = [];
        if (is_array($event_msg_args)) {
            foreach ($event_msg_args as $k => $v)
            {
                if ('term_id' === $k) {
                    $term_arg         = "{$taxonomy}_ID";
                    $label            = $term_arg;
                    $$term_arg        = $term_id;
                    $setup_event_data = array_merge($setup_event_data, compact(
                        $term_arg
                    ));
                }
                // We only want to format array values
                elseif (is_array($v)) {
                    $raw_label = $this->getVar($v, 0, '');
                    $label     = $raw_label;

                    if (empty($label)) continue;

                    if ($this->strStartsWith($label, 'term_'))
                    {
                        $term_arg = str_replace('term_', "{$taxonomy}_", $label);
                        $label    = [ $term_arg ];

                        // Check if the current term field variable is set
                        $has_term_arg = isset($$k);
                        $$term_arg    = $has_term_arg ? $$k : '';

                        if ($update && $has_term_arg) {
                            $split_label = explode('_', $raw_label);
                            $term_field  = is_array($split_label) ? end($split_label) : false;

                            if (isset($prev_term_data[ $term_field ])) {
                                $prev_field_data = $this->sanitizeOption($this->getVar($prev_term_data, $term_field));

                                if (0 !== strcasecmp($$term_arg, $prev_field_data)) {
                                    $new_field_name      = $this->makeFieldReadable("new_{$term_arg}");
                                    $previous_field_name = $this->makeFieldReadable("previous_{$term_arg}");

                                    $update_info .= sprintf(
                                        '%s: %s%s',
                                        $previous_field_name,
                                        $prev_field_data,
                                        $break_line
                                    );

                                    $update_info .= sprintf(
                                        '%s: %s%s',
                                        $new_field_name,
                                        $$term_arg,
                                        $break_line_2
                                    );
                                    
                                    // Ignore the specific term field in the event message
                                    $kk = '_ignore_';
                                }
                            }
                        }

                        if ($has_term_arg) {
                            $setup_event_data = array_merge($setup_event_data, compact(
                                $term_arg
                            ));
                        }
                    }
                }
                else {
                    $label = $v;
                }
                
                $msg_args[ $k ] = $label;
            }
        }

        if (!empty($msg_args)) {
            $event_msg_args = $msg_args;

            // Ignore the term_slug, term_group, and term_taxonomy_ID
            $term_slug        = '_ignore_';
            $term_group       = '_ignore_';
            $term_taxonomy_ID = '_ignore_';
        }

        // Event main message
        $event_msg_args['_main'] = sprintf($event_msg_args['_main'], $taxonomy, $term_name);
        $this->overrideActiveEventData('message', $event_msg_args);

        // Ignore the term_name
        $term_name = '_ignore_';

        // Event title
        $event_title_placeholder = $update ? '%s updated' : 'New %s created' ;
        $this->overrideActiveEventData('title', sprintf(
            $this->getVar($event_data, 'title', $event_title_placeholder),
            $taxonomy
        ));

        $update_info = $this->rtrim($update_info, $break_line_2);

        $setup_event_data = array_merge($setup_event_data, compact(
            'taxonomy',
            'object_id',
            'term_name',
            'term_slug',
            'term_group',
            'update_info',
            'term_taxonomy_ID'
        ));

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('term', $setup_event_data);

        // Bail update if no changes were made
        return !empty($update_info);
    }

    /**
     * Fires after a term has been saved, and the term cache has been cleared.
     */
    public function saved_term_event($term_id, $tt_id, $taxonomy, $update)
    {
        /**
         * Nav menu is a term and is handled separately
         */
        if ($this->canBailOutWithCustomEventHandler('term', $taxonomy))
            return;

        if ($update) {
            do_action('alm_term_updated', $term_id, $tt_id, $taxonomy, $update);
        } else {
            $event_handler = __FUNCTION__;
            $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'update');

            $this->_saveTermEventHelper($term_args);
            $this->LogActiveEvent('term', __METHOD__);
        }

        /**
         * Trigger the term taxonomy event
         * 
         * @see wp_insert_term()
         */
        do_action(
            'alm_term_taxonomy_saved',
            $term_id,
            $tt_id,
            $taxonomy,
            $update
        );
    }

    /**
     * Fires after a term has been updated, and the term cache has been cleared.
     */
    public function alm_term_updated_event($term_id, $tt_id, $taxonomy, $update)
    {
        /**
         * Nav menu is a term and is handled separately
         */
        if ($this->canBailOutWithCustomEventHandler('term', $taxonomy))
            return;

        $event_handler = __FUNCTION__;
        $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'update');

        if ($this->_saveTermEventHelper($term_args))
            $this->LogActiveEvent('term', __METHOD__);
    }

    /**
     * Fires after a term has been updated, and the term cache has been cleared.
     */
    public function delete_term_event($term_id, $tt_id, $taxonomy, $deleted_term, $object_ids)
    {
        /**
         * Nav menu is a term and is handled separately
         */
        if ($this->canBailOutWithCustomEventHandler('term', $taxonomy))
            return;

        $delete        = true;
        $event_handler = __FUNCTION__;
        
        $term_args     = compact(
            'tt_id',
            'term_id',
            'delete',
            'taxonomy',
            'object_ids',
            'deleted_term',
            'event_handler'
        );

        $this->_saveTermEventHelper($term_args);
        $this->LogActiveEvent('term', __METHOD__);
    }
}