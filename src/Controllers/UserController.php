<?php

namespace Tabulate\Controllers;

class UserController extends ControllerBase
{

    public function loginForm()
    {
        $template = new \Tabulate\Template('users/login.twig');
        $template->title = 'Log in';
        echo $template->render();
    }

    public function registerForm()
    {
        $template = new \Tabulate\Template('users/register.twig');
        $template->title = 'Register';
        echo $template->render();
    }
}
