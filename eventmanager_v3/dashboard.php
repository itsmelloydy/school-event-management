<?php
require_once 'config.php';
require_login();
$user = $_SESSION['user'];
$pageTitle = 'Dashboard';

// Dashboard summary queries. These values come from the database, not sample cards.
$totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$upcomingEvents = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming' AND event_date >= CURDATE()")->fetchColumn();
if ($user['role'] === 'admin') {
    $totalRegistrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
    $eventQuery = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registered_count FROM events e WHERE e.status = 'upcoming' AND e.event_date >= CURDATE() ORDER BY e.event_date, e.start_time LIMIT 3");
    $eventQuery->execute();
} else {
    $count = $pdo->prepare('SELECT COUNT(*) FROM registrations WHERE user_id = ?');
    $count->execute([$user['id']]);
    $totalRegistrations = (int)$count->fetchColumn();
    $eventQuery = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registered_count FROM events e JOIN registrations mine ON mine.event_id = e.id WHERE mine.user_id = ? AND e.status = 'upcoming' AND e.event_date >= CURDATE() ORDER BY e.event_date, e.start_time LIMIT 3");
    $eventQuery->execute([$user['id']]);
}
$upcomingList = $eventQuery->fetchAll();

function page_header(string $title, array $user): void { ?>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="brand-mark">CC</div><div><b>CampusConnect</b><small>EVENT PORTAL</small></div></div>
        <div class="nav-caption">WORKSPACE</div>
        <a class="nav-link active" href="dashboard.php"><span>▦</span>Dashboard</a>
        <a class="nav-link" href="events.php"><span>◷</span>Events</a>
        <a class="nav-link" href="registrations.php"><span>▤</span>Registrations</a>
        <div class="sidebar-bottom">
            <div class="user-mini"><div class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div><div class="user-meta"><b><?= e($user['full_name']) ?></b><small><?= $user['role'] === 'admin' ? 'Admin' : 'Student / Staff' ?></small></div></div>
            <a class="logout-btn" href="logout.php">↪ Sign out</a>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar"><div><span class="eyebrow">SCHOOL EVENT PORTAL</span><div class="topbar-title"><?= e($title) ?></div></div></header>
        <div class="content-area">
<?php }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<?php page_header($pageTitle, $user); ?>
    <div class="welcome-row">
        <div>
            <h1 class="page-heading">Welcome, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h1>
            <p>Here's what's happening around your campus.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-card"><div class="stat-label">Total events</div><div class="stat-value"><?= $totalEvents ?></div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="stat-label">Upcoming events</div><div class="stat-value"><?= $upcomingEvents ?></div></div></div>
        <div class="col-md-4"><div class="stat-card"><div class="stat-label"><?= $user['role'] === 'admin' ? 'Registrations' : 'My registrations' ?></div><div class="stat-value"><?= $totalRegistrations ?></div></div></div>
    </div>

    <div class="section-heading">
        <div><h2><?= $user['role'] === 'admin' ? 'Upcoming events' : 'My upcoming events' ?></h2><p>Keep an eye on what's coming up.</p></div>
        <a href="events.php">View all events →</a>
    </div>

    <?php if (!$upcomingList): ?>
        <p class="empty-inline">No upcoming events.</p>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($upcomingList as $event): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="event-card">
                        <div class="event-top"><span class="badge-status status-upcoming">upcoming</span><span class="capacity"><?= (int)$event['registered_count'] ?>/<?= (int)$event['capacity'] ?> registered</span></div>
                        <h3><?= e($event['title']) ?></h3>
                        <p class="event-description"><?= e($event['description']) ?></p>
                        <div class="event-meta"><span><?= e($event['event_date']) ?></span><span><?= e(date('h:i A', strtotime($event['start_time']))) ?></span><span><?= e($event['venue']) ?></span></div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
