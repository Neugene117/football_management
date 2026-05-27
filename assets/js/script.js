(function () {
  const spinner = document.getElementById('loadingSpinner');
  window.addEventListener('load', () => {
    if (spinner) spinner.style.display = 'none';
  });

  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('sidebarToggle');
  const overlay = document.createElement('div');
  overlay.className = 'mobile-overlay';
  document.body.appendChild(overlay);

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    overlay.classList.remove('show');
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', closeSidebar);
  }

  document.querySelectorAll('[data-nav-group]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const grp = btn.closest('.nav-group');
      if (grp) grp.classList.toggle('open');
    });
  });

  document.querySelectorAll('.dropdown-toggle').forEach((button) => {
    button.addEventListener('click', function (e) {
      e.stopPropagation();
      const drop = this.closest('.dropdown');
      document.querySelectorAll('.dropdown').forEach((d) => {
        if (d !== drop) d.classList.remove('open');
      });
      if (drop) drop.classList.toggle('open');
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown').forEach((d) => d.classList.remove('open'));
    if (window.innerWidth < 920) closeSidebar();
  });

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

  document.querySelectorAll('[data-confirm]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      const msg = btn.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  });

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
})();
