# Core

- Unraid plugin for ZFS scheduled snapshots; repo root contains source dir `zfs.scheduled.snapshots/`, root install manifest `zfs.scheduled.snapshots.plg`, built release tarballs `zfs.scheduled.snapshots-*.tgz`, docs, and GitHub Actions packaging.
- Read `mem:tech_stack` for language/tooling/runtime assumptions.
- Read `mem:conventions` for PHP/API/service style and ZFS shell-command handling.
- Read `mem:suggested_commands` for useful local/Unraid commands.
- Read `mem:task_completion` before declaring code changes done.
- Main source map:
  - `zfs.scheduled.snapshots/include/`: common PHP library, API response helpers, validation, service classes.
  - `zfs.scheduled.snapshots/include/bootstrap.php`: shared include entrypoint for API/web PHP code.
  - `zfs.scheduled.snapshots/include/common.php`: `ZfsScheduledSnapshots` utility class, constants, logging, ZFS command wrappers, snapshot operations.
  - `zfs.scheduled.snapshots/include/services/`: `DatasetService`, `SnapshotService`, `LogService` business logic.
  - `zfs.scheduled.snapshots/api/`: thin JSON API endpoints, usually bootstrap + service call.
  - `zfs.scheduled.snapshots/web/`: standalone WebUI PHP pages plus `assets/js`, `assets/css`, and `layout` partials.
  - `zfs.scheduled.snapshots/scripts/runner.php`: cron runner; evaluates enabled datasets and enforces snapshot creation/retention.
  - `zfs.scheduled.snapshots/event/cron_setup.sh`: install/remove cron entry running runner every 5 minutes.
  - `zfs.scheduled.snapshots/ZFSScheduledSnapshots.page`: native Unraid plugin page/entry point.
- Runtime/plugin paths are Unraid-specific: installed plugin lives under `/usr/local/emhttp/plugins/zfs.scheduled.snapshots`; log file is `/var/log/zfs-scheduled-snapshots.log`.
- Project is early-development and data-destructive by domain; avoid behavior changes to snapshot deletion, hold release, or rollback without targeted verification and clear user acknowledgement.