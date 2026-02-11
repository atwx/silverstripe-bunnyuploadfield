# SilverStripe Bunny Stream Module

SilverStripe module for seamless Bunny Stream video uploads with direct browser-to-CDN upload (no PHP upload limits).

## Features

- ✅ Direct browser upload to Bunny Stream (bypasses PHP upload limits)
- ✅ Real-time upload progress
- ✅ Automatic video transcoding via Bunny
- ✅ Video preview in CMS
- ✅ Easy integration with DataObjects
- ✅ Webhook support for encoding status
- ✅ Support for multiple Bunny libraries per customer

## Requirements

- SilverStripe ^5.0
- Bunny Stream account with API access

## Installation

```bash
composer require yourvendor/silverstripe-bunny-stream
```

## Configuration

Add your Bunny credentials to your `.env` file:

```env
BUNNY_LIBRARY_ID=your-library-id
BUNNY_API_KEY=your-api-key
```

You can find these in your Bunny Stream dashboard under your Video Library settings.

## Usage

### Basic Usage in DataObject

```php
<?php

use SilverStripe\ORM\DataObject;
use YourVendor\BunnyStream\Forms\BunnyVideoUploadField;

class VideoPage extends DataObject
{
    private static $db = [
        'BunnyVideoID' => 'Varchar(255)'
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldToTab(
            'Root.Video',
            BunnyVideoUploadField::create('BunnyVideoID', 'Upload Video')
        );
        
        return $fields;
    }
    
    public function getVideoEmbedCode()
    {
        if (!$this->BunnyVideoID) {
            return null;
        }
        
        return $this->obj('BunnyVideoID')->getEmbedCode();
    }
}
```

### Using in Templates

```html
<% if $BunnyVideoID %>
    $BunnyVideoID.EmbedHTML
<% end_if %>
```

Or with custom options:

```html
$BunnyVideoID.EmbedHTML(800, 450, true, false)
<!-- EmbedHTML(width, height, autoplay, controls) -->
```

### Multiple Libraries (Multi-Tenancy)

```php
$fields->addFieldToTab(
    'Root.Video',
    BunnyVideoUploadField::create('BunnyVideoID', 'Upload Video')
        ->setLibraryId('customer-specific-library-id')
        ->setApiKey('customer-specific-api-key')
);
```

## API Endpoints

The module automatically registers these endpoints:

- `POST /api/bunny/create-video` - Creates video entry and returns upload URL
- `POST /api/bunny/webhook` - Receives Bunny encoding webhooks

## Setting up Webhooks

In your Bunny Stream dashboard, configure the webhook URL:

```
https://yourdomain.com/api/bunny/webhook
```

This allows the module to track encoding status automatically.

## Development

### Building Assets

```bash
cd client
npm install
npm run build
```

### Running in Dev Mode

```bash
npm run watch
```

## License

BSD-3-Clause

## Support

For issues and feature requests, please use the GitHub issue tracker.
