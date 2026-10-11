<?php

namespace Fromholdio\ExternalURLField\Tests;

use Fromholdio\ExternalURLField\ExternalURLField;
use Fromholdio\ExternalURLField\Tests\ExternalURLFieldFormTest\TestController;
use Fromholdio\ExternalURLField\Tests\ExternalURLFieldFormTest\TestRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Dev\FunctionalTest;

/**
 * A saved url edited through a real form submission with an optional
 * `ExternalURLField`.
 *
 * Requests go through the framework's form handling to a test-only controller
 * that saves a test record, so each step checks what the user sees after the
 * submission and what the database holds. Field-level tests cannot show that a
 * redisplayed form, submitted again, leaves the saved url alone.
 */
class ExternalURLFieldFormTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestRecord::class,
    ];

    protected static $extra_controllers = [
        TestController::class,
    ];

    protected static $disable_themes = true;

    protected const SAVED_URL = 'https://www.example.com/existing';

    public static function provideInvalidInput(): array
    {
        return [
            'space in hostname' => ['http://exa mple.com/path'],
            'credentials before an invalid hostname' => ['http://user:pass@exa\\mple.com/path'],
        ];
    }

    /**
     * Invalid input fails validation and is redisplayed, and submitting that
     * redisplayed form again fails the same way, so neither submission changes the
     * saved url. Corrected input and an intentionally empty field are then saved.
     *
     * Each failed submission takes the framework's default path: the form data is
     * stored in the session, the browser is redirected, and the edit page restores
     * that data over the saved record.
     */
    #[DataProvider('provideInvalidInput')]
    public function testInvalidSubmissionsKeepSavedUrlUntilCorrectedOrCleared(string $invalidInput): void
    {
        $record = $this->createSavedRecord();

        $this->get(TestController::singleton()->Link('edit/' . $record->ID));
        $this->assertSame(static::SAVED_URL, $this->getRenderedWebsite());

        $this->submitForm('Form_Form', 'action_doSave', ['Website' => $invalidInput]);
        $this->assertSubmissionFailed($record->ID, $invalidInput);

        //submit the form exactly as it was redisplayed
        $this->submitForm('Form_Form', 'action_doSave');
        $this->assertSubmissionFailed($record->ID, $invalidInput);

        $this->submitForm('Form_Form', 'action_doSave', ['Website' => 'https://user:pass@www.example.org/corrected']);
        $this->assertSubmissionSaved($record->ID, 'https://www.example.org/corrected');

        $this->submitForm('Form_Form', 'action_doSave', ['Website' => '']);
        $this->assertSubmissionSaved($record->ID, null);
    }

    /**
     * An ajax submission that fails validation gets the same form back, rendered from
     * the field that validated it, with no redirect. Resubmitting the rendered input
     * fails again and the saved url is kept.
     */
    #[DataProvider('provideInvalidInput')]
    public function testInvalidAjaxSubmissionsKeepSavedUrl(string $invalidInput): void
    {
        $record = $this->createSavedRecord();
        $headers = ['X-Requested-With' => 'XMLHttpRequest'];
        $formURL = TestController::singleton()->Link('Form');

        $this->post($formURL, ['ID' => $record->ID, 'Website' => $invalidInput, 'action_doSave' => 'Save'], $headers);
        $this->assertSubmissionFailed($record->ID, $invalidInput);

        //submit the rendered input again
        $this->post(
            $formURL,
            ['ID' => $record->ID, 'Website' => $this->getRenderedWebsite(), 'action_doSave' => 'Save'],
            $headers
        );
        $this->assertSubmissionFailed($record->ID, $invalidInput);
    }

    /**
     * The field's own `saveInto()` leaves the record's url unchanged for input it could
     * not rebuild, even when called without validating, so the rejected input is not
     * saved and the saved url is not cleared.
     */
    #[DataProvider('provideInvalidInput')]
    public function testFieldSaveIntoKeepsSavedUrlForRejectedInput(string $invalidInput): void
    {
        $this->assertSame(static::SAVED_URL, $this->saveFieldValueInto($invalidInput));
    }

    public function testFieldSaveIntoSavesCorrectedInput(): void
    {
        $this->assertSame(
            'https://www.example.org/corrected',
            $this->saveFieldValueInto('https://user:pass@www.example.org/corrected')
        );
    }

    public function testFieldSaveIntoClearsForEmptyInput(): void
    {
        $this->assertNull($this->saveFieldValueInto(''));
    }

    // ---------------------------------------------------------------- Helpers

    protected function createSavedRecord(): TestRecord
    {
        $record = TestRecord::create(['Website' => static::SAVED_URL]);
        $record->write();
        return $record;
    }

    /**
     * Set the value on a field, save it into a saved record with the field's own
     * `saveInto()`, write the record and read it back.
     */
    protected function saveFieldValueInto(string $value): ?string
    {
        $record = $this->createSavedRecord();
        $field = ExternalURLField::create('Website');
        $field->setValue($value);
        $field->saveInto($record);
        $record->write();

        return $this->getSavedWebsite($record->ID);
    }

    protected function assertSubmissionFailed(int $recordID, string $redisplayedInput): void
    {
        $this->assertSame(['Please enter a valid URL'], $this->getRenderedMessages());
        $this->assertSame($redisplayedInput, $this->getRenderedWebsite());
        $this->assertSame(static::SAVED_URL, $this->getSavedWebsite($recordID));
    }

    protected function assertSubmissionSaved(int $recordID, ?string $expected): void
    {
        $this->assertSame([], $this->getRenderedMessages());
        $this->assertSame($expected, $this->getSavedWebsite($recordID));
        $this->assertSame((string) $expected, $this->getRenderedWebsite());
    }

    /**
     * The value the most recent page renders in the url input, read the same way
     * `submitForm()` reads it, so it is what the next submission sends unless the
     * test replaces it.
     */
    protected function getRenderedWebsite(): string
    {
        $inputs = $this->mainSession->lastPage()->filterXPath("//input[@id='Form_Form_Website']");
        $this->assertCount(1, $inputs);
        return (string) $inputs->attr('value');
    }

    /**
     * @return string[] The messages the most recent page renders on the url field
     */
    protected function getRenderedMessages(): array
    {
        return $this->mainSession->lastPage()
            ->filterXPath("//div[@id='Form_Form_Website_Holder']//*[contains(concat(' ', @class, ' '), ' message ')]")
            ->each(fn($message) => trim($message->text()));
    }

    /**
     * The url as stored in the database, read through a fresh query
     */
    protected function getSavedWebsite(int $recordID): ?string
    {
        return TestRecord::get()->byID($recordID)->Website;
    }
}
