<?php
// Silence is golden


$strs = ['run', 'zenith', 'john', 'jeff', '_', '*'];
function strEndsWith( $str, $pattern )
{
    if ( is_array( $pattern ) )
    {
        foreach ( $pattern as $p )
        {
            if ( ! is_array( $p ) )
            {
                $match = '/'. preg_quote( $p ) .'$/';
                echo $match . PHP_EOL;
                if ( preg_match( $match, $str ) ) 
                    return true;
            }
        }
    }
    return false;
}

var_dump( strEndsWith( 'feeling this **', $strs ) );

exit;

$total = 100;
$limit = 5;

$count = 0;
do {
    if ($count > $total) goto end;
    echo $count . PHP_EOL;
    $count++;

    if ($count % 5 === 0) $limit += 5;
} while ($count <= $limit);


end: exit(var_dump('finished'));