<?php

namespace App\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Core\Environment;
use Atwx\BunnyUploadField\Forms\BunnyVideoUploadField;

/**
 * Example: Multi-Tenancy with Customer-Specific Bunny Libraries
 *
 * This example shows how to give each customer their own Bunny library
 * so videos are completely separated per customer.
 */
class Customer extends DataObject
{
    private static $table_name = 'Customer';

    private static $db = [
        'Name' => 'Varchar(255)',
        'BunnyLibraryID' => 'Varchar(50)',
        'BunnyAPIKey' => 'Varchar(255)'
    ];

    private static $has_many = [
        'Videos' => CustomerVideo::class
    ];

    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Name', 'Kundenname'),
            TextField::create('BunnyLibraryID', 'Bunny Library ID')
                ->setDescription('Wird automatisch erstellt wenn leer'),
            TextField::create('BunnyAPIKey', 'Bunny API Key')
                ->setDescription('Optional: Spezifischer API-Key für diesen Kunden')
        ]);

        // Show videos tab only if customer is saved
        if ($this->ID) {
            $fields->addFieldsToTab('Root.Videos', [
                GridField::create(
                    'Videos',
                    'Videos',
                    $this->Videos(),
                    GridFieldConfig_RecordEditor::create()
                )
            ]);
        }

        return $fields;
    }

    /**
     * Create Bunny library for this customer on first save
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->BunnyLibraryID && !$this->isInDB()) {
            $this->createBunnyLibrary();
        }
    }

    /**
     * Create a dedicated Bunny library for this customer
     */
    protected function createBunnyLibrary()
    {
        // This would call Bunny API to create a new library
        // For now, this is just a placeholder

        $apiKey = Environment::getEnv('BUNNY_API_KEY');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.bunny.net/videolibrary',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'Name' => 'Customer: ' . $this->Name,
                'ReplicationRegions' => ['DE'] // EU only
            ]),
            CURLOPT_HTTPHEADER => [
                'AccessKey: ' . $apiKey,
                'Content-Type: application/json'
            ]
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['Id'])) {
            $this->BunnyLibraryID = $result['Id'];
        }
    }
}

/**
 * Customer Video DataObject
 */
class CustomerVideo extends DataObject
{
    private static $table_name = 'CustomerVideo';

    private static $db = [
        'Title' => 'Varchar(255)',
        'BunnyVideoID' => 'Varchar(100)',
        'Description' => 'Text'
    ];

    private static $has_one = [
        'Customer' => Customer::class
    ];

    private static $summary_fields = [
        'Title',
        'Created.Nice' => 'Hochgeladen'
    ];

    public function getCMSFields()
    {
        $fields = FieldList::create(TabSet::create('Root'));

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Titel'),

            // Use customer's specific Bunny library
            BunnyVideoUploadField::create('BunnyVideoID', 'Video')
                ->setLibraryId($this->Customer()->BunnyLibraryID)
                ->setApiKey($this->Customer()->BunnyAPIKey ?: Environment::getEnv('BUNNY_API_KEY')),

            TextareaField::create('Description', 'Beschreibung')
        ]);

        return $fields;
    }

    public function getVideoEmbed()
    {
        if (!$this->BunnyVideoID || !$this->Customer()->BunnyLibraryID) {
            return null;
        }

        return sprintf(
            '<iframe src="https://iframe.mediadelivery.net/%s/%s" loading="lazy" style="border: 0; width: 100%%; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>',
            $this->Customer()->BunnyLibraryID,
            $this->BunnyVideoID
        );
    }
}
