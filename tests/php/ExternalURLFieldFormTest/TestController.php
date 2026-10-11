<?php

namespace Fromholdio\ExternalURLField\Tests\ExternalURLFieldFormTest;

use Fromholdio\ExternalURLField\ExternalURLField;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;

/**
 * Test-only front-end edit form for a {@see TestRecord}, with an optional
 * `ExternalURLField`.
 *
 * Submissions go through the framework's own form handling: the form validates
 * the submitted data and, on failure, stores the result and the form's data in
 * the session and redirects back, where the form restores them over the saved
 * record. Only a valid submission reaches `doSave()`.
 */
class TestController extends Controller implements TestOnly
{
    private static $url_segment = 'ExternalURLFieldFormTest_TestController';

    private static $allowed_actions = [
        'edit',
        'Form',
    ];

    public function edit(HTTPRequest $request): string
    {
        $record = TestRecord::get()->byID((int) $request->param('ID'));
        if (!$record)
        {
            $this->httpError(404);
        }

        $form = $this->Form();
        $form->loadDataFrom($record);
        //redisplay a failed submission, with its messages, in place of the saved values
        $form->restoreFormState();

        //form actions are relative links, resolved against the base tag as on any page
        return '<!DOCTYPE html><html><head><base href="' . Director::absoluteBaseURL() . '"></head><body>'
            . $form->forTemplate()
            . '</body></html>';
    }

    public function Form(): Form
    {
        return Form::create(
            $this,
            'Form',
            FieldList::create(
                HiddenField::create('ID'),
                ExternalURLField::create('Website')
            ),
            FieldList::create(
                FormAction::create('doSave', 'Save')
            )
        );
    }

    public function doSave(array $data, Form $form): HTTPResponse
    {
        $record = TestRecord::get()->byID((int) $data['ID']);
        $form->saveInto($record);
        $record->write();

        return $this->redirectBack();
    }
}
