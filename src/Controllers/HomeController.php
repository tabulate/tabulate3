<?php

namespace Tabulate\Controllers;

class HomeController extends ControllerBase
{

    public function index()
    {
        $template = new \Tabulate\Template('home.twig');
        $template->title = 'Welcome!';
        $db = new \Tabulate\DB\Database();
        $template->tables = $db->get_tables();
        echo $template->render();
    }
}
