<?php

namespace TransactionApi\Dbal;

use Doctrine\DBAL\Driver\Connection;
use Ray\DbalModule\DbalModule;
use Ray\Di\Injector;
use TransactionApi\Annotation\Transactional;
use TransactionApi\TransactionScope;

class DaoStub
{
    public function insert(Connection $conn, $id, $text)
    {
        $conn->insert('todo', ['id' => $id, 'todo' => $text, 'created' => time()]);
    }

    public function select(Connection $conn, $id)
    {
        return $conn->fetchAssoc('select * from todo where id = :id', ['id' => $id]);
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

    private function getConnection()
    {
        $dir = __DIR__;
        $config = "driver=pdo_sqlite&path={$dir}/../var/db/todo.sqlite3";

        $injector = new Injector(new DbalModule($config));

        return $injector->getInstance(Connection::class);
    }

    /**
     * @test
     */
    public function test_commiting_transaction()
    {
        $annotation = new Transactional();

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $this->assertEquals(0, $trans->depth());

        $scope->runInto(function () use ($trans) {
            $this->assertEquals(1, $trans->depth());
        });

        $this->assertEquals(0, $trans->depth());
    }

    /**
     * @test
     */
    public function test_rollbacking_transaction()
    {
        $annotation = new Transactional();

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $this->assertEquals(0, $trans->depth());

        try {
            $scope->runInto(function () use ($trans) {
                $this->assertEquals(1, $trans->depth());

                throw new \LogicException('ERROR');
            });
        } catch (\LogicException $ex) {
        }

        $this->assertEquals(0, $trans->depth());
    }

    /**
     * @test
     */
    public function test_commiting_transaction_insertion_row()
    {
        $annotation = new Transactional();

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

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

    /**
     * @test
     */
    public function test_rollbacking_transaction_insertion_row()
    {
        $annotation = new Transactional();

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $dao = new DaoStub();

        $this->assertTrue($dao->select($conn, 999) === false);

        try {
            $scope->runInto(function () use ($dao, $conn) {
                $dao->insert($conn, 999, 'transaction test');

                $row = $dao->select($conn, 999);
                $this->assertFalse($row === false);
                $this->assertEquals(999, $row['id']);
                $this->assertEquals('transaction test', $row['todo']);

                throw new \LogicException('ERROR');
            });
        } catch (\LogicException $ex) {
        }

        $this->assertTrue($dao->select($conn, 999) === false);
    }

    /**
     * @test
     */
    public function test_commiting_nested_transaction()
    {
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $this->assertEquals(0, $trans->depth());

        $scope->runInto(function () use ($trans, $scope) {
            $this->assertEquals(1, $trans->depth());

            $scope->runInto(function () use ($trans) {
                $this->assertEquals(2, $trans->depth());
            });

            $this->assertEquals(1, $trans->depth());
        });

        $this->assertEquals(0, $trans->depth());
    }

    /**
     * @test
     */
    public function test_rollbacking_nested_transaction()
    {
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $this->assertEquals(0, $trans->depth());

        try {
            $scope->runInto(function () use ($trans, $scope) {
                $this->assertEquals(1, $trans->depth());

                try {
                    $scope->runInto(function () use ($trans) {
                        $this->assertEquals(2, $trans->depth());

                        throw new \LogicException('ERROR');
                    });
                } catch (\LogicException $ex) {
                }

                $this->assertEquals(1, $trans->depth());

                throw new \LogicException('ERROR');
            });
        } catch (\LogicException $ex) {
        }

        $this->assertEquals(0, $trans->depth());
    }

    /**
     * @test
     */
    public function test_commiting_nested_transaction_insertion_row()
    {
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $dao = new DaoStub();

        $this->assertTrue($dao->select($conn, 999) === false);
        $this->assertTrue($dao->select($conn, 888) === false);

        $scope->runInto(function () use ($dao, $conn, $scope) {
            $dao->insert($conn, 999, 'transaction test');

            $row = $dao->select($conn, 999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('transaction test', $row['todo']);

            $this->assertTrue($dao->select($conn, 888) === false);

            $scope->runInto(function () use ($dao, $conn) {
                $dao->insert($conn, 888, 'nested transaction test');

                $row = $dao->select($conn, 888);
                $this->assertFalse($row === false);
                $this->assertEquals(888, $row['id']);
                $this->assertEquals('nested transaction test', $row['todo']);
            });

            $row = $dao->select($conn, 999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('transaction test', $row['todo']);
        });

        $row = $dao->select($conn, 999);
        $this->assertFalse($row === false);
        $this->assertEquals(999, $row['id']);
        $this->assertEquals('transaction test', $row['todo']);

        $row = $dao->select($conn, 888);
        $this->assertFalse($row === false);
        $this->assertEquals(888, $row['id']);
        $this->assertEquals('nested transaction test', $row['todo']);
    }

    /**
     * @test
     */
    public function test_rollbacking_nested_transaction_insertion_row()
    {
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $dao = new DaoStub();

        $this->assertTrue($dao->select($conn, 999) === false);
        $this->assertTrue($dao->select($conn, 888) === false);

        try {
            $scope->runInto(function () use ($dao, $conn, $scope) {
                $dao->insert($conn, 999, 'transaction test');

                $row = $dao->select($conn, 999);
                $this->assertFalse($row === false);
                $this->assertEquals(999, $row['id']);
                $this->assertEquals('transaction test', $row['todo']);

                $this->assertTrue($dao->select($conn, 888) === false);

                $scope->runInto(function () use ($dao, $conn) {
                    $dao->insert($conn, 888, 'nested transaction test');

                    $row = $dao->select($conn, 888);
                    $this->assertFalse($row === false);
                    $this->assertEquals(888, $row['id']);
                    $this->assertEquals('nested transaction test', $row['todo']);

                    throw new \LogicException('ERROR');
                });
            });
        } catch (\LogicException $ex) {
        }

        $this->assertTrue($dao->select($conn, 999) === false);
        $this->assertTrue($dao->select($conn, 888) === false);
    }

    /**
     * @test
     */
    public function test_rollbacking_after_commiting_nested_transaction_insertion_row()
    {
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $dao = new DaoStub();

        $this->assertTrue($dao->select($conn, 999) === false);
        $this->assertTrue($dao->select($conn, 888) === false);

        $scope->runInto(function () use ($dao, $conn, $scope) {
            $dao->insert($conn, 999, 'transaction test');

            $row = $dao->select($conn, 999);
            $this->assertFalse($row === false);
            $this->assertEquals(999, $row['id']);
            $this->assertEquals('transaction test', $row['todo']);

            $this->assertTrue($dao->select($conn, 888) === false);

            try {
                $scope->runInto(function () use ($dao, $conn) {
                    $dao->insert($conn, 888, 'nested transaction test');

                    $row = $dao->select($conn, 888);
                    $this->assertFalse($row === false);
                    $this->assertEquals(888, $row['id']);
                    $this->assertEquals('nested transaction test', $row['todo']);

                    throw new \LogicException('ERROR');
                });
            } catch (\LogicException $ex) {
            }
        });

        $row = $dao->select($conn, 999);
        $this->assertFalse($row === false);
        $this->assertEquals(999, $row['id']);
        $this->assertEquals('transaction test', $row['todo']);

        $this->assertTrue($dao->select($conn, 888) === false);
    }

    /**
     * @test
     */
    public function test_commiting_after_rollback_nested_transaction_insertion_row()
    {
        $annotation = new Transactional();
        $annotation->txType = TransactionScope::REQUIRES_NEW;

        $conn = $this->getConnection();
        $trans = new DbalTransaction($conn, $annotation);
        $scope = new TransactionScope($trans, $annotation);

        $dao = new DaoStub();

        $this->assertTrue($dao->select($conn, 999) === false);
        $this->assertTrue($dao->select($conn, 888) === false);

        try {
            $scope->runInto(function () use ($dao, $conn, $scope) {
                $dao->insert($conn, 999, 'transaction test');

                $row = $dao->select($conn, 999);
                $this->assertFalse($row === false);
                $this->assertEquals(999, $row['id']);
                $this->assertEquals('transaction test', $row['todo']);

                $this->assertTrue($dao->select($conn, 888) === false);

                $scope->runInto(function () use ($dao, $conn) {
                    $dao->insert($conn, 888, 'nested transaction test');

                    $row = $dao->select($conn, 888);
                    $this->assertFalse($row === false);
                    $this->assertEquals(888, $row['id']);
                    $this->assertEquals('nested transaction test', $row['todo']);
                });

                $row = $dao->select($conn, 888);
                $this->assertFalse($row === false);
                $this->assertEquals(888, $row['id']);
                $this->assertEquals('nested transaction test', $row['todo']);

                $row = $dao->select($conn, 999);
                $this->assertFalse($row === false);
                $this->assertEquals(999, $row['id']);
                $this->assertEquals('transaction test', $row['todo']);

                throw new \LogicException('ERROR');
            });
        } catch (\LogicException $ex) {
        }

        $this->assertTrue($dao->select($conn, 999) === false);
        $this->assertTrue($dao->select($conn, 888) === false);
    }
}
