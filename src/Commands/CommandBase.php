<?php

namespace Tabulate\Commands;

abstract class CommandBase {

    /** @var string[] CLI arguments. */
    protected $args;

    public function __construct($args) {
        $this->args = $args;
    }

    protected function write($message, $newline = true) {
        echo $message . ($newline ? PHP_EOL : '');
    }

}
