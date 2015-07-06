<?php

namespace TransactionApi\Dbal;

use Ray\Di\Injector;
use Ray\DbalModule\DbalModule;

use Doctrine\DBAL\Driver\Connection;

use TransactionApi\TransactionScope;
use TransactionApi\Annotation\Transactional;

class DaoStub
{
    public function insert(Connection $conn, $id, $text)
    {
        $conn->insert('todo', ['id' => $id, 'todo' => $text, 'created' => time()]);
    }
    
    public function select(Connection $conn, $id)
    {
        return $conn->fetchAssoc('select * from todo where id = :id', ['id' => $id])
        ;
    }
}

class TransactionTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $dir = __DIR__;
        copy(
            realpath("{$dir}/../var/db/todo.sqlite3.tmpl"),
            realpath("{$dir}/../var/db/todo.sqlite3")
        );
    }
    
    /**
     * @test
     */
    public function test_commiting_transaction()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new DbalModule($config));
        $conn = $injector->getInstance(Connection::class);
        
        $annotation = new Transactional();
        
        $tran = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($tran, $annotation);

        $this->assertEquals(0, $tran->depth());
        
        $scope->runInto(function () use ($tran) {
            $this->assertEquals(1, $tran->depth());
        });
        
        $this->assertEquals(0, $tran->depth());
    }
    
    /**
     * @test
     */
    public function test_commiting_transaction_insertion_row()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new DbalModule($config));
        $conn = $injector->getInstance(Connection::class);
        
        $annotation = new Transactional();
        
        $tran = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($tran, $annotation);
        
        $dao = new DaoStub();
        $row = $dao->select($conn, 999);

        $this->assertTrue($row === false);
        
        $scope->runInto(function () use ($dao, $conn) {
            $dao->insert($conn, 999, 'transaction test');
            
            $row = $dao->select($conn, 999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('transaction test', $row['todo']);
        });
        
        $row = $dao->select($conn, 999);
        $this->assertFalse($row === false);
        $this->assertEquals(999, $row['id']);
        $this->assertEquals('transaction test', $row['todo']);
    }
}
