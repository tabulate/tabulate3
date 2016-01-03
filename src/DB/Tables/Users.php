<?php

namespace Tabulate\DB\Tables;

class Users extends \Tabulate\DB\Table
{

    const ADMIN = 1;
    const ANON = 2;

    public function __construct(\Tabulate\DB\Database $database, $name = 'users')
    {
        parent::__construct($database, $name);
    }
}
