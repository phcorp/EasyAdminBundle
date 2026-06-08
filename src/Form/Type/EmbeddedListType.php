<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Form\Type;

use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The 'embedded list' form type is a special form type used to display an entity
 * relation as a list in a form.
 */
class EmbeddedListType extends AbstractType
{
    private AdminUrlGenerator $adminUrlGenerator;
    private AdminControllerRegistryInterface $crudControllerRegistry;

    public function __construct(AdminUrlGenerator $adminUrlGenerator, AdminControllerRegistryInterface $controllerRegistry)
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->crudControllerRegistry = $controllerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'embedded_list';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // For collections that are NOT a real Doctrine association (virtual /
        // computed getters) the embed target cannot be derived from the mapping,
        // so the field can provide it explicitly: the target CRUD controller and
        // the name of its filter that narrows the list down to the owning entity.
        $resolver->setDefaults([
            'embedded_controller' => null,
            'embedded_filter_field' => null,
        ]);
        $resolver->setAllowedTypes('embedded_controller', ['null', 'string']);
        $resolver->setAllowedTypes('embedded_filter_field', ['null', 'string']);
    }

    /**
     * Builds embedded list view.
     *
     * Prerequisites:
     * - ESI MUST be enabled to display the embedded view
     * - Source entity MUST have a single field identifier accessible by method ::getId()
     * - Index controller of the target entity MUST be filterable with source entity
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $form->getData();

        if ($data instanceof PersistentCollection) {
            // Real Doctrine association: derive the target CRUD, the filter field
            // (the inverse association name) and the owning entity from the mapping.
            $assoc = $data->getMapping();
            $entity = $data->getOwner();
            $field = $assoc->inversedBy ?: $assoc->mappedBy;
            $controllerFqcn = $this->crudControllerRegistry->findCrudControllerByEntity($data->getTypeClass()->getName());
        } else {
            // Virtual/computed collection (no mapping, possibly a null property):
            // use the explicitly configured target controller + filter field, with
            // the owning entity taken from the parent (root) form data.
            $controllerFqcn = $options['embedded_controller'];
            $field = $options['embedded_filter_field'];
            $entity = $form->getParent()?->getData();
        }

        if (null === $controllerFqcn || null === $field || !\is_object($entity) || !method_exists($entity, 'getId')) {
            $view->vars['embedded_list_url'] = null;

            return;
        }

        $view->vars['embedded_list_url'] = $this->adminUrlGenerator
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->set(EA::TEMPLATE_BLOCK, 'main')
            ->set("filters[$field][comparison]", '=')
            ->set("filters[$field][value]", (string) $entity->getId())
            ->generateUrl();
    }
}
