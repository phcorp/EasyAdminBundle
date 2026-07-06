<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Unit\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\AssociationMapping;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ManyToManyInverseSideMapping;
use Doctrine\ORM\Mapping\ManyToManyOwningSideMapping;
use Doctrine\ORM\Mapping\ToManyAssociationMapping;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EmbeddedListType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class EmbeddedListTypeTest extends TestCase
{
    /**
     * Doctrine ORM 3 mapping objects only carry the property of their own side
     * ($mappedBy on inverse-side mappings, $inversedBy on owning-side ones), so
     * buildView() must not probe the missing one: that raises an "Undefined
     * property" warning, which debug kernels escalate to a 500.
     */
    public function testBuildViewDerivesFilterFieldFromInverseSideMappingWithoutWarning(): void
    {
        $mapping = new ManyToManyInverseSideMapping('works', EmbeddedListTypeTestCategory::class, EmbeddedListTypeTestWork::class);
        $mapping->mappedBy = 'categories';

        [$url, $setParameters] = $this->buildViewUrl($mapping);

        self::assertSame('https://example.com/generated', $url);
        self::assertSame([
            'filters[categories][comparison]' => '=',
            'filters[categories][value]' => '81',
        ], $setParameters);
    }

    public function testBuildViewDerivesFilterFieldFromOwningSideMappingWithoutWarning(): void
    {
        $mapping = new ManyToManyOwningSideMapping('categories', EmbeddedListTypeTestWork::class, EmbeddedListTypeTestCategory::class);
        $mapping->inversedBy = 'works';

        [$url, $setParameters] = $this->buildViewUrl($mapping);

        self::assertSame('https://example.com/generated', $url);
        self::assertSame([
            'filters[works][comparison]' => '=',
            'filters[works][value]' => '81',
        ], $setParameters);
    }

    /**
     * Runs buildView() against a collection carrying the given mapping. PHP
     * warnings and notices are escalated to exceptions, mirroring debug-mode
     * error handling. Returns the generated embedded-list URL together with
     * the parameters set on the URL generator.
     *
     * @return array{string|null, array<string, mixed>}
     */
    private function buildViewUrl(AssociationMapping&ToManyAssociationMapping $mapping): array
    {
        $owner = new class {
            public function getId(): int
            {
                return 81;
            }
        };

        $typeClass = $this->createMock(ClassMetadata::class);
        $typeClass->method('getName')->willReturn(EmbeddedListTypeTestWork::class);

        // PersistentCollection is final: use a real instance (the EM is only
        // needed for lazy initialization, which buildView() never triggers).
        $collection = new PersistentCollection(null, $typeClass, new ArrayCollection());
        $collection->setOwner($owner, $mapping);

        $form = $this->createMock(FormInterface::class);
        $form->method('getData')->willReturn($collection);

        $registry = $this->createMock(AdminControllerRegistryInterface::class);
        $registry->method('findCrudControllerByEntity')->with(EmbeddedListTypeTestWork::class)->willReturn('App\Controller\Admin\WorkCrudController');

        $setParameters = [];
        $urlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $urlGenerator->method('setController')->with('App\Controller\Admin\WorkCrudController')->willReturnSelf();
        $urlGenerator->method('setAction')->with(Action::INDEX)->willReturnSelf();
        $urlGenerator->method('set')->willReturnCallback(function (string $name, mixed $value) use (&$setParameters, $urlGenerator) {
            $setParameters[$name] = $value;

            return $urlGenerator;
        });
        $urlGenerator->method('generateUrl')->willReturn('https://example.com/generated');

        $type = new EmbeddedListType($urlGenerator, $registry);
        $view = new FormView();

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (\E_WARNING === $severity || \E_NOTICE === $severity) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });
        try {
            $type->buildView($view, $form, ['embedded_controller' => null, 'embedded_filter_field' => null]);
        } finally {
            restore_error_handler();
        }

        return [$view->vars['embedded_list_url'], $setParameters];
    }
}

class EmbeddedListTypeTestCategory
{
}

class EmbeddedListTypeTestWork
{
}
