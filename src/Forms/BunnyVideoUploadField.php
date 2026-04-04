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
    protected $schemaDataType = FormField::SCHEMA_DATA_TYPE_TEXT;
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

    /**
     * Get the API endpoint URL for creating videos
     *
     * @return string
     */
    public function getEndpoint()
    {
        return Director::absoluteURL('api/bunny/create-video');
    }

    public function Field($properties = [])
    {
        return parent::Field($properties);
    }

    public function getSchemaDataDefaults()
    {
        $data = parent::getSchemaDataDefaults();

        $data['data']['endpoint']           = $this->getEndpoint();
        $data['data']['libraryId']          = $this->libraryId;
        $data['data']['searchFormEndpoint'] = Director::absoluteURL('api/bunny/search-form');
        $data['data']['searchEndpoint']     = Director::absoluteURL('api/bunny/search-results');
        $data['data']['cdnHostname']        = $this->getCdnHostname();

        return $data;
    }

    /**
     * Returns the Bunny CDN pull-zone hostname for thumbnail URLs,
     * e.g. "vz-d6fa3ce6-6c4.b-cdn.net".
     *
     * Checks BUNNY_CDN_HOSTNAME env var first (fastest, no API call),
     * then falls back to fetching from the Bunny library API.
     */
    public function getCdnHostname(): string
    {
        $envHostname = Environment::getEnv('BUNNY_CDN_HOSTNAME');
        if ($envHostname) {
            return $envHostname;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "https://video.bunnycdn.com/library/{$this->libraryId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'AccessKey: ' . $this->apiKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return '';
        }

        $lib = json_decode($response, true) ?? [];

        // Bunny API returns PullZone.Hostnames array
        foreach ($lib['PullZone']['Hostnames'] ?? [] as $h) {
            if (!empty($h['Value'])) {
                return $h['Value'];
            }
        }
        // Fallback: PullZone.Name + standard CDN suffix
        if (!empty($lib['PullZone']['Name'])) {
            return $lib['PullZone']['Name'] . '.b-cdn.net';
        }
        // Fallback: top-level PullZoneName field
        if (!empty($lib['PullZoneName'])) {
            return $lib['PullZoneName'] . '.b-cdn.net';
        }

        return '';
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
                'data-endpoint' => $this->getEndpoint()
            ]
        );
    }
}
