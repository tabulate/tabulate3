<?php

namespace Tabulate\Controllers;

use Tabulate\Template;

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
        $remind = filter_input(INPUT_POST, 'remind', FILTER_DEFAULT, [FILTER_NULL_ON_FAILURE]);
        if ($remind) {
            $this->doRemind();
        } else {
            $this->doLogin();
        }
    }

    protected function doLogin()
    {
        $template = new Template('users/login.twig');
        $name = filter_input(INPUT_POST, 'name');
        $sql = 'SELECT id, password FROM users WHERE name LIKE :name LIMIT 1';
        $user = $this->db->query($sql, ['name' => $name])->fetch();
        if (isset($user->password)) {
            $verified = password_verify(filter_input(INPUT_POST, 'password'), $user->password);
            if ($verified) {
                $_SESSION['user_id'] = $user->id;
                $template->addNotice('info', 'You are now logged in');
                $this->redirect('/');
            }
        }
        $template->addNotice('info', 'Access denied');
        $this->redirect('/login');
    }

    protected function doRemind()
    {
        $template = new Template('users/login.twig');
        $template->addNotice('info', 'Please check your email');
        $this->redirect('/');
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
        $template = new Template('users/register.twig');
        $template->title = 'Register';
        echo $template->render();
    }

    public function register()
    {
        $template = new Template('users/register.twig');
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $findSql = 'SELECT id, password FROM users WHERE name LIKE :name LIMIT 1';
        $user = $this->db->query($findSql, ['name' => $name])->fetch();
        if ($user) {
            $template->addNotice('info', 'That name is already taken');
            $this->redirect('/register');
        }
        $insertSql = 'INSERT INTO users SET name=:name, email=:email';
        $this->db->query($insertSql, ['name' => $name, 'email' => $email]);
        $template->addNotice('info', 'Thank you for registering; please check your email.');
        $this->redirect('/');
    }
}
