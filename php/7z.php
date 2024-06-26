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
    
    protected static function Exec($arg, $toStdOut = FALSE, &$output = NULL)
    {
        if(!$toStdOut)
            exec('7z ' . $arg, $output);
        else
            passthru('7z ' . $arg);
    }
    
    public static function FileName($name)
    {
        return $name . '.7z';
    }
    
    public function __construct($name)
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
        return TRUE;
    }
    
    public function Close()
    {
        if($this->lockHandle) fclose($this->lockHandle);
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
        $details = array();
        self::Exec('l -slt ' . escapeshellarg($this->fileName) . ' ' . escapeshellarg($file), FALSE, $details);
        $size = -1;
        $modified = -1;
        foreach ($details as $detail)
        {
            $kv = explode(' = ', $detail);
            if($kv[0] == 'Size')
                $size = $kv[1];
            elseif($kv[0] == 'Modified')
                $modified = strtotime($kv[1]);
        }

        ob_end_clean();
        header('Content-Disposition: attachment;filename="' . addslashes(utf8_decode($file)) . '";filename*=utf-8\'\'' . rawurlencode($file));
        header("Content-type: application/octet-stream");
        if($size != -1)
            header("Content-Length: " . $size);
        if($modified != -1)
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $modified));

        self::Exec('e ' . escapeshellarg($this->fileName) . ' -so ' . escapeshellarg($file), TRUE);
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