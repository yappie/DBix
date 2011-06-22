<?php

/*
 * DBix.php
 *
 * @author  Slava V. <bomboze@gmail.com>
 * @license BSD/MIT Dual Licensed
 * @url     https://github.com/yappie/DBix
 *
 */

namespace DBix;

class DBAL {
    public $dbh;
    private $active_models;

    public function __construct($url) {
        $this->verbose = false;
        $this->connect($url);
        $this->active_models = array();
        $this->default_engine = 'MyISAM';
        $this->table_charset = 'utf8';
    }

    private function connect($url) {
        $u = parse_url($url);
        if(!array_key_exists('path', $u) || !array_key_exists('host', $u))
            throw new Exception('Please supply a valid connection URI');

        try {
            $db_name = substr($u['path'], 1, 1000);
            $conn_string = sprintf('mysql:host=%s;dbname=%s;charset=UTF-8', $u['host'], $db_name);
            $this->dbh = new PDONullEnabled($conn_string, $u['user'], $u['pass']);

            $this->dbh->exec("SET NAMES utf8");
            $this->dbh->exec("SET character_set_client='utf8'");
            $this->dbh->exec("SET character_set_results='utf8'");
            $this->dbh->exec("SET collation_connection='utf8_general_ci'");

            $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new Exception ('MySQL can not connect. ' . $e->getMessage());
        }

        return $this;
    }

    public function query($query, $params = null) {
        if(!$query) throw new Exception ('Needs query');
        $params = lazy_params($params, func_get_args());

        $q = new ActiveRecordQuery($this, $query, $params);
        $q->verbose = $this->verbose;
        return $q;
    }

    public function execute($query, $params = null) {
        $params = lazy_params($params, func_get_args());
        return $this->query($query, $params)->run();
    }

    public function insert($table, $row) {
        if(!$table) throw new Exception ('Needs table');
        $keys_holders = join(', ', array_repeat('`?`', count($row)));
        $keys_values = join(', ', array_repeat('?', count($row)));
        $values = array_merge(
            (array)$table, array_keys($row), array_values($row)
            );

        $sql = 'INSERT INTO `?` ('.$keys_holders.') VALUES ('.$keys_values.')';
        return $this->execute($sql, $values);
    }

    # array('?=?', '?=?',)
    private function k_eq_q($kv) {
        $ret = array();
        $placeholders = array();
        foreach($kv as $k=>$v) {
            $placeholders []= '`?`=?';
            $ret []= $k;
            $ret []= $v;
        }
        return array($placeholders, $ret);
    }

    public function insert_update($table, $insert, $update) {
        if(!$table) throw new Exception ('Needs table');
        $keys_holders = join(', ', array_repeat('`?`', count($insert)));
        $keys_values = join(', ', array_repeat('?', count($insert)));

        list($update_holders, $update_keys_values) = $this->k_eq_q($update);
        $values = array_merge(
            (array)$table,
            array_keys($insert), array_values($insert),
            $update_keys_values
            );

        $sql = 'INSERT INTO `?` ('.$keys_holders.') VALUES ('.$keys_values.') '.
            'ON DUPLICATE KEY UPDATE '.join(', ', $update_holders);
        return $this->execute($sql, $values);
    }

    public function update_where($table, $update, $where) {
        if(!$table) throw new Exception ('Needs table');
        list($update_holders, $update_keys_values) = $this->k_eq_q($update);
        list($where_holders,  $where_keys_values) = $this->k_eq_q($where);

        $values = array_merge(
            (array)$table,
            $update_keys_values,
            $where_keys_values
            );

        $sql = 'UPDATE `?` SET '.join(', ', $update_holders).' '.
            'WHERE '.join(' AND ', $where_holders);
        return $this->execute($sql, $values);
    }

    public function delete_where($table, $where) {
        if(!$table) throw new Exception ('Needs table');
        list($where_holders,  $where_keys_values) = $this->k_eq_q($where);

        $values = array_merge(
            (array)$table,
            $where_keys_values
            );

        $sql = 'DELETE FROM `?` '.
            'WHERE '.join(' AND ', $where_holders);
        return $this->execute($sql, $values);
    }

    public static function check_symbols($syms, $def, $exc) {
        if(!preg_match('#^[' . $syms . ']+$#is', $def))
                throw new Exception($exc);
    }

    public function create_table($table, $schema) {
        $fields = array();
        foreach($schema as $field_name => $def) {
            self::check_symbols('a-z0-9_\(\) ', $def,
                sprintf('Field `%s` has bad definition: "%s"',
                $field_name, $def));
            $fields []= '`?` ' . $def;
        };
        $def = join(', ', $fields);
        $query = sprintf("CREATE TABLE `?` (%s) ENGINE=%s DEFAULT CHARACTER SET %s",
            $def, $this->default_engine, $this->table_charset);
        $params = array_merge((array)$table, array_keys($schema));
        $this->execute($query, $params);
    }

    public function lock_for_write($table) {
        $this->execute('LOCK TABLES `?` WRITE', $table);
    }

    public function unlock() {
        $this->execute('UNLOCK TABLES');
    }

    public function get_indices($table) {
        $indices = $this->query('SHOW INDEX FROM `?`', $table)->fetch_all();
    }

    public function get_columns_names($table) {
        $cols = $this->query('SHOW COLUMNS FROM `?`', $table)->fetch_all();
         # [Field] => id [Type] => bigint(20) [Null] => NO [Key] => PRI [Default] => [Extra] => auto_increment
        foreach($cols as $col) {
            $ret []= $col['Field'];
        }
        return $ret;
    }

    public function table_exists($table) {
        $q = $this->query('SHOW TABLES LIKE ?', $table)->run();
        return $q->num_rows() > 0;
    }

    public function upgrade_schema($table, $new_schema) {
        $old_columns_keys = $this->get_columns_names($table);
        $new_columns_keys = array_keys($new_schema);

        $added_columns = array_diff($new_columns_keys, $old_columns_keys);
        $same_columns = array_intersect($new_columns_keys, $old_columns_keys);
        $deleted_columns = array_diff($old_columns_keys, $new_columns_keys);

        $query = 'ALTER TABLE `?` ';
        $params = array($table);

        $cmds = array();

        foreach($added_columns as $col) {
            $cmds []= 'ADD COLUMN `?` ' . $new_schema[$col];
            $params []= $col;
        }

        foreach($same_columns as $col) {
            $col_def = $new_schema[$col];
            $col_def = preg_replace('#\b(primary key|unique|index)\b#is', '', $col_def);
            $cmds []= 'MODIFY COLUMN `?` ' . $col_def;
            $params []= $col;
        }

        $query .= join(', ', $cmds);

        $this->execute($query, $params);
    }

    public function quote($item) {
        return $this->dbh->quote($item);
    }

    public function set_model_for($table, $class_name = null) {
        if($class_name === null) {
            unset($this->active_models[$table]);
            return;
        }

        if(!class_exists($class_name)) {
            throw new Exception(sprintf('"%s" is not a valid class', $class_name));
        }

        $class = new \ReflectionClass($class_name);
        if(!$class->isSubclassOf('DBix\Model')) {
            throw new Exception(sprintf('Your class %s must extend DBix\Model', $class_name));
        }

        $this->active_models[$table] = $class_name;
    }

    public function get_model_for($table) {
        if(!array_key_exists($table, $this->active_models)) {
            return 'DBix\Model';
        }

        return $this->active_models[$table];
    }

}

class Query {
    private $_affected, $_num_rows;
    public function __construct($db, $query, $params = null) {
        $params = lazy_params($params, func_get_args());
        $this->db = $db;
        $this->query = $this->sql_query($query, $params);
        $this->has_run = false;
    }

    public function get_sql() {
        return $this->query;
    }

    public function last_id() {
        return $this->db->dbh->lastInsertId();
    }

    public function affected() {
        return $this->_affected;
    }

    public function num_rows() {
        return $this->_num_rows;
    }

    private function sql_query($query, $params = null) {
        if($params === null) $params = array();
        $regex_to_find = '(\?|`\?`)';
        $regex = '#' . $regex_to_find . '#is';

        $m = array();
        $num_found = preg_match_all($regex, $query, $m);

        if($num_found != count($params))
            throw new Exception (sprintf('Number of placeholders(%d) didn\'t '.
                    'match number of params(%d)', $num_found, count($params)));

        $saved_this = $this;
        $replacer = function($m) use ($params, $saved_this) {
            static $calls;
            if(!isset($calls)) $calls = 0;
            $replace = $params[(int)$calls];
            $calls++;
            if($replace === null) {
                return 'NULL';
            } else {
                if($m[1] == '?') {
                    if(is_array($replace)) {
                        $quoted = $saved_this->quote_array($replace);
                    } else {
                        $quoted = $saved_this->db->quote($replace);
                    }
                    return $quoted;
                } elseif($m[1] == '`?`') {
                    $quoted = $saved_this->db->quote($replace);
                    $quoted = substr($quoted, 1, count($quoted) - 2);
                    return '`' . $quoted . '`';
                } else {
                    throw new Exception ('Unhandled placeholder');
                }
            }
        };

        return preg_replace_callback($regex, $replacer, $query);
    }

    public function quote_array($arr) {
        $values = array();
        foreach($arr as $v) {
            $values []= $this->db->quote($v);
        };
        return '(' . join(', ', $values) . ')';
    }

    public function run() {
        if(!$this->has_run) {
            $this->has_run = true;
            try {
                $this->sth = $this->db->dbh->prepare($this->get_sql());
                $this->sth->execute();
            } catch(\PDOException $e) {
                throw new Exception ('Query:' . $this->get_sql() . "\n" .
                                     'MySQL error: ' . $e->getMessage());
            }

            $this->_affected = $this->_num_rows = $this->sth->rowCount();

            if($this->verbose)
                print sprintf("<div style='background: gray; font: 11px Arial;
                color: silver; padding: 5px; margin-bottom:
                10px;'>%s<br>Affected %d; num_rows: %d</div>",
                    $this->get_sql(), $this->affected(), $this->num_rows());

        }
        return $this;
    }

    public function fetch_all() {
        $this->run();
        $ret = array();
        $this->sth->setFetchMode(\PDO::FETCH_ASSOC);

        while($o = $this->sth->fetch()) {
            $ret []= $o;
        }
        return $ret;
    }

    public function fetch_row() {
        $this->run();
        $ret = array();
        if($this->num_rows() < 1)
            throw new Exception ('There were no results');

        $this->sth->setFetchMode(\PDO::FETCH_ASSOC);
        $o = $this->sth->fetch();
        return $o;
    }

    public function fetch_column() {
        $this->run();
        $ret = array();

        $this->sth->setFetchMode(\PDO::FETCH_NUM);
        while($o = $this->sth->fetch()) {
            $ret []= $o[0];
        }
        return $ret;
    }

    public function fetch_cell() {
        $this->run();
        $ret = array();
        if($this->num_rows() < 1)
            throw new Exception ('There were no results');

        $this->sth->setFetchMode(\PDO::FETCH_NUM);
        $o = $this->sth->fetch();
        return $o[0];
    }

}

class Model {
    public $__meta;

    public function __get_fields() {
        $table = $this->get_meta('table');
        return $this->get_meta('db')->get_columns_names($table);
    }

    public function __construct($db, $table, $item = array()) {
        $this->__meta = array();
        $this->set_meta('changed', false);
        $this->set_meta('deleted', false);
        $this->set_meta('new_item', empty($item));

        $this->set_meta('db', $db);
        $this->set_meta('table', $table);

        if($this->get_meta('new_item')) {
            foreach($this->__get_fields() as $f) {
                $item[$f] = null;
            };
        };

        $this->set_meta('item', $item);

        $pk = array();
        foreach($this->primary_key() as $p) {
            if(!array_key_exists($p, $item))
                throw new Exception ('One of the primary keys '.
                                     'fields wasn\'t fetched');
            $pk[$p] = $item[$p];
        }

        $this->set_meta('primary_key', $pk);
    }

    private function set_meta($k, $v) {
        $this->__meta[$k] = $v;
    }

    private function get_meta($k) {
        return $this->__meta[$k];
    }

    public function __set($k, $v) {
        if(array_key_exists($k, $this->__meta['item'])) {
            if($this->__meta['item'][$k] != $v)
                $this->__meta['changed'] = true;
            $this->__meta['item'][$k] = $v;
        } else {
            throw new StructureException(sprintf('Field "%s" doesn\'t exist', $k));
        }
    }

    public function __get($k) {
        if(array_key_exists($k, $this->__meta['item'])) {
            return $this->__meta['item'][$k];
        } else {
            throw new StructureException(sprintf('Field "%s" doesn\'t exist', $k));
        }
    }

    public function primary_key() {
        return array('id');
    }

    public function __toString() {
        $r = '';
        foreach($this->get_meta('item') as $k => $v) {
            if($r) $r .= ', ';
            $r .= sprintf('%s = "%s"', $k, $v);
        }
        return sprintf("%s(%s)", ucfirst($this->get_meta('table')), $r);
    }

    public function delete() {
        $where = $this->get_meta('primary_key');
        $table = $this->get_meta('table');
        $q = $this->get_meta('db')->delete_where($table, $where);
        if($q->affected() != 1)
            throw new Exception (sprintf('ActiveRecord update went wrong: '.
                'there were %d rows deleted instead of 1', $q->affected()));
        $this->__meta['deleted'] = true;
    }

    public function save() {
        if($this->__meta['deleted'])
            throw new Exception ('This item was previously deleted');

        $table = $this->get_meta('table');
        if($this->get_meta('new_item')) {
            $q = $this->get_meta('db')->insert($table, $this->get_meta('item'));

            if($this->primary_key() == array('id')) {
                $new_id = $q->last_id();
                $this->__meta['item']['id'] = $new_id;
                $this->__meta['primary_key']['id'] = $new_id;
                $this->set_meta('new_item', false);
            } else {
                throw new Exception ('I don\'t know how to update composite '.
                    'primary keys (missing feature), but the row was inserted, '.
                    'suppress this with try-catch.');
            }
            return;

        }

        if($this->__meta['changed']) {

            $where = $this->get_meta('primary_key');
            $table = $this->get_meta('table');
            $q = $this->get_meta('db')->update_where($table,
                                                $this->get_meta('item'), $where);

            if($q->affected() != 1)
                throw new Exception (sprintf('ActiveRecord update went wrong: '.
                    'there were %d rows updated instead of 1', $q->affected()));
        } else {
            if($this->get_meta('db')->verbose)
                print "Won't save!";
        };
    }

}

class ActiveRecordQuery extends Query {
    #public function __construct($query, $params = null) {
    #    parent::__construct($query, $params);
    #}

    public function extract_table() {
        $this->run();
        $column_meta = $this->sth->getColumnMeta(0);
        $table = $column_meta['table'];
        return $table;
    }

    public function fetch_all_active() {
        $this->run();
        $ret = array();
        $this->sth->setFetchMode(\PDO::FETCH_ASSOC);

        $table = $this->extract_table();
        $model_class = $this->db->get_model_for($table);
        while($row = $this->sth->fetch()) {
            $m = new $model_class($this->db, $table, $row);
            $ret []= $m;
        }
        return $ret;
    }
}

class Exception extends \Exception {}
class StructureException extends Exception {}

class PDONullEnabled extends \PDO {
  public function quote($value, $parameter_type = \PDO::PARAM_STR ) {
    if(is_null($value)) {
      return "NULL";
    }

    return parent::quote($value, $parameter_type);
  }
}

function array_repeat($needle, $times) {
    $ret = array();
    for ($i = 0; $i < $times; $i++) {
        $ret []= $needle;
    }
    return $ret;
}

function lazy_params($params, $func_get_args) {
    if(!is_array($params)) {
        if (count($func_get_args) > 2) {
            $params = array_splice($func_get_args, 1, 1000);
        } else {
            $params = (array)$params;
        };
    }
    return $params;
}

function partition($string, $cut_at) {
    $pos = strpos($string, $cut_at);
    if($pos === false)
        return array($string, '');
    return array(substr($string, 0, $pos),
                 substr($string, $pos+1,
                 strlen($string)-$pos));
}

