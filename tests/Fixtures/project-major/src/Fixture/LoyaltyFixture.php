<?php

declare(strict_types=1);

namespace App\Fixture;

use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class LoyaltyFixture extends AbstractFixture implements FixtureInterface
{
    public function getName(): string
    {
        return 'loyalty_tiers';
    }

    public function load(array $options): void
    {
        // Load loyalty tier fixture data
        foreach ($options['tiers'] as $tierData) {
            // Create tier entities
        }
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('tiers')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->end()
                            ->integerNode('min_points')->isRequired()->end()
                            ->floatNode('discount_percent')->defaultValue(0.0)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
