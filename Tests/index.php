<?php

require('lib/DBix.php');
define('MYSQL_TABLE', 'test1');

class DbalTest extends PHPUnit_Framework_Testcase {
    public function setUp() {
        $conn = 'mysql://root:'.trim(file_get_contents('/home/http/my.cnf')).
                '@localhost/' . MYSQL_TABLE;
        $this->db = new DBix\DBAL($conn);
        $this->db->verbose = false;
        $this->table = 'test1';
    }

    public function testArrayRepeat() {
        $calc = DBix\array_repeat('2', 2);
        $this->assertEquals(array('2','2'), $calc);
    }

    public function testLazyParams() {
        $calc = DBix\lazy_params(1, array(1));
        $expected = array(1);
        $this->assertEquals($expected, $calc);

        $calc = DBix\lazy_params(array(1), array(array(1)));
        $expected = array(1);
        $this->assertEquals($expected, $calc);

        $calc = DBix\lazy_params(2, array(1, 2, 3));
        $expected = array(2, 3);
        $this->assertEquals($expected, $calc);

        $calc = DBix\lazy_params(array(2, 3), array(1, array(2, 3)));
        $expected = array(2, 3);
        $this->assertEquals($expected, $calc);
    }

    public function testPartition() {
        $calc = DBix\partition('test:best', ':');
        $expected = array('test','best');
        $this->assertEquals($expected, $calc);

        $calc = DBix\partition('test', ':');
        $expected = array('test','');
        $this->assertEquals($expected, $calc);
    }

    public function testTableExists() {
        $this->db->execute('DROP TABLE IF EXISTS `?`', $this->table);
        $this->assertEquals(false, $this->db->table_exists($this->table));

        $this->db->execute('CREATE TABLE `?` (i INT)', $this->table);
        $this->assertEquals(true, $this->db->table_exists($this->table));

        $this->db->execute('DROP TABLE IF EXISTS `?`', $this->table);
        $this->assertEquals(false, $this->db->table_exists($this->table));
    }

    public function testTableCreation() {
        $this->db->execute('DROP TABLE IF EXISTS `?`', $this->table);

        $this->db->create_table($this->table, array(
            'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
            'st' => 'VARCHAR(250)',
        ));

        $cols = $this->db->get_columns_names($this->table);
        $this->assertContains('id', $cols);
        $this->assertContains('st', $cols);
        $this->assertNotContains('st1', $cols);
    }

    /**
    * @depends testTableCreation
    */
    public function testMigration() {
        $this->db->execute('ALTER TABLE `?` ADD UNIQUE INDEX (st)', $this->table);

        $this->db->migrate_schema($this->table, array(
            'id' => 'BIGINT AUTO_INCREMENT PRIMARY KEY',
            'st' => 'VARCHAR(250)',
            'st1' => 'VARCHAR(250)',
        ));
        $cols = $this->db->get_columns_names($this->table);
        $this->assertContains('id', $cols);
        $this->assertContains('st', $cols);
        $this->assertContains('st1', $cols);
    }

    /**
    * @depends testMigration
    */
    public function testSelectsUpdates() {
        $table = $this->table;

        $this->db->insert($table,
            array('st' => 'test', 'st1' => 'test')
        );

        $calc = $this->db->query('SELECT * FROM `?`', $table)->fetch_all();
        $expected = array(array('id' => 1, 'st' => 'test', 'st1' => 'test'));
        $this->assertEquals($expected, $calc);

        $this->db->insert_update($table,
            array('st' => 'test', 'st1' => 'test'),
            array('st' => 'test1', 'st1' => 'test1')
        );
    }

    /**
    * @depends testMigration
    */
    public function testSelectsUpdates1() {
        $table = $this->table;
        $calc = $this->db->query('SELECT * FROM `?`', $table)->fetch_all();
        $expected = array(array('id' => 1, 'st' => 'test1', 'st1' => 'test1'));
        $this->assertEquals($expected, $calc);

        $item = $this->db->query('SELECT * FROM `?`', $table)->fetch_row();
        $this->assertEquals(array('id' => 1, 'st' => 'test1', 'st1' => 'test1'), $item);

        $item = $this->db->query('SELECT * FROM `?` WHERE ?', $table, '1=1')->fetch_row();
        $this->assertEquals(array('id' => 1, 'st' => 'test1', 'st1' => 'test1'), $item);

        $q = $this->db->update_where($table,
            array('st' => 'test2', 'st1' => 'test2'),
            array('id' => 1)
        );
        $this->assertEquals(1, $q->affected());
        $item = $this->db->query('SELECT id, st, st1 FROM `?`', $table)->fetch_row();
        $this->assertEquals(array('id' => 1, 'st' => 'test2', 'st1' => 'test2'), $item);
    }

    /**
    * @depends testMigration
    */
    public function test1() {
        $q = $this->db->query('SELECT id, st FROM `?`', $this->table);
        while($item = $q->fetch_active()) {
            $item->st = 1;
            $item->save();
        }

        $item = $this->db->query('SELECT id, st FROM `?`', $this->table)->fetch_row();
        $this->assertEquals(array('id' => 1, 'st' => '1'), $item);
    }


    /**
    * @expectedException DBix\Exception
    */
    public function testException() {
        $this->db->query('SELECT id, st FROM `?`');
    }

    /**
    * @expectedException DBix\Exception
    */
    public function testException2() {
        $this->db->query('SELECT id, st FROM `?`', 1, 2);
    }

    /**
    * @expectedException DBix\Exception
    */
    public function testException3() {
        $this->db->query('SELECT id, st FROM `?`', array(1, 2));
    }

    /**
    * @expectedException DBix\Exception
    */
    public function testException4() {
        $this->db->query('WRONG BUTON')->run();
    }

}



