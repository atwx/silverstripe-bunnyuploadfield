<?php

namespace Atwx\BunnyUploadField\Forms;

use SilverStripe\Forms\FormField;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;

/**
 * FormField for uploading videos directly to Bunny Stream
 * 
 * Uploads bypass PHP upload limits by uploading directly from browser to Bunny CDN
 */
class BunnyVideoUploadField extends FormField
{
    protected $schemaDataType = FormField::SCHEMA_DATA_TYPE_CUSTOM;
    protected $schemaComponent = 'BunnyVideoUploadField';
    
    private $libraryId;
    private $apiKey;
    
    /**
     * @param string $name Field name
     * @param string|null $title Field label
     * @param string|null $libraryId Bunny library ID (defaults to env var)
     * @param string|null $apiKey Bunny API key (defaults to env var)
     */
    public function __construct($name, $title = null, $libraryId = null, $apiKey = null)
    {
        parent::__construct($name, $title);
        
        $this->libraryId = $libraryId ?: Environment::getEnv('BUNNY_LIBRARY_ID');
        $this->apiKey = $apiKey ?: Environment::getEnv('BUNNY_API_KEY');
        
        if (!$this->libraryId || !$this->apiKey) {
            user_error('BUNNY_LIBRARY_ID and BUNNY_API_KEY must be set', E_USER_WARNING);
        }
    }
    
    /**
     * Set the Bunny library ID
     * 
     * @param string $libraryId
     * @return $this
     */
    public function setLibraryId($libraryId)
    {
        $this->libraryId = $libraryId;
        return $this;
    }
    
    /**
     * Get the Bunny library ID
     * 
     * @return string
     */
    public function getLibraryId()
    {
        return $this->libraryId;
    }
    
    /**
     * Set the Bunny API key
     * 
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Get the Bunny API key
     * 
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }
    
    public function Field($properties = [])
    {
        Requirements::javascript('atwx/silverstripe-bunnyuploadfield:client/dist/js/bunny-upload-field.js');
        Requirements::css('atwx/silverstripe-bunnyuploadfield:client/dist/css/bunny-upload-field.css');
        
        return parent::Field($properties);
    }
    
    public function getSchemaStateDefaults()
    {
        $state = parent::getSchemaStateDefaults();
        
        // Get the stored JSON data
        $jsonValue = $this->value;
        $videoData = [];
        $videoId = '';
        $autoplay = false;
        $controls = true;
        $muted = false;
        $loop = false;
        
        if (!empty($jsonValue)) {
            $decoded = json_decode($jsonValue, true);
            if (is_array($decoded)) {
                $videoData = $decoded;
                $videoId = $decoded['guid'] ?? $decoded['videoId'] ?? $decoded['VideoID'] ?? '';
                $autoplay = (bool)($decoded['autoplay'] ?? $decoded['Autoplay'] ?? false);
                $controls = isset($decoded['controls']) ? (bool)$decoded['controls'] : 
                           (isset($decoded['Controls']) ? (bool)$decoded['Controls'] : true);
                $muted = (bool)($decoded['muted'] ?? $decoded['Muted'] ?? false);
                $loop = (bool)($decoded['loop'] ?? $decoded['Loop'] ?? false);
            }
        }
        
        // Pass video ID as value for the React component
        $state['value'] = $videoId;
        $state['data'] = [
            'endpoint' => Director::absoluteURL('api/bunny/create-video'),
            'libraryId' => $this->libraryId,
            'autoplay' => $autoplay,
            'controls' => $controls,
            'muted' => $muted,
            'loop' => $loop,
        ];
        
        return $state;
    }

    /**
     * Save the JSON data into the record
     */
    public function saveInto(\SilverStripe\ORM\DataObjectInterface $record)
    {
        $fieldName = $this->getName();
        $value = $this->value;
        
        // Store the JSON string in the field
        $record->$fieldName = $value;
    }

    /**
     * Set submitted value from form
     */
    public function setSubmittedValue($value, $data = null)
    {
        // The value should now already be JSON from the React component
        if (is_string($value) && !empty($value)) {
            // Check if it's JSON
            if ($value[0] === '{' || $value[0] === '[') {
                $this->value = $value;
            } else {
                // Fallback: treat as video ID and create minimal JSON
                $videoData = [
                    'guid' => $value,
                    'VideoID' => $value,
                    'autoplay' => false,
                    'controls' => true,
                    'muted' => false,
                    'loop' => false,
                ];
                $this->value = json_encode($videoData);
            }
        } else {
            $this->value = '';
        }
        
        return $this;
    }
    
    public function Type()
    {
        return 'bunny-video-upload';
    }
    
    public function getAttributes()
    {
        return array_merge(
            parent::getAttributes(),
            [
                'data-library-id' => $this->libraryId,
                'data-endpoint' => Director::absoluteURL('api/bunny/create-video')
            ]
        );
    }
}
