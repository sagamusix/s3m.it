<?php

// modinfo.php: extract titles from stuff
// based on lazyslash TrackerMod.h

function title_it($f) {
	if(fread($f,4) != "IMPM") return;
	$data = fread($f,26);
	$len = strpos($data,"\x00");
	if($len === FALSE) $len = 26;
	return substr($data, 0, $len);
}

function title_mtm($f) {
	if(fread($f,4) != "MTM\x10") return;
	$data = fread($f,26);
	$len = strpos($data,"\x00");
	if($len === FALSE) $len = 20;
	return substr($data, 0, $len);
}

function title_xm($f) {
	if(fread($f,17) != "Extended Module: ") return;
	$data = fread($f,20);
	return $data; // space-padded
}

function title_s3m($f) {
	$data = fread($f,28);
	$len = strpos($data,"\x00");
	if($len === FALSE) $len = 28;
	
	if(fread($f,2) != "\x1A\x10") return;

	return substr($data, 0, $len);
}

function title_mod($f) {
	$data = fread($f,20);
	$len = strpos($data,"\x00");
	if($len === FALSE) $len = 20;
	return substr($data, 0, $len);
}

function getModTitle($filename, $realname)
{
	$f = fopen($filename, 'rb');
	$ext = strtolower(strrchr($realname, '.'));
	$title = "";
	if($ext == ".it")	$title = title_it($f);
	if($ext == ".mtm")	$title = title_mtm($f);
	if($ext == ".xm")	$title = title_xm($f);
	if($ext == ".s3m")	$title = title_s3m($f);
	if($ext == ".mod")	$title = title_mod($f);
	$title = trim($title);
	if($title == '')
	{
        $title = $realname;
    }
	fclose($f);
	return $title;
}