<?php

namespace Tabulate;

class Config
{

    public static function configFile()
    {
        $envConfig = getenv('TABULATE_CONFIG_FILE');
        $configFile = ($envConfig) ? $envConfig : __DIR__ . '/../config.php';
        return realpath($configFile);
    }

    protected static function get($name, $default = null)
    {
        if (file_exists(self::configFile())) {
            require self::configFile();
            if (isset($$name)) {
                return $$name;
            }
        }
        return $default;
    }

    public static function debug()
    {
        return (bool) self::get('debug', false);
    }

    /**
     * Get the Base URL of the application. Never has a trailing slash.
     * @return string
     */
    public static function baseUrl()
    {
        $calculatedBaseUrl = substr($_SERVER['SCRIPT_NAME'], 0, -(strlen('index.php')));
        $baseUrl = self::get('baseUrl', $calculatedBaseUrl);
        return rtrim($baseUrl, ' /');
    }

    public static function siteTitle()
    {
        return self::get('siteTitle', 'A Tabularium');
    }

    public static function databaseHost()
    {
        return self::get('databaseHost', 'localhost');
    }

    public static function databaseName()
    {
        return self::get('databaseName', 'tabulate');
    }

    public static function databaseUser()
    {
        return self::get('databaseUser', 'tabulate');
    }

    public static function databasePassword()
    {
        return self::get('databasePassword', '');
    }

    public static function storageDirData($subdir = '')
    {
        return self::storageDir('storageDirData', 'data', $subdir);
    }

    public static function storageDirTmp($subdir = '')
    {
        return self::storageDir('storageDirTmp', 'tmp', $subdir);
    }

    protected static function storageDir($configVarName, $dir = '', $subdir = '')
    {
        $dataDir = self::get($configVarName, false);
        if ($dataDir === false) {
            $dataDir = __DIR__ . '/../data';
        }
        $dataDir = $dataDir . '/' . $dir . '/' . $subdir;
        if (!file_exists($dataDir)) {
            try {
                mkdir($dataDir, 0755, true);
            } catch (\Exception $e) {
                throw new \Exception("Unable to create directory '$dataDir'", 500);
            }
        }
        return realpath($dataDir);
    }

    public static function dotCommand()
    {
        return self::get('dotCommand', 'dot');
    }
}
