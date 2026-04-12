/**
 * pga-ui.js — Composants UI réutilisables
 * Toast, loader, modal de confirmation, filtres géographiques cascadants
 */

const PGA_UI = (() => {

  // ── TOAST ────────────────────────────────────────────────────────────────
  // ── UTILITAIRE SANITIZATION XSS ──────────────────────────────────────────
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  let toastTimeout;
  function toast(message, type = 'info', duree = 4000) {
    const existing = document.getElementById('pga-toast');
    if (existing) existing.remove();
    clearTimeout(toastTimeout);

    const colors = {
      success: { bg: '#1a4731', border: '#2ECC7A', text: '#2ECC7A' },
      error:   { bg: '#4a1a1a', border: '#E8524A', text: '#E8524A' },
      warning: { bg: '#3a2a0a', border: '#F0A030', text: '#F0A030' },
      info:    { bg: '#1a2a3a', border: '#5B9CF6', text: '#5B9CF6' },
    };
    const c = colors[type] || colors.info;
    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };

    const el = document.createElement('div');
    el.id = 'pga-toast';
    el.style.cssText = `
      position:fixed; bottom:24px; right:24px; z-index:9999;
      background:${c.bg}; border:1px solid ${c.border};
      border-radius:10px; padding:12px 18px;
      display:flex; align-items:center; gap:10px;
      font-family:'Instrument Sans',sans-serif; font-size:13px;
      color:${c.text}; max-width:380px; box-shadow:0 4px 24px rgba(0,0,0,0.4);
      animation: slideIn .2s ease; cursor:pointer;
    `;
    el.innerHTML = `
      <span style="font-size:16px;flex-shrink:0">${icons[type]}</span>
      <span style="flex:1;color:#E8EDF5;font-weight:500">${escapeHtml(message)}</span>
      <span style="font-size:18px;opacity:.6;flex-shrink:0" onclick="this.closest('#pga-toast').remove()">×</span>
    `;
    document.body.appendChild(el);
    el.onclick = () => el.remove();

    toastTimeout = setTimeout(() => el?.remove(), duree);
  }

  // ── LOADER GLOBAL ────────────────────────────────────────────────────────
  function showLoader(message = 'Chargement…') {
    const existing = document.getElementById('pga-loader');
    if (existing) { existing.querySelector('span').textContent = message; return; }
    const el = document.createElement('div');
    el.id = 'pga-loader';
    el.style.cssText = `
      position:fixed; inset:0; z-index:9998;
      background:rgba(13,17,23,.7); backdrop-filter:blur(2px);
      display:flex; flex-direction:column; align-items:center; justify-content:center; gap:16px;
    `;
    el.innerHTML = `
      <div style="width:36px;height:36px;border:2px solid #2ECC7A33;border-top-color:#2ECC7A;
        border-radius:50%;animation:spin .7s linear infinite"></div>
      <span style="font-family:'Instrument Sans',sans-serif;color:#8A97AC;font-size:13px">${escapeHtml(message)}</span>
    `;
    document.body.appendChild(el);
  }

  function hideLoader() {
    document.getElementById('pga-loader')?.remove();
  }

  // ── SPINNER INLINE ───────────────────────────────────────────────────────
  function spinnerHtml(size = 16) {
    return `<span style="display:inline-block;width:${size}px;height:${size}px;
      border:2px solid #2ECC7A33;border-top-color:#2ECC7A;
      border-radius:50%;animation:spin .7s linear infinite"></span>`;
  }

  // ── MODAL DE CONFIRMATION ────────────────────────────────────────────────
  function confirmer(titre, message, opts = {}) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.style.cssText = `
        position:fixed; inset:0; z-index:9997;
        background:rgba(0,0,0,.6); display:flex; align-items:center; justify-content:center;
        padding:20px;
      `;
      const labelOk    = opts.labelOk    || 'Confirmer';
      const labelAnnul = opts.labelAnnul || 'Annuler';
      const danger     = opts.danger     || false;
      const btnColor   = danger ? '#E8524A' : '#2ECC7A';

      overlay.innerHTML = `
        <div style="background:#1E2530;border:1px solid rgba(255,255,255,.08);border-radius:14px;
          padding:28px 28px 24px;max-width:420px;width:100%;">
          <h3 style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;
            color:#E8EDF5;margin:0 0 10px;letter-spacing:-.02em">${escapeHtml(titre)}</h3>
          <p style="font-size:13px;color:#8A97AC;line-height:1.6;margin:0 0 24px">${escapeHtml(message)}</p>
          <div style="display:flex;gap:10px;justify-content:flex-end">
            <button id="modal-annul" style="padding:8px 18px;border-radius:8px;border:1px solid rgba(255,255,255,.1);
              background:transparent;color:#8A97AC;font-size:13px;font-weight:600;cursor:pointer">
              ${labelAnnul}
            </button>
            <button id="modal-ok" style="padding:8px 18px;border-radius:8px;border:none;
              background:${btnColor};color:${danger ? '#fff' : '#0D1117'};font-size:13px;font-weight:700;cursor:pointer">
              ${labelOk}
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
      overlay.querySelector('#modal-ok').onclick    = () => { overlay.remove(); resolve(true); };
      overlay.querySelector('#modal-annul').onclick = () => { overlay.remove(); resolve(false); };
      overlay.onclick = (e) => { if (e.target === overlay) { overlay.remove(); resolve(false); } };
    });
  }

  // ── AFFICHAGE DES ERREURS DE VALIDATION ─────────────────────────────────
  function afficherErreursForm(form, erreurs) {
    // Nettoyer les erreurs précédentes
    form.querySelectorAll('.err-msg').forEach(el => el.remove());
    form.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));

    if (!erreurs) return;

    Object.entries(erreurs).forEach(([champ, messages]) => {
      const input = form.querySelector(`[name="${champ}"]`);
      if (!input) return;
      input.style.borderColor = '#E8524A';
      const msg = document.createElement('span');
      msg.className = 'err-msg';
      msg.style.cssText = 'display:block;font-size:11px;color:#E8524A;margin-top:4px;';
      msg.textContent = Array.isArray(messages) ? messages[0] : messages;
      input.parentNode.insertBefore(msg, input.nextSibling);
    });
  }

  function nettoyerErreursForm(form) {
    form.querySelectorAll('.err-msg').forEach(el => el.remove());
    form.querySelectorAll('input,select,textarea').forEach(el =>
      el.style.borderColor = ''
    );
  }

  // ── FILTRES GÉOGRAPHIQUES CASCADANTS ────────────────────────────────────
  /**
   * Initialise un groupe de selects géographiques cascadants.
   * Chaque select se charge automatiquement quand le niveau supérieur change.
   *
   * Usage :
   *   PGA_UI.initFiltresGeo(container, {
   *     niveaux: ['region', 'province', 'district', 'formation', 'village'],
   *     onChange: (niveau, id) => { ... }
   *   });
   */
  async function initFiltresGeo(container, opts = {}) {
    const niveaux = opts.niveaux || ['region', 'province', 'district', 'formation'];
    const onChange = opts.onChange || (() => {});
    const labels = {
      region: 'Région', province: 'Province', district: 'District',
      formation: 'CSPS / Formation', village: 'Village',
    };
    // apiMap : niveau courant → fonction(parentId, parentNiveau)
    // Permet de passer le bon paramètre selon que l'on vient de 'region' ou 'province',
    // et de 'district' ou 'commune' pour les formations.
    const apiMap = {
      region:    (id, parentNiveau) => PGA_API.geo.regions(),
      province:  (id, parentNiveau) => PGA_API.geo.provinces(id),
      district:  (id, parentNiveau) => parentNiveau === 'region'
                    ? PGA_API.geo.districtsByRegion(id)
                    : PGA_API.geo.districts(id),
      commune:   (id, parentNiveau) => PGA_API.geo.communes(id),
      formation: (id, parentNiveau) => parentNiveau === 'district'
                    ? PGA_API.geo.formationsParDistrict(id)
                    : PGA_API.geo.formations(id),
      village:   (id, parentNiveau) => PGA_API.geo.villages(id),
    };

    // Créer les selects
    const selects = {};
    container.innerHTML = '';

    niveaux.forEach((niveau, index) => {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'display:flex;flex-direction:column;gap:5px;';

      const label = document.createElement('label');
      label.textContent = labels[niveau];
      label.style.cssText = 'font-size:10px;font-weight:600;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;';

      const select = document.createElement('select');
      select.name   = `${niveau}_id`;
      select.dataset.niveau = niveau;
      select.style.cssText = `
        background:#161B24; border:1px solid rgba(255,255,255,.07);
        border-radius:8px; padding:8px 12px; font-size:13px; color:#8A97AC;
        outline:none; cursor:pointer; width:100%;
        font-family:'Instrument Sans',sans-serif;
      `;
      select.innerHTML = `<option value="">— Tous (${labels[niveau]}) —</option>`;
      select.disabled = index > 0;

      wrap.append(label, select);
      container.appendChild(wrap);
      selects[niveau] = select;
    });

    // Charger les régions au démarrage
    await chargerNiveau('region', null, selects, niveaux, apiMap);

    // Gérer les changements en cascade
    niveaux.forEach((niveau, index) => {
      selects[niveau].addEventListener('change', async (e) => {
        const id = e.target.value;

        // Réinitialiser les niveaux suivants
        for (let i = index + 1; i < niveaux.length; i++) {
          const s = selects[niveaux[i]];
          s.innerHTML = `<option value="">— Tous (${labels[niveaux[i]]}) —</option>`;
          s.disabled = true;
        }

        // Charger le niveau suivant si une valeur est sélectionnée
        if (id && index + 1 < niveaux.length) {
          await chargerNiveau(niveaux[index + 1], id, selects, niveaux, apiMap, niveau);
        }

        onChange(niveau, id);
      });
    });

    // Retourner les valeurs courantes
    return {
      getValues: () => {
        const vals = {};
        niveaux.forEach(n => { if (selects[n].value) vals[`${n}_id`] = selects[n].value; });
        return vals;
      },
      reset: async () => {
        niveaux.forEach((n, i) => {
          selects[n].innerHTML = `<option value="">— Tous (${labels[n]}) —</option>`;
          if (i > 0) selects[n].disabled = true;
        });
        await chargerNiveau('region', null, selects, niveaux, apiMap);
      },
    };
  }

  async function chargerNiveau(niveau, parentId, selects, niveaux, apiMap, parentNiveau = null) {
    const select = selects[niveau];
    select.innerHTML = `<option value="">${PGA_UI.spinnerHtml(12)} Chargement…</option>`;
    select.disabled = true;

    try {
      const data = await apiMap[niveau](parentId, parentNiveau);
      const label = { region:'Région', province:'Province', district:'District',
                      formation:'CSPS', village:'Village' }[niveau];
      select.innerHTML = `<option value="">— Tous (${label}) —</option>`;
      (Array.isArray(data) ? data : data.data || []).forEach(item => {
        const opt = document.createElement('option');
        opt.value       = item.id;
        opt.textContent = item.nom;
        select.appendChild(opt);
      });
      select.disabled = false;
    } catch {
      select.innerHTML = `<option value="">Erreur de chargement</option>`;
      select.disabled = true;
    }
  }

  // ── PAGINATION ───────────────────────────────────────────────────────────
  function renderPagination(container, meta, onPage) {
    container.innerHTML = '';
    if (!meta || meta.last_page <= 1) return;

    const { current_page, last_page, from, to, total } = meta;
    container.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid rgba(255,255,255,.06)';

    const info = document.createElement('span');
    info.style.cssText = 'font-size:12px;color:#5A6578;font-family:"IBM Plex Mono",monospace';
    info.textContent = `${from}–${to} sur ${total}`;

    const btns = document.createElement('div');
    btns.style.cssText = 'display:flex;gap:6px';

    const btn = (label, page, disabled) => {
      const b = document.createElement('button');
      b.textContent = label;
      b.disabled = disabled;
      b.style.cssText = `
        padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;
        background:${disabled ? 'transparent' : 'rgba(255,255,255,.05)'};
        border:1px solid ${disabled ? 'rgba(255,255,255,.05)' : 'rgba(255,255,255,.1)'};
        color:${disabled ? '#3a3a3a' : '#8A97AC'};
        font-family:'IBM Plex Mono',monospace;
      `;
      if (!disabled) b.onclick = () => onPage(page);
      return b;
    };

    btns.append(
      btn('← Préc.', current_page - 1, current_page === 1),
      btn(`${current_page} / ${last_page}`, null, true),
      btn('Suiv. →', current_page + 1, current_page === last_page),
    );

    container.append(info, btns);
  }

  // ── FORMATAGE ─────────────────────────────────────────────────────────────
  const fmt = {
    pct:   (v) => (v ?? 0).toFixed(1) + '%',
    nb:    (v) => new Intl.NumberFormat('fr-FR').format(v ?? 0),
    date:  (s) => s || '—',
    montant: (v) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0 }).format(v ?? 0) + ' FCFA',
    periode: (s) => {
      if (!s) return '—';
      const [y, m] = s.split('-');
      return new Date(y, m - 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
    },
    cnibBadge: (statut) => {
      const c = { expire: '#E8524A', expiration_proche: '#F0A030', valide: '#2ECC7A', inconnu: '#5A6578' };
      const l = { expire: 'Expiré', expiration_proche: '< 3 mois', valide: 'Valide', inconnu: '?' };
      return `<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;
        font-family:'IBM Plex Mono',monospace;text-transform:uppercase;letter-spacing:.04em;
        background:${c[statut]}22;color:${c[statut]};border:1px solid ${c[statut]}44">
        ${l[statut] || statut}</span>`;
    },
    statutBadge: (statut) => {
      const map = {
        actif:                  { c: '#2ECC7A', l: 'Actif' },
        inactif:                { c: '#E8524A', l: 'Inactif' },
        rejete:                 { c: '#C05AB5', l: 'Rejeté' },
        en_attente_validation:  { c: '#F0A030', l: 'En attente' },
        fonctionnel:            { c: '#2ECC7A', l: 'Fonctionnel' },
        non_fonctionnel:        { c: '#E8524A', l: 'Non fonct.' },
        incomplet:              { c: '#5A6578', l: 'Incomplet' },
        reussi:                 { c: '#2ECC7A', l: 'Réussi' },
        echec:                  { c: '#E8524A', l: 'Échec' },
        introuvable:            { c: '#F0A030', l: 'Introuvable' },
      };
      const m = map[statut] || { c: '#5A6578', l: statut };
      return `<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;
        font-family:'IBM Plex Mono',monospace;text-transform:uppercase;letter-spacing:.04em;
        background:${m.c}22;color:${m.c};border:1px solid ${m.c}44">${m.l}</span>`;
    },
  };

  // ── MODAL CHANGEMENT MOT DE PASSE ─────────────────────────────────────────
  function modalChangerMdp() {
    // Supprimer un modal existant
    const ancien = document.getElementById('pga-modal-mdp');
    if (ancien) ancien.remove();

    const overlay = document.createElement('div');
    overlay.id = 'pga-modal-mdp';
    overlay.style.cssText = `
      position:fixed;inset:0;z-index:9998;display:flex;align-items:center;justify-content:center;
      background:rgba(0,0,0,.6);backdrop-filter:blur(4px);animation:slideIn .2s ease;`;

    overlay.innerHTML = `
      <div style="background:#161B24;border:1px solid rgba(255,255,255,.07);border-radius:12px;
        width:400px;max-width:92vw;padding:28px 32px;box-shadow:0 20px 60px rgba(0,0,0,.5);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <h3 style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#E8EDF5;margin:0;">
            Changer le mot de passe
          </h3>
          <button id="mdp-fermer" style="background:none;border:none;color:#5A6578;font-size:20px;cursor:pointer;padding:4px;">&times;</button>
        </div>
        <form id="mdp-form" autocomplete="off">
          <div style="margin-bottom:14px;">
            <label style="display:block;font-size:11px;font-weight:600;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">
              Mot de passe actuel
            </label>
            <input type="password" id="mdp-actuel" required autocomplete="current-password"
              style="width:100%;background:#1E2530;border:1px solid rgba(255,255,255,.07);border-radius:8px;
              padding:10px 14px;font-size:13px;color:#E8EDF5;font-family:inherit;outline:none;box-sizing:border-box;">
          </div>
          <div style="margin-bottom:14px;">
            <label style="display:block;font-size:11px;font-weight:600;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">
              Nouveau mot de passe <span style="font-weight:400;color:#5A6578;">(min. 8 caractères)</span>
            </label>
            <input type="password" id="mdp-nouveau" required minlength="8" autocomplete="new-password"
              style="width:100%;background:#1E2530;border:1px solid rgba(255,255,255,.07);border-radius:8px;
              padding:10px 14px;font-size:13px;color:#E8EDF5;font-family:inherit;outline:none;box-sizing:border-box;">
          </div>
          <div style="margin-bottom:20px;">
            <label style="display:block;font-size:11px;font-weight:600;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">
              Confirmer le nouveau mot de passe
            </label>
            <input type="password" id="mdp-confirm" required minlength="8" autocomplete="new-password"
              style="width:100%;background:#1E2530;border:1px solid rgba(255,255,255,.07);border-radius:8px;
              padding:10px 14px;font-size:13px;color:#E8EDF5;font-family:inherit;outline:none;box-sizing:border-box;">
          </div>
          <div id="mdp-erreur" style="display:none;padding:10px 14px;background:rgba(232,82,74,.1);
            border-radius:6px;font-size:12px;color:#E8524A;margin-bottom:14px;"></div>
          <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" id="mdp-annuler" style="padding:10px 18px;background:#1E2530;border:1px solid rgba(255,255,255,.07);
              border-radius:8px;font-size:13px;font-weight:500;color:#8A97AC;cursor:pointer;">
              Annuler
            </button>
            <button type="submit" id="mdp-btn-submit" style="padding:10px 22px;background:#2ECC7A;border:none;border-radius:8px;
              font-family:'Syne',sans-serif;font-size:13px;font-weight:700;color:#0D1117;cursor:pointer;">
              Modifier
            </button>
          </div>
        </form>
      </div>`;

    document.body.appendChild(overlay);

    const fermer = () => overlay.remove();
    document.getElementById('mdp-fermer').onclick = fermer;
    document.getElementById('mdp-annuler').onclick = fermer;
    overlay.addEventListener('click', e => { if (e.target === overlay) fermer(); });

    document.getElementById('mdp-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const actuel  = document.getElementById('mdp-actuel').value;
      const nouveau = document.getElementById('mdp-nouveau').value;
      const confirm = document.getElementById('mdp-confirm').value;
      const errDiv  = document.getElementById('mdp-erreur');
      const btn     = document.getElementById('mdp-btn-submit');

      errDiv.style.display = 'none';

      if (nouveau !== confirm) {
        errDiv.textContent = 'Les deux mots de passe ne correspondent pas.';
        errDiv.style.display = 'block';
        return;
      }
      if (nouveau.length < 8) {
        errDiv.textContent = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
        errDiv.style.display = 'block';
        return;
      }

      btn.disabled = true;
      btn.innerHTML = `<span style="display:inline-block;width:14px;height:14px;border:2px solid #0D111722;border-top-color:#0D1117;border-radius:50%;animation:spin .7s linear infinite"></span>`;

      try {
        await PGA_API.auth.changerMotDePasse(actuel, nouveau, confirm);
        fermer();
        toast('Mot de passe modifié. Vous allez être déconnecté.', 'success', 3000);
        setTimeout(() => {
          PGA_API.auth.deconnexion();
          window.location.href = 'login.html';
        }, 2000);
      } catch (err) {
        const msg = (err.erreurs ? Object.values(err.erreurs).flat()[0] : null) || err.message || 'Erreur lors du changement.';
        errDiv.textContent = msg;
        errDiv.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Modifier';
      }
    });
  }

  // Injecter les keyframes CSS
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes slideIn { from { transform: translateY(10px); opacity:0; } to { opacity:1; transform:none; } }
  `;
  document.head.appendChild(style);

  // ── MULTI-SELECT DROPDOWN ──────────────────────────────────────────────────
  /**
   * Crée un dropdown multi-select avec checkboxes.
   * Usage : const ms = PGA_UI.multiSelect(container, { placeholder, onChange });
   *         ms.setItems([{id, nom}]);  ms.getValues();  ms.clear();
   */
  function multiSelect(container, opts = {}) {
    const placeholder = opts.placeholder || 'Sélectionner…';
    const onChange = opts.onChange || (() => {});
    let items = [], selected = new Set(), open = false;

    const wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;min-width:180px;';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.style.cssText = `
      width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;
      background:#1E2530;border:1px solid rgba(255,255,255,.07);border-radius:8px;
      padding:8px 12px;font-size:12px;color:#8A97AC;cursor:pointer;
      font-family:'Instrument Sans',sans-serif;text-align:left;outline:none;
      transition:border-color .15s;`;
    btn.onmouseenter = () => btn.style.borderColor = 'rgba(255,255,255,.15)';
    btn.onmouseleave = () => { if (!open) btn.style.borderColor = 'rgba(255,255,255,.07)'; };

    const btnLabel = document.createElement('span');
    btnLabel.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;';
    btnLabel.textContent = placeholder;
    const arrow = document.createElement('span');
    arrow.textContent = '▾';
    arrow.style.cssText = 'font-size:10px;flex-shrink:0;transition:transform .15s;';
    btn.append(btnLabel, arrow);

    const dropdown = document.createElement('div');
    dropdown.style.cssText = `
      display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:100;
      background:#1E2530;border:1px solid rgba(255,255,255,.12);border-radius:8px;
      box-shadow:0 8px 32px rgba(0,0,0,.5);max-height:260px;overflow-y:auto;padding:4px 0;`;
    dropdown.className = 'pga-ms-dropdown';

    wrap.append(btn, dropdown);
    container.appendChild(wrap);

    function updateLabel() {
      if (selected.size === 0) btnLabel.textContent = placeholder;
      else if (selected.size <= 2) {
        btnLabel.textContent = items.filter(i => selected.has(i.id)).map(i => i.nom).join(', ');
      } else btnLabel.textContent = `${selected.size} sélectionnés`;
    }

    function renderDropdown() {
      dropdown.innerHTML = '';
      if (items.length === 0) {
        dropdown.innerHTML = '<div style="padding:10px 14px;font-size:11px;color:#5A6578;">Aucun élément</div>';
        return;
      }
      // Toggle all
      const allRow = document.createElement('label');
      allRow.style.cssText = `
        display:flex;align-items:center;gap:8px;padding:7px 14px;cursor:pointer;
        font-size:12px;color:#5B9CF6;font-weight:600;border-bottom:1px solid rgba(255,255,255,.06);margin-bottom:2px;`;
      const allCb = document.createElement('input');
      allCb.type = 'checkbox';
      allCb.checked = selected.size === items.length && items.length > 0;
      allCb.style.cssText = 'accent-color:#2ECC7A;width:14px;height:14px;cursor:pointer;';
      allCb.onchange = () => {
        if (allCb.checked) items.forEach(i => selected.add(i.id));
        else selected.clear();
        renderDropdown(); updateLabel(); onChange([...selected]);
      };
      allRow.append(allCb, document.createTextNode('Tout sélectionner'));
      dropdown.appendChild(allRow);

      items.forEach(item => {
        const row = document.createElement('label');
        row.style.cssText = `
          display:flex;align-items:center;gap:8px;padding:6px 14px;cursor:pointer;
          font-size:12px;color:#E8EDF5;transition:background .1s;`;
        row.onmouseenter = () => row.style.background = 'rgba(255,255,255,.04)';
        row.onmouseleave = () => row.style.background = 'transparent';
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = selected.has(item.id);
        cb.style.cssText = 'accent-color:#2ECC7A;width:14px;height:14px;cursor:pointer;';
        cb.onchange = () => {
          if (cb.checked) selected.add(item.id); else selected.delete(item.id);
          renderDropdown(); updateLabel(); onChange([...selected]);
        };
        row.append(cb, document.createTextNode(item.nom));
        dropdown.appendChild(row);
      });
    }

    btn.onclick = (e) => {
      e.stopPropagation();
      open = !open;
      dropdown.style.display = open ? 'block' : 'none';
      arrow.style.transform = open ? 'rotate(180deg)' : 'none';
      btn.style.borderColor = open ? 'rgba(46,204,122,.4)' : 'rgba(255,255,255,.07)';
    };

    document.addEventListener('click', (e) => {
      if (!wrap.contains(e.target) && open) {
        open = false;
        dropdown.style.display = 'none';
        arrow.style.transform = 'none';
        btn.style.borderColor = 'rgba(255,255,255,.07)';
      }
    });

    return {
      setItems(newItems) {
        items = (Array.isArray(newItems) ? newItems : newItems?.data || [])
          .map(i => ({ id: String(i.id), nom: i.nom }))
          .sort((a, b) => a.nom.localeCompare(b.nom));
        selected.clear();
        renderDropdown(); updateLabel();
      },
      getValues() { return [...selected]; },
      clear() { selected.clear(); renderDropdown(); updateLabel(); },
      getSelected() { return items.filter(i => selected.has(i.id)); },
    };
  }

  // ── FILTRES GÉO MULTI-SELECT (régions + districts multi, niveaux inférieurs single) ──
  async function initFiltresGeoMulti(container, opts = {}) {
    const niveaux    = opts.niveaux || ['region', 'district'];
    const onChange    = opts.onChange || (() => {});
    const labels     = { region: 'Régions', district: 'Districts', formation: 'CSPS', village: 'Village' };

    container.style.cssText = 'display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;';
    container.innerHTML = '';

    const ms = {};     // multi-selects (region, district)
    const singles = {}; // single selects (formation, village)
    const multiLevels = ['region', 'district'];

    niveaux.forEach(n => {
      const wrap = document.createElement('div');
      wrap.className = 'filter-group';
      const lbl = document.createElement('label');
      lbl.textContent = labels[n] || n;
      lbl.style.cssText = 'font-size:10px;font-weight:600;color:#5A6578;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;display:block;';
      const div = document.createElement('div');
      wrap.append(lbl, div);
      container.appendChild(wrap);

      if (multiLevels.includes(n)) {
        ms[n] = multiSelect(div, {
          placeholder: `Tous (${labels[n]})`,
          onChange: async (ids) => {
            await cascadeAfter(n, ids);
            onChange();
          },
        });
      } else {
        const sel = document.createElement('select');
        sel.style.cssText = 'background:#1E2530;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:8px 12px;font-size:12px;color:#8A97AC;outline:none;cursor:pointer;min-width:180px;font-family:inherit;';
        sel.innerHTML = `<option value="">— Tous (${labels[n]}) —</option>`;
        sel.disabled = true;
        sel.onchange = async () => { await cascadeAfter(n, sel.value ? [sel.value] : []); onChange(); };
        div.appendChild(sel);
        singles[n] = sel;
      }
    });

    async function cascadeAfter(level, ids) {
      const idx = niveaux.indexOf(level);
      // Reset everything after this level
      for (let i = idx + 1; i < niveaux.length; i++) {
        const n = niveaux[i];
        if (ms[n]) ms[n].setItems([]);
        if (singles[n]) { singles[n].innerHTML = `<option value="">— Tous (${labels[n]}) —</option>`; singles[n].disabled = true; }
      }
      if (ids.length === 0 || idx + 1 >= niveaux.length) return;

      const next = niveaux[idx + 1];
      try {
        let data = [];
        if (next === 'district') {
          const results = await Promise.all(ids.map(id => PGA_API.geo.districtsByRegion(id)));
          data = results.flat();
        } else if (next === 'formation') {
          const results = await Promise.all(ids.map(id => PGA_API.geo.formationsParDistrict(id)));
          data = results.flat();
        } else if (next === 'village') {
          const results = await Promise.all(ids.map(id => PGA_API.geo.villages(id)));
          data = results.flat();
        }
        if (ms[next]) ms[next].setItems(data);
        if (singles[next]) {
          singles[next].innerHTML = `<option value="">— Tous (${labels[next]}) —</option>`;
          (Array.isArray(data) ? data : data.data || []).forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.id; opt.textContent = item.nom;
            singles[next].appendChild(opt);
          });
          singles[next].disabled = false;
        }
      } catch {}
    }

    // Charger les régions
    try {
      const regions = await PGA_API.geo.regions();
      if (ms.region) ms.region.setItems(regions);
    } catch {}

    return {
      getValues() {
        const vals = {};
        const rIds = ms.region?.getValues() || [];
        const dIds = ms.district?.getValues() || [];
        if (dIds.length) vals['district_ids[]'] = dIds;
        else if (rIds.length) vals['region_ids[]'] = rIds;
        if (singles.formation?.value) vals.formation_id = singles.formation.value;
        if (singles.village?.value) vals.village_id = singles.village.value;
        return vals;
      },
      clear() {
        Object.values(ms).forEach(m => m.clear());
        Object.values(singles).forEach(s => {
          s.innerHTML = `<option value="">— Tous —</option>`; s.disabled = true;
        });
      },
    };
  }

  // ── EXPORT PDF (jsPDF + AutoTable) ──────────────────────────────────────────
  /**
   * Génère et télécharge un PDF tabulaire.
   * Requiert jsPDF + jspdf-autotable chargés via CDN.
   * @param {Array} data    - Tableau d'objets JSON
   * @param {Array} columns - [{header:'Nom', dataKey:'nom'}, ...]
   * @param {string} title  - Titre du rapport
   * @param {string} filename - Nom du fichier
   * @param {object} opts   - {orientation:'l'|'p', subtitle:''}
   */
  function downloadPdf(data, columns, title, filename, opts = {}) {
    if (typeof window.jspdf === 'undefined') {
      toast('Bibliothèque PDF non chargée', 'error');
      return;
    }
    const { jsPDF } = window.jspdf;
    const orientation = opts.orientation || (columns.length > 6 ? 'l' : 'p');
    const doc = new jsPDF({ orientation, unit: 'mm', format: 'a4' });
    const pageW = doc.internal.pageSize.getWidth();

    // ── En-tête ──
    doc.setFontSize(16);
    doc.setFont(undefined, 'bold');
    doc.text('PGA \u2014 Burkina Faso', pageW / 2, 14, { align: 'center' });
    doc.setFontSize(11);
    doc.setFont(undefined, 'normal');
    doc.text(title, pageW / 2, 22, { align: 'center' });
    if (opts.subtitle) {
      doc.setFontSize(9);
      doc.setTextColor(100);
      doc.text(opts.subtitle, pageW / 2, 28, { align: 'center' });
      doc.setTextColor(0);
    }
    const dateStr = new Date().toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' });
    doc.setFontSize(8);
    doc.setTextColor(120);
    doc.text(`Export\u00e9 le ${dateStr}  \u2014  ${fmt.nb(data.length)} enregistrement${data.length > 1 ? 's' : ''}`, pageW / 2, opts.subtitle ? 33 : 28, { align: 'center' });
    doc.setTextColor(0);

    // ── Tableau ──
    doc.autoTable({
      startY: opts.subtitle ? 37 : 32,
      head: [columns.map(c => c.header)],
      body: data.map(row => columns.map(c => row[c.dataKey] ?? '')),
      styles: { fontSize: 7, cellPadding: 2, overflow: 'linebreak', font: 'helvetica' },
      headStyles: { fillColor: [27, 158, 90], textColor: 255, fontStyle: 'bold', fontSize: 7.5 },
      alternateRowStyles: { fillColor: [245, 247, 250] },
      margin: { left: 8, right: 8 },
      didDrawPage: (d) => {
        // Pied de page
        doc.setFontSize(7);
        doc.setTextColor(150);
        doc.text(
          `PGA Burkina Faso  \u2014  Page ${doc.internal.getCurrentPageInfo().pageNumber}`,
          pageW / 2,
          doc.internal.pageSize.getHeight() - 6,
          { align: 'center' }
        );
      },
    });

    // Ouvrir le PDF dans un nouvel onglet (visible immédiatement)
    const blob = doc.output('blob');
    const url  = URL.createObjectURL(blob);
    window.open(url, '_blank');
    toast(`PDF g\u00e9n\u00e9r\u00e9 : ${filename} \u2014 ouvert dans un nouvel onglet`, 'success', 5000);
  }

  return { toast, showLoader, hideLoader, spinnerHtml, confirmer,
           afficherErreursForm, nettoyerErreursForm,
           initFiltresGeo, initFiltresGeoMulti, multiSelect, renderPagination, fmt, downloadPdf, modalChangerMdp, escapeHtml };

})();
