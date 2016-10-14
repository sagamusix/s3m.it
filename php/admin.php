<?php

/*
 * admin.php
 * =========
 * Purpose: Admin (host) functionality stub. All administration functionality is routed through this file.
 *
 * Functions:
 * none
 *
 */

if(!defined("COMPOMANAGER") || !defined("ACCESS"))
{
    die("hacking attempt");
}

if(ACCESS < ACCESS_MINADMIN)
{
    redirect(BASEDIR);
}

switch($_GET["action"])
{
    // Compo actions
    case "compo":
    case "listcompos":
    case "addcompo":
    case "editcompo":
    case "delcompo":
    case "checkping":
    case "ajaxcompo":
        require("php/compo.php");
        break;
    // File actions
    case "fetchfile":
    case "delfile":
    case "fetchpack":
    case "delpack":
        require("php/file.php");
        break;
    // User management actions
    case "users":
    case "user":
    case "mydetails":
    case "adduser":
    case "edituser":
    case "deluser":
        require("php/users.php");
        break;
    case "voting":
        require("php/lazyvote.php");
        break;
}