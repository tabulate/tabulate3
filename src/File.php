<?php

namespace Tabulate;

class File
{

    public static function rmdir($dir)
    {
        if (!is_dir($dir)) {
            throw new \Exception("'$dir' is not a directory and will not be deleted");
        }
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    public static function maxUploadSize()
    {
        $u_bytes = self::convertHrToBytes(ini_get('upload_max_filesize'));
        $p_bytes = self::convertHrToBytes(ini_get('post_max_size'));
        return min($u_bytes, $p_bytes);
    }

    public static function convertHrToBytes($size)
    {
        $size = strtolower($size);
        $bytes = (int) $size;
        if (strpos($size, 'k') !== false)
            $bytes = intval($size) * 1024;
        elseif (strpos($size, 'm') !== false)
            $bytes = intval($size) * 1024 * 1024;
        elseif (strpos($size, 'g') !== false)
            $bytes = intval($size) * 1024 * 1024 * 1024;
        return $bytes;
    }
}
