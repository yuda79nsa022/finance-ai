<?php /** @var array|null $month */ ?>
<div class="wizard-shell">
  <h4 class="text-center mb-3"><?= $month ? 'Let\'s set up ' . e($month['label']) : 'Guided Entry' ?></h4>
  <div class="card">
    <div id="wizardLog" class="wizard-log"></div>
    <div class="border-top p-3">
      <div id="wizardInputArea"></div>
    </div>
  </div>
</div>

<script>
const answerUrl = '<?= base_url('/wizard/answer') ?>';
const log = document.getElementById('wizardLog');
const inputArea = document.getElementById('wizardInputArea');

function addBubble(text, who) {
  const div = document.createElement('div');
  div.className = 'wizard-bubble ' + who;
  div.textContent = text;
  log.appendChild(div);
  log.scrollTop = log.scrollHeight;
}

function renderQuestion(q) {
  addBubble(q.question, 'bot');
  inputArea.innerHTML = '';

  if (q.type === 'confirm') {
    const wrap = document.createElement('div');

    const btnRow = document.createElement('div');
    btnRow.className = 'd-flex gap-2 mb-2';
    ['Yes', 'No'].forEach(label => {
      const btn = document.createElement('button');
      btn.className = 'btn btn-outline-primary flex-fill';
      btn.textContent = label;
      btn.onclick = () => submitAnswer(label);
      btnRow.appendChild(btn);
    });
    wrap.appendChild(btnRow);

    if (q.quick_add) {
      const form = document.createElement('form');
      form.className = 'd-flex gap-2';
      const input = document.createElement('input');
      input.className = 'form-control form-control-sm';
      input.type = 'text';
      input.placeholder = 'Or type directly, e.g. "add 15 for groceries"';
      const submit = document.createElement('button');
      submit.className = 'btn btn-sm btn-secondary';
      submit.textContent = 'Send';
      form.appendChild(input);
      form.appendChild(submit);
      form.onsubmit = (e) => { e.preventDefault(); if (input.value.trim() !== '') submitAnswer(input.value); };
      wrap.appendChild(form);
    }

    inputArea.appendChild(wrap);
    return;
  }

  if (q.type === 'select') {
    const wrap = document.createElement('div');
    wrap.className = 'wizard-options';
    (q.options || []).forEach(opt => {
      const btn = document.createElement('button');
      btn.className = 'btn btn-outline-secondary btn-sm';
      btn.textContent = opt.name;
      btn.onclick = () => submitAnswer(String(opt.id));
      wrap.appendChild(btn);
    });
    if (q.optional) {
      const skip = document.createElement('button');
      skip.className = 'btn btn-link btn-sm';
      skip.textContent = 'Skip';
      skip.onclick = () => submitAnswer('');
      wrap.appendChild(skip);
    }
    inputArea.appendChild(wrap);
    return;
  }

  // text / number / date
  const form = document.createElement('form');
  form.className = 'd-flex gap-2';
  const input = document.createElement('input');
  input.className = 'form-control';
  input.type = q.type === 'number' ? 'number' : (q.type === 'date' ? 'date' : 'text');
  if (q.type === 'number') input.step = '0.001';
  input.required = !q.optional;
  input.autofocus = true;
  const submit = document.createElement('button');
  submit.className = 'btn btn-primary';
  submit.textContent = 'Send';
  form.appendChild(input);
  form.appendChild(submit);
  form.onsubmit = (e) => { e.preventDefault(); submitAnswer(input.value); };
  inputArea.appendChild(form);
  input.focus();
}

function submitAnswer(value) {
  if (value !== '') addBubble(value, 'user');
  inputArea.innerHTML = '<div class="text-muted small">Thinking…</div>';

  fetch(answerUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'answer=' + encodeURIComponent(value)
  })
  .then(r => r.json())
  .then(data => {
    if (data.done) {
      addBubble(data.message, 'bot');
      const s = data.summary;
      const box = document.createElement('div');
      box.className = 'alert alert-success mt-2';
      box.innerHTML = `Salary: <strong>${s.salary}</strong><br>Fixed: <strong>${s.fixed_total}</strong><br>Variable: <strong>${s.variable_total}</strong><br>Remaining: <strong>${s.remaining_to_spend}</strong>`;
      log.appendChild(box);
      inputArea.innerHTML = `<a href="${data.redirect}" class="btn btn-primary w-100">View Month</a>`;
      return;
    }
    renderQuestion(data);
  });
}

// Render the first question (already generated server-side — no round trip needed)
renderQuestion(<?= json_encode($firstQuestion) ?>);
</script>
