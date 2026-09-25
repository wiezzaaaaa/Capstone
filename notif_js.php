<?php
/**
 * notif_js.php — Include just before </body>.
 * Set $notif_path before including:
 *   $notif_path = '../';  // admin/ subfolder
 *   $notif_path = '';     // root pages
 */
$notif_path = $notif_path ?? '';
?>
<script>
(function() {
    var BASE     = '<?php echo $notif_path; ?>';  // API path ('' or '../')
    var IS_ADMIN = (BASE === '../');               // true for admin/ pages

    // ── Position fixed dropdown below the bell button ──────────
    function positionDropdown() {
        var btn = document.getElementById('notifBellBtn');
        var d   = document.getElementById('notifDropdown');
        if (!btn || !d) return;
        var rect = btn.getBoundingClientRect();
        var left = rect.right - 320;
        if (left < 8) left = 8;
        d.style.top  = (rect.bottom + 6) + 'px';
        d.style.left = left + 'px';
    }

    // ── Toggle open/close ───────────────────────────────────────
    window.toggleNotifPanel = function(e) {
        if (e) e.stopPropagation();
        var d = document.getElementById('notifDropdown');
        if (!d) return;
        if (d.classList.contains('open')) {
            d.classList.remove('open');
        } else {
            positionDropdown();
            d.classList.add('open');
            fetchNotifications();
        }
    };

    // ── Fetch & render notifications ───────────────────────────
    function fetchNotifications() {
        fetch(BASE + 'get_notifications.php')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) return;
                var badge = document.getElementById('notifBellBadge');
                var list  = document.getElementById('notifList');
                if (!badge || !list) return;

                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }

                if (data.notifications.length === 0) {
                    list.innerHTML = '<div class="notif-empty"><i class="fa fa-bell-slash"></i><p>No notifications yet</p></div>';
                    return;
                }

                // NOTE: stopPropagation on each item so clicking inside
                // dropdown does NOT trigger the document close-listener
                list.innerHTML = data.notifications.map(function(n) {
                    var url = getNotifUrl(n);
                    // Store url as data attribute to avoid quote/special char issues in onclick
                    return '<div class="notif-item ' + (n.is_read ? '' : 'unread') + '" '
                         + 'data-notif-id="' + n.id + '" '
                         + 'data-notif-url="' + (url || '') + '" '
                         + 'onclick="event.stopPropagation(); handleNotifClick(this)" '
                         + 'style="cursor:pointer;">'
                         + '<div class="notif-title">' + n.title + '</div>'
                         + '<div class="notif-msg">'   + n.message + '</div>'
                         + '<div class="notif-time">'  + n.time_ago + '</div>'
                         + '</div>';
                }).join('');
            })
            .catch(function() {});
    }
    window.fetchNotifications = fetchNotifications;

    // ── Prevent clicks inside the dropdown from closing it ─────
    document.addEventListener('DOMContentLoaded', function() {
        var dropdown = document.getElementById('notifDropdown');
        if (dropdown) {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });

    // ── Redirect URL per notification type ─────────────────────
    function getNotifUrl(n) {
        var type = (n.type || '').toLowerCase();
        var sid  = n.schedule_id || 0;

        if (IS_ADMIN) {
            // Reschedule request from user → go directly to reschedule tab
            if (type === 'reschedule_request')
                return 'schedule_management.php?tab=reschedTab';

            // Schedule was rescheduled by admin/super admin → show schedule management
            if (type === 'updated_schedule')
                return 'schedule_management.php';

            // Appointment completed → completed tab
            if (type === 'completed_schedule')
                return 'schedule_management.php?tab=completedTab';

            // New patient registration → super admin dashboard approval section
            if (type === 'new_registration' || type === 'registration')
                return 'super_admin_dashboard.php';

            // Reminder → schedule management
            if (type === 'reminder')
                return 'schedule_management.php';

            // Worker approval → super admin dashboard
            if (type === 'worker_approved' || type === 'worker_rejected')
                return 'super_admin_dashboard.php';

            // Default → schedule management
            return 'schedule_management.php';

        } else {
            // User side
            // Reschedule approved/rejected → user schedule page
            if (type === 'reschedule_request' || type === 'updated_schedule'
                || type === 'new_schedule'    || type === 'schedule_approved'
                || type === 'reminder')
                return 'user_schedule.php';

            // Registration approved / completed appointment
            if (type === 'completed_schedule')
                return 'user_schedule.php';

            // Cancelled schedule
            if (type === 'cancelled_schedule')
                return 'user_schedule.php';

            // Account approved / registration confirmed
            if (type === 'registration' || type === 'approved')
                return 'user_dashboard.php';
        }
        return null;
    }

    // ── Handle notification click via data attributes ──────────
    window.handleNotifClick = function(el) {
        var id  = el.getAttribute('data-notif-id');
        var url = el.getAttribute('data-notif-url');
        markRead(id, url || null);
    };

    // ── Mark as read → redirect or refresh list ─────────────────
    window.markRead = function(id, url) {
        fetch(BASE + 'mark_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        }).then(function() {
            if (url) {
                window.location.href = url;
            } else {
                fetchNotifications();
            }
        });
    };

    window.markAllRead = function() {
        fetch(BASE + 'mark_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_all=1'
        }).then(function() { fetchNotifications(); });
    };

    // ── Reposition on scroll/resize ────────────────────────────
    window.addEventListener('scroll', function() {
        var d = document.getElementById('notifDropdown');
        if (d && d.classList.contains('open')
        ) positionDropdown();
    }, true);
    window.addEventListener('resize', function() {
        var d = document.getElementById('notifDropdown');
        if (d && d.classList.contains('open')) positionDropdown();
    });

    // ── Close only when clicking OUTSIDE the wrapper ───────────
    document.addEventListener('click', function(e) {
        var w = document.getElementById('notifWrapper');
        if (w && !w.contains(e.target)) {
            var d = document.getElementById('notifDropdown');
            if (d) d.classList.remove('open');
        }
    });

    // ── Auto-fetch every 30s ───────────────────────────────────
    // Wait for DOM to be ready before first fetch
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fetchNotifications();
        });
    } else {
        fetchNotifications();
    }
    setInterval(fetchNotifications, 30000);
})();
</script>
