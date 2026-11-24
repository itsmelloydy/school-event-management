<?php
require_once 'config.php';
require_login();
$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';
$message = '';
$messageType = 'success';

// Handle event creation, editing, deletion, and student registration on this page.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_event' && $isAdmin) {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $eventDate = $_POST['event_date'] ?? '';
            $startTime = $_POST['start_time'] ?? '';
            $venue = trim($_POST['venue'] ?? '');
            $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? 'upcoming';
            if ($title === '' || $eventDate === '' || $startTime === '' || $venue === '' || !$capacity || $capacity < 1 || !in_array($status, ['upcoming','completed','cancelled'], true)) {
                throw new RuntimeException('Please complete all required event fields. Capacity must be at least 1.');
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE events SET title=?, description=?, event_date=?, start_time=?, venue=?, capacity=?, status=? WHERE id=?');
                $stmt->execute([$title,$description,$eventDate,$startTime,$venue,$capacity,$status,$id]);
                $message = 'Event updated successfully.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO events (title,description,event_date,start_time,venue,capacity,status,created_by) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$title,$description,$eventDate,$startTime,$venue,$capacity,$status,$user['id']]);
                $message = 'Event created successfully.';
            }
        } elseif ($action === 'delete_event' && $isAdmin) {
            $stmt = $pdo->prepare('DELETE FROM events WHERE id=?');
            $stmt->execute([(int)($_POST['id'] ?? 0)]);
            $message = $stmt->rowCount() ? 'Event deleted successfully.' : 'Event not found.';
        } elseif ($action === 'register_event' && !$isAdmin) {
            $eventId = (int)($_POST['event_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, event_date, status, capacity, (SELECT COUNT(*) FROM registrations WHERE event_id=events.id) AS registered_count FROM events WHERE id=?");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();
            if (!$event || $event['status'] !== 'upcoming' || $event['event_date'] < date('Y-m-d')) {
                throw new RuntimeException('This event is not open for registration.');
            }
            if ((int)$event['registered_count'] >= (int)$event['capacity']) {
                throw new RuntimeException('This event has reached its registration capacity.');
            }
            $stmt = $pdo->prepare('INSERT INTO registrations (event_id,user_id) VALUES (?,?)');
            $stmt->execute([$eventId,$user['id']]);
            $message = 'You have successfully registered for this event.';
        }
    } catch (PDOException $e) {
        $message = $e->getCode() === '23000' ? 'You are already registered for this event or the record conflicts with existing data.' : 'A database error occurred. Please try again.';
        $messageType = 'danger';
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Load events directly from MySQL for display.
if ($isAdmin) {
    $stmt = $pdo->query('SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS registered_count FROM events e ORDER BY e.event_date DESC, e.start_time DESC');
} else {
    $stmt = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS registered_count, (SELECT COUNT(*) FROM registrations mine WHERE mine.event_id=e.id AND mine.user_id=?) AS already_registered FROM events e ORDER BY e.event_date, e.start_time");
    $stmt->execute([$user['id']]);
}
$events = $stmt->fetchAll();
$pageTitle = 'Events';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Events | CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<script src="assets/delete-confirm.js"></script>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="brand-mark">CC</div><div><b>CampusConnect</b><small>EVENT PORTAL</small></div></div>
        <div class="nav-caption">WORKSPACE</div>
        <a class="nav-link" href="dashboard.php"><span>▦</span>Dashboard</a>
        <a class="nav-link active" href="events.php"><span>◷</span>Events</a>
        <a class="nav-link" href="registrations.php"><span>▤</span>Registrations</a>
        <div class="sidebar-bottom"><div class="user-mini"><div class="avatar"><?= e(strtoupper(substr($user['full_name'],0,1))) ?></div><div class="user-meta"><b><?= e($user['full_name']) ?></b><small><?= $isAdmin ? 'Admin' : 'Student / Staff' ?></small></div></div><a class="logout-btn" href="logout.php">↪ Sign out</a></div>
    </aside>
    <main class="main-area">
        <header class="topbar"><div><span class="eyebrow">SCHOOL EVENT PORTAL</span><div class="topbar-title"><?= e($pageTitle) ?></div></div></header>
        <div class="content-area">
            <div class="section-heading"><div><h1 class="page-heading">Events</h1><p><?= $isAdmin ? 'Create and manage school activities.' : 'Browse school activities and register for events.' ?></p></div>
                <?php if ($isAdmin): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#eventModal">＋ Add event</button><?php endif; ?>
            </div>
            <?php if ($message !== ''): ?><div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div><?php endif; ?>
            <div class="toolbar"><input class="form-control" id="eventSearch" placeholder="Search events or venues"><select class="form-select" id="statusFilter"><option value="">All statuses</option><option value="upcoming">Upcoming</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
            <?php if (!$events): ?><div class="empty-state"><b>No events found</b>There are no events to display right now.</div><?php else: ?>
                <div class="row g-3" id="eventsGrid">
                    <?php foreach ($events as $event): ?>
                        <div class="col-md-6 col-xl-4 event-item" data-search="<?= e(strtolower($event['title'].' '.$event['venue'].' '.$event['description'])) ?>" data-status="<?= e($event['status']) ?>">
                            <article class="event-card">
                                <div class="event-top"><span class="badge-status status-<?= e($event['status']) ?>"><?= e($event['status']) ?></span><span class="capacity"><?= (int)$event['registered_count'] ?>/<?= (int)$event['capacity'] ?> registered</span></div>
                                <h3><?= e($event['title']) ?></h3><p class="event-description"><?= e($event['description']) ?></p>
                                <div class="event-meta"><span><?= e($event['event_date']) ?></span><span><?= e(date('h:i A',strtotime($event['start_time']))) ?></span><span><?= e($event['venue']) ?></span></div>
                                <?php if ($isAdmin): ?>
                                    <div class="event-actions">
                                        <button class="btn btn-sm btn-outline-primary edit-event" type="button" data-bs-toggle="modal" data-bs-target="#eventModal" data-id="<?= (int)$event['id'] ?>" data-title="<?= e($event['title']) ?>" data-description="<?= e($event['description']) ?>" data-date="<?= e($event['event_date']) ?>" data-time="<?= e($event['start_time']) ?>" data-venue="<?= e($event['venue']) ?>" data-capacity="<?= (int)$event['capacity'] ?>" data-status="<?= e($event['status']) ?>">Edit</button>
                                        <form method="POST" onsubmit="return confirmDeleteEvent();">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_event">
                                            <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                <?php elseif ((int)$event['already_registered'] > 0): ?>
                                    <span class="badge text-bg-success">Already registered</span>
                                <?php elseif ($event['status'] === 'upcoming' && $event['event_date'] >= date('Y-m-d') && (int)$event['registered_count'] < (int)$event['capacity']): ?>
                                    <form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register_event"><input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>"><button class="btn btn-primary btn-sm">Register</button></form>
                                <?php else: ?><span class="text-secondary small">Registration unavailable</span><?php endif; ?>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php if ($isAdmin): ?>
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="events.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalTitle">Create event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
    <div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_event">
    <input type="hidden" name="id" id="eventId" value="0">
    <label class="form-label" for="eventTitle">Event title</label>
    <input class="form-control mb-3" id="eventTitle" name="title" maxlength="150" required>
    <label class="form-label" for="eventDescription">Description</label>
    <textarea class="form-control mb-3" id="eventDescription" name="description" rows="3"></textarea>
    <div class="row"><div class="col-md-6"><label class="form-label" for="eventDate">Event date</label>
    <input class="form-control mb-3" id="eventDate" type="date" name="event_date" required></div>
    <div class="col-md-6">
        <label class="form-label" for="eventTime">Start time</label>
        <input class="form-control mb-3" id="eventTime" type="time" name="start_time" required>
    </div>
</div>
    <label class="form-label" for="eventVenue">Venue</label>
    <input class="form-control mb-3" id="eventVenue" name="venue" maxlength="150" required>
    <div class="row">
        <div class="col-md-6"><label class="form-label" for="eventCapacity">Capacity</label>
    <input class="form-control mb-3" id="eventCapacity" type="number" name="capacity" min="1" required>
        </div>
<div class="col-md-6">
    <label class="form-label" for="eventStatus">Status</label>
    <select class="form-select mb-3" id="eventStatus" name="status">
        <option value="upcoming">Upcoming</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option></select></div></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
    <button class="btn btn-primary" type="submit">Save event</button>
</div>
</form>
</div>
</div>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const searchInput=document.getElementById('eventSearch');const statusFilter=document.getElementById('statusFilter');
function filterEvents(){const q=(searchInput?.value||'').toLowerCase();const status=statusFilter?.value||'';document.querySelectorAll('.event-item').forEach(card=>{const matchesText=card.dataset.search.includes(q);const matchesStatus=!status||card.dataset.status===status;card.style.display=matchesText&&matchesStatus?'':'none';});}
searchInput?.addEventListener('input',filterEvents);statusFilter?.addEventListener('change',filterEvents);
document.querySelectorAll('.edit-event').forEach(button=>button.addEventListener('click',()=>{document.getElementById('eventModalTitle').textContent='Edit event';document.getElementById('eventId').value=button.dataset.id;document.getElementById('eventTitle').value=button.dataset.title;document.getElementById('eventDescription').value=button.dataset.description;document.getElementById('eventDate').value=button.dataset.date;document.getElementById('eventTime').value=button.dataset.time;document.getElementById('eventVenue').value=button.dataset.venue;document.getElementById('eventCapacity').value=button.dataset.capacity;document.getElementById('eventStatus').value=button.dataset.status;}));
document.querySelector('[data-bs-target="#eventModal"]:not(.edit-event)')?.addEventListener('click',()=>{document.getElementById('eventModalTitle').textContent='Create event';document.getElementById('eventId').value='0';document.querySelector('#eventModal form').reset();});
</script>
</body>
</html>
