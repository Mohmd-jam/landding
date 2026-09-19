(function () {
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];
  const root = document.documentElement;
  $$('[data-theme-toggle]').forEach(b => b.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    document.cookie = 'theme=' + next + ';path=/;max-age=' + 86400 * 365 + ';samesite=lax';
  }));
  $$('[data-side-toggle]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('side-open')));
  $$('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
  const tabs = document.querySelector('[data-tabs]');
  if (tabs) {
    const show = id => {
      $$('button', tabs).forEach(b => b.classList.toggle('active', b.dataset.tab === id));
      $$('.tab').forEach(t => t.classList.toggle('active', t.id === id));
      history.replaceState(null, '', '#' + id);
    };
    tabs.addEventListener('click', e => { const b = e.target.closest('button'); if (b) show(b.dataset.tab); });
    if (location.hash && document.getElementById(location.hash.slice(1))) show(location.hash.slice(1));
  }
  $$('input[type=color]').forEach(i => i.addEventListener('input', () => { const c = i.nextElementSibling; if (c) c.textContent = i.value; }));
})();
