Example code
============

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

Quering:

    $query = 'select * from `?` where st != ?';

    $run_query = $db->query($query, 'table', 'wrong-stuff')->run();

    $items = $db->query($query, 'table', 'wrong-stuff')->fetch_all();
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



Ideas (maybe)
=============

* $users = $db->create_table('users', ....); $users->create(...)
* fetch_assoc()
* caching

More info
=========

See [example.php](https://github.com/yappie/DBix/blob/master/example.php) and [Tests/index.php]([example.php](https://github.com/yappie/DBix/blob/master/Tests/index.php))
