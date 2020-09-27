<?php
namespace ALM\Controllers\Base\Traits;

// Prevent direct file access
defined( 'ALM_PLUGIN_FILE' ) || exit( 'You are not allowed to do this on your own.' );

/**
 * Internet Address (IP) Factory Template for the Plugin Factory Controller
 * @see \ALM\Controllers\Base\PluginFactory
 * @since 1.0.0
 */

/**
 * Part of this code (from line #L333) was cloned from the linked Github repository 
 * has shown below and has been modified by the ViewPact Team.
 */

/**
 * This module determine if an IP is located in a specific range as specified via 
 * several alternative formats.
 * 
 * @copyright 2008: 10 January 2008 - Paul Gregg <pgregg@pgregg.com>
 * @version: 1.2
 * 
 * @link https://github.com/cloudflarearchive/Cloudflare-Tools/blob/master/cloudflare/ip_in_range.php
 *
 * Network ranges can be specified as:
 *      1. Wildcard format:     1.2.3.*
 *      2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
 *      3. Start-End IP format: 1.2.3.0-1.2.3.255
 *
 * Source website: http://www.pgregg.com/projects/php/ip_in_range/
 * Version 1.2
 *
 * This software is Donationware - if you feel you have benefited from
 * the use of this tool then please consider a donation. The value of
 * which is entirely left up to your discretion.
 * http://www.pgregg.com/donate/
 *
 * Please do not remove this header, or source attribution from this file.
 * 
 * 
 * Modified by James Greene <james@cloudflare.com> to include IPV6 support
 * (original version only supported IPV4).
 * 21 May 2012 
 */

trait IP_Factory
{
    /**
     * Client IP Address
     * @var string
     * @since 1.0.0
     */
    protected $client_ip = '';

    /**
     * Client IPV4 Address
     * @var string
     * @since 1.0.0
     */
    protected $client_ipv4 = '';

    /**
     * Client IPV6 Address
     * @var string
     * @since 1.0.0
     */
    protected $client_ipv6 = '';

    /**
     * Sets the client top level IP address from list of client IP addresses
     * @var string
     * @since 1.0.0
     */
    protected $top_level_ip = '';

    /**
     * Get the top client IP address from the client IP list
     * 
     * @author ViewPact Team
     * 
     * @see IP_Factory::getClientIpList()
     * 
     * Note: this method will retrieve only the first client IP address
     * from the client iP address list.
     * 
     * @return string|WP_Error Returns the client ip address on success.
     *                         Otherwise a WP_Error object is returned on failure.
     */
    public function getTopLevelIp()
    {
        if ( ! $this->isIpProxyFixEnabled() ) {
            $ip = $this->getRemoteAddr();
        }
        else {
            // Call the IP address list generator to sets the top level ip
            $this->getClientIpList();
            $ip = $this->top_level_ip;
        }

        if ( ! empty( $ip ) ) 
            return $ip;

        $error = new \WP_Error;
        $error->add(
            'invalid_ip',
            'Error: IP Address is invalid.'
        );

        return $error;
    }

    /**
     * Get the client IP addresses
     * 
     * @author ViewPact Team
     * 
     * @return array List of client IP addresses
     */
    public function getClientIpList()
    {
        $ip_list = [];
        $ip_keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ];

        foreach ( $ip_keys as $key )
        {
            if ( $this->isServerVarSet( $key ) )
            {
                foreach ( explode( ',', $this->getServerVar( $key ) ) as $ip )
                {
                    // Clean the ip if needed
                    $ip = $this->maybeCleanIp( $ip );

                    // Validate the IP address
                    if ( $this->isIpValid( $ip ) )
                    {
                        if ( ! isset( $ip_list[ $key ] ) ) {
                            $ip_list[ $key ] = "{$ip}";
                        } else {
                            $ip_list[ $key ] .= "|{$ip}";
                        }

                        if ( empty( $this->top_level_ip ) ) {
                            $this->top_level_ip = $ip;
                        }
                    }
                }
            }
        }
        
        return $ip_list;
    }

    /**
     * Get the client remote address
     * 
     * @author ViewPact Team
     * 
     * @return string Returns the client remote address on success. Otherwise an empty string.
     */
    public function getRemoteAddr()
    {
        if ( ! $this->isServerVarSet( 'REMOTE_ADDR' )  ) return '';
        
        $ip = sanitize_text_field( wp_unslash( $this->getServerVar('REMOTE_ADDR') ) );
        $ip = $this->maybeCleanIp( $ip );

        return ( $this->isIpValid( $ip ) ) ? $ip : '';
    }

    /**
     * Check whether a given IP format is IPv4
     * 
     * @author ViewPact Team
     * 
     * @param  string  $ip  Specifies the IP address format to check
     * @return bool         True if the IP address format is IPv4. Otherwise false.
     */
    public function maybeIpv4Format( $ip )
    {
        if ( empty( $ip ) 
        || false !== strpos( $ip, '[' ) 
        || count( explode( ':', $ip ) ) > 1 )
        {
            return false;
        }

        $ip_paths = explode( '.', explode( ':', $ip )[0] );
        return ( 4 === count( $ip_paths ) );
    }

    /**
     * Remove port numbers from IP address (both IPv4 and IPv6)
     * 
     * @author ViewPact Team
     * 
     * @param  string $ip Specifies the IP address to strip out port number from
     * @return string     The IP address without port number
     */
    public function removePortNumberFromIp( $ip )
    {
        $regex = $this->maybeIpv4Format( $ip ) ? '/\:[0-9]+$/' : '/^(\[)|(\].*)$/';

        return preg_replace( $regex, '', $ip );
    }

    /**
     * Sanitize ip address
     * 
     * @author ViewPact Team
     * 
     * @param  string $ip Specifies the IP address to sanitize
     * @return string     The sanitized IP address
     */
    public function sanitizeIp( $ip )
    {
        return sanitize_text_field( wp_unslash( $ip ) );
    }

    /**
     * Clean the IP address if needed
     * 
     * @author ViewPact Team
     * 
     * @param  string $ip Specifies the ip address to clean
     * @return string     The cleaned ip address.
     */
    public function maybeCleanIp( $ip )
    {
        $ip = $this->sanitizeIp( $ip );
        $ip = $this->removePortNumberFromIp( $ip );

        return $ip;
    }

    /**
     * Validate client IP address (IPv4 and IPv6)
     * 
     * @author ViewPact Team
     * 
     * @param  string $ip Specifies the Ip address to validate
     * @return bool       True if the IP address is valid. Otherwise false.
     */
    public function isIpValid( $ip )
    {
        $ip         = trim( $ip );
        $ip_options = FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;

        /**
         * Bail out on localhost server
         */
        if ( $this->isLocalhost() )
            return true;

        /**
         * Check whether the internal IP address can be filtered, then enable the 
         * private and reserve range option flags.
         */
		if ( $this->isInternalIpFilterable() ) {
			$ip_options = $ip_options | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		}

        $validate_ip = (bool) filter_var( $ip, FILTER_VALIDATE_IP, $ip_options );

        // Return the ip at this point if valid
        if ( $validate_ip ) 
            return true;

        /**
         * Validate the IP using regex
         */
        $regex_ipv4 = '/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/';
        
        if ( preg_match( $regex_ipv4, $ip ) ) 
            return $ip;

        $regex_ipv6 = '/^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$|^::(?:[0-9a-fA-F]{1,4}:){0,6}[0-9a-fA-F]{1,4}$|^[0-9a-fA-F]{1,4}::(?:[0-9a-fA-F]{1,4}:){0,5}[0-9a-fA-F]{1,4}$|^[0-9a-fA-F]{1,4}:[0-9a-fA-F]{1,4}::(?:[0-9a-fA-F]{1,4}:){0,4}[0-9a-fA-F]{1,4}$|^(?:[0-9a-fA-F]{1,4}:){0,2}[0-9a-fA-F]{1,4}::(?:[0-9a-fA-F]{1,4}:){0,3}[0-9a-fA-F]{1,4}$|^(?:[0-9a-fA-F]{1,4}:){0,3}[0-9a-fA-F]{1,4}::(?:[0-9a-fA-F]{1,4}:){0,2}[0-9a-fA-F]{1,4}$|^(?:[0-9a-fA-F]{1,4}:){0,4}[0-9a-fA-F]{1,4}::(?:[0-9a-fA-F]{1,4}:)?[0-9a-fA-F]{1,4}$|^(?:[0-9a-fA-F]{1,4}:){0,5}[0-9a-fA-F]{1,4}::[0-9a-fA-F]{1,4}$|^(?:[0-9a-fA-F]{1,4}:){0,6}[0-9a-fA-F]{1,4}::$/';

        if ( preg_match( $regex_ipv6, $ip ) ) 
            return $ip;

        return false;
    }

    /**
     * Get the lower and upper IPv4 ranges.
     * Example format: 127.0.0.1-89 = 127.0.0.1 - 127.0.0.89
     * 
     * @author ViewPact Team
     * 
     * @param  string $range        Specifies the IP range
     * 
     * @param  bool   $return_str   Specifies whether to return the IP address in object or 
     *                              string format.
     * 
     * @return object|string        Returns a stdClass object containing the IP lower and 
     *                              upper ranges. If the return type is string, then it returns 
     *                              the IP ranges in string.
     */
    public function getIpv4Ranges( $range, $return_str = false )
    {
        if ( false === strpos( $range, '-' ) ) 
            $range .= '-';

        // Remove invalid IP characters
        $range = preg_replace( '/[^0-9\.\-]/', '', $range );

        // Removes trailing IP range specifiers
        $range = preg_replace( '/(^[\-]+)|([\-]+$)/', '', $range );

        // Properly parse the IP range specifier character
        $range = preg_replace( '/[\-]+/', '-', $range );
        
        $all_ip_paths = explode( '-', $range );
        
        // If more than one range specifier exists, just reduce it to two
        $ip_paths = [
            ( isset( $all_ip_paths[0] ) ? $all_ip_paths[0] : '' ),
            ( isset( $all_ip_paths[1] ) ? $all_ip_paths[1] : '' ),
        ];

        $ip_ranges = [
            'lower' => '',
            'upper' => '',
        ];

        $range_limit        = isset( $ip_paths[1] ) ? $ip_paths[1] : '';
        $ip_ranges['lower'] = $ip_paths[0];

        if ( ! empty( $ip_ranges['lower'] ) && ! empty( $range_limit ) )
        {
            $split_lower_range = explode( '.', $ip_ranges['lower'] ); 
            
            $split_lower_range[ count( $split_lower_range ) - 1 ] = $range_limit;
            $ip_ranges['upper'] = implode( '.', $split_lower_range );
        }
        else {
            $ip_ranges['upper'] = $ip_ranges['lower'];
        }

        return ( $return_str ) ? implode( '-', $ip_ranges ) : ( (object) $ip_ranges );
    }



    /*********************************************************************************
     * 
     * This function takes 2 arguments, an IP address and a "range" in several 
     * different formats as specified above in the file header.
     * 
     * @param string $ip    Specifies the ip address to check
     * @param string $range Specifies the network range
     * 
     * @return bool True if the supplied IP is within the range. Otherwise false.
     * 
     * Note little validation is done on the range inputs - it expects you to
     * use one of the above 3 formats.
     */
    public function ipv4InRange( $ip, $range )
    {
        if ( !is_scalar( $ip ) || !is_scalar( $range ) ) return false;

        if (strpos($range, '/') !== false)
        {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);

            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                return ( (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec) );
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                while(count($x)<4) $x[] = '0';
                list($a,$b,$c,$d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a)?'0':$a, empty($b)?'0':$b,empty($c)?'0':$c,empty($d)?'0':$d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);
                
                # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));
                
                # Strategy 2 - Use math to create it
                $wildcard_dec = pow(2, (32-$netmask)) - 1;
                $netmask_dec = ~ $wildcard_dec;
                
                return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
            }
        } else {
            // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
            if (strpos($range, '*') !==false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }
            
            if (strpos($range, '-')!==false) { // A-B format
                list($lower, $upper) = explode('-', $range, 2);
                $lower_dec = (float)sprintf("%u",ip2long($lower));
                $upper_dec = (float)sprintf("%u",ip2long($upper));
                $ip_dec = (float)sprintf("%u",ip2long($ip));
                return ( ($ip_dec>=$lower_dec) && ($ip_dec<=$upper_dec) );
            }
            return false;
        } 
    }

    /**
     * Get the ipv6 full format and return it as a decimal value.
     * @param  string $ip Specifies the ip address to get
     * @return string     The full ipv6 address
     */
    public function getIpv6Full( $ip )
    {
        $pieces = explode ("/", $ip, 2);
        $left_piece = $pieces[0];
        $right_piece = $pieces[1];

        // Extract out the main IP pieces
        $ip_pieces = explode("::", $left_piece, 2);
        $main_ip_piece = $ip_pieces[0];
        $last_ip_piece = $ip_pieces[1];

        // Pad out the shorthand entries.
        $main_ip_pieces = explode(":", $main_ip_piece);
        foreach($main_ip_pieces as $key=>$val) {
            $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, "0", STR_PAD_LEFT);
        }

        // Check to see if the last IP block (part after ::) is set
        $last_piece = "";
        $size = count($main_ip_pieces);
        if (trim($last_ip_piece) != "") {
            $last_piece = str_pad($last_ip_piece, 4, "0", STR_PAD_LEFT);
        
            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $main_ip_pieces[$i] = "0000";
            }
            $main_ip_pieces[7] = $last_piece;
        }
        else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $main_ip_pieces[$i] = "0000";
            }        
        }
        
        // Rebuild the final long form IPV6 address
        $final_ip = implode(":", $main_ip_pieces);

        return $this->ip2Long6($final_ip);
    }

    /**
     * Basically, this is used to rebuild the ipv6 address long form
     * @param  string $ip Specifies the ipv6 to rebuild into long form
     * @return string     The converted ipv6 address long form
     */
    public function ip2Long6( $ip )
    {
        if (substr_count($ip, '::')) {
            $ip = str_replace('::', str_repeat(':0000', 8 - substr_count($ip, ':')) . ':', $ip);
        }

        $ip = explode(':', $ip);
        $r_ip = '';

        foreach ($ip as $v) {
            $r_ip .= str_pad(base_convert($v, 16, 2), 16, 0, STR_PAD_LEFT);
        }
        return base_convert($r_ip, 2, 10);
    }

    /**
     * Determine whether the IPV6 address is within range.
     * $ip is the IPV6 address in decimal format to check if its within the IP range 
     * created by the cloudflare IPV6 address, $range_ip. 
     * 
     * @param string $ip        Specifies the ipv6 address to check for.
     *                          The ip will be converted to full IPV6 format.
     * 
     * @param string $range_ip  Specifies the ipv6 range.
     *                          The range ip will be converted to full IPV6 format.
     * 
     * @return bool True if the IPV6 address, $ip, is within the range from $range_ip. 
     *              False otherwise.
     */
    public function ipv6InRange( $ip, $range_ip )
    {
        $pieces = explode ("/", $range_ip, 2);
        $left_piece = $pieces[0];
        $right_piece = $pieces[1];

        // Extract out the main IP pieces
        $ip_pieces = explode("::", $left_piece, 2);
        $main_ip_piece = $ip_pieces[0];
        $last_ip_piece = $ip_pieces[1];

        // Pad out the shorthand entries.
        $main_ip_pieces = explode(":", $main_ip_piece);
        foreach($main_ip_pieces as $key=>$val) {
            $main_ip_pieces[$key] = str_pad($main_ip_pieces[$key], 4, "0", STR_PAD_LEFT);
        }

        // Create the first and last pieces that will denote the IPV6 range.
        $first = $main_ip_pieces;
        $last = $main_ip_pieces;

        // Check to see if the last IP block (part after ::) is set
        $last_piece = "";
        $size = count($main_ip_pieces);
        if (trim($last_ip_piece) != "") {
            $last_piece = str_pad($last_ip_piece, 4, "0", STR_PAD_LEFT);
        
            // Build the full form of the IPV6 address considering the last IP block set
            for ($i = $size; $i < 7; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }
            $main_ip_pieces[7] = $last_piece;
        }
        else {
            // Build the full form of the IPV6 address
            for ($i = $size; $i < 8; $i++) {
                $first[$i] = "0000";
                $last[$i] = "ffff";
            }        
        }

        // Rebuild the final long form IPV6 address
        $first = $this->ip2Long6(implode(":", $first));
        $last = $this->ip2Long6(implode(":", $last));
        $in_range = ($ip >= $first && $ip <= $last);

        return $in_range;
    }
}