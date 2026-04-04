<?php

namespace Atwx\BunnyUploadField\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;
use SilverStripe\Security\SecurityToken;

/**
 * API Controller for Bunny Upload Field operations
 */
class BunnyAPIController extends Controller
{
    private static $url_handlers = [
        'POST create-video'   => 'createVideo',
        'POST webhook'        => 'handleWebhook',
        'GET video/$VideoID'  => 'getVideoInfo',
        'GET search-form'     => 'searchForm',
        'GET search-results'  => 'searchResults',
        'GET videos'          => 'searchVideos',
    ];

    private static $allowed_actions = [
        'createVideo',
        'handleWebhook',
        'getVideoInfo',
        'searchForm',
        'searchResults',
        'searchVideos',
    ];
    
    /**
     * Creates a new video entry in Bunny and returns upload URL
     * 
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function createVideo(HTTPRequest $request)
    {
        // Verify CSRF token
        if (!$request->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }
        
        $data = json_decode($request->getBody(), true);
        
        if (!$data) {
            return $this->jsonError('Invalid JSON', 400);
        }
        
        $title = $data['title'] ?? 'Untitled';
        $libraryId = $data['libraryId'] ?? Environment::getEnv('BUNNY_LIBRARY_ID');
        $apiKey = Environment::getEnv('BUNNY_API_KEY');
        
        if (!$libraryId || !$apiKey) {
            return $this->jsonError('Bunny credentials not configured', 500);
        }
        
        // Create video via Bunny API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://video.bunnycdn.com/library/{$libraryId}/videos",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'title' => $title,
                'collectionId' => $data['collectionId'] ?? null
            ]),
            CURLOPT_HTTPHEADER => [
                'AccessKey: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->getLogger()->error('Bunny API error: ' . $response);
            return $this->jsonError('Failed to create video: ' . $error, 500);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['guid'])) {
            return $this->jsonError('Invalid response from Bunny', 500);
        }
        
        $videoId = $result['guid'];
        $uploadUrl = "https://video.bunnycdn.com/library/{$libraryId}/videos/{$videoId}";
        
        $this->getLogger()->info("Created Bunny video: {$videoId}");
        
        return $this->jsonResponse([
            'videoId' => $videoId,
            'uploadUrl' => $uploadUrl,
            'libraryId' => $libraryId,
            'apiKey' => $apiKey
        ]);
    }
    
    /**
     * Handles Bunny Stream webhooks
     * 
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function handleWebhook(HTTPRequest $request)
    {
        $data = json_decode($request->getBody(), true);
        
        if (!$data) {
            return $this->jsonError('Invalid webhook data', 400);
        }
        
        $this->getLogger()->info('Bunny webhook received', $data);
        
        // Handle different event types
        $eventType = $data['EventType'] ?? null;
        
        switch ($eventType) {
            case 'video.uploaded':
                $this->handleVideoUploaded($data);
                break;
                
            case 'video.encoded':
                $this->handleVideoEncoded($data);
                break;
                
            case 'video.encoding.failed':
                $this->handleEncodingFailed($data);
                break;
                
            default:
                $this->getLogger()->info('Unknown webhook event: ' . $eventType);
        }
        
        return $this->jsonResponse(['status' => 'received']);
    }
    
    /**
     * Get video information
     * 
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function getVideoInfo(HTTPRequest $request)
    {
        $videoId = $request->param('VideoID');
        
        if (!$videoId) {
            return $this->jsonError('Video ID required', 400);
        }
        
        $libraryId = Environment::getEnv('BUNNY_LIBRARY_ID');
        $apiKey = Environment::getEnv('BUNNY_API_KEY');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://video.bunnycdn.com/library/{$libraryId}/videos/{$videoId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'AccessKey: ' . $apiKey,
                'Accept: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return $this->jsonError('Video not found', 404);
        }
        
        return HTTPResponse::create()
            ->setStatusCode(200)
            ->addHeader('Content-Type', 'application/json')
            ->setBody($response);
    }
    
    /**
     * Returns a search form HTML snippet for use in CmsModalSearch
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function searchForm(HTTPRequest $request)
    {
        if (!$request->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }

        $libraryId = Environment::getEnv('BUNNY_LIBRARY_ID');
        $cdnHostname = $this->getCdnHostname($libraryId);

        $html = '<form method="GET" data-cms-form-target>'
            . '<div class="input-group mb-3">'
            . '<input type="text" name="q" class="form-control" placeholder="Search by title..." autofocus>'
            . '<button class="btn btn-primary" type="submit">Search</button>'
            . '</div>'
            . '<input type="hidden" name="libraryId" value="' . htmlspecialchars($libraryId, ENT_QUOTES) . '">'
            . '<input type="hidden" name="cdnHostname" value="' . htmlspecialchars($cdnHostname, ENT_QUOTES) . '">'
            . '</form>';

        return HTTPResponse::create()
            ->setStatusCode(200)
            ->addHeader('Content-Type', 'text/html; charset=utf-8')
            ->setBody($html);
    }

    /**
     * Get CDN hostname for thumbnails.
     *
     * First checks the BUNNY_CDN_HOSTNAME environment variable.
     * If not set, fetches from Bunny API:
     * 1. Library API returns PullZoneId
     * 2. PullZone API returns Hostnames array with CDN hostname
     *
     * @param string|null $libraryId
     * @return string
     */
    protected function getCdnHostname($libraryId = null): string
    {
        $libraryId = $libraryId ?: Environment::getEnv('BUNNY_LIBRARY_ID');
        $apiKey = Environment::getEnv('BUNNY_API_KEY');

        // Check environment variable first (fastest)
        $envHostname = Environment::getEnv('BUNNY_CDN_HOSTNAME');
        if ($envHostname) {
            return $envHostname;
        }

        if (!$libraryId || !$apiKey) {
            return '';
        }

        // Fetch from Library API to get the PullZoneId
        $library = $this->getLibraryDetails($libraryId, $apiKey);
        if (!$library || !isset($library['PullZoneId'])) {
            return '';
        }

        // Use original API key to fetch PullZone details
        $pullZoneId = $library['PullZoneId'];
        $pullZone = $this->getPullZoneDetails($pullZoneId, $apiKey);
        if (!$pullZone || !isset($pullZone['Hostnames'][0]['Value'])) {
            return '';
        }

        return $pullZone['Hostnames'][0]['Value'];
    }

    /**
     * Get library details from Bunny API
     *
     * @param string $libraryId
     * @param string $apiKey
     * @return array|null
     */
    protected function getLibraryDetails($libraryId, $apiKey)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.bunny.net/videolibrary/{$libraryId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'AccessKey: ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            return json_decode($response, true);
        }
        return null;
    }

    /**
     * Get videos from Bunny library using the library's API key
     *
     * @param string $libraryId
     * @param string $libraryApiKey
     * @param string $query
     * @param int $page
     * @param int $itemsPerPage
     * @return array|null
     */
    protected function getLibraryVideos($libraryId, $libraryApiKey, $query = '', $page = 1, $itemsPerPage = 24)
    {
        $params = http_build_query([
            'search' => $query,
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
            'orderBy' => 'date',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://video.bunnycdn.com/library/{$libraryId}/videos?{$params}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'AccessKey: ' . $libraryApiKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            return json_decode($response, true);
        }
        return null;
    }

    /**
     * Get PullZone details from Bunny API
     *
     * @param int $pullZoneId
     * @param string $apiKey
     * @return array|null
     */
    protected function getPullZoneDetails($pullZoneId, $apiKey)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.bunny.net/pullzone/{$pullZoneId}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'AccessKey: ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            return json_decode($response, true);
        }
        return null;
    }

    /**
     * Lists/searches videos from the Bunny library and returns selectable HTML results
     * Used by CmsModalSearch - returns HTML with data-cms-select attributes
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function searchResults(HTTPRequest $request)
    {
        if (!$request->isAjax()) {
            return $this->jsonError('Invalid request', 400);
        }

        $libraryId = Environment::getEnv('BUNNY_LIBRARY_ID');
        $apiKey = Environment::getEnv('BUNNY_API_KEY');

        if (!$libraryId || !$apiKey) {
            return HTTPResponse::create()
                ->setStatusCode(200)
                ->addHeader('Content-Type', 'text/html; charset=utf-8')
                ->setBody('<div class="alert alert-danger">Bunny credentials not configured.</div>');
        }

        // Get CDN hostname (from env var or API)
        $cdnHostname = $this->getCdnHostname($libraryId);

        $query = trim($request->getVar('q') ?? '');

        // Get videos using library's API key
        $library = $this->getLibraryDetails($libraryId, $apiKey);
        $libraryApiKey = $library['ApiKey'] ?? $apiKey;
        $videosResponse = $this->getLibraryVideos($libraryId, $libraryApiKey, $query, 1, 24);

        if (!$videosResponse || empty($videosResponse['items'])) {
            if (!$videosResponse) {
                return HTTPResponse::create()
                    ->setStatusCode(200)
                    ->addHeader('Content-Type', 'text/html; charset=utf-8')
                    ->setBody('<div class="alert alert-danger">Error loading videos from Bunny.</div>');
            }
            return HTTPResponse::create()
                ->setStatusCode(200)
                ->addHeader('Content-Type', 'text/html; charset=utf-8')
                ->setBody('<div class="text-muted">No videos found.</div>');
        }

        $items = $videosResponse['items'];

        // Build HTML results with data-cms-select attributes
        $html = '<div class="bunny-search-results" data-cms-results-target>';
        foreach ($items as $video) {
            $videoId = htmlspecialchars($video['guid'] ?? '', ENT_QUOTES);
            $title = htmlspecialchars($video['title'] ?? 'Untitled', ENT_QUOTES);
            $thumbnailFileName = htmlspecialchars($video['thumbnailFileName'] ?? 'thumbnail.jpg', ENT_QUOTES);
            $thumbnail = $cdnHostname
                ? "https://{$cdnHostname}/{$videoId}/{$thumbnailFileName}"
                : "https://video.bunnycdn.com/library/{$libraryId}/videos/{$videoId}/{$thumbnailFileName}";

            $html .= '<div class="bunny-result-item" data-cms-select=\'{"videoId":"'.$videoId.'","title":"'.$title.'"}\'>'
                . '<div class="bunny-result-thumb" style="position:relative;aspect-ratio:16/9;background:#1a1a2e;overflow:hidden;margin-bottom:8px;">'
                . '<img src="'.$thumbnail.'" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" onerror="this.style.display=\'none\';this.previousElementSibling.style.display=\'flex\';this.previousElementSibling.style.alignItems=\'center\';this.previousElementSibling.style.justifyContent=\'center\';this.previousElementSibling.style.fontSize=\'2rem\';this.previousElementSibling.style.color=\'#666\';this.previousElementSibling.textContent=\'Preview\';">'
                . '</div>'
                . '<div style="font-weight:600;font-size:.85rem;">'.$title.'</div>'
                . '<div style="font-size:.7rem;color:#666;">'.substr($videoId, 0, 8).'...</div>'
                . '</div>';
        }
        $html .= '</div>';

        return HTTPResponse::create()
            ->setStatusCode(200)
            ->addHeader('Content-Type', 'text/html; charset=utf-8')
            ->setBody($html);
    }

    /**
     * Legacy JSON endpoint for backward compatibility
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function searchVideos(HTTPRequest $request)
    {
        // Redirect to new HTML endpoint for CmsModalSearch
        return $this->searchResults($request);
    }

    /**
     * Handle video uploaded event
     */
    protected function handleVideoUploaded($data)
    {
        // Override in extension if needed
        $this->extend('onVideoUploaded', $data);
    }
    
    /**
     * Handle video encoded event
     */
    protected function handleVideoEncoded($data)
    {
        // Override in extension if needed
        $this->extend('onVideoEncoded', $data);
    }
    
    /**
     * Handle encoding failed event
     */
    protected function handleEncodingFailed($data)
    {
        // Override in extension if needed
        $this->extend('onEncodingFailed', $data);
    }
    
    /**
     * Return JSON response
     */
    protected function jsonResponse($data, $status = 200)
    {
        return HTTPResponse::create()
            ->setStatusCode($status)
            ->addHeader('Content-Type', 'application/json')
            ->setBody(json_encode($data));
    }
    
    /**
     * Return JSON error response
     */
    protected function jsonError($message, $status = 400)
    {
        return $this->jsonResponse([
            'error' => $message,
            'status' => $status
        ], $status);
    }
    
    /**
     * Get logger
     */
    protected function getLogger()
    {
        return \SilverStripe\Core\Injector\Injector::inst()->get(\Psr\Log\LoggerInterface::class);
    }
}
