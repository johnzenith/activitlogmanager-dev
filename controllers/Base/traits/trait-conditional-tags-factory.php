<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * Browser Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */
trait ConditionalTags
{
    /**
     * Check whether a pluggable function exists.
     * 
     * @since 1.0.0
     * 
     * @param string $pluggable  Specify the pluggable function to check for.
     * @return bool              True if the pluggable function exists. Otherwise false.
     */
    public function isPluggable( $pluggable )
    {
        return function_exists( "alm_{$pluggable}" );
    }
    
    /**
     * Check whether the post type should not be logged if it has been 
     * handled individually.
     * 
     * @since 1.0.0
     * 
     * @param string $post_type Specifies the post type to check for.
     * 
     *@return bool True if the post type is handle individually.
     *             Otherwise false.
     */
    public function isPostTypeEventWatched( $post_type = '' )
    {
        return (
            !empty($this->getConstant("ALM_HAS_POST_TYPE_EVENT_$post_type"))
            || 
            $this->getConstant('ALM_HAS_POST_TYPE_EVENT') === $post_type
        );
    }
}