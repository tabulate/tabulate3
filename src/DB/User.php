<?php

namespace Tabulate\DB;

class User
{

    protected $data;

    public function __construct(Database $db)
    {
        $id = (isset($_SESSION['user'])) ? $_SESSION['user'] : false;
        if ($id) {
            $this->data = $db->query("SELECT * FROM users WHERE id=:id", ['id' => $id]);
        }
    }

    public function getId()
    {
        return (isset($this->data['id'])) ? $this->data['id'] : null;
    }
}
