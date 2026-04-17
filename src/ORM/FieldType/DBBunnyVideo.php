<?php

namespace Atwx\BunnyUploadField\ORM\FieldType;

use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\Core\Environment;
use SilverStripe\Model\ModelData;

/**
 * Database field for storing complete Bunny video JSON data
 * Stores the full video object from Bunny Stream API as JSON
 * Provides helper methods for accessing video properties and embedding videos
 */
class DBBunnyVideo extends DBText
{
    private static $casting = [
        'EmbedHTML' => 'HTMLFragment',
        'EmbedURL' => 'Varchar',
        'ThumbnailURL' => 'Varchar',
        'VideoID' => 'Varchar',
        'Title' => 'Varchar',
        'LibraryId' => 'Varchar',
        'QueryParams' => 'Varchar',
    ];

    /**
     * Get the library ID for this video
     * Can be overridden per instance
     */
    protected $libraryId;

    /**
     * Cached decoded JSON data
     */
    protected $videoData = null;

    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, $options);
        $this->libraryId = Environment::getEnv('BUNNY_LIBRARY_ID');
    }

    /**
     * Set custom library ID
     */
    public function setLibraryId($id)
    {
        $this->libraryId = $id;
        return $this;
    }

    /**
     * Get library ID
     */
    public function getLibraryId()
    {
        return $this->libraryId;
    }

    /**
     * Get the decoded video data from JSON
     * 
     * @return array|null
     */
    protected function getVideoData()
    {
        if ($this->videoData === null && !empty($this->value)) {
            $decoded = json_decode($this->value, true);
            $this->videoData = is_array($decoded) ? $decoded : [];
        }
        return $this->videoData ?? [];
    }

    /**
     * Set video data from array or JSON string
     * 
     * @param array|string $data
     * @return $this
     */
    public function setVideoData($data)
    {
        if (is_string($data)) {
            $this->value = $data;
            $this->videoData = json_decode($data, true);
        } elseif (is_array($data)) {
            $this->value = json_encode($data);
            $this->videoData = $data;
        } else {
            $this->value = '';
            $this->videoData = [];
        }
        return $this;
    }

    /**
     * Get video ID (guid from Bunny)
     */
    public function getVideoID()
    {
        $data = $this->getVideoData();
        return $data['guid'] ?? $data['videoId'] ?? $data['VideoID'] ?? '';
    }

    /**
     * Get video title
     */
    public function getTitle()
    {
        $data = $this->getVideoData();
        return $data['title'] ?? '';
    }

    /**
     * Get autoplay setting
     */
    public function getAutoplay()
    {
        $data = $this->getVideoData();
        return (bool)($data['autoplay'] ?? $data['Autoplay'] ?? false);
    }

    /**
     * Get controls setting
     */
    public function getControls()
    {
        $data = $this->getVideoData();
        return isset($data['controls']) ? (bool)$data['controls'] : 
               (isset($data['Controls']) ? (bool)$data['Controls'] : true);
    }

    /**
     * Get muted setting
     */
    public function getMuted()
    {
        $data = $this->getVideoData();
        return (bool)($data['muted'] ?? $data['Muted'] ?? false);
    }

    /**
     * Get loop setting
     */
    public function getLoop()
    {
        $data = $this->getVideoData();
        return (bool)($data['loop'] ?? $data['Loop'] ?? false);
    }

    /**
     * Get query parameters for iframe URL
     * Always includes all 4 parameters with true/false values
     * 
     * @return string Query parameters (without leading ?)
     */
    public function getQueryParams()
    {
        $params = [
            'autoplay=' . ($this->getAutoplay() ? 'true' : 'false'),
            'controls=' . ($this->getControls() ? 'true' : 'false'),
            'muted=' . ($this->getMuted() ? 'true' : 'false'),
            'loop=' . ($this->getLoop() ? 'true' : 'false'),
        ];
        
        return implode('&', $params);
    }

    /**
     * Get the embed URL for this video
     *
     * @return string|null
     */
    public function getEmbedURL()
    {
        $videoId = $this->getVideoID();
        if (!$videoId || !$this->libraryId) {
            return null;
        }

        return sprintf(
            'https://iframe.mediadelivery.net/embed/%s/%s',
            $this->libraryId,
            $videoId
        );
    }

    /**
     * Get the thumbnail URL for this video
     *
     * @return string|null
     */
    public function getThumbnailURL()
    {
        $videoId = $this->getVideoID();
        if (!$videoId || !$this->libraryId) {
            return null;
        }

        return sprintf(
            'https://vz-%s.b-cdn.net/%s/thumbnail.jpg',
            $this->libraryId,
            $videoId
        );
    }

    /**
     * Get embed HTML for this video
     *
     * @param int|string $width Width in pixels or percentage
     * @param int $height Height in pixels
     * @param bool|null $autoplay Auto-play video (null = use stored value)
     * @param bool|null $controls Show controls (null = use stored value)
     * @param bool|null $muted Start muted (null = use stored value)
     * @param bool|null $loop Loop video (null = use stored value)
     * @return string|null
     */
    public function getEmbedHTML(
        $width = '100%',
        $height = 360,
        $autoplay = null,
        $controls = null,
        $muted = null,
        $loop = null
    ) {
        $url = $this->getEmbedURL();

        if (!$url) {
            return null;
        }

        // Use stored values if not explicitly provided
        $autoplay = $autoplay ?? $this->getAutoplay();
        $controls = $controls ?? $this->getControls();
        $muted = $muted ?? $this->getMuted();
        $loop = $loop ?? $this->getLoop();

        // Add query parameters
        $params = [];
        $params[] = $autoplay ? 'autoplay=true' : 'autoplay=false';
        $params[] = $controls ? 'controls=true' : 'controls=false';
        $params[] = $muted ? 'muted=true' : 'muted=false';
        $params[] = $loop ? 'loop=true' : 'loop=false';

        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }

        // Format width
        $widthAttr = is_numeric($width) ? $width . 'px' : $width;

        return sprintf(
            '<iframe src="%s" loading="lazy" style="border: 0; width: %s; height: %dpx; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen="true"></iframe>',
            htmlspecialchars($url),
            htmlspecialchars($widthAttr),
            (int)$height
        );
    }

    /**
     * Get embed HTML with default settings (for templates)
     *
     * @return string|null
     */
    public function EmbedHTML()
    {
        return $this->getEmbedHTML();
    }

    /**
     * Get the embed HTML for template rendering
     *
     * @return string
     */
    public function forTemplate(): string
    {
        return $this->renderWith('Atwx\BunnyUploadField\ORM\FieldType\DBBunnyVideo');
    }

    /**
     * Check if video exists
     *
     * @return bool
     */
    public function exists(): bool
    {
        return !empty($this->getVideoID());
    }

    /**
     * Set value - accepts JSON string or array
     *
     * @param mixed $value
     * @param ModelData|array|null $record
     * @param bool $markChanged
     * @return static
     */
    public function setValue(mixed $value, ModelData|array|null $record = null, bool $markChanged = true): static
    {
        if (is_string($value)) {
            // If it's a JSON string, store it directly
            if (!empty($value) && ($value[0] === '{' || $value[0] === '[')) {
                $this->value = $value;
                $this->videoData = null; // Reset cache
            }
            // If it's just a video ID, create minimal JSON
            elseif (!empty($value)) {
                $this->setVideoData(['guid' => $value]);
            } else {
                $this->value = '';
                $this->videoData = null;
            }
        } elseif (is_array($value)) {
            // Store array as JSON
            $this->setVideoData($value);
        } else {
            $this->value = '';
            $this->videoData = null;
        }

        if ($markChanged) {
            $this->isChanged = true;
        }

        return $this;
    }
}
