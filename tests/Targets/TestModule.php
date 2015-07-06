<?php

namespace TransactionApi\Dbal\Targets;

use Ray\Di\AbstractModule;
use Ray\DbalModule\DbalModule;
use TransactionApi\Dbal\TransactionScopeModule;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Annotations\AnnotationReader;

class TestModule extends AbstractModule
{
    /**
     * @var string
     */
    private $config;
    
    public function __construct($config)
    {
        $this->config = $config;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->install(new DbalModule($this->config));
        $this->install(new TransactionScopeModule());
        
        $this->bind(Reader::class)->to(AnnotationReader::class);
        $this->bind(TodoModel::class)->to(TodoModel::class);
        
    }
}
