<?php

declare(strict_types=1);

namespace Keboola\GoogleAnalyticsExtractor\Configuration\Node;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class ServiceAccountNode extends ArrayNodeDefinition
{
    public const NODE_NAME = 'service_account';

    public function __construct()
    {
        parent::__construct(self::NODE_NAME);
        $this->init($this->children());
    }

    protected function init(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('#private_key')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('project_id')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('client_email')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('private_key_id')->end()
            ->scalarNode('client_id')->end()
            ->scalarNode('auth_uri')->end()
            ->scalarNode('token_uri')->end()
            ->scalarNode('universe_domain')->end()
            ->scalarNode('auth_provider_x509_cert_url')->end()
            ->scalarNode('client_x509_cert_url')->end()
        ;
    }
}
