/**
 * PGA Open — Layout dynamique (sidebar, topbar, navigation).
 * Utilise PGA_CONFIG pour le branding et les labels.
 */
const PGA_LAYOUT = (() => {

  async function init(activePage, pageTitle) {
    // Charger la config tenant
    await PGA_CONFIG.load();

    // V\u00e9rifier l'authentification
    const token = PGA_API.getToken();
    if (!token) { window.location.href = 'login.html'; return null; }

    let user;
    try {
      user = await PGA_API.auth.me();
    } catch {
      window.location.href = 'login.html';
      return null;
    }

    // Injecter le branding dynamique
    const primaryColor = PGA_CONFIG.get('branding.primary_color', '#1B9E5A');
    document.documentElement.style.setProperty('--green', primaryColor);

    // G\u00e9n\u00e9rer la sidebar
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.innerHTML = buildSidebar(activePage, user);

    // G\u00e9n\u00e9rer la topbar
    const topbar = document.getElementById('topbar');
    if (topbar) topbar.innerHTML = buildTopbar(pageTitle);

    return user;
  }

  function buildSidebar(active, user) {
    const appName = PGA_CONFIG.get('branding.app_name', 'PGA Open');
    const country = PGA_CONFIG.get('country', '');
    const coatOfArms = PGA_CONFIG.get('branding.coat_of_arms_url', '');
    const agentType = PGA_CONFIG.get('agent_type', 'Agents');

    const navItems = [
      { id: 'dashboard', label: 'Tableau de bord', href: 'dashboard.html', icon: 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z' },
      { id: 'agents',    label: agentType,          href: 'agents.html',   icon: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z' },
      { id: 'statuts',   label: 'Fonctionnalit\u00e9',   href: 'statuts.html',  icon: 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z' },
      { id: 'payments',  label: 'Paiements',        href: 'payments.html', icon: 'M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z' },
      { id: 'reports',   label: 'Rapports',         href: 'reports.html',  icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z' },
    ];

    return `
      <div class="logo-area">
        <div class="logo-badge">
          ${coatOfArms ? `<img src="${coatOfArms}" alt="${appName}" class="logo-armoirie">` : `<div class="logo-mark">P</div>`}
          <div><div class="logo-text">${appName}</div><div class="logo-sub">${country}</div></div>
        </div>
      </div>
      <nav class="nav-section">
        <div class="nav-group">Navigation</div>
        ${navItems.map(n => `
          <a class="nav-item ${active === n.id ? 'active' : ''}" href="${n.href}">
            <svg viewBox="0 0 24 24"><path d="${n.icon}"/></svg>
            <span>${n.label}</span>
          </a>
        `).join('')}
      </nav>
      <div class="sidebar-footer">
        <div class="user-row" onclick="PGA_LAYOUT.showUserMenu()">
          <div class="user-av">${initials(user.nom)}</div>
          <div><div class="user-name">${user.nom}</div><div class="user-role">${PGA_CONFIG.get('roles.' + user.role, user.role)}</div></div>
        </div>
      </div>
    `;
  }

  function buildTopbar(title) {
    return `
      <div class="page-title">${title}</div>
      <div class="topbar-right">
        <div class="sync-pill"><div class="sync-dot"></div><span class="sync-txt" id="sync-txt">Synchronis\u00e9</span></div>
      </div>
    `;
  }

  function initials(name) {
    if (!name) return '?';
    return name.split(' ').filter(Boolean).slice(0, 2).map(p => p[0]).join('').toUpperCase();
  }

  function showUserMenu() {
    // TODO: dropdown d\u00e9connexion / changer MDP
  }

  return { init, showUserMenu };
})();
