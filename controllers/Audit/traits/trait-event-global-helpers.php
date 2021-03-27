<?php
namespace ALM\Controllers\Audit\Traits;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * @package Event Global Helpers
 * @since   1.0.0
 */

trait EventGlobalHelpers
{
    /**
     * Register global event helpers.
     * This is used to retrieve an event data before it is updated.
     * By event data, we mean things such as: post data, comment data, menu data, etc.
     * 
     * -------------------
     * Naming conventions:
     * -------------------
     * 
     * For any previous event data, it will be named the corresponding 
     * object slug. For example: the post event object before update will be named as 'post', 
     * also 'comment', 'term', etc.
     * 
     * While for event related data, it will be named the corresponding hook name like so:
     * the {@see wp_insert_post} action hook will be named 'wp_insert_post' and vice versa.
     */
    protected function _registerGlobalEventHelpers()
    {
        $self = $this;

        /**
         * Get the inserted post object.
         * This is useful for event handlers that is connected with the wp_post table 
         * so that the handlers can retrieve its corresponding post data.
         * 
         * @see wp_insert_post()
         * 
         * @todo - maybe this should be removed
         */
        add_action('wp_insert_post', function($post_ID, $post, $update) use (&$self) {
            $self->_pre_event_data['wp_insert_post'] = [
                'ID'     => $post_ID,
                'post'   => $post,
                'update' => $update,
            ];
        }, 10, 3);

        /**
         * Get the post data before an update.
         * 
         * Note: This uses the {@see post_updated} action hook to retrieve the post data 
         * before it is updated.
         */
        add_action('post_updated', function($post_ID, $post_after, $post_before) use (&$self) {
            $self->_pre_event_data['post'] = $post_before;
        }, 10, 3);

        /**
         * Get metadata values before they are updated
         */
        $meta_types = ['post', 'comment', 'term', 'user'];

        foreach ($meta_types as $meta_type) {
            $this->_doMetadataPreviousValueHelper($meta_type);
        }

        /**
         * Get the new term data when it is created
         * 
         * @see wp_insert_term()
         */
        add_filter('wp_insert_term_data', function($data, $taxonomy, $args) use (&$self)
        {
            $term_data = $data;
            
            if (!isset($term_data['description']))
                $term_data['description'] = $self->getVar($args, 'description', '');

            if (!isset($term_data['parent']))
                $term_data['parent'] = $self->getVar($args, 'parent', 0);

            $self->_pre_event_data['wp_insert_term_data'] = $term_data;

            return $data;
        }, 999, 3);

        /**
         * Get the previous term data before it is updated
         * 
         * @see wp_update_term()
         */
        add_action('edit_terms', function($term_id, $taxonomy) use (&$self)
        {
            $self->_pre_event_data['term'] = get_term($term_id, $taxonomy, ARRAY_A);
        }, 10, 2);

        /**
         * Get the previous term taxonomy relationship data before it is updated
         * 
         * @see wp_update_term()
         */
        add_action('edit_term_taxonomy', function($tt_id, $taxonomy) use (&$self)
        {
            $prev_term_taxonomy = $this->getTermTaxonomyById( $self->getTermTaxonomyId($tt_id, $taxonomy) );

            // Get the parent category name parent_term_name
            $parent = $self->sanitizeOption($self->getVar($prev_term_taxonomy, 'parent', 0), 'int');

            if ($parent > 0) {
                $parent_name = $self->sanitizeOption($self->getVar(
                    get_term($parent, $taxonomy),
                    'name',
                    'Unknown'
                ));
            } else {
                $parent_name = 'None';
            }

            /**
             * Get the term taxonomy count before it is updated
             * @see _update_post_term_count()
             * @see _update_generic_term_count()
             */
            $prev_term_taxonomy['count'] = $self->getTermTaxonomyCount($tt_id);

            $prev_term_taxonomy['parent_name'] = $parent_name;

            $self->_pre_event_data['edit_term_taxonomy'] = $prev_term_taxonomy;
        }, 10, 2);

        /**
         * Get the filtered term parent during update
         * 
         * @see wp_update_term()
         */
        add_filter('wp_update_term_parent', function($term_parent, $term_id, $taxonomy, $parsed_args, $args) use (&$self) {
            $self->_pre_event_data['wp_update_term_parent'] = $term_parent;
            return $term_parent;
        }, 999, 5);

        /**
         * Get the filtered term data during update
         * 
         * @see wp_update_term()
         */
        add_filter('wp_update_term_data', function($data, $term_id, $taxonomy, $args) use (&$self)
        {
            $term_data = $data;
            
            if (!isset($term_data['description']))
                $term_data['description'] = $self->getVar($args, 'description', '');
            
            $term_data['parent'] = $self->_getPreEventData('wp_update_term_parent');

            $self->_pre_event_data['wp_update_term_data'] = $term_data;

            return $data;
        }, 999, 4);

        /**
         * Get the term data before it is deleted
         * 
         * @see wp_delete_term()
         */
        add_action('pre_delete_term', function($term_id, $taxonomy) use (&$self)
        {
            $term_data = get_term( $term_id, $taxonomy );
            
            $name      = $self->getVar($term_data, 'name', '');
            $slug      = $self->getVar($term_data, 'slug', '');
            $parent    = $self->getVar($term_data, 'parent', '');

            $self->_pre_event_data['pre_delete_term'] = compact('term_id', 'taxonomy', 'name', 'slug', 'parent');
        }, 10, 2);

        /**s
         * Get the term data before it is deleted
         * 
         * @see wp_delete_term()
         */
        add_action('delete_term_taxonomy', function($tt_id) use (&$self)
        {
            // the 'pre_delete_term' action hook is fired before the 'delete_term_taxonomy' action hook.
            // Let's use it to retrieve related term taxonomy data
            $term_data = $self->_getPreEventData('pre_delete_term');

            $term_data['tt_id'] = $tt_id;

            $self->_pre_event_data['delete_term_taxonomy'] = array_merge( $term_data, $self->getTermTaxonomyById($tt_id) );
        });

        /**
         * Get the term data before its parent it updated
         * 
         * @see wp_delete_term()
         * @todo - remove
         */
        add_action('edit_term_taxonomies', function($edit_tt_ids) use (&$self)
        {
            $self->_pre_event_data['edit_term_taxonomies'] = [];
        });
    }

    /**
     * Setup the pre event data
     * 
     * @see EventList::_prePreEventData()
     * 
     * @param string  $name  Specifies the type of event
     * @param mixed   $data  Specifies the event data
     */
    protected function setupPreEventData($name, $data = [])
    {
        $this->_pre_event_data[ $name ] = $data;
    }

    /**
     * Get the event data before it is updated
     * 
     * @param  string     $name Specifies the type of event
     * 
     * @param string      $key  Specifies the array key to use to retrieve the value 
     *                          if previous value is an array or object.
     *                          Note: if $key is specified and the key does not exists 
     *                          in the array/object, then it
     * 
     * @return mixed|null       The pre-event data on success. Otherwise null.
     */
    protected function _getPreEventData($name, $key = '')
    {
        $data = $this->getVar($this->_pre_event_data, $name, null);
        if (empty($key)) 
            return $data;

        return $this->getVar($data, $key, $data);
    }

    /**
     * Get metadata previous value helper
     * @see get_metadata()
     */
    private function _doMetadataPreviousValueHelper( $meta_type )
    {
        $self = $this;

        add_action("update_{$meta_type}_meta", function( $meta_id, $object_id, $meta_key, $meta_value ) use (&$self, &$meta_type)
        {
            // Get the previous value
            $prev_value = get_metadata_raw( $meta_type, $object_id, $meta_key );
            if ( is_countable($prev_value ) && count( $prev_value ) === 1 ) {
                if ($prev_value[0] === $meta_value ) {
                    return;
                }
                $prev_value = $prev_value[0];
            }

            if ($prev_value != $meta_value) {
                $self->_pre_event_data[$object_id][$meta_key] = $prev_value;
            }
        }, 10, 4);
    }
}