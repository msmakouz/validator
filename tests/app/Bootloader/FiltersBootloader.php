<?php

declare(strict_types=1);

namespace Spiral\Validator\App\Bootloader;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\BinderInterface;
use Spiral\Core\Container;
use Spiral\Core\InterceptableCore;
use Spiral\Filters\Config\FiltersConfig;
use Spiral\Filters\Model\FilterBag;
use Spiral\Filters\Model\FilterInterface;
use Spiral\Filters\Model\FilterProvider;
use Spiral\Filters\Model\FilterProviderInterface;
use Spiral\Filters\Model\Interceptor\Core;
use Spiral\Filters\Model\Interceptor\PopulateDataFromEntityInterceptor;
use Spiral\Filters\Model\Interceptor\ValidateFilterInterceptor;
use Spiral\Filters\InputInterface;
use Spiral\Validator\App\InputScope;

final class FiltersBootloader extends Bootloader implements Container\InjectorInterface, Container\SingletonInterface
{
    protected const SINGLETONS = [
        FilterProviderInterface::class => [self::class, 'initFilterProvider'],
        InputInterface::class => InputScope::class,
    ];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly BinderInterface $binder,
        private readonly ConfiguratorInterface $config
    ) {
    }

    public function init(): void
    {
        $this->binder->bindInjector(FilterInterface::class, self::class);

        $this->config->setDefaults(
            FiltersConfig::CONFIG,
            [
                'interceptors' => [
                    PopulateDataFromEntityInterceptor::class,
                    ValidateFilterInterceptor::class,
                ],
            ]
        );
    }

    public function addInterceptor(string $interceptor): void
    {
        $this->config->modify(
            FiltersConfig::CONFIG,
            new Append('interceptors', null, $interceptor)
        );
    }

    /**
     * @throws \Throwable
     */
    public function createInjection(\ReflectionClass $class, string $context = null): FilterInterface
    {
        /** @var FilterBag $filter */
        return $this->container->get(FilterProviderInterface::class)->createFilter(
            $class->getName(),
            $this->container->get(InputInterface::class)
        );
    }

    private function initFilterProvider(
        Container $container,
        FiltersConfig $config,
        ?EventDispatcherInterface $dispatcher = null
    ): FilterProvider {
        $core = new InterceptableCore(new Core(), $dispatcher);

        foreach ($config->getInterceptors() as $interceptor) {
            $core->addInterceptor($container->get($interceptor));
        }

        return new FilterProvider($container, $container, $core);
    }
}
