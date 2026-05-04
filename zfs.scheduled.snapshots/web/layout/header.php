<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZFS Scheduled Snapshots - WebUI</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>ZFS Scheduled Snapshots</h1>
            <nav class="nav">
                <a href="index.php" class="nav-link <?php echo ($currentPage ?? 'index') === 'index' ? 'active' : ''; ?>">概览</a>
                <a href="datasets.php" class="nav-link <?php echo ($currentPage ?? '') === 'datasets' ? 'active' : ''; ?>">数据集</a>
                <a href="snapshots.php" class="nav-link <?php echo ($currentPage ?? '') === 'snapshots' ? 'active' : ''; ?>">快照</a>
                <a href="logs.php" class="nav-link <?php echo ($currentPage ?? '') === 'logs' ? 'active' : ''; ?>">日志</a>
                <a href="../ZFSScheduledSnapshots.page" class="nav-link">插件页</a>
            </nav>
        </header>
        <main class="content">
