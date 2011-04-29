<?

require 'DBix.php';

# Replace this with your connection string
function mysql_pass() { return trim(file_get_contents('/home/http/my.cnf')); }
$db = new DBix\DBAL('mysql://root:'.mysql_pass().'@localhost/test1');

$db->verbose = true;
$table = 'test1';

if($db->table_exists($table))
    $db->execute('DROP TABLE `?`', array($table));

$db->create_table($table, array(
    'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
    'st' => 'VARCHAR(250)',
));

$db->migrate_schema($table, array(
    'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
    'st' => 'VARCHAR(250)',
    'st1' => 'VARCHAR(250)',
));

# Middle level

$db->insert($table,
    array('st' => 'test', 'st1' => 'test')
);

$db->insert_update($table,
    array('st' => 'test', 'st1' => 'test'),
    array('st' => 'test1', 'st1' => 'test1')
);

# Low-level

$db->execute('DROP TABLE IF EXISTS `?`', array($table));
$db->execute('CREATE TABLE `?` (id BIGINT AUTO_INCREMENT PRIMARY KEY, '.
                'st VARCHAR(250), st1 VARCHAR(250))', array($table));
$db->execute('ALTER TABLE `?` ADD UNIQUE INDEX (st)', array($table));
$db->execute('ALTER TABLE `?` ADD UNIQUE INDEX (st1)', array($table));

$db->insert($table, array('st' => 'test', 'st1' => 'test'));

$item = $db->query('SELECT * FROM `?`', $table)->fetch_row();
$item = $db->query('SELECT * FROM `?` WHERE ?', $table, '1=1')->fetch_row();

$db->update_where($table,
    array('st' => 'test', 'st1' => 'test'),
    $item
);

# ActiveRecord

$q = $db->query('SELECT * FROM `?`', array($table));

while($item = $q->fetch_active()) {
    $item->st = 1;
    $item->save();
}

