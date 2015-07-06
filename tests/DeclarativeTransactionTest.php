<?php

namespace TransactionApi\Dbal;

use Doctrine\DBAL\Driver\Connection;
use Ray\Di\Injector;
use TransactionApi\Annotation\Transactional;
use TransactionApi\TransactionScope;

use TransactionApi\Dbal\Targets\TestModule;
use TransactionApi\Dbal\Targets\TodoModel;

class DeclarativeTransactionTest extends \PHPUnit_Framework_TestCase
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
    public function test_commiting_declarative_transaction()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new TestModule($config));
        
        $model = $injector->getInstance(TodoModel::class);
        
        $this->assertTrue($model->select(999) === false);
        
        $model->insertRecord(999, 'test memo');
        
        $row = $model->select(999);
        $this->assertFalse($row === false);
        $this->assertEquals(999, $row['id']);
        $this->assertEquals('test memo', $row['todo']);
    }
    
    /**
     * @test
     */
    public function test_rollbacking_declarative_transaction()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new TestModule($config));
        
        $model = $injector->getInstance(TodoModel::class);
        
        $this->assertTrue($model->select(999) === false);
        
        try {
            $model->insertFail(999, 'test memo', function ($m) {
                $row = $m->select(999);
                $this->assertFalse($row === false);
                $this->assertEquals(999, $row['id']);
                $this->assertEquals('test memo', $row['todo']);
            });
        }
        catch (\LogicException $ex) {
        }
        
        $this->assertTrue($model->select(999) === false);
    }
    
    /**
     * @test
     */
    public function test_commiting_ignore_nested_transaction()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new TestModule($config));
        
        $annotation = new Transactional();
        
        $trans = new DbalTransaction($injector->getInstance(Connection::class), $annotation);
        $scope = new TransactionScope($trans, $annotation);
        
        $model = $injector->getInstance(TodoModel::class);
        
        $scope->runInto(function () use ($model) {
            $this->assertTrue($model->select(999) === false);
            
            $model->insertRecordIgnoringNestedTransaction(999, 'test memo');
            
            $row = $model->select(999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('test memo', $row['todo']);
        });
        
        $row = $model->select(999);
        $this->assertFalse($row === false);
        $this->assertEquals(999, $row['id']);
        $this->assertEquals('test memo', $row['todo']);
    }
    
    /**
     * @test
     */
    public function test_rollbacking_ignore_nested_transaction()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new TestModule($config));
        
        $annotation = new Transactional();
        
        $trans = new DbalTransaction($injector->getInstance(Connection::class), $annotation);
        $scope = new TransactionScope($trans, $annotation);
        
        $model = $injector->getInstance(TodoModel::class);
        
        $scope->runInto(function () use ($model) {
            $this->assertTrue($model->select(999) === false);
            $this->assertTrue($model->select(888) === false);
            
            $model->insertRecordIgnoringNestedTransaction(999, 'test memo');
                
            try {
                $model->insertFailIgnoringNestedTransaction(888, 'failed', function ($m) {
                    $row = $m->select(888);
                    $this->assertFalse($row === false);
                    $this->assertEquals(888, $row['id']);
                    $this->assertEquals('failed', $row['todo']);
                });
            } catch (\LogicException $ex) {
            }
            
            // Rollback is not executed.
            $row = $model->select(999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('test memo', $row['todo']);
            
            $row = $model->select(888);
            $this->assertFalse($row === false);
            $this->assertEquals(888, $row['id']);
            $this->assertEquals('failed', $row['todo']);
        });
    }
    
    /**
     * @test
     */
    public function test_rollbacking_nested_transaction()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";
        
        $injector = new Injector(new TestModule($config));
        
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;
        
        $trans = new DbalTransaction($injector->getInstance(Connection::class), $annotation);
        $scope = new TransactionScope($trans, $annotation);
        
        $model = $injector->getInstance(TodoModel::class);
        
        $scope->runInto(function () use ($model) {
            $this->assertTrue($model->select(999) === false);
            $this->assertTrue($model->select(888) === false);
                
            try {
                $model->insertRecordAllowingNestedTransaction(999, 'test memo');
            
                $model->insertFailAllowingNestedTransaction(888, 'failed', function ($m) {
                    $row = $m->select(888);
                    $this->assertFalse($row === false);
                    $this->assertEquals(888, $row['id']);
                    $this->assertEquals('failed', $row['todo']);
                });
            } catch (\LogicException $ex) {
            }
            
            $row = $model->select(999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('test memo', $row['todo']);
            
            // For latter transaction, Rollback is not executed.
            $this->assertTrue($model->select(888) === false);
        });
        
        $row = $model->select(999);
        $this->assertFalse($row === false);
        $this->assertEquals(999, $row['id']);
        $this->assertEquals('test memo', $row['todo']);
        
        // For latter transaction, Rollback is not executed.
        $this->assertTrue($model->select(888) === false);
    }
}
