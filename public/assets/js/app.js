(() => {
  'use strict';
  const boot = JSON.parse(document.getElementById('boot').textContent);
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const state = { step: 1, verified: false, timer: null };

  class ApiError extends Error { constructor(m, field, status) { super(m); this.field = field; this.status = status; } }
  async function api(action, payload = {}) {
    let res, data;
    try {
      res = await fetch('api.php?action=' + action, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': boot.csrf }, body: JSON.stringify(payload) });
      data = await res.json();
    } catch { throw new ApiError('Could not reach the server. Check your connection and try again.'); }
    if (!res.ok || !data.ok) throw new ApiError(data.error || 'Something went wrong.', data.field, res.status);
    return data;
  }

  // ---- navigation ----
  function goTo(n) {
    state.step = n;
    $$('.step').forEach(el => { el.hidden = +el.dataset.step !== n; });
    $$('.stepper li').forEach(li => {
      const i = +li.dataset.dot;
      li.className = i < n || n === 6 ? 'done' : i === n ? 'active' : '';
      $('span', li).textContent = i < n || n === 6 ? '✓' : i;
    });
    const form = $(`.step[data-step="${n}"]`);
    $('h2', form).focus(); window.scrollTo({ top: 0 });
  }
  $$('[data-back]').forEach(b => b.addEventListener('click', () => goTo(state.step - 1)));

  // ---- errors / busy ----
  const clearErrors = form => {
    $('.alert', form)?.setAttribute('hidden', ''); $$('.err', form).forEach(p => p.textContent = '');
    $$('[aria-invalid]', form).forEach(i => i.removeAttribute('aria-invalid'));
  };
  function showError(form, e) {
    if (e.status === 409 && e.field == null) { alert(e.message); location.reload(); return; }
    const slot = e.field && $(`[data-err="${e.field}"]`, form);
    if (slot) { slot.textContent = e.message; const i = $('#' + e.field, form); if (i) { i.setAttribute('aria-invalid', 'true'); i.focus(); } else if (e.field === 'otp') $('#otp input').focus(); return; }
    const a = $('.alert', form); a.textContent = e.message; a.hidden = false;
  }
  function submit(form, handler) {
    form.addEventListener('submit', async ev => {
      ev.preventDefault(); clearErrors(form);
      const btn = $('button[type=submit]', form), label = btn.textContent;
      btn.disabled = true; btn.textContent = 'Please wait…';
      try { await handler(); } catch (e) { showError(form, e instanceof ApiError ? e : new ApiError('Unexpected error.')); }
      finally { btn.disabled = false; btn.textContent = label; }
    });
  }
  const clientCheck = (form, rules) => {
    for (const [id, test, msg] of rules) if (!test($('#' + id, form).value.trim())) throw new ApiError(msg, id);
  };

  // ---- step 1 ----
  submit($('[data-step="1"]'), async () => {
    const f = $('[data-step="1"]');
    clientCheck(f, [['full_name', v => v.trim().length >= 2, 'Enter your full name.'],
      ['email', v => /^[\w.+-]+@g\.batstate-u\.edu\.ph$/i.test(v), 'Use your BatStateU email: name@g.batstate-u.edu.ph.'],
      ['student_id', v => /^\d{2}-\d{5}$/.test(v), 'Use the YY-NNNNN format, for example 23-01041.']]);
    const v = id => $('#' + id, f).value.trim();
    const email = v('email').toLowerCase();
    if (email !== state.email) state.verified = false;
    state.email = email;
    await api('basic', { full_name: v('full_name'), email, student_id: v('student_id') });
    goTo(2);
  });

  // ---- step 2 ----
  const opt = (v, t) => Object.assign(document.createElement('option'), { value: v, textContent: t });
  const block = $('#block_id');
  block.append(opt('', 'Select your block'), ...boot.blocks.map(b => {
    const track = b.track ? ` · ${b.track === 'BA' ? 'Business Analytics' : 'Networking'}` : '';
    return opt(b.id, `${b.block_name} ${b.section} · Year ${b.year_level} · Semester ${b.semester}${track}`);
  }));

  submit($('[data-step="2"]'), async () => {
    const f = $('[data-step="2"]');
    clientCheck(f, [['block_id', v => v !== '', 'Select your block.']]);
    const r = await api('department', { block_id: +block.value });
    showVerification(r);
    goTo(3);
  });

  // ---- step 3 ----
  const otpInputs = $$('#otp input');
  const resendBtn = $('#resend'), timerEl = $('#timer'), dev = $('#devCode');
  function showVerification(r) {
    state.verified = r.verified;
    $('#otpBox').hidden = r.verified; $('#verifiedBox').hidden = !r.verified;
    if (r.verified) return;
    $('#otpEmail').textContent = r.email;
    otpInputs.forEach(i => i.value = ''); otpInputs[0].focus();
    setDev(r.dev_code); startTimer(r.cooldown || 0);
  }
  const setDev = c => { dev.hidden = !c; if (c) dev.textContent = `Development mode — OTP: ${c}. Disable EVS_DEV_MODE in production.`; };
  function startTimer(sec) {
    clearInterval(state.timer); resendBtn.disabled = sec > 0;
    const tick = () => { timerEl.textContent = sec > 0 ? `(00:${String(sec).padStart(2, '0')})` : ''; if (sec-- <= 0) { clearInterval(state.timer); resendBtn.disabled = false; } };
    tick(); if (sec >= 0) state.timer = setInterval(tick, 1000);
  }
  resendBtn.addEventListener('click', async () => {
    const f = $('[data-step="3"]'); clearErrors(f); resendBtn.disabled = true;
    try { const r = await api('resend'); setDev(r.dev_code); startTimer(r.cooldown); otpInputs.forEach(i => i.value = ''); otpInputs[0].focus(); }
    catch (e) { showError(f, e); startTimer(e.status === 429 ? +(e.message.match(/\d+/) || [0])[0] : 0); }
  });
  otpInputs.forEach((inp, i) => {
    inp.addEventListener('input', () => { inp.value = inp.value.replace(/\D/g, '').slice(-1); if (inp.value && otpInputs[i + 1]) otpInputs[i + 1].focus(); });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && otpInputs[i - 1]) otpInputs[i - 1].focus();
      if (e.key === 'ArrowLeft' && otpInputs[i - 1]) otpInputs[i - 1].focus();
      if (e.key === 'ArrowRight' && otpInputs[i + 1]) otpInputs[i + 1].focus();
    });
    inp.addEventListener('paste', e => {
      const d = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (!d) return; e.preventDefault();
      [...d].forEach((ch, k) => otpInputs[k].value = ch); otpInputs[Math.min(d.length, 5)].focus();
    });
  });
  submit($('[data-step="3"]'), async () => {
    if (state.verified) return goTo(4);
    const code = otpInputs.map(i => i.value).join('');
    if (code.length !== 6) throw new ApiError('Enter all six digits of the verification code.', 'otp');
    try { await api('verify', { code }); }
    catch (e) { otpInputs.forEach(i => i.value = ''); throw e; }
    state.verified = true; goTo(4);
  });

  // ---- step 4 ----
  $$('[data-toggle]').forEach(b => b.addEventListener('click', () => {
    const i = $('#' + b.dataset.toggle), show = i.type === 'password';
    i.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show'; b.setAttribute('aria-pressed', show);
  }));
  const dl = (el, obj) => el.replaceChildren(...Object.entries(obj).map(([k, v]) => {
    const row = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
    dt.textContent = k; dd.textContent = v; row.append(dt, dd); return row;
  }));
  submit($('[data-step="4"]'), async () => {
    const p = $('#password').value, c = $('#confirm').value;
    if (p.length < 8 || !/[A-Za-z]/.test(p) || !/\d/.test(p)) throw new ApiError('Password must be at least 8 characters and include letters and numbers.', 'password');
    if (p !== c) throw new ApiError('The passwords do not match.', 'confirm');
    const r = await api('password', { password: p, confirm: c });
    $('#password').value = $('#confirm').value = '';
    dl($('#review'), r.review); goTo(5);
  });

  // ---- step 5 ----
  submit($('[data-step="5"]'), async () => {
    const r = await api('complete');
    dl($('#success'), r.details); goTo(6);
  });
})();
