/**
 * PGA Open — Client API.
 * Toutes les requ\u00eates passent par la connexion tenant (r\u00e9solue par le middleware backend).
 */
const PGA_API = (() => {
  const BASE = '/api/v1';
  let _token = null;

  function setToken(t) { _token = t; localStorage.setItem('pga_token', t); }
  function getToken() { return _token || localStorage.getItem('pga_token'); }
  function clearToken() { _token = null; localStorage.removeItem('pga_token'); }

  async function request(method, url, body = null) {
    const headers = { 'Accept': 'application/json' };
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const opts = { method, headers };
    if (body && !(body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    } else if (body) {
      opts.body = body;
    }

    const res = await fetch(`${BASE}${url}`, opts);

    if (res.status === 401) {
      clearToken();
      window.location.href = '/pages/login.html';
      throw new Error('Session expir\u00e9e');
    }

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || data.error || `Erreur ${res.status}`);
    return data;
  }

  const get = (url, params = {}) => {
    const qs = new URLSearchParams();
    for (const [key, val] of Object.entries(params)) {
      if (Array.isArray(val)) val.forEach(v => qs.append(key, v));
      else if (val !== undefined && val !== null && val !== '') qs.append(key, val);
    }
    const qsStr = qs.toString();
    return request('GET', qsStr ? `${url}?${qsStr}` : url);
  };
  const post   = (url, data)     => request('POST', url, data);
  const patch  = (url, data)     => request('PATCH', url, data);
  const put    = (url, data)     => request('PUT', url, data);
  const del    = (url)           => request('DELETE', url);
  const upload = (url, formData) => request('POST', url, formData);

  return {
    setToken, getToken, clearToken,

    auth: {
      login:          (creds)  => post('/auth/login', creds),
      logout:         ()       => post('/auth/logout'),
      me:             ()       => get('/auth/me'),
      changePassword: (data)   => post('/auth/password', data),
    },

    geo: {
      levels:   ()                   => get('/geo/levels'),
      units:    (params = {})        => get('/geo/units', params),
      children: (id)                 => get(`/geo/units/${id}/children`),
      ancestors:(id)                 => get(`/geo/units/${id}/ancestors`),
    },

    config: () => get('/config'),
  };
})();
