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
}
