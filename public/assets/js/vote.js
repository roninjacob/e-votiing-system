(() => {
  'use strict';
  const csrf = document.querySelector('meta[name=csrf]').content;
  const $ = (s, r = document) => r.querySelector(s);
  const el = (t, c, x) => { const e = document.createElement(t); if (c) e.className = c; if (x != null) e.textContent = x; return e; };
  async function api(action, payload = {}) {
    let r, d;
    try {
      r = await fetch('api.php?action=' + action, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(payload) });
      d = await r.json();
    } catch { throw Object.assign(new Error('Could not reach the server.'), { status: 0 }); }
    if (!r.ok || !d.ok) throw Object.assign(new Error(d.error || 'Something went wrong.'), { status: r.status });
    return d;
  }
  const warn = m => { const a = $('.alert'); a.textContent = m; a.hidden = false; };

  if (document.body.dataset.page === 'login') {
    $('#loginForm').addEventListener('submit', async e => {
      e.preventDefault(); $('.alert').hidden = true;
      const b = $('button[type=submit]'); b.disabled = true;
      try { await api('login', { student_id: $('#student_id').value.trim(), password: $('#password').value }); location.href = 'vote.php'; }
      catch (x) { warn(x.message); b.disabled = false; }
    });
    return;
  }

  const root = $('#ballot'), dlg = $('#confirm');
  const scope = document.body.dataset.page === 'cics-vote' ? 'cics-' : '';
  let data;
  $('#logout').addEventListener('click', async () => { await api('logout').catch(() => {}); location.href = 'login.php'; });
  (async () => {
    try { data = await api(scope + 'ballot'); render(); }
    catch (x) { if (x.status === 401) location.href = 'login.php'; else { $('#title').textContent = 'CICS Student Council Election'; warn(x.message); } }
  })();

  const banner = (cls, text) => el('div', 'box ' + cls, text);
  function render() {
    const { election, positions, voted, name } = data;
    $('#who').textContent = name; $('#title').textContent = election.title;
    $('#window').textContent = [election.block, election.year, `Semester ${election.semester}`, election.track === 'BA' ? 'Business Analytics' : election.track === 'NT' ? 'Networking' : '', `Voting period: ${election.window}`].filter(Boolean).join(' · ');
    const canVote = election.status === 'open' && !voted;
    const status = voted ? 'Vote recorded' : election.status === 'open' ? 'Voting open' : election.status === 'upcoming' ? 'Upcoming' : election.status === 'unavailable' ? 'Not configured' : 'Voting closed';
    const stats = [['Election status', status], ['Election year', election.academic_year], ['Student year', election.year], ['Class / block', election.block], ['Semester', `Semester ${election.semester}`], ['Positions', positions.length], ['Candidates', positions.reduce((n, p) => n + p.candidates.length, 0)]];
    $('#dashboardStats').replaceChildren(...stats.map(([label, value]) => {
      const card = el('div', 'dashboard-stat'); card.append(el('span', 'dashboard-stat-label', label), el('strong', null, value)); return card;
    }));
    const content = [];
    if (voted) content.push(banner('ok', `✓ Your vote has been recorded. Receipt reference: ${voted}. Candidate profiles remain available below.`));
    else if (!canVote) content.push(banner('', election.status === 'upcoming' ? 'Voting has not started yet. Candidate profiles are available below.' : election.status === 'unavailable' ? 'This election is not configured for voting yet. Candidate profiles are available below.' : 'Voting is closed. Candidate profiles are available below.'));

    const form = el('form'); form.noValidate = true;
    positions.forEach(p => {
      const fs = el('fieldset', 'pos'); fs.append(el('legend', null, p.name));
      fs.append(el('p', 'muted', p.max_votes > 1 ? `Choose up to ${p.max_votes} candidates, or abstain.` : 'Choose one candidate or abstain.'));
      [...p.candidates, { id: 0, name: 'Abstain', abstain: true }].forEach(c => {
        const l = el('label', 'cand' + (c.abstain ? ' abstain' : '')), i = el('input');
        i.type = p.max_votes > 1 && !c.abstain ? 'checkbox' : 'radio'; i.name = 'p' + p.id; i.value = c.id; i.disabled = !canVote; l.append(i);
        if (canVote) i.addEventListener('change', () => {
          const choices = [...form.querySelectorAll(`input[name=p${p.id}]`)];
          if (i.checked && c.abstain) choices.forEach(input => { if (input !== i) input.checked = false; });
          if (i.checked && !c.abstain) choices.filter(input => input.value === '0').forEach(input => { input.checked = false; });
          const chosen = choices.filter(input => input.checked && input.value !== '0');
          if (p.max_votes > 1 && chosen.length > p.max_votes) { i.checked = false; warn(`Choose no more than ${p.max_votes} candidates for ${p.name}.`); }
        });
        if (c.abstain) {
          const info = el('span', 'candidate-info'); info.append(el('span', 'cn', c.name), el('span', 'candidate-meta', 'Skip this position')); l.append(info);
        } else {
          const initials = c.name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
          let photo;
          if (c.photo_path) {
            photo = el('img', 'candidate-photo'); photo.src = c.photo_path; photo.alt = `Photo of ${c.name}`; photo.loading = 'lazy';
            photo.onerror = () => { const fallback = el('span', 'candidate-photo photo-placeholder', `Photo not provided · ${initials}`); fallback.setAttribute('aria-label', `No photo provided for ${c.name}`); photo.replaceWith(fallback); };
          } else {
            photo = el('span', 'candidate-photo photo-placeholder', `Photo not provided · ${initials}`); photo.setAttribute('aria-label', `No photo provided for ${c.name}`);
          }
          const info = el('span', 'candidate-info'); info.append(el('span', 'candidate-role', p.name), el('span', 'cn', c.name));
          const meta = [c.party, election.year, election.block].filter(Boolean).join(' · '); if (meta) info.append(el('span', 'candidate-meta', meta));
          const credentials = el('span', 'candidate-detail'); credentials.append(el('strong', null, 'Credentials: '), document.createTextNode(c.credentials || 'Not provided yet.')); info.append(credentials);
          const platform = el('span', 'candidate-detail'); platform.append(el('strong', null, 'Platform: '), document.createTextNode(c.platform || 'Not provided yet.')); info.append(platform);
          l.append(photo, info);
        }
        fs.append(l);
      });
      form.append(fs);
    });

    if (canVote) {
      const go = el('button', 'btn', 'Review Ballot'); go.type = 'submit'; form.append(go);
      form.addEventListener('submit', e => {
        e.preventDefault(); $('.alert').hidden = true;
        const choices = {}, rows = [];
        for (const p of positions) {
          const selected = [...form.querySelectorAll(`input[name=p${p.id}]:checked`)].map(input => +input.value);
          if (!selected.length && p.is_required) return warn(`Select a candidate or Abstain for ${p.name}.`);
          if (selected.length > p.max_votes && !selected.includes(0)) return warn(`Choose no more than ${p.max_votes} candidates for ${p.name}.`);
          choices[p.id] = selected;
          const labels = selected.length === 0 || selected.includes(0) ? 'Abstain' : selected.map(id => p.candidates.find(c => +c.id === id)?.name).filter(Boolean).join(', ');
          rows.push([p.name, labels]);
        }
        $('#summary').replaceChildren(...rows.map(([k, v]) => { const r = el('div'); r.append(el('dt', null, k), el('dd', null, v)); return r; }));
        form.choices = choices; dlg.showModal();
      });
      $('#cancel').onclick = () => dlg.close();
      $('#cast').onclick = async e => {
        e.target.disabled = true;
        try { data.voted = (await api(scope + 'vote', { choices: form.choices })).reference; dlg.close(); render(); }
        catch (x) { dlg.close(); warn(x.message); if (x.status === 409) location.reload(); }
        finally { e.target.disabled = false; }
      };
    }
    content.push(form); root.replaceChildren(...content);
  }
})();
