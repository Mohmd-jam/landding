/**
 * Tiny promise-based HTTP client for both bundles.
 *
 * - sends the CSRF token on every write (read from the bootstrap payload or the
 *   live /api/admin/session response),
 * - forwards the current locale so the API answers in the right language,
 * - normalises the `{ data, meta, message, error }` envelope the backend uses,
 * - surfaces field-level validation errors so forms can highlight inputs.
 */

const state = {
  csrf: null,
  locale: null,
  onUnauthorized: null,
};

export function setCsrfToken(token) {
  state.csrf = token ?? null;
}

export function csrfToken() {
  return state.csrf;
}

export function setLocale(locale) {
  state.locale = locale ?? null;
}

export function onUnauthorized(handler) {
  state.onUnauthorized = handler;
}

export class ApiError extends Error {
  constructor(message, { status = 0, fields = null, payload = null } = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.fields = fields;
    this.payload = payload;
  }
}

function query(params = {}) {
  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    if (Array.isArray(value)) {
      value.forEach((item) => search.append(`${key}[]`, String(item)));
      return;
    }
    search.set(key, String(value));
  });

  const text = search.toString();

  return text ? `?${text}` : '';
}

async function request(method, path, { body, params, formData, headers = {}, locale, raw = false } = {}) {
  const isWrite = !['GET', 'HEAD'].includes(method);
  const url = path + query({ ...(locale ? { lang: locale } : state.locale ? { lang: state.locale } : {}), ...params });

  const init = {
    method,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(state.locale ? { 'X-Locale': state.locale } : {}),
      ...headers,
    },
  };

  if (isWrite && state.csrf) {
    init.headers['X-CSRF-Token'] = state.csrf;
  }

  if (formData) {
    init.body = formData;
  } else if (body !== undefined) {
    init.headers['Content-Type'] = 'application/json';
    init.body = JSON.stringify(body);
  }

  const response = await fetch(url, init);
  const contentType = response.headers.get('content-type') || '';
  const payload = contentType.includes('application/json') ? await response.json().catch(() => null) : null;

  if (response.status === 401) {
    state.onUnauthorized?.(payload);
  }

  if (!response.ok) {
    const error = payload?.error ?? {};
    throw new ApiError(error.message || `Request failed (${response.status})`, {
      status: response.status,
      fields: error.fields ?? null,
      payload,
    });
  }

  if (raw) {
    return response;
  }

  return payload ?? {};
}

export const api = {
  get: (path, options) => request('GET', path, options),
  post: (path, body, options) => request('POST', path, { ...options, body }),
  put: (path, body, options) => request('PUT', path, { ...options, body }),
  patch: (path, body, options) => request('PATCH', path, { ...options, body }),
  delete: (path, options) => request('DELETE', path, options),
  upload: (path, formData, options) => request('POST', path, { ...options, formData }),
};

/* -------------------------------------------------------------------------- */
/* Public API (v1)                                                            */
/* -------------------------------------------------------------------------- */

export const publicApi = {
  bootstrap: (locale) => api.get('/api/v1/bootstrap', { locale }),
  home: (locale) => api.get('/api/v1/home', { locale }),
  projects: (locale, params) => api.get('/api/v1/projects', { locale, params }),
  project: (locale, slug) => api.get(`/api/v1/projects/${encodeURIComponent(slug)}`, { locale }),
  projectCategories: (locale) => api.get('/api/v1/project-categories', { locale }),
  technologies: (locale) => api.get('/api/v1/technologies', { locale }),
  posts: (locale, params) => api.get('/api/v1/posts', { locale, params }),
  post: (locale, slug) => api.get(`/api/v1/posts/${encodeURIComponent(slug)}`, { locale }),
  blogCategories: (locale) => api.get('/api/v1/blog-categories', { locale }),
  blogTags: (locale) => api.get('/api/v1/blog-tags', { locale }),
  services: (locale) => api.get('/api/v1/services', { locale }),
  service: (locale, slug) => api.get(`/api/v1/services/${encodeURIComponent(slug)}`, { locale }),
  skills: (locale) => api.get('/api/v1/skills', { locale }),
  timeline: (locale) => api.get('/api/v1/timeline', { locale }),
  testimonials: (locale) => api.get('/api/v1/testimonials', { locale }),
  resume: (locale) => api.get('/api/v1/resume', { locale }),
  search: (locale, term) => api.get('/api/v1/search', { locale, params: { q: term } }),
  contact: (locale, body) => api.post('/api/v1/contact', body, { locale }),
  translateSlug: (params) => api.get('/api/v1/translate-slug', { params }),
};

/* -------------------------------------------------------------------------- */
/* Admin API                                                                  */
/* -------------------------------------------------------------------------- */

export const adminApi = {
  session: () => api.get('/api/admin/session'),
  login: (credentials) => api.post('/api/admin/login', credentials),
  logout: () => api.post('/api/admin/logout'),
  me: () => api.get('/api/admin/me'),
  changePassword: (body) => api.put('/api/admin/password', body),
  dashboard: () => api.get('/api/admin/dashboard'),
  schema: () => api.get('/api/admin/schema'),

  list: (resource, params) => api.get(`/api/admin/${resource}`, { params }),
  show: (resource, id) => api.get(`/api/admin/${resource}/${id}`),
  create: (resource, body) => api.post(`/api/admin/${resource}`, body),
  update: (resource, id, body) => api.put(`/api/admin/${resource}/${id}`, body),
  destroy: (resource, id) => api.delete(`/api/admin/${resource}/${id}`),
  toggle: (resource, id, field) => api.post(`/api/admin/${resource}/${id}/toggle`, { field }),
  reorder: (resource, ids) => api.post(`/api/admin/${resource}/reorder`, { ids }),

  media: (params) => api.get('/api/admin/media', { params }),
  uploadMedia: (formData) => api.upload('/api/admin/media', formData),
  updateMedia: (id, body) => api.put(`/api/admin/media/${id}`, body),
  deleteMedia: (id, force = false) => api.delete(`/api/admin/media/${id}`, { params: { force: force ? 1 : 0 } }),
  mediaBulk: (body) => api.post('/api/admin/media/bulk', body),

  messages: (params) => api.get('/api/admin/messages', { params }),
  message: (id) => api.get(`/api/admin/messages/${id}`),
  updateMessage: (id, body) => api.put(`/api/admin/messages/${id}`, body),
  deleteMessage: (id) => api.delete(`/api/admin/messages/${id}`),
  messageBulk: (body) => api.post('/api/admin/messages/bulk', body),

  settings: () => api.get('/api/admin/settings'),
  saveSettings: (settings) => api.put('/api/admin/settings', { settings }),
  languages: () => api.get('/api/admin/languages'),
  createLanguage: (body) => api.post('/api/admin/languages', body),
  updateLanguage: (id, body) => api.put(`/api/admin/languages/${id}`, body),
  deleteLanguage: (id, force = false) => api.delete(`/api/admin/languages/${id}`, { params: { force: force ? 1 : 0 } }),
  translations: (params) => api.get('/api/admin/translations', { params }),
  saveTranslation: (body) => api.post('/api/admin/translations', body),
  updateTranslation: (id, body) => api.put(`/api/admin/translations/${id}`, body),
  deleteTranslation: (id) => api.delete(`/api/admin/translations/${id}`),
  navigation: () => api.get('/api/admin/navigation'),
  createNavigation: (body) => api.post('/api/admin/navigation', body),
  updateNavigation: (id, body) => api.put(`/api/admin/navigation/${id}`, body),
  deleteNavigation: (id) => api.delete(`/api/admin/navigation/${id}`),
  reorderNavigation: (ids, parentId = null) => api.post('/api/admin/navigation/reorder', { ids, parent_id: parentId }),
  seo: (params) => api.get('/api/admin/seo', { params }),
  saveSeo: (body) => api.put('/api/admin/seo', body),
  regenerateSitemap: () => api.post('/api/admin/seo/sitemap'),
  users: (params) => api.get('/api/admin/users', { params }),
  createUser: (body) => api.post('/api/admin/users', body),
  updateUser: (id, body) => api.put(`/api/admin/users/${id}`, body),
  deleteUser: (id) => api.delete(`/api/admin/users/${id}`),
  security: () => api.get('/api/admin/security'),
  revokeSession: (id) => api.delete(`/api/admin/security/sessions/${encodeURIComponent(id)}`),
  clearAttempts: () => api.post('/api/admin/security/attempts/clear'),
  clearCache: () => api.post('/api/admin/security/cache/clear'),
  uploadResume: (id, formData) => api.upload(`/api/admin/resumes/${id}/file`, formData),
};
