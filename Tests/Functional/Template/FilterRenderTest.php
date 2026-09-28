<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Template;

use Ipf\NewsBunny\Domain\Model\NewsConstraint;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the filter panel with the view the module renders with.
 *
 * The panel folds away with a "details" element, and the marker the browser draws for
 * a "summary" is placed in the box of the list item, which is a block in front of the
 * content of the row. As soon as the row starts with a block itself, the heading of
 * this panel, the marker ends up on a line of its own above the row instead of beside
 * the title.
 */
final class FilterRenderTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importFixture('page_permissions');
        $backendUser = $this->setUpBackendUser(2);
        $backendUser->fetchGroupData();
        // the module template factory reads the language from the global
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);
    }

    public function testTheMarkerOfThePanelSitsInTheRowOfItsTitle(): void
    {
        $html = $this->render(true);

        // the marker of the browser is switched off, it is the one of the markup now
        self::assertStringContainsString('<summary class="panel-heading" style="list-style: none">', $html);
        // and the marker of the markup is inside the heading, so it cannot end up on
        // a line of its own above the row
        self::assertMatchesRegularExpression(
            '#<h5 class="panel-title">\s*<svg[^>]*aria-hidden="true"[^>]*>\s*<path#',
            $html,
            'the marker is the first child of the heading'
        );
    }

    public function testTheTitleAndItsIconStayTogether(): void
    {
        $html = $this->render(true);

        // a row of its own would drop the space between the icon and the title
        self::assertStringContainsString('<span class="ms-1">', $html);
        self::assertStringNotContainsString('panel-title d-flex', $html);
    }

    public function testTheFormIsFoldedAwayByDefault(): void
    {
        self::assertStringNotContainsString('<details class="panel panel-default" open', $this->render(false));
    }

    public function testTheFormIsOpenedWhenAFilterIsSet(): void
    {
        self::assertStringContainsString('<details class="panel panel-default" open', $this->render(true));
    }

    private function render(bool $open): string
    {
        // the module renders the partial with arguments="{_all}", so everything the
        // partial reads is handed in as the variables of the partial
        return (string)$this->templateView()->renderPartial('NewsBunny/Filter', null, [
            'constraint' => new NewsConstraint(),
            'pageId' => 0,
            'formValues' => [
                'searchWord' => '',
                'manualDateStart' => '',
                'manualDateStop' => '',
                'timeRestriction' => '',
                'topNewsRestriction' => '',
                'archived' => '',
                'hidden' => '',
                'language' => (string)NewsConstraint::LANGUAGE_ALL,
                'perPage' => '20',
                'categoryConjunction' => 'or',
                'categories' => [],
            ],
            'filter' => [
                'open' => $open,
                'enabled' => [
                    'searchWord' => false,
                    'timeRestriction' => false,
                    'topNewsRestriction' => false,
                    'archived' => false,
                    'hidden' => false,
                    'language' => false,
                    'categories' => false,
                    'categoryConjunction' => false,
                    'includeSubCategories' => false,
                    'recursive' => false,
                ],
            ],
            'categoryOptions' => [],
        ]);
    }

    private function templateView(): FluidViewAdapter
    {
        $serverRequest = (new ServerRequest('https://example.com/typo3/main', 'GET'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest(
                new ServerRequest('https://example.com/typo3/main', 'GET')
            ))
            ->withAttribute('route', new Route('/typo3/main', ['packageName' => 'news_bunny']))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $moduleTemplate = GeneralUtility::makeInstance(ModuleTemplateFactory::class)->create($serverRequest);
        self::assertInstanceOf(ModuleTemplate::class, $moduleTemplate);

        $view = (new \ReflectionProperty(ModuleTemplate::class, 'view'))->getValue($moduleTemplate);
        // the partial resolves its short translation keys through the request of the
        // Extbase controller, which is where the extension name of the labels comes from
        $view->getRenderingContext()->setAttribute(
            ServerRequestInterface::class,
            GeneralUtility::makeInstance(
                ExtbaseRequest::class,
                $serverRequest->withAttribute(
                    'extbase',
                    (new ExtbaseRequestParameters())->setControllerExtensionName('NewsBunny')
                )
            )
        );

        return $view;
    }
}
