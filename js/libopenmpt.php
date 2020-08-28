<?php
// Serve libopenmpt through gzip-compressed stream if possible.
// Done with PHP to avoid server-specific setup.

$supportsGzip = false;
$supportsBrotli = false;
$encodings = explode(',', @$_SERVER['HTTP_ACCEPT_ENCODING']);
foreach($encodings as $encoding)
{
    $encoding = trim($encoding);
    if($encoding == 'br')
        $supportsBrotli = true;
    else if($encoding == 'gzip')
        $supportsGzip = true;
}

$file = 'libopenmpt.js';
if($supportsBrotli)
    $file .= '.br';
else if($supportsGzip)
    $file .= '.gz';

$stat = stat($file);
$fdate = $stat['mtime'];
$fsize = $stat['size']; 
if(strtotime(@$_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $fdate)
{
    header("HTTP/1.1 304 Not Modified");
    die();
}

header('Content-type: text/javascript');
if($supportsBrotli)
    header('Content-Encoding: br');
else if($supportsGzip)
    header('Content-Encoding: gzip');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $fdate));
header("Cache-Control: must-revalidate");
header('Expires: ' . date('r', time() + 60 * 60 * 24));
header('Content-Length: ' . $fsize);
readfile($file);
