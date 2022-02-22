<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Form\Type;

use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Registry\CrudControllerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * The 'embedded list' form type is a special form type used to display an entity
 * relation as a list in a form.
 */
class EmbeddedListType extends AbstractType
{
    /** @var AdminUrlGenerator */
    private $adminUrlGenerator;
    /** @var CrudControllerRegistry */
    private $crudControllerRegistry;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, CrudControllerRegistry $controllerRegistry)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->crudControllerRegistry = $controllerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'embedded_list';
    }

    /**
     * Builds embedded list view.
     *
     * Prerequisites:
     * - ESI MUST be enabled to display the embedded view
     * - Source entity MUST have a single field identifier accessible by method ::getId()
     * - Index controller of the target entity MUST be filterable with source entity
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        /** @var PersistentCollection $data */
        $data = $form->getData();
        if ($data instanceof PersistentCollection) {
            $assoc = $data->getMapping();
            $entityFqcn = $data->getTypeClass()->getName();
            $field = $assoc['inversedBy'] ?: $assoc['mappedBy'];
            $owner = $data->getOwner();
            $ownerId = $owner->getId();
        } else {
            $field = lcfirst($form->getParent()->getName());
            $owner = $form->getParent()->getData();
            $ownerField = $form->getName();
            $ownerId = $owner->getId();
            $refClass = new \ReflectionClass($owner);
            $refProperty = $refClass->getProperty($ownerField);
            if (preg_match('/@var (?:(?:Array)Collection<([^>]*)>|(\w+)\[])/', $refProperty->getDocComment(), $matches)) {
                $entityFqcn = class_exists($matches[1]) ? $matches[1] : sprintf('%s\\%s', $refClass->getNamespaceName(), $matches[1]);
            }
        }

        $absoluteUrl = $this->adminUrlGenerator
            ->setController($this->crudControllerRegistry->findCrudFqcnByEntityFqcn($entityFqcn))
            ->setAction(Action::INDEX)
            ->unset(EA::ENTITY_ID)
            ->unset(EA::PAGE)
            ->set(EA::TEMPLATE_BLOCK, 'main')
            ->set("filters[$field][comparison]", '=')
            ->set("filters[$field][value]", "{$ownerId}")
            ->removeReferrer()
            ->generateUrl();
        $parts = parse_url($absoluteUrl);
        $relativePath = $parts['path'].(empty($parts['query']) ? '' : '?'.$parts['query']).(empty($parts['fragment']) ? '' : '#'.$parts['fragment']);
        $view->vars['embedded_list_url'] = $relativePath;
    }
}
