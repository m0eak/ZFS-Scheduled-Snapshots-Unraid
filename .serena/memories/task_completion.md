# Task Completion

- For PHP behavior changes, run the lightweight PHP tests:
  - `php tests/run.php`
- For PHP changes, run syntax checks at minimum:
  - `find zfs.scheduled.snapshots tests -name '*.php' -print0 | xargs -0 -n1 php -l`
- For shell script changes, run syntax checks where Bash is available:
  - `bash -n zfs.scheduled.snapshots/event/cron_setup.sh`
  - `bash -n zfs.scheduled.snapshots/debug_check.sh`
- For plugin packaging/manifest changes, inspect both manifests and ensure root/source copies stay intentionally synchronized:
  - `zfs.scheduled.snapshots/zfs.scheduled.snapshots.plg`
  - `zfs.scheduled.snapshots.plg`
- For changes to runner, snapshot creation/deletion, hold release, rollback, or dataset mutation:
  - Prefer a non-production Unraid/ZFS test environment.
  - Verify command construction is shell-escaped.
  - Manually test with disposable pool/dataset before claiming runtime behavior is safe.
- There is no discovered PHPUnit/Composer/Node test suite. State clearly when only syntax/static checks were possible.
- Check git status before final response and do not treat generated `.tgz` or manifest churn as harmless unless requested.
- After Serena onboarding/memory edits, user can run `serena memories check` from repo root.