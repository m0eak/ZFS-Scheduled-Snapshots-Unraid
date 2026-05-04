# ZFS Scheduled Snapshots (Unraid Plugin)

<div align="center">

[![GitHub license](https://img.shields.io/github/license/m0eak/ZFS-Scheduled-Snapshots-Unraid)](https://github.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/blob/main/LICENSE)
[![GitHub release](https://img.shields.io/github/v/release/m0eak/ZFS-Scheduled-Snapshots-Unraid)](https://github.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/releases)
[![GitHub last commit](https://img.shields.io/github/last-commit/m0eak/ZFS-Scheduled-Snapshots-Unraid)](https://github.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/commits)

</div>

---

## ⚠️ 警告 / Warning

**中文：** 本插件目前处于早期开发阶段，未经严格测试。请务必谨慎使用，作者不对任何数据丢失负责。建议仅在非生产环境或已有完整备份的情况下测试使用。

**English：** This plugin is in early development and has NOT been strictly tested. Use at your own risk. The author is not responsible for any data loss. It is recommended to test only in non-production environments or with complete backups.

---

## 📖 语言 / Language

- **[中文](#-简介)**（默认）
- **[English](#-introduction)**

---

## 📝 简介

**ZFS Scheduled Snapshots** 是一个用于 Unraid 的 ZFS 自动定时快照管理插件，提供分层 UI 架构：轻量插件预览页 + 独立 WebUI 完整管理界面。

插件通过后台 Cron 任务每 5 分钟检查一次，并根据每个数据集独立配置的策略自动创建、保留、清理快照，支持不可变快照（ZFS Hold）和时间窗口保留。

---

## ✨ 功能特性

| 功能 | 说明 |
|------|------|
| **多周期调度** | 5分钟 / 15分钟 / 每小时 / 每天 / 每周 / 每月，共 6 种频率 |
| **时间点精确控制** | 日/周/月支持指定执行时间（HH:MM），周/月支持指定日期 |
| **不可变快照保护** | 支持为快照添加 ZFS Hold，防止误删除 |
| **双维度保留策略** | 按数量保留（普通快照） + 按天数保留（不可变快照）独立配置 |
| **自动补拍** | 错过计划执行时间（如服务器关机），下次运行自动补拍 |
| **分层 UI 架构** | 插件页概览预览 + WebUI 完整管理，兼顾原生体验与功能完整 |
| **快照管理** | WebUI 支持手动创建、删除、添加 Hold、释放 Hold |
| **执行日志** | 完整的调度执行日志，支持级别过滤和一键清空 |
| **向下兼容** | 平滑升级，已有配置、快照、Hold 全部保留 |

---

## 🏗️ 架构设计

```
┌─────────────────────────────────────────────────────┐
│  Unraid 插件页（ZFSScheduledSnapshots.page）          │
│  ─────────────────────────────────────────────────  │
│  - 纯预览模式，100% 原生 Unraid 表格风格             │
│  - 概览统计卡片 + 数据集状态表格                      │
│  - 「打开 WebUI」跳转入口                             │
└────────────────────────────┬────────────────────────┘
                             │ 跳转
                             ▼
┌─────────────────────────────────────────────────────┐
│  WebUI（独立管理界面）                                 │
│  ─────────────────────────────────────────────────  │
│  - Dashboard：全局概览统计                            │
│  - Datasets：数据集列表 + 配置编辑                    │
│  - Snapshots：快照管理（创建/删除/Hold）              │
│  - Logs：执行日志查看与管理                           │
│  - 扁平克制设计，高信息密度，无过度装饰               │
└────────────────────────────┬────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────┐
│  API 层（10 个端点）                                  │
│  ─────────────────────────────────────────────────  │
│  - 统一 JSON 响应格式（ok/data/meta/error）          │
│  - 完整参数校验                                       │
└────────────────────────────┬────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────┐
│  Service 层（3 个服务）                               │
│  ─────────────────────────────────────────────────  │
│  - DatasetService：数据集配置读写、统计              │
│  - SnapshotService：快照列表、创建、删除、Hold        │
│  - LogService：日志持久化、查询、清空                │
└────────────────────────────┬────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────┐
│  Common 层（ZFS 命令封装）                            │
│  ─────────────────────────────────────────────────  │
│  - 与 runner.php（定时调度）共享核心逻辑              │
│  - 100% 向下兼容旧版本                               │
└─────────────────────────────────────────────────────┘
```

---

## 📸 截图 / Screenshots

### 插件预览页
![插件预览页](docs/images/plugin-preview.png)
- 100% 原生 Unraid 风格
- 概览统计卡片
- 数据集状态表格
- WebUI 跳转入口

### WebUI - Dashboard
![WebUI Dashboard](docs/images/webui-dashboard.png)
- 全局统计概览
- 数据集状态列表

### WebUI - 数据集编辑
![WebUI Dataset Edit](docs/images/webui-dataset-edit.png)
- 完整配置编辑
- 频率动态字段显示

### WebUI - 快照管理
![WebUI Snapshots](docs/images/webui-snapshots.png)
- 快照列表
- Hold 状态可视化
- 手动操作按钮

### WebUI - 执行日志
![WebUI Logs](docs/images/webui-logs.png)
- 完整执行日志
- 级别过滤
- 一键清空

---

## 🚀 安装 / Installation

### 通过 URL 安装（推荐）

在 Unraid 插件管理页面，使用以下 URL 安装：

```
https://raw.githubusercontent.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/main/zfs.scheduled.snapshots.plg
```

### 手动安装

1. 下载 `.plg` 文件
2. 上传到 Unraid 插件管理页面
3. 点击安装

---

## 📚 使用说明

### 快速开始

1. 安装插件后，在 Unraid 「设置」菜单找到 **ZFS Scheduled Snapshots**
2. 插件页显示概览信息，点击右上角「打开 WebUI」进入完整管理界面
3. 在 WebUI 的「数据集」页面，编辑需要启用自动快照的数据集
4. 配置快照频率、保留数量、是否启用只读保护等参数
5. 保存后，系统会每 5 分钟检查一次，按计划执行快照

### 配置说明

| 参数 | 说明 |
|------|------|
| **启用自动快照** | 开启/关闭该数据集的自动快照功能 |
| **快照频率** | 5分钟 / 15分钟 / 每小时 / 每天 / 每周 / 每月 |
| **保留快照数量** | 最多保留多少个自动快照，超出后自动清理最旧的 |
| **快照时间** | 日/周/月频率时，指定执行的具体时间（HH:MM） |
| **星期/日期** | 周/月频率时，指定执行的星期或日期 |
| **新快照设为只读** | 自动创建的快照自动添加 ZFS Hold，防止误删除 |
| **只读快照保留天数** | 不可变快照的额外保留时间窗口，0 表示不限制 |

---

## 🔧 构建 / Build

插件使用 GitHub Actions 自动构建，当代码推送到 `main`、`dev`、`beta` 或 `feature-*` 分支时，会自动：

1. 更新版本号
2. 打包插件文件
3. 提交并推送到对应分支

---

## ⚠️ 风险免责声明 / Disclaimer

**中文：** 本软件按"原样"提供，不提供任何形式的明示或暗示担保，包括但不限于适销性、特定用途适用性和非侵权性的担保。在任何情况下，作者或版权持有人均不对因软件或软件的使用或其他交易而产生的任何索赔、损害或其他责任负责，无论是在合同、侵权或其他方面。

**请确保您了解 ZFS 快照的工作原理，并始终保持重要数据的异地备份。**

**English：** This software is provided "as is", without warranty of any kind, express or implied, including but not limited to the warranties of merchantability, fitness for a particular purpose and noninfringement. In no event shall the authors or copyright holders be liable for any claim, damages or other liability, whether in an action of contract, tort or otherwise, arising from, out of or in connection with the software or the use or other dealings in the software.

**Please make sure you understand how ZFS snapshots work, and always maintain off-site backups of important data.**

---

## 🤝 贡献 / Contributing

欢迎提交 Issue 和 Pull Request！

---

## 📄 许可证 / License

[GPL-3.0 License](LICENSE)

---

---

## 🇬🇧 Introduction (English)

**ZFS Scheduled Snapshots** is an automatic scheduled snapshot management plugin for ZFS datasets on Unraid, featuring a layered UI architecture: a lightweight plugin preview page + a standalone WebUI for full management.

The plugin checks every 5 minutes via a background Cron job, and automatically creates, retains, and cleans up snapshots according to the independently configured strategy for each dataset. It supports immutable snapshots (ZFS Hold) and time window retention.

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| **Multi-frequency Scheduling** | 5min / 15min / Hourly / Daily / Weekly / Monthly, total 6 frequencies |
| **Precise Timing Control** | Daily/Weekly/Monthly support specific execution time (HH:MM), Weekly/Monthly support specific date |
| **Immutable Snapshot Protection** | Support ZFS Hold on snapshots to prevent accidental deletion |
| **Dual Retention Strategy** | Quantity-based retention (regular snapshots) + Time-based retention (immutable snapshots) independently configurable |
| **Auto Catch-up** | If execution is missed (e.g., server off), automatically catch up on next run |
| **Layered UI Architecture** | Plugin page overview + WebUI full management, balancing native experience and feature completeness |
| **Snapshot Management** | WebUI supports manual create, delete, add Hold, release Hold |
| **Execution Logs** | Complete scheduling execution logs, with level filtering and one-click clear |
| **Backward Compatible** | Smooth upgrade, all existing configurations, snapshots, and Holds are preserved |

---

## 🏗️ Architecture Design

(Same architecture diagram as above)

---

## 🚀 Installation

Install via URL in Unraid Plugin Manager:

```
https://raw.githubusercontent.com/m0eak/ZFS-Scheduled-Snapshots-Unraid/main/zfs.scheduled.snapshots.plg
```

---

## ⚠️ Disclaimer

This software is provided "as is", without warranty of any kind. Please make sure you understand how ZFS snapshots work, and always maintain off-site backups of important data.
