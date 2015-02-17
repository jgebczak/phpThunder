<?php

class PTDatabase
{
//======================================================================================================================

    public $h;

    public $connection_string;
    public $user;
    public $pass;

//======================================================================================================================

    public function __construct($settings)
    {
        $this->connection_string = $settings[0];
        $this->user              = $settings[1];
        $this->pass             = $settings[2];

        $this->h = new PDO($this->connection_string, $this->user, $this->pass);
        $this->h->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

//======================================================================================================================

    function createCommand($s)
    {
        return new PTDatabaseCommand ($this, $s);
    }

//======================================================================================================================

    function getLastInsertID()
    {
        return $this->h->lastInsertId();
    }

//======================================================================================================================

}


?>