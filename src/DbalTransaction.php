<?php

namespace TransactionApi\Dbal;

use Doctrine\DBAL\Driver\Connection;
use TransactionApi\TransactionInterface;
use TransactionApi\TransactionScope;
use TransactionApi\Annotation\Transactional;

class DbalTransaction implements TransactionInterface
{
    /**
     * @var Connection
     */
    private $conn;
    
    /**
     * @param Connection conn
     */
    public function __construct(Connection $conn, Transactional $annotation)
    {
        $this->conn = $conn;
        $this->conn->setNestTransactionsWithSavepoints($annotation->txType === TransactionScope::REQUIRES_NEW);
    }
    
    /**
     * {@inheritdoc}
     */
    public function begin()
    {
        $this->conn->beginTransaction();
    }
    
    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $this->conn->commit();
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollback()
    {
        $this->conn->rollback();
    }
    
    /**
     * {@inheritdoc}
     */
    public function inTransaction()
    {
        return $this->depth() > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function depth()
    {
        return $this->conn->getTransactionNestingLevel();
    }
}
