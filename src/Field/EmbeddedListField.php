<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\EmbeddedListType;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class EmbeddedListField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param string|false|null $label
     */
    public static function new(string $propertyName, $label = false): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplateName('crud/field/embedded_list')
            ->setFormType(EmbeddedListType::class)
            ->addCssClass('field-embedded-list')
            ->addJsFiles('bundles/easyadmin/form-type-embedded-list.js')
            ->setDefaultColumns(false === $label ? 'col-xs-12' : 'col-md-7 col-xxl-6');
    }
}
