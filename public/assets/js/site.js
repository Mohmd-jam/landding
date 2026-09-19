/* JamSoft — site interactions (no dependencies) */
(function () {
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const root = document.documentElement;
  const rtl = root.dir === 'rtl';
  const persian = n => rtl ? String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]) : String(n);

  // ---- Theme toggle (cookie so PHP renders the right theme on next load) ----
  $$('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    document.cookie = 'theme=' + next + ';path=/;max-age=' + 86400 * 365 + ';samesite=lax';
  }));

  // ---- Mobile nav ----
  $$('[data-nav-toggle]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('nav-open')));
  $$('#navLinks a').forEach(a => a.addEventListener('click', () => document.body.classList.remove('nav-open')));

  // ---- Reveal on scroll ----
  const io = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
  }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  $$('.reveal, .skill').forEach(el => io.observe(el));

  // ---- Counters ----
  const cio = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return; cio.unobserve(e.target);
    const el = e.target, target = parseFloat(el.dataset.count) || 0, dec = (String(el.dataset.count).split('.')[1] || '').length;
    const t0 = performance.now(), dur = 1400;
    const tick = t => {
      const p = Math.min(1, (t - t0) / dur), ease = 1 - Math.pow(1 - p, 3);
      el.textContent = persian((target * ease).toFixed(dec));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }), { threshold: 0.5 });
  $$('[data-count]').forEach(el => cio.observe(el));

  // ---- Terminal typing ----
  const term = $('#term');
  if (term) {
    const html = term.innerHTML;
    const lines = html.split('\n');
    term.innerHTML = '';
    let i = 0;
    const step = () => {
      if (i >= lines.length) return;
      term.innerHTML += (i ? '\n' : '') + lines[i++];
      setTimeout(step, i % 2 ? 380 : 160);
    };
    setTimeout(step, 400);
  }

  // ---- Portfolio filter ----
  const filters = $('[data-filters]');
  if (filters) {
    filters.addEventListener('click', e => {
      const b = e.target.closest('button'); if (!b) return;
      $$('button', filters).forEach(x => x.classList.toggle('active', x === b));
      const f = b.dataset.f;
      $$('[data-filter-grid] .pcard').forEach(c => c.classList.toggle('is-hidden', f !== '*' && c.dataset.cat !== f));
    });
  }

  // ---- Custom cursor + magnetic buttons (pointer devices only) ----
  if (matchMedia('(hover:hover) and (pointer:fine)').matches) {
    const cur = $('.cursor');
    let x = 0, y = 0, cx = 0, cy = 0;
    addEventListener('mousemove', e => { x = e.clientX; y = e.clientY; document.body.classList.add('has-cursor'); }, { passive: true });
    (function loop() { cx += (x - cx) * .2; cy += (y - cy) * .2; cur.style.transform = `translate(${cx}px,${cy}px) translate(-50%,-50%)`; requestAnimationFrame(loop); })();
    $$('a, button, .pcard').forEach(el => {
      el.addEventListener('mouseenter', () => document.body.classList.add('cursor-big'));
      el.addEventListener('mouseleave', () => document.body.classList.remove('cursor-big'));
    });
    $$('.magnetic').forEach(el => {
      el.addEventListener('mousemove', e => {
        const r = el.getBoundingClientRect();
        el.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * .18}px,${(e.clientY - r.top - r.height / 2) * .3}px)`;
      });
      el.addEventListener('mouseleave', () => el.style.transform = '');
    });
  }

  // ---- Scroll to #contact after redirect ----
  if (location.hash === '#contact' && $('#contact')) setTimeout(() => $('#contact').scrollIntoView({ behavior: 'smooth' }), 50);
})();
