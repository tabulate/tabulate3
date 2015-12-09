<?php

namespace Tabulate\Controllers;

abstract class ControllerBase {

    public function __construct() {
    }

    protected function redirect($route) {
        $url = \Tabulate\Config::baseUrl() . '/' . ltrim($route, '/ ');
        http_response_code(303);
        header("Location: $url");
        exit(1);
    }

    protected function send_file($ext, $mime, $content, $download_name = false) {
        $download_name = ($download_name ? : date('Y-m-d') ) . '.' . $ext;
        header('Content-Encoding: UTF-8');
        header('Content-type: ' . $mime . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        echo $content;
        exit;
    }

}
