<?php
require_once 'config.php';
require_login();
$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';
$message = '';
$messageType = 'success';

// AJAX endpoint used by the admin QR scanner. The QR contains only a random token,
// never a student's name, email address, or database ID.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'scan_qr') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only an administrator can scan attendance QR codes.']);
        exit;
    }

    $postedToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
        exit;
    }

    $qrToken = trim($_POST['qr_token'] ?? '');
    if ($qrToken === '' || !preg_match('/^[a-f0-9]{64}$/', $qrToken)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This QR code is not a valid CampusConnect attendance code.']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.attendance, u.full_name, u.email, e.title AS event_title, e.event_date
         FROM registrations r
         JOIN users u ON u.id = r.user_id
         JOIN events e ON e.id = r.event_id
         WHERE r.qr_token = ? LIMIT 1'
    );
    $stmt->execute([$qrToken]);
    $registration = $stmt->fetch();

    if (!$registration) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'QR code not found. This student may not be registered for an event.']);
        exit;
    }

    if ($registration['attendance'] === 'present') {
        echo json_encode([
            'success' => true,
            'already_present' => true,
            'message' => 'Already checked in.',
            'student' => $registration['full_name'],
            'event' => $registration['event_title']
        ]);
        exit;
    }

    // Atomic update prevents two scanners from checking in the same QR at once.
    $update = $pdo->prepare("UPDATE registrations SET attendance = 'present' WHERE id = ? AND attendance <> 'present'");
    $update->execute([$registration['id']]);

    if ($update->rowCount() === 0) {
        echo json_encode([
            'success' => true,
            'already_present' => true,
            'message' => 'Already checked in.',
            'student' => $registration['full_name'],
            'event' => $registration['event_title']
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'already_present' => false,
        'message' => 'Attendance successfully marked PRESENT.',
        'student' => $registration['full_name'],
        'email' => $registration['email'],
        'event' => $registration['event_title'],
        'event_date' => $registration['event_date']
    ]);
    exit;
}

// Admin actions: generate a QR token, manually update attendance, or remove a registration.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'generate_qr') {
        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE registrations SET qr_token = ? WHERE id = ? AND (qr_token IS NULL OR qr_token = '')");
        $stmt->execute([$token, $registrationId]);
        $message = $stmt->rowCount() ? 'Unique QR code generated. The student can now view it in their account.' : 'A QR code already exists for this registration, or the registration was not found.';
        $messageType = $stmt->rowCount() ? 'success' : 'warning';
    } elseif ($action === 'attendance') {
        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $attendance = $_POST['attendance'] ?? '';
        if (in_array($attendance, ['registered','present','absent'], true)) {
            $stmt = $pdo->prepare('UPDATE registrations SET attendance = ? WHERE id = ?');
            $stmt->execute([$attendance, $registrationId]);
            $message = 'Attendance updated successfully.';
        } else {
            $message = 'Invalid attendance selection.';
            $messageType = 'danger';
        }
    } elseif ($action === 'delete_registration') {
        $stmt = $pdo->prepare('DELETE FROM registrations WHERE id = ?');
        $stmt->execute([(int)($_POST['registration_id'] ?? 0)]);
        $message = 'Registration removed.';
    }
}

// Administrators see all registrations; students/staff see only their own.
if ($isAdmin) {
    $sql = "SELECT r.id, r.qr_token, r.attendance, r.registered_at, u.full_name, u.email,
                   u.year_level, u.block, e.title AS event_title, e.event_date
            FROM registrations r
            JOIN users u ON u.id = r.user_id
            JOIN events e ON e.id = r.event_id
            ORDER BY
                CASE WHEN u.year_level IS NULL OR u.year_level = '' THEN 1 ELSE 0 END,
                u.year_level, u.block, u.full_name, e.event_date";
    $stmt = $pdo->query($sql);
} else {
    $sql = 'SELECT r.id, r.qr_token, r.attendance, r.registered_at,
                   e.title AS event_title, e.event_date, e.start_time, e.venue
            FROM registrations r
            JOIN events e ON e.id = r.event_id
            WHERE r.user_id = ?
            ORDER BY e.event_date, e.start_time';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user['id']]);
}
$registrations = $stmt->fetchAll();

// Group administrator's participant list by year, then by block/section.
$groupedRegistrations = [];
if ($isAdmin) {
    foreach ($registrations as $registration) {
        $yearGroup = trim((string)($registration['year_level'] ?? '')) ?: 'Year not specified';
        $blockGroup = trim((string)($registration['block'] ?? '')) ?: 'Block not specified';
        $groupedRegistrations[$yearGroup][$blockGroup][] = $registration;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrations | CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php if ($isAdmin): ?><script src="https://unpkg.com/html5-qrcode"></script><?php endif; ?>
</head>
<script src="assets/delete-confirm.js"></script>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand"><div class="brand-mark">CC</div><div><b>CampusConnect</b><small>EVENT PORTAL</small></div></div>
        <div class="nav-caption">WORKSPACE</div>
        <a class="nav-link" href="dashboard.php"><span>▦</span>Dashboard</a>
        <a class="nav-link" href="events.php"><span>◷</span>Events</a>
        <a class="nav-link active" href="registrations.php"><span>▤</span>Registrations</a>
        <div class="sidebar-bottom"><div class="user-mini"><div class="avatar"><?= e(strtoupper(substr($user['full_name'],0,1))) ?></div><div class="user-meta"><b><?= e($user['full_name']) ?></b><small><?= $isAdmin ? 'Admin' : 'Student / Staff' ?></small></div></div><a class="logout-btn" href="logout.php">↪ Sign out</a></div>
    </aside>
    <main class="main-area">
        <header class="topbar"><div><span class="eyebrow">SCHOOL EVENT PORTAL</span><div class="topbar-title">Registrations</div></div></header>
        <div class="content-area">
            <h1 class="page-heading"><?= $isAdmin ? 'Participant registrations' : 'My registrations' ?></h1>
            <p class="text-secondary mb-4"><?= $isAdmin ? 'Review registrations, generate student QR codes, and scan attendance.' : 'View your event registrations and show your QR code at the event entrance.' ?></p>
            <?php if ($message !== ''): ?><div class="alert alert-<?= e($messageType) ?>"><?= e($message) ?></div><?php endif; ?>

            <?php if ($isAdmin): ?>
                <section class="qr-scanner-card mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div><h2 class="h5 mb-1">QR attendance scanner</h2><p class="text-secondary small mb-0">Scan a student's event QR code to automatically mark them present.</p></div>
                        <button type="button" class="btn btn-primary" id="startScanner">Start camera scanner</button>
                    </div>
                    <div id="qr-reader" class="qr-reader-box" hidden></div>
                    <div id="scan-result" class="scan-result" aria-live="polite">Camera scanner is stopped.</div>
                </section>
            <?php endif; ?>

            <div class="table-card"><div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr>
                    <?php if ($isAdmin): ?><th>Participant</th><th>Year</th><th>Block</th><th>Event</th><th>Date</th><th>Registered</th><th>Attendance</th><th>QR code</th><th>Action</th>
                    <?php else: ?><th>Event</th><th>Date</th><th>Time</th><th>Venue</th><th>Attendance</th><th>Your QR code</th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php if (!$registrations): ?><tr><td colspan="<?= $isAdmin ? 9 : 6 ?>" class="text-center py-5 text-secondary">No registrations found.</td></tr>
                <?php elseif ($isAdmin): ?>
                    <?php foreach ($groupedRegistrations as $yearGroup => $blockGroups): ?>
                        <?php foreach ($blockGroups as $blockGroup => $blockRegistrations): ?>
                            <tr class="participant-group-row"><td colspan="9"><strong><?= e($yearGroup) ?></strong><span class="group-separator">/</span> Block <strong><?= e($blockGroup) ?></strong><span class="group-count"><?= count($blockRegistrations) ?> registration(s)</span></td></tr>
                            <?php foreach ($blockRegistrations as $registration): ?>
                                <tr>
                                    <td><?= e($registration['full_name']) ?><br><small class="text-secondary"><?= e($registration['email']) ?></small></td>
                                    <td><?= e($registration['year_level'] ?: '—') ?></td>
                                    <td><?= e($registration['block'] ?: '—') ?></td>
                                    <td><?= e($registration['event_title']) ?></td><td><?= e($registration['event_date']) ?></td><td><?= e($registration['registered_at']) ?></td>
                        <td><form method="POST" class="d-flex gap-2 align-items-center"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="attendance"><input type="hidden" name="registration_id" value="<?= (int)$registration['id'] ?>"><select class="form-select form-select-sm attendance-select" name="attendance"><option value="registered" <?= $registration['attendance']==='registered'?'selected':'' ?>>Registered</option><option value="present" <?= $registration['attendance']==='present'?'selected':'' ?>>Present</option><option value="absent" <?= $registration['attendance']==='absent'?'selected':'' ?>>Absent</option></select><button class="btn btn-sm btn-primary">Save</button></form></td>
                        <td>
                            <?php if (empty($registration['qr_token'])): ?>
                                <form method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="generate_qr"><input type="hidden" name="registration_id" value="<?= (int)$registration['id'] ?>"><button class="btn btn-sm btn-outline-primary">Generate QR</button></form>
                            <?php else: ?><span class="badge text-bg-success">Generated</span><div class="admin-qr" id="admin-qr-<?= (int)$registration['id'] ?>" data-token="<?= e($registration['qr_token']) ?>"></div><button type="button" class="btn btn-sm btn-outline-secondary download-qr" data-target="admin-qr-<?= (int)$registration['id'] ?>" data-filename="campusconnect-qr-<?= (int)$registration['id'] ?>.png">Download QR</button><?php endif; ?>
                        </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirmDeleteStudent();">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_registration">
                                            <input type="hidden" name="registration_id" value="<?= (int)$registration['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: foreach ($registrations as $registration): ?>
                    <tr>
                        <td><?= e($registration['event_title']) ?></td><td><?= e($registration['event_date']) ?></td><td><?= e(date('h:i A',strtotime($registration['start_time']))) ?></td><td><?= e($registration['venue']) ?></td>
                        <td><span class="badge text-bg-<?= $registration['attendance']==='present'?'success':($registration['attendance']==='absent'?'danger':'secondary') ?>"><?= e(ucfirst($registration['attendance'])) ?></span></td>
                        <td>
                            <?php if (!empty($registration['qr_token'])): ?>
                                <div class="student-qr" id="qr-<?= (int)$registration['id'] ?>" data-token="<?= e($registration['qr_token']) ?>"></div>
                                <small class="text-secondary">Show this code at the event.</small>
                            <?php else: ?><span class="text-secondary small">Waiting for admin to generate your QR code.</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table></div></div>
        </div>
    </main>
</div>

<script>
document.querySelectorAll('.student-qr, .admin-qr').forEach(function (element) {
    new QRCode(element, {
        text: element.dataset.token,
        width: 150,
        height: 150,
        correctLevel: QRCode.CorrectLevel.M
    });
});

document.querySelectorAll('.download-qr').forEach(function (button) {
    button.addEventListener('click', function () {
        const qrContainer = document.getElementById(button.dataset.target);
        const canvas = qrContainer ? qrContainer.querySelector('canvas') : null;
        const image = qrContainer ? qrContainer.querySelector('img') : null;
        const imageUrl = canvas ? canvas.toDataURL('image/png') : (image ? image.src : '');
        if (!imageUrl) return;
        const link = document.createElement('a');
        link.href = imageUrl;
        link.download = button.dataset.filename || 'campusconnect-qr.png';
        link.click();
    });
});
</script>

<?php if ($isAdmin): ?>
<script>
(function () {
    const startButton = document.getElementById('startScanner');
    const readerElement = document.getElementById('qr-reader');
    const resultElement = document.getElementById('scan-result');
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    let scanner = null;
    let scannerRunning = false;
    let processingScan = false;

    function showResult(message, success) {
        resultElement.textContent = message;
        resultElement.className = 'scan-result ' + (success ? 'scan-success' : 'scan-error');
    }

    async function handleScan(decodedText) {
        if (processingScan) return;
        processingScan = true;
        try {
            const formData = new FormData();
            formData.append('action', 'scan_qr');
            formData.append('csrf_token', csrfToken);
            formData.append('qr_token', decodedText);

            const response = await fetch('registrations.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            const success = response.ok && data.success;
            let message = data.message || 'Unable to process this QR code.';
            if (data.student) message += ' Student: ' + data.student + '. Event: ' + data.event + '.';
            showResult(message, success);
        } catch (error) {
            showResult('Could not contact the server. Check your connection and try again.', false);
        } finally {
            // Prevent the same QR image from being submitted many times while in view.
            window.setTimeout(function () { processingScan = false; }, 1600);
        }
    }

    startButton.addEventListener('click', async function () {
        if (scannerRunning) {
            await scanner.stop();
            scannerRunning = false;
            readerElement.hidden = true;
            startButton.textContent = 'Start camera scanner';
            showResult('Camera scanner is stopped.', true);
            return;
        }

        if (typeof Html5Qrcode === 'undefined') {
            showResult('The QR scanner library could not load. Check your internet connection.', false);
            return;
        }

        readerElement.hidden = false;
        scanner = new Html5Qrcode('qr-reader');
        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 230, height: 230 } },
                handleScan,
                function () {}
            );
            scannerRunning = true;
            startButton.textContent = 'Stop camera scanner';
            showResult('Camera is active. Point it at a student QR code.', true);
        } catch (error) {
            readerElement.hidden = true;
            showResult('Unable to open the camera. Allow camera permission or use HTTPS/localhost.', false);
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
