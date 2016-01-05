<?php

namespace Tabulate\Commands;

class HelpCommand extends CommandBase
{

    public function run()
    {
        $it = new \RecursiveDirectoryIterator(__DIR__, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        $commands = [];
        foreach ($files as $file) {
            $suffix = 'Command.php';
            if (substr($file->getBasename(), -strlen($suffix)) === $suffix) {
                $commands[] = strtolower(substr($file->getBasename(), 0, -strlen($suffix)));
            }
        }
        $this->write("The following commands are available:");
        foreach ($commands as $cmd) {
            $this->write("   $cmd");
        }
    }
}
