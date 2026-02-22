<?php

namespace Atwx\BunnyUploadField\Tests;

use Atwx\BunnyUploadField\ORM\FieldType\DBBunnyVideo;
use SilverStripe\Dev\SapphireTest;

class DBBunnyVideoTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function makeField(string $value = '', string $libraryId = 'test-library'): DBBunnyVideo
    {
        $field = new DBBunnyVideo('VideoID');
        $field->setLibraryId($libraryId);
        if ($value) {
            $field->setValue($value);
        }
        return $field;
    }

    public function testExistsWithValue(): void
    {
        $field = $this->makeField('abc-123');
        $this->assertTrue($field->exists());
    }

    public function testExistsWithoutValue(): void
    {
        $field = $this->makeField();
        $this->assertFalse($field->exists());
    }

    public function testGetEmbedURL(): void
    {
        $field = $this->makeField('video-guid');
        $this->assertEquals(
            'https://iframe.mediadelivery.net/test-library/video-guid',
            $field->getEmbedURL()
        );
    }

    public function testGetEmbedURLWithoutValue(): void
    {
        $field = $this->makeField();
        $this->assertNull($field->getEmbedURL());
    }

    public function testGetEmbedURLWithoutLibraryId(): void
    {
        $field = new DBBunnyVideo('VideoID');
        $field->setLibraryId('');
        $field->setValue('video-guid');
        $this->assertNull($field->getEmbedURL());
    }

    public function testGetThumbnailURL(): void
    {
        $field = $this->makeField('video-guid');
        $this->assertEquals(
            'https://vz-test-library.b-cdn.net/video-guid/thumbnail.jpg',
            $field->getThumbnailURL()
        );
    }

    public function testGetThumbnailURLWithoutValue(): void
    {
        $field = $this->makeField();
        $this->assertNull($field->getThumbnailURL());
    }

    public function testGetEmbedHTMLContainsIframe(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML();

        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('iframe.mediadelivery.net/test-library/video-guid', $html);
    }

    public function testGetEmbedHTMLDefaultDimensions(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML();

        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('height: 360px', $html);
    }

    public function testGetEmbedHTMLWithAutoplay(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML(autoplay: true);

        $this->assertStringContainsString('autoplay=true', $html);
    }

    public function testGetEmbedHTMLWithMuted(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML(muted: true);

        $this->assertStringContainsString('muted=true', $html);
    }

    public function testGetEmbedHTMLWithoutControls(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML(controls: false);

        $this->assertStringContainsString('controls=false', $html);
    }

    public function testGetEmbedHTMLWithLoop(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML(loop: true);

        $this->assertStringContainsString('loop=true', $html);
    }

    public function testGetEmbedHTMLNoParamsWhenDefaults(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML();

        // Keine Query-Parameter bei Standardwerten
        $this->assertStringNotContainsString('?', $html);
    }

    public function testGetEmbedHTMLWithNumericWidth(): void
    {
        $field = $this->makeField('video-guid');
        $html = $field->getEmbedHTML(width: 800);

        $this->assertStringContainsString('width: 800px', $html);
    }

    public function testGetEmbedHTMLWithoutValueReturnsNull(): void
    {
        $field = $this->makeField();
        $this->assertNull($field->getEmbedHTML());
    }

    public function testForTemplateWithValue(): void
    {
        $field = $this->makeField('video-guid');
        $this->assertNotEmpty($field->forTemplate());
    }

    public function testForTemplateWithoutValueReturnsEmptyString(): void
    {
        $field = $this->makeField();
        $this->assertSame('', $field->forTemplate());
    }

    public function testEmbedHTMLEscapesSpecialChars(): void
    {
        $field = $this->makeField('video-guid');
        $field->setLibraryId('<script>alert(1)</script>');
        $html = $field->getEmbedHTML();

        $this->assertStringNotContainsString('<script>', $html);
    }
}
