# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-04-17

### Added
- Playback settings (Autoplay, Controls, Muted, Loop) can now be configured per video in CMS
- Checkboxes in the upload field to configure video playback behavior
- Settings are stored as JSON in the database
- New `getTitle()` method to access video title from stored JSON
- Support for storing complete Bunny video metadata as JSON

### Changed
- **BREAKING**: DBBunnyVideo now extends DBText instead of DBVarchar
- **BREAKING**: Database field now stores complete JSON data instead of just video ID
- Backend preview no longer uses Autoplay by default
- `EmbedHTML()` now uses stored settings from JSON by default (can be overridden with parameters)
- `getVideoID()` now reads from JSON field `guid`

### Fixed
- Backend video preview now respects user preference (no forced autoplay)

### Migration
- Run `dev/build` after updating to migrate database schema from Varchar to Text
- Existing video IDs will be automatically converted to JSON format
- See [UPGRADE.md](docs/UPGRADE.md) for detailed migration instructions
- Backward compatible: existing video IDs will be wrapped in JSON automatically

## [1.0.0] - 2025-02-10

### Added
- Initial release
- BunnyVideoUploadField for direct browser-to-CDN uploads
- API controller for video creation and webhook handling
- DBBunnyVideo field type with embed functionality
- Full SilverStripe 5 support
- Real-time upload progress tracking
- Video preview in CMS
- Multi-library support for multi-tenancy
- Comprehensive documentation

### Features
- Direct upload bypasses PHP upload limits
- Automatic video transcoding via Bunny Stream
- Responsive upload interface
- Error handling and validation
- CSRF protection
- Extension points for custom webhook handling
