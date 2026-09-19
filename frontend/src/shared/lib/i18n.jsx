import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { publicApi, setLocale as setApiLocale } from './api.js';
import { digits, formatDate, formatNumber, withLocale } from './format.js';
import { DEFAULT_STRINGS } from './strings.js';

/**
 * Locale provider.
 *
 * The server already knows the locale and ships it in `window.__BOOTSTRAP__`
 * (settings, labels, navigation, contact details). Switching language refetches
 * that payload, sets `<html lang|dir>` and — when the current page is an entity
 * — asks the API for the translated slug so `/en/projects/erp` becomes
 * `/fa/projects/<fa-slug>` instead of a 404.
 */
const I18nContext = createContext(null);

export function I18nProvider({ initial, children }) {
  const [bootstrap, setBootstrap] = useState(initial);
  const locale = bootstrap?.locale ?? 'fa';
  const direction = bootstrap?.direction ?? (locale === 'fa' ? 'rtl' : 'ltr');
  const strings = useMemo(() => ({ ...DEFAULT_STRINGS, ...(bootstrap?.strings ?? {}) }), [bootstrap]);

  setApiLocale(locale);

  useEffect(() => {
    const root = document.documentElement;
    root.lang = locale;
    root.dir = direction;
  }, [locale, direction]);

  const t = useCallback(
    (key, fallback = '') => strings[key] ?? DEFAULT_STRINGS[key] ?? fallback ?? key,
    [strings]
  );

  const load = useCallback(async (nextLocale) => {
    if (!nextLocale || nextLocale === locale) return bootstrap;

    const payload = await publicApi.bootstrap(nextLocale);
    setBootstrap(payload.data);

    return payload.data;
  }, [bootstrap, locale]);

  /**
   * Keep the current page when switching language: entity pages translate their
   * slug, section pages keep the path.
   */
  const switchTo = useCallback(async (nextLocale) => {
    if (nextLocale === locale) return;

    await load(nextLocale);

    const segments = window.location.pathname.split('/').filter(Boolean);
    const type = segments[1] === 'projects' ? 'project' : segments[1] === 'blog' ? 'post' : null;
    const slug = type && segments[2] ? segments[2] : null;

    if (type && slug) {
      try {
        const result = await publicApi.translateSlug({ type, slug, from: locale, to: nextLocale });

        if (result?.data?.translated) {
          window.location.assign(`/${nextLocale}/${segments[1]}/${result.data.translated}`);
          return;
        }
      } catch {
        // fall through to the section root
      }

      window.location.assign(`/${nextLocale}/${segments[1]}`);
      return;
    }

    window.location.assign(withLocale(window.location.pathname, nextLocale));
  }, [load, locale]);

  const value = useMemo(() => ({
    bootstrap,
    locale,
    direction,
    locales: bootstrap?.locales ?? [{ code: 'fa' }, { code: 'en' }],
    strings,
    t,
    setBootstrap,
    load,
    switchTo,
    digits: (value_) => digits(value_, locale),
    number: (value_) => formatNumber(value_, locale),
    date: (value_, options) => formatDate(value_, locale, options),
    path: (path) => withLocale(path, locale),
  }), [bootstrap, direction, load, locale, strings, switchTo, t]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
  const context = useContext(I18nContext);

  if (!context) {
    throw new Error('useI18n must be used inside <I18nProvider>');
  }

  return context;
}
