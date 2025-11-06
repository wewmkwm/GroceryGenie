<?php
// customer/friends.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit(); }
$me = (int)$_SESSION['customer_id'];
require_once __DIR__ . '/../db_connect.php';

// Ensure table exists (no-op if present)
$conn->query("CREATE TABLE IF NOT EXISTS friends (
  id INT NOT NULL AUTO_INCREMENT,
  requester_id INT NOT NULL,
  addressee_id INT NOT NULL,
  status ENUM('pending','accepted','rejected','cancelled','blocked') NOT NULL DEFAULT 'pending',
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_direct (requester_id, addressee_id),
  KEY idx_addressee_status (addressee_id, status),
  KEY idx_requester_status (requester_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Incoming pending
$incoming = $conn->prepare('SELECT f.id, f.requester_id AS uid, c.name, c.email FROM friends f JOIN customers c ON c.customer_id=f.requester_id WHERE f.addressee_id=? AND f.status="pending" ORDER BY f.created_at DESC');
$incoming->bind_param('i',$me); $incoming->execute(); $inRes=$incoming->get_result(); $incoming_rows = $inRes->fetch_all(MYSQLI_ASSOC); $incoming->close();

// Outgoing pending
$outgoing = $conn->prepare('SELECT f.id, f.addressee_id AS uid, c.name, c.email FROM friends f JOIN customers c ON c.customer_id=f.addressee_id WHERE f.requester_id=? AND f.status="pending" ORDER BY f.created_at DESC');
$outgoing->bind_param('i',$me); $outgoing->execute(); $outRes=$outgoing->get_result(); $outgoing_rows = $outRes->fetch_all(MYSQLI_ASSOC); $outgoing->close();

// Friends (accepted)
$friends = $conn->prepare('SELECT CASE WHEN f.requester_id=? THEN f.addressee_id ELSE f.requester_id END AS uid, c.name, c.email, f.created_at
  FROM friends f JOIN customers c ON c.customer_id = CASE WHEN f.requester_id=? THEN f.addressee_id ELSE f.requester_id END
  WHERE (f.requester_id=? OR f.addressee_id=?) AND f.status="accepted" ORDER BY c.name');
$friends->bind_param('iiii',$me,$me,$me,$me); $friends->execute(); $frRes=$friends->get_result(); $friend_rows = $frRes->fetch_all(MYSQLI_ASSOC); $friends->close();

include 'customer_header.php';
?>
<div class="container mt-4 mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3><i class="fas fa-user-friends"></i> My Friends</h3>
    <a href="find_friends.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Find Friends</a>
  </div>

  <ul class="nav nav-tabs" id="frTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-friends" type="button">Friends</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-incoming" type="button">Requests</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-outgoing" type="button">Sent</button></li>
  </ul>
  <div class="tab-content border border-top-0 p-3 shadow-sm rounded-bottom">
    <div class="tab-pane fade show active" id="tab-friends">
      <?php if (!$friend_rows): ?>
        <p class="text-muted">No friends yet.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($friend_rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['name']); ?></td>
                  <td><?php echo htmlspecialchars($r['email']); ?></td>
                  <td>
                    <a class="btn btn-sm btn-primary" href="chat.php?user_id=<?php echo (int)$r['uid']; ?>"><i class="fas fa-comments"></i> Chat</a>
                    <button class="btn btn-sm btn-outline-danger act-unfriend" data-id="<?php echo (int)$r['uid']; ?>"><i class="fas fa-user-times"></i> Unfriend</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="tab-incoming">
      <?php if (!$incoming_rows): ?>
        <p class="text-muted">No incoming requests.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($incoming_rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['name']); ?></td>
                  <td><?php echo htmlspecialchars($r['email']); ?></td>
                  <td>
                    <div class="btn-group">
                      <button class="btn btn-sm btn-primary act-accept" data-id="<?php echo (int)$r['uid']; ?>"><i class="fas fa-check"></i> Accept</button>
                      <button class="btn btn-sm btn-outline-secondary act-reject" data-id="<?php echo (int)$r['uid']; ?>"><i class="fas fa-times"></i> Reject</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="tab-outgoing">
      <?php if (!$outgoing_rows): ?>
        <p class="text-muted">No sent requests.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($outgoing_rows as $r): ?>
                <tr>
                  <td><?php echo htmlspecialchars($r['name']); ?></td>
                  <td><?php echo htmlspecialchars($r['email']); ?></td>
                  <td>
                    <button class="btn btn-sm btn-warning act-cancel" data-id="<?php echo (int)$r['uid']; ?>"><i class="fas fa-ban"></i> Cancel Request</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  async function call(action, userId){
    const res = await fetch('../api/friend_action.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ action, user_id: userId })
    });
    return res.json();
  }
  document.addEventListener('click', async (e)=>{
    const b = e.target.closest('button'); if (!b) return;
    const id = b.dataset.id ? parseInt(b.dataset.id) : 0; if (!id) return;
    if (b.classList.contains('act-accept')) { const r=await call('accept',id); alert(r.message|| (r.ok?'Accepted':'Failed')); if(r.ok) location.reload(); }
    if (b.classList.contains('act-reject')) { const r=await call('reject',id); alert(r.message|| (r.ok?'Rejected':'Failed')); if(r.ok) location.reload(); }
    if (b.classList.contains('act-cancel')) { const r=await call('cancel',id); alert(r.message|| (r.ok?'Cancelled':'Failed')); if(r.ok) location.reload(); }
    if (b.classList.contains('act-unfriend')) { if(!confirm('Unfriend this user?'))return; const r=await call('unfriend',id); alert(r.message|| (r.ok?'Unfriended':'Failed')); if(r.ok) location.reload(); }
  });
</script>

<?php include 'customer_footer.php'; ?>
