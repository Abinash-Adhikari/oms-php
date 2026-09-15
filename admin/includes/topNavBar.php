<?php
/**
 * SB-Tech — top navbar (Smart-School shell ported wholesale).
 * Notifications are server-rendered then kept fresh by a mobile-friendly
 * poll; language (Google Translate), theme palette and fullscreen controls
 * ported verbatim from the Smart-School shell.
 */
$loggedInUserId = (int) Auth::id();

$orgShort = office_display_short_name();

/** Initials for the avatar chip (falls back to a user icon). */
$userFullname = trim((string) ($_SESSION['fullname'] ?? ''));
$userInitials = '';
if ($userFullname !== '') {
    $words = preg_split('/\s+/', $userFullname, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $words[0] ?? '';
    $last  = count($words) > 1 ? $words[count($words) - 1] : '';
    $userInitials = mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}
?>
<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-light border-bottom cms-top-navbar">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
            <a href="<?= pageUrl('dashboard') ?>" class="nav-link"><?= e($orgShort) ?></a>
        </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
        <?php
        $navNotifUnread = count_unread_notifications($loggedInUserId);
        $navNotifications = get_user_notifications($loggedInUserId, 8);
        ?>
        <!-- Personal To-dos Dropdown Menu -->
        <?php
        $db = Database::instance();
        if (Auth::isSuperAdmin()) {
            $navTodos = $db->select(
                'SELECT `id`, `title`, `todo_date`, `todo_time`, `remarks`, `completed`
                 FROM `tbl_office_todos`
                 ORDER BY `completed`, `todo_date`, `todo_time`
                 LIMIT 15'
            );
        } else {
            $navTodos = $db->select(
                'SELECT `id`, `title`, `todo_date`, `todo_time`, `remarks`, `completed`
                 FROM `tbl_office_todos`
                 WHERE `added_by` = ?
                 ORDER BY `completed`, `todo_date`, `todo_time`
                 LIMIT 15',
                [$loggedInUserId]
            );
        }
        $navTodosPending = count(array_filter($navTodos, fn ($t) => empty($t['completed'])));
        ?>
        <li class="nav-item dropdown cms-notif-nav">
            <a class="nav-link" data-toggle="dropdown" href="#" title="To-dos" aria-label="To-dos">
                <i class="fas fa-check-double"></i>
                <span class="badge badge-warning navbar-badge cms-notif-badge" id="cmsTodosBadge" <?= $navTodosPending > 0 ? '' : 'style="display:none"' ?>><?= $navTodosPending ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right cms-notif-menu">
                <div class="cms-notif-header">
                    <div>
                        <div class="cms-notif-title">To-dos</div>
                        <div class="cms-notif-sub" id="cmsTodosSub"><?= $navTodos ? $navTodosPending . ' pending' : 'Nothing pending' ?></div>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <div class="cms-notif-list" id="cmsTodosList">
                    <?php if (empty($navTodos)): ?>
                        <div class="cms-notif-empty">
                            <i class="fas fa-clipboard-check"></i>
                            <p>No to-dos yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($navTodos as $t): ?>
                            <div class="cms-notif-item <?= empty($t['completed']) ? '' : 'is-read' ?>" data-id="<?= (int) $t['id'] ?>">
                                <button type="button" class="cms-todo-toggle <?= empty($t['completed']) ? '' : 'done' ?>" title="<?= empty($t['completed']) ? 'Mark done' : 'Mark pending' ?>"><i class="fas fa-<?= empty($t['completed']) ? 'check' : 'undo' ?>"></i></button>
                                <span class="cms-notif-body">
                                    <span class="cms-notif-text<?= empty($t['completed']) ? '' : ' text-muted' ?>" style="text-decoration:<?= empty($t['completed']) ? 'none' : 'line-through' ?>"><?= e($t['title']) ?></span>
                                    <span class="cms-notif-meta">
                                        <span class="cms-notif-sender"><?= e($t['todo_date']) ?><?= $t['todo_time'] ? ' · ' . e($t['todo_time']) : '' ?></span>
                                    </span>
                                </span>
                                <button type="button" class="cms-todo-del" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="dropdown-divider"></div>
                <div class="cms-notif-body px-3 py-2">
                    <form id="cmsTodoAddForm" class="d-flex">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="text" name="title" class="form-control form-control-sm mr-2" placeholder="Add a quick to-do…" required>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
                    </form>
                </div>
                <a href="<?= pageUrl('my_office', 'office_calendar') ?>" class="dropdown-item dropdown-footer cms-notif-footer">
                    <i class="fas fa-calendar-alt mr-1"></i> Open Calendar
                </a>
            </div>
        </li>

        <!-- Notifications Dropdown Menu (real data) -->
        <li class="nav-item dropdown cms-notif-nav">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Notifications" aria-label="Notifications">
                <i class="far fa-bell"></i>
                <span class="badge badge-danger navbar-badge cms-notif-badge" id="cmsNotifBadge" <?php echo $navNotifUnread > 0 ? '' : 'style="display:none"'; ?>"><?php echo $navNotifUnread; ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right cms-notif-menu">
                <div class="cms-notif-header">
                    <div>
                        <div class="cms-notif-title">Notifications</div>
                        <div class="cms-notif-sub" id="cmsNotifSub"><?php echo $navNotifUnread > 0 ? $navNotifUnread . ' unread' : 'You\'re all caught up'; ?></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary cms-notif-markall" id="cmsNotifMarkAll" <?php echo $navNotifUnread > 0 ? '' : 'disabled'; ?>>
                        <i class="fas fa-check-double"></i> Mark all read
                    </button>
                </div>
                <div class="dropdown-divider"></div>
                <div class="cms-notif-list" id="cmsNotifList">
                    <?php if (empty($navNotifications)): ?>
                        <div class="cms-notif-empty">
                            <i class="far fa-bell-slash"></i>
                            <p>No notifications yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($navNotifications as $navNotif):
                            $navMeta = notification_type_meta($navNotif['type']);
                            $navUnread = empty($navNotif['viewed']);
                            $navText = htmlspecialchars(strip_tags((string) $navNotif['details']), ENT_QUOTES, 'UTF-8');
                            $navUrl = notification_target_url($navNotif);
                        ?>
                            <a href="<?php echo $navUrl; ?>" class="cms-notif-item <?php echo $navUnread ? 'is-unread' : ''; ?>" data-id="<?php echo (int) $navNotif['id']; ?>">
                                <span class="cms-notif-icon cms-notif-<?php echo $navMeta['color']; ?>"><i class="<?php echo $navMeta['icon']; ?>"></i></span>
                                <span class="cms-notif-body">
                                    <span class="cms-notif-text"><?php echo $navText; ?></span>
                                    <span class="cms-notif-meta">
                                        <?php if (!empty($navNotif['sender_name'])): ?><span class="cms-notif-sender"><?php echo htmlspecialchars($navNotif['sender_name'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                        <span class="cms-notif-time"><?php echo notification_time_ago($navNotif['added_on']); ?></span>
                                    </span>
                                </span>
                                <?php if ($navUnread): ?><span class="cms-notif-dot"></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?= pageUrl('dashboard') ?>" class="dropdown-item dropdown-footer cms-notif-footer">
                    <i class="fas fa-list-ul mr-1"></i> See All Notifications
                </a>
            </div>
        </li>

        <!-- Language (Google Translate) -->
        <li class="nav-item dropdown cms-lang-nav notranslate">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Language" aria-label="Change language" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-globe" aria-hidden="true"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right cms-lang-panel">
                <div class="cms-theme-title">Language</div>
                <div class="cms-theme-sub">Translate this site with Google</div>
                <div class="cms-lang-list" role="group" aria-label="Site language">
                    <button type="button" class="cms-lang-item" data-lang="en" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">English</span>
                        <span class="cms-lang-code">EN</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="ne" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">नेपाली</span>
                        <span class="cms-lang-code">NE</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="hi" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">हिन्दी</span>
                        <span class="cms-lang-code">HI</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="zh-CN" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">中文</span>
                        <span class="cms-lang-code">ZH</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="ar" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">العربية</span>
                        <span class="cms-lang-code">AR</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="es" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">Español</span>
                        <span class="cms-lang-code">ES</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="fr" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">Français</span>
                        <span class="cms-lang-code">FR</span>
                    </button>
                    <button type="button" class="cms-lang-item" data-lang="de" aria-pressed="false">
                        <span class="cms-lang-check d-none"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <span class="cms-lang-name">Deutsch</span>
                        <span class="cms-lang-code">DE</span>
                    </button>
                </div>
            </div>
            <div id="google_translate_element" aria-hidden="true"></div>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link" data-toggle="dropdown" href="#" title="Theme">
                <i class="fas fa-palette"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right cms-theme-panel">
                <div class="cms-theme-title">Theme</div>
                <div class="cms-theme-sub">Color mode and accent palette</div>
                <div class="cms-mode-toggle" role="group" aria-label="Color mode">
                    <button type="button" class="cms-mode-btn" id="cmsThemeModeLight" data-mode="light">
                        <i class="fas fa-sun"></i> Light
                    </button>
                    <button type="button" class="cms-mode-btn" id="cmsThemeModeDark" data-mode="dark">
                        <i class="fas fa-moon"></i> Dark
                    </button>
                </div>
                <div class="cms-palette-grid">
                    <button type="button" class="cms-palette-item" data-accent="blue" title="Blue">
                        <span class="cms-palette-check d-none"><i class="fas fa-check"></i></span>
                        <div class="cms-palette-swatches">
                            <span class="cms-palette-swatch" style="background:#3b82f6"></span>
                            <span class="cms-palette-swatch" style="background:rgba(59,130,246,0.2)"></span>
                        </div>
                        <div class="cms-palette-name">Blue</div>
                        <div class="cms-palette-hex">#3B82F6</div>
                    </button>
                    <button type="button" class="cms-palette-item" data-accent="emerald" title="Emerald">
                        <span class="cms-palette-check d-none"><i class="fas fa-check"></i></span>
                        <div class="cms-palette-swatches">
                            <span class="cms-palette-swatch" style="background:#10b981"></span>
                            <span class="cms-palette-swatch" style="background:rgba(16,185,129,0.2)"></span>
                        </div>
                        <div class="cms-palette-name">Emerald</div>
                        <div class="cms-palette-hex">#10B981</div>
                    </button>
                    <button type="button" class="cms-palette-item" data-accent="purple" title="Purple">
                        <span class="cms-palette-check d-none"><i class="fas fa-check"></i></span>
                        <div class="cms-palette-swatches">
                            <span class="cms-palette-swatch" style="background:#8b5cf6"></span>
                            <span class="cms-palette-swatch" style="background:rgba(139,92,246,0.2)"></span>
                        </div>
                        <div class="cms-palette-name">Purple</div>
                        <div class="cms-palette-hex">#8B5CF6</div>
                    </button>
                    <button type="button" class="cms-palette-item" data-accent="rose" title="Rose">
                        <span class="cms-palette-check d-none"><i class="fas fa-check"></i></span>
                        <div class="cms-palette-swatches">
                            <span class="cms-palette-swatch" style="background:#f43f5e"></span>
                            <span class="cms-palette-swatch" style="background:rgba(244,63,94,0.2)"></span>
                        </div>
                        <div class="cms-palette-name">Rose</div>
                        <div class="cms-palette-hex">#F43F5E</div>
                    </button>
                    <button type="button" class="cms-palette-item" data-accent="amber" title="Amber">
                        <span class="cms-palette-check d-none"><i class="fas fa-check"></i></span>
                        <div class="cms-palette-swatches">
                            <span class="cms-palette-swatch" style="background:#f59e0b"></span>
                            <span class="cms-palette-swatch" style="background:rgba(245,158,11,0.22)"></span>
                        </div>
                        <div class="cms-palette-name">Amber</div>
                        <div class="cms-palette-hex">#F59E0B</div>
                    </button>
                    <button type="button" class="cms-palette-item" data-accent="indigo" title="Indigo">
                        <span class="cms-palette-check d-none"><i class="fas fa-check"></i></span>
                        <div class="cms-palette-swatches">
                            <span class="cms-palette-swatch" style="background:#6366f1"></span>
                            <span class="cms-palette-swatch" style="background:rgba(99,102,241,0.2)"></span>
                        </div>
                        <div class="cms-palette-name">Indigo</div>
                        <div class="cms-palette-hex">#6366F1</div>
                    </button>
                </div>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link" data-widget="fullscreen" href="#" role="button" title="Fullscreen (preference saved for this browser)" aria-label="Toggle fullscreen">
                <i class="fas fa-expand-arrows-alt"></i>
            </a>
        </li>

        <!-- User Dropdown -->
        <li class="nav-item dropdown">
            <div class="dropdown main-profile-menu">
                <a class="d-flex nav-link" data-toggle="dropdown" href="#">
                    <?php if ($userInitials !== ''): ?>
                        <span class="user-avatar-chip"><?= e($userInitials) ?></span>
                    <?php else: ?>
                        <span class="user-avatar-chip"><i class="fas fa-user"></i></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <div class="header-navheading text-center mt-2">
                        <h5 class="main-notification-title"><?php echo htmlspecialchars((string) ($_SESSION['fullname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
                        <p class="main-notification-text">@<?php echo htmlspecialchars((string) ($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <hr>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fas fa-sign-out-alt mr-2"></i> Sign Out
                    </a>
                </div>
            </div>
        </li>
    </ul>
</nav>
<!-- /.navbar -->

<script>
$(function() {
    var $badge = $('#cmsNotifBadge');
    var $sub = $('#cmsNotifSub');
    var $markAll = $('#cmsNotifMarkAll');
    var $todoBadge = $('#cmsTodosBadge');
    var $todoSub = $('#cmsTodosSub');
    var $todoList = $('#cmsTodosList');

    function esc(s) {
        return $('<div/>').text(s == null ? '' : s).html();
    }

    function setBadge(count) {
        count = parseInt(count, 10) || 0;
        $badge.text(count).toggle(count > 0);
        $sub.text(count > 0 ? count + ' unread' : "You're all caught up");
        $markAll.prop('disabled', count === 0);
    }

    function refreshBadge() {
        $.getJSON('ajax.php', { action: 'get_unread_count' })
            .done(function(res) {
                if (res && typeof res.count !== 'undefined') setBadge(res.count);
            })
            .fail(function() {});
    }

    // ── Personal to-dos dropdown ──────────────────────────────────────────────
    function renderTodos(todos) {
        var pending = todos.filter(function(t) { return !t.completed; }).length;
        $todoBadge.text(pending).toggle(pending > 0);
        $todoSub.text(todos.length ? pending + ' pending' : 'Nothing pending');
        if (!todos.length) {
            $todoList.html('<div class="cms-notif-empty"><i class="fas fa-clipboard-check"></i><p>No to-dos yet</p></div>');
            return;
        }
        $todoList.empty();
        todos.forEach(function(t) {
            var done = !!t.completed;
            var item = $('<div class="cms-notif-item"></div>').attr('data-id', t.id);
            item.append(
                $('<button type="button" class="cms-todo-toggle"></button>')
                    .addClass(done ? 'done' : '')
                    .attr('title', done ? 'Mark pending' : 'Mark done')
                    .html('<i class="fas fa-' + (done ? 'undo' : 'check') + '"></i>')
            );
            var body = $('<span class="cms-notif-body"></span>');
            body.append(
                $('<span class="cms-notif-text"></span>')
                    .addClass(done ? 'text-muted' : '')
                    .css('text-decoration', done ? 'line-through' : 'none')
                    .text(t.title),
                $('<span class="cms-notif-meta"><span class="cms-notif-sender"></span></span>')
                    .find('.cms-notif-sender')
                    .text(t.todo_date + (t.todo_time ? ' · ' + t.todo_time : ''))
                    .end()
            );
            item.append(body);
            item.append($('<button type="button" class="cms-todo-del" title="Delete"><i class="fas fa-trash-alt"></i></button>'));
            $todoList.append(item);
        });
    }

    function refreshTodos() {
        $.getJSON('ajax.php', { action: 'get_my_todos' })
            .done(function(res) {
                if (res && res.success) renderTodos(res.todos || []);
            })
            .fail(function() {});
    }

    $todoList.on('click', '.cms-todo-toggle', function(e) {
        e.preventDefault();
        var id = $(this).closest('.cms-notif-item').data('id');
        $.post('ajax.php', { action: 'toggle_todo', id: id, csrf_token: $('#cmsTodoAddForm [name=csrf_token]').val() })
            .done(function() { refreshTodos(); });
    });

    $todoList.on('click', '.cms-todo-del', function(e) {
        e.preventDefault();
        var id = $(this).closest('.cms-notif-item').data('id');
        if (!confirm('Delete this to-do?')) { return; }
        $.post('ajax.php', { action: 'delete_todo', id: id, csrf_token: $('#cmsTodoAddForm [name=csrf_token]').val() })
            .done(function() { refreshTodos(); });
    });

    $('#cmsTodoAddForm').on('submit', function(e) {
        e.preventDefault();
        var $title = $(this).find('[name=title]');
        var title = $.trim($title.val());
        if (!title) { return; }
        var csrf = $(this).find('[name=csrf_token]').val();
        $.post('ajax.php', { action: 'save_todo', title: title, csrf_token: csrf })
            .done(function() {
                $title.val('');
                refreshTodos();
            });
    });
    // ── End personal to-dos ───────────────────────────────────────────────────

    // Clicking an unread item marks it read (and navigates to the center)
    $('.cms-notif-list').on('click', '.cms-notif-item.is-unread', function(e) {
        e.preventDefault();
        var $item = $(this);
        var id = $item.data('id');
        var href = $item.attr('href');
        $item.removeClass('is-unread').find('.cms-notif-dot').remove();
        var go = function() { window.location.href = href; };
        if (id) {
            // Navigate only after the mark-read POST settles so it isn't aborted
            $.post('ajax.php', { action: 'mark_notification_read', id: id })
                .done(function() { refreshBadge(); })
                .always(go);
        } else {
            go();
        }
    });

    // Mark all read
    $markAll.on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('ajax.php', { action: 'mark_all_notifications_read' })
            .done(function(res) {
                if (res && res.success) {
                    $('.cms-notif-item').removeClass('is-unread').find('.cms-notif-dot').remove();
                    setBadge(0);
                }
            })
            .always(function() {
                $btn.html('<i class="fas fa-check-double"></i> Mark all read');
                refreshBadge();
            });
    });

    // Poll every 60s so the badge stays fresh without a reload
    setInterval(refreshBadge, 60000);
    refreshBadge();
});
</script>