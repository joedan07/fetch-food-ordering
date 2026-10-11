/* ==========================================================================
   Fetch — shared front-end helpers.
   Plain ES6: no framework, no build step. Pages add their own small scripts
   on top of this.
   ========================================================================== */

/** Theme, remembered per browser. */
function toggleTheme() {
  const r = document.documentElement;
  r.dataset.theme = r.dataset.theme === 'light' ? 'dark' : 'light';
  try { localStorage.setItem('fetch-theme', r.dataset.theme); } catch (e) {}
}

/** Small corner message. */
function toast(msg, type = 'ok', ms = 3800) {
  const box = document.getElementById('toasts');
  if (!box) return alert(msg);
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + String(msg).replace(/</g, '&lt;') + '</span>';
  box.appendChild(el);
  setTimeout(() => { el.classList.add('out'); setTimeout(() => el.remove(), 300); }, ms);
}

/** POST JSON to one of our endpoints and return the parsed reply. */
async function api(path, data = {}) {
  const res = await fetch(FETCH.base + path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF': FETCH.csrf },
    body: JSON.stringify(data),
  });
  let out = {};
  try { out = await res.json(); } catch (e) { out = { error: 'Server error (' + res.status + ')' }; }
  if (!res.ok || out.error) throw new Error(out.error || 'Request failed');
  return out;
}

/** GET JSON. */
async function apiGet(path) {
  const res = await fetch(FETCH.base + path, { headers: { 'X-CSRF': FETCH.csrf } });
  if (!res.ok) throw new Error('Request failed (' + res.status + ')');
  return res.json();
}

/**
 * Poll an endpoint on a timer. Pauses while the tab is hidden so a laptop left
 * open during the review doesn't hammer the server, and backs off after errors.
 */
function poll(fn, ms = 4000) {
  let stop = false, fails = 0;
  const tick = async () => {
    if (stop) return;
    if (document.visibilityState === 'visible') {
      try { await fn(); fails = 0; } catch (e) { fails++; }
    }
    setTimeout(tick, ms * Math.min(1 + fails, 4));
  };
  setTimeout(tick, ms);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') fn().catch(() => {}); });
  return () => { stop = true; };
}

/** Copy text and say so. */
function copyText(text, note = 'Copied') {
  navigator.clipboard?.writeText(text).then(() => toast(note), () => toast('Copy failed', 'err'));
}

function money(n) {
  return '₹' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 });
}

/** Close the avatar menu when clicking anywhere else. */
document.addEventListener('click', (e) => {
  document.querySelectorAll('.menu-wrap.open').forEach((m) => { if (!m.contains(e.target)) m.classList.remove('open'); });
});

/** Any form with data-confirm asks first. */
document.addEventListener('submit', (e) => {
  const msg = e.target.dataset.confirm;
  if (msg && !confirm(msg)) e.preventDefault();
});

/** Stop double-submits on slow connections. */
document.addEventListener('submit', (e) => {
  const b = e.target.querySelector('button[type=submit], button:not([type])');
  if (b && !e.defaultPrevented) setTimeout(() => { b.disabled = true; b.style.opacity = .6; }, 10);
});

/* ==========================================================================
   MOTION
   Wired up once, for every page: scroll reveals, counters that roll, the
   cursor spotlight, magnetic buttons and click sparks. All of it checks
   prefers-reduced-motion first.
   ========================================================================== */
const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Replace a container's HTML only when it actually changed, and animate in
 *  whatever is new. Keeps polled panels from flickering every few seconds. */
function setHTML(el, html, animate = true) {
  if (!el || el.dataset.sig === html) return false;
  const first = el.dataset.sig === undefined;
  el.dataset.sig = html;
  el.innerHTML = html;
  if (animate && !REDUCED && !first) {
    [...el.children].forEach((c, i) => {
      c.classList.add('enter');
      c.style.animationDelay = i * 45 + 'ms';
      c.addEventListener('animationend', () => { c.classList.remove('enter'); c.style.animationDelay = ''; }, { once: true });
    });
  }
  return true;
}

/** Roll a number to a new value instead of snapping to it. */
function setNum(el, value, { prefix = '', suffix = '', decimals = 0 } = {}) {
  if (!el) return;
  const to = Number(value);
  const from = Number(el.dataset.val ?? to);
  el.dataset.val = to;
  const fmt = (n) => prefix + n.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
  if (REDUCED || from === to) { el.textContent = fmt(to); return; }
  const t0 = performance.now(), dur = 650;
  const tick = (t) => {
    const p = Math.min((t - t0) / dur, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = fmt(Math.round((from + (to - from) * eased) * 10 ** decimals) / 10 ** decimals);
    if (p < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
  el.classList.add('bump');
  setTimeout(() => el.classList.remove('bump'), 520);
}

document.addEventListener('DOMContentLoaded', () => {
  /* --- scroll reveal: sections, cards, rows ------------------------------ */
  const targets = document.querySelectorAll('.card, .stat, .table-wrap, .r-card, .tape, .bars-wrap, .empty');
  if (!REDUCED && targets.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { threshold: .08, rootMargin: '0px 0px -30px 0px' });
    targets.forEach((el, i) => {
      // things already on screen at load get a quick stagger instead of a wait
      el.setAttribute('data-reveal', '');
      el.style.setProperty('--d', Math.min(i, 6));
      io.observe(el);
    });
  }

  /* --- scroll progress + shrinking top bar ------------------------------- */
  const bar = document.getElementById('progress');
  const top = document.querySelector('.topbar');
  const onScroll = () => {
    const h = document.documentElement.scrollHeight - innerHeight;
    if (bar) bar.style.transform = `scaleX(${h > 40 ? scrollY / h : 0})`;
    if (top) top.classList.toggle('shrunk', scrollY > 30);
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* --- cursor spotlight + per-card glow ---------------------------------- */
  const spot = document.querySelector('.spot');
  if (!REDUCED && matchMedia('(pointer: fine)').matches) {
    addEventListener('pointermove', (e) => {
      document.documentElement.style.setProperty('--mx', e.clientX + 'px');
      document.documentElement.style.setProperty('--my', e.clientY + 'px');
      spot?.classList.add('on');
      const card = e.target.closest?.('.card');
      if (card) {
        const r = card.getBoundingClientRect();
        card.style.setProperty('--cx', e.clientX - r.left + 'px');
        card.style.setProperty('--cy', e.clientY - r.top + 'px');
      }
      const btn = e.target.closest?.('.btn');
      if (btn) {
        const r = btn.getBoundingClientRect();
        btn.style.setProperty('--bx', ((e.clientX - r.left) / r.width * 100) + '%');
        btn.style.setProperty('--by', ((e.clientY - r.top) / r.height * 100) + '%');
        // a gentle magnetic pull towards the pointer
        btn.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .12}px, ${(e.clientY - r.top - r.height / 2) * .22}px)`;
      }
    }, { passive: true });
    addEventListener('pointerout', (e) => {
      const btn = e.target.closest?.('.btn');
      if (btn) btn.style.transform = '';
    });
  }

  /* --- click sparks ------------------------------------------------------ */
  if (!REDUCED) {
    addEventListener('pointerdown', (e) => {
      if (!e.target.closest('.btn, .switch, .qty button, .icon-btn, .demo-grid button')) return;
      for (let i = 0; i < 7; i++) {
        const s = document.createElement('i');
        s.className = 'spark';
        s.style.left = e.clientX - 1 + 'px';
        s.style.top = e.clientY - 5 + 'px';
        s.style.setProperty('--a', (i * 51 + 10) + 'deg');
        document.body.appendChild(s);
        setTimeout(() => s.remove(), 520);
      }
    });
  }

  /* --- numbers that count up on first sight ------------------------------ */
  const nums = document.querySelectorAll('[data-count]');
  if (nums.length) {
    const cio = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        const el = e.target;
        setNum(el, el.dataset.count, { prefix: el.dataset.prefix || '', suffix: el.dataset.suffix || '', decimals: +(el.dataset.decimals || 0) });
        cio.unobserve(el);
      });
    }, { threshold: .6 });
    nums.forEach((el) => { el.dataset.val = 0; el.textContent = (el.dataset.prefix || '') + '0' + (el.dataset.suffix || ''); cio.observe(el); });
  }

  /* --- rotating word in a hero ------------------------------------------- */
  const rot = document.querySelectorAll('.rotator > span');
  if (rot.length > 1 && !REDUCED) {
    let i = 0;
    setInterval(() => {
      rot[i].classList.replace('is-on', 'is-off');
      const prev = rot[i];
      setTimeout(() => prev.classList.remove('is-off'), 700);
      i = (i + 1) % rot.length;
      rot[i].classList.add('is-on');
    }, 2400);
  }
});
