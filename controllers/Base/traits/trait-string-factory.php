<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined('ALM_PLUGIN_FILE') || exit('!!!');

/**
 * String Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

trait StringFactory
{
    /**
     * Get the last character in a string
     * @param  string $str Specifies the string to get the last character from
     * @return string      The string last character.
     */
    public function strLastChar( $str )
    {
        if ( ! is_scalar( $str ) ) 
            return '';

        $str = (string) $str;
        return empty( trim($str) ) ? '' : substr( $str, -1, 1 );
    }

    /**
     * Check whether a string ends with a given pattern
     * 
     * @param  string       $str              Specify string to check for given pattern
     * 
     * @param  string|array $pattern          The pattern to check for at end of string.
     *                                        This can be a string or list of string.
     * 
     * @param bool          $case_insensitive Specifies whether the check should be case 
     *                                        sensitive or not
     * 
     * @return bool                           True if the given string ends with the given pattern.
     *                                        Otherwise false.
     */
    public function strEndsWith( $str, $pattern, $case_insensitive = false )
    {
        $_pattern = (array) $pattern;
        foreach ( $_pattern as $p )
        {
            if ( ! is_array( $p ) )
            {
                $match = '/'. preg_quote($p, '/') .'$/' . ($case_insensitive ? 'i' : '');;
                if ( preg_match( $match, $str ) ) 
                    return true;
            }
        }
        return false;
    }

    /**
     * Check whether a string starts with a given pattern
     * 
     * @param  string       $str              Specify string to check for given pattern
     * 
     * @param  string|array $pattern          The pattern to check for at beginning of string.
     * 
     * @param bool          $case_insensitive Specifies whether the check should be case 
     *                                        sensitive or not
     * 
     * @return bool                           True if the given string starts with the given pattern.
     *                                        Otherwise false.
     */
    public function strStartsWith( $str, $pattern, $case_insensitive = false )
    {
        $_pattern = (array) $pattern;
        foreach ( $_pattern as $p )
        {
            if ( ! is_array( $p ) )
            {
                $match = '/^'. preg_quote($p, '/') .'/' . ($case_insensitive ? 'i' : '');
                if ( preg_match( $match, $str ) ) 
                    return true;
            }
        }
        return false;
    }

    /**
     * Generate a cryptographically secure numbers
     * 
     * @param int     $min     The minimum number range to used in generating the secure numbers
     * @param int     $max     The maximum number range to used in generating the secure numbers
     * @param int     $length  Specify the length of the generated secure numbers
     * 
     * @return string          Returns the generated cryptographically secure numbers on success.
     *                         An empty string is returned when $min or $max is not an integer.
     */
    public function generateSecureInt( $min = 0, $max = 9, $length = 16 )
    {
        if ( ! is_int( $min ) || ! is_int( $max ) ) return '';

        $length     = ( $length < 1 ) ? 1 : $length;
        $secure_str = '';

        for ( $i = 0; $i <= 16; $i++ ) {
            $secure_str .= random_int( $min, $max );
        }
        return $secure_str;
    }

    /**
     * Get alphabet list in uppercase or lowercase
     * 
     * @param string  $alphabet  Specify the alphabet letters to return, whether lowercase or 
     *                              uppercase. Values accepted: 'lowercase' | 'uppercase'.
     *                              Note: This function returns a combination of uppercase and 
     *                              lowercase letters when the alphabet type to return is not 
     *                              specified.
     * 
     * @return string             The specified alphabet letters.
     */
    public function getAlphabets( $alphabet = '' )
    {
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        if ( 'uppercase' === $alphabet ) return $uppercase;
        if ( 'lowercase' === $alphabet ) return $lowercase;

        return $lowercase . $uppercase;
    }

    /**
     * Sanitize string data prior to specified context
     * 
     * @see /wp-includes/default-filters.php
     * @see /wp-includes/user.php
     * 
     * @return string The sanitized string data
     */
    public function sanitizeStr( $str = '', $context = 'display' )
    {
        if (empty($str)) return $str;

        if (!is_string($str) && !is_numeric($str)) return $str;

        switch($context)
        {
            case 'raw':
                return $str;

            case 'db':
                $str = sanitize_text_field( $str );
                $str = wp_filter_kses( $str );
                $str = _wp_specialchars( $str );
                return $str;

            case 'description':
                return esc_html( $str );

            case 'attr':
            case 'attribute':
                return esc_attr( $str );

            case 'js':
                return esc_js( $str );

            case 'user_url':
                return esc_url( $str );

            case 'url_raw':
                return esc_url_raw( $str );

            case 'display':
                if ( $this->is_admin ) {
                    // These are expensive. Run only on admin pages for defense in depth.
                    $str = sanitize_text_field( $str );
                    $str = wp_kses_data( $str );
                }
                $str = _wp_specialchars( $str );
                return $str;

            case 'textarea_save':
                return wp_filter_kses( $str );

            default:
                return _wp_specialchars( wp_kses_data( $str ) );
        }
    }

    /**
     * Join values from an array with a given separator character
     * @param string $data      Specifies the array data to join
     * @param string $separator Specifies the character to use to separate the array values
     */
    public function joinValues( array $data, $separator = '!|!' )
    {
        if (!is_string($separator)) 
            $separator = '!|!';

        $join = '';
        foreach ($data as $k => $v)
        {
            if ( is_array($v) || is_object($v) ) {
                $join .= (empty($join) ? '' : str_repeat($separator, 2)) . "[{$k}]{$separator}";
                $join .= $this->joinValues( $v, $separator );
            } else {
                $join .= "{$k}={$v}{$separator}";
            }
        }
        return rtrim($join, $separator);
    }

    /**
     * Get the event metadata separator character
     * @return string
     */
    public function getEventMetadataSeparatorChar()
    {
        return '!|!';
    }

    /**
     * Get the event message character separator
     * @return string
     */
    protected function getEventMsgSeparatorChar()
    {
        return '!|||!';
    }

    /**
     * Get the event message error character
     * @return string
     */
    protected function getEventMsgErrorChar()
    {
        return '!__error__!';
    }

    /**
     * Get the event message line break character
     * @return string
     */
    public function getEventMsgLineBreak()
    {
        return '!__break__!';
    }

    /**
     * Get the event log data update identifier
     * @return string
     */
    public function getEventLogUpdateIdentifier()
    {
        $updated_at = $this->getDate();
        return "!-----[{$updated_at}]-----!";
    }

    /**
     * Get event message view more button
     * 
     * @param bool $is_serialized_string Specifies whether the view more button is 
     *                                   for a serialized string or not
     * 
     * @return string
     */
    public function getEventMsgViewMoreBtnIdentifier($is_serialized_string = false)
    {
        if ($is_serialized_string) {
            $btn_str = '[alm_show_more_btn_serialized]';
        } else {
            $btn_str = '[alm_show_more_btn]';
        }
        return $btn_str;
    }

    /**
     * Trim characters from right side of a string
     * @param  string $str  Specifies the string to trim the character from
     * @param  string $char Specifies the character to trim from right side of the string
     * @return string       The trimmed string
     */
    public function rtrim($str, $char = '')
    {
        if (empty($char)) return $str;

        $char = '/' . preg_quote($char, '/') . '$/';
        return preg_replace($char, '', $str);
    }

    /**
     * Trim characters from left side of a string
     * @param  string $str  Specifies the string to trim the character from
     * @param  string $char Specifies the character to trim from the left side of the string
     * @return string       The trimmed string
     */
    public function ltrim($str, $char = '')
    {
        if (empty($char)) return $str;

        $char = '/^' . preg_quote($char, '/') . '/';
        return preg_replace($char, '', $str);
    }
}