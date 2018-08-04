<?php

if(!function_exists("str_ends"))
{
    function str_ends($haystack, $needle)
    {
        $length = strlen($needle);
        return $length === 0 || (substr($haystack, -$length) === $needle);
    }
}
