<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Service;

/**
 * Builds the nested page tree of the module from the flat list of accessible pages.
 *
 * The tree is rendered with plain lists and "details" elements, so it works without
 * any JavaScript and can be folded away by the editor.
 *
 * Two things are not visible in the flat list and are therefore resolved here:
 *
 * - A translated page is a record of its own with the pid of the original page. All
 *   records of one page are folded into a single node, the one of the default
 *   language, so the editor sees the page once. The news of a translation still
 *   belong to that page and are counted on the node.
 * - The accessible pages need not start at the root of the page tree, an editor
 *   mounted on a sub page only sees a part of it. Such a page becomes a root of
 *   the module tree, otherwise the whole tree would be empty.
 */
final class PageTreeBuilder
{
    /**
     * @param array<int, array<string, mixed>> $pages uid => page information
     * @param array<int, int> $newsCounts uid of a page => news records on it, as returned by NewsRepository
     * @return array<int, array{
     *     uid: int,
     *     title: string,
     *     path: string,
     *     hidden: bool,
     *     current: bool,
     *     newsCount: int,
     *     newsTotal: int,
     *     children: array<int, array<string, mixed>>
     * }>
     */
    public function build(array $pages, int $currentPageId, array $newsCounts = [], int $maxDepth = 6): array
    {
        $families = $this->groupLanguageVariants($pages);
        $tree = $this->groupIntoTree($pages, $families);
        $roots = $tree['roots'];
        $children = $tree['children'];

        $currentUid = $families['representative'][$currentPageId] ?? 0;
        $ownCounts = [];
        foreach ($families['variants'] as $uid => $variantUids) {
            $ownCounts[$uid] = $this->countOwn($variantUids, $newsCounts);
        }

        $build = function (array $uids, int $depth, array $ancestors) use (&$build, $children, $pages, $families, $ownCounts, $currentUid, $maxDepth): array {
            $branch = [];
            foreach ($uids as $uid) {
                if (isset($ancestors[$uid])) {
                    // guard against loops in a broken page tree
                    continue;
                }
                $branch[$uid] = [
                    'uid' => $uid,
                    'title' => (string)$pages[$uid]['title'],
                    'path' => (string)$pages[$uid]['path'],
                    'hidden' => $this->isHidden($families['variants'][$uid], $pages),
                    'current' => $uid === $currentUid,
                    'newsCount' => $ownCounts[$uid],
                    'newsTotal' => $ownCounts[$uid],
                    'children' => $depth < $maxDepth
                        ? $build($children[$uid] ?? [], $depth + 1, $ancestors + [$uid => true])
                        : [],
                ];
            }
            uasort($branch, static fn(array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

            return $branch;
        };

        $tree = $build($roots, 1, []);
        $totals = $this->collectSubtreeTotals($roots, $children, $ownCounts);

        return $this->applySubtreeTotals($tree, $totals);
    }

    /**
     * Ids of the given page and all of its children, based on the accessible pages.
     *
     * The result contains the translations of every page as well, because the news
     * may be stored on a translated page record.
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

        $families = $this->groupLanguageVariants($pages);
        $children = $this->groupIntoTree($pages, $families)['children'];

        $ids = [];
        $queue = [$families['representative'][$pageId]];
        while ($queue !== []) {
            $representativeUid = array_shift($queue);
            if (in_array($representativeUid, $ids, true)) {
                continue;
            }
            foreach ($families['variants'][$representativeUid] as $variantUid) {
                $ids[] = $variantUid;
            }
            foreach ($children[$representativeUid] ?? [] as $childUid) {
                $queue[] = $childUid;
            }
        }

        return $ids;
    }

    /**
     * Arranges the accessible pages in the tree of the module, one entry per page and
     * not per language variant.
     *
     * A record may point to a translation of its parent page, so the parent is
     * resolved through the group of the page and not taken over as it is.
     *
     * @param array<int, array<string, mixed>> $pages
     * @param array{representative: array<int, int>, variants: array<int, int[]>} $families
     * @return array{roots: int[], children: array<int, int[]>}
     */
    private function groupIntoTree(array $pages, array $families): array
    {
        $children = [];
        $roots = [];
        foreach (array_keys($families['variants']) as $uid) {
            $parentUid = $families['representative'][(int)$pages[$uid]['pid']] ?? 0;
            if ($parentUid === 0 || $parentUid === $uid) {
                // The parent page is not accessible, so this page is a root of the
                // module tree. Without that an editor mounted on a sub page would
                // not see any page at all.
                $roots[] = $uid;
                continue;
            }
            $children[$parentUid][] = $uid;
        }

        return ['roots' => $roots, 'children' => $children];
    }

    /**
     * Groups the language variants of a page, so a page is represented by a single
     * record. The representative is the record of the default language, because that
     * is the page the module links to.
     *
     * A translation whose record of the default language is not accessible forms a
     * group of its own, so the page is still shown instead of disappearing.
     *
     * @param array<int, array<string, mixed>> $pages
     * @return array{representative: array<int, int>, variants: array<int, int[]>}
     */
    private function groupLanguageVariants(array $pages): array
    {
        $groups = [];
        foreach ($pages as $uid => $page) {
            $language = (int)($page['language'] ?? 0);
            $key = $language === 0 ? $uid : (int)($page['l10nParent'] ?? 0);
            $groups[$key === 0 ? $uid : $key][] = $uid;
        }

        $representative = [];
        $variants = [];
        foreach ($groups as $key => $group) {
            sort($group, SORT_NUMERIC);
            $head = isset($pages[$key]) ? $key : $group[0];
            foreach ($group as $uid) {
                $representative[$uid] = $head;
            }
            $variants[$head] = $group;
        }

        return ['representative' => $representative, 'variants' => $variants];
    }

    /**
     * A page is marked as hidden if any of its language variants is hidden, because
     * the records of a hidden translation are not published either.
     *
     * @param int[] $variants
     * @param array<int, array<string, mixed>> $pages
     */
    private function isHidden(array $variants, array $pages): bool
    {
        foreach ($variants as $variantUid) {
            if ((bool)($pages[$variantUid]['hidden'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int[] $variants
     * @param array<int, int> $newsCounts
     */
    private function countOwn(array $variants, array $newsCounts): int
    {
        $count = 0;
        foreach ($variants as $variantUid) {
            $count += $newsCounts[$variantUid] ?? 0;
        }

        return $count;
    }

    /**
     * News records of a node and of all its descendants, over the whole accessible
     * tree. The rendered tree is cut off at "maxDepth", the total of a node must not
     * depend on that cut off.
     *
     * @param int[] $roots
     * @param array<int, int[]> $children
     * @param array<int, int> $ownCounts
     * @return array<int, int>
     */
    private function collectSubtreeTotals(array $roots, array $children, array $ownCounts): array
    {
        $totals = [];
        $collect = function (int $uid) use (&$collect, &$totals, $children, $ownCounts): int {
            if (isset($totals[$uid])) {
                return $totals[$uid];
            }
            // assigned before the children are added, which is the guard against loops
            $totals[$uid] = $ownCounts[$uid] ?? 0;
            foreach ($children[$uid] ?? [] as $childUid) {
                $totals[$uid] += $collect($childUid);
            }

            return $totals[$uid];
        };
        foreach ($roots as $uid) {
            $collect($uid);
        }

        return $totals;
    }

    /**
     * @param array<int, array<string, mixed>> $tree
     * @param array<int, int> $totals
     * @return array<int, array<string, mixed>>
     */
    private function applySubtreeTotals(array $tree, array $totals): array
    {
        foreach ($tree as $uid => $node) {
            $node['newsTotal'] = $totals[$uid] ?? $node['newsCount'];
            $node['children'] = $this->applySubtreeTotals($node['children'], $totals);
            $tree[$uid] = $node;
        }

        return $tree;
    }
}
