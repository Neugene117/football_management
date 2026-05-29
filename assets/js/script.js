(function () {
  /* ═══════════════════════════════
     SIDEBAR TOGGLE
  ═══════════════════════════════ */
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');

  let overlay = document.getElementById('mobileOverlay');
  if (!overlay) {
    overlay = document.querySelector('.mobile-overlay');
  }
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'mobile-overlay';
    overlay.id = 'mobileOverlay';
    document.body.appendChild(overlay);
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
    document.body.style.overflow = '';
  }

  function openSidebar() {
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      if (sidebar.classList.contains('open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeSidebar();
    });
  }

  /* ═══════════════════════════════
     NAV GROUP ACCORDION
  ═══════════════════════════════ */
  document.querySelectorAll('[data-nav-group]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const grp = btn.closest('.nav-group');
      if (grp) grp.classList.toggle('open');
    });
  });

  /* ═══════════════════════════════
     DROPDOWN SYSTEM
  ═══════════════════════════════ */
  document.querySelectorAll('.dropdown-toggle').forEach((button) => {
    button.addEventListener('click', function (e) {
      e.stopPropagation();
      const drop = this.closest('.dropdown');

      // Close all other dropdowns
      document.querySelectorAll('.dropdown').forEach((d) => {
        if (d !== drop) d.classList.remove('open');
      });

      if (drop) {
        drop.classList.toggle('open');

        // Animate notification items when opening
        if (drop.classList.contains('notif-dropdown') && drop.classList.contains('open')) {
          const items = drop.querySelectorAll('.notif-item');
          items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(-8px)';
            setTimeout(() => {
              item.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
              item.style.opacity = '1';
              item.style.transform = 'translateY(0)';
            }, index * 50);
          });
        }
      }
    });
  });

  // Close dropdowns when clicking outside
  document.addEventListener('click', (e) => {
    document.querySelectorAll('.dropdown').forEach((d) => {
      if (!d.contains(e.target)) {
        d.classList.remove('open');
      }
    });
  });

  // Prevent dropdown menu clicks from closing the dropdown
  document.querySelectorAll('.dropdown-menu').forEach((menu) => {
    menu.addEventListener('click', (e) => {
      // Only stop propagation for notification items, not links
      if (e.target.closest('.notif-item') || e.target.closest('.notif-mark-all')) {
        e.stopPropagation();
      }
    });
  });

  /* ═══════════════════════════════
     NOTIFICATION SYSTEM
  ═══════════════════════════════ */

  const notifList = document.getElementById('notifList');
  const notifDropdown = document.getElementById('notifDropdown');

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function notificationIcon(type) {
    const icons = {
      info: 'fa-circle-info',
      success: 'fa-circle-check',
      warning: 'fa-triangle-exclamation',
      error: 'fa-circle-xmark',
      approval: 'fa-clipboard-check',
      team: 'fa-shield-halved',
      match: 'fa-futbol',
      user: 'fa-user-plus',
    };
    return icons[type] || icons.info;
  }

  function notificationColor(type) {
    const colors = {
      info: 'notif-info',
      success: 'notif-success',
      warning: 'notif-warning',
      error: 'notif-error',
      approval: 'notif-approval',
      team: 'notif-team',
      match: 'notif-match',
      user: 'notif-user',
    };
    return colors[type] || colors.info;
  }

  function renderNotificationItem(item) {
    const isUnread = parseInt(item.is_read, 10) === 0;
    const type = item.type || 'info';
    const message = item.message
      ? `<p class="notif-message">${escapeHtml(String(item.message).slice(0, 120))}</p>`
      : '';
    const dot = isUnread ? '<span class="notif-dot"></span>' : '';

    return `
      <div class="notif-item ${isUnread ? 'unread' : ''} ${notificationColor(type)}"
           data-notif-id="${escapeHtml(item.id)}"
           role="button" tabindex="0">
        <div class="notif-icon-wrap">
          <i class="fa-solid ${notificationIcon(type)}"></i>
        </div>
        <div class="notif-content">
          <p class="notif-title">${escapeHtml(item.title)}</p>
          ${message}
          <span class="notif-time">
            <i class="fa-regular fa-clock"></i>
            ${escapeHtml(item.time_ago || 'Just now')}
          </span>
        </div>
        ${dot}
      </div>`;
  }

  function renderNotifications(items) {
    if (!notifList) return;

    if (!items || items.length === 0) {
      notifList.innerHTML = `
        <div class="notif-empty">
          <i class="fa-regular fa-bell-slash"></i>
          <p>No notifications yet</p>
        </div>`;
      return;
    }

    notifList.innerHTML = items.map(renderNotificationItem).join('');
  }

  function ensureMarkAllButton(count) {
    const header = document.querySelector('.notif-header');
    if (!header) return;

    let markAll = document.getElementById('markAllRead');
    if (count > 0 && !markAll) {
      markAll = document.createElement('button');
      markAll.className = 'notif-mark-all';
      markAll.id = 'markAllRead';
      markAll.type = 'button';
      markAll.textContent = 'Mark all read';
      header.appendChild(markAll);
      bindMarkAllButton(markAll);
    } else if (count <= 0 && markAll) {
      markAll.remove();
    }
  }

  function setBadgeCount(count) {
    const button = notifDropdown ? notifDropdown.querySelector('.topbar-action-item') : null;
    let badge = document.getElementById('notifBadge');

    if (count <= 0) {
      if (badge) badge.remove();
      ensureMarkAllButton(0);
      return;
    }

    if (!badge && button) {
      badge = document.createElement('span');
      badge.className = 'topbar-badge';
      badge.id = 'notifBadge';
      button.appendChild(badge);
    }

    if (badge) {
      badge.textContent = count;
      badge.classList.add('badge-pulse');
      setTimeout(() => badge.classList.remove('badge-pulse'), 400);
    }

    ensureMarkAllButton(count);
  }

  function markNotificationRead(item) {
    const notifId = item.dataset.notifId;
    if (!notifId || !item.classList.contains('unread')) return;

    item.classList.remove('unread');
    item.classList.add('read-transition');
    const dot = item.querySelector('.notif-dot');
    if (dot) {
      dot.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      dot.style.opacity = '0';
      dot.style.transform = 'scale(0)';
      setTimeout(() => dot.remove(), 300);
    }

    updateBadgeCount(-1);

    const formData = new FormData();
    formData.append('mark_notification_read', '1');
    formData.append('notification_id', notifId);

    fetch(window.location.pathname, {
      method: 'POST',
      body: formData,
    }).catch(() => {
      item.classList.add('unread');
      item.classList.remove('read-transition');
      updateBadgeCount(1);
    });
  }

  if (notifList) {
    notifList.addEventListener('click', (e) => {
      const item = e.target.closest('.notif-item[data-notif-id]');
      if (item) markNotificationRead(item);
    });

    notifList.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      const item = e.target.closest('.notif-item[data-notif-id]');
      if (!item) return;
      e.preventDefault();
      markNotificationRead(item);
    });
  }

  // Mark all notifications as read
  function bindMarkAllButton(button) {
    if (!button || button.dataset.bound === '1') return;
    button.dataset.bound = '1';
    button.addEventListener('click', function () {
      const unreadItems = document.querySelectorAll('.notif-item.unread');

      // Optimistic UI update
      unreadItems.forEach((item, index) => {
        setTimeout(() => {
          item.classList.remove('unread');
          item.classList.add('read-transition');
          const dot = item.querySelector('.notif-dot');
          if (dot) {
            dot.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            dot.style.opacity = '0';
            dot.style.transform = 'scale(0)';
            setTimeout(() => dot.remove(), 300);
          }
        }, index * 80);
      });

      // Hide badge
      const badge = document.getElementById('notifBadge');
      if (badge) {
        badge.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        badge.style.opacity = '0';
        badge.style.transform = 'scale(0)';
        setTimeout(() => badge.remove(), 300);
      }

      // Hide the mark all button
      this.style.transition = 'opacity 0.3s ease';
      this.style.opacity = '0';
      setTimeout(() => this.remove(), 300);

      // Send AJAX request
      const formData = new FormData();
      formData.append('mark_all_notifications_read', '1');

      fetch(window.location.pathname, {
        method: 'POST',
        body: formData,
      }).catch(() => {
        // On failure, just reload the page
        window.location.reload();
      });
    });
  }

  bindMarkAllButton(document.getElementById('markAllRead'));

  function updateBadgeCount(delta) {
    const badge = document.getElementById('notifBadge');
    let count = badge ? parseInt(badge.textContent, 10) || 0 : 0;
    count = Math.max(0, count + delta);
    setBadgeCount(count);
  }

  function pollNotifications() {
    if (!notifList) return;

    fetch(`${window.location.pathname}?notifications_poll=1`, {
      headers: { Accept: 'application/json' },
    })
      .then((response) => response.ok ? response.json() : null)
      .then((payload) => {
        if (!payload || !payload.success) return;
        renderNotifications(payload.notifications || []);
        setBadgeCount(parseInt(payload.unread_count, 10) || 0);
      })
      .catch(() => {});
  }

  if (notifList) {
    setInterval(pollNotifications, 15000);
  }

  /* ═══════════════════════════════
     MODALS
  ═══════════════════════════════ */
  document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = document.querySelector(btn.dataset.openModal);
      if (target) target.classList.add('active');
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal');
      if (modal) modal.classList.remove('active');
    });
  });

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('active');
    });
  });

  /* ═══════════════════════════════
     CONFIRMATIONS
  ═══════════════════════════════ */
  document.querySelectorAll('[data-confirm]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      const msg = btn.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  });

  /* ═══════════════════════════════
     COUNTER ANIMATIONS
  ═══════════════════════════════ */
  document.querySelectorAll('[data-counter]').forEach((el) => {
    const target = parseInt(el.dataset.counter || '0', 10);
    const start = performance.now();
    const duration = 720;

    const animate = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(target * eased).toLocaleString();
      if (progress < 1) requestAnimationFrame(animate);
    };

    requestAnimationFrame(animate);
  });

  /* ═══════════════════════════════
     CHART RENDERING
  ═══════════════════════════════ */
  function drawLineChart(canvas, values, color, fillColor) {
    if (!canvas || values.length < 1) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;
    canvas.width = width * 2;
    canvas.height = height * 2;
    ctx.scale(2, 2);

    const max = Math.max(...values, 1);
    const stepX = width / (values.length - 1 || 1);

    ctx.clearRect(0, 0, width, height);
    ctx.strokeStyle = '#e8eef7';
    ctx.lineWidth = 1;
    for (let i = 0; i < 5; i++) {
      const y = (height / 4) * i;
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(width, y);
      ctx.stroke();
    }

    ctx.strokeStyle = color;
    ctx.lineWidth = 2.4;
    ctx.beginPath();
    values.forEach((v, i) => {
      const x = i * stepX;
      const y = height - (v / max) * (height - 22) - 11;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();

    if (values.length > 1) {
      ctx.lineTo(width, height - 10);
      ctx.lineTo(0, height - 10);
      ctx.closePath();
      ctx.fillStyle = fillColor;
      ctx.fill();

      ctx.strokeStyle = color;
      ctx.lineWidth = 2.4;
      ctx.beginPath();
      values.forEach((v, i) => {
        const x = i * stepX;
        const y = height - (v / max) * (height - 22) - 11;
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();
    }

    ctx.fillStyle = color;
    values.forEach((v, i) => {
      const x = i * stepX;
      const y = height - (v / max) * (height - 22) - 11;
      ctx.beginPath();
      ctx.arc(x, y, 2.8, 0, Math.PI * 2);
      ctx.fill();
    });
  }

  function drawBarChart(canvas, values, color) {
    if (!canvas || values.length < 1) return;
    const ctx = canvas.getContext('2d');
    const width = canvas.offsetWidth;
    const height = canvas.offsetHeight;
    canvas.width = width * 2;
    canvas.height = height * 2;
    ctx.scale(2, 2);

    const max = Math.max(...values, 1);
    const gap = 8;
    const barW = (width - gap * (values.length + 1)) / values.length;

    ctx.clearRect(0, 0, width, height);
    values.forEach((v, i) => {
      const bh = (v / max) * (height - 18);
      const x = gap + i * (barW + gap);
      const y = height - bh - 6;
      ctx.fillStyle = color;
      ctx.fillRect(x, y, barW, bh);
    });
  }

  document.querySelectorAll('[data-line-chart]').forEach((cv) => {
    const values = (cv.dataset.values || '').split(',').map((v) => parseFloat(v) || 0);
    drawLineChart(
      cv,
      values,
      cv.dataset.color || '#0d6b4e',
      cv.dataset.fill || 'rgba(13, 107, 78, 0.16)'
    );
  });

  document.querySelectorAll('[data-bar-chart]').forEach((cv) => {
    const values = (cv.dataset.values || '').split(',').map((v) => parseFloat(v) || 0);
    drawBarChart(cv, values, '#0b1f3a');
  });

  /* ═══════════════════════════════
     ROLE SELECTION AUTO-DISABLING
  ═══════════════════════════════ */
  function initRoleSelection() {
    const checkboxes = document.querySelectorAll('.role-checkbox');
    if (checkboxes.length === 0) return;

    function updateStates() {
      // Find if any master role is checked
      let checkedMaster = null;
      checkboxes.forEach((cb) => {
        if (cb.checked) {
          cb.closest('.role-card-item').classList.add('active');
          if (cb.dataset.isMaster === '1') {
            checkedMaster = cb;
          }
        } else {
          cb.closest('.role-card-item').classList.remove('active');
        }
      });

      if (checkedMaster) {
        // Disable and uncheck all others
        checkboxes.forEach((cb) => {
          if (cb !== checkedMaster) {
            cb.disabled = true;
            cb.closest('.role-card-item').classList.add('disabled-role');
            if (cb.checked) {
              cb.checked = false;
              cb.closest('.role-card-item').classList.remove('active');
            }
          }
        });
      } else {
        // Enable everything
        checkboxes.forEach((cb) => {
          cb.disabled = false;
          cb.closest('.role-card-item').classList.remove('disabled-role');
        });
      }
    }

    checkboxes.forEach((cb) => {
      cb.addEventListener('change', updateStates);
    });

    // Run once on load to initialize state
    updateStates();
  }

  // Initialize
  initRoleSelection();

  /* ═══════════════════════════════
     LOADING SPINNER
  ═══════════════════════════════ */
  const spinner = document.getElementById('loadingSpinner');
  if (spinner) {
    window.addEventListener('load', () => {
      spinner.style.transition = 'opacity 0.4s ease';
      spinner.style.opacity = '0';
      setTimeout(() => {
        spinner.style.display = 'none';
      }, 400);
    });
  }
})();
