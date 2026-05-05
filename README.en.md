# ZFS Scheduled Snapshots (Unraid Plugin)

<div align="center">

[![GitHub stars](https://img.shields.io/github/stars/m0eak/ZFS-Scheduled-Snapshots-Unraid?style=flat-square)](https://github.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/m0eak/ZFS-Scheduled-Snapshots-Unraid?style=flat-square)](https://github.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/network/members)
[![GitHub last commit](https://img.shields.io/github/last-commit/m0eak/ZFS-Scheduled-Snapshots-Unraid?style=flat-square)](https://github.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/commits)

</div>

> 中文版本： [README.md](README.md)

---

## ⚠️ Warning

This plugin is still in an early development stage and has not yet gone through full real-world regression testing.

Please use it carefully:

- It is recommended to test only in **non-production environments** or with **complete backups already in place**
- The author is **not responsible for any data loss**
- Before use, make sure you understand the basic behavior of ZFS snapshots, holds, and retention policies

---

## 📝 Introduction

**ZFS Scheduled Snapshots** is an Unraid plugin for automatic scheduled snapshot management of ZFS datasets.

The current version uses a **layered UI architecture**:

- **Unraid plugin page**: overview and entry point only
- **Standalone WebUI**: detailed configuration, snapshot management, and log viewing

A background Cron task checks every 5 minutes and automatically:

- creates snapshots
- prunes regular automatic snapshots
- retains immutable snapshots (ZFS Hold)
- catches up after missed execution windows

---

## ✨ Main Features

- **Multi-frequency scheduling**
  - 5 minutes / 15 minutes / hourly / daily / weekly / monthly
- **Precise timing control**
  - daily / weekly / monthly support a specific execution time
  - weekly / monthly support a specific weekday or day of month
- **Immutable snapshot protection**
  - automatically add the `autosnap` hold to new snapshots
- **Dual retention strategy**
  - `keep`: quantity-based retention for regular automatic snapshots
  - `retain_days`: time-based retention for immutable / held snapshots
- **Automatic catch-up**
  - if a scheduled run is missed, the next runner execution can catch up
- **Layered management UI**
  - plugin page for preview, WebUI for full management
- **Manual snapshot operations**
  - manually create, delete, add hold, and release hold
- **Log viewing**
  - inspect runner execution logs, filter by level, and clear logs
- **Backward compatibility**
  - keeps existing ZFS properties, existing snapshots, and existing holds

---

## 🏗️ Architecture

### 1) Plugin page (`ZFSScheduledSnapshots.page`)

Responsibilities:

- show overview statistics
- show dataset status summary
- provide a WebUI entry point

Principles:

- keep a native Unraid look and feel
- no longer handle complex configuration editing directly

### 2) WebUI

Current pages:

- **Dashboard**: overview statistics
- **Datasets**: dataset configuration management
- **Snapshots**: snapshot management
- **Logs**: execution log viewing

### 3) API + Service layer

The backend is now split into:

- **DatasetService**: dataset config read/update/statistics
- **SnapshotService**: snapshot listing, creation, deletion, and hold management
- **LogService**: log reading, filtering, and clearing

The API uses a unified JSON response format so both the plugin page and WebUI can reuse it.

---

## 📸 Screenshots

### Plugin Preview
![Plugin Preview](docs/images/plugin-preview.png)

### WebUI - Dashboard
![WebUI Dashboard](docs/images/webui-dashboard.png)

### WebUI - Dataset Edit
![WebUI Dataset Edit](docs/images/webui-dataset-edit.png)

### WebUI - Snapshot Management
![WebUI Snapshots](docs/images/webui-snapshots.png)

### WebUI - Logs
![WebUI Logs](docs/images/webui-logs.png)

---

## 🚀 Installation

### Install via URL (recommended)

Use this in the Unraid plugin manager:

```text
https://raw.githubusercontent.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/main/zfs.scheduled.snapshots.plg
```

### Manual install

1. Download the `.plg` file
2. Upload it in the Unraid plugin manager
3. Click install

---

## 📚 Usage

### Quick start

1. After installation, open **ZFS Scheduled Snapshots** from Unraid Settings
2. Review the overview on the plugin page
3. Click **WebUI** to enter the full management interface
4. Edit target dataset settings in the **Datasets** page
5. Save and let the background runner execute on schedule

### Configuration fields

- **Enable automatic snapshots**
  - turns automatic snapshots on or off for the dataset
- **Snapshot frequency**
  - 5min / 15min / hourly / daily / weekly / monthly
- **Snapshot retention count (`keep`)**
  - maximum number of regular automatic snapshots to keep before pruning the oldest ones
- **Snapshot time (`time`)**
  - execution time for daily / weekly / monthly, format `HH:MM`
- **Weekday / day (`day`)**
  - weekday for weekly, day-of-month for monthly
- **Set new snapshots as read-only (`readonly`)**
  - adds hold protection after automatic snapshot creation
- **Read-only snapshot retention days (`retain_days`)**
  - only affects immutable / held automatic snapshots, `0` means unlimited

---

## 🔧 Build

The project uses GitHub Actions for automated builds.

The current workflow packages on pushes to:

- `main`
- `dev`
- `beta`
- `feature-*`

Build output includes:

- plugin version update
- `.tgz` package generation
- root-level `.plg` update
- committing generated artifacts

---

## 📌 Current Status

The following parts are already in place:

- backend base layering (bootstrap / response / validation / services)
- plugin preview page refactor
- first WebUI version
- dataset editing
- snapshot management
- log persistence and log page
- workflow repository-name fix

Still recommended before formal release:

- continue polishing README screenshots and wording
- test upgrade scenarios on a real Unraid system
- run pre-release regression testing

---

## ⚠️ Disclaimer

This software is provided “as is”, without warranty of any kind, express or implied, including but not limited to:

- merchantability
- fitness for a particular purpose
- noninfringement

In no event shall the authors or copyright holders be liable for any claim, damages, or other liability arising from the software or its use, distribution, or other dealings.

**Always keep off-site backups of important data.**

---

## 🤝 Contributing

Issues and pull requests are welcome.

---

## 📄 License

[GPL-3.0 License](LICENSE)
