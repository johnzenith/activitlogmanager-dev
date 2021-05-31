<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/*
 * Set Constants Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait SetConstantsFactory
{
    /*
     * This constants specifies that the given post type event has been 
     * handed individually.
     * 
     * @since 1.0.0
     * 
     * @param string $post_type Specifies the post type to set constant for.
     */
    public function maybeSetPostTypeEventWatch( $post_type = '' )
    {
        $this->setConstant("ALM_HAS_POST_TYPE_EVENT_{$post_type}", true);
    }
}