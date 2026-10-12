<?php
/**
 * One-click installer: creates the database, loads sql/schema.sql and fills it
 * with demo restaurants, menus, people and a few orders so every dashboard has
 * something to show. Safe to run again — it rebuilds from scratch.
 */
require_once __DIR__ . '/config.php';

$log  = [];
$fail = null;

// When INSTALL_KEY is set (as it is on the deployed copy) the installer only
// runs for someone who knows the key: install.php?key=…
$locked = INSTALL_KEY !== '' && ($_GET['key'] ?? '') !== INSTALL_KEY;

if (!$locked && ($_POST['run'] ?? '') === 'yes') {
    try {
        // 1. the database itself
        $root = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT),
            DB_USER,
            DB_PASS,
            pdo_options()
        );
        $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $log[] = 'Database `' . DB_NAME . '` ready';

        // 2. the tables
        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $sql = file_get_contents(ROOT_PATH . '/sql/schema.sql');
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
            $stmt = trim(preg_replace('/^\s*--.*$/m', '', $stmt));
            if ($stmt !== '') {
                $pdo->exec($stmt);
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sessions (
               id         VARCHAR(128) PRIMARY KEY,
               payload    MEDIUMTEXT   NOT NULL,
               updated_at DATETIME     NOT NULL
             )'
        );
        $log[] = '12 tables created';

        // 3. demo data
        require ROOT_PATH . '/sql/seed.php';
        $log = array_merge($log, seed_everything());
    } catch (Throwable $e) {
        $fail = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — Fetch</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="plain-page">
<div class="grid-bg"></div>
<div class="auth-wrap">
  <div class="card" style="max-width:560px">
    <a class="logo" href="index.php"><b>fetch</b><i>(</i>'/food'<i>)</i></a>
    <h1 class="h2" style="margin:18px 0 6px">Installer</h1>
    <p class="muted">Creates <code><?= DB_NAME ?></code>, builds the tables and loads the demo data.</p>

    <?php if ($locked): ?>
      <div class="alert alert--err"><b>Locked.</b> This installer needs the key from the host's environment variables:
        <code>install.php?key=…</code></div>
    <?php elseif ($fail): ?>
      <div class="alert alert--err"><b>Failed.</b> <?= e($fail) ?></div>
      <p class="muted sm">Is MySQL running in the XAMPP manager? Check the credentials at the top of <code>config.php</code>.</p>
    <?php elseif ($log): ?>
      <div class="alert alert--ok"><b>Done.</b> Your database is ready.</div>
      <ul class="tick-list">
        <?php foreach ($log as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
      </ul>
      <div class="row gap mt">
        <a class="btn btn--primary" href="index.php">Open Fetch →</a>
        <a class="btn btn--ghost" href="login.php">Demo logins</a>
      </div>
    <?php else: ?>
      <div class="alert alert--warn">This <b>deletes and rebuilds</b> every Fetch table. Existing orders will be lost.</div>
      <form method="post" class="mt" action="?key=<?= e($_GET['key'] ?? '') ?>">
        <input type="hidden" name="run" value="yes">
        <button class="btn btn--primary">Install now</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
