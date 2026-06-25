<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();
if (!isSuperAdmin() && !isManager()) { header("Location:../admin/dashboard.php"); exit; }

// Handle add/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $email  = !empty($_POST['email']) ? mysqli_real_escape_string($conn, trim($_POST['email'])) : null;
            $phone  = !empty($_POST['phone']) ? mysqli_real_escape_string($conn, trim($_POST['phone'])) : null;
            $reason = in_array(isset($_POST['reason']) ? $_POST['reason'] : '', ['unsubscribed','bounce','stop_sms','spam','manual']) ? $_POST['reason'] : 'manual';
            if ($email || $phone) {
                $ev = $email ? "'$email'" : 'NULL';
                $pv = $phone ? "'$phone'" : 'NULL';
                mysqli_query($conn, "INSERT IGNORE INTO do_not_contact (email, phone, reason, source, created_at) VALUES ($ev, $pv, '$reason', 'manual', NOW())");
                auditLog($conn, 'add_dnc', 'do_not_contact', 0, "email=$email phone=$phone");
            }
        } elseif ($_POST['action'] === 'delete') {
            $did = (int)$_POST['dnc_id'];
            mysqli_query($conn, "DELETE FROM do_not_contact WHERE id='$did'");
            auditLog($conn, 'remove_dnc', 'do_not_contact', $did);
        }
    }
    header("Location:list.php");
    exit;
}

$page    = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 50; $offset = ($page-1)*$perPage;
$total   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM do_not_contact"))['n'];
$totalPages = max(1,(int)ceil($total/$perPage));
$rows = []; $r = mysqli_query($conn,"SELECT * FROM do_not_contact ORDER BY id DESC LIMIT $perPage OFFSET $offset");
while($row=mysqli_fetch_assoc($r)) $rows[]=$row;

include("../layout/header.php");
include("../layout/sidebar.php");
?>
<section class="content-header"><div class="container-fluid"><h1><i class="fas fa-ban mr-2 text-danger"></i>Do-Not-Contact List</h1><p class="text-muted">Emails/phones on this list are automatically skipped during campaign sends.</p></div></section>
<section class="content"><div class="container-fluid">
<div class="row">
<div class="col-md-4">
    <div class="card card-danger card-outline">
        <div class="card-header"><h3 class="card-title">Add Entry</h3></div>
        <div class="card-body">
            <form method="post">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="someone@example.com">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="+19998887777">
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <select name="reason" class="form-control">
                        <option value="manual">Manual</option>
                        <option value="unsubscribed">Unsubscribed</option>
                        <option value="bounce">Bounce</option>
                        <option value="stop_sms">STOP SMS</option>
                        <option value="spam">Spam</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger btn-block">Add to DNC List</button>
            </form>
        </div>
    </div>
</div>
<div class="col-md-8">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Suppressed Contacts (<?php echo number_format($total); ?>)</h3></div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light"><tr><th>Email</th><th>Phone</th><th>Reason</th><th>Source</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((isset($row['email']) ? $row['email'] : '—')); ?></td>
                    <td><?php echo htmlspecialchars((isset($row['phone']) ? $row['phone'] : '—')); ?></td>
                    <td><span class="badge badge-danger"><?php echo htmlspecialchars($row['reason']); ?></span></td>
                    <td><small><?php echo htmlspecialchars((isset($row['source']) ? $row['source'] : '')); ?></small></td>
                    <td><small><?php echo $row['created_at'] ? date('m/d/Y', strtotime($row['created_at'])) : ''; ?></small></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Remove from DNC list? They may receive future emails.')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="dnc_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($rows)): ?><tr><td colspan="6" class="text-center text-muted p-3">No entries yet. Entries are added automatically when contacts unsubscribe or reply STOP.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if($totalPages>1): $qp=$_GET;unset($qp['page']);$qs=($t=http_build_query($qp))?'&'.$t:''; ?>
        <div class="card-footer"><ul class="pagination pagination-sm mb-0 float-right">
            <?php if($page>1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1;?><?php echo $qs;?>">‹</a></li><?php endif; ?>
            <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <li class="page-item <?php echo $p===$page?'active':'';?>"><a class="page-link" href="?page=<?php echo $p;?><?php echo $qs;?>"><?php echo $p;?></a></li>
            <?php endfor; ?>
            <?php if($page<$totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1;?><?php echo $qs;?>">›</a></li><?php endif; ?>
        </ul></div>
        <?php endif; ?>
    </div>
</div>
</div>
</div></section>
<?php include("../layout/footer.php"); ?>
