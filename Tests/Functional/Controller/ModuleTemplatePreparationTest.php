<?php

declare(strict_types=1);

namespace Ipf\NewsBunny\Tests\Functional\Controller;

use Ipf\NewsBunny\Controller\NewsBunnyController;
use Ipf\NewsBunny\Tests\Functional\AbstractFunctionalTestCase;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Template\Components\Buttons\Action\ShortcutButton;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\DocHeaderComponent;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The module template preparation has to work on every supported TYPO3 version.
 *
 * DocHeaderComponent::setShortcutContext() only exists since TYPO3 v14, while on
 * TYPO3 v13 the shortcut button has to be added to the button bar. Calling the
 * wrong one of both is a fatal error, so this test covers the branch that the
 * running version selects.
 */
final class ModuleTemplatePreparationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'ipf/news_bunny',
        'georgringer/news',
    ];

    private NewsBunnyController $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // the fixture provides the backend user, the labels come from the extension
        $this->importFixture('page_permissions');
        $backendUser = $this->setUpBackendUser(2);
        $backendUser->fetchGroupData();
        // the module template factory reads the language from the global
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($backendUser);

        // The controller is an Extbase controller, which cannot be built without a
        // configured ConfigurationManager. Only the dependencies of the method under
        // test are needed, so the instance is created without the constructor.
        $this->subject = (new \ReflectionClass(NewsBunnyController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(NewsBunnyController::class, 'languageServiceFactory'))
            ->setValue($this->subject, GeneralUtility::makeInstance(LanguageServiceFactory::class));
    }

    private function createPreparedModuleTemplate(): ModuleTemplate
    {
        $request = (new ServerRequest('https://example.com/typo3/main', 'GET'))
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest(
                new ServerRequest('https://example.com/typo3/main', 'GET')
            ))
            // the module template factory reads the extension from the current route
            ->withAttribute('route', new Route(
                '/typo3/main',
                ['packageName' => 'news_bunny']
            ))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $moduleTemplate = GeneralUtility::makeInstance(ModuleTemplateFactory::class)->create($request);
        (new \ReflectionMethod(NewsBunnyController::class, 'prepareModuleTemplate'))
            ->invoke($this->subject, $moduleTemplate);

        return $moduleTemplate;
    }

    /**
     * The shortcut button of the prepared module template, no matter which TYPO3
     * version put it where.
     */
    private function findShortcutButton(ModuleTemplate $moduleTemplate): ?ShortcutButton
    {
        $docHeader = $moduleTemplate->getDocHeaderComponent();

        if (method_exists($docHeader, 'setShortcutContext')) {
            // TYPO3 v14 keeps it in the doc header
            $property = new \ReflectionProperty(DocHeaderComponent::class, 'automaticShortcutButton');
            $button = $property->getValue($docHeader);

            return $button instanceof ShortcutButton ? $button : null;
        }

        // TYPO3 v13 adds it to the button bar, whose getButtons() has a different
        // signature per version, so the internal state is read directly. The nesting
        // depth of the internal structure differs as well, so it is flattened.
        $property = new \ReflectionProperty(ButtonBar::class, 'buttons');

        return $this->findShortcutIn((array)$property->getValue($docHeader->getButtonBar()));
    }

    /**
     * @param array<mixed> $candidates
     */
    private function findShortcutIn(array $candidates): ?ShortcutButton
    {
        foreach ($candidates as $candidate) {
            if ($candidate instanceof ShortcutButton) {
                return $candidate;
            }
            if (is_array($candidate)) {
                $found = $this->findShortcutIn($candidate);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    public function testPreparingTheModuleTemplateDoesNotFail(): void
    {
        self::assertInstanceOf(ModuleTemplate::class, $this->createPreparedModuleTemplate());
    }

    public function testAShortcutIsOfferedForTheModule(): void
    {
        self::assertNotNull(
            $this->findShortcutButton($this->createPreparedModuleTemplate()),
            'the module offers a shortcut to the backend user'
        );
    }

    public function testTheShortcutPointsAtTheModuleRoute(): void
    {
        $button = $this->findShortcutButton($this->createPreparedModuleTemplate());

        self::assertNotNull($button);
        self::assertSame('web_newsBunny', $button->getRouteIdentifier());
    }
}
