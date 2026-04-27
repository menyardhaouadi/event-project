<?php
include 'connect.php';
include 'notification_bootstrap.php';
session_start();

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

ensure_notification_schema($conn);

$query = "SELECT * FROM events WHERE 1";

if ($search) {
    $query .= " AND title LIKE '%$search%'";
}

if ($category) {
    $query .= " AND category='$category'";
}

$query .= " ORDER BY date ASC";

$res = $conn->query($query);
$totalEvents = $res ? $res->num_rows : 0;
$dmUnread = unread_dm_count($conn, $user_id);
$joinUnread = $role === 'admin' ? unread_join_count($conn, $user_id) : 0;

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css?v=unreal1">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="page-app">

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<main class="site-shell app-shell">

<header class="topbar">
    <div class="topbar-actions">
        <?php if ($role == 'admin'): ?>
            <a class="btn btn-secondary btn-with-dot" href="admin.php" id="adminPanelNotificationButton">
                Admin Panel
                <span class="notification-count notification-count-alert<?php echo $joinUnread > 0 ? ' is-visible' : ''; ?>" id="adminPanelNotificationCount"><?php echo $joinUnread > 99 ? '99+' : $joinUnread; ?></span>
            </a>
        <?php endif; ?>
        <a class="btn btn-secondary btn-with-dot dm-notification-button<?php echo $dmUnread > 0 ? ' has-unread' : ''; ?>" href="chat.php" id="dmNotificationButton">
            DM
            <span class="notification-count notification-count-dm<?php echo $dmUnread > 0 ? ' is-visible' : ''; ?>" id="dmNotificationCount"><?php echo $dmUnread > 99 ? '99+' : $dmUnread; ?></span>
        </a>
        <a class="btn btn-ghost" href="logout.php">Logout</a>
    </div>
</header>

<section class="panel">
    <form method="GET" class="form-stack">
        <input name="search" placeholder="Search by name" value="<?php echo e($search); ?>">

        <select name="category">
            <option value="">All categories</option>
            <option value="hackathon" <?php if ($category=='hackathon') echo 'selected'; ?>>Hackathon</option>
            <option value="workshop" <?php if ($category=='workshop') echo 'selected'; ?>>Workshop</option>
            <option value="adventure" <?php if ($category=='adventure') echo 'selected'; ?>>Adventure</option>
        </select>

        <button class="btn btn-primary">Search</button>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Event feed</h2>
        <span class="badge"><?php echo $totalEvents; ?> listed</span>
    </div>

<?php if ($totalEvents === 0): ?>
    <div class="empty-state">No events found.</div>
<?php else: ?>

<div class="event-grid">

<?php while ($row = $res->fetch_assoc()): ?>

<?php
$count = $conn->query("SELECT COUNT(*) as c FROM event_participants WHERE event_id=".$row['id'])->fetch_assoc()['c'];

$joined = $conn->query("SELECT * FROM event_participants WHERE user_id=$user_id AND event_id=".$row['id']);
$participant = $joined ? $joined->fetch_assoc() : null;
$token = $participant['token'] ?? null;

$eventLocation = trim((string)($row['location'] ?? ''));
$eventLatitude = isset($row['latitude']) ? (float)$row['latitude'] : null;
$eventLongitude = isset($row['longitude']) ? (float)$row['longitude'] : null;
$hasLocation = ($eventLatitude !== null && $eventLongitude !== null);
?>

<article class="event-card">

    <div class="event-card-header">
        <h3 class="event-title"><?php echo e($row['title']); ?></h3>
        <span class="badge"><?php echo e($row['date']); ?></span>
    </div>

    <p class="event-description"><?php echo e($row['description']); ?></p>

    <div class="meta-list">
        <div class="meta-row">
            <span>Category</span>
            <strong><?php echo e($row['category']); ?></strong>
        </div>

        <div class="meta-row">
            <span>Time</span>
            <strong><?php echo e($row['start_time']); ?> - <?php echo e($row['end_time']); ?></strong>
        </div>

        <div class="meta-row">
            <span>Participants</span>
            <strong><?php echo $count; ?> / <?php echo e($row['max_participants']); ?></strong>
        </div>

        <div class="meta-row">
            <span>Admin</span>
            <strong>#<?php echo e($row['admin_id']); ?></strong>
        </div>

        <?php if ($eventLocation): ?>
        <div class="meta-row">
            <span>Location</span>
            <strong><?php echo e($eventLocation); ?></strong>
        </div>
        <?php endif; ?>

        <div class="meta-row">
            <span>Weather</span>
            <strong id="weather-<?php echo $row['id']; ?>">Loading...</strong>
        </div>
    </div>

    <div class="event-actions">

        <?php if ($token): ?>
            <span class="status-pill status-pill-success">Joined</span>
        <?php elseif ($count < $row['max_participants']): ?>
            <a class="btn btn-primary btn-sm" href="join_event.php?id=<?php echo $row['id']; ?>">Join</a>
        <?php else: ?>
            <span class="status-pill status-pill-danger">Full</span>
        <?php endif; ?>

        <button class="btn btn-secondary btn-sm" onclick="toggleParticipants(<?php echo $row['id']; ?>)">
            See Participants
        </button>

        <?php if ($hasLocation): ?>
        <button id="map-btn-<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm"
            onclick="toggleMap(<?php echo $row['id']; ?>, <?php echo $eventLatitude; ?>, <?php echo $eventLongitude; ?>)">
            Show Map
        </button>
        <?php endif; ?>

    </div>

    <div id="participants-<?php echo $row['id']; ?>"></div>

    <?php if ($hasLocation): ?>
    <div id="map-<?php echo $row['id']; ?>" style="height:300px; display:none;"></div>
    <?php endif; ?>

    <!-- ✅ QR BUTTON UNDER MAP -->
    <?php if ($token): ?>
    <button id="qr-btn-<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm"
        onclick="toggleQR(<?php echo $row['id']; ?>, '<?php echo $token; ?>')">
        Show QR Code
    </button>

    <div id="qr-container-<?php echo $row['id']; ?>" style="display:none; margin-top:10px;"></div>
    <?php endif; ?>

</article>

<?php endwhile; ?>

</div>
<?php endif; ?>
</section>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// QR TOGGLE
function toggleQR(id, token){
    const div = document.getElementById("qr-container-"+id);
    const btn = document.getElementById("qr-btn-"+id);

    if(div.style.display==="none"){
        div.style.display="block";
        btn.innerText="Hide QR Code";

        if(!div.dataset.loaded){
            new QRCode(div,{
                text:"http://localhost/event-project/verify.php?token="+token,
                width:120,
                height:120
            });
            div.dataset.loaded=true;
        }

    } else {
        div.style.display="none";
        btn.innerText="Show QR Code";
    }
}

// MAP
function toggleMap(id,lat,lng){
    const mapDiv=document.getElementById("map-"+id);
    const btn=document.getElementById("map-btn-"+id);

    if(mapDiv.style.display==="none"){
        mapDiv.style.display="block";
        btn.innerText="Hide Map";

        if(!mapDiv.dataset.loaded){
            const map=L.map(mapDiv).setView([lat,lng],13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            L.marker([lat,lng]).addTo(map);
            mapDiv.dataset.loaded=true;
        }

    } else {
        mapDiv.style.display="none";
        btn.innerText="Show Map";
    }
}
function toggleParticipants(id) {
    const div = document.getElementById("participants-" + id);

    if (div.innerHTML !== "") {
        div.innerHTML = "";
        return;
    }

    fetch("participants.php?id=" + id)
.then(res => {
    if (!res.ok) {
        throw new Error("HTTP error");
    }
    return res.json();
})
.then(data => {
    let html = "";

    data.forEach(user => {
        html += "<div style='display:flex; justify-content:space-between; align-items:center; margin:6px 0;'>";

        html += "<span>" + user.username + "</span>";

        if (user.is_self) {
            html += "<span style='color:gray;'>You</span>";
        } else {
            html += "<a class='btn btn-primary btn-sm' href='chat.php?user_id=" + user.id + "'>Message</a>";
        }

        html += "</div>";
    });

    div.innerHTML = html || "<p>No participants.</p>";
})
.catch(err => {function toggleParticipants(id) {
    const div = document.getElementById("participants-" + id);

    if (div.innerHTML !== "") {
        div.innerHTML = "";
        return;
    }

    fetch("participants.php?id=" + id)
    .then(res => res.json())
    .then(data => {

        if (data.length === 0) {
            div.innerHTML = "<p>No participants yet.</p>";
            return;
        }

        let html = "";

        data.forEach(user => {
            html += "<div style='display:flex; justify-content:space-between; align-items:center; margin:6px 0;'>";

            html += "<span>" + user.username + "</span>";

            if (user.is_self) {
                html += "<span style='color:gray;'>You</span>";
            } else {
                html += "<a class='btn btn-primary btn-sm' href='chat.php?user_id=" + user.id + "'>Message</a>";
            }

            html += "</div>";
        });

        div.innerHTML = html;
    })
    .catch(err => {
        console.error(err);
        div.innerHTML = "<p style='color:red;'>Error loading participants.</p>";
    });
}
    console.error("Participants error:", err);
    div.innerHTML = "<p style='color:red;'>Error loading participants.</p>";
});
}
function loadWeather(id, city) {
    fetch("weather.php?city=" + encodeURIComponent(city))
        .then(res => res.json())
        .then(data => {
            document.getElementById("weather-" + id).innerText = data.weather;
        })
        .catch(() => {
            document.getElementById("weather-" + id).innerText = "N/A";
        });
}
document.addEventListener("DOMContentLoaded", function() {

<?php
$res->data_seek(0);
while ($row = $res->fetch_assoc()):
?>

    loadWeather(
        <?php echo $row['id']; ?>,
        "<?php echo $row['location'] ?: 'Tunis'; ?>"
    );

<?php endwhile; ?>

});

function refreshNotificationDots() {
    fetch('notification_status.php', { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                return;
            }

            const formatCount = value => {
                const count = Number(value) || 0;
                return count > 99 ? '99+' : String(count);
            };
            const dmButton = document.getElementById('dmNotificationButton');
            const dmCount = document.getElementById('dmNotificationCount');
            const adminPanelCount = document.getElementById('adminPanelNotificationCount');
            const hasUnreadDm = Number(data.dm_unread) > 0;

            if (dmButton) {
                dmButton.classList.toggle('has-unread', hasUnreadDm);
            }

            if (dmCount) {
                dmCount.textContent = formatCount(data.dm_unread);
                dmCount.classList.toggle('is-visible', hasUnreadDm);
            }

            if (adminPanelCount) {
                adminPanelCount.textContent = formatCount(data.join_unread);
                adminPanelCount.classList.toggle('is-visible', Number(data.join_unread) > 0);
            }
        })
        .catch(() => {});
}

refreshNotificationDots();
setInterval(refreshNotificationDots, 4000);
</script>


</body>
</html>
