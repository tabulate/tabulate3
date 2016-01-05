<?php

namespace Tabulate;

class Session implements \SessionHandlerInterface
{

    /** @var Tabulate\DB\Database */
    protected $db;

    /** @var boolean */
    protected $exists;

    public function __construct()
    {
        session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
        $this->db = new DB\Database();
        if (!headers_sent()) {
            session_start();
        }
    }

    public function open($savePath, $name)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function destroy($sessionId)
    {
        $this->db->query('DELETE FROM sessions WHERE id=:id', ['id' => $sessionId]);
        return true;
    }

    public function gc($maxLifetime)
    {
        $lifetime = time() - $maxLifetime;
        $this->db->query('DELETE FROM `sessions` WHERE `updated` <= :lifetime', ['lifetime' => $lifetime]);
    }

    public function read($sessionId)
    {
        $session = $this->db->query('SELECT `data` FROM `sessions` WHERE id=:id', ['id' => $sessionId])->fetch();
        if ($session !== false) {
            $this->exists = true;
            return base64_decode($session->data);
        }
    }

    public function write($sessionId, $sessionData)
    {
        if ($this->exists) {
            $sql = 'UPDATE sessions SET data=:data WHERE id=:id';
        } else {
            $sql = 'INSERT INTO sessions SET id=:id, data=:data';
        }
        $params = ['id' => $sessionId, 'data' => base64_encode($sessionData)];
        $this->db->query($sql, $params);
    }
}
