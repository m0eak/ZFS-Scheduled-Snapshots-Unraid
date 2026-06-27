# Tech Stack

- Primary language: PHP (`.php`) targeting Unraid's bundled web/PHP runtime; Serena project language is `php`.
- Supporting languages/assets: Bash shell scripts for cron/debugging, plain JavaScript, CSS, Unraid `.page` file, XML-like `.plg` plugin manifests.
- No Composer manifest and no Node/package manifest observed; dependencies are system/runtime tools rather than vendored packages.
- Runtime dependencies: Unraid, PHP CLI/Web runtime, ZFS CLI (`zfs`), cron/crontab, syslog functions when available.
- Build/package: GitHub Actions workflow `.github/workflows/build-scheduled-snapshots.yml` rewrites plugin version/entity, copies the manifest to repo root, removes old tarballs, and creates `zfs.scheduled.snapshots-$buildver.tgz` from `zfs.scheduled.snapshots/`.
- Branch packaging convention: non-main builds use date + run number + branch suffix; main omits branch suffix. Workflow triggers on pushes touching plugin source/workflow for `main`, `dev`, `beta`, and `feature-**`.