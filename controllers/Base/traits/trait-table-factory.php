<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Table Factory Template for the Plugin Factory Controller
 * @see   \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait TableFactory
{
    /**
     * Get the WP option ID.
     * On multisite, the sitemata ID will be retrieved if found.
     * 
     * The object ID represents the 'active_plugins' option ID in the:
     * 
     * {@see WordPress options table} on single site or 
     * {@see WordPress sitemeta table} on multisite
     * 
     * @param string $option_name Specifies the option name whose ID should be retrieved
     * 
     * @return int                Returns the option ID if found. Otherwise 0.
     */
    protected function getWpOptionId($option_name = '')
    {
        $table_prefix = $this->getBlogPrefix();

        $field        = 'option_id';
        $table        = $table_prefix . 'options';
        $field_key    = 'option_name';

        if ($this->is_network_admin) {
            $field       = 'meta_id';
            $table       = $table_prefix . 'sitemeta';
            $field_key   = 'meta_key';

            $object_id = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT $field FROM $table WHERE $field_key = %s AND site_id = %d LIMIT 1",
                $option_name,
                $this->main_site_ID
            ));
        } else {
            $object_id = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT $field FROM $table WHERE $field_key = %s LIMIT 1",
                $option_name
            ));
        }
        return $this->sanitizeOption($object_id, 'int');
    }

    /**
     * Get the WP core setting (option) ID.
     * 
     * This is basically a wrapper around the {@see ALM/Controllers/Base/PluginFactory::getWpOptionId()}.
     * 
     * @param string $option_name  Specifies the setting (option) name whose ID should be retrieved.
     * 
     * @return int                 Returns the given option ID if found. Otherwise 0.
     */
    protected function getWpCoreOptionId($option_name = '')
    {
        if (empty($option_name))
            return 0;

        return $this->getWpOptionId($option_name);
    }

    /**
     * Get the term taxonomy ID
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug.
     * @return int             The term taxonomy ID on success. Otherwise 0.
     */
    protected function getTermTaxonomyId($term_id, $taxonomy)
    {
        $tt_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT tt.term_taxonomy_id 
                    FROM {$this->wpdb->term_taxonomy} AS tt 
                    INNER JOIN {$this->wpdb->terms} AS t 
                    ON tt.term_id = t.term_id 
                    WHERE tt.taxonomy = %s 
                    AND t.term_id = %d",
                $taxonomy,
                $term_id
            )
        );

        return $this->sanitizeOption($tt_id, 'int');
    }

    /**
     * Get the term taxonomy data
     * 
     * @param  int    $tt_id         Term taxonomy ID.
     * @param  string $return        Specifies the query return type.
     * 
     * @return WP_Term_Taxonomy|null Returns the WP_Term_Taxonomy object on success. 
     *                               Otherwise null.
     */
    protected function getTermTaxonomyById($tt_id, $return = ARRAY_A)
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT description, parent  
                    FROM {$this->wpdb->term_taxonomy} 
                    WHERE term_taxonomy_id = %d",
                $tt_id
            ),
            $return
        );
    }

    /**
     * Get the term ID given the term taxonomy ID.
     * @param int $tt_id Term taxonomy ID.
     * @return int       The term ID if found.
     */
    protected function getTermIdWithTermTaxonomyId($term_taxonomy_id)
    {
        $term_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT term_id 
                FROM {$this->wpdb->term_taxonomy} 
                WHERE term_taxonomy_id = %d",
            $term_taxonomy_id
        ));
        return $this->sanitizeOption($term_id);
    }

    /**
     * Get the term taxonomy count
     * @param int $tt_id Term taxonomy ID
     * @return int
     */
    protected function getTermTaxonomyCount($tt_id)
    {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT count 
                FROM {$this->wpdb->term_taxonomy} 
                WHERE term_taxonomy_id = %d",
            $tt_id
        ));

        return $this->sanitizeOption($count, 'int');
    }

    /**
     * Get the term object
     * @param string        $taxonomy   Name of taxonomy object to return.
     * @param int           $object_id  Object ID (post, link, etc.).
     * @param int           $tt_id      Term taxonomy ID.
     * @return object|null              Returns selected term object data on success. Otherwise null.
     */
    protected function getTermObject($taxonomy, $object_id, $tt_id)
    {
        $t            = get_taxonomy($taxonomy);
        $object_types = implode("', '", (array) $this->getVar($t, 'object_type', []));

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT wpt.ID, wpt.post_title, wpt.post_type 
                FROM {$this->wpdb->term_relationships} as wtr, {$this->wpdb->posts} as wpt 
                WHERE wpt.ID = wtr.object_id 
                AND wpt.ID = %d 
                AND post_status = 'publish' 
                AND post_type IN ('" . $object_types . "') 
                AND term_taxonomy_id = %d",
            $object_id,
            $tt_id
        ));
    }
}