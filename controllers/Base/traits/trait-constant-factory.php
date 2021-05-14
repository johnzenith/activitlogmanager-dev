<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * String Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait ConstantFactory
{
    /**
     * Set constant
     * @see define()
     */
    public function setConstant( $name, $value )
    {
        if ( ! defined( $name ) ) 
            define( $name, $value );
    }

    /**
     * Check if a constant is defined
     * @see define()
     */
    public function getConstant( $name )
    {
        if (defined($name))
            return constant( $name );

        return false;
    }
}