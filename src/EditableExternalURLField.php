<?php

namespace SilverStripe\UserForms\Model\EditableFormField;

use BurnBright\ExternalURLField\ExternalURLField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FormField;
use SilverStripe\UserForms\Model\EditableFormField;

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
