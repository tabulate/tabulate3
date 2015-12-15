<?php

namespace Tabulate\DB\Tables;

class Grants extends \Tabulate\DB\Table
{

    const READ = 'read';
    const CREATE = 'create';
    const UPDATE = 'update';
    const DELETE = 'delete';
    const IMPORT = 'import';

    public static function getPermissions()
    {
        return [
            self::READ,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::IMPORT,
        ];
    }
}
