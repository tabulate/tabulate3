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

    public function login()
    {
        $template = new \Tabulate\Template('users/login.twig');
        $name = filter_input(INPUT_POST, 'name');
        $user = $this->db->query('SELECT * FROM users WHERE name LIKE :name LIMIT 1', ['name' => $name])->fetch();
        if (isset($user->password)) {
            $verified = password_verify(filter_input(INPUT_POST, 'password'), $user->password);
            if ($verified) {
                $_SESSION['user_id'] = $user->id;
                $template->addNotice('info', 'You are now logged in.');
                $this->redirect('/');
            }
        }
        $template->addNotice('info', 'Access Denied');
        $this->redirect('/login');
    }

    public function logout()
    {
        $_SESSION = [];
        setcookie(session_name(), '', time() - 3600, '/');
        session_destroy();
        $this->redirect('/login');
    }

    public function registerForm()
    {
        $template = new \Tabulate\Template('users/register.twig');
        $template->title = 'Register';
        echo $template->render();
    }
}
