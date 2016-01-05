<?php

namespace Tabulate\Controllers;

use Tabulate\DB\Database;

abstract class ControllerBase
{

    /** @var \Tabulate\DB\User */
    protected $user;

    /** @var \Tabulate\DB\Database */
    protected $db;

    public function __construct()
    {
        $this->db = new Database();
        if (isset($_SESSION['user_id'])) {
            $this->user = $this->db->query('SELECT `id`, `name` FROM users WHERE id=:id', ['id' => $_SESSION['user_id']])->fetch();
            $this->db->setCurrentUser($this->user->id);
        }
    }

    protected function redirect($route)
    {
        $url = \Tabulate\Config::baseUrl() . '/' . ltrim($route, '/ ');
        http_response_code(303);
        header("Location: $url");
        exit(0);
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
