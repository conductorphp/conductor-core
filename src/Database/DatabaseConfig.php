<?php

namespace DevopsToolCore\Database;

class DatabaseConfig
{
    public $username;
    public $password;
    public $host;
    public $port;

    public function __construct($username, $password, $host = 'localhost', $port = 3306)
    {
        $this->username = $username;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
    }
}
