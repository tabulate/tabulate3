<?php

namespace Tabulate\DB\Tables;

class Grants extends \Tabulate\DB\Table
{

    const READ = 'read';
    const CREATE = 'create';
    const UPDATE = 'update';
    const DELETE = 'delete';
    const IMPORT = 'import';

    protected static $userGrants;

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

    public static function checkGrant($permission, $tableName)
    {
    }
}
