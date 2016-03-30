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

    public function remindForm(Request $request, Response $response, array $args)
    {
        $template = new \App\Template('remind.twig');
        $template->title = 'Remind';
        $template->name = $request->get('name');
        $response->setContent($template->render());
        return $response;
    }

    public function remind(Request $request, Response $response, array $args)
    {
        $name = $request->get('name');
        if ($request->get('login')) {
            return new RedirectResponse($this->config->baseUrl() . '/login?name=' . $name);
        }
        $config = new Config();
        $user = new User($this->db);
        $user->loadByName($name);
        $template = new Template('remind_email.twig');
        if (!empty($user->getEmail())) {
            $template->user = $user;
            $template->token = $user->getReminder();
            $message = \Swift_Message::newInstance()
                ->setSubject('Password reminder')
                ->setFrom(array($config->siteEmail() => $config->siteTitle()))
                ->setTo(array($user->getEmail() => $user->getName()))
                ->setBody($template->render(), 'text/html');
            $this->email($message);
        } else {
            // Pause for a moment, so it's not so obvious which users' names are resulting in mail being sent.
            sleep(5);
        }
        $template->alert('success', 'Please check your email', true);
        return new RedirectResponse($this->config->baseUrl() . '/remind?name=' . $user->getName());
    }

    public function remindResetForm(Request $request, Response $response, array $args)
    {
        if (!isset($args['token'])) {
            return new RedirectResponse($this->config->baseUrl() . '/remind');
        }
        $template = new \App\Template('remind_reset.twig');
        $template->title = 'Password Reset';
        $template->userid = $args['userid'];
        $template->token = $args['token'];
        $response->setContent($template->render());
        return $response;
    }

    public function remindReset(Request $request, Response $response, array $args)
    {
        $template = new \App\Template('remind_reset.twig');
        // First check that the passwords match.
        $password = $request->get('password');
        if ($password !== $request->get('password-confirmation')) {
            $template->alert('warning', 'Your passwords did not match.', true);
            return new RedirectResponse($this->config->baseUrl() . "/remind/" . $args['userid'] . "/" . $args['token']);
        }
        // Then see if the token is valid.
        $user = new User($this->db);
        $user->load($args['userid']);
        if (!$user->checkReminderToken($args['token'])) {
            $template->alert('warning', 'That reminder token has expired. Please try again.', true);
            return new RedirectResponse($this->config->baseUrl() . "/remind");
        }
        // Finally change the password. This will delete the token as well.
        $user->changePassword($password);
        $template->alert('success', 'Your password has been changed. Please log in.', true);
        return new RedirectResponse($this->config->baseUrl() . "/login?name=" . $user->getName());
    }
}
