<?php

/*
 * navigation.php
 * ==============
 * Purpose: Management navigation (stupid name)
 *
 * Functions:
 * getNavigationBox()
 *     Displays the main navigation box. 
 *
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

function getNavigationBox()
{
    global $mysqli;
    if(!isset($_SESSION["idhost"]))
    {
        return '
              <li><a href="{{BASE}}login" style="background-image:url({{BASE}}img/key.png)">Login</a></li>
              <li><a href="{{BASE}}search" style="background-image:url({{BASE}}img/magnifier.png)">Search</a></li>
              <li><a href="{{BASE}}stats" style="background-image:url({{BASE}}img/chart_bar.png)">Statistics</a></li>
        ';
    } else
    {
        $result = $mysqli->query("SELECT `access_level` FROM `hosts` WHERE `idhost` = " . intval($_SESSION["idhost"])) or die('query failed');
        if($result->num_rows == 0)
        {
            die("hacking attempt");
        }
        
        $row = $result->fetch_assoc();
        $result->free();
        $access = $row["access_level"];
        
        $retval = "";
        
        $retval .= '<li><a href="{{BASE}}" style="background-image:url({{BASE}}img/house.png)">Overview</a></li>';

        if($access >= ACCESS_HOST)
        {
            $retval .= '<li><a href="{{BASE}}admin/compo" style="background-image:url({{BASE}}img/page.png)">My Compos</a></li>';
        }

        $retval .= '<li><a href="{{BASE}}admin/mydetails" style="background-image:url({{BASE}}img/user.png)">My Details</a></li>';

        if($access >= ACCESS_USERDB)
        {
            $retval .= '<li><a href="{{BASE}}admin/users" style="background-image:url({{BASE}}img/vcard.png)">Manage Users</a></li>';
        }

        $retval .= '
            <li><a href="{{BASE}}search" style="background-image:url({{BASE}}img/magnifier.png)">Search</a></li>
            <li><a href="{{BASE}}stats" style="background-image:url({{BASE}}img/chart_bar.png)">Statistics</a></li>
            <li><a href="{{BASE}}logout" style="background-image:url({{BASE}}img/key.png)">Logout</a></li>';
        
        return $retval;
    }

}