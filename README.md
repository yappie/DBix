Example code
============

    $db = 'mysql://user:pass@localhost/db';

    $db->create_table($this->table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
    ));

    $this->db->upgrade_schema($this->table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
        'st1' => 'VARCHAR(250)', // this field will be added to 'table'
    ));

    $item = new DBix\Model($this->db, 'table');
    $item->st = 'test';
    $item->save();

    $db->verbose = true; // enable debugging

    $query = 'select * from `?` where st != ?';

    $q = $db->query($query, 'table', 'wrong-stuff')->run();
    print $q->num_rows();

    $items = $db->query($query, 'table', 'wrong-stuff')->fetch_all_active();
    $items[0]->st = 'more-updates';
    $items[0]->save();
    $items[0]->delete();

    $db->execure('DROP TABLE `?`', 'table');


Ideas (maybe)
=============

$users = $db->create_table('users', ....)
$users->create(...)


More info
=========

See [example.php](https://github.com/yappie/DBix/blob/master/example.php) and [Tests/index.php]([example.php](https://github.com/yappie/DBix/blob/master/Tests/index.php))
