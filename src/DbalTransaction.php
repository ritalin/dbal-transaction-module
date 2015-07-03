<?php

namespace TransactionApi\Dbal;

use Doctrine\DBAL\Driver\Connection;

class DbalTransaction implements TransactionInterface
{
    /**
     * @var Connection
     */
    private $conn;
    
    /**
     * @param Connection conn
     */
    public function __constrruct(Connection $conn, Annotation\Transactional $annotation)
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
