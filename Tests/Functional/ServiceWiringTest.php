<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional;

use Ipf\NewsBunny\Repository\NewsRepository;
use Ipf\NewsBunny\Repository\PageRepository;
use Ipf\NewsBunny\Repository\SortingRepository;
use Ipf\NewsBunny\Service\DataHandlerFactory;
use Ipf\NewsBunny\Service\PageTreeBuilder;
use Ipf\NewsBunny\Service\SettingsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The repositories of this extension are private services, so the tests build them
 * by hand. This test makes sure the container can build them as well, otherwise the
 * module would fail at runtime while the tests would stay green.
 *
 * The controller is not listed here: Extbase instantiates it with a configured
 * ConfigurationManager and a server request, so it cannot be built standalone. Its
 * dependencies are still covered, because the container compiles the whole graph
 * including the controller when the instance is built.
 */
final class ServiceWiringTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'ipf/news_bunny',
        'georgringer/news',
    ];

    public static function serviceProvider(): array
    {
        return [
            'NewsRepository' => [NewsRepository::class],
            'PageRepository' => [PageRepository::class],
            'SortingRepository' => [SortingRepository::class],
            'DataHandlerFactory' => [DataHandlerFactory::class],
            'SettingsProvider' => [SettingsProvider::class],
            'PageTreeBuilder' => [PageTreeBuilder::class],
        ];
    }

    #[DataProvider('serviceProvider')]
    public function testTheContainerCanBuildTheService(string $class): void
    {
        self::assertInstanceOf($class, $this->get($class));
    }

    public function testTheDataHandlerFactoryReturnsAFreshInstanceEveryTime(): void
    {
        /** @var DataHandlerFactory $factory */
        $factory = $this->get(DataHandlerFactory::class);

        $first = $factory->create();
        $second = $factory->create();

        self::assertNotSame($first, $second, 'a DataHandler carries per operation state');
    }
}
