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

  // Mark single notification as read
  document.querySelectorAll('.notif-item[data-notif-id]').forEach((item) => {
    item.addEventListener('click', function () {
      const notifId = this.dataset.notifId;
      if (!notifId || !this.classList.contains('unread')) return;

      // Optimistic UI update
      this.classList.remove('unread');
      this.classList.add('read-transition');
      const dot = this.querySelector('.notif-dot');
      if (dot) {
        dot.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        dot.style.opacity = '0';
        dot.style.transform = 'scale(0)';
        setTimeout(() => dot.remove(), 300);
      }

      // Update badge count
      updateBadgeCount(-1);

      // Send AJAX request
      const formData = new FormData();
      formData.append('mark_notification_read', '1');
      formData.append('notification_id', notifId);

      fetch(window.location.pathname, {
        method: 'POST',
        body: formData,
      }).catch(() => {
        // Revert on failure
        this.classList.add('unread');
        this.classList.remove('read-transition');
        updateBadgeCount(1);
      });
    });

    // Keyboard support
    item.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  });

  // Mark all notifications as read
  const markAllBtn = document.getElementById('markAllRead');
  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
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

  function updateBadgeCount(delta) {
    const badge = document.getElementById('notifBadge');
    if (!badge) return;

    let count = parseInt(badge.textContent, 10) || 0;
    count = Math.max(0, count + delta);

    if (count <= 0) {
      badge.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
      badge.style.opacity = '0';
      badge.style.transform = 'scale(0)';
      setTimeout(() => badge.remove(), 300);

      // Also hide mark all button
      const markAll = document.getElementById('markAllRead');
      if (markAll) {
        markAll.style.transition = 'opacity 0.3s ease';
        markAll.style.opacity = '0';
        setTimeout(() => markAll.remove(), 300);
      }
    } else {
      badge.textContent = count;
      // Pulse animation
      badge.classList.add('badge-pulse');
      setTimeout(() => badge.classList.remove('badge-pulse'), 400);
    }
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
