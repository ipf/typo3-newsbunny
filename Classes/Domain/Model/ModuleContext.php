<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Domain\Model;

use Ipf\NewsBunny\Service\SettingsProvider;

/**
 * The resolved state of a NewsBunny request: the page the editor works on,
 * the pages the editor may see and the settings of the module.
 */
final class ModuleContext
{
    /**
     * @param array<int, array<string, mixed>> $pages
     * @param int[]|null $pageIds Restriction for the database queries, null means "no restriction"
     * @param array<int, array<string, mixed>> $pageTree
     * @param array<string, string> $createUrls
     */
    public function __construct(
        public readonly int $pageId,
        public readonly array $pages,
        public readonly ?array $pageIds,
        public readonly array $pageTree,
        public readonly SettingsProvider $settings,
        public readonly NewsConstraint $constraint,
        public readonly array $createUrls = [],
    ) {}

    public function isPageRestricted(): bool
    {
        return $this->pageId > 0;
    }
}
