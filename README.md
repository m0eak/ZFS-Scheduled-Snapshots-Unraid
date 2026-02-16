# ZFS Scheduled Snapshots (Unraid Plugin)

⚠️ **警告：本插件目前处于早期开发阶段，未经严格测试。请务必谨慎使用，作者不对任何数据丢失负责。建议仅在非生产环境或已有完整备份的情况下测试使用。**

⚠️ **WARNING: This plugin is in early development and has NOT been strictly tested. Use at your own risk. The author is not responsible for any data loss. It is recommended to test only in non-production environments or with complete backups.**

## 简介 / Introduction

**ZFS Scheduled Snapshots** 是一个用于 Unraid 的插件，旨在为 ZFS 数据集提供自动化的定时快照功能。它通过后台 Cron 任务定期检查，并根据配置的策略自动创建和清理快照。

## 功能 / Features

- **多周期支持**：支持多种快照频率，并可针对长周期任务设定精确时间：
  - **5min / 15min / Hourly**：基于时间间隔执行。
  - **Daily**：每天在指定时间（如 10:00）执行。
  - **Weekly**：每周在指定的一天（如周一）和指定时间执行。
  - **Monthly**：每月在指定日期（如 1号）和指定时间执行。
- **自动补拍**：如果错过了计划的执行时间（例如服务器关机），插件会在下次运行时自动检测并在当天/当周/当月内补拍一次。
- **自动清理**：支持配置保留快照的数量，自动删除过期的旧快照。
- **轻量级**：基于 PHP 脚本和原生 ZFS 命令运行。

## 安装 / Installation

可以通过 Unraid 的插件管理页面安装。
(目前需手动安装或通过 URL 安装)

URL: `https://raw.githubusercontent.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/main/zfs.scheduled.snapshots.plg`

## 风险免责声明 / Disclaimer

本软件按“原样”提供，不提供任何形式的明示或暗示担保，包括但不限于适销性、特定用途适用性和非侵权性的担保。在任何情况下，作者或版权持有人均不对因软件或软件的使用或其他交易而产生的任何索赔、损害或其他责任负责，无论是在合同、侵权或其他方面。

**请确保您了解 ZFS 快照的工作原理，并始终保持重要数据的异地备份。**
