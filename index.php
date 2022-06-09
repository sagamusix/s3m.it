<?php
header("Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-eval' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self'; form-action 'self'; connect-src 'self'");

require_once('php/settings.php');

if(strpos($_SERVER['REQUEST_URI'], basename(__FILE__)) !== false)
{
    redirect(BASEDIR);
}

db_connect();

// Set up right management
if(isset($_SESSION['idhost']))
{
    $result = $mysqli->query("SELECT `access_level` FROM `hosts` WHERE `idhost` = " . intval($_SESSION['idhost'])) or die('query failed');
    if($result->num_rows == 0)
    {
        define('ACCESS', ACCESS_GUEST);
    } else
    {
        $row = $result->fetch_assoc();
        define('ACCESS', $row['access_level']);
    }
    $result->free();
} else
{
    define('ACCESS', ACCESS_GUEST);
}

ob_start();
echo file_get_contents('php/head.htm');

if(isset($_GET['admin']))
{
    require('php/admin.php');
} else if(isset($_GET['fileupload']))
{
    require('php/file.php');
} else if(isset($_GET['login']))
{
    require('php/login.php');
} else if(isset($_GET['getpack']))
{
    require('php/file.php');
} else if(isset($_GET['getfile']))
{
    require('php/file.php');
} else if(isset($_GET['getresults']))
{
    require('php/file.php');
} else if(isset($_GET['getcompo']))
{
    require('php/file.php');
} else if(isset($_GET['uploadping']))
{
    require('php/file.php');
} else if(isset($_GET['stats']))
{
    require('php/stats.php');
} else if(isset($_GET['search']))
{
    require('php/search.php');
} else if(isset($_GET['compos']))
{
    require('php/all-compos.php');
} else
{
    require('php/upload.php');
}

echo file_get_contents('php/foot.htm');

$text = ob_get_clean();

header('Content-type: text/html; charset=utf-8');

@ob_start('ob_gzhandler');

require('php/navigation.php');
$text = str_replace('{{MANAGE}}', getNavigationBox(), $text);

$text = str_replace('{{BASE}}', htmlspecialchars(BASEDIR), $text);
$text = str_replace('{{BASE_ABS}}', htmlspecialchars(BASEDIR_ABSOLUTE), $text);
$text = str_replace('{{SERVER}}', htmlspecialchars(SERVER), $text);
echo $text;

db_disconnect();
