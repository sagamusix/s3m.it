<?php

/*
 * zip.php
 * =======
 * Purpose: Functions for handling zip archives.
 *          This is the original implementation, now superseded by 7z.php.
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

class ArchiveFile
{
    protected $zip;
    protected $fileName;
    protected $lockHandle;
    protected $lockName;
    
    public function FileName($name)
    {
        return $name . '.zip';
    }
    
    public function ArchiveFile($name)
    {
        $this->fileName = self::FileName($name);
        $this->lockName = $this->fileName . ".lock";
    }
    
    public function OpenRead()
    {
        return $this->OpenInternal(FALSE);
    }
    
    public function OpenWrite()
    {
        return $this->OpenInternal(TRUE);
    }

    private function OpenInternal($writeAccess)
    {
        $this->lockHandle = fopen($this->lockName, "w+");
        if(!flock($this->lockHandle, $writeAccess ? LOCK_EX : LOCK_SH))
        {
            fclose($this->lockHandle);
            @unlink($this->lockName);
            return FALSE;
        }
        
        $this->zip = new ZipArchive;
        if($this->zip->open($this->fileName, ZIPARCHIVE::CREATE) !== TRUE)  // TODO: use ZIPARCHIVE::RDONLY on PHP 7.4.3 and above for !$writeAccess
        {
            fclose($this->lockHandle);
            @unlink($this->lockName);
            return FALSE;
        }
        return TRUE;
    }
    
    public function Close()
    {
        $this->zip->close();
        fclose($this->lockHandle);
        @unlink($this->lockName);
        @chmod($this->fileName, 0755);
    }
    
    public function Add($file)
    {
        $this->zip->addFile($file, basename($file));
    }
    
    public function PrepareReplace($file)
    {
        $this->Remove($file);
    }
    
    public function Delete($file)
    {
        $this->zip->deleteName($file);
    }
    
    public function Extract($file)
    {
        $stat = $this->zip->statName($file);

        ob_end_clean();
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header("Content-type: application/octet-stream");
        header("Content-Length: " . $stat["size"]);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $stat["mtime"]));
        echo $this->zip->getFromName($file);
    }
    
    public function Recompress()
    {
        // Not necessary
    }
}