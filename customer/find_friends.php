<?php
// customer/find_friends.php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['customer_id'])) { header('Location: customer_login.php'); exit(); }
$me = (int)$_SESSION['customer_id'];
require_once __DIR__ . '/../db_connect.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

include 'customer_header.php';
?>
<div class="container mt-4 mb-5">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="fas fa-user-friends"></i> Find Friends</h3>
    <!-- ✅ New Back Button -->
    <a href="friends.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left"></i> Back to Friends
    </a>
  </div>

  <form class="row g-2 mb-3" method="GET" action="">
    <div class="col-md-8">
      <input type="text" class="form-control" name="q" placeholder="Search by name or email" value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="col-md-4">
      <button class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
    </div>
  </form>

  <?php if ($q !== ''): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead>
            <tbody>
              <?php
              $like = '%' . $q . '%';
              $st = $conn->prepare('SELECT customer_id, name, email, profile_pic FROM customers WHERE (name LIKE ? OR email LIKE ?) AND customer_id <> ? ORDER BY name LIMIT 50');
              $st->bind_param('ssi', $like, $like, $me);
              $st->execute();
              $res = $st->get_result();
              if ($res->num_rows === 0) {
                echo '<tr><td colspan="3" class="text-muted">No results.</td></tr>';
              }
              while ($u = $res->fetch_assoc()) {
                $uid = (int)$u['customer_id'];
                // Check link state
                $lk = null;
                $chk = $conn->prepare('SELECT requester_id, addressee_id, status FROM friends WHERE (requester_id=? AND addressee_id=?) OR (requester_id=? AND addressee_id=?) LIMIT 1');
                $chk->bind_param('iiii', $me, $uid, $uid, $me);
                $chk->execute();
                $lk = $chk->get_result()->fetch_assoc();
                $chk->close();
                echo '<tr>';
                echo '<td>'.htmlspecialchars($u['name']).'</td>';
                echo '<td>'.htmlspecialchars($u['email']).'</td>';
                echo '<td>';
                if (!$lk) {
                  echo '<button class="btn btn-sm btn-success act-send" data-id="'.$uid.'"><i class="fas fa-user-plus"></i> Add Friend</button>';
                } else {
                  $req = (int)$lk['requester_id'];
                  $add = (int)$lk['addressee_id'];
                  $stt = $lk['status'];
                  if ($stt === 'accepted') {
                    echo '<button class="btn btn-sm btn-outline-danger act-unfriend" data-id="'.$uid.'"><i class="fas fa-user-times"></i> Unfriend</button>';
                  } elseif ($stt === 'pending') {
                    if ($add === $me) {
                      echo '<div class="btn-group">'
                         .'<button class="btn btn-sm btn-primary act-accept" data-id="'.$uid.'"><i class="fas fa-check"></i> Accept</button>'
                         .'<button class="btn btn-sm btn-outline-secondary act-reject" data-id="'.$uid.'"><i class="fas fa-times"></i> Reject</button>'
                         .'</div>';
                    } else {
                      echo '<div class="btn-group">'
                         .'<button class="btn btn-sm btn-warning act-cancel" data-id="'.$uid.'"><i class="fas fa-ban"></i> Cancel Request</button>'
                         .'</div>';
                    }
                  } else {
                    echo '<button class="btn btn-sm btn-success act-send" data-id="'.$uid.'"><i class="fas fa-user-plus"></i> Add Friend</button>';
                  }
                }
                echo '</td>';
                echo '</tr>';
              }
              $st->close();
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  async function call(action, userId){
    const res = await fetch('../api/friend_action.php', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ action, user_id: userId })
    });
    return res.json();
  }

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('button'); if (!btn) return;
    const id = btn.dataset.id ? parseInt(btn.dataset.id) : 0;
    if (!id) return;
    if (btn.classList.contains('act-send')) {
      const r = await call('send', id); alert(r.message || (r.ok? 'Sent':'Failed'));
      if (r.ok) location.reload();
    }
    if (btn.classList.contains('act-accept')) {
      const r = await call('accept', id); alert(r.message || (r.ok? 'Accepted':'Failed'));
      if (r.ok) location.reload();
    }
    if (btn.classList.contains('act-reject')) {
      const r = await call('reject', id); alert(r.message || (r.ok? 'Rejected':'Failed'));
      if (r.ok) location.reload();
    }
    if (btn.classList.contains('act-cancel')) {
      const r = await call('cancel', id); alert(r.message || (r.ok? 'Cancelled':'Failed'));
      if (r.ok) location.reload();
    }
    if (btn.classList.contains('act-unfriend')) {
      if (!confirm('Unfriend this user?')) return;
      const r = await call('unfriend', id); alert(r.message || (r.ok? 'Unfriended':'Failed'));
      if (r.ok) location.reload();
    }
  });
</script>

<?php include 'customer_footer.php'; ?>
