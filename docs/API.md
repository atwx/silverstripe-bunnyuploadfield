# API Documentation

## BunnyVideoUploadField

### Usage

```php
use YourVendor\BunnyStream\Forms\BunnyVideoUploadField;

BunnyVideoUploadField::create($name, $title = null, $libraryId = null, $apiKey = null)
```

### Methods

#### setLibraryId(string $libraryId)

Set a custom Bunny library ID for this field.

```php
$field->setLibraryId('my-custom-library-id');
```

#### getLibraryId()

Get the current library ID.

#### setApiKey(string $apiKey)

Set a custom API key for this field.

```php
$field->setApiKey('my-custom-api-key');
```

#### getApiKey()

Get the current API key.

---

## DBBunnyVideo

Database field type for storing Bunny video IDs with helper methods.

### Usage

```php
use YourVendor\BunnyStream\ORM\FieldType\DBBunnyVideo;

private static $db = [
    'VideoID' => DBBunnyVideo::class
];
```

### Methods

#### getEmbedURL()

Returns the iframe embed URL for the video.

```php
$url = $this->dbObject('VideoID')->getEmbedURL();
// Returns: https://iframe.mediadelivery.net/{library}/{video}
```

#### getThumbnailURL()

Returns the thumbnail URL for the video.

```php
$thumb = $this->dbObject('VideoID')->getThumbnailURL();
// Returns: https://vz-{library}.b-cdn.net/{video}/thumbnail.jpg
```

#### getEmbedHTML($width, $height, $autoplay, $controls, $muted, $loop)

Returns complete iframe HTML for embedding.

**Parameters:**
- `$width` (int|string): Width in pixels or percentage (default: '100%')
- `$height` (int): Height in pixels (default: 360)
- `$autoplay` (bool): Auto-play video (default: false)
- `$controls` (bool): Show controls (default: true)
- `$muted` (bool): Start muted (default: false)
- `$loop` (bool): Loop video (default: false)

```php
// Basic embed
$html = $this->dbObject('VideoID')->getEmbedHTML();

// Custom embed
$html = $this->dbObject('VideoID')->getEmbedHTML(800, 450, true, true, false, true);
```

#### exists()

Check if a video ID is set.

```php
if ($this->dbObject('VideoID')->exists()) {
    // Video exists
}
```

### Template Usage

```ss
<% if $VideoID %>
    <!-- Simple embed -->
    $VideoID.EmbedHTML
    
    <!-- Custom embed -->
    $VideoID.getEmbedHTML(640, 360, false, true)
    
    <!-- Just the URL -->
    <a href="$VideoID.EmbedURL">Watch Video</a>
    
    <!-- Thumbnail -->
    <img src="$VideoID.ThumbnailURL" alt="Video Thumbnail">
<% end_if %>
```

---

## BunnyAPIController

REST API controller for Bunny operations.

### Endpoints

#### POST /api/bunny/create-video

Creates a new video entry in Bunny and returns upload URL.

**Request Body:**
```json
{
    "title": "My Video.mp4",
    "libraryId": "optional-library-id"
}
```

**Response:**
```json
{
    "videoId": "abc123-def456",
    "uploadUrl": "https://video.bunnycdn.com/library/{lib}/videos/{id}",
    "libraryId": "12345"
}
```

#### POST /api/bunny/webhook

Receives webhooks from Bunny Stream.

**Events:**
- `video.uploaded` - Video file uploaded
- `video.encoded` - Video encoding complete
- `video.encoding.failed` - Encoding failed

**Request Body:**
```json
{
    "EventType": "video.encoded",
    "VideoGuid": "abc123-def456",
    "VideoLibraryId": "12345"
}
```

#### GET /api/bunny/video/{VideoID}

Get information about a specific video.

**Response:**
```json
{
    "guid": "abc123-def456",
    "title": "My Video",
    "length": 120,
    "status": 4,
    "availableResolutions": "240p,360p,720p,1080p"
}
```

### Extension Points

You can extend the API controller to add custom webhook handling:

```php
<?php

use YourVendor\BunnyStream\Controllers\BunnyAPIController;

class CustomBunnyController extends BunnyAPIController
{
    protected function handleVideoEncoded($data)
    {
        parent::handleVideoEncoded($data);
        
        // Your custom logic
        $videoId = $data['VideoGuid'];
        
        // Update database
        $video = CustomerVideo::get()->filter('BunnyVideoID', $videoId)->first();
        if ($video) {
            $video->Status = 'Ready';
            $video->write();
        }
        
        // Send notification
        Email::create()
            ->setTo('admin@example.com')
            ->setSubject('Video encoded')
            ->setBody("Video {$videoId} is ready")
            ->send();
    }
}
```

Or use extensions:

```php
<?php

use SilverStripe\Core\Extension;

class BunnyWebhookExtension extends Extension
{
    public function onVideoEncoded($data)
    {
        // Your custom logic
    }
}
```

```yaml
# app/_config/extensions.yml
YourVendor\BunnyStream\Controllers\BunnyAPIController:
  extensions:
    - App\Extensions\BunnyWebhookExtension
```

---

## JavaScript Events

The upload field fires custom events you can listen to:

```javascript
document.addEventListener('bunny-upload-start', (e) => {
    console.log('Upload started', e.detail);
});

document.addEventListener('bunny-upload-progress', (e) => {
    console.log('Progress:', e.detail.percent + '%');
});

document.addEventListener('bunny-upload-complete', (e) => {
    console.log('Upload complete', e.detail.videoId);
});

document.addEventListener('bunny-upload-error', (e) => {
    console.error('Upload failed', e.detail.error);
});
```

---

## Error Handling

All API methods return appropriate HTTP status codes:

- `200` - Success
- `400` - Bad request (invalid input)
- `404` - Resource not found
- `500` - Server error

Error responses include a JSON body:

```json
{
    "error": "Error message here",
    "status": 400
}
```
