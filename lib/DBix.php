<?php

namespace DBix;

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

class DBAL {
    public function __construct($url) {
        $this->verbose = false;
        $u = parse_url($url);

        @mysql_pconnect($u['host'], $u['user'], $u['pass']);
        if(mysql_error()) throw new Exception ('MySQL can not connect');

        @mysql_select_db(substr($u['path'], 1, 1000));
        if(mysql_error()) throw new Exception ('MySQL can not select db');

        return $this;
    }

    public function query($query, $params = null) {
        if(!$query) throw new Exception ('Needs query');
        $params = lazy_params($params, func_get_args());

        $q = new ActiveRecordQuery($query, $params);
        $q->verbose = $this->verbose;
        $q->db = $this;
        return $q;
    }

    public function execute($query, $params = null) {
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
        $query = "CREATE TABLE `?` ($def)";
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

    public function migrate_schema($table, $new_schema) {
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
}

class Query {
    private $_affected, $_num_rows;
    public function __construct($query, $params = null) {
        $params = lazy_params($params, func_get_args());
        $this->query = $this->sql_query($query, $params);
        $this->has_run = false;
        $this->db = null;
    }

    public function get_sql() {
        return $this->query;
    }

    public function last_id() {
        return $this->db->query('SELECT LAST_INSERT_ID()')->fetch_cell();
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

        $replacer = function($m) use ($params) {
            static $calls;
            if(!isset($calls)) $calls = 0;
            $replace = $params[(int)$calls];
            $calls++;
            if($replace === null) {
                return 'NULL';
            } else {
                if($m[1] == '?') {
                    return '"' . @mysql_real_escape_string($replace) . '"';
                } elseif($m[1] == '`?`') {
                    return '`' . @mysql_real_escape_string($replace) . '`';
                } else {
                    throw new Exception ('Unhandled placeholder');
                }
            }
        };

        return preg_replace_callback($regex, $replacer, $query);
    }

    public function run() {
        if(!$this->has_run) {
            $this->has_run = true;
            $this->rq = @mysql_query($this->get_sql());
            if(mysql_error())
                throw new Exception ('Query:' . $this->get_sql() . "\n" .
                                      'MySQL error: ' . mysql_error());

            if($this->verbose)
                print sprintf("<div style='background: gray; font: 11px Arial;
                color: silver; padding: 5px; margin-bottom:
                10px;'>%s<br>Affected %d; num_rows: %d</div>",
                    $this->get_sql(), $this->affected(), $this->num_rows());

            $this->_affected = @mysql_affected_rows();
            $this->_num_rows = @mysql_num_rows($this->rq);

        }
        return $this;
    }

    public function fetch_all() {
        $this->run();
        $ret = array();
        while($o = @mysql_fetch_assoc($this->rq)) {
            $ret []= $o;
        }
        return $ret;
    }

    public function fetch_row() {
        $this->run();
        $ret = array();
        if($this->num_rows() < 1)
            throw new Exception ('There were no results');

        $o = @mysql_fetch_assoc($this->rq);
        return $o;
    }

    public function fetch_column() {
        $this->run();
        $ret = array();

        while($o = @mysql_fetch_row($this->rq)) {
            $ret []= $o[0];
        }
        return $ret;
    }

    public function fetch_cell() {
        $this->run();
        $ret = array();
        if($this->num_rows() < 1)
            throw new Exception ('There were no results');

        $o = @mysql_fetch_row($this->rq);
        return $o[0];
    }

}

class Model {
    public $__meta;

    public function __construct($db, $table, $item) {
        $this->__meta = array();
        $this->__meta['changed'] = false;
        $this->__meta['deleted'] = false;

        $this->set_meta('db', $db);
        $this->set_meta('table', $table);
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
            if(!$this->__meta['item'][$k] !== $v)
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
    public function __construct($query, $params = null) {
        parent::__construct($query, $params);
    }

    public function extract_table() {
        preg_match('#^\s*select.*?from\s+`(.*?)`#is', $this->get_sql(), $m);
        return $m[1];
    }

    public function fetch_all_active() {
        $this->run();
        $ret = array();
        while($row = @mysql_fetch_assoc($this->rq)) {
            $m = 'DBix\Model';
            $m = new $m($this->db, $this->extract_table(), $row);
            $ret []= $m;
        }
        return $ret;
    }
}

function partition($string, $cut_at) {
    $pos = strpos($string, $cut_at);
    if($pos === false)
        return array($string, '');
    return array(substr($string, 0, $pos), substr($string, $pos+1, strlen($string)-$pos));
}

class Exception extends \Exception {}
class StructureException extends Exception {}
