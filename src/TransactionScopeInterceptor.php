<?php

namespace TransactionApi\Dbal;

use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\Di\Exception\NotFound;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\Common\Annotations\Reader;

use TransactionApi\Annotation\Transactional;

class TransactionScopeInterceptor implements MethodInterceptor
{
    /**
     * @var Connection
     */
    private $conn;

    /**
     * @var Reader
     */
    private $reader;
    
    public function __construct(Connection $conn, Reader $reader)
    {
        $this->conn = $conn;
    }
    
    /**
     * {@inheritdoc}
     */
    public function invoke(MethodInvocation $invocation)
    {
        $annotation = $this->extractAnnotation($invocation->getMethod(), Transactional::class);
        
        $scope = new TransactionScope(new DbalTransaction($conn, $annotation), $annotation);
        
        return $scope->runInto(function() use ($invocation) {
            return $invocation->proceed();
        });
    }
    
    private function extractAnnotation(\ReflectionMethod $m, $typeName)
    {
        if (($a = $this->reader->getMethodAnnotation($m, $typeName)) === null) {
            throw new NotFound("Annotation: $typeName is not found.");
        }
        
        return $a;
    }
}
