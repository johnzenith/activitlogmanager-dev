<?php
// Silence

$a = ['admin' => 1, 'editor' => 1, 'newu' => 0];
$b = ['subscriber' => 1, 'editor' => 1];
print_r( array_diff_key( [], $a ) );
// print_r( array_intersect( array_keys($a), array_keys($b) ) );