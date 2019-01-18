<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/models/PDOModel.php');

Class PDOController extends PDOModel{

    private $_host;

    private $_db_name;

    private $_charset;

    private $_user;

    private $_pass;

    private $_conn;

    function __construct(){     
        
        $this->_config = require_once($_SERVER['DOCUMENT_ROOT'].'/config/db.php');

        $this->_host = $this->_config['host'];

        $this->_db_name = $this->_config['db_name'];

        $this->_charset = $this->_config['charset'];

        $this->_user = $this->_config['user'];

        $this->_pass = $this->_config['pass'];

        $dsn = "mysql:host=$this->_host;dbname=$this->_db_name;charset=$this->_charset";

        $opt = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $this->_conn = new PDO($dsn, $this->_user, $this->_pass, $opt);       

    }

    function getConnection(){

        return $this->_conn;
    }

    function closeConnection($conn){

        $conn = null;
    }

}