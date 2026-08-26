<?php
/** @var array $yearInsights */
/** @var array $monthInsights */
/** @var array|null $currentMonth */
/** @var array|null $year */
/** @var array $conversations */
/** @var array|null $activeConversation */
/** @var array $messages */
/** @var bool $aiConfigured */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h3 class="mb-0"><i class="bi bi-stars"></i> AI Advisor</h3>
    <div class="text-muted">Insights computed from your own numbers, plus a chat you can ask financial questions in.</div>
  </div>
</div>

<div class="alert alert-secondary small">
  <i class="bi bi-info-circle"></i>
  This is informational analysis of your own tracked numbers, not licensed financial advice. For decisions with real
  legal, tax, or investment consequences, check with a licensed professional.
</div>

<?php if (!$year): ?>
  <div class="alert alert-warning">No financial year set up yet — start with <a href="<?= base_url('/wizard/start') ?>">Guided Entry</a> to get insights and ask the advisor questions.</div>
<?php else: ?>

<div class="section-title mt-0">Insights</div>
<div class="row g-3 mb-2">
  <div class="col-lg-6">
    <div class="text-muted small text-uppercase mb-2">This Year</div>
    <?php if (!$yearInsights): ?>
      <div class="text-muted small">Nothing notable to flag for the year yet.</div>
    <?php endif; ?>
    <?php foreach ($yearInsights as $insight): ?>
      <div class="insight-card <?= e($insight['severity']) ?>">
        <div class="title"><?= e($insight['title']) ?></div>
        <div class="small"><?= e($insight['message']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="col-lg-6">
    <div class="text-muted small text-uppercase mb-2"><?= $currentMonth ? e($currentMonth['label']) : 'This Month' ?></div>
    <?php if (!$monthInsights): ?>
      <div class="text-muted small">Nothing notable to flag this month.</div>
    <?php endif; ?>
    <?php foreach ($monthInsights as $insight): ?>
      <div class="insight-card <?= e($insight['severity']) ?>">
        <div class="title"><?= e($insight['title']) ?></div>
        <div class="small"><?= e($insight['message']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="section-title">Ask the Advisor</div>
<?php if (!$aiConfigured): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i>
    The chat isn't set up yet<?= \App\Core\Auth::isAdmin() ? ' — add a provider and API key in <a href="' . base_url('/admin#ai-advisor') . '">Settings &gt; AI Advisor</a> to enable it' : ' — ask an admin to add a provider and API key in Settings > AI Advisor' ?>.
    The insight cards above work regardless, since they don't need the API.
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-3">
    <div class="card p-2">
      <a href="<?= base_url('/advisor') ?>" class="btn btn-sm btn-primary w-100 mb-2"><i class="bi bi-plus-lg"></i> New conversation</a>
      <div class="list-group list-group-flush advisor-conv-list">
        <?php foreach ($conversations as $c): ?>
          <a href="<?= base_url('/advisor/' . $c['id']) ?>"
             class="list-group-item list-group-item-action small <?= ($activeConversation && (int) $activeConversation['id'] === (int) $c['id']) ? 'active' : '' ?>">
            <?= e($c['title']) ?>
          </a>
        <?php endforeach; ?>
        <?php if (!$conversations): ?>
          <div class="text-muted small p-2">No conversations yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-9">
    <div class="card">
      <div id="advisorLog" class="advisor-log">
        <?php foreach ($messages as $m): ?>
          <?php $isUser = $m['role'] === 'user'; ?>
          <div class="advisor-row <?= $isUser ? 'user' : 'bot' ?>">
            <?php if (!$isUser): ?><div class="advisor-avatar bot"><i class="bi bi-stars"></i></div><?php endif; ?>
            <div class="advisor-bubble-wrap">
              <div class="advisor-bubble <?= $isUser ? 'user' : 'bot' ?>" data-raw="<?= e($m['content']) ?>"></div>
              <div class="advisor-time"><?= e(date('g:i A', strtotime($m['created_at']))) ?></div>
            </div>
            <?php if ($isUser): ?><div class="advisor-avatar user"><i class="bi bi-person-fill"></i></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$messages): ?>
          <div class="advisor-empty text-center">
            <div class="advisor-empty-icon"><i class="bi bi-stars"></i></div>
            <div class="text-muted small mb-3">Ask me anything about your finances — I'll answer using your real numbers.</div>
            <div class="advisor-suggestions">
              <button type="button" class="advisor-suggestion">Can I afford to increase my loan payment?</button>
              <button type="button" class="advisor-suggestion">Where am I overspending this year?</button>
              <button type="button" class="advisor-suggestion">How's my savings rate looking?</button>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="border-top p-3">
        <form id="advisorForm" class="d-flex gap-2">
          <input id="advisorInput" class="form-control" type="text" placeholder="Ask a question about your finances..." <?= $aiConfigured ? '' : 'disabled' ?> autocomplete="off">
          <button class="btn btn-primary" type="submit" <?= $aiConfigured ? '' : 'disabled' ?>><i class="bi bi-send"></i></button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const askUrl = '<?= base_url('/advisor/ask') ?>';
const log = document.getElementById('advisorLog');
const form = document.getElementById('advisorForm');
const input = document.getElementById('advisorInput');
let conversationId = <?= $activeConversation ? (int) $activeConversation['id'] : "'new'" ?>;

/**
 * The AI's replies come back as markdown (**bold**, "- " bullets, "1. "
 * numbered lists, blank-line-separated paragraphs) since that's how the
 * providers naturally write. This turns that into real HTML for display,
 * escaping everything first so nothing in the text can inject markup.
 */
function escapeHtml(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function inlineFormat(s) {
  s = escapeHtml(s);
  s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  s = s.replace(/(^|[^*])\*([^*\s][^*]*?)\*(?!\*)/g, '$1<em>$2</em>');
  return s;
}

function renderMarkdown(text) {
  const lines = String(text).replace(/\r\n/g, '\n').split('\n');
  let html = '';
  let listType = null; // 'ul' | 'ol' | null
  let paragraph = [];

  function flushParagraph() {
    if (paragraph.length) {
      html += '<p>' + paragraph.join('<br>') + '</p>';
      paragraph = [];
    }
  }
  function closeList() {
    if (listType) {
      html += listType === 'ul' ? '</ul>' : '</ol>';
      listType = null;
    }
  }

  for (const rawLine of lines) {
    const line = rawLine.trim();

    if (line === '') {
      flushParagraph();
      closeList();
      continue;
    }

    const bullet = line.match(/^[-*]\s+(.*)/);
    const numbered = line.match(/^\d+[.)]\s+(.*)/);
    const heading = line.match(/^#{1,6}\s+(.*)/);

    if (bullet) {
      flushParagraph();
      if (listType !== 'ul') { closeList(); html += '<ul>'; listType = 'ul'; }
      html += '<li>' + inlineFormat(bullet[1]) + '</li>';
      continue;
    }
    if (numbered) {
      flushParagraph();
      if (listType !== 'ol') { closeList(); html += '<ol>'; listType = 'ol'; }
      html += '<li>' + inlineFormat(numbered[1]) + '</li>';
      continue;
    }

    closeList();
    if (heading) {
      flushParagraph();
      html += '<p><strong>' + inlineFormat(heading[1]) + '</strong></p>';
      continue;
    }
    paragraph.push(inlineFormat(line));
  }
  flushParagraph();
  closeList();
  return html || escapeHtml(text);
}

function nowLabel() {
  return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

/** Builds one message row (avatar + bubble + timestamp) and appends it. Returns the bubble element (what earlier code updates/removes). */
function addBubble(text, who) {
  const empty = log.querySelector('.advisor-empty');
  if (empty) empty.remove();

  const row = document.createElement('div');
  row.className = 'advisor-row ' + who;

  const avatar = document.createElement('div');
  avatar.className = 'advisor-avatar ' + who;
  avatar.innerHTML = who === 'bot' ? '<i class="bi bi-stars"></i>' : '<i class="bi bi-person-fill"></i>';

  const wrap = document.createElement('div');
  wrap.className = 'advisor-bubble-wrap';

  const bubble = document.createElement('div');
  bubble.className = 'advisor-bubble ' + who;
  if (who === 'bot') {
    bubble.innerHTML = renderMarkdown(text);
  } else {
    bubble.textContent = text;
  }

  const time = document.createElement('div');
  time.className = 'advisor-time';
  time.textContent = nowLabel();

  wrap.appendChild(bubble);
  wrap.appendChild(time);

  if (who === 'bot') {
    row.appendChild(avatar);
    row.appendChild(wrap);
  } else {
    row.appendChild(wrap);
    row.appendChild(avatar);
  }

  log.appendChild(row);
  log.scrollTop = log.scrollHeight;
  return bubble;
}

/** The three-dot "typing…" indicator shown while waiting on the API. Returns the whole row so it can be removed as one unit. */
function addTypingIndicator() {
  const row = document.createElement('div');
  row.className = 'advisor-row bot';
  row.innerHTML =
    '<div class="advisor-avatar bot"><i class="bi bi-stars"></i></div>' +
    '<div class="advisor-bubble-wrap"><div class="advisor-bubble bot advisor-typing"><span></span><span></span><span></span></div></div>';
  log.appendChild(row);
  log.scrollTop = log.scrollHeight;
  return row;
}

// Render the conversation history that was printed server-side (see the
// data-raw attributes above) using the same formatter as new replies, so
// past and present messages look consistent.
document.querySelectorAll('.advisor-bubble[data-raw]').forEach(function (el) {
  const raw = el.getAttribute('data-raw');
  if (el.classList.contains('bot')) {
    el.innerHTML = renderMarkdown(raw);
  } else {
    el.textContent = raw;
  }
  el.removeAttribute('data-raw');
});

function ask(value) {
  if (value === '') return;
  addBubble(value, 'user');
  const typingRow = addTypingIndicator();

  fetch(askUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'message=' + encodeURIComponent(value) + '&conversation_id=' + encodeURIComponent(conversationId)
  })
  .then(r => r.json())
  .then(data => {
    typingRow.remove();
    if (!data.ok) {
      addBubble('Error: ' + data.error, 'bot');
      return;
    }
    addBubble(data.reply, 'bot');
    if (conversationId === 'new') {
      conversationId = data.conversation_id;
      history.replaceState(null, '', '<?= base_url('/advisor') ?>/' + conversationId);
    }
  })
  .catch(() => {
    typingRow.remove();
    addBubble('Something went wrong reaching the advisor. Try again.', 'bot');
  });
}

form.addEventListener('submit', function (e) {
  e.preventDefault();
  const value = input.value.trim();
  input.value = '';
  ask(value);
});

// Suggestion chips shown on an empty conversation — click to ask instantly.
log.querySelectorAll('.advisor-suggestion').forEach(function (btn) {
  btn.addEventListener('click', function () {
    ask(btn.textContent.trim());
  });
});
</script>

<?php endif; ?>
