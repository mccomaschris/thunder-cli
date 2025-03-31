# thundr site:cron

## Description

Enable or disable Laravel's scheduler on the site.

## Usage

```bash
thundr site:cron
```

## What it does

- Adds or removes a cron job for `php artisan schedule:run`.
- Uses the correct PHP binary per `php_version` in thundr.yml.

## Example

```bash
thundr site:cron
```

## Notes

Only effective if `enable_scheduler: true` is set.
