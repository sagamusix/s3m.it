<?php
/*
 * util.php
 * ============
 * Purpose: Various utilities for other functions
 *
 * Functions:
 * ordinalize($number)
 *     Returns the ordinalized (rank) version of a number, e.g. 1 => 1st 
 *
 */

function ordinalize($number)
{
    if(in_array(($number % 100), range(11, 13)))
    {
        return $number . 'th';
    } else if($number == '')
    {
        return 'n/a';
    } else
    {
        switch(($number % 10))
        {
            case 1:
                return $number . 'st';
                break;
            case 2:
                return $number . 'nd';
                break;
            case 3:
                return $number . 'rd';
            default:
                return $number . 'th';
                break;
        }
    }
}