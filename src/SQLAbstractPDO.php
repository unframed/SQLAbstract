<?php

class SQLAbstractPDO extends SQLAbstract {
    /**
     *
     */
    static function open ($dsn, $username=NULL, $password=NULL, $options=NULL) {
        $pdo = new PDO($dsn, $username, $password, (
            $options === NULL ? array() : $options
            ));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
    /**
     *
     */
    function openMySQL ($name, $user, $password, $host='localhost', $port='3306') {
        $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$name;
        $pdo = SQLAbstractPDO::open(
            $dsn, $user, $password,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
        return $pdo;
    }
    private $_pdo;
    function __construct ($pdo, $prefix='') {
        $this->_pdo = $pdo;
        $this->_prefix = $prefix;
    }
    function pdo () {
        return $this->_pdo;
    }
    // ? TODO: leave to the Unframed application ?
    function transaction ($callable, $arguments=NULL) {
        $transaction = FALSE;
        if ($arguments === NULL) {
            $arguments = array($this);
        }
        try {
            $transaction = $this->_pdo->beginTransaction();
            $result = call_user_func_array($callable, $arguments);
            $this->_pdo->commit();
            return $result;
        } catch (Exception $e) {
            if ($transaction) {
                $this->_pdo->rollBack();
            }
            throw $e;
        }
    }
    private static function _bindValue ($st, $index, $value) {
        if (!is_scalar($value)) {
            throw new Unframed('cannot bind non scalar '.json_encode($value));
        } elseif (is_int($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_INT);
        } elseif (is_bool($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_BOOL);
        } elseif (is_null($value)) {
            return $st->bindValue($index, $value, PDO::PARAM_NULL);
        } else {
            return $st->bindValue($index, $value); // String
        }
    }
    private function _statement ($sql, $parameters) {
        $st = $this->_pdo->prepare($sql);
        if ($parameters !== NULL) {
            if (JSONMessage::is_list($parameters)) {
                $index = 1;
                foreach ($parameters as $value) {
                    self::_bindValue($st, $index, $value);
                    $index = $index + 1;
                }
            } else {
                throw new Exception('Type Error - $parameters not a List');
            }
        }
        if ($st->execute()) {
            return $st;
        }
        $info = $st->errorInfo();
        throw new Exception($info[2]);
    }
    function execute ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->rowCount();
    }
    function lastInsertId () {
        return $this->_pdo->lastInsertId();
    }
    function fetchOne ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetch(PDO::FETCH_ASSOC);
    }
    function fetchAll ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);
    }
    function fetchOneColumn ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetch(PDO::FETCH_COLUMN);
    }
    function fetchAllColumn ($sql, $parameters=NULL) {
        return $this->_statement($sql, $parameters)->fetchAll(PDO::FETCH_COLUMN);
    }
    function prefix($name='') {
        return $this->_prefix.$name;
    }
    function identifier($name) {
        return "`".$name."`";
    }
    function placeholder($value) {
        return '?';
    }
}