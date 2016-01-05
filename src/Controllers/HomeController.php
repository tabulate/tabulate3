<?php

namespace Tabulate\Controllers;

class HomeController extends ControllerBase
{

    public function index()
    {
        $template = new \Tabulate\Template('home.twig');
        $template->title = 'Welcome!';
        $template->tables = $this->db->getTables();
        $template->user = $this->user;
        echo $template->render();
    }
}
