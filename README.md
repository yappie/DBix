Example code
============

    require 'lib/DBix.php';
    $db = new DBix\DBAL('mysql://user:pass@localhost/db');

Create table:

    $db->create_table($table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
    ));

Migrate table to newer version:

    $db->upgrade_schema($table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
        'st1' => 'VARCHAR(250)', // this field will be added to 'table'
    ));

Create new item:

    $item = new DBix\Model($db, 'table');
    $item->st = 'test';
    $item->save();

ActiveRecord:

    $query = 'select * from `?` where st == ?';
    $items = $db->query($query, 'table', '1')->fetch_all_active();
    $items[0]->st = 'more-updates';
    $items[0]->save();
    $items[0]->delete();

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
        //                                              ->fetch_column() - 1 column as array
        //                                              ->fetch_row()    - 1 row   [throws DBix\Exception if none]
        //                                              ->fetch_cell()   - 1 item  [throws DBix\Exception if none]

Getting number of rows returned and affected:

    print $q->num_rows();
    print $q->affected();

Raw statments:

    $db->execute('DROP TABLE `?`', 'table');

Copy an row with auto_increment'ing ID:

    $row = $db->query('SELECT * FROM items WHERE id=?',
                          (int)$_POST['item_id'])->fetch_row();
    $row['id'] = 0;
    $new_id = $db->insert('items', $row)->last_id();


Unsorted stuff:

    $db->insert($table, array('st' => 'test', 'st1' => 'test'));
    $db->update_where($table,
        array('st' => 'test', 'st1' => 'test'),
        array('st' => 'test', 'st1' => 'test')
    );

    if($db->table_exists($table))

Road Map
========
* PDO http://www.php.net/manual/en/pdostatement.getcolumnmeta.php
* Validation
* Joins

Ideas (for sure)
=============

* fetch_row_active
* listeners (for logging, maybe?)
* fetch_assoc()
* indices management
* joins
* model forms (w/perms)
* column forms
* ajax'y DataGrids

Ideas (maybe)
=============

* support for is_deleted flag?
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

