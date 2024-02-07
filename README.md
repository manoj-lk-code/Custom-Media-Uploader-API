# Custom Media Uploader API

This WordPress plugin, crafted by [Manoj lk](https://manojlk.work) & [ChatGPT](https://chat.openai.com), enables secure and efficient media file uploads through a REST API using file link. 

## Features

- **Secure Uploads**: Utilizes WordPress authentication to ensure uploads are authorized.
- **Supports Various Media Types**: Compatible with JPEG, PNG, GIF, MP4, and MP3.
- **Customizable Endpoint**: Allows setting a unique slug for the API endpoint.

## Installation

1. **Download**: Get the PHP file of the plugin.
2. **Upload**: In WordPress admin, go to Plugins > Add New > Upload Plugin, and upload the file.
3. **Activate**: Activate the plugin via the 'Plugins' menu.

## Configuration

### Custom Slug

For security, define a custom slug in `wp-config.php`: This will provide great security and allows you to define your own custom slug for endpoint to keep it unique. [Example: you can use => qoiwusegdvbsao]

```php
define('WP_MEDIA_API_SLUG', 'your_custom_slug');
```

Replace `'your_custom_slug'` with something unique and you can also use cmv_api as default value. **It is recommended to use unique value for BEST OF BEST security.**

### Usage

Upload files via the API using curl:

```bash
curl --location 'https://your_site_url/wp-json/wp/v2/your_slug_or_cmv_api' \
--header 'Content-Type: application/json' \
--data '{ "file_url": "http://example.com/image.jpg" }' \
--user 'username:password'
```

Replace placeholders with your details and the URL of the media file to upload.

## Troubleshooting

If you encounter issues:
- Ensure `WP_MEDIA_API_SLUG` is defined if you see an admin notice.
- You can enable WordPress debug mode to enable logging for errors related to file uploads.

## Disclaimer

This plugin is provided "as is" without warranty. The authors are not liable for any damages or data loss. Test in a staging environment before use.
