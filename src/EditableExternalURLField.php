<?php

namespace BurnBright\ExternalURLField;

use SilverStripe\Forms\FormField;
use SilverStripe\UserForms\Model\EditableFormField;

if (!class_exists(EditableFormField::class)) {
    return;
}

/**
 * EditableEmailField
 *
 * Allow users to define a validating editable email field for a UserDefinedForm
 *
 * @package userforms
 */
class EditableExternalURLField extends EditableFormField
{
    private static $singular_name = 'URL Field';

    private static $plural_name = 'URL Fields';

    private static $has_placeholder = true;

    private static $table_name = 'EditableExternalURLField';

    public function getSetsOwnError()
    {
        return true;
    }

    public function getFormField()
    {
        $field = ExternalURLField::create($this->Name, $this->Title ?: false, $this->Default)
            ->setFieldHolderTemplate(EditableFormField::class . '_holder')
            ->setTemplate(EditableFormField::class);

        $this->doUpdateFormField($field);

        return $field;
    }

    /**
     * Updates a formfield with the additional metadata specified by this field
     *
     * @param FormField $field
     */
    protected function updateFormField($field)
    {
        parent::updateFormField($field);

        $field->setAttribute('data-rule-url', true);
    }
}
