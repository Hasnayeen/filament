---
title: Upgrading from v4.x
---

> If you see anything missing from this guide, please do not hesitate to [make a pull request](https://github.com/filamentphp/filament/edit/4.x/packages/notifications/docs/07-upgrade-guide.md) to our repository! Any help is appreciated!

## New requirements

- Tailwind CSS v4.0+

## Upgrading automatically

The easiest way to upgrade your app is to run the automated upgrade script. This script will automatically upgrade your application to the latest version of Filament, and make changes to your code which handle most breaking changes.

```bash
composer require filament/upgrade:"^4.0" -W --dev
vendor/bin/filament-v4
```

Make sure to carefully follow the instructions, and review the changes made by the script. You may need to make some manual changes to your code afterwards, but the script should handle most of the repetitive work for you.

Finally, you must run `php artisan filament:install` to finalize the Filament v4 installation. This command must be run for all new Filament projects.

You can now `composer remove filament/upgrade` as you don't need it anymore.

> Some plugins you're using may not be available in v4 just yet. You could temporarily remove them from your `composer.json` file until they've been upgraded, replace them with a similar plugins that are v4-compatible, wait for the plugins to be upgraded before upgrading your app, or even write PRs to help the authors upgrade them.

## Upgrading manually

After upgrading the dependency via Composer, you should execute `php artisan filament:upgrade` in order to clear any Laravel caches and publish the new frontend assets.

### High-impact changes

#### The `FILAMENT_FILESYSTEM_DISK` environment variable

Please see the [Panel Builder](../panels/upgrade-guide#the-filament_filesystem_disk-environment-variable) upgrade guide for information about this change.

### Medium-impact changes

### Low-impact changes

#### The European Portuguese translations

The European Portuguese translations have been moved from `pt_PT` to `pt`, which appears to be the more commonly used language code for the language within the Laravel community.

#### Nepalese translations

The Nepalese translations have been moved from `np` to `ne`, which appears to be the more commonly used language code for the language within the Laravel community. 
