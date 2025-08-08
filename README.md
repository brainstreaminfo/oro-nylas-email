# Nylas-Email-Sync extension

## Overview

The nylas-email-sync extension by Brainstream tech integrates Nylas email services with OroCRM for seamless email account connection and folder synchronization. This bundle extends OroCRM's email functionality, allowing users to manage Nylas-connected email folders and customize sync preferences via an intuitive interface.

## Features

- **OAuth Integration**: Connect Nylas email accounts to OroCRM using secure OAuth authentication
- **Folder Synchronization**: Synchronize email folders including Inbox, Sent, and Custom Labels
- **Flexible UI**: Enable/disable folder synchronization with an intuitive checkbox-based interface
- **Enterprise Compatible**: Full compatibility with OroCRM 6.1 and later versions
- **Automatic Cron Jobs**: Built-in cron commands for email synchronization and cleanup

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

> **Note**: This will install the latest stable version. For development versions, use `dev-develop`.

### 2. Enable the Bundle

The bundle should be automatically registered. If not, manually register it in your `config/bundles.php` file:

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

### 4. Clear Cache

Clear the cache to ensure the bundle is fully loaded:

```bash
php bin/console cache:clear --env=prod
```

### 5. Install and Build Assets

Install and build the frontend assets:

```bash
# Install assets with symlinks
php bin/console assets:install --symlink --relative

# Build assets for production
php bin/console oro:assets:build
```

> **Note**: The extension includes CSS and JavaScript assets that need to be installed and built for the UI to work properly.

### 6. Warm Up Cache

Warm up the cache to optimize performance:

```bash
php bin/console cache:warmup --env=prod
```

### 7. Configure Nylas Credentials

1. Log in to the [Nylas Dashboard](https://dashboard.nylas.com) to obtain your Client ID and Client Secret
2. In OroCRM, navigate to **System > Configuration > Integrations > Nylas Settings**
3. Enter the Region(US/EU), Client ID and Client Secret, then save

### 8. Set Up Cron Jobs (Required)

The extension includes automatic cron commands that need to be configured:

#### Option A: Using OroCRM's Built-in Cron System

The extension automatically registers these cron commands:
- `oro:cron:nylas-sync` - Synchronizes emails (runs every minute by default)
- `oro:cron:email-body-purge` - Cleans up old email bodies

To enable them:
1. Go to **System > Scheduled Tasks**
2. Find and enable the Nylas sync commands
3. Configure the schedule as needed

#### Option B: Manual Cron Setup

Add to your server's crontab:

```bash
# Nylas Email Sync (every 5 minutes)
*/5 * * * * cd /path/to/orocrm && php bin/console oro:cron:nylas-sync --env=prod

# Email Body Cleanup (daily at 1 AM)
0 1 * * * cd /path/to/orocrm && php bin/console oro:cron:email-body-purge --env=prod
```

### 9. Access the Feature

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

The account connected by default will be active. Use the "Active/Deactive" action to toggle the account status.

### Enable Multiple Accounts

Enable multiple email support in user settings to connect and manage additional email accounts.

## Troubleshooting

### Sync Not Working

If synchronization isn't working:

1. **Check Cron Jobs**: Ensure cron jobs are running
   ```bash
   php bin/console oro:cron:nylas-sync --env=dev --verbose
   ```

2. **Check Logs**: Review logs at `var/logs/dev.log` for error messages

3. **Verify Nylas Credentials**: Ensure Client ID and Secret are correct

4. **Check Network Access**: Verify server can reach Nylas API

5. **Review Folder Configuration**: Ensure folders are selected for sync

### Debug Logging

Enable detailed debug logging by configuring `config/packages/dev/monolog.yaml` for comprehensive error traces.

### Manual Sync Testing

Test sync manually for a specific email origin:

```bash
php bin/console oro:cron:nylas-sync --env=dev --id=EMAIL_ORIGIN_ID --verbose
```

## Changelog

### Version 1.0.0 (Initial Release - August 2025)

- Added Nylas email integration with OAuth authentication
- Implemented folder synchronization UI with folderUid support
- Added automatic cron commands for email sync and cleanup
- Initial compatibility with OroCRM 6.1
- Smart deduplication across folders
- Performance optimizations with batch processing

## License

This bundle is released under the **MIT License**. See the [LICENSE](LICENSE) file for complete details.

## Support

For issues, questions, or feature requests:

- **Email Support**: Contact [info@brainstream.tech](mailto:info@brainstream.tech)

## Acknowledgments

Built by **BrainStream**, leveraging the powerful OroCRM and Nylas ecosystems to deliver seamless email integration solutions.
