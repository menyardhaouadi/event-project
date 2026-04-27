<?php 
include 'connect.php'; 
include 'notification_bootstrap.php';
session_start();

$admin_id = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

if (!$admin_id) {
    header('Location: login.php');
    exit();
}

if ($role !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

ensure_notification_schema($conn);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$myEvents = $conn->query("SELECT * FROM events WHERE admin_id=$admin_id ORDER BY date ASC");
$allEvents = $conn->query("SELECT * FROM events ORDER BY date ASC");

$myCount = $myEvents ? $myEvents->num_rows : 0;
$totalCount = $allEvents ? $allEvents->num_rows : 0;
$joinUnread = unread_join_count($conn, $admin_id);
$dmUnread = unread_dm_count($conn, $admin_id);
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css?v=unreal1">
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="page-app">
<main class="site-shell app-shell">
    <header class="topbar">
        <div>
            <div class="brand-mark">EM</div>
            <h1 class="page-title">Admin control center for your events.</h1>
            <p class="page-subtitle">Create events, manage the ones you own, and keep an eye on the full event catalog from a single polished dashboard.</p>
        </div>

        <div class="topbar-actions">
            <div class="notification-menu">
                <button class="btn btn-secondary btn-with-dot event-notification-button<?php echo $joinUnread > 0 ? ' has-unread' : ''; ?>" type="button" id="eventNotificationButton">
                    Notifications
                    <span class="notification-count notification-count-alert<?php echo $joinUnread > 0 ? ' is-visible' : ''; ?>" id="eventNotificationBadge"><?php echo $joinUnread > 99 ? '99+' : $joinUnread; ?></span>
                </button>
                <div class="notification-popover" id="eventNotificationPanel" hidden>
                    <div class="notification-popover-head">
                        <div>
                            <strong>Event joins</strong>
                            <small id="eventNotificationSummary"><?php echo $joinUnread; ?> unread update<?php echo $joinUnread === 1 ? '' : 's'; ?></small>
                        </div>
                        <span id="eventNotificationCount"><?php echo $joinUnread; ?> new</span>
                    </div>
                    <div class="notification-list" id="eventNotificationList">
                        <p class="notification-empty">No new joins yet.</p>
                    </div>
                </div>
            </div>
            <a class="btn btn-secondary btn-with-dot dm-notification-button<?php echo $dmUnread > 0 ? ' has-unread' : ''; ?>" href="chat.php" id="dmNotificationButton">
                DM
                <span class="notification-count notification-count-dm<?php echo $dmUnread > 0 ? ' is-visible' : ''; ?>" id="dmNotificationCount"><?php echo $dmUnread > 99 ? '99+' : $dmUnread; ?></span>
            </a>
            <a class="btn btn-secondary" href="dashboard.php">View User Feed</a>
            <a class="btn btn-ghost" href="logout.php">Logout</a>
        </div>
    </header>

    <section class="stats-row">
        <article class="metric-card">
            <span class="metric-value"><?php echo $myCount; ?></span>
            <span class="metric-label">Events currently owned by your admin account.</span>
        </article>

        <article class="metric-card">
            <span class="metric-value"><?php echo $totalCount; ?></span>
            <span class="metric-label">Events visible across the full platform.</span>
        </article>

        <article class="metric-card">
            <span class="metric-value">Fast</span>
            <span class="metric-label">Creation, editing, and review are now separated into cleaner sections.</span>
        </article>
    </section>

    <section class="page-grid">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Create a new event</h2>
                    <p class="panel-subtitle">Use this form to publish a new item to the platform.</p>
                </div>
            </div>

            <form action="create_event.php" method="POST" class="form-stack">
                <div class="field">
                    <label for="title">Event name</label>
                    <input id="title" name="title" placeholder="Enter the event title" required>
                </div>

                <div class="field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Write a clear event description" required></textarea>
                </div>

                <div class="field">
                    <label for="date">Event date</label>
                    <input id="date" type="date" name="date" required>
                </div>

                <div class="field">
                    <label for="location">Event location</label>
                    <input id="location" name="location" placeholder="Search a place in Tunisia" value="Tunis" required>
                    <input id="latitude" name="latitude" type="hidden">
                    <input id="longitude" name="longitude" type="hidden">
                </div>

                <div class="field">
                    <div class="location-toolbar">
                        <button class="btn btn-secondary btn-sm" type="button" id="preview-location-btn">Preview Location</button>
                        <span class="section-note" id="location-status">Search a place to preview it on the map before publishing. You can also click the map to fine-tune the exact spot.</span>
                    </div>

                    <div class="event-map event-map-picker" id="location-picker-map"></div>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Add Event</button>
                </div>
                <div class="field">
    <label for="category">Category</label>
    <select id="category" name="category" required>
        <option value="">Select category</option>
        <option value="hackathon">Hackathon</option>
        <option value="workshop">Workshop</option>
        <option value="adventure">Adventure</option>
    </select>
</div>
<input type="time" name="start_time" required>
<input type="time" name="end_time" required>
<input type="number" name="max_participants" placeholder="Max participants" required>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">My events</h2>
                    <p class="panel-subtitle">Only the events created by your account appear in this section.</p>
                </div>
                <span class="badge"><?php echo $myCount; ?> owned</span>
            </div>

            <?php if ($myCount === 0): ?>
                <div class="empty-state">You have not created any events yet. Use the form to publish your first one.</div>
            <?php else: ?>
                <div class="event-grid">
                    <?php while ($row = $myEvents->fetch_assoc()): ?>
                        <article class="event-card">
                            <div class="event-card-header">
                                <h3 class="event-title"><?php echo e($row['title']); ?></h3>
                                <span class="badge"><?php echo e($row['date']); ?></span>
                            </div>

                            <p class="event-description"><?php echo e($row['description']); ?></p>

                            <div class="meta-list">
                                <div class="meta-row">
                                    <span>Date</span>
                                    <strong><?php echo e($row['date']); ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>Admin ID</span>
                                    <strong>#<?php echo e($row['admin_id']); ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>Location</span>
                                    <strong><?php echo e($row['location'] ?? 'Tunis'); ?></strong>
                                </div>
                            </div>

                            <div class="inline-actions">
                                <a class="btn btn-secondary btn-sm" href="edit_event.php?id=<?php echo e($row['id']); ?>">Edit</a>
                                <a class="btn btn-danger btn-sm" href="delete_event.php?id=<?php echo e($row['id']); ?>">Delete</a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel panel-wide">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">All events on the platform</h2>
                    <p class="panel-subtitle">You can review everything here, and edit or delete only the events that belong to your account.</p>
                </div>
                <span class="badge"><?php echo $totalCount; ?> total</span>
            </div>

            <?php if ($totalCount === 0): ?>
                <div class="empty-state">There are no events in the system yet.</div>
            <?php else: ?>
                <div class="event-grid">
                    <?php while ($row = $allEvents->fetch_assoc()): ?>
                        <article class="event-card">
                            <div class="event-card-header">
                                <h3 class="event-title"><?php echo e($row['title']); ?></h3>
                                <span class="badge"><?php echo e($row['date']); ?></span>
                            </div>

                            <p class="event-description"><?php echo e($row['description']); ?></p>

                            <div class="meta-list">
                                <div class="meta-row">
                                    <span>Date</span>
                                    <strong><?php echo e($row['date']); ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>Admin ID</span>
                                    <strong>#<?php echo e($row['admin_id']); ?></strong>
                                </div>
                                <div class="meta-row">
                                    <span>Location</span>
                                    <strong><?php echo e($row['location'] ?? 'Tunis'); ?></strong>
                                </div>
                            </div>

                            <?php if ($row['admin_id'] == $admin_id): ?>
                                <div class="inline-actions">
                                    <a class="btn btn-secondary btn-sm" href="edit_event.php?id=<?php echo e($row['id']); ?>">Edit</a>
                                    <a class="btn btn-danger btn-sm" href="delete_event.php?id=<?php echo e($row['id']); ?>">Delete</a>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
const locationInput = document.getElementById('location');
const latitudeInput = document.getElementById('latitude');
const longitudeInput = document.getElementById('longitude');
const locationStatus = document.getElementById('location-status');
const previewLocationButton = document.getElementById('preview-location-btn');
const pickerMap = L.map('location-picker-map').setView([36.8065, 10.1815], 11);

L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
}).addTo(pickerMap);

let pickerMarker = null;

function setPickerLocation(lat, lon, label) {
    pickerMap.setView([lat, lon], 13);
    latitudeInput.value = Number(lat).toFixed(7);
    longitudeInput.value = Number(lon).toFixed(7);

    if (!pickerMarker) {
        pickerMarker = L.marker([lat, lon]).addTo(pickerMap);
    } else {
        pickerMarker.setLatLng([lat, lon]);
    }

    pickerMarker.bindPopup(label).openPopup();
    locationStatus.textContent = "Selected location: " + label;

    setTimeout(() => {
        pickerMap.invalidateSize();
    }, 50);
}

pickerMap.on('click', (event) => {
    const label = locationInput.value.trim() || 'Selected location';
    setPickerLocation(event.latlng.lat, event.latlng.lng, label);
    locationStatus.textContent = "Pinned exact spot for " + label + ".";
});

function previewLocation() {
    const query = locationInput.value.trim();

    if (!query) {
        locationStatus.textContent = 'Enter a location first.';
        return;
    }

    locationStatus.textContent = 'Searching location...';

    fetch('geocode.php?q=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                locationStatus.textContent = data.message || 'Location not found.';
                return;
            }

            locationInput.value = query;
            setPickerLocation(data.lat, data.lon, data.display_name || query);
        })
        .catch(() => {
            locationStatus.textContent = 'Could not load the map preview right now.';
        });
}

previewLocationButton.addEventListener('click', previewLocation);
locationInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        previewLocation();
    }
});

previewLocation();

const notificationButton = document.getElementById('eventNotificationButton');
const notificationPanel = document.getElementById('eventNotificationPanel');
const notificationBadge = document.getElementById('eventNotificationBadge');
const dmNotificationButton = document.getElementById('dmNotificationButton');
const dmNotificationCount = document.getElementById('dmNotificationCount');
const notificationCount = document.getElementById('eventNotificationCount');
const notificationSummary = document.getElementById('eventNotificationSummary');
const notificationList = document.getElementById('eventNotificationList');

function formatCount(value) {
    const count = Number(value) || 0;
    return count > 99 ? '99+' : String(count);
}

function setUnreadBadge(button, badge, count) {
    const hasUnread = Number(count) > 0;

    if (button) {
        button.classList.toggle('has-unread', hasUnread);
    }

    if (badge) {
        badge.textContent = formatCount(count);
        badge.classList.toggle('is-visible', hasUnread);
    }
}

function formatNotificationTime(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value.replace(' ', 'T'));

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function renderJoinNotifications(items) {
    if (!notificationList) {
        return;
    }

    if (!items || items.length === 0) {
        notificationList.innerHTML = '<p class="notification-empty">No joins yet.</p>';
        return;
    }

    const escapeHtml = value => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    notificationList.innerHTML = items.map(item => {
        const username = escapeHtml(item.username || 'Someone');
        const email = escapeHtml(item.email || '');
        const title = escapeHtml(item.title || 'your event');
        const date = escapeHtml(item.date || 'Date not set');
        const location = escapeHtml(item.location || 'Location not set');
        const time = formatNotificationTime(item.created_at);
        const isUnread = Number(item.is_read) === 0;

        return `
            <div class="notification-item${isUnread ? ' notification-item-unread' : ''}">
                <div class="notification-item-top">
                    <strong>${username}</strong>
                    ${isUnread ? '<span class="notification-new-label">New</span>' : ''}
                </div>
                <span>${email}</span>
                <span>Joined <strong>${title}</strong></span>
                <small>${date} · ${location}</small>
                <small>${escapeHtml(time)}</small>
            </div>
        `;
    }).join('');
}

function refreshNotificationDots() {
    fetch('notification_status.php', { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                return;
            }

            const joinUnread = Number(data.join_unread) || 0;
            const hasUnreadDm = Number(data.dm_unread) > 0;

            setUnreadBadge(notificationButton, notificationBadge, joinUnread);
            setUnreadBadge(dmNotificationButton, dmNotificationCount, data.dm_unread);

            if (notificationCount) {
                notificationCount.textContent = joinUnread + ' new';
            }

            if (notificationSummary) {
                notificationSummary.textContent = joinUnread + ' unread update' + (joinUnread === 1 ? '' : 's');
            }

            renderJoinNotifications(data.join_notifications || []);
        })
        .catch(() => {});
}

if (notificationButton && notificationPanel) {
    notificationButton.addEventListener('click', () => {
        const willOpen = notificationPanel.hidden;
        notificationPanel.hidden = !willOpen;

        if (willOpen) {
            fetch('notification_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_joins_read'
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.ok) {
                        return;
                    }

                    setUnreadBadge(notificationButton, notificationBadge, 0);
                    notificationCount.textContent = '0 new';
                    notificationSummary.textContent = '0 unread updates';
                    renderJoinNotifications(data.join_notifications || []);
                })
                .catch(() => {});
        }
    });
}

refreshNotificationDots();
setInterval(refreshNotificationDots, 4000);
</script>
</body>
</html>
