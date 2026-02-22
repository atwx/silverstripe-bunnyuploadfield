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
        'POST create-video' => 'createVideo',
        'POST webhook' => 'handleWebhook',
        'GET video/$VideoID' => 'getVideoInfo'
    ];
    
    private static $allowed_actions = [
        'createVideo',
        'handleWebhook',
        'getVideoInfo'
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
