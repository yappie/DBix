<?php

/*
 * DBix.php
 *
 * @author  Slava V. <bomboze@gmail.com>
 * @license BSD/MIT Dual Licensed
 * @url     https://github.com/yappie/DBix
 *
 */

require('lib/DBix.php');
define('MYSQL_TABLE', 'test1');

class DbalTest extends PHPUnit_Framework_TestCase {
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

        $this->db->upgrade_schema($this->table, array(
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
    * @depends testSelectsUpdates
    */
    public function test1() {
        $q = $this->db->query('SELECT id, st FROM `?`', $this->table);
        foreach($q->fetch_all_active() as $item) {
            $item->st = 777;
            $item->save();
        }

        $item = $this->db->query('SELECT id, st FROM `?`', $this->table)->fetch_row();
        $this->assertEquals(array('id' => 1, 'st' => '777'), $item);
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

    public function create_st_12_item() {
        return $this->db->insert($this->table, array('st' =>'12'))->last_id();
    }

    public function get_st_12_item($id) {
        $q = $this->db->query('SELECT * FROM `?` WHERE st=?', $this->table, 12);
        $items = $q->fetch_all_active();
        $this->assertEquals(1, count($items));
        $this->assertEquals($items[0]->id, $id);
        return $items[0];
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\Exception
    */
    public function test2() {
        $id = $this->create_st_12_item();
        $item1 = $this->get_st_12_item($id);
        $item2 = $this->get_st_12_item($id);
        $item2->st = 13;

        $item1->delete();
        $item2->save();
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\Exception
    */
    public function test3() {
        $id = $this->create_st_12_item();
        $item1 = $this->get_st_12_item($id);
        $item2 = $this->get_st_12_item($id);

        $item1->delete();
        $item2->delete();
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\Exception
    */
    public function test4() {
        $id = $this->create_st_12_item();
        $item1 = $this->get_st_12_item($id);

        $item1->delete();
        $item1->delete();
    }

    /**
    * @depends testSelectsUpdates
    */
    public function test5() {
        $id = $this->create_st_12_item();
        $item1 = $this->get_st_12_item($id);
        $item1->st1 = '14';
        $item1->save();

        $item1 = $this->get_st_12_item($id);
        $this->assertEquals(14, $item1->st1);
        $item1->delete();
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\Exception
    */
    public function test6() {
        $id = $this->create_st_12_item();
        $item1 = $this->get_st_12_item($id);
        $item1->delete();
        $item1->save();
    }

    /**
    * @depends testSelectsUpdates
    */
    public function test7() {
        $id = $this->create_st_12_item();

        $column = array();
        $items = $this->db->query('SELECT id FROM `?`', $this->table)->fetch_all();

        foreach($items as $item) $col []= $item['id'];

        $col2 = $this->db->query('SELECT id FROM `?`', $this->table)->fetch_column();
        $this->assertEquals($col, $col2);

        $item1 = $this->get_st_12_item($id);
        $item1->delete();
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\StructureException
    */
    public function testNonExistingGet() {
        $items = $this->db->query('select * from `?`', $this->table)->fetch_all_active();
        $fail = $items[0]->non_field;
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\StructureException
    */
    public function testNonExistingSet() {
        $items = $this->db->query('select * from `?`', $this->table)->fetch_all_active();
        $items[0]->non_field = 'fail';
    }

    /**
    * @depends testSelectsUpdates
    * @expectedException DBix\Exception
    */
    public function testBadPrimaryKeyUpdate() {
        $items = $this->db->query('select st from `?`', $this->table)->fetch_all_active();
        $items[0]->st = '13';
        $items[0]->save();
    }

    public function testItemCreation() {
        $cnt = $this->db->query('SELECT * FROM `?` WHERE st=13', $this->table)->run()->num_rows();
        $this->assertEquals(0, $cnt);

        $item = new DBix\Model($this->db, $this->table);
        $this->assertEquals(null, $item->id);

        $item->st = 13;
        $item->save();
        $this->assertNotEquals(null, $item->id);

        $cnt = $this->db->query('SELECT * FROM `?` WHERE st=13', $this->table)->run()->num_rows();
        $this->assertEquals(1, $cnt);

        $item->st1 = 14;
        $item->save();

        $cnt = $this->db->query('SELECT * FROM `?` WHERE st=13', $this->table)->run()->num_rows();
        $this->assertEquals(1, $cnt);

    }

}




