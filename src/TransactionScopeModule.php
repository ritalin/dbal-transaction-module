<?php

namespace TransactionApi\Dbal;

use Ray\Di\AbstractModule;

use TransactionApi\Annotation\Transactional;

class TransactionScopeModule extends AbstractModule
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(Transactional::class),
            [TransactionScopeInterceptor::class]
        );
    }
}
