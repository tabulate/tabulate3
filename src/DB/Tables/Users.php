<?php

namespace Tabulate\DB\Tables;

class Users extends \Tabulate\DB\Table
{

    const ADMIN = 1;
    const ANON = 2;

//    protected $data;
//
//    public function __construct(Database $db)
//    {
//        $id = (isset($_SESSION['user'])) ? $_SESSION['user'] : false;
//        if ($id) {
//            $this->data = $db->query("SELECT * FROM users WHERE id=:id", ['id' => $id]);
//        }
//    }
//
//    public function getId()
//    {
//        return (isset($this->data['id'])) ? $this->data['id'] : null;
//    }
}
