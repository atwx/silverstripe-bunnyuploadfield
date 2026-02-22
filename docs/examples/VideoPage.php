<?php

namespace App\PageTypes;

use Page;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use Atwx\BunnyUploadField\Forms\BunnyVideoUploadField;
use Atwx\BunnyUploadField\ORM\FieldType\DBBunnyVideo;

/**
 * Example Page with Bunny video upload
 *
 * This example shows how to integrate Bunny Stream video uploads
 * into your SilverStripe page types or DataObjects.
 */
class VideoPage extends Page
{
    private static $db = [
        'BunnyVideoID' => DBBunnyVideo::class,
        'VideoTitle' => 'Varchar(255)',
        'VideoDescription' => 'Text'
    ];

    private static $summary_fields = [
        'Title',
        'VideoTitle',
        'HasVideo'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Add video upload field
        $fields->addFieldsToTab('Root.Main', [
            BunnyVideoUploadField::create('BunnyVideoID', 'Video hochladen')
                ->setDescription('Laden Sie ein Video direkt zu Bunny Stream hoch'),

            TextField::create('VideoTitle', 'Video Titel'),
            TextareaField::create('VideoDescription', 'Video Beschreibung')
        ], 'Content'); // Add before the Content field

        return $fields;
    }

    /**
     * Check if page has a video
     *
     * @return string
     */
    public function getHasVideo()
    {
        return $this->BunnyVideoID ? 'Ja' : 'Nein';
    }

    /**
     * Get video embed HTML for templates
     *
     * @return string|null
     */
    public function VideoEmbed()
    {
        return $this->dbObject('BunnyVideoID')->getEmbedHTML();
    }
}
