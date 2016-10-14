<?php

/*
 * 7z.php
 * ======
 * Purpose: Functions for handling 7z archives
 */

if(!defined("COMPOMANAGER"))
{
    die("hacking attempt");
}

class ArchiveFile
{
    protected $fileName;
    protected $lockHandle;
    protected $lockName;
    
    protected function Exec($arg)
    {
        exec('7z ' . $arg);
    }
    
    public function FileName($name)
    {
        return $name . '.7z';
    }
    
    public function ArchiveFile($name)
    {
        $this->fileName = self::FileName($name);
        $this->lockName = $this->fileName . ".lock";
    }
    
    public function Open()
    {
        $this->lockHandle = fopen($this->lockName, "w+");
        flock($this->lockHandle, LOCK_EX);
        return TRUE;
    }
    
    public function Close()
    {
        fclose($this->lockHandle);
        @unlink($this->lockName);
        @chmod($this->fileName, 0755);
    }
    
    public function Add($file)
    {
        self::Exec('a -t7z ' . escapeshellarg($this->fileName) . ' ' . escapeshellarg($file));
    }
    
    public function PrepareReplace($file)
    {
        // Not necessary
        //self::Exec('d ' . escapeshellarg($this->fileName) . ' ' . escapeshellarg($file));
    }
    
    public function Delete($file)
    {
        self::Exec('d ' . escapeshellarg($this->fileName) . ' ' . escapeshellarg($file));
    }
    
    public function Extract($file)
    {
        $tempDir = sys_get_temp_dir() . '/';
        self::Exec('e ' . escapeshellarg($this->fileName) . ' -o' . escapeshellarg($tempDir) . ' ' . escapeshellarg($file));
        $tempFile = $tempDir . $file;
        $stat = stat($tempFile);
    
        ob_end_clean();
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header("Content-type: application/octet-stream");
        header("Content-Length: " . $stat["size"]);
        header('Last-Modified: ' . date('r', $stat["mtime"]));
        readfile($tempFile);
        unlink($tempFile);
    }

    public function Recompress()
    {
        $tempDir = sys_get_temp_dir() . '/compo' . time() . '/';

        $stat = stat($this->fileName);
        if($stat === FALSE) return;
        
        self::Exec('e ' . escapeshellarg($this->fileName) . ' -o' . escapeshellarg($tempDir) . ' -y');
        unlink($this->fileName);
        self::Exec('a -t7z ' . escapeshellarg($this->fileName) . ' ' . escapeshellarg($tempDir . '*') . ' -r');
        touch($this->fileName, $stat["mtime"]);
        
        $objects = scandir($tempDir);
        foreach ($objects as $object)
        {
            if ($object != "." && $object != "..")
            {
                unlink($tempDir . $object);
            }
        }
        rmdir($tempDir);
    }
}