<?php

namespace Tabulate\Controllers;

use Tabulate\DB\Database;
use Tabulate\DB\User;

abstract class ControllerBase
{

    /** @var \Tabulate\DB\User */
    protected $user;

    public function __construct()
    {
        $this->db = new Database();
        $this->user = new User($this->db);
    }

    protected function redirect($route)
    {
        $url = \Tabulate\Config::baseUrl() . '/' . ltrim($route, '/ ');
        http_response_code(303);
        header("Location: $url");
        exit(1);
    }

    protected function sendFile($ext, $mime, $content, $downloadName = false)
    {
        $downloadName = ($downloadName ? : date('Y-m-d') ) . '.' . $ext;
        header('Content-Encoding: UTF-8');
        header('Content-type: ' . $mime . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        echo $content;
        exit;
    }
}
