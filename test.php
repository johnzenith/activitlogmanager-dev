<?php
add_action('update_user_meta', 'test_meta_func', 10, 4);

function test_meta_func(...$args)
{
    var_dump($args);
}


function strStartsWith( $str, $pattern)
{
        // if ( ! is_scalar( $str ) ) return '';

        // $str        = (string) $str;
        // $start_with = substr( $str, 0, strlen( $pattern ) );
        // return $pattern === $start_with;
}

function strEndsWith( $str, $pattern)
{
    // if ( ! is_scalar( $str ) ) return '';

        // $str      = (string) $str;
        // $end_with = substr( $str, strlen( $str ) - strlen( $pattern ) );
        // return $pattern === $end_with;
}