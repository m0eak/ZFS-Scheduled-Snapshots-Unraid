# ZFS Safety Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve command safety, test coverage, CI verification, destructive-action confirmation, service boundaries, and WebUI risk signaling for the ZFS Scheduled Snapshots plugin.

**Architecture:** Keep the current no-Composer PHP structure. Add small focused PHP classes under `zfs.scheduled.snapshots/include/` and test them through the existing lightweight `tests/run.php` harness. Each phase must be independently tested, committed, and pushed to `origin/dev`.

**Tech Stack:** PHP, Bash, GitHub Actions, plain JavaScript/CSS, Unraid plugin runtime.

---

### Task 1: ZFS Command Wrapper

**Files:**
- Create: `zfs.scheduled.snapshots/include/ZfsCommand.php`
- Modify: `zfs.scheduled.snapshots/include/bootstrap.php`
- Modify: `zfs.scheduled.snapshots/include/common.php`
- Create: `tests/zfs_command_test.php`
- Modify: `tests/run.php`

- [ ] Write failing tests for argument escaping and command execution result shape.
- [ ] Run `php tests/run.php` and verify the new tests fail because `ZfsCommand` does not exist.
- [ ] Add `ZfsCommand` with `escapeArg`, `build`, and `run` methods.
- [ ] Keep `ZfsScheduledSnapshots::exec($command)` as a compatibility wrapper around `ZfsCommand::runShell($command)`.
- [ ] Run `php tests/run.php`.
- [ ] Run `find zfs.scheduled.snapshots tests -name '*.php' -print0 | xargs -0 -n1 php -l`.
- [ ] Commit with `git commit -m "Add ZFS command wrapper"` and push `dev`.

### Task 2: Pure Logic Tests

**Files:**
- Create: `zfs.scheduled.snapshots/include/SnapshotNaming.php`
- Create: `zfs.scheduled.snapshots/include/SchedulePolicy.php`
- Create: `zfs.scheduled.snapshots/include/RetentionPolicy.php`
- Modify: `zfs.scheduled.snapshots/include/bootstrap.php`
- Modify: `zfs.scheduled.snapshots/include/services/SnapshotService.php`
- Create: `tests/snapshot_naming_test.php`
- Create: `tests/schedule_policy_test.php`
- Create: `tests/retention_policy_test.php`
- Modify: `tests/run.php`

- [ ] Write failing tests for snapshot origin classification, schedule due checks, and retention delete selection.
- [ ] Run `php tests/run.php` and verify these tests fail for missing classes.
- [ ] Extract pure logic without changing ZFS side effects.
- [ ] Run `php tests/run.php`.
- [ ] Run PHP syntax checks.
- [ ] Commit with `git commit -m "Add pure policy tests"` and push `dev`.

### Task 3: CI Verification

**Files:**
- Modify: `.github/workflows/build-scheduled-snapshots.yml`

- [ ] Add CI steps before packaging: `php tests/run.php`, PHP syntax check, `bash -n` for shell scripts.
- [ ] Run the exact commands locally.
- [ ] Commit with `git commit -m "Run tests in build workflow"` and push `dev`.

### Task 4: Backend Destructive Confirmation

**Files:**
- Modify: `zfs.scheduled.snapshots/api/snapshot-delete.php`
- Modify: `zfs.scheduled.snapshots/api/snapshot-release.php`
- Modify: `zfs.scheduled.snapshots/api/snapshot-rollback.php`
- Modify: `zfs.scheduled.snapshots/include/validation.php`
- Create: `tests/action_confirmation_test.php`
- Modify: `tests/run.php`

- [ ] Write failing tests for required confirmation values.
- [ ] Require `confirm` to equal the full snapshot name for delete and rollback.
- [ ] Require `confirm` to equal `snapshotName:tag` for hold release.
- [ ] Return `CONFIRMATION_REQUIRED` or `CONFIRMATION_MISMATCH` before side effects.
- [ ] Run tests and syntax checks.
- [ ] Commit with `git commit -m "Require backend confirmations for destructive actions"` and push `dev`.

### Task 5: Service Decomposition

**Files:**
- Modify: `zfs.scheduled.snapshots/include/common.php`
- Modify: `zfs.scheduled.snapshots/include/services/DatasetService.php`
- Modify: `zfs.scheduled.snapshots/include/services/SnapshotService.php`
- Modify extracted helper files from earlier tasks as needed.

- [ ] Move pure naming, schedule, and retention decisions out of side-effect-heavy services.
- [ ] Keep public API endpoint behavior compatible except for the explicit safety checks.
- [ ] Run tests and syntax checks after each extracted unit.
- [ ] Commit with `git commit -m "Split snapshot policy helpers"` and push `dev`.

### Task 6: WebUI Risk Signaling

**Files:**
- Modify: `zfs.scheduled.snapshots/web/assets/js/snapshots.js`
- Modify: `zfs.scheduled.snapshots/web/assets/js/logs.js`
- Modify: `zfs.scheduled.snapshots/web/i18n.php`
- Modify: `zfs.scheduled.snapshots/web/assets/css/next.css`
- Modify API files only if UI needs additional response data.

- [ ] Hide destructive action buttons for non-operable external snapshots.
- [ ] Add confirm payloads matching backend requirements.
- [ ] Add clearer copy for protected snapshots, external snapshots, release risk, and rollback risk.
- [ ] Add lightweight log filtering improvements if supported by the existing API.
- [ ] Run tests and syntax checks.
- [ ] Commit with `git commit -m "Improve snapshot risk signaling"` and push `dev`.

### Final Verification

- [ ] Run `php tests/run.php`.
- [ ] Run `find zfs.scheduled.snapshots tests -name '*.php' -print0 | xargs -0 -n1 php -l`.
- [ ] Run `bash -n zfs.scheduled.snapshots/event/cron_setup.sh`.
- [ ] Run `bash -n zfs.scheduled.snapshots/debug_check.sh`.
- [ ] Run `git status --short --branch` and verify `dev` is aligned with `origin/dev`.
