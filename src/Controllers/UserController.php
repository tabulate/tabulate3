<?php

namespace Tabulate\Controllers;

class UserController extends \Tabulate\Controllers\ControllerBase
{

    public function loginForm()
    {
        $template = new \Tabulate\Template('login.twig');
        $template->title = 'Log in';
        echo $template->render();
    }

    public function registerForm()
    {
        $template = new \Tabulate\Template('register.twig');
        $template->title = 'Register';
        echo $template->render();
    }
}
