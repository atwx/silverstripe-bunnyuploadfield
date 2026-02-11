# Installation Guide

## Requirements

- SilverStripe 5.0 or higher
- PHP 8.1 or higher
- Active Bunny Stream account
- Composer

## Step 1: Install via Composer

```bash
composer require yourvendor/silverstripe-bunny-stream
```

Or add to your `composer.json`:

```json
{
    "require": {
        "yourvendor/silverstripe-bunny-stream": "^1.0"
    }
}
```

## Step 2: Configure Environment

Add your Bunny credentials to `.env`:

```env
BUNNY_LIBRARY_ID=your-library-id-here
BUNNY_API_KEY=your-api-key-here
```

### Finding Your Credentials

1. Log in to https://dash.bunny.net
2. Go to "Stream" → "Video Library"
3. Select your library (or create a new one)
4. Your **Library ID** is shown in the library details
5. Your **API Key** is under "API" tab

## Step 3: Run dev/build

```bash
vendor/bin/sake dev/build flush=1
```

Or visit: `https://yoursite.com/dev/build?flush=1`

## Step 4: Configure Webhooks (Optional but Recommended)

To track encoding status, configure webhooks in Bunny:

1. Go to your Video Library settings in Bunny
2. Navigate to "Webhooks"
3. Add webhook URL: `https://yoursite.com/api/bunny/webhook`
4. Select events:
   - Video Uploaded
   - Video Encoded
   - Encoding Failed

## Step 5: Test the Integration

Create a test page:

```php
<?php

use Page;
use YourVendor\BunnyStream\Forms\BunnyVideoUploadField;

class TestVideoPage extends Page
{
    private static $db = [
        'TestVideoID' => 'Varchar(255)'
    ];
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->addFieldToTab(
            'Root.Main',
            BunnyVideoUploadField::create('TestVideoID', 'Test Video')
        );
        
        return $fields;
    }
}
```

Run `dev/build` and try uploading a video in the CMS.

## Troubleshooting

### Upload fails with CORS error

Make sure your Bunny library allows uploads from your domain:

1. Go to Bunny library settings
2. Under "Security" → "Allowed Referrers"
3. Add your domain (e.g., `*.yoursite.com`)

### "Bunny credentials not configured" error

Double-check your `.env` file has the correct variables:
- `BUNNY_LIBRARY_ID`
- `BUNNY_API_KEY`

Make sure to run `dev/build flush=1` after changing `.env`.

### Upload succeeds but no preview shows

The video is being encoded by Bunny. This can take a few minutes depending on video size. Refresh the page after a minute or two.

## Multi-Library Setup (Multi-Tenancy)

See `docs/examples/MultiTenancy.php` for setting up separate libraries per customer.

## Advanced Configuration

### Custom API Endpoint

Override in your project config:

```yaml
# app/_config/bunny-stream.yml
YourVendor\BunnyStream\Controllers\BunnyAPIController:
  custom_endpoint: 'custom-video-api'
```

### Extending the API Controller

```php
<?php

use YourVendor\BunnyStream\Controllers\BunnyAPIController;

class MyBunnyController extends BunnyAPIController
{
    protected function onVideoEncoded($data)
    {
        // Custom logic when video is encoded
        parent::onVideoEncoded($data);
        
        // Send notification, update database, etc.
    }
}
```

## Next Steps

- Read the [README.md](../README.md) for usage examples
- Check `docs/examples/` for code samples
- Review API documentation in source code
