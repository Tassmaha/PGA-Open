/**
 * PGA Open — Configuration runtime charg\u00e9e depuis l'API tenant.
 *
 * Au chargement de chaque page, fetch /api/v1/config et cache en localStorage.
 * Fournit PGA_CONFIG.get('agent_type') => 'ASBC' (ou 'ASC', 'Relais', etc.)
 */
const PGA_CONFIG = (() => {
  const CACHE_KEY = 'pga_config';
  const CACHE_TTL = 3600000; // 1 heure
  let _config = null;

  function getCached() {
    try {
      const raw = localStorage.getItem(CACHE_KEY);
      if (!raw) return null;
      const { data, ts } = JSON.parse(raw);
      if (Date.now() - ts > CACHE_TTL) {
        localStorage.removeItem(CACHE_KEY);
        return null;
      }
      return data;
    } catch { return null; }
  }

  function setCache(data) {
    try {
      localStorage.setItem(CACHE_KEY, JSON.stringify({ data, ts: Date.now() }));
    } catch {}
  }

  /**
   * Charge la config depuis le cache ou l'API.
   * Doit \u00eatre appel\u00e9 au d\u00e9marrage de chaque page : await PGA_CONFIG.load();
   */
  async function load() {
    _config = getCached();
    if (_config) return _config;

    try {
      const baseUrl = window.PGA_API_BASE || '';
      const res = await fetch(`${baseUrl}/api/v1/config`);
      if (!res.ok) throw new Error(`Config HTTP ${res.status}`);
      _config = await res.json();
      setCache(_config);
    } catch (err) {
      console.warn('PGA_CONFIG: fallback defaults', err);
      _config = defaultConfig();
    }
    return _config;
  }

  /**
   * Acc\u00e8de \u00e0 une cl\u00e9 de config (dot notation).
   * Ex: PGA_CONFIG.get('agent_type') => 'ASBC'
   *     PGA_CONFIG.get('branding.primary_color') => '#1B9E5A'
   */
  function get(key, fallback = '') {
    if (!_config) return fallback;
    return key.split('.').reduce((obj, k) => obj?.[k], _config) ?? fallback;
  }

  /** Retourne la config compl\u00e8te. */
  function all() { return _config || defaultConfig(); }

  /** Invalide le cache (apr\u00e8s changement de tenant). */
  function clear() {
    localStorage.removeItem(CACHE_KEY);
    _config = null;
  }

  function defaultConfig() {
    return {
      country: 'PGA Open',
      agent_type: 'Agent',
      agent_type_full: 'Agent de Sant\u00e9 Communautaire',
      id_document: 'CNI',
      health_facility: 'Centre de Sant\u00e9',
      currency: 'FCFA',
      payment_provider: 'Mobile Money',
      branding: { app_name: 'PGA Open', primary_color: '#1B9E5A' },
      geo_levels: [],
      roles: {},
    };
  }

  return { load, get, all, clear };
})();
