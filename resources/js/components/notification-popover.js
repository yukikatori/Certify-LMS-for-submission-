export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');

    if (!root) {
        return;
    }

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');
    const markAllButton = root.querySelector('[data-notification-popover-mark-all]');

    if (!trigger || !panel) {
        return;
    }

    let notifications = [];
    let activeTab = 'all';

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activeTab = tab.dataset.notificationPopoverTab;
            updateTabs(root, activeTab);
            renderNotifications(root, filterNotifications(notifications, activeTab));
        });
    });

    if (markAllButton) {
        markAllButton.addEventListener('click', async () => {
            const data = await markAllAsRead();

            notifications = notifications.map((notification) => ({
                ...notification,
                is_unread: false,
                read_at: notification.read_at ?? new Date().toISOString(),
            }));

            renderNotifications(root, filterNotifications(notifications, activeTab));
            updateUnreadCount(root, data.unread_count);
        });
    }

    trigger.addEventListener('click', async (event) => {
        event.stopPropagation();

        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (isOpen) {
            closePopover(trigger, panel);
            return;
        }

        openPopover(trigger, panel);
        setLoading(root, true);

        try {
            const data = await fetchNotifications();

            notifications = data.notifications;

            renderNotifications(root, filterNotifications(notifications, activeTab));
            updateUnreadCount(root, data.unread_count);
            updateTabs(root, activeTab);
        } catch (error) {
            console.error(error);
            showEmpty(root, '通知を取得できませんでした。');
        } finally {
            setLoading(root, false);
        }
    });

    document.addEventListener('click', (event) => {
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (!isOpen) {
            return;
        }

        if (root.contains(event.target)) {
            return;
        }

        closePopover(trigger, panel);
    });

    document.addEventListener('keydown', (event) => {
        const isOpen = trigger.getAttribute('aria-expanded') === 'true';

        if (!isOpen) {
            return;
        }

        if (event.key !== 'Escape') {
            return;
        }

        closePopover(trigger, panel);
        trigger.focus();
    });
}

function openPopover(trigger, panel) {
    trigger.setAttribute('aria-expanded', 'true');

    panel.classList.remove('hidden');
    panel.style.display = 'flex';

    requestAnimationFrame(() => {
        panel.classList.remove('opacity-0', '-translate-y-1');
    });
}

function closePopover(trigger, panel) {
    trigger.setAttribute('aria-expanded', 'false');

    panel.classList.add('opacity-0', '-translate-y-1');

    window.setTimeout(() => {
        panel.classList.add('hidden');
        panel.style.display = 'none';
    }, 150);
}

async function fetchNotifications() {
    const response = await fetch('/api/v1/notifications', {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error('通知を取得できませんでした。');
    }

    return response.json();
}

function updateTabs(root, activeTab) {
    const tabs = root.querySelectorAll('[data-notification-popover-tab]');

    tabs.forEach((tab) => {
        const tabName = tab.dataset.notificationPopoverTab;
        tab.setAttribute('aria-selected', tabName === activeTab ? 'true' : 'false');
    });
}

function renderNotifications(root, notifications) {
    const items = root.querySelector('[data-notification-popover-items]');
    const template = root.querySelector('[data-notification-popover-row-template]');

    if (!items || !template) {
        return;
    }

    items.innerHTML = '';

    if (notifications.length === 0) {
        showEmpty(root);
        return;
    }

    showItems(root);

    notifications.forEach((notification) => {
        const fragment = template.content.cloneNode(true);

        const row = fragment.querySelector('[data-notification-popover-row]');
        const dot = fragment.querySelector('[data-notification-popover-row-dot]');
        const title = fragment.querySelector('[data-notification-popover-row-title]');
        const message = fragment.querySelector('[data-notification-popover-row-message]');
        const time = fragment.querySelector('[data-notification-popover-row-time]');

        row.href = notification.action_url;
        row.dataset.notificationId = notification.id;
        row.dataset.unread = notification.is_unread ? 'true' : 'false';

        row.addEventListener('click', async (event) => {
            event.preventDefault();

            try {
                const data = await markAsRead(notification.id);

                window.location.href = data.redirect_url || notification.action_url;
            } catch (error) {
                console.error(error);
                window.location.href = notification.action_url;
            }
        });

        title.textContent = notification.title;
        message.textContent = notification.message;
        time.textContent = notification.created_relative;

        if (!notification.is_unread) {
            dot.classList.add('hidden');
        }

        items.appendChild(fragment);
    });
}

function filterNotifications(notifications, activeTab) {
    if (activeTab === 'unread') {
        return notifications.filter((notification) => notification.is_unread);
    }

    return notifications;
}

function updateUnreadCount(root, unreadCount) {
    const unreadCountElement = root.querySelector('[data-notification-popover-unread-count]');
    const badge = root.querySelector('[data-notification-popover-badge]');

    if (unreadCountElement) {
        unreadCountElement.textContent = formatCount(unreadCount);
    }

    if (badge) {
        badge.textContent = formatCount(unreadCount);
        badge.classList.toggle('hidden', unreadCount <= 0);
    }
}

function formatCount(count) {
    return count > 99 ? '99+' : String(count);
}

async function markAllAsRead() {
    await prepareCsrfToken();

    const response = await fetch('/api/v1/notifications/read-all', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
    });

    if (!response.ok) {
        throw new Error('通知を既読にできませんでした。');
    }

    return response.json();
}

async function prepareCsrfToken() {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
    });
}

function getXsrfToken() {
    const token = getCookie('XSRF-TOKEN');

    return token ? decodeURIComponent(token) : '';
}

function getCookie(name) {
    return document.cookie
        .split('; ')
        .find((row) => row.startsWith(`${name}=`))
        ?.split('=')[1];
}

async function markAsRead(notificationId) {
    await prepareCsrfToken();

    const response = await fetch(`/api/v1/notifications/${notificationId}/read`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': getXsrfToken(),
        },
    });

    if (!response.ok) {
        throw new Error('通知を既読にできませんでした。');
    }

    return response.json();
}

function setLoading(root, isLoading) {
    const loading = root.querySelector('[data-notification-popover-loading]');
    const items = root.querySelector('[data-notification-popover-items]');
    const empty = root.querySelector('[data-notification-popover-empty]');

    if (loading) {
        loading.classList.toggle('hidden', !isLoading);
    }

    if (items) {
        items.classList.toggle('hidden', isLoading);
    }

    if (empty && isLoading) {
        empty.classList.add('hidden');
    }
}

function showEmpty(root, message = '通知はありません。') {
    const empty = root.querySelector('[data-notification-popover-empty]');
    const items = root.querySelector('[data-notification-popover-items]');

    if (items) {
        items.classList.add('hidden');
    }

    if (empty) {
        empty.textContent = message;
        empty.classList.remove('hidden');
    }
}

function showItems(root) {
    const empty = root.querySelector('[data-notification-popover-empty]');
    const items = root.querySelector('[data-notification-popover-items]');

    if (empty) {
        empty.classList.add('hidden');
    }

    if (items) {
        items.classList.remove('hidden');
    }
}
