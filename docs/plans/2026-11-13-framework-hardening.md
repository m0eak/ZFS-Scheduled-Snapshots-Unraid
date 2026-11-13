# Framework hardening implementation plan

User authorization: Luna implements the five architecture improvements; the
controller independently accepts each task before advancing.

## Scope and constraints

- Preserve PHP + vanilla JavaScript, native multipage navigation, existing
  action confirmation, CSRF checks, hold protection and rollback restrictions.
- Use the isolated `feat/framework-hardening` worktree. Do not push, deploy,
  touch unrelated untracked files, or invoke additional agents.
- Read Graft context before source discovery. The controller provides the
  existing graph through the original repository when needed.
- Write executable regression tests first for behavior changes. Record the
  failing command and the passing command. Tests use fixtures and temporary
  directories; never invoke real ZFS mutations.
- Keep each task separately reviewable. Controller owns acceptance and the
  progress ledger. Implementer reports changed files, validation and limitations.
- No command timeout policy changes in this batch: write timeout reconciliation
  requires a separate design. A runner lock must safely prevent overlap even
  while an existing command is still running.

### Task 1: Serialize scheduler runs

Add a small reusable advisory operation lock and acquire it before the runner
reads configuration or invokes ZFS. The shared lock is also used by task 2.
Use a fixed plugin-specific location under `/var/run`; tests supply an isolated
temporary directory. Acquire nonblocking, retain the file handle for the whole
operation, and never unlink the lock file on release. Distinguish busy from
failure to create/open/lock the file. Busy runs log and exit without doing any
ZFS work; lock failures fail closed. Release reliably on normal exits and
exceptions, with process exit retaining the operating system's normal cleanup.

Keep changes in the new lock helper, bootstrap, runner, and focused tests.
Avoid cron rewrites or process-killing mechanisms.

Acceptance:
- A real second PHP process cannot acquire the held lock.
- Releasing it allows a later process to acquire it.
- Unwritable/invalid lock paths fail explicitly.
- A duplicate runner performs no mocked ZFS work.
- Existing PHP suite and relevant lint pass.

### Task 2: Make schedule updates recoverable and coordinate with the runner

Use task 1's same operation lock around the entire configuration write sequence,
including the initial authoritative read and post-write verification.
Validate the complete proposed configuration before any mutation.
Preserve the existing API contract on success; add structured diagnostic fields
on failure without exposing stack traces. The API must forward those fields.

Capture original values and inheritance/source information for properties being
changed. Apply changes in a safe order (enable last). If a write fails, stop
forward writes and restore attempted changes, preserving local versus inherited
state. Verify effective state after successful writes and after recovery.
Do not claim transactional atomicity: incomplete recovery must be reported
explicitly, with changed fields and the actual observed configuration. Never
return an ordinary success for a partial update. Busy writes return a distinct
retryable error and perform no ZFS mutations.

Acceptance:
- Invalid payload performs no writes.
- Mid-update failure restores earlier changes and stops later writes.
- Inherited properties remain inherited after recovery.
- Failed recovery is visible and never represented as a clean rollback.
- Runner/config writer contention uses the same lock.
- Existing success behavior and PHP tests remain compatible.

### Task 3: Add executable frontend regression tests

Add a small Node-based behavior harness that runs the actual existing JavaScript
functions with controlled network promises, storage, animation frames and
minimal DOM fixtures. No frontend framework, package installation, or generated
copy of production functions. Add it to CI alongside existing PHP checks.

Cover cache confirmation with identical data unlocking actions; changed dataset
frequency/readonly updating the editor's authoritative data; invalidation
preventing an old response from repainting or caching; A-to-B snapshot requests
settling out of order; cached empty A to uncached empty B replacing Loading;
persistent stale banner surviving partial failure and clearing on successful
refresh; overview ring updating and independent cached sections appearing.
Fix defects exposed by these tests within the existing shared loading design.
Do not add new literal-only assertions as a substitute.

Acceptance:
- Tests execute production behavior and fail for the corresponding broken path.
- Deterministic promises/frames, no arbitrary sleeps or real network.
- Existing PHP suite and JS syntax checks pass.
- CI executes the behavior tests.

### Task 4: Remove redundant dataset collection

Give `DatasetService::getOverviewStats` an optional precollected dataset list,
preserving no-argument callers. `overview.php` collects datasets once and reuses
that list for both statistics and warnings. `datasets.php` returns the same list
in `data`, with the derived overview in `meta.overview`.

The overview page restores its two caches immediately and derives both fresh
sections from a single `fetchDatasetsShared()` response, persisting overview
and datasets caches consistently. The tree and page must continue sharing that
promise. Preserve failure/stale/epoch behavior and existing refresh semantics.
Do not introduce a cross-request server cache.
Keep command failure distinct from a successful empty catalog: the datasets
and overview read APIs must return a structured failure when catalog collection
fails, allowing the frontend to retain its cache rather than confirming [].

Avoid re-enumerating all dataset names separately for every dataset during a
single list build; preserve public single-dataset membership validation.
Keep every currently displayed tree field; do not silently remove counts.
The shared dataset list must also include `time` and `day`: openEdit reads these
fields, but the current list omits them and silently falls back to midnight /
day 1. Add a regression proving existing weekly time/day survive opening the
editor and saving an unrelated setting.
For dataset protection counts use the native snapshot `userrefs` property in a
batch `zfs list` (already used by SnapshotService), counting snapshots where
userrefs is positive. This removes per-snapshot `zfs holds` calls from dataset
statistics without changing the detailed hold-tag action endpoint. Preserve
latest-automatic-snapshot semantics; do not substitute newest external/manual.

Acceptance:
- Instrumented fixtures show one full collection per overview API request.
- Overview fresh load uses one shared datasets response, not a second full
  overview request.
- Summary remains equal to the same supplied dataset rows.
- Known dataset list is not re-enumerated N times during one collection.
- Dataset protection-count queries do not issue per-snapshot hold commands.
- Failed catalog collection is not reported as a successful empty dataset list.
- Dataset list and actual edit form preserve scheduled time/day.
- Behavior tests from task 3 and PHP tests pass.

### Task 5: Define space accounting and display source segments

Treat dataset `usedbysnapshots` as the total-space measurement. Separately sum
individual snapshot `used` by the existing naming classification: `autosnap`,
`plugin_manual`, `external`. Represent the positive remainder as shared or
unattributed space. Label categories as exclusive-space estimates, never as
guaranteed category-deletion savings. Classification is naming-based.

Return explicit measurement status, allowing unknown and inconsistent data.
Use the per-dataset `snapshot_space` contract:
`{status: "ok"|"unknown"|"inconsistent", total_bytes: int|null,
exclusive_bytes: {autosnap: int|null, plugin_manual: int|null,
external: int|null}, shared_bytes: int|null}`.
Total measurement and breakdown validity are independent: if total is known but
the breakdown fails, retain total_bytes and the legacy numeric total; leave
unavailable category/shared values null and status unknown. A measured exclusive
sum above total retains the known total with status inconsistent and no invented
shared remainder. Valid empty measurements are numeric zero, not unknown.

Failed commands, malformed/partial data, and exclusive sums above the observed
total must not be silently converted into zero or normalized into believable
segments. Keep legacy `snapshot_used_bytes` for compatibility, using null for
unknown; overview aggregate must not imply unknown rows are known zeros.
The overview adds `snapshot_space` containing `status: "ok"|"partial"`,
`known_bytes`, and `unknown_datasets` for total measurements. If any dataset
total is unknown, legacy aggregate snapshot_used_bytes is null; show the known
subtotal explicitly as partial rather than presenting it as the full total.

Render a stacked bar for known consistent measurements with stable source
colors, text legend and byte values, using the existing host theme. Keep the
largest dataset as the chart's comparative scale. Show total separately from
category details; unknown and inconsistent rows stay visible with clear status.
Add Chinese and English explanations and labels. Existing old browser caches
must be safely ignored or migrated after the payload contract change.

Acceptance:
- Backend fixtures cover all three origins, zero, shared remainder, failed
  total, malformed/partial per-snapshot values and inconsistent sums.
- Runtime frontend tests verify segment sizing/labels, unknown handling,
  aggregate partial status and compatibility with cached older payloads.
- No additional per-snapshot `zfs get used` calls; reuse batch list data.
- Existing action semantics, theme inheritance and all suites remain passing.

## Final verification

Run PHP suite, Node behavior suite, PHP/JS/shell syntax checks appropriate to
changed files and `git diff --check`. Controller audits every task's final diff,
including failure paths, and performs a final integrated review. Refresh Graft
after substantial changes. Real Unraid/ZFS validation remains a clearly stated
environment limitation.
