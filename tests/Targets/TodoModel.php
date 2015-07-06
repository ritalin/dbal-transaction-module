<?php

namespace TransactionApi\Dbal\Targets;

use Doctrine\DBAL\Driver\Connection;

use TransactionApi\TransactionScope;
use TransactionApi\Annotation\Transactional;

class TodoModel
{
    /**
     * @var Connection
     */
    private $conn;
    
    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }
    
    private function InsertInternal($id, $text)
    {
        $this->conn->insert('todo', ['id' => $id, 'todo' => $text, 'created' => time()]);
    }
    
    /**
     * @Transactional
     */
    public function insertRecord($id, $text)
    {
        $this->insertInternal($id, $text);
    }
    
    /**
     * @Transactional
     */
    public function insertFail($id, $text, callable $fn)
    {
        $this->insertInternal($id, $text);
        $fn($this);
        
        throw new \LogicException('Error occured duaring insertion');
    }
    
    /**
     * @Transactional(txType=TransactionScope::REQUIRES)
     */
    public function insertRecordIgnoringNestedTransaction($id, $text)
    {
        $this->insertInternal($id, $text);
    }
    
    /**
     * @Transactional(txType=TransactionScope::REQUIRES)
     */
    public function insertFailIgnoringNestedTransaction($id, $text, callable $fn)
    {
        $this->insertInternal($id, $text);
        $fn($this);
        
        throw new \LogicException('Error occured duaring insertion');
    }
    
    /**
     * @Transactional(txType=TransactionScope::REQUIRES_NEW)
     */
    public function insertRecordAllowingNestedTransaction($id, $text)
    {
        $this->insertInternal($id, $text);
    }
    
    /**
     * @Transactional(txType=TransactionScope::REQUIRES_NEW)
     */
    public function insertFailAllowingNestedTransaction($id, $text, callable $fn)
    {
        $this->insertInternal($id, $text);
        $fn($this);
        
        throw new \LogicException('Error occured duaring insertion');
    }
    
    public function select($id)
    {
        return $this->conn->fetchAssoc('select * from todo where id = :id', ['id' => $id]);
    }
}
