<?php
    function str_replace_first($search, $replace, $subject) {
        return implode($replace, explode($search, $subject, 2));
    }

    abstract class Database {
        abstract protected function prepare($sql, $args);
        abstract public function query($sql, $args);
        abstract public function exec($sql, $args);
        abstract public function columns($table);
        public $connection;
    }

    class SimpleDB {
        public function __construct($db) {
            $this->db = $db;
        }

        /**
         * A function to execute SELECT/UPDATE/DELETE operations in a simple way
         */
        public function operation($table, $operation = 'SELECT', $field = null, $where = null, $set_fields=null, $order = null, $offset = 0, $limit = 0){
            $args = [];
            $whereQ = (is_null($where)) ? '' : 'WHERE';
            $setQ = (is_null($set_fields)) ? '' :'SET';
            $orderQ = '';
            $offsetQ = '';
            $limitQ = '';
            $operationQ = $operation;
            if(!is_null($field)){
                $operationQ .= ($field == '*') ? ' *' : ' `'.$field.'`';
            }

            if(!is_null($where)){
                foreach(array_keys($where) as $key){
                    $value = (!is_array($where[$key])) ? $where[$key] : $where[$key]['val'];
                    $sign = (!is_array($where[$key])) ? '=' : $where[$key]['sign'];
                    $whereQ .= (($whereQ == 'WHERE' or str_ends_with($whereQ, 'AND')) ? ' ' : ' AND ').' '.$key.' '.$sign.' %param%';
                    array_push($args, $value);
                }
            }

            if(!is_null($set_fields)){
                foreach(array_keys($set_fields) as $key){
                    $setQ .= (($setQ == 'SET' or str_ends_with($setQ, ',')) ? ' ' : ' , ').' `'.$key.'` = %param%';
                    array_push($args, $set_fields[$key]);
                }
            }

            if($order != null){
                $order = 'ORDER BY `'.$order[0].'` '.$order[1];
            }
            if($offset != 0){
                $offsetQ = 'OFFSET %param%';
                array_push($args, $offset);
            }
            if($limit != 0){
                $limitQ = 'LIMIT %param%';
                array_push($args, $limit);
            }

            $sql_final = $operationQ.' '.(($operation == 'UPDATE') ? '' : 'FROM').' `'.$table.'` '.$setQ.' '.$whereQ.' '.$orderQ.' '.$offsetQ.' '.$limitQ;
            return $this->db->query($sql_final, $args);
        }

        // "operation" function shortcuts
        public function select($table, $field = '*', $where = null, $order = null, $offset = 0, $limit = 0){ return $this->operation($table, 'SELECT', $field, $where, null, $order, $offset, $limit); }
        public function delete($table, $where = null){ return $this->operation($table, 'DELETE', null, $where); }
        public function update($table, $set_fields, $where = null){ return $this->operation($table, 'UPDATE', null, $where, $set_fields); }

        public function insert($table, $values){ // This handles any INSERT operations.
            $columns = $this->db->columns($table);
            $c2 = [];
            foreach ($columns as $value) {
                array_push($c2, '`'.$value.'`');
            }
            $this->db->exec('INSERT INTO `'.$table.'` ('.implode(',', $c2).') VALUES ('.implode(',', array_fill(0, count($columns), '%param%')).');', $values);
        }
    }

    class SQLiteDB extends Database {
        public function __construct($db_file) {
            $this->connection = new SQLite3($db_file);
            $this->simple = new SimpleDB($this);
        }

        protected function prepare($sql, $args = []){
            $args2 = [];
            foreach(range(1, substr_count($sql, "%param%")) as $i){
                $p = ':'.strval($i);
                array_push($args2, $p);
                $sql = str_replace_first("%param%", $p, $sql);
            }
            $query = $this->connection->prepare($sql);
            foreach ($args2 as $value) {
                $query->bindParam($value, $args[array_search($value, $args2)]);
            }
            return $query;
        }

        public function query($sql, $args = [], $getResult = 1, $fetchFirst = 0){
            $query = $this->prepare($sql, $args);
            $res = $query->execute();
            if($getResult){
                $rows = [];
                while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
                    array_push($rows, $row);
                }
                return ($fetchFirst) ? ((count($rows)>0) ? $rows[0] : NULL) : $rows;
            }
        }
        public function exec($sql, $args = []){ $this->query($sql, $args, 0); }

        public function columns($table){
            $columns_s = $this->query('PRAGMA table_info(`'.$table.'`)');
            $columns = [];
            foreach ($columns_s as $value) {
                array_push($columns, $value['name']);
            }
            return $columns;
        }
    }
?>