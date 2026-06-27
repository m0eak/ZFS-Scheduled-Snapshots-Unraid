# Conventions

- PHP style is simple procedural/includes plus static service classes; no namespaces, Composer autoloading, or framework conventions.
- Shared PHP entrypoint is `include/bootstrap.php`; API endpoints should usually require it, then call `zss_api_run(...)` when needed or emit via response helpers.
- JSON API response shape:
  - success: `{ ok: true, data: ..., meta: { generated_at: ... } }`
  - error: `{ ok: false, error: { code, message }, meta: { generated_at: ... } }`
  - use `zss_json_success`, `zss_json_error`, `zss_emit_json`; response helper clears output buffers and exits.
- API endpoints are thin; put ZFS/domain behavior in `include/services/*Service.php` or `ZfsScheduledSnapshots` as appropriate.
- Shell command safety: dataset names, snapshot names, property names/assignments must be shell-escaped before composing `zfs` commands. Existing helpers include `escapeshellarg`, `DatasetService::quoteDatasetName`, `quotePropertyAssignment`, and `quoteCreateOption`.
- ZFS properties use the `com.sun:auto-snapshot*` namespace; existing defaults include frequency `daily`, keep `31`, time `00:00`, day `1`, readonly `false`, retain_days `0`.
- Snapshot naming constants live in `ZfsScheduledSnapshots`: auto prefix `autosnap`, manual prefix `manual`, hold tag `autosnap`.
- Runner is cron-oriented and logs verbosely with `ZfsScheduledSnapshots::log`; preserve useful operational logging around snapshot creation, retention, and failures.
- Comments are mixed English/Chinese. Keep comments sparse and local; when editing nearby Chinese-commented code, Chinese comments are acceptable.
- WebUI is layered apart from native Unraid page: `ZFSScheduledSnapshots.page` should stay lightweight/preview-oriented; full workflows belong in standalone `web/` pages + API/service layer.