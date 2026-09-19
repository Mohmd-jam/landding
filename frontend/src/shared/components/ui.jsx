import { useEffect, useRef, useState } from 'react';

/**
 * Small, dependency-free UI primitives shared by the site and the admin panel.
 * They only handle presentation; data always arrives from the API.
 */

export function Button({
  as = 'button',
  variant = 'primary',
  size = 'md',
  href,
  loading = false,
  icon = null,
  children,
  ...rest
}) {
  const Tag = href ? 'a' : as;
  const classes = ['btn', `btn-${variant}`, `btn-${size}`, loading ? 'is-loading' : ''].filter(Boolean).join(' ');
  const props = Tag === 'button' ? { type: rest.type ?? 'button', ...rest } : { href, ...rest };

  return (
    <Tag className={classes} {...props} aria-busy={loading || undefined}>
      {icon ? <span className="btn-icon" aria-hidden="true">{icon}</span> : null}
      <span>{children}</span>
    </Tag>
  );
}

export function Card({ as: Tag = 'div', interactive = false, className = '', children, ...rest }) {
  return (
    <Tag className={`card ${interactive ? 'card-interactive' : ''} ${className}`.trim()} {...rest}>
      {children}
    </Tag>
  );
}

export function Badge({ children, tone = 'default', mono = false }) {
  return <span className={`badge badge-${tone} ${mono ? 'badge-mono' : ''}`.trim()}>{children}</span>;
}

export function Section({ id, eyebrow, title, lead, actions, children, className = '' }) {
  return (
    <section id={id} className={`section ${className}`.trim()}>
      <div className="section-head">
        <div>
          {eyebrow ? <p className="section-eyebrow">{eyebrow}</p> : null}
          {title ? <h2>{title}</h2> : null}
          {lead ? <p className="section-lead">{lead}</p> : null}
        </div>
        {actions ? <div className="section-actions">{actions}</div> : null}
      </div>
      {children}
    </section>
  );
}

export function Skeleton({ height = 16, width = '100%', radius = 8 }) {
  return <span className="skeleton" style={{ height, width, borderRadius: radius }} aria-hidden="true" />;
}

export function Spinner({ label }) {
  return (
    <span className="spinner" role="status" aria-live="polite">
      <span className="spinner-dot" />
      <span className="sr-only">{label ?? 'Loading'}</span>
    </span>
  );
}

export function EmptyState({ title, description, action }) {
  return (
    <div className="empty-state">
      <p className="empty-title">{title}</p>
      {description ? <p className="empty-description">{description}</p> : null}
      {action}
    </div>
  );
}

export function Alert({ tone = 'info', children, onDismiss }) {
  return (
    <div className={`alert alert-${tone}`} role={tone === 'error' ? 'alert' : 'status'}>
      <div>{children}</div>
      {onDismiss ? (
        <button type="button" className="alert-close" onClick={onDismiss} aria-label="Dismiss">
          ×
        </button>
      ) : null}
    </div>
  );
}

export function Field({ label, hint, error, required = false, children, htmlFor }) {
  return (
    <div className={`field ${error ? 'has-error' : ''}`}>
      <label htmlFor={htmlFor}>
        {label}
        {required ? <span className="field-required" aria-hidden="true"> *</span> : null}
      </label>
      {children}
      {error ? <p className="field-error">{error}</p> : hint ? <p className="field-hint">{hint}</p> : null}
    </div>
  );
}

export function Input({ error, ...rest }) {
  return <input className={`input ${error ? 'is-invalid' : ''}`.trim()} aria-invalid={error ? 'true' : undefined} {...rest} />;
}

export function Textarea({ error, rows = 5, ...rest }) {
  return <textarea className={`input textarea ${error ? 'is-invalid' : ''}`.trim()} rows={rows} aria-invalid={error ? 'true' : undefined} {...rest} />;
}

export function Select({ error, children, ...rest }) {
  return (
    <select className={`input select ${error ? 'is-invalid' : ''}`.trim()} aria-invalid={error ? 'true' : undefined} {...rest}>
      {children}
    </select>
  );
}

export function Toggle({ checked, onChange, label, id }) {
  return (
    <label className="toggle" htmlFor={id}>
      <input id={id} type="checkbox" checked={!!checked} onChange={(event) => onChange?.(event.target.checked)} />
      <span className="toggle-track" aria-hidden="true">
        <span className="toggle-thumb" />
      </span>
      <span className="toggle-label">{label}</span>
    </label>
  );
}

export function Tabs({ tabs, active, onChange }) {
  return (
    <div className="tabs" role="tablist">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          type="button"
          role="tab"
          aria-selected={tab.key === active}
          className={`tab ${tab.key === active ? 'is-active' : ''}`}
          onClick={() => onChange?.(tab.key)}
        >
          {tab.label}
          {tab.count !== undefined ? <span className="tab-count">{tab.count}</span> : null}
        </button>
      ))}
    </div>
  );
}

export function Pagination({ page, totalPages, onChange, labels = {} }) {
  if (totalPages <= 1) return null;

  const windowed = [];
  const from = Math.max(1, page - 2);
  const to = Math.min(totalPages, from + 4);

  for (let index = from; index <= to; index += 1) windowed.push(index);

  return (
    <nav className="pagination" aria-label={labels.label ?? 'Pagination'}>
      <button type="button" disabled={page <= 1} onClick={() => onChange(page - 1)} aria-label={labels.previous ?? 'Previous'}>
        ‹
      </button>
      {from > 1 ? <span className="pagination-gap">…</span> : null}
      {windowed.map((index) => (
        <button
          key={index}
          type="button"
          className={index === page ? 'is-active' : ''}
          aria-current={index === page ? 'page' : undefined}
          onClick={() => onChange(index)}
        >
          {index}
        </button>
      ))}
      {to < totalPages ? <span className="pagination-gap">…</span> : null}
      <button type="button" disabled={page >= totalPages} onClick={() => onChange(page + 1)} aria-label={labels.next ?? 'Next'}>
        ›
      </button>
    </nav>
  );
}

export function Rating({ value = 5, max = 5 }) {
  return (
    <span className="rating" aria-label={`${value} / ${max}`}>
      {Array.from({ length: max }, (_, index) => (
        <svg key={index} viewBox="0 0 20 20" width="14" height="14" aria-hidden="true" className={index < value ? 'is-filled' : ''}>
          <path d="M10 1.6l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8L1.5 7.8l5.9-.9z" />
        </svg>
      ))}
    </span>
  );
}

/** Lazy image with a reserved aspect box (no layout shift, no scroll jank). */
export function LazyImage({ src, alt = '', ratio = '16 / 10', className = '', sizes = '100vw', priority = false }) {
  const ref = useRef(null);
  const [visible, setVisible] = useState(priority);

  useEffect(() => {
    if (priority || visible || !ref.current) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setVisible(true);
            observer.disconnect();
          }
        });
      },
      { rootMargin: '200px' }
    );

    observer.observe(ref.current);

    return () => observer.disconnect();
  }, [priority, visible]);

  return (
    <span ref={ref} className={`lazy-image ${className}`.trim()} style={{ aspectRatio: ratio }}>
      {visible && src ? (
        <img src={src} alt={alt} sizes={sizes} loading={priority ? 'eager' : 'lazy'} decoding="async" />
      ) : null}
    </span>
  );
}

/** Dependency-free count-up that respects reduced-motion and re-runs on view. */
export function Counter({ value = 0, suffix = '', duration = 1200, digits = (v) => String(v) }) {
  const ref = useRef(null);
  const [display, setDisplay] = useState(0);
  const [started, setStarted] = useState(false);

  useEffect(() => {
    if (!ref.current || started) return undefined;

    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        setStarted(true);
        observer.disconnect();
      }
    });

    observer.observe(ref.current);

    return () => observer.disconnect();
  }, [started]);

  useEffect(() => {
    if (!started) return undefined;

    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    if (reduce) {
      setDisplay(Number(value) || 0);
      return undefined;
    }

    let frame;
    const start = performance.now();
    const target = Number(value) || 0;

    const step = (now) => {
      const progress = Math.min(1, (now - start) / duration);
      const eased = 1 - (1 - progress) ** 3;
      setDisplay(Math.round(target * eased));

      if (progress < 1) frame = requestAnimationFrame(step);
    };

    frame = requestAnimationFrame(step);

    return () => cancelAnimationFrame(frame);
  }, [duration, started, value]);

  return (
    <span ref={ref} className="counter">
      {digits(display)}
      {suffix}
    </span>
  );
}
