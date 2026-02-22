<?php

namespace Atwx\BunnyUploadField\Tests;

use Atwx\BunnyUploadField\Forms\BunnyVideoUploadField;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Control\Controller;

class BunnyVideoUploadFieldTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeField(string $name = 'Video', string $title = 'Video'): BunnyVideoUploadField
    {
        // Env vars werden via .ddev/config.yaml gesetzt (BUNNY_LIBRARY_ID, BUNNY_API_KEY)
        return new BunnyVideoUploadField($name, $title);
    }

    public function testType(): void
    {
        $field = $this->makeField();
        $this->assertEquals('bunny-video-upload', $field->Type());
    }

    public function testGetLibraryId(): void
    {
        $field = new BunnyVideoUploadField('Video', 'Video', 'my-library', 'my-key');
        $this->assertEquals('my-library', $field->getLibraryId());
    }

    public function testGetApiKey(): void
    {
        $field = new BunnyVideoUploadField('Video', 'Video', 'my-library', 'my-key');
        $this->assertEquals('my-key', $field->getApiKey());
    }

    public function testSetLibraryIdReturnsSelf(): void
    {
        $field = $this->makeField();
        $result = $field->setLibraryId('new-library');
        $this->assertSame($field, $result);
        $this->assertEquals('new-library', $field->getLibraryId());
    }

    public function testSetApiKeyReturnsSelf(): void
    {
        $field = $this->makeField();
        $result = $field->setApiKey('new-key');
        $this->assertSame($field, $result);
        $this->assertEquals('new-key', $field->getApiKey());
    }

    public function testGetAttributesContainsLibraryId(): void
    {
        $field = new BunnyVideoUploadField('Video', 'Video', 'my-library', 'my-key');
        $attrs = $field->getAttributes();

        $this->assertArrayHasKey('data-library-id', $attrs);
        $this->assertEquals('my-library', $attrs['data-library-id']);
    }

    public function testGetAttributesContainsEndpoint(): void
    {
        $field = $this->makeField();
        $attrs = $field->getAttributes();

        $this->assertArrayHasKey('data-endpoint', $attrs);
        $this->assertStringContainsString('api/bunny/create-video', $attrs['data-endpoint']);
    }

    public function testGetSchemaStateDefaultsContainsEndpoint(): void
    {
        $field = $this->makeField();
        $state = $field->getSchemaStateDefaults();

        $this->assertArrayHasKey('data', $state);
        $this->assertArrayHasKey('endpoint', $state['data']);
        $this->assertStringContainsString('api/bunny/create-video', $state['data']['endpoint']);
    }

    public function testGetSchemaStateDefaultsContainsLibraryId(): void
    {
        $field = new BunnyVideoUploadField('Video', 'Video', 'my-library', 'my-key');
        $state = $field->getSchemaStateDefaults();

        $this->assertEquals('my-library', $state['data']['libraryId']);
    }

    public function testGetSchemaStateDefaultsContainsVideoId(): void
    {
        $field = new BunnyVideoUploadField('Video', 'Video', 'my-library', 'my-key');
        $field->setValue('some-video-guid');
        $state = $field->getSchemaStateDefaults();

        $this->assertEquals('some-video-guid', $state['data']['videoId']);
    }

    public function testGetSchemaStateDefaultsVideoIdNullWhenEmpty(): void
    {
        $field = $this->makeField();
        $state = $field->getSchemaStateDefaults();

        $this->assertNull($state['data']['videoId']);
    }

    public function testFallsBackToEnvVars(): void
    {
        // BUNNY_LIBRARY_ID und BUNNY_API_KEY sind via ddev gesetzt
        $field = new BunnyVideoUploadField('Video', 'Video');
        $this->assertNotEmpty($field->getLibraryId());
        $this->assertNotEmpty($field->getApiKey());
    }
}
