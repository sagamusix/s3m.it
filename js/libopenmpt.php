<?php
// Serve libopenmpt through gzip-compressed stream if possible.
// Done with PHP to avoid server-specific setup.

$supportsGzip = strpos(@$_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;

$file = 'libopenmpt.js';
if ($supportsGzip)
    $file .= '.gz';

$fdate = filemtime($file); 
if(strtotime(@$_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $fdate)
{
    header("HTTP/1.1 304 Not Modified");
    die();
}

header('Content-type: text/javascript');
if ($supportsGzip) header('Content-Encoding: gzip');
header('Last-Modified: ' . date('r', $fdate));
header("Cache-Control: must-revalidate");
header('Expires: ' . date('r', time() + 60 * 60 * 24));
ob_start();
readfile($file);
header('Content-Length: ' . ob_get_length());
ob_end_flush();