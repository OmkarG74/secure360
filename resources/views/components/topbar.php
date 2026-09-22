<?php
/**
 * Standard Minimal Top Header for Secure360 Admin & Superadmin
 */
$authUser = auth() ?? [];
$userName = $authUser['full_name'] ?? $authUser['name'] ?? 'Admin';
$userRole = ucfirst($authUser['role_name'] ?? $authUser['role'] ?? 'Admin');
$userOrg = $authUser['organization_name'] ?? 'Secure360';
$initials = strtoupper(substr($userName, 0, 1));
?>
<header class="app-topbar">
    <!-- Left: Navigation Context / Minimal Spacer -->
    <div class="topbar-left"></div>

    <!-- Right: Operational Notification & Admin Profile -->
    <div class="topbar-right" style="display: flex; align-items: center; gap: 1.25rem;">
        
        <!-- Header Notification Center -->
        <div class="notification-wrapper" id="notificationWrapper">
            <!-- Notification Bell Icon Button -->
            <button type="button" 
                    class="notification-bell-btn" 
                    id="notificationBellBtn" 
                    aria-label="Notifications" 
                    aria-expanded="false" 
                    aria-haspopup="true"
                    title="Notifications & Alerts"
                    onclick="toggleNotificationPopover(event)">
                <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="notification-badge hidden" id="notificationBadge">0</span>
            </button>

            <!-- Notification Popover Dropdown -->
            <div class="notification-popover" id="notificationPopover" role="region" aria-label="Notifications Dropdown">
                <!-- Popover Header -->
                <div class="notification-popover-header">
                    <div class="notif-header-title">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span>Notifications</span>
                    </div>
                    <button type="button" 
                            class="notif-mark-all-btn" 
                            id="markAllReadBtn" 
                            style="display: none;" 
                            onclick="markAllNotificationsAsRead(event)">
                        Mark all as read
                    </button>
                </div>

                <!-- Popover Body / Notification List -->
                <div class="notification-popover-body" id="notificationList">
                    <div class="notif-empty-box" style="padding: 2.5rem 1rem;">
                        <svg class="notif-empty-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <div class="notif-empty-title">Loading notifications...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Admin Identity & Organization -->
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8125rem; border: 1px solid #bfdbfe;">
                <?= e($initials) ?>
            </div>
            <div style="line-height: 1.25; display: flex; flex-direction: column;">
                <span style="font-size: 0.8125rem; font-weight: 600; color: #0f172a;"><?= e($userName) ?></span>
                <span style="font-size: 0.7rem; color: #64748b;"><?= e($userOrg) ?> &bull; <strong style="color: #2563eb; font-weight: 500;"><?= e($userRole) ?></strong></span>
            </div>
        </div>

        <!-- Logout Action -->
        <form method="POST" action="<?= url('/logout') ?>" style="display: inline; margin-left: 0.25rem;">
            <?= csrf_field() ?>
            <button type="submit" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #475569; font-size: 0.75rem; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 6px; cursor: pointer; transition: all 0.15s ease;">
                Sign Out
            </button>
        </form>
    </div>
</header>

<script>
(function() {
    const NOTIF_URL = '<?= url('/admin/notifications') ?>';
    const MARK_ALL_URL = '<?= url('/admin/notifications/mark-all-read') ?>';
    const CSRF_TOKEN = '<?= csrf_token() ?>';

    let notificationsCache = [];
    let isFetching = false;

    // Toggle Popover Open/Close
    window.toggleNotificationPopover = function(e) {
        if (e) e.stopPropagation();
        const popover = document.getElementById('notificationPopover');
        const bellBtn = document.getElementById('notificationBellBtn');
        if (!popover || !bellBtn) return;

        const isOpen = popover.classList.contains('open');
        if (isOpen) {
            closeNotificationPopover();
        } else {
            popover.classList.add('open');
            bellBtn.classList.add('active');
            bellBtn.setAttribute('aria-expanded', 'true');
            loadNotifications(true);
        }
    };

    function closeNotificationPopover() {
        const popover = document.getElementById('notificationPopover');
        const bellBtn = document.getElementById('notificationBellBtn');
        if (popover) popover.classList.remove('open');
        if (bellBtn) {
            bellBtn.classList.remove('active');
            bellBtn.setAttribute('aria-expanded', 'false');
        }
    }

    // Fetch notifications from server
    function loadNotifications(showLoadingState = false) {
        if (isFetching) return;
        isFetching = true;

        const listContainer = document.getElementById('notificationList');
        if (showLoadingState && listContainer && notificationsCache.length === 0) {
            listContainer.innerHTML = `
                <div class="notif-empty-box" style="padding: 2.5rem 1rem;">
                    <div style="font-size: 0.8125rem; font-weight: 500; color: #64748b;">Loading updates...</div>
                </div>
            `;
        }

        fetch(NOTIF_URL, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            isFetching = false;
            if (data && data.success) {
                notificationsCache = data.notifications || [];
                updateUnreadBadge(data.unread_count || 0);
                renderNotifications(notificationsCache, data.unread_count || 0);
            }
        })
        .catch(err => {
            isFetching = false;
            console.error('Error fetching notifications:', err);
        });
    }

    // Update unread count badge on bell
    function updateUnreadBadge(count) {
        const badge = document.getElementById('notificationBadge');
        const markAllBtn = document.getElementById('markAllReadBtn');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
            if (markAllBtn) markAllBtn.style.display = 'inline-block';
        } else {
            badge.textContent = '0';
            badge.classList.add('hidden');
            if (markAllBtn) markAllBtn.style.display = 'none';
        }
    }

    // Render notifications inside popover
    function renderNotifications(items, unreadCount) {
        const listContainer = document.getElementById('notificationList');
        if (!listContainer) return;

        if (!items || items.length === 0) {
            listContainer.innerHTML = `
                <div class="notif-empty-box">
                    <svg class="notif-empty-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <div class="notif-empty-title">No notifications</div>
                    <div class="notif-empty-desc">You're all caught up.</div>
                </div>
            `;
            return;
        }

        let html = '';
        items.forEach(notif => {
            const isUnread = !notif.is_read;
            const accentClass = notif.accent || 'blue';
            
            // Icon SVG depending on category
            let iconSvg = '';
            if (accentClass === 'green') {
                iconSvg = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>`;
            } else if (accentClass === 'amber') {
                iconSvg = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`;
            } else if (accentClass === 'red') {
                iconSvg = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`;
            } else {
                iconSvg = `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>`;
            }

            html += `
                <div class="notification-item ${isUnread ? 'unread' : ''}" 
                     data-id="${notif.id}" 
                     data-url="${notif.action_url || ''}"
                     onclick="handleNotificationClick(event, ${notif.id}, '${notif.action_url || ''}')"
                     role="button"
                     tabindex="0">
                    <div class="notif-icon-circle ${accentClass}">
                        ${iconSvg}
                    </div>
                    <div class="notif-content">
                        <div class="notif-item-title">${escapeHtml(notif.title)}</div>
                        ${notif.message ? `<div class="notif-item-msg">${escapeHtml(notif.message)}</div>` : ''}
                        <div class="notif-item-time">${escapeHtml(notif.time_ago)}</div>
                    </div>
                    ${isUnread ? `<span class="notif-unread-dot" title="Unread"></span>` : ''}
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    // Handle clicking a notification
    window.handleNotificationClick = function(e, id, actionUrl) {
        if (e) e.stopPropagation();
        
        // Optimistically update UI
        const itemEl = document.querySelector(`.notification-item[data-id="${id}"]`);
        if (itemEl && itemEl.classList.contains('unread')) {
            itemEl.classList.remove('unread');
            const dot = itemEl.querySelector('.notif-unread-dot');
            if (dot) dot.remove();
        }

        // Send read request to server
        fetch(`<?= url('/admin/notifications/') ?>${id}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                updateUnreadBadge(data.unread_count || 0);
            }
        })
        .catch(err => console.error('Error marking notification as read:', err));

        // If notification has a destination action URL, navigate there
        if (actionUrl && actionUrl.trim() !== '') {
            closeNotificationPopover();
            window.location.href = actionUrl;
        }
    };

    // Mark all notifications as read
    window.markAllNotificationsAsRead = function(e) {
        if (e) e.stopPropagation();

        fetch(MARK_ALL_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                updateUnreadBadge(0);
                document.querySelectorAll('.notification-item.unread').forEach(el => {
                    el.classList.remove('unread');
                    const dot = el.querySelector('.notif-unread-dot');
                    if (dot) dot.remove();
                });
            }
        })
        .catch(err => console.error('Error marking all as read:', err));
    };

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notificationWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotificationPopover();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeNotificationPopover();
        }
    });

    // Initial check on load & poll every 45s
    document.addEventListener('DOMContentLoaded', function() {
        loadNotifications(false);
        setInterval(function() {
            loadNotifications(false);
        }, 45000);
    });
})();
</script>
