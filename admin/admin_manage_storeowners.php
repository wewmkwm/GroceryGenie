<?php
// admin/admin_manage_storeowners.php

session_start();
$conn = new mysqli("localhost", "root", "", "grocerygenie");

// Handle approve/reject action
if (isset($_GET['action']) && isset($_GET['owner_id'])) {
    $action = $_GET['action'];
    $owner_id = intval($_GET['owner_id']); // security

    if ($action === 'approve') {
        $conn->query("UPDATE store_owners SET status='approved' WHERE owner_id='$owner_id'");
    } elseif ($action === 'reject') {
        $conn->query("UPDATE store_owners SET status='rejected' WHERE owner_id='$owner_id'");
    }

    header("Location: admin_manage_storeowners.php");
    exit();
}

// Fetch all store owners
$ownersResult = $conn->query("SELECT * FROM store_owners");
?>

<?php include("admin_header.php"); ?> 

  <h2 class="mb-4">Manage Store Owners</h2>
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Owner ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($ownersResult instanceof mysqli_result && $ownersResult->num_rows > 0): ?>
        <?php while ($row = $ownersResult->fetch_assoc()) { ?>
        <tr>
          <td><?php echo htmlspecialchars($row['owner_id']); ?></td>
          <td><?php echo htmlspecialchars($row['name']); ?></td>
          <td><?php echo htmlspecialchars($row['email']); ?></td>
          <td>
            <?php if ($row['status'] === 'approved') { ?>
              <span class="badge bg-success">Approved</span>
            <?php } elseif ($row['status'] === 'rejected') { ?>
              <span class="badge bg-danger">Rejected</span>
            <?php } else { ?>
              <span class="badge bg-warning text-dark">Pending</span>
            <?php } ?>
          </td>
          <td>
            <?php if ($row['status'] === 'pending') { ?>
              <a href="admin_manage_storeowners.php?action=approve&owner_id=<?php echo $row['owner_id']; ?>" 
                 class="btn btn-sm btn-success">Approve</a>
              <a href="admin_manage_storeowners.php?action=reject&owner_id=<?php echo $row['owner_id']; ?>" 
                 class="btn btn-sm btn-danger">Reject</a>
            <?php } else { ?>
              <em>No action needed</em>
            <?php } ?>
          </td>
        </tr>
        <?php } ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center text-muted">No store owners found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div> <!-- ✅ closes the container opened in admin_header.php -->

<?php include "admin_footer.php"; ?>
