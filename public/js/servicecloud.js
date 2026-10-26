/* ServiceCloud AI — comportamiento de la interfaz (JavaScript vanilla).
 * JavaScript de la interfaz: datos del servidor por fetch con token CSRF.
 */
(() => {
  'use strict';

  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const escapeHTML = (str = '') => String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (name) => `<i data-lucide="${name}"></i>`;
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const iconos = () => { if (window.lucide) lucide.createIcons(); };

  // ---------------------------------------------------------------- fetch
  /** fetch con CSRF y JSON. Devuelve {ok, status, data}. */
  async function scFetch(url, { method = 'GET', body = null, headers = {} } = {}) {
    const opts = { method, headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json', ...headers }, credentials: 'same-origin' };
    if (body instanceof FormData) opts.body = body;
    else if (body !== null) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
    const res = await fetch(url, opts);
    let data = null;
    const tipo = res.headers.get('content-type') || '';
    data = tipo.includes('application/json') ? await res.json() : await res.text();
    return { ok: res.ok, status: res.status, data };
  }

  // ---------------------------------------------------------------- toasts
  function scToast(title, text = '', tipo = 'ok') {
    const clase = { ok: '', error: 'toast-error', aviso: 'toast-warn' }[tipo] ?? '';
    const ic = { ok: 'check', error: 'circle-alert', aviso: 'info' }[tipo] ?? 'check';
    const el = document.createElement('div');
    el.className = `toast ${clase}`;
    el.innerHTML = `<div class="toast-icon">${icon(ic)}</div><div><b>${escapeHTML(title)}</b>${text ? `<p>${escapeHTML(text)}</p>` : ''}</div>`;
    $('#toastStack').appendChild(el); iconos();
    setTimeout(() => el.remove(), 5000);
  }
  $$('[data-auto-cerrar]').forEach(el => setTimeout(() => el.remove(), 5000));

  // ---------------------------------------------------------------- modal
  function openModal(title, body, footer = '') {
    $('#modalTitle').textContent = title;
    $('#modalBody').innerHTML = body;
    $('#modalFooter').innerHTML = footer;
    $('#modalBackdrop').classList.add('open');
    $$('#modalBody form').forEach(filtrarPorOrganizacion);
    iconos();
    $('#modalBody input:not([type=hidden]), #modalBody select, #modalBody textarea')?.focus();
  }
  function closeModal() { $('#modalBackdrop').classList.remove('open'); }

  /** Abre en el modal un contenido con secciones [data-modal-body] y [data-modal-footer]. */
  function openModalDesdeHTML(html, tituloPorDefecto = '') {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const raiz = tmp.querySelector('template') ? tmp.querySelector('template').content : tmp;
    const titulo = tmp.querySelector('[data-modal-titulo]')?.dataset.modalTitulo || tituloPorDefecto;
    const body = raiz.querySelector('[data-modal-body]')?.innerHTML ?? html;
    const footer = raiz.querySelector('[data-modal-footer]')?.innerHTML ?? '';
    openModal(titulo, body, footer);
  }

  async function abrirModalRemoto(url, titulo) {
    openModal(titulo || 'Cargando…', `<div class="empty"><div class="loader"><span></span><span></span><span></span></div><b style="margin-top:10px">Cargando…</b></div>`);
    const r = await scFetch(url, { headers: { Accept: 'text/html' } });
    if (!r.ok) { closeModal(); scToast('No se pudo abrir', r.status === 403 ? 'No tienes permiso para esta acción.' : 'Inténtalo nuevamente.', 'error'); return; }
    openModalDesdeHTML(r.data, titulo);
  }

  function confirmar(el) {
    const texto = el.dataset.confirmar;
    const accion = el.dataset.accion;
    const metodo = (el.dataset.metodo || 'DELETE').toUpperCase();
    const boton = el.dataset.botonConfirmar || 'Eliminar';
    const iconoBoton = el.dataset.iconoConfirmar || 'trash-2';
    const claseBoton = el.dataset.claseConfirmar || 'btn-danger';
    const titulo = el.dataset.tituloConfirmar || 'Confirmar eliminación';
    openModal(titulo,
      `<p class="ticket-desc">${escapeHTML(texto)}</p>
       <form id="formConfirmar" method="POST" action="${escapeHTML(accion)}">
         <input type="hidden" name="_token" value="${escapeHTML(csrf())}">
         ${metodo !== 'POST' ? `<input type="hidden" name="_method" value="${escapeHTML(metodo)}">` : ''}
       </form>`,
      `<button class="btn btn-secondary" type="button" data-cerrar-modal>Cancelar</button><button class="btn ${escapeHTML(claseBoton)}" type="submit" form="formConfirmar">${icon(iconoBoton)} ${escapeHTML(boton)}</button>`);
  }

  // ------------------------------------- selects dependientes de la organización
  // Para el Administrador SaaS: las opciones con data-org solo se muestran si coinciden
  // con la organización elegida (FK compuestas: todo debe ser de la misma organización).
  function filtrarPorOrganizacion(form) {
    const selectorOrg = form.querySelector('select[name="organizacion_id"]');
    if (!selectorOrg) return;
    const org = selectorOrg.value;
    $$('option[data-org]', form).forEach(o => {
      const visible = !!org && o.dataset.org === org;
      o.hidden = !visible; o.disabled = !visible;
      if (!visible && o.selected) o.selected = false;
    });
  }
  document.addEventListener('change', e => {
    if (e.target.matches('select[name="organizacion_id"]') && e.target.form) filtrarPorOrganizacion(e.target.form);
  });
  $$('form').forEach(filtrarPorOrganizacion);

  // ------------------------------------------------- formularios AJAX (modales)
  function limpiarErrores(form) {
    $$('.form-error', form).forEach(e => { e.textContent = ''; e.hidden = true; });
    $$('.is-invalid', form).forEach(e => e.classList.remove('is-invalid'));
  }
  function mostrarErrores(form, errores) {
    Object.entries(errores || {}).forEach(([campo, mensajes]) => {
      const nombre = campo.includes('.') ? campo.replace(/\.(\w+)/g, '[$1]') : campo;
      const base = campo.split('.')[0];
      const grupo = form.querySelector(`[data-campo="${CSS.escape(campo)}"]`) || form.querySelector(`[data-campo="${CSS.escape(nombre)}"]`) || form.querySelector(`[data-campo="${CSS.escape(base)}"]`);
      const control = form.querySelector(`[name="${CSS.escape(nombre)}"]`) || form.querySelector(`[name="${CSS.escape(base)}[]"]`) || form.querySelector(`[name="${CSS.escape(base)}"]`);
      control?.classList.add('is-invalid');
      const destino = grupo?.querySelector('.form-error');
      if (destino) { destino.textContent = mensajes[0]; destino.hidden = false; }
    });
  }
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-ajax]');
    if (!form) return;
    e.preventDefault();
    const botones = $$(`button[type=submit][form="${form.id}"], button[type=submit]`, document).filter(b => b.form === form);
    botones.forEach(b => { b.disabled = true; });
    limpiarErrores(form);
    try {
      const r = await scFetch(form.action, { method: 'POST', body: new FormData(form) });
      if (r.status === 422) {
        mostrarErrores(form, r.data.errors);
        scToast('Revisa los datos', r.data.message || 'Hay campos con errores.', 'error');
      } else if (r.ok) {
        if (r.data?.redirect) { window.location.href = r.data.redirect; return; }
        closeModal();
        if (r.data?.mensaje) scToast(r.data.mensaje, r.data.detalle || '');
        if (r.data?.recargar) window.location.reload();
      } else {
        scToast('No se pudo guardar', r.data?.message || (r.status === 403 ? 'No tienes permiso para esta acción.' : 'Inténtalo nuevamente.'), 'error');
      }
    } catch (err) {
      scToast('Error de conexión', 'No se pudo contactar al servidor.', 'error');
    } finally {
      botones.forEach(b => { b.disabled = false; });
    }
  });

  // ---------------------------------------------- filtros de listados (servidor)
  // Los selects envían el formulario al cambiar; la búsqueda, tras una pausa al escribir.
  $$('form[data-filtros]').forEach(form => {
    let t;
    $$('select', form).forEach(s => s.addEventListener('change', () => form.requestSubmit()));
    $$('input[type=search], input[data-buscar]', form).forEach(i => {
      i.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit(), 450); });
      if (i.value) { i.focus(); i.setSelectionRange(i.value.length, i.value.length); }
    });
    form.addEventListener('submit', () => { $$('input,select', form).forEach(c => { if (!c.value) c.disabled = true; }); });
  });

  // ---------------------------------------------------------------- gráficos
  const charts = [];
  /** Mismas opciones que initChart() del mockup (las opciones propias se mezclan igual: spread superficial). */
  function scInitChart(el, type, data, options = {}) {
    if (!el || !window.Chart) return null;
    const c = new Chart(el.getContext('2d'), {
      type, data,
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          // Tema claro: textos grises sobre blanco y tooltip oscuro
          legend: { labels: { color: '#4b5563', boxWidth: 9, boxHeight: 9, usePointStyle: true, font: { size: 10 } } },
          tooltip: { backgroundColor: '#111827', titleColor: '#fff', bodyColor: '#e5e7eb', borderColor: '#111827', borderWidth: 1, padding: 10 }
        },
        scales: type === 'doughnut' ? {} : {
          x: { ticks: { color: '#6b7280', font: { size: 9 } }, grid: { color: 'rgba(17,24,39,.06)' }, border: { color: 'rgba(17,24,39,.10)' } },
          y: { ticks: { color: '#6b7280', font: { size: 9 } }, grid: { color: 'rgba(17,24,39,.06)' }, border: { color: 'rgba(17,24,39,.10)' } }
        },
        ...options
      }
    });
    charts.push(c);
    return c;
  }
  function iniciarGraficos(root = document) {
    $$('canvas[data-chart]', root).forEach(el => {
      try {
        const g = JSON.parse(el.dataset.chart || '{}');
        if (g.type) scInitChart(el, g.type, g.data, g.options || {});
      } catch (e) { /* gráfico sin datos */ }
    });
  }

  // ---------------------------------------------------------------- búsqueda global
  let tBusqueda;
  async function handleGlobalSearch() {
    const input = $('#globalSearch'), box = $('#searchResults');
    const q = input.value.trim();
    if (q.length < 2) { box.classList.add('hidden'); return; }
    const r = await scFetch(`${input.dataset.url}?q=${encodeURIComponent(q)}`);
    if (!r.ok || input.value.trim() !== q) return;
    const resultados = r.data.resultados || [];
    box.innerHTML = resultados.length
      ? resultados.map(x => `<a class="result-item" href="${escapeHTML(x.url)}"><div class="result-icon">${icon(x.icono)}</div><div class="result-text"><b>${escapeHTML(x.titulo)}</b><span>${escapeHTML(x.tipo)} · ${escapeHTML(x.sub || '')}</span></div></a>`).join('')
      : `<div class="empty" style="grid-column:1/-1">${icon('search-x')}<b>Sin resultados</b><p>No encontramos coincidencias.</p></div>`;
    box.classList.remove('hidden');
    iconos();
  }

  // ---------------------------------------------------------------- sidebar
  function toggleSidebar() {
    if (window.innerWidth <= 1024) { document.body.classList.toggle('sidebar-open'); }
    else {
      document.body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('sc_sidebar', document.body.classList.contains('sidebar-collapsed') ? 'mini' : 'full'); } catch (e) {}
    }
    actualizarIconoMenu();
  }
  function actualizarIconoMenu() {
    const btn = $('#menuToggle');
    if (!btn) return;
    btn.innerHTML = icon(document.body.classList.contains('sidebar-collapsed') ? 'panel-left-open' : 'panel-left-close');
    iconos();
  }
  function closeDrawer() { document.body.classList.remove('sidebar-open'); }

  // ---------------------------------------------------------------- eventos
  document.addEventListener('click', e => {
    const remoto = e.target.closest('[data-modal-url]');
    if (remoto) { e.preventDefault(); abrirModalRemoto(remoto.dataset.modalUrl, remoto.dataset.modalTitulo); return; }
    const plantilla = e.target.closest('[data-modal-template]');
    if (plantilla) {
      e.preventDefault();
      const tpl = $(plantilla.dataset.modalTemplate);
      if (tpl) { const div = document.createElement('div'); div.appendChild(tpl.content.cloneNode(true)); openModalDesdeHTML(div.innerHTML, tpl.dataset.titulo || plantilla.dataset.modalTitulo || ''); }
      return;
    }
    const conf = e.target.closest('[data-confirmar]');
    if (conf) { e.preventDefault(); confirmar(conf); return; }
    if (e.target.closest('[data-cerrar-modal]')) { closeModal(); return; }
    const fila = e.target.closest('tr[data-href]');
    if (fila && !e.target.closest('a,button,form,input,select')) { window.location.href = fila.dataset.href; return; }

    if (!e.target.closest('.search-wrap')) $('#searchResults')?.classList.add('hidden');
    if (!e.target.closest('#notifBtn') && !e.target.closest('#notifDropdown')) $('#notifDropdown')?.classList.add('hidden');
    if (!e.target.closest('#profileBtn') && !e.target.closest('#profileDropdown')) $('#profileDropdown')?.classList.add('hidden');
  });

  $('#menuToggle')?.addEventListener('click', toggleSidebar);
  $('#mobileOverlay')?.addEventListener('click', closeDrawer);
  $('#globalSearch')?.addEventListener('input', () => { clearTimeout(tBusqueda); tBusqueda = setTimeout(handleGlobalSearch, 250); });
  $('#notifBtn')?.addEventListener('click', e => { e.stopPropagation(); $('#profileDropdown').classList.add('hidden'); $('#notifDropdown').classList.toggle('hidden'); });
  $('#profileBtn')?.addEventListener('click', e => { e.stopPropagation(); $('#notifDropdown').classList.add('hidden'); $('#profileDropdown').classList.toggle('hidden'); });
  $('#modalClose')?.addEventListener('click', closeModal);
  $('#modalBackdrop')?.addEventListener('click', e => { if (e.target === $('#modalBackdrop')) closeModal(); });
  window.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); $('#notifDropdown')?.classList.add('hidden'); $('#profileDropdown')?.classList.add('hidden'); $('#searchResults')?.classList.add('hidden'); closeDrawer(); }
  });

  // ---------------------------------------------------- notificaciones en vivo
  // La campana consulta /notificaciones/resumen cada 20 s (y al volver a la pestaña):
  // actualiza el contador, el desplegable y muestra un aviso por cada notificación nueva.
  const campana = $('#notifBtn');
  const tituloBase = document.title.replace(/^\(\d+\+?\)\s*/, '');
  function pintarContador(n) {
    $$('[data-notif-count]').forEach(el => { el.textContent = n > 99 ? '99+' : n; el.hidden = n === 0; });
    document.title = n > 0 ? `(${n}) ${tituloBase}` : tituloBase;
  }
  function avisoNotificacion(n) {
    const el = document.createElement('a');
    el.className = 'toast toast-notif';
    el.href = n.url;
    el.innerHTML = `<div class="toast-icon">${icon(n.icono || 'bell')}</div><div><b>${escapeHTML(n.titulo)}</b><p>${escapeHTML(n.mensaje)}</p><span class="toast-link">Ver detalle →</span></div>`;
    $('#toastStack').appendChild(el); iconos();
    setTimeout(() => el.remove(), 9000);
  }
  async function revisarNotificaciones() {
    if (!campana || document.hidden) return;
    try {
      const r = await scFetch(`${campana.dataset.url}?desde=${campana.dataset.ultima || 0}`);
      if (!r.ok || !r.data || typeof r.data !== 'object') return;
      pintarContador(r.data.no_leidas);
      const panel = $('#notifDropdown');
      if (panel.classList.contains('hidden')) { panel.innerHTML = r.data.html; iconos(); }
      r.data.nuevas.forEach(avisoNotificacion);
      if (r.data.nuevas.length) { campana.classList.add('sonando'); setTimeout(() => campana.classList.remove('sonando'), 1400); }
      campana.dataset.ultima = Math.max(Number(campana.dataset.ultima || 0), r.data.ultima || 0);
      // Si la novedad es del ticket que se está viendo, se recarga (salvo que el usuario esté escribiendo)
      const actual = $('[data-ticket-actual]')?.dataset.ticketActual;
      const escribiendo = $$('textarea').some(t => t.value.trim()) || $('#modalBackdrop')?.classList.contains('open');
      if (actual && !escribiendo && r.data.nuevas.some(nv => nv.ticket === actual)) {
        scToast('Este ticket tiene novedades', 'Actualizando la página…', 'aviso');
        setTimeout(() => window.location.reload(), 2500);
      }
    } catch (e) { /* sin conexión: se reintenta en el siguiente ciclo */ }
  }
  if (campana?.dataset.url) {
    pintarContador(parseInt($('[data-notif-count]', campana)?.textContent, 10) || 0);
    setInterval(revisarNotificaciones, 20000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) revisarNotificaciones(); });
  }

  // API para las vistas de cada módulo
  window.SC = { $, $$, escapeHTML, icon, iconos, scFetch, scToast, openModal, closeModal, openModalDesdeHTML, abrirModalRemoto, scInitChart, iniciarGraficos };

  actualizarIconoMenu();
  iniciarGraficos();
  iconos();
})();
