<?php

namespace Atwx\BunnyUploadField\ORM\FieldType;

use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\Core\Environment;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Database field for storing Bunny video IDs
 * Provides helper methods for embedding videos
 */
class DBBunnyVideo extends DBVarchar
{
    private static $casting = [
        'EmbedHTML' => 'HTMLFragment',
        'EmbedURL' => 'Varchar',
        'ThumbnailURL' => 'Varchar'
    ];
    
    /**
     * Get the library ID for this video
     * Can be overridden per instance
     */
    protected $libraryId;
    
    public function __construct($name = null, $options = [])
    {
        parent::__construct($name, 255, $options);
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
     * Get the embed URL for this video
     * 
     * @return string|null
     */
    public function getEmbedURL()
    {
        if (!$this->value || !$this->libraryId) {
            return null;
        }
        
        return sprintf(
            'https://iframe.mediadelivery.net/%s/%s',
            $this->libraryId,
            $this->value
        );
    }
    
    /**
     * Get the thumbnail URL for this video
     * 
     * @return string|null
     */
    public function getThumbnailURL()
    {
        if (!$this->value || !$this->libraryId) {
            return null;
        }
        
        return sprintf(
            'https://vz-%s.b-cdn.net/%s/thumbnail.jpg',
            $this->libraryId,
            $this->value
        );
    }
    
    /**
     * Get embed HTML for this video
     * 
     * @param int $width Width in pixels or percentage
     * @param int $height Height in pixels
     * @param bool $autoplay Auto-play video
     * @param bool $controls Show controls (default true)
     * @param bool $muted Start muted
     * @param bool $loop Loop video
     * @return string|null
     */
    public function getEmbedHTML(
        $width = '100%',
        $height = 360,
        $autoplay = false,
        $controls = true,
        $muted = false,
        $loop = false
    ) {
        $url = $this->getEmbedURL();
        
        if (!$url) {
            return null;
        }
        
        // Add query parameters
        $params = [];
        if ($autoplay) $params[] = 'autoplay=true';
        if (!$controls) $params[] = 'controls=false';
        if ($muted) $params[] = 'muted=true';
        if ($loop) $params[] = 'loop=true';
        
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
        return $this->getEmbedHTML() ?? '';
    }
    
    /**
     * Check if video exists
     * 
     * @return bool
     */
    public function exists(): bool
    {
        return !empty($this->value);
    }
}
