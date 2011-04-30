Example code
============

    require 'lib/DBix.php';
    $db = new DBix\DBAL('mysql://user:pass@localhost/db');

Create table:

    $db->create_table($this->table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
    ));

Migrate table to newer version:

    $this->db->upgrade_schema($this->table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
        'st1' => 'VARCHAR(250)', // this field will be added to 'table'
    ));

Create new item:

    $item = new DBix\Model($this->db, 'table');
    $item->st = 'test';
    $item->save();

Enable debug:

    $db->verbose = true;

Querying and placeholders:

    $query = 'select * from `?` where st != ?';
    $run_query = $db->query($query, 'table', 'first string')->run();

    // or more formal:

    $query = 'select * from `?` where st != ?';
    $params = array('table', 'first string');
    $run_query = $db->query($query, $params)->run();

Getting results:

    $items = $db->query($query, 'table', 'first string')->fetch_all();
    // also try instead of fetch_all():
    // ->fetch_row() - 1 row
    // ->fetch_column() - 1 column as array
    // ->fetch_cell() - 1 item

Getting number of rows returned and affected:

    print $q->num_rows();
    print $q->affected();

ActiveRecord:

    $items = $db->query($query, 'table', 'wrong-stuff')->fetch_all_active();
    $items[0]->st = 'more-updates';
    $items[0]->save();
    $items[0]->delete();

Raw statments:

    $db->execute('DROP TABLE `?`', 'table');

Unsorted stuff:

    $db->insert($table, array('st' => 'test', 'st1' => 'test'));
    $db->update_where($table,
        array('st' => 'test', 'st1' => 'test'),
        array('st' => 'test', 'st1' => 'test')
    );

    if($db->table_exists($table))

Ideas (for sure)
=============

* fetch_assoc()
* validation
* indices management
* joins

Ideas (maybe)
=============

* $users = $db->create_table('users', ....); $users->create(...)
* caching ?
* non-mysql databases support ?
* multiple db support
* slaves? 
* write protection flag?

Download
========

Get [DBix.php](https://github.com/yappie/DBix/raw/master/lib/DBix.php)

More info
=========

See [example.php](https://github.com/yappie/DBix/blob/master/example.php) and [Tests/index.php](https://github.com/yappie/DBix/blob/master/Tests/index.php)

Tests
=====

In [Tests/index.php](https://github.com/yappie/DBix/blob/master/Tests/index.php)

License
=======

MIT/BSD Dual license
