/**
 * SB-Tech — Notifications Client
 * Real-time notifications via Server-Sent Events (SSE).
 * Falls back to AJAX polling if EventSource is unavailable.
 */
(function () {
    'use strict';

    const POLL_INTERVAL = 15000; // 15s fallback polling
    const TYPE_ICONS = {
        info:    'fas fa-info-circle',
        success: 'fas fa-check-circle',
        warning: 'fas fa-exclamation-triangle',
        danger:  'fas fa-times-circle'
    };

    let lastEventId = 0;
    let es = null;
    let pollTimer = null;

    /**
     * Get the authenticated user's ID from a meta tag or data attribute.
     */
    function getUserId() {
        const el = document.querySelector('[data-user-id]');
        return el ? parseInt(el.getAttribute('data-user-id'), 10) : 0;
    }

    /**
     * Update the unread badge count in the navbar.
     */
    function updateBadge(count) {
        const badge = document.getElementById('notif-badge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = '';
            badge.setAttribute('data-count', count);
        } else {
            badge.textContent = '';
            badge.style.display = 'none';
            badge.setAttribute('data-count', '0');
        }
    }

    /**
     * Render a single notification item as HTML string.
     */
    function renderNotifItem(notif) {
        const iconClass = TYPE_ICONS[notif.type] || TYPE_ICONS.info;
        const href = notif.url ? ' href="' + escapeHtml(notif.url) + '"' : '';
        const time = formatRelativeTime(notif.created_at || notif.added_on);

        return '<a class="notif-item unread"' + href + ' data-id="' + notif.id + '">'
            + '  <span class="notif-icon type-' + escapeHtml(notif.type || 'info') + '">'
            + '    <i class="' + iconClass + '"></i>'
            + '  </span>'
            + '  <span class="notif-content">'
            + '    <div class="notif-title">' + escapeHtml(notif.title || 'Notification') + '</div>'
            + '    <div class="notif-message">' + escapeHtml(notif.details || notif.message || '') + '</div>'
            + '  </span>'
            + '  <span class="notif-time">' + time + '</span>'
            + '</a>';
    }

    /**
     * Prepend a new notification to the dropdown body.
     */
    function prependNotification(notif) {
        const body = document.getElementById('notif-body');
        if (!body) return;

        // Remove empty state if present
        const empty = body.querySelector('.notif-empty');
        if (empty) empty.remove();

        // Prepend new item
        const temp = document.createElement('div');
        temp.innerHTML = renderNotifItem(notif);
        const item = temp.firstElementChild;
        if (body.firstChild) {
            body.insertBefore(item, body.firstChild);
        } else {
            body.appendChild(item);
        }

        // Bind click to mark-as-read
        item.addEventListener('click', function (e) {
            e.preventDefault();
            markAsRead(notif.id, item);
            if (notif.url) {
                window.location.href = notif.url;
            }
        });
    }

    /**
     * Mark a single notification as read via AJAX.
     */
    function markAsRead(id, itemEl) {
        fetch('api/notifications-actions.php?action=mark_read&id=' + id, {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                if (itemEl) itemEl.classList.remove('unread');
                const badge = document.getElementById('notif-badge');
                if (badge) {
                    const current = parseInt(badge.getAttribute('data-count') || '0', 10);
                    updateBadge(Math.max(0, current - 1));
                }
            }
        }).catch(function () { /* ignore */ });
    }

    /**
     * Mark all notifications as read.
     */
    function markAllRead() {
        fetch('api/notifications-actions.php?action=mark_all_read', {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                document.querySelectorAll('.notif-item.unread').forEach(function (el) {
                    el.classList.remove('unread');
                });
                updateBadge(0);
            }
        }).catch(function () { /* ignore */ });
    }

    /**
     * Load initial unread count on page load.
     */
    function loadUnreadCount() {
        fetch('api/notifications-actions.php?action=unread_count', {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data.ok) {
                updateBadge(data.count || 0);
                if (data.last_id) {
                    lastEventId = data.last_id;
                }
            }
        }).catch(function () { /* ignore */ });
    }

    /**
     * Load recent notifications into the dropdown.
     */
    function loadRecent() {
        fetch('api/notifications-actions.php?action=fetch&limit=10', {
            method: 'GET',
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data.ok || !data.items) return;
            const body = document.getElementById('notif-body');
            if (!body) return;

            if (data.items.length === 0) {
                body.innerHTML = '<div class="notif-empty"><i class="fas fa-bell-slash fa-2x mb-2 d-block" style="opacity:.3"></i>No notifications yet</div>';
                return;
            }

            let html = '';
            data.items.forEach(function (n) {
                html += renderNotifItem(n);
            });
            body.innerHTML = html;

            // Bind click handlers
            body.querySelectorAll('.notif-item').forEach(function (item) {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    const nid = parseInt(item.getAttribute('data-id'), 10);
                    markAsRead(nid, item);
                    // Navigate if URL present
                    const url = item.getAttribute('href');
                    if (url && url !== '#') {
                        window.location.href = url;
                    }
                });
            });

            if (data.last_id) {
                lastEventId = data.last_id;
            }
        }).catch(function () { /* ignore */ });
    }

    /**
     * Open SSE stream for real-time notifications.
     */
    function openSSE() {
        if (typeof EventSource === 'undefined') {
            startPolling();
            return;
        }

        const url = 'api/sse-notifications.php' + (lastEventId ? '?last_id=' + lastEventId : '');
        es = new EventSource(url);

        es.onmessage = function (event) {
            try {
                const notif = JSON.parse(event.data);
                prependNotification(notif);

                // Increment badge
                const badge = document.getElementById('notif-badge');
                const current = badge ? parseInt(badge.getAttribute('data-count') || '0', 10) : 0;
                updateBadge(current + 1);

                // Update lastEventId
                if (notif.id && parseInt(notif.id, 10) > lastEventId) {
                    lastEventId = parseInt(notif.id, 10);
                }

                // Browser notification (only if permission already granted)
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification(notif.title || (typeof APP_ORG_NAME !== 'undefined' ? APP_ORG_NAME : 'Notification'), {
                        body: notif.details || notif.message || '',
                        icon: '/assets/img/logo.png'
                    });
                }
            } catch (e) { /* parse error — ignore */ }
        };

        es.onerror = function () {
            // EventSource auto-reconnects; show subtle indicator
            const status = document.getElementById('sse-status');
            if (status) {
                status.classList.add('visible', 'reconnecting');
                status.textContent = 'Reconnecting...';
            }
        };

        es.onopen = function () {
            const status = document.getElementById('sse-status');
            if (status) {
                status.classList.remove('visible', 'reconnecting');
                status.textContent = '';
            }
        };
    }

    /**
     * Fallback: AJAX polling when EventSource unavailable.
     */
    function startPolling() {
        pollTimer = setInterval(function () {
            fetch('api/notifications-actions.php?action=unread_count', {
                method: 'GET',
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (data.ok) {
                    updateBadge(data.count || 0);
                }
            }).catch(function () { /* ignore */ });
        }, POLL_INTERVAL);
    }

    /**
     * Escape HTML to prevent XSS in notification content.
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Format a timestamp as relative time ("2 min ago", "1 hr ago", etc.).
     */
    function formatRelativeTime(dateStr) {
        if (!dateStr) return '';
        const now = Date.now();
        const then = new Date(dateStr).getTime();
        if (isNaN(then)) return '';

        const diffSec = Math.floor((now - then) / 1000);
        if (diffSec < 60) return 'Just now';
        if (diffSec < 3600) return Math.floor(diffSec / 60) + ' min ago';
        if (diffSec < 86400) return Math.floor(diffSec / 3600) + ' hr ago';
        return Math.floor(diffSec / 86400) + ' days ago';
    }

    /**
     * Initialize everything on DOM ready.
     */
    function init() {
        loadUnreadCount();
        loadRecent();
        openSSE();

        // Mark all read button
        const markAllBtn = document.getElementById('notif-mark-all');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                markAllRead();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Clean up SSE connection on page unload to free PHP-FPM workers
    window.addEventListener('beforeunload', function () {
        if (es) {
            es.close();
            es = null;
        }
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    });

    // Expose for programmatic use
    window.SBTechNotif = {
        markAllRead: markAllRead,
        refresh: function () {
            loadUnreadCount();
            loadRecent();
        }
    };
})();
