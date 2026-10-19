/* ============================================================
   CINEMATIC STORYBOARD PRO v18 — javascript.js
   ------------------------------------------------------------
   Interactions client complètes :
     - Gestion des clés API (localStorage)
     - Upload image + prompt texte (drag&drop + sélection)
     - Génération par SSE (analyses + file d'attente)
     - Polling global pour process les items
     - Rendu dynamique : style cards, storyboard panels, modify
     - Bouton "Vider la liste d'attente" + "▶ Continuer"
     - Galerie : sessions lazy + pagination + modifier + regenerate
     - "Modify" (img→txt→img) sur TOUTES les images
   ============================================================ */

/* ============================================================
   CONSTANTES & ÉTAT GLOBAL
   ============================================================ */
const apiKeyArea   = document.getElementById('apiKeyArea');
const configPanel  = document.getElementById('configPanel');
const genBtn       = document.getElementById('generateBtn');
const textPromptEl = document.getElementById('textPrompt');
const fileInput    = document.getElementById('fileInput');
const preview      = document.getElementById('preview');
const clearImgBtn  = document.getElementById('clearImgBtn');
const dropZone     = document.getElementById('dropZone');

const categoryLabels = {
    faithful: 'Fidèle',
    photographer: 'Photographe',
    director: 'Réalisateur',
    manga: 'Manga',
    painting: 'Peinture',
    artist: 'Artiste',
    digital: 'Numérique',
    poster: 'Affiche',
    scifi: 'Sci-Fi',
    storyboard: 'Storyboard',
    modify: 'Modify',
};

let currentTab   = 'generate';
let currentMode  = 'new_style';
let selectedFile = null;

let currentSessionId  = null;
let currentTextPrompt = '';

let analysisState = { char: 0, scene: 0, style: 0 };
let faithfulCount = 0, transformCount = 0;

let pollingActive        = false;
let pollingStopRequested = false;

const activeStoryboards   = new Map();  // sbSessionId → { card, panelEls, sbProgress, received, btn }
const pendingModifications = new Map(); // itemId      → { el }
const galleryState         = new Map(); // sessionId   → { offset, total, loaded, entries }

let toastTimer = null;

/* ============================================================
   UTILS
   ============================================================ */
function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
    })[c]);
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function setStatus(msg) { document.getElementById('statusText').innerText = msg; }
function setProgress(v) {
    const el = document.getElementById('progressFill');
    if (el) el.style.width = Math.max(0, Math.min(100, v)) + '%';
}
function toast(msg, type = 'info') {
    const el = document.getElementById('toast');
    el.className = 'toast show ' + type;
    el.innerText = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 3000);
}
function bindCopyButtons(root) {
    root.querySelectorAll('.copy-btn').forEach(btn => {
        if (btn.dataset.bound) return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', async e => {
            e.preventDefault();
            const text = btn.dataset.copy || '';
            try {
                await navigator.clipboard.writeText(text);
                const old = btn.innerText;
                btn.innerText = '✓ Copié';
                setTimeout(() => btn.innerText = old, 1500);
            } catch (err) {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); btn.innerText = '✓ Copié'; }
                catch (_) { toast('Copie impossible', 'error'); }
                document.body.removeChild(ta);
                setTimeout(() => btn.innerText = old, 1500);
            }
        });
    });
}
function newSessionId() {
    return new Date().toISOString().replace(/[-:.]/g, '').slice(0, 15)
        + '_' + Math.random().toString(36).slice(2, 8);
}

/* ============================================================
   CLÉS API
   ============================================================ */
if (localStorage.getItem('agnes_keys')) {
    apiKeyArea.value = localStorage.getItem('agnes_keys');
}
apiKeyArea.addEventListener('input', updateKeyStat);

function toggleConfig() {
    configPanel.classList.toggle('active');
    updateKeyStat();
}
function saveKeys() {
    localStorage.setItem('agnes_keys', apiKeyArea.value.trim());
    toast('Clés sauvegardées', 'success');
    toggleConfig();
}
function updateKeyStat() {
    const keys = apiKeyArea.value.trim().split('\n').filter(k => k.trim());
    const el = document.getElementById('keyStat');
    if (!keys.length) { el.innerText = ''; return; }
    let totalRpm = 0;
    keys.forEach(k => {
        const parts = k.split(':');
        totalRpm += parts.length > 1 ? (parseInt(parts[1]) || 10) : 10;
    });
    el.innerText = `${keys.length} clé(s), ${totalRpm} RPM cumulés`;
}

/* ============================================================
   TABS
   ============================================================ */
function switchTab(name) {
    currentTab = name;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.toggle('active', p.id === 'tab-' + name));
    if (name === 'gallery') loadGallerySessions();
}

/* ============================================================
   MODE SELECTOR
   ============================================================ */
document.querySelectorAll('.mode-selector label').forEach(label => {
    label.addEventListener('click', e => {
        e.stopPropagation();
        document.querySelectorAll('.mode-selector label').forEach(l => l.classList.remove('active'));
        label.classList.add('active');
        const input = label.querySelector('input[type="radio"]');
        input.checked = true;
        currentMode = input.value;
    });
});

/* ============================================================
   UPLOAD / DROP ZONE
   ============================================================ */
dropZone.addEventListener('click', e => {
    if (['BUTTON', 'SELECT', 'OPTION', 'INPUT', 'TEXTAREA', 'LABEL'].indexOf(e.target.tagName) === -1
        && !e.target.closest('.mode-selector')
        && !e.target.closest('.text-prompt-section')
        && !e.target.closest('.upload-actions')) {
        fileInput.click();
    }
});
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
});
fileInput.addEventListener('change', e => { if (e.target.files.length) handleFile(e.target.files[0]); });
textPromptEl.addEventListener('input', updateGenBtnState);

function handleFile(file) {
    if (!file.type.startsWith('image/')) { toast('Fichier image requis', 'error'); return; }
    selectedFile = file;
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
    clearImgBtn.style.display = 'inline-block';
    updateGenBtnState();
}
function clearImage() {
    selectedFile = null;
    preview.src = '';
    preview.style.display = 'none';
    clearImgBtn.style.display = 'none';
    fileInput.value = '';
    updateGenBtnState();
}
function updateGenBtnState() {
    genBtn.disabled = (!selectedFile && !textPromptEl.value.trim());
}

/* ============================================================
   SSE — PROCESS STREAM
   ============================================================ */
async function processSSE(formData, onEvent) {
    try {
        const resp = await fetch('?stream=1', { method: 'POST', body: formData });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            const chunks = buffer.split('\n\n');
            buffer = chunks.pop();
            for (const chunk of chunks) {
                if (!chunk.trim() || chunk.trim().startsWith(':')) continue;
                let event = 'message', dataStr = '';
                for (const line of chunk.split('\n')) {
                    if (line.startsWith('event: ')) event = line.slice(7).trim();
                    else if (line.startsWith('data: ')) dataStr += line.slice(6);
                }
                if (dataStr) {
                    try { onEvent(event, JSON.parse(dataStr)); }
                    catch (e) { console.warn('SSE parse', dataStr); }
                }
            }
        }
    } catch (err) {
        setStatus('Erreur SSE : ' + err.message);
    }
}

/* ============================================================
   GÉNÉRATION (bouton principal)
   ============================================================ */
genBtn.addEventListener('click', async () => {
    const keys = apiKeyArea.value.trim();
    if (!keys) return toast('Clé API requise', 'error');
    if (!selectedFile && !textPromptEl.value.trim()) return toast('Image ou prompt texte requis', 'error');

    resetGenerationUI();
    genBtn.disabled = true;
    setStatus('Initialisation...');
    setProgress(2);

    currentSessionId  = newSessionId();
    currentTextPrompt = textPromptEl.value.trim();

    const fd = new FormData();
    fd.append('action', 'generate');
    fd.append('keys', keys);
    fd.append('ratio', document.getElementById('ratio').value);
    if (selectedFile) fd.append('ref', selectedFile);
    fd.append('text_prompt', currentTextPrompt);
    fd.append('mode', currentMode);
    fd.append('session_id', currentSessionId);

    let queuedInfo = null;
    await processSSE(fd, (event, data) => {
        if (event === 'session_created' && data.session_id) currentSessionId = data.session_id;
        if (event === 'phase' && data.msg) setStatus(data.msg);
        if (event === 'styles_preset_list') renderStyleSkeletons(data.styles);
        if (event === 'analysis_progress') updateAnalysisProgress(data);
        if (event === 'analysis_complete') {
            renderAnalysis('charRow', data.character);
            renderAnalysis('sceneRow', data.scene);
            renderAnalysis('styleRow', data.style);
            setProgress(22);
        }
        if (event === 'queued') queuedInfo = data;
        if (event === 'error') setStatus('Erreur : ' + (data.msg || ''));
    });

    genBtn.disabled = false;

    if (queuedInfo && queuedInfo.items) {
        setStatus(`Traitement en cours (${queuedInfo.total} images prioritaires)...`);
        queuedInfo.items.forEach(it => {
            const card = document.querySelector(`.style-card[data-style-id="${it.style_id}"]`);
            if (card) {
                card.dataset.itemId = it.item_id || '';
                card.dataset.prompt = it.prompt || '';
                card.dataset.textPrompt = it.text_prompt || '';
            }
        });
        updateQueueBadge();
        startPolling();
    } else {
        setStatus('Erreur : pas de queue.');
    }
});

function resetGenerationUI() {
    document.getElementById('faithfulGallery').innerHTML = '';
    document.getElementById('transformGallery').innerHTML = '';
    document.getElementById('faithfulHeader').style.display = 'none';
    document.getElementById('transformHeader').style.display = 'none';
    document.getElementById('analysisSummary').classList.remove('active');
    document.getElementById('charRow').innerHTML = '';
    document.getElementById('sceneRow').innerHTML = '';
    document.getElementById('styleRow').innerHTML = '';
    document.getElementById('charProgress').innerText = '0/3';
    document.getElementById('sceneProgress').innerText = '0/3';
    document.getElementById('styleProgress').innerText = '0/1';
    document.getElementById('progressBar').classList.add('active');
    setProgress(2);
    analysisState = { char: 0, scene: 0, style: 0 };
    faithfulCount = 0;
    transformCount = 0;
}

function updateAnalysisProgress(data) {
    document.getElementById('analysisSummary').classList.add('active');
    if (data.key && data.key.startsWith('char_')) {
        analysisState.char++;
        document.getElementById('charProgress').innerText = analysisState.char + '/3';
    } else if (data.key && data.key.startsWith('style_')) {
        analysisState.style++;
        document.getElementById('styleProgress').innerText = analysisState.style + '/1';
    } else if (data.key === 'text') {
        document.getElementById('charProgress').innerText = '1/1';
        document.getElementById('sceneProgress').innerText = '1/1';
        document.getElementById('styleProgress').innerText = '1/1';
    } else {
        analysisState.scene++;
        document.getElementById('sceneProgress').innerText = analysisState.scene + '/3';
    }
}

function renderStyleSkeletons(styles) {
    const fg = document.getElementById('faithfulGallery');
    const tg = document.getElementById('transformGallery');
    let fSeen = 0, tSeen = 0;
    styles.forEach(s => {
        const isFaithful = s.faithful || s.category === 'faithful';
        const gal = isFaithful ? fg : tg;
        const card = document.createElement('div');
        card.className = 'style-card skeleton';
        card.dataset.styleId = s.id;
        const label = categoryLabels[s.category] || 'Style';
        card.innerHTML = `
            <div class="img-wrap loading"></div>
            <div class="info">
                <div class="badges"><span class="badge ${s.category}">${escapeHtml(label)}</span></div>
                <div class="name">${escapeHtml(s.name)}</div>
            </div>`;
        gal.appendChild(card);
        if (isFaithful) fSeen++; else tSeen++;
    });
    if (fSeen > 0) {
        document.getElementById('faithfulHeader').style.display = 'flex';
        document.getElementById('faithfulCount').innerText = `0 / ${fSeen}`;
    }
    if (tSeen > 0) {
        document.getElementById('transformHeader').style.display = 'flex';
        document.getElementById('transformCount').innerText = `0 / ${tSeen}`;
    }
}

function renderAnalysis(rowId, obj) {
    const row = document.getElementById(rowId);
    row.innerHTML = '';
    if (!obj || typeof obj !== 'object') return;
    const labels = {
        age_range: 'Âge', gender_presentation: 'Genre', ethnicity_primary: 'Ethnie',
        skin_tone: 'Peau', hair_color: 'Cheveux', eye_color: 'Yeux',
        face_shape: 'Visage', body_type: 'Silhouette',
        clothing_top: 'Haut', clothing_bottom: 'Bas', accessories: 'Accessoires',
        location_specific: 'Lieu', time_of_day: 'Heure', weather: 'Météo',
        lighting_type: 'Lumière', lighting_quality: 'Qualité', color_temperature: 'Température',
        composition: 'Composition', camera_angle: 'Angle', depth_of_field: 'Profondeur',
        emotional_tone: 'Ton', cinematic_genre_hint: 'Genre', color_palette: 'Palette',
        medium: 'Médium', technique: 'Technique', color_grading: 'Étalonnage',
        lighting_style: 'Style lumière', texture: 'Texture', line_quality: 'Trait',
        art_movement: 'Mouvement', reference_artists: 'Artistes réf',
        overall_style_description: 'Description style',
        additional_elements: 'Éléments ajoutés',
        user_addition: 'Instruction utilisateur',
    };
    Object.entries(obj).forEach(([k, v]) => {
        if (v === null || v === undefined || v === '') return;
        if (Array.isArray(v) && v.length === 0) return;
        const label = labels[k] || k.replace(/_/g, ' ');
        const val = Array.isArray(v) ? v.join(', ') : String(v);
        const div = document.createElement('div');
        div.className = 'item';
        div.innerHTML = `<span class="k">${escapeHtml(label)}</span><span class="v">${escapeHtml(val)}</span>`;
        row.appendChild(div);
    });
}

/* ============================================================
   POLLING GLOBAL
   ============================================================ */
async function startPolling() {
    if (pollingActive) return;
    pollingActive = true;
    pollingStopRequested = false;
    let backoff = 400;

    try {
        while (true) {
            if (pollingStopRequested) break;
            const data = await pollBatch();
            if (pollingStopRequested) break;
            if (!data) { await sleep(1500); continue; }
            if (data.locked) { await sleep(700); continue; }
            const results = data.results || [];
            backoff = results.length > 0 ? 400 : Math.min(backoff * 1.5, 3000);
            for (const res of results) routeResult(res);
            updateQueueBadge();
            updateStoryboardsProgress();
            if ((data.remaining || 0) === 0) {
                await sleep(1200);
                const peek = await fetch('?gallery=1').then(r => r.json()).catch(() => ({}));
                if ((peek.queue_count || 0) === 0) break;
            }
            await sleep(backoff);
        }
    } catch (err) {
        console.error('poll err', err);
    } finally {
        pollingActive = false;
        updateQueueBadge();
        document.getElementById('progressBar').classList.remove('active');
        if (!pollingStopRequested) setStatus('Terminé.');
    }
}

async function pollBatch() {
    const keys = apiKeyArea.value.trim();
    if (!keys) return null;
    const fd = new FormData();
    fd.append('keys', keys);
    try {
        const r = await fetch('?process=1', { method: 'POST', body: fd });
        return r.ok ? await r.json() : null;
    } catch (e) { return null; }
}

function routeResult(res) {
    const meta = res.meta || {};
    const kind = res.kind || meta._kind;
    if (kind === 'storyboard') renderStoryboardResult(res, meta);
    else if (kind === 'modify') renderModifyResult(res, meta);
    else renderStyleResult(res, meta);
}

/* ============================================================
   RENDU — STYLE CARD
   ============================================================ */
function renderStyleResult(res, meta) {
    const styleId = meta.style_id || '';
    const card = document.querySelector(`.style-card[data-style-id="${styleId}"]`);
    if (!card) return;
    card.classList.remove('skeleton', 'queued', 'failed');
    card.dataset.itemId = res.item_id || '';

    const prompt = meta.prompt || card.dataset.prompt || '';
    const textPrompt = meta.text_prompt || card.dataset.textPrompt || '';
    const imgUrl = res.server_url || res.url;

    if (res.status === 'ok') {
        const isFaithful = meta.faithful === true || meta.category === 'faithful';
        const label = categoryLabels[meta.category] || 'Style';
        const safeChar   = JSON.stringify(meta.character || {}).replace(/"/g, '&quot;');
        const safeScene  = JSON.stringify(meta.scene || {}).replace(/"/g, '&quot;');
        const safeStyle  = (meta.style_name || '').replace(/'/g, "\\'");
        const safeBody   = (meta.style_body || '').replace(/'/g, "\\'");
        const safeMode   = (meta.mode || 'new_style').replace(/'/g, "\\'");
        const safeText   = textPrompt.replace(/'/g, "\\'");
        const escPrompt  = escapeHtml(prompt);

        card.innerHTML = `
            <div class="img-wrap"><img src="${escapeHtml(imgUrl)}" alt="" loading="lazy"></div>
            <div class="info">
                <div class="badges"><span class="badge ${meta.category || 'faithful'}">${escapeHtml(label)}</span></div>
                <div class="name">${escapeHtml(meta.style_name || '')}</div>
                <details class="prompt-det">
                    <summary>📝 Prompt complet</summary>
                    <pre>${escPrompt}</pre>
                    <button class="copy-btn" data-copy="${escPrompt}">📋 Copier</button>
                </details>
                <details class="modify-det">
                    <summary>✏️ Modifier avec prompt</summary>
                    <textarea placeholder="Ex: ajoute un chapeau melon, ciel d'orage..."></textarea>
                    <button class="modify-btn" onclick="submitModify(this, '${escapeHtml(imgUrl)}')">✨ Générer la modification</button>
                    <div class="modified-results"></div>
                </details>
                <button class="sb-btn" onclick="launchStoryboard(this, ${safeChar}, ${safeScene}, '${safeStyle}', '${safeBody}', '${safeMode}', '${safeText}')">🎬 Storyboard (50)</button>
            </div>`;
        bindCopyButtons(card);
        if (isFaithful) {
            faithfulCount++;
            document.getElementById('faithfulCount').innerText = faithfulCount + ' / 10';
        } else {
            transformCount++;
            document.getElementById('transformCount').innerText = transformCount + ' / 80';
        }
    } else {
        card.classList.add('failed');
        const wrap = card.querySelector('.img-wrap');
        if (wrap) {
            wrap.classList.remove('loading');
            wrap.innerHTML = `<div class="placeholder">❌ Échec<br><small>${escapeHtml(res.error || '')}</small></div>`;
        }
        const info = card.querySelector('.info');
        if (info && !info.querySelector('.retry-btn')) {
            const btn = document.createElement('button');
            btn.className = 'retry-btn';
            btn.innerText = '🔄 Traiter cette image';
            btn.onclick = () => retryOne(res.item_id, card, 'style');
            info.appendChild(btn);
        }
    }
    const total = document.querySelectorAll('.style-card').length;
    const done = faithfulCount + transformCount;
    if (total > 0) setProgress(Math.min(95, 20 + Math.round(75 * done / total)));
}

/* ============================================================
   RENDU — STORYBOARD PANEL
   ============================================================ */
function renderStoryboardResult(res, meta) {
    const sbSessId = meta._session_id || meta.session_id || '';
    const idx = parseInt(meta.index, 10);
    if (isNaN(idx)) return;
    const sb = activeStoryboards.get(sbSessId);
    if (!sb) return;
    const el = sb.panelEls[idx];
    if (!el) return;

    el.classList.remove('queued', 'failed');
    el.dataset.itemId = res.item_id || '';
    const imgUrl = res.server_url || res.url;

    if (res.status === 'ok') {
        const wrap = el.querySelector('.img-wrap');
        wrap.classList.remove('loading');
        wrap.innerHTML = `<img src="${escapeHtml(imgUrl)}" alt="" loading="lazy">`;

        const pinfo = el.querySelector('.pinfo');
        if (pinfo && !pinfo.querySelector('.prompt-det')) {
            const prompt = meta.prompt || el.dataset.prompt || '';
            const det = document.createElement('details');
            det.className = 'prompt-det';
            det.innerHTML = `
                <summary>📝 Prompt</summary>
                <pre>${escapeHtml(prompt)}</pre>
                <button class="copy-btn" data-copy="${escapeHtml(prompt)}">📋 Copier</button>`;
            pinfo.appendChild(det);
            bindCopyButtons(det);
        }
        if (pinfo && !pinfo.querySelector('.modify-det')) {
            const modDet = document.createElement('details');
            modDet.className = 'modify-det';
            modDet.innerHTML = `
                <summary>✏️ Modifier</summary>
                <textarea placeholder="Décrivez la modification..."></textarea>
                <button class="modify-btn" onclick="submitModify(this, '${escapeHtml(imgUrl)}')">✨ Générer</button>
                <div class="modified-results"></div>`;
            pinfo.appendChild(modDet);
        }
        sb.received = (sb.received || 0) + 1;
    } else {
        el.classList.add('failed');
        const wrap = el.querySelector('.img-wrap');
        wrap.classList.remove('loading');
        wrap.innerHTML = `<div class="placeholder">❌ Échec<br><small>${escapeHtml(res.error || '')}</small></div>`;
        const pinfo = el.querySelector('.pinfo');
        if (pinfo && !pinfo.querySelector('.retry-btn')) {
            const btn = document.createElement('button');
            btn.className = 'retry-btn';
            btn.innerText = '🔄 Traiter';
            btn.onclick = () => retryOne(res.item_id, el, 'storyboard', sbSessId, idx);
            pinfo.appendChild(btn);
        }
    }
    updateStoryboardProgress(sb);
}

function updateStoryboardProgress(sb) {
    if (!sb || !sb.sbProgress) return;
    const total = Object.keys(sb.panelEls).length;
    const received = sb.received || 0;
    sb.sbProgress.innerText = `${received} / ${total}`;
}
function updateStoryboardsProgress() {
    for (const sb of activeStoryboards.values()) updateStoryboardProgress(sb);
}

/* ============================================================
   RENDU — MODIFY
   ============================================================ */
function renderModifyResult(res, meta) {
    const itemId = res.item_id || meta._item_id;
    const pending = pendingModifications.get(itemId);
    if (!pending) return;
    const el = pending.el;
    pendingModifications.delete(itemId);

    const prompt = meta.prompt || '';
    const userMod = meta.user_modification || '';
    const imgUrl = res.server_url || res.url;

    if (res.status === 'ok') {
        el.classList.remove('skeleton');
        el.innerHTML = `
            <div class="img-wrap"><img src="${escapeHtml(imgUrl)}" alt="" loading="lazy"></div>
            <div class="info">✨ ${escapeHtml(userMod)}</div>
            <details class="prompt-det">
                <summary>📝 Prompt</summary>
                <pre>${escapeHtml(prompt)}</pre>
                <button class="copy-btn" data-copy="${escapeHtml(prompt)}">📋 Copier</button>
            </details>`;
        bindCopyButtons(el);
        toast('Modification terminée', 'success');
    } else {
        el.classList.remove('skeleton');
        el.innerHTML = `<div class="placeholder">❌ Échec: ${escapeHtml(res.error || 'unknown')}</div>`;
    }
}

/* ============================================================
   MODIFY — soumission
   ============================================================ */
async function submitModify(btn, imageUrl) {
    const det = btn.closest('.modify-det');
    if (!det) return;
    const textarea = det.querySelector('textarea');
    const results  = det.querySelector('.modified-results');
    const prompt   = textarea.value.trim();
    if (!prompt) { toast('Décrivez la modification', 'error'); return; }

    btn.disabled = true;
    btn.innerText = '⏳ Analyse...';

    const loaderEl = document.createElement('div');
    loaderEl.className = 'modified-image skeleton';
    loaderEl.innerHTML = `<div style="width:24px;height:24px;border-radius:50%;border:3px solid #ddd;border-top-color:#666;animation:spin 0.8s linear infinite;"></div>`;
    results.appendChild(loaderEl);

    try {
        const fd = new FormData();
        fd.append('image_url', imageUrl);
        fd.append('prompt', prompt);
        fd.append('session_id', 'mod_' + Date.now());
        fd.append('ratio', document.getElementById('ratio').value);
        fd.append('keys', apiKeyArea.value.trim());

        const r = await fetch('?modify=1', { method: 'POST', body: fd });
        const data = await r.json();

        if (data.ok) {
            pendingModifications.set(data.item_id, { el: loaderEl });
            btn.innerText = '✨ Générer la modification';
            btn.disabled = false;
            textarea.value = '';
            startPolling();
        } else {
            loaderEl.remove();
            btn.innerText = '✨ Générer la modification';
            btn.disabled = false;
            toast('Erreur : ' + (data.error || 'inconnu'), 'error');
        }
    } catch (e) {
        loaderEl.remove();
        btn.innerText = '✨ Générer la modification';
        btn.disabled = false;
        toast('Erreur : ' + e.message, 'error');
    }
}

/* ============================================================
   RETRY ONE
   ============================================================ */
async function retryOne(itemId, targetEl, kind, sbSessId, panelIdx) {
    if (!itemId) { toast('Item ID manquant', 'error'); return; }
    const btn = targetEl.querySelector('.retry-btn');
    if (btn) { btn.disabled = true; btn.innerText = '⏳...'; }
    try {
        const fd = new FormData();
        fd.append('keys', apiKeyArea.value.trim());
        fd.append('item_id', itemId);
        const r = await fetch('?retry_one=1', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.ok && data.url) {
            if (kind === 'storyboard') {
                renderStoryboardResult({
                    status: 'ok',
                    url: data.url,
                    server_url: data.server_url,
                    item_id: itemId,
                }, data.meta || { _session_id: sbSessId, index: panelIdx });
            } else {
                renderStyleResult({
                    status: 'ok',
                    url: data.url,
                    server_url: data.server_url,
                    kind: 'style',
                    meta: data.meta,
                    item_id: itemId,
                }, data.meta || {});
            }
        } else {
            if (btn) { btn.disabled = false; btn.innerText = '🔄 Réessayer'; }
            toast('Échec : ' + (data.error || 'inconnu'), 'error');
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.innerText = '🔄 Réessayer'; }
        toast('Erreur : ' + e.message, 'error');
    }
}

/* ============================================================
   STORYBOARD — lancement
   ============================================================ */
async function launchStoryboard(btn, character, scene, styleName, styleBody, mode, userTextPrompt) {
    mode = mode || 'new_style';
    userTextPrompt = userTextPrompt || '';
    const card = btn.closest('.style-card');
    if (!card) return;

    if (card.classList.contains('expanded')) {
        card.classList.remove('expanded');
        const sbEl = card.querySelector('.sb-expanded');
        if (sbEl) sbEl.remove();
        btn.innerText = '🎬 Storyboard (50)';
        btn.disabled = false;
        for (const [sid, sb] of activeStoryboards.entries()) {
            if (sb.card === card) activeStoryboards.delete(sid);
        }
        return;
    }

    if (!confirm(`Générer 50 panneaux de storyboard pour "${styleName}" ?\nMode : ${mode === 'keep_style' ? 'garder le style' : 'nouveau style'}${userTextPrompt ? '\nInstruction : ' + userTextPrompt : ''}\n\n⚠️ Ceci remplace la file d'attente en cours.`)) return;

    const sbSessionId = 'sb_' + newSessionId();

    btn.disabled = true;
    btn.innerText = '⏳ Préparation...';
    card.classList.add('expanded');
    const existing = card.querySelector('.sb-expanded');
    if (existing) existing.remove();

    const sbEl = document.createElement('div');
    sbEl.className = 'sb-expanded';
    sbEl.innerHTML = `
        <div class="sb-header">
            <div>
                <div class="sb-title"><span class="dot"></span> Storyboard — ${escapeHtml(styleName)}</div>
                <div class="sb-progress">Préparation... 0 / 50</div>
            </div>
            <button class="sb-close">✕ Fermer</button>
        </div>
        <div class="sb-panels"></div>`;
    card.appendChild(sbEl);

    const panelsGrid = sbEl.querySelector('.sb-panels');
    const sbProgress = sbEl.querySelector('.sb-progress');
    const panelEls   = {};
    const sbState    = { card, panelEls, sbProgress, received: 0, btn };
    activeStoryboards.set(sbSessionId, sbState);

    sbEl.querySelector('.sb-close').addEventListener('click', () => {
        card.classList.remove('expanded');
        sbEl.remove();
        btn.innerText = '🎬 Storyboard (50)';
        btn.disabled = false;
        activeStoryboards.delete(sbSessionId);
    });

    function addPanel(p) {
        if (panelEls[p.index]) return;
        const el = document.createElement('div');
        el.className = 'sb-panel';
        el.dataset.index = p.index;
        el.dataset.prompt = p.prompt || '';
        el.innerHTML = `
            <div class="img-wrap loading"></div>
            <div class="pinfo">
                <div class="pnum">
                    <span class="src ${p.source}">${p.source === 'preset' ? 'PRESET' : 'IA'}</span>
                    #${String(p.index + 1).padStart(2, '0')}
                </div>
                <div class="ptitle">${escapeHtml(p.title || '')}</div>
                <div class="pdesc">${escapeHtml(p.description || '')}</div>
            </div>`;
        panelsGrid.appendChild(el);
        panelEls[p.index] = el;
    }

    const fd = new FormData();
    fd.append('action', 'storyboard');
    fd.append('keys', apiKeyArea.value.trim());
    fd.append('ratio', document.getElementById('ratio').value);
    fd.append('character_json', JSON.stringify(character));
    fd.append('scene_json', JSON.stringify(scene));
    fd.append('main_style', styleName);
    fd.append('main_prompt', styleBody);
    fd.append('mode', mode);
    fd.append('session_id', sbSessionId);
    fd.append('user_text_prompt', userTextPrompt);

    let queuedInfo = null;
    await processSSE(fd, (event, data) => {
        if (event === 'sb_script') {
            data.panels.forEach(addPanel);
            sbProgress.innerText = '0 / 50 (30 preset)';
        }
        if (event === 'sb_script_append') {
            data.panels.forEach(addPanel);
            sbProgress.innerText = '0 / 50 (50 prêts)';
        }
        if (event === 'queued') queuedInfo = data;
        if (event === 'phase' && data.msg) sbProgress.innerText = data.msg;
        if (event === 'error') sbProgress.innerText = 'Erreur : ' + data.msg;
    });

    btn.innerText = '✕ Fermer Storyboard';
    btn.disabled = false;

    if (queuedInfo && queuedInfo.total > 0) {
        queuedInfo.items.forEach(it => {
            const el = panelEls[it.index];
            if (el) {
                el.dataset.itemId = it.item_id || '';
                if (it.prompt) el.dataset.prompt = it.prompt;
            }
        });
        setStatus(`Storyboard : ${queuedInfo.total} panneaux en queue prioritaire...`);
        updateQueueBadge();
        startPolling();
    } else {
        sbProgress.innerText = 'Erreur : pas de queue.';
    }
}

/* ============================================================
   QUEUE — badge / clear / resume
   ============================================================ */
async function updateQueueBadge() {
    try {
        const r = await fetch('?gallery=1');
        if (!r.ok) return;
        const data = await r.json();
        const q = data.queue_count || 0;

        const badge = document.getElementById('queueBadge');
        if (q > 0) { badge.style.display = 'inline-block'; badge.innerText = q; }
        else badge.style.display = 'none';

        const banner = document.getElementById('queueBanner');
        if (q > 0 && !pollingActive) {
            banner.classList.add('active');
            document.getElementById('queueBannerText').innerText = `${q} image(s) en attente.`;
        } else {
            banner.classList.remove('active');
        }

        const gBtn = document.getElementById('clearQueueGalleryBtn');
        if (gBtn) gBtn.style.display = q > 0 ? 'inline-block' : 'none';
    } catch (e) {}
}

async function clearQueue() {
    if (!confirm("Vider complètement la liste d'attente ?\n\nToutes les générations en attente seront annulées.")) return;
    pollingStopRequested = true;
    try {
        const r = await fetch('?clear_queue=1');
        const data = await r.json();
        if (data.ok) {
            toast("Liste d'attente vidée", 'success');
            document.getElementById('queueBanner').classList.remove('active');
            document.getElementById('queueBadge').style.display = 'none';
            document.getElementById('progressBar').classList.remove('active');
            setStatus('File vidée.');
            document.querySelectorAll('.style-card.skeleton').forEach(c => c.classList.remove('skeleton'));
            document.querySelectorAll('.style-card .img-wrap.loading').forEach(w => {
                w.classList.remove('loading');
                w.innerHTML = '<div class="placeholder">🗑 Annulé</div>';
            });
            document.querySelectorAll('.sb-panel .img-wrap.loading').forEach(w => {
                w.classList.remove('loading');
                w.innerHTML = '<div class="placeholder">🗑 Annulé</div>';
            });
            updateQueueBadge();
        } else {
            toast('Erreur vidage', 'error');
        }
    } catch (e) {
        toast('Erreur : ' + e.message, 'error');
    }
}

async function clearQueueFromGallery() {
    await clearQueue();
    loadGallerySessions();
}

async function resumeQueue() {
    document.getElementById('queueBanner').classList.remove('active');
    setStatus('Reprise...');
    document.getElementById('progressBar').classList.add('active');
    await startPolling();
}

/* ============================================================
   GALERIE — chargement
   ============================================================ */
async function loadGallerySessions() {
    const container = document.getElementById('galleryContainer');
    container.innerHTML = '<div class="gallery-empty">Chargement...</div>';
    try {
        const r = await fetch('?gallery=1');
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const data = await r.json();
        updateQueueBadge();
        renderGallerySessions(data.sessions || [], data.queue_count || 0);
    } catch (err) {
        container.innerHTML = '<div class="gallery-empty">Erreur : ' + escapeHtml(err.message) + '</div>';
    }
}

function renderGallerySessions(sessions, queueCount) {
    const container = document.getElementById('galleryContainer');
    container.innerHTML = '';

    if (queueCount > 0) {
        const info = document.createElement('div');
        info.style.cssText = 'background:var(--queue-soft);color:var(--queue);padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:600;font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;';
        info.innerHTML = `<span>⏳ ${queueCount} image(s) en attente.</span>
            <div style="display:flex;gap:8px;">
                <button onclick="switchTab('generate');resumeQueue();" style="background:var(--queue);color:white;border:none;padding:6px 14px;border-radius:8px;font-weight:700;cursor:pointer;">▶ Traiter</button>
                <button onclick="clearQueueFromGallery();" style="background:var(--danger);color:white;border:none;padding:6px 14px;border-radius:8px;font-weight:700;cursor:pointer;">🗑 Vider</button>
            </div>`;
        container.appendChild(info);
    }

    if (!sessions.length) {
        const empty = document.createElement('div');
        empty.className = 'gallery-empty';
        empty.innerText = 'Aucune image encore.';
        container.appendChild(empty);
        return;
    }

    sessions.forEach(sess => {
        const acc = document.createElement('div');
        acc.className = 'sess-accordion';
        acc.dataset.sessionId = sess.session_id;

        const modeLabel = sess.mode === 'keep_style' ? 'Style source gardé'
            : sess.mode === 'new_style' ? 'Nouveau style total'
            : sess.mode === 'modify' ? 'Modification'
            : 'Inconnu';
        const date = sess.timestamp ? new Date(sess.timestamp).toLocaleString() : sess.session_id;
        const previewHtml = (sess.preview || []).map(u => `<img src="${escapeHtml(u)}" alt="" loading="lazy">`).join('');

        acc.innerHTML = `
            <div class="sess-head">
                <div>
                    <div class="sess-title">Session ${escapeHtml(sess.session_id)}</div>
                    <div class="sess-meta">${escapeHtml(date)} · ${sess.count} image(s)</div>
                </div>
                <div class="sess-right">
                    <div class="sess-preview">${previewHtml}</div>
                    <span class="sess-badge ${sess.mode || 'unknown'}">${modeLabel}</span>
                    <span class="sess-arrow">▶</span>
                </div>
            </div>
            <div class="sess-body">
                <div data-role="info-block"></div>
                <div class="sess-actions" data-role="actions"></div>
                <div class="gallery-grid" data-role="grid"></div>
                <button class="load-more" data-role="loadmore" style="display:none;">Voir plus</button>
            </div>`;
        container.appendChild(acc);

        renderSessionInfoBlock(acc, sess);

        acc.querySelector('.sess-head').addEventListener('click', () => toggleSession(acc));
        acc.querySelector('[data-role="loadmore"]').addEventListener('click', e => {
            e.stopPropagation();
            loadMoreSession(acc.dataset.sessionId);
        });

        const state = galleryState.get(sess.session_id);
        if (state && state.loaded) {
            acc.classList.add('open');
            renderSessionEntries(sess.session_id);
        }
    });
}

function renderSessionInfoBlock(accEl, sess) {
    const infoBlock = accEl.querySelector('[data-role="info-block"]');
    const actionsBlock = accEl.querySelector('[data-role="actions"]');
    const info = sess.info || {};
    const ref = sess.reference_url;
    const textPrompt = sess.text_prompt || info.text_prompt || '';

    let html = '';
    if (ref) {
        html += `<div class="sess-info-block">
            <div>
                <div class="sess-ref-label">Image de référence</div>
                <img class="sess-ref-thumb" src="${escapeHtml(ref)}" alt="" loading="lazy">
            </div>
            <div>
                <div class="sess-ref-label">Informations</div>
                <div style="font-size:11.5px;color:var(--text-soft);">
                    ${info.mode ? 'Mode: ' + escapeHtml(info.mode) : ''}<br>
                    ${sess.count} image(s) générée(s)
                </div>
                ${textPrompt ? `<div class="sess-text-prompt">💬 "${escapeHtml(textPrompt)}"</div>` : ''}
            </div>
        </div>`;
    } else {
        html += `<div class="sess-info-block no-ref">
            <div>
                <div class="sess-ref-label">Informations</div>
                <div style="font-size:11.5px;color:var(--text-soft);">
                    ${info.mode ? 'Mode: ' + escapeHtml(info.mode) : ''}<br>
                    ${sess.count} image(s) générée(s) · pas d'image de référence
                </div>
                ${textPrompt ? `<div class="sess-text-prompt">💬 "${escapeHtml(textPrompt)}"</div>` : ''}
            </div>
        </div>`;
    }
    infoBlock.innerHTML = html;

    if (info.character || info.scene) {
        const sub = document.createElement('details');
        sub.className = 'prompt-det';
        const charStr = JSON.stringify(info.character || {}, null, 0).slice(0, 500);
        const sceneStr = JSON.stringify(info.scene || {}, null, 0).slice(0, 500);
        sub.innerHTML = `<summary>📊 Analyse sauvegardée</summary>
            <pre>Personnage: ${escapeHtml(charStr)}\n\nScène: ${escapeHtml(sceneStr)}</pre>`;
        infoBlock.appendChild(sub);
    }

    const sidEsc = escapeHtml(accEl.dataset.sessionId).replace(/'/g, "\\'");
    actionsBlock.innerHTML = `
        <button class="primary" onclick="regenerateSeries('${sidEsc}')">🔁 Regénérer série similaire</button>
        <button onclick="loadSessionEntries('${sidEsc}', 0, true)">🔄 Rafraîchir cette session</button>`;
}

async function regenerateSeries(sessionId) {
    if (!confirm(`Regénérer 90 nouvelles images avec les mêmes paramètres que "${sessionId}" ?\n\n⚠️ Ceci remplace la file d'attente en cours.`)) return;
    const keys = apiKeyArea.value.trim();
    if (!keys) return toast('Clés API requises', 'error');
    toast('Regénération en cours...', 'info');

    const fd = new FormData();
    fd.append('keys', keys);
    fd.append('session_id', sessionId);
    fd.append('ratio', document.getElementById('ratio').value);

    try {
        const r = await fetch('?regenerate=1', { method: 'POST', body: fd });
        const data = await r.json();
        if (data.ok) {
            toast(`${data.total} images en queue.`, 'success');
            updateQueueBadge();
            startPolling();
        } else {
            toast('Erreur : ' + (data.error || 'inconnu'), 'error');
        }
    } catch (e) {
        toast('Erreur : ' + e.message, 'error');
    }
}

/* ============================================================
   GALERIE — sessions lazy / pagination
   ============================================================ */
function toggleSession(accEl) {
    const sessId = accEl.dataset.sessionId;
    const isOpen = accEl.classList.toggle('open');
    if (!isOpen) return;
    const state = galleryState.get(sessId);
    if (state && state.loaded) return;
    loadSessionEntries(sessId, 0);
}

async function loadSessionEntries(sessId, offset, force = false) {
    const accEl = document.querySelector(`.sess-accordion[data-session-id="${sessId}"]`);
    if (!accEl) return;
    const grid = accEl.querySelector('[data-role="grid"]');
    const loadMore = accEl.querySelector('[data-role="loadmore"]');
    const limit = 20;

    if (offset === 0) grid.innerHTML = '<div class="gallery-empty" style="padding:20px;">Chargement...</div>';
    loadMore.disabled = true;

    if (force) galleryState.delete(sessId);

    try {
        const r = await fetch(`?gallery=1&session_id=${encodeURIComponent(sessId)}&offset=${offset}&limit=${limit}`);
        if (!r.ok) throw new Error('HTTP ' + r.status);
        const data = await r.json();

        const state = galleryState.get(sessId) || { offset: 0, total: 0, loaded: false, entries: [] };
        if (offset === 0) { grid.innerHTML = ''; state.entries = []; }

        const entries = data.entries || [];
        entries.forEach(e => {
            grid.appendChild(makeGalleryItem(e));
            state.entries.push(e);
        });

        state.offset = offset + entries.length;
        state.total = data.total || 0;
        state.loaded = true;
        galleryState.set(sessId, state);

        if (data.has_more) {
            loadMore.style.display = 'block';
            loadMore.innerText = `Voir plus (${state.offset} / ${state.total})`;
            loadMore.disabled = false;
        } else {
            loadMore.style.display = 'none';
        }
    } catch (err) {
        if (offset === 0) grid.innerHTML = `<div class="gallery-empty">Erreur : ${escapeHtml(err.message)}</div>`;
    }
}

function makeGalleryItem(e) {
    const item = document.createElement('div');
    item.className = 'gallery-item';
    const thumbUrl = e.server_url || e.agnes_url || '';
    const cat = e.category || (e.kind === 'storyboard' ? 'storyboard' : '');
    const catLabel = categoryLabels[cat] || cat || '—';
    const name = e.style_name || e.title || (e.panel_index != null ? 'Panneau #' + (e.panel_index + 1) : 'Image');
    const prompt = e.prompt || '';
    const modifyNote = e.user_modification ? ' · ' + e.user_modification.slice(0, 50) : '';

    item.innerHTML = `
        <div class="img-wrap"><a href="${escapeHtml(thumbUrl)}" target="_blank"><img src="${escapeHtml(thumbUrl)}" alt="" loading="lazy"></a></div>
        <div class="info">
            <div class="g-name">${escapeHtml(name)}</div>
            <div class="g-meta">${escapeHtml(e.kind || 'image')}${escapeHtml(modifyNote)}</div>
            <span class="g-cat ${cat}">${escapeHtml(catLabel)}</span>
            <details class="prompt-det">
                <summary>📝 Prompt</summary>
                <pre>${escapeHtml(prompt)}</pre>
                <button class="copy-btn" data-copy="${escapeHtml(prompt)}">📋 Copier</button>
            </details>
            <details class="modify-det">
                <summary>✏️ Modifier</summary>
                <textarea placeholder="Décrivez la modification..."></textarea>
                <button class="modify-btn" onclick="submitModify(this, '${escapeHtml(thumbUrl)}')">✨ Générer</button>
                <div class="modified-results"></div>
            </details>
        </div>`;
    bindCopyButtons(item);
    return item;
}

async function loadMoreSession(sessId) {
    const state = galleryState.get(sessId);
    if (!state) return;
    await loadSessionEntries(sessId, state.offset);
}

function renderSessionEntries(sessId) {
    const accEl = document.querySelector(`.sess-accordion[data-session-id="${sessId}"]`);
    const state = galleryState.get(sessId);
    if (!accEl || !state || !state.loaded) return;
    const grid = accEl.querySelector('[data-role="grid"]');
    const loadMore = accEl.querySelector('[data-role="loadmore"]');
    grid.innerHTML = '';
    state.entries.forEach(e => grid.appendChild(makeGalleryItem(e)));
    if (state.offset < state.total) {
        loadMore.style.display = 'block';
        loadMore.innerText = `Voir plus (${state.offset} / ${state.total})`;
        loadMore.disabled = false;
    } else {
        loadMore.style.display = 'none';
    }
}

function refreshGallery() {
    galleryState.clear();
    loadGallerySessions();
}

/* ============================================================
   INIT
   ============================================================ */
updateQueueBadge();
updateKeyStat();
