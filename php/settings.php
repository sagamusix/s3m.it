<?php

/*
 * settings.php
 * ============
 * Purpose: Compo manager setup.
 *
 * Functions:
 * db_connect()
 *     Connect to the defined database.
 * db_disconnect()
 *     Disconnect from the database
 * redirect($target)
 *     Redirect to another page.    
 *
 */

// Change those.
define('MYSQL_HOST',          'localhost');
define('MYSQL_USER',          'FILL ME IN');
define('MYSQL_PASS',          'FILL ME IN');
define('MYSQL_DATABASE',      'openmpt-compo');
// Max upload file size
define('MAX_UPLOAD_SIZE',     min(1024 * 1024 * intval(ini_get('upload_max_filesize')), 1024 * 1024 * 16));
define('UPLOAD_DIR',          dirname(__DIR__) . '/uploads/'); // MUST be absolute for 7z to work

define('MAX_FILENAME_LENGTH',  64);
define('MAX_USERNAME_LENGTH',  64);
define('MAX_AUTHOR_LENGTH',    64);
define('MAX_COMPONAME_LENGTH', 64);

// Server constants.
define('BASEDIR', rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/');
define('SERVER', 'http://' . $_SERVER['HTTP_HOST']);
define('BASEDIR_ABSOLUTE', SERVER . BASEDIR);

// Has to be defined or else the scripts will abort.
define('COMPOMANAGER',      1);

// Access levels
define('ACCESS_GUEST',      0);
define('ACCESS_MINADMIN',   5);
define('ACCESS_HOST',       5);     // Normal host. Can host compos.
define('ACCESS_USERDB',     10);    // Advanced host. Can host compos and also add new normal hosts. Can edit normal hosts, can't delete other hosts.
define('ACCESS_FULLADMIN',  15);    // Admin. Can do anything.

// Required for 7-zip and the escapeshellarg() function to work
putenv('LC_ALL=en_US.UTF-8');
setlocale(LC_ALL, 'en_US.UTF-8');

session_cache_expire(9000);
session_start() or die("Can't initialize session, aborting...");

function db_connect()
{
    global $db_link;
    if($db_link)
    {
        db_disconnect();
    }
    $db_link = @mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS) or die("Can't connect to the database!");
    mysql_select_db(MYSQL_DATABASE) or die("Can't connect to the database!");
}

function db_disconnect()
{
    global $db_link;
    mysql_close($db_link);
}


function redirect($target)
{
    header("Location: $target");
    db_disconnect();
    die();
}


?>
