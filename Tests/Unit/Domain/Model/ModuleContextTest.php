<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Unit\Domain\Model;

use Ipf\NewsBunny\Domain\Model\ModuleContext;
use Ipf\NewsBunny\Domain\Model\NewsConstraint;
use Ipf\NewsBunny\Service\SettingsProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * ModuleContext is a plain value object around the resolved request state. The
 * settings are only passed through, so they are stubbed here: the real
 * SettingsProvider reads EXT:news_bunny/Configuration/Module/Settings.php,
 * which is not available in a unit test environment.
 */
final class ModuleContextTest extends UnitTestCase
{
    private function createContext(int $pageId): ModuleContext
    {
        $settings = (new \ReflectionClass(SettingsProvider::class))->newInstanceWithoutConstructor();

        return new ModuleContext(
            pageId: $pageId,
            pages: [],
            pageIds: null,
            settings: $settings,
            constraint: new NewsConstraint(),
        );
    }

    public function testIsPageRestrictedIsFalseOnTheRootLevel(): void
    {
        self::assertFalse($this->createContext(0)->isPageRestricted());
    }

    public function testIsPageRestrictedIsTrueForASelectedPage(): void
    {
        self::assertTrue($this->createContext(42)->isPageRestricted());
    }

    public function testContextExposesTheResolvedState(): void
    {
        $pages = [1 => ['uid' => 1, 'title' => 'News']];
        $pageIds = [1];
        $constraint = new NewsConstraint();
        $constraint->setSearchWord('keep');

        $context = new ModuleContext(
            pageId: 1,
            pages: $pages,
            pageIds: $pageIds,
            settings: (new \ReflectionClass(SettingsProvider::class))->newInstanceWithoutConstructor(),
            constraint: $constraint,
        );

        self::assertSame(1, $context->pageId);
        self::assertSame($pages, $context->pages);
        self::assertSame($pageIds, $context->pageIds);
        self::assertSame($constraint, $context->constraint);
    }

    public function testPageIdsMayBeNullToMeanNoRestriction(): void
    {
        $context = new ModuleContext(
            pageId: 0,
            pages: [],
            pageIds: null,
            settings: (new \ReflectionClass(SettingsProvider::class))->newInstanceWithoutConstructor(),
            constraint: new NewsConstraint(),
        );

        self::assertNull($context->pageIds);
    }
}
