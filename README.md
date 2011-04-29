Example code:

    $db = 'mysql://user:pass@localhost/db';

    $db->create_table($this->table, array(
        'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
        'st' => 'VARCHAR(250)',
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


See [example.php](https://github.com/yappie/DBix/blob/master/example.php)
