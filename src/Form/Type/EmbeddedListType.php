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
    private AdminUrlGenerator $adminUrlGenerator;
    private CrudControllerRegistry $crudControllerRegistry;

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
        $assoc = $data->getMapping();
        $entity = $data->getOwner();
        $field = $assoc['inversedBy'] ?: $assoc['mappedBy'];

        $view->vars['embedded_list_url'] = $this->adminUrlGenerator
            ->setController($this->crudControllerRegistry->findCrudFqcnByEntityFqcn($data->getTypeClass()->getName()))
            ->setAction(Action::INDEX)
            ->set(EA::TEMPLATE_BLOCK, 'main')
            ->set("filters[$field][comparison]", '=')
            ->set("filters[$field][value]", "{$entity->getId()}")
            ->removeReferrer()
            ->generateUrl();
    }
}
