<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Service;

/**
 * Builds the nested page tree of the module from the flat list of accessible pages.
 *
 * The tree is rendered with plain lists and "details" elements, so it works without
 * any JavaScript and can be folded away by the editor.
 */
final class PageTreeBuilder
{
    /**
     * @param array<int, array<string, mixed>> $pages uid => page information
     * @return array<int, array{uid: int, title: string, path: string, current: bool, children: array<int, array<string, mixed>>}>
     */
    public function build(array $pages, int $currentPageId, int $maxDepth = 6): array
    {
        $children = [];
        foreach ($pages as $uid => $page) {
            $parentUid = (int)$page['pid'];
            // Pages whose parent is not accessible are treated as root pages
            $children[$parentUid][$uid] = $uid;
        }

        $build = function (int $parentUid, int $depth) use (&$build, $children, $pages, $currentPageId, $maxDepth): array {
            $branch = [];
            foreach ($children[$parentUid] ?? [] as $uid) {
                $branch[$uid] = [
                    'uid' => $uid,
                    'title' => (string)$pages[$uid]['title'],
                    'path' => (string)$pages[$uid]['path'],
                    'hidden' => (bool)$pages[$uid]['hidden'],
                    'current' => $uid === $currentPageId,
                    'children' => $depth < $maxDepth ? $build($uid, $depth + 1) : [],
                ];
            }
            // Sort the branches by title, the current page and its ancestors are not needed here
            uasort($branch, static fn(array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

            return $branch;
        };

        return $build(0, 1);
    }

    /**
     * Ids of the given page and all of its children, based on the accessible pages.
     *
     * @param array<int, array<string, mixed>> $pages
     * @return int[]
     */
    public function collectIds(array $pages, int $pageId): array
    {
        if ($pageId <= 0) {
            return [];
        }
        if (!isset($pages[$pageId])) {
            // The page is not accessible, but its children might be reachable through the permissions
            return [$pageId];
        }

        $children = [];
        foreach ($pages as $uid => $page) {
            $children[(int)$page['pid']][] = $uid;
        }

        $ids = [];
        $queue = [$pageId];
        while ($queue !== []) {
            $current = array_shift($queue);
            if (in_array($current, $ids, true)) {
                continue;
            }
            $ids[] = $current;
            foreach ($children[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $ids;
    }
}
