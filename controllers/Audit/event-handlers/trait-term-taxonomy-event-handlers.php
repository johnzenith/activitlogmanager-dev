<?php
namespace ALM\Controllers\Audit\Events\Handlers;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * @package Term Event Handlers
 * @since   1.0.0
 */

trait TermTaxonomyEvents
{    
    /**
     * Prepare the saved term data for logging
     * 
     * @see TermEvents::save_term_event()
     * 
     * @return bool|void Returns true if the term data has been changed during the update.
     *                   Otherwise void.
     */
    private function _saveTermTaxonomyEventHelper($args)
    {
        $tt_id            = $this->getVar($args, 'tt_id', 0);
        $update           = (bool) $this->getVar($args, 'update', false);
        $delete           = (bool) $this->getVar($args, 'delete', false);
        $term_id          = $this->getVar($args, 'term_id', 0);
        $taxonomy         = $this->getVar($args, 'taxonomy', '');
        $event_handler    = $this->getVar($args, 'event_handler', '');
        $term_children    = (bool) $this->getVar($args, 'term_children', false);

        // Term counts
        $new_count        = $this->getVar($args, 'new_term_count', '');
        $previous_count   = $this->getVar($args, 'previous_term_count', '');
        
        // Term object data (Post, Link, etc.)
        $term_obj_ID      = $this->getVar($args, 'post_ID', 0);
        $term_obj_url     = $this->getVar($args, 'post_url', '');
        $term_obj_type    = $this->getVar($args, 'post_type', '');
        $object_type      = $term_obj_type;
        $term_obj_title   = $this->getVar($args, 'post_title', '');
        $term_obj_data    = (bool) $this->getVar($args, 'has_post_data', false);

        $event_slug       = $this->getEventSlugByEventHandlerName($event_handler);
        $event_id         = $this->getEventIdBySlug($event_slug, 'term_taxonomy');
        $event_data       = $this->getEventData($event_id);
        $event_msg_args   = $this->getVar($event_data, 'message', []);

        $break_line       = $this->getEventMsgLineBreak();
        $break_line_2     = str_repeat($break_line, 2);

        if ($delete) {
            $term_data = array_merge($args, (array) $this->_getPreEventData('delete_term_taxonomy'));
        } else {
            $term_data = $update ? 
                $this->_getPreEventData('wp_insert_term_data') 
                : 
                $this->_getPreEventData('wp_update_term_data');

            if (empty($term_data))
                $term_data = get_term($term_id, $taxonomy);
        }
            
        // Get the previous term data when updating
        $update_info          = '';
        $prev_term_data       = $update ? $this->_getPreEventData('edit_term_taxonomy') : [];
        
        $object_id            = $term_id;
        $term_name            = $this->sanitizeOption($this->getVar($term_data, 'name', 'term'));
        $parent_term_ID       = $this->sanitizeOption($this->getVar($term_data, 'parent', 0), 'int');
        $term_description     = $this->sanitizeOption($this->getVar($term_data, 'description', ''));
        $term_taxonomy_ID     = $tt_id;

        $new_parent_info      = '_ignore_';
        $setup_event_data     = [];
        $previous_parent_info = '_ignore_';

        if ($parent_term_ID > 0) {
            $parent_term_name = $this->sanitizeOption($this->getVar(
                get_term($parent_term_ID, $taxonomy), 'name', 'Unknown'
            ));
        } else {
            $parent_term_name = 'None';
        }
        
        // wp_die(var_dump($prev_term_data));

        // Format the event message arguments
        $msg_args = [];
        if (is_array($event_msg_args)) {
            foreach ($event_msg_args as $k => $v)
            {
                if (in_array($k, ['object_term_ID', 'term_id'], true))
                {
                    if ('object_term_ID' === $k) {
                        $term_arg         = "{$term_obj_type}_ID";
                        $label            = $term_arg;
                        $$term_arg        = $term_obj_ID;
                    } else {
                        $term_arg         = "{$taxonomy}_ID";
                        $label            = $term_arg;
                        $$term_arg        = $term_id;
                    }

                    $setup_event_data = array_merge($setup_event_data, compact( $term_arg ));
                }
                // We only want to format array values
                elseif (is_array($v)) {
                    $raw_label = $this->getVar($v, 0, '');
                    $label     = $raw_label;

                    if (empty($label)) continue;

                    if ($this->strStartsWith($label, 'term_') 
                    || $this->strEndsWith($label, '_term') 
                    || false !== strpos($label, '_term_'))
                    {
                        if ($term_obj_data) {
                            $term_arg = 'term_obj_type' === $k ? 
                                'object_type' 
                                : 
                                str_replace('term_obj', $term_obj_type, $label);
                        } else {
                            $term_arg = str_replace('term', $taxonomy, $label);
                        }

                        $label = [ $term_arg ];

                        // Check if the current term field variable is set
                        $has_term_arg = isset($$k);
                        $$term_arg    = $has_term_arg ? $$k : '';

                        // If we're updating
                        if ($update && $has_term_arg) {
                            $split_label = explode('_', $raw_label);

                            if (is_array($split_label)) {
                                $term_field = 'parent' === $this->getVar($split_label, 0, '') ? 
                                    current($split_label) : end($split_label);
                            } else {
                                $term_field = false;
                            }

                            if (isset($prev_term_data[ $term_field ]))
                            {
                                $_term_field = (
                                    'parent' === $term_field && in_array('name', $split_label, true)
                                ) ? 'parent_name' : $term_field;

                                $prev_field_data = $this->sanitizeOption($this->getVar($prev_term_data, $_term_field));

                                if (0 !== strcasecmp((string) $$term_arg, $prev_field_data)) {
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
                                    $$term_arg = '_ignore_';
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
        }

        // Setup the previous/new parent info
        // Note: 'post_tag' taxonomy is not hierarchical
        if ('post_tag' === $taxonomy) {
            $term_children = false;
        }

        if ($term_children) {
            $previous_parent_info = sprintf(
                'Previous parent ID: %s%s',
                $this->getVar($prev_term_data, 'parent', 0),
                $break_line
            );

            $previous_parent_info .= sprintf(
                'Previous parent Name: %s%s',
                $this->getVar($prev_term_data, 'parent_name', 'None'),
                $break_line_2
            );

            $new_parent_info = sprintf(
                'New parent ID: %s%s',
                $parent_term_ID,
                $break_line
            );

            $new_parent_info .= sprintf(
                'New parent Name: %s%s',
                $parent_term_name,
                $break_line_2
            );

            $new_parent_info = $this->rtrim($new_parent_info, $break_line_2);
        }

        // Event main message
        if ($term_children) {
            $main_msg = sprintf($event_msg_args['_main'], $taxonomy, $term_name);
        }
        elseif($term_obj_data) {
            $main_msg = sprintf(
                $event_msg_args['_main'],
                $term_obj_type, $term_obj_title, $term_name, $taxonomy
            );
        }
        else {
            $main_msg = sprintf($event_msg_args['_main'], $term_name, $taxonomy);
        }

        $event_msg_args['_main'] = $main_msg;
        $this->overrideActiveEventData('message', $event_msg_args);

        // Ignore redundant term info
        $term_name        = '_ignore_';
        $parent_term_ID   = '_ignore_';
        $parent_term_name = '_ignore_';
        $term_description = '_ignore_';
        $term_taxonomy_ID = '_ignore_';

        /**
         * Event title
         */
        $set_event_title         = $term_obj_data ? $term_obj_title : $taxonomy;
        $event_title_placeholder = $update ? '%s relationship updated' : 'New %s relationship created';

        $this->overrideActiveEventData('title', sprintf(
            $this->getVar($event_data, 'title', $event_title_placeholder),
            $set_event_title
        ));

        $update_info = $this->rtrim($update_info, $break_line_2);

        $setup_event_data = array_merge($setup_event_data, compact(
            'taxonomy',
            'term_id',
            'object_id',
            'term_name',
            'update_info',
            'previous_count',
            'new_count',
            'parent_term_ID',
            'parent_term_name',
            'term_description',
            'term_taxonomy_ID',
            'new_parent_info',
            'previous_parent_info'
        ));

        $event_obj_data = $setup_event_data;
        unset($event_obj_data['object_id']);

        $setup_event_data['obj_data'] = $event_obj_data;

        $this->setupEventMsgData('term_taxonomy', $setup_event_data);

        // Bail update if no changes were made
        return !empty($update_info);
    }

    /**
     * Fires after a term has been saved, and the term cache has been cleared.
     */
    public function alm_term_taxonomy_saved_event($term_id, $tt_id, $taxonomy, $update)
    {
        /**
         * Nav menu is a term and is handled separately
         */
        if ($this->canBailOutWithCustomEventHandler('term', $taxonomy))
            return;

        if ($update) {
            do_action('alm_term_taxonomy_updated', $term_id, $tt_id, $taxonomy, $update);
        } else {
            $event_handler = __FUNCTION__;
            $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'update');

            $this->_saveTermTaxonomyEventHelper($term_args);
            $this->LogActiveEvent('term_taxonomy', __METHOD__);
        }
    }

    /**
     * Fires after a term has been updated, and the term cache has been cleared.
     */
    public function alm_term_taxonomy_updated_event($term_id, $tt_id, $taxonomy, $update)
    {
        /**
         * Nav menu is a term and is handled separately
         */
        if ($this->canBailOutWithCustomEventHandler('term', $taxonomy))
            return;
            
        $event_handler = __FUNCTION__;
        $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'update');

        if ($this->_saveTermTaxonomyEventHelper($term_args))
            $this->LogActiveEvent('term_taxonomy', __METHOD__);
    }

    /**
     * Fires immediately after a term taxonomy ID is deleted.
     */
    public function deleted_term_taxonomy_event($tt_id)
    {
        $delete        = true;
        $term_id       = 0;
        $taxonomy      = '';
        $event_handler = __FUNCTION__;
        $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'delete');

        // Get the term data
        $term_data     = (array) $this->_getPreEventData('pre_delete_term');

        $term_args     = array_merge($term_args, $term_data);

        /**
         * Nav menu is a term and is handled separately
         */
        if ($this->canBailOutWithCustomEventHandler('term', $this->getVar($term_args, 'taxonomy', '')))
            return;

        $this->_saveTermTaxonomyEventHelper($term_args);
        $this->LogActiveEvent('term_taxonomy', __METHOD__);
    }

    /**
     * Fires immediately after a term to delete's children are reassigned a parent.
     */
    public function edited_term_taxonomies_event($edit_tt_ids)
    {
        if (empty($edit_tt_ids)) return;
    
        /**
         * We would have aggregated the term children but that it will too difficult to 
         * read through and understand the event info.
         */
        foreach ($edit_tt_ids as $tt_id)
        {
            // Setup the term taxonomy data
            $this->setupPreEventData('term_taxonomy', $this->getTermTaxonomyById($tt_id));

            $delete        = true;
            $term_id       = 0;
            $taxonomy      = '';
            $term_children = true;
            $event_handler = __FUNCTION__;

            $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'delete', 'term_children');

            // Get the term data
            $term_data     = (array) $this->_getPreEventData('pre_delete_term');

            $term_args     = array_merge($term_args, $term_data);

            /**
             * Nav menu is a term and is handled separately
             */
            if ($this->canBailOutWithCustomEventHandler('term', $this->getVar($term_args, 'taxonomy', '')))
                return;

            $this->_saveTermTaxonomyEventHelper($term_args);
            $this->LogActiveEvent('term_taxonomy', __METHOD__);
        }
    }

    /**
     * Fires immediately before an object-term relationship is added.
     */
    public function added_term_relationship_event($object_id, $tt_id, $taxonomy)
    {
        // Setup the term taxonomy data
        $this->setupPreEventData('term_taxonomy', $this->getTermTaxonomyById($tt_id));

        $term_id       = 0;
        $event_handler = __FUNCTION__;

        $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy');

        // Get the term data
        $term_data     = get_term($this->getTermIdWithTermTaxonomyId($tt_id), $taxonomy, ARRAY_A);

        // Object data (post, link, etc.)
        $object_data   = $this->getTermObject($taxonomy, $object_id, $tt_id);

        $post_ID       = $this->sanitizeOption($this->getVar($object_data, 'ID', 0), 'int');
        $post_url      = $this->sanitizeOption(get_the_permalink($post_ID), 'url');
        $post_type     = $this->sanitizeOption($this->getVar($object_data, 'post_type'));
        $post_title    = $this->sanitizeOption($this->getVar($object_data, 'post_title'));

        $has_post_data = true; // Specifies that this event requires post data parsing

        $term_args     = array_merge($term_args, $term_data, compact(
            'post_ID', 'post_url', 'post_type', 'post_title', 'has_post_data'
        ));

        $this->_saveTermTaxonomyEventHelper($term_args);
        $this->LogActiveEvent('term_taxonomy', __METHOD__);
    }

    /**
     * Object term relationship helper
     * @see wp_remove_object_terms()
     * @see wp_set_object_terms()
     */
    private function _runObjectTermRelationshipHelper($object_id, $tt_ids, $taxonomy, $is_relationship_deleted = false, $append = false)
    {
        foreach ((array) $tt_ids as $tt_id) {
            $t = get_taxonomy($taxonomy);

            if (!$append && isset($t->sort) && $t->sort) {
                $final_tt_ids = wp_get_object_terms(
                    $object_id,
                    $taxonomy,
                    array(
                        'fields'                 => 'tt_ids',
                        'update_term_meta_cache' => false,
                    )
                );

                // Ignore if not in object terms array
                if (!in_array((int) $tt_id, $final_tt_ids, true))
                    continue;

                // Setup the term taxonomy data
                $this->setupPreEventData('term_taxonomy', $this->getTermTaxonomyById($tt_id));

                $term_id       = 0;
                $event_handler = __FUNCTION__;

                $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy');

                // Get the term data
                $term_data     = get_term($this->getTermIdWithTermTaxonomyId($tt_id), $taxonomy, ARRAY_A);

                // Object data (post, link, etc.)
                $object_data   = $this->getTermObject($taxonomy, $object_id, $tt_id);

                $post_ID       = $this->sanitizeOption($this->getVar($object_data, 'ID', 0), 'int');
                $post_url      = $this->sanitizeOption(get_the_permalink($post_ID), 'url');
                $post_type     = $this->sanitizeOption($this->getVar($object_data, 'post_type'));
                $post_title    = $this->sanitizeOption($this->getVar($object_data, 'post_title'));

                $has_post_data = true; // Specifies that this event requires post data parsing

                $term_args     = array_merge($term_args, $term_data, compact(
                    'post_ID',
                    'post_url',
                    'post_type',
                    'post_title',
                    'has_post_data'
                ));

                $this->_saveTermEventHelper($term_args);
            }
        }
    }

    /**
     * Fires after an object's terms have been set.
     */
    public function set_object_terms_event($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        foreach ((array) $tt_ids as $tt_id)
        {
            $t = get_taxonomy($taxonomy);

            if (!$append && isset($t->sort) && $t->sort)
            {
                $final_tt_ids = wp_get_object_terms(
                    $object_id,
                    $taxonomy,
                    array(
                        'fields'                 => 'tt_ids',
                        'update_term_meta_cache' => false,
                    )
                );

                // Ignore if not in object terms array
                if (!in_array((int) $tt_id, $final_tt_ids, true))
                    continue;

                // Setup the term taxonomy data
                $this->setupPreEventData('term_taxonomy', $this->getTermTaxonomyById($tt_id));

                $term_id       = 0;
                $event_handler = __FUNCTION__;

                $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy');

                // Get the term data
                $term_data     = get_term($this->getTermIdWithTermTaxonomyId($tt_id), $taxonomy, ARRAY_A);

                // Object data (post, link, etc.)
                $object_data   = $this->getTermObject($taxonomy, $object_id, $tt_id);

                $post_ID       = $this->sanitizeOption($this->getVar($object_data, 'ID', 0), 'int');
                $post_url      = $this->sanitizeOption(get_the_permalink($post_ID), 'url');
                $post_type     = $this->sanitizeOption($this->getVar($object_data, 'post_type'));
                $post_title    = $this->sanitizeOption($this->getVar($object_data, 'post_title'));

                $has_post_data = true; // Specifies that this event requires post data parsing

                $term_args     = array_merge($term_args, $term_data, compact(
                    'post_ID',
                    'post_url',
                    'post_type',
                    'post_title',
                    'has_post_data'
                ));

                $this->_saveTermEventHelper($term_args);
                $this->LogActiveEvent('term_taxonomy', __METHOD__);
            }
        }
    }

    /**
     * Fires immediately after a term-taxonomy relationship is updated.
     */
    public function edited_term_taxonomy_event($tt_id, $taxonomy)
    {
        // Setup the term taxonomy data
        $this->setupPreEventData('term_taxonomy', $this->getTermTaxonomyById($tt_id));

        $term_id       = 0;
        $term_count    = true;
        $event_handler = __FUNCTION__;

        $term_args     = compact('event_handler', 'term_id', 'tt_id', 'taxonomy', 'term_count');

        // Get the term data
        $term_data     = get_term($this->getTermIdWithTermTaxonomyId($tt_id), $taxonomy, ARRAY_A);

        $term_args     = array_merge($term_args, $term_data);

        // Previous term taxonomy count
        $previous_term_count = (int) $this->_getPreEventData('edit_term_taxonomy', 'count');

        // New term taxonomy count
        $new_term_count = $this->getTermTaxonomyCount($tt_id);

        /**
         * This event maybe triggered more than once.
         * So let's bail out if the count has not been updated
         */
        if ($new_term_count === $previous_term_count)
            return;

        $term_count_args = compact('previous_term_count', 'new_term_count');

        $this->_saveTermTaxonomyEventHelper(array_merge($term_args, $term_count_args));
        $this->LogActiveEvent('term_taxonomy', __METHOD__);
    }
}
