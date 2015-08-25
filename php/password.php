<?php

/*
 * password.php
 * ============
 * Purpose: Password encryption functions.
 *
 * Functions:
 * makepassword($plain, $hashed = NULL)
 *     Create a password from plain text ($plain) either using a random hash if $hashed is NULL
 *     or the hash of another password otherwise.  
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

function makepassword($plain, $hashed = NULL)
{
    $salt_len = 100;
    if (empty($hashed))
        for($salt = "", $x = 0; $x++ < $salt_len; $salt .= bin2hex(chr(mt_rand(0, 255))));   // make a new salt
    else
        $salt = substr($hashed, 0, $salt_len * 2);  //  extract existing salt

    return $salt . hash('whirlpool', $salt . $plain);
}

?>