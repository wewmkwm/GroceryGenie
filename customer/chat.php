<?php
// customer/chat.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit(); }
require_once __DIR__ . '/../db_connect.php';

$me = (int)$_SESSION['customer_id'];
$peer_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($peer_id <= 0) { include 'customer_header.php'; echo '<div class="container mt-4"><div class="alert alert-danger">Invalid chat user.</div></div>'; include 'customer_footer.php'; exit; }

// Ensure peer exists
$st = $conn->prepare('SELECT name, email, profile_pic FROM customers WHERE customer_id=? LIMIT 1');
$st->bind_param('i', $peer_id); $st->execute(); $res = $st->get_result();
if (!$res || $res->num_rows === 0) { include 'customer_header.php'; echo '<div class="container mt-4"><div class="alert alert-danger">User not found.</div></div>'; include 'customer_footer.php'; exit; }
$peer = $res->fetch_assoc(); $st->close();

// Check friendship
$st = $conn->prepare('SELECT 1 FROM friends WHERE ((requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?)) AND status="accepted" LIMIT 1');
$st->bind_param('iiii', $me, $peer_id, $peer_id, $me); $st->execute(); $st->store_result();
if ($st->num_rows === 0) { $st->close(); include 'customer_header.php'; echo '<div class="container mt-4"><div class="alert alert-warning">You can only chat with accepted friends.</div></div>'; include 'customer_footer.php'; exit; }
$st->close();

include 'customer_header.php';
?>
<style>
  body { background: linear-gradient(135deg,#ffe8d6 0%,#fff5ee 100%); }
  .chat-wrap { max-width: 860px; margin: 0 auto; }
  .chat-box { background:#fff; border:1px solid #f0d4bd; border-radius:20px; display:flex; flex-direction:column; height:72vh; overflow:hidden; box-shadow:0 20px 45px rgba(255,145,77,0.18); }
  .chat-header { padding:16px 22px; border-bottom:1px solid #f5d9c5; display:flex; align-items:center; gap:14px; background:#fff8f1; }
  .chat-header h5 { margin:0; font-weight:700; font-size:1.1rem; }
  .status-dot { margin-left:auto; width:11px; height:11px; border-radius:50%; background:#4cd964; box-shadow:0 0 0 6px rgba(76,217,100,0.2); }
  .chat-messages { flex:1; overflow-y:auto; padding:22px 26px; background:#fffdf9; display:flex; flex-direction:column; gap:14px; }
  .empty-state { text-align:center; color:#b8a79a; font-style:italic; margin-top:30px; }
  .bubble { max-width:70%; padding:12px 14px; border-radius:18px; box-shadow:0 10px 24px rgba(0,0,0,0.08); display:inline-flex; flex-direction:column; gap:6px; }
  .bubble-me { align-self:flex-end; background:linear-gradient(135deg,#ffb774 0%,#ff914d 100%); color:#fff; border-bottom-right-radius:6px; }
  .bubble-them { align-self:flex-start; background:#fff; border:1px solid #ffe1c9; border-bottom-left-radius:6px; }
  .bubble-text { white-space:pre-wrap; word-break:break-word; letter-spacing:.02em; }
  .bubble-meta { font-size:0.75rem; opacity:0.85; text-align:right; }
  .bubble-me .bubble-meta { color:#ffeede; }
  .bubble-them .bubble-meta { color:#a1784d; }
  .bubble-recipe { margin-top:6px; border:1px solid #ffd7b5; background:#fff8f1; padding:10px 12px; border-radius:14px; display:flex; flex-direction:column; gap:6px; }
  .bubble-recipe .recipe-title { font-weight:600; color:#a46632; }
  .bubble-recipe .recipe-note { font-size:0.85rem; color:#7c6044; }
  .bubble-recipe .btn { align-self:flex-start; border-radius:12px; padding:4px 12px; }
  .chat-input { display:flex; gap:10px; padding:16px; border-top:1px solid #f5d9c5; background:#fff; }
  .chat-input textarea { resize:none; height:54px; border-radius:14px; border:1px solid #f0d4bd; padding:12px 14px; transition:border-color .2s ease, box-shadow .2s ease; }
  .chat-input textarea:focus { outline:none; border-color:#ff914d; box-shadow:0 0 0 4px rgba(255,145,77,0.18); }
  .chat-input button { border-radius:14px; padding:0 26px; font-weight:600; }
  .avatar { width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #ffd5b3; }
</style>

<div class="container mt-4 chat-wrap">
  <div class="chat-box shadow-sm">
    <div class="chat-header">
      <?php
        $pic = !empty($peer['profile_pic']) ? 'uploads/' . htmlspecialchars(basename($peer['profile_pic'])) : '../assets/img/default_profile.png';
      ?>
      <img class="avatar" src="<?php echo $pic; ?>" alt="avatar">
      <div>
        <h5><?php echo htmlspecialchars($peer['name']); ?></h5>
        <div class="text-muted small"><?php echo htmlspecialchars($peer['email']); ?></div>
      </div>
      <a href="friends.php" class="btn btn-outline-secondary ms-auto">
        <i class="fas fa-arrow-left"></i> Back
      </a>
    </div>
    <div id="chatMessages" class="chat-messages">
      <div id="emptyState" class="empty-state">Start the conversation by sending a message.</div>
    </div>
    <div class="chat-input">
      <textarea id="msgInput" class="form-control" placeholder="Type a message..."></textarea>
      <button id="sendBtn" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
    </div>
  </div>
</div>

<script>
  const peerId = <?php echo (int)$peer_id; ?>;
  const ME_ID = <?php echo (int)$me; ?>;
  let lastId = 0;
  const list = document.getElementById('chatMessages');
  const emptyState = document.getElementById('emptyState');
  const input = document.getElementById('msgInput');
  const sendBtn = document.getElementById('sendBtn');

  function formatTime(ts) {
    if (!ts) return '';
    const parsed = new Date(ts.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return ts;
    return parsed.toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
  }

  function toggleEmptyState() {
    if (!emptyState) return;
    const hasMessages = list.querySelector('.bubble');
    emptyState.style.display = hasMessages ? 'none' : 'block';
  }

  function render(messages) {
    messages.forEach(m => {
      const isMe = (m.sender_id == ME_ID);
      const wrap = document.createElement('div');
      wrap.className = 'bubble ' + (isMe ? 'bubble-me' : 'bubble-them');

      const textContent = (m.content || '').trim();
      if (textContent !== '') {
        const txt = document.createElement('div');
        txt.className = 'bubble-text';
        txt.textContent = textContent;
        wrap.appendChild(txt);
      }

      if (m.recipe_id && parseInt(m.recipe_id) > 0) {
        const card = document.createElement('div');
        card.className = 'bubble-recipe';
        const title = document.createElement('div');
        title.className = 'recipe-title';
        title.textContent = m.recipe_name ? `Recipe: ${m.recipe_name}` : 'Shared recipe';
        card.appendChild(title);

        const btn = document.createElement('a');
        btn.className = 'btn btn-sm btn-outline-primary';
        btn.href = `recipe_details.php?recipe_id=${parseInt(m.recipe_id)}`;
        btn.innerHTML = '<i class="fas fa-utensils"></i> View Recipe';
        card.appendChild(btn);
        wrap.appendChild(card);
      }

      const meta = document.createElement('div');
      meta.className = 'bubble-meta';
      meta.textContent = formatTime(m.created_at ?? '');
      wrap.appendChild(meta);

      list.appendChild(wrap);
      lastId = Math.max(lastId, parseInt(m.message_id));
    });
    toggleEmptyState();
    list.scrollTop = list.scrollHeight;
  }

  async function fetchNew(){
    try {
      const r = await fetch('../api/chat_action.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'fetch', peer_id: peerId, after_id: lastId })
      });
      const j = await r.json();
      if (j.ok && j.messages && j.messages.length) {
        render(j.messages);
      } else {
        toggleEmptyState();
      }
    } catch(e) {
      console.error('Failed to fetch messages', e);
    }
  }

  async function send(){
    const text = (input.value || '').trim();
    if (!text) return;
    input.value = '';
    try {
      const r = await fetch('../api/chat_action.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'send', peer_id: peerId, content: text })
      });
      const j = await r.json();
      if (!j.ok) {
        alert(j.message || 'Failed to send');
      }
      await fetchNew();
    } catch(e) {
      alert('Network error');
    }
  }

  sendBtn.addEventListener('click', send);
  input.addEventListener('keydown', (e)=>{
    if(e.key==='Enter' && !e.shiftKey){
      e.preventDefault();
      send();
    }
  });

  (async function init(){
    toggleEmptyState();
    await fetchNew();
    setInterval(fetchNew, 3000);
  })();
</script>

<?php include 'customer_footer.php'; ?>
