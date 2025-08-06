# BrainStream Nylas Bundle

## Overview

The oro-nylas-email extension by Brainstream integrates Nylas email services with OroCRM for seamless email account connection and folder synchronization. This bundle extends OroCRM's email functionality, allowing users to manage Nylas-connected email folders and customize sync preferences via an intuitive interface.

## Features

- **OAuth Integration**: Connect Nylas email accounts to OroCRM using secure OAuth authentication
- **Folder Synchronization**: Synchronize email folders including Inbox, Sent, and Custom Labels
- **Flexible UI**: Enable/disable folder synchronization with an intuitive checkbox-based interface
- **Enterprise Compatible**: Full compatibility with OroCRM 6.1 and later versions

## Requirements

- **OroCRM**: Version 6.1.0 or higher
- **PHP**: Version 8.1 or higher
- **Nylas API Credentials**: Client ID and Client Secret from [Nylas Dashboard](https://dashboard.nylas.com)

## Installation

### 1. Install via Composer

Add the bundle to your OroCRM project:

```bash
composer require brainstreaminfo/oro-nylas-email
```

### 2. Enable the Bundle

Register the bundle in your `config/bundles.php` file incase if it's not already there.

```php
return [
    // Other bundles...
    BrainStream\Bundle\NylasBundle\BrainStreamNylasBundle::class => ['all' => true],
];
```

### 3. Update Database Schema

Run the following command to apply migrations:

```bash
php bin/console oro:migration:load --force
```

> **Note**: Use `--force` only in development or initial setup. In production, coordinate with your deployment process to avoid data issues.

### 4. Clear and Warm Up Cache

Clear the cache to ensure the bundle is fully loaded:

```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### 5. Configure Nylas Credentials

1. Log in to the [Nylas Dashboard](https://dashboard.nylas.com) to obtain your Client ID and Client Secret
2. In OroCRM, navigate to **System > Configuration > Integrations > Nylas Settings**
3. Enter the Region(US/EU), Client ID and Client Secret, then save

### 6. Access the Feature

1. Go to Nylas Email Sync from top right menu in the OroCRM dashboard
2. Click "Connect New Account" to authenticate with Nylas
3. Select an email account from the dropdown
4. Choose folders to sync and click "Save Sync Preferences"

### Managing Preferences

- Reload the page to see updated sync statuses reflected by checked checkboxes
- Adjust settings as needed and save again to modify your sync preferences

## Configuration

### Set Default Account

The first connected account is automatically set as default. Use the "Mark as Default" action to change the default account.

### Set Active/Deactive status for Account

The account connected by default will be active. Use the "Active/Deactive" action to toogle the account status.

### Enable Multiple Accounts

Enable multiple email support in user settings to connect and manage additional email accounts.

## Troubleshooting

### Sync Not Saving

If synchronization preferences aren't saving:

1. Check symfony logs at 'var/logs/dev.log' for error messages
2. Verify Nylas credentials are correct
3. Ensure network access to Nylas API
4. Review OroCRM configuration settings

### Debug Logging

Enable detailed debug logging by configuring `config/packages/dev/monolog.yaml` for comprehensive error traces.

## Changelog

### Version 1.0.0 (Initial Release - August 2025)

- Added Nylas email integration with OAuth authentication
- Implemented folder synchronization UI with folderUid support
- Initial compatibility with OroCRM 6.1

## License

This bundle is released under the **MIT License**. See the [LICENSE](LICENSE) file for complete details.

## Support

For issues, questions, or feature requests:

- **GitHub Issues**: Open an issue on the GitHub repository
- **Email Support**: Contact [info@brainstream.tech](mailto:info@brainstream.tech.)

## Acknowledgments

Built by **BrainStream**, leveraging the powerful OroCRM and Nylas ecosystems to deliver seamless email integration solutions.
