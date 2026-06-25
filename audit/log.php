<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();
if (!isSuperAdmin()) { header("Location:../admin/dashboard.php"); exit; }

$page    = max(1,(int)(isset($_GET['page'])?$_GET['page']:1));
$perPage = 50; $offset = ($page-1)*$perPage;
$where = "1=1";
if (!empty($_GET['module'])) { $m=mysqli_real_escape_string($conn,$_GET['module']); $where.=" AND module='$m'"; }
if (!empty($_GET['user_id'])) { $u=(int)$_GET['user_id']; $where.=" AND user_id='$u'"; }
$total = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM audit_log WHERE $where"))['n'];
$totalPages = max(1,(int)ceil($total/$perPage));
$rows=[]; $r=mysqli_query($conn,"SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE $where ORDER BY a.id DESC LIMIT $perPage OFFSET $offset");
while($row=mysqli_fetch_assoc($r)) $rows[]=$row;
$modules=[]; $mq=mysqli_query($conn,"SELECT DISTINCT module FROM audit_log ORDER BY module");
while($row=mysqli_fetch_assoc($mq)) $modules[]=$row['module'];
include("../layout/header.php");
include("../layout/sidebar.php");
?>
<section class="content-header"><div class="container-fluid"><h1><i class="fas fa-history mr-2"></i>Audit Log</h1></div></section>
<section class="content"><div class="container-fluid">
<form method="GET" class="mb-3">
    <div class="row">
        <div class="col-md-2">
            <select name="module" class="form-control form-control-sm">
                <option value="">All Modules</option>
                <?php foreach($modules as $mod): ?>
                <option value="<?php echo htmlspecialchars($mod);?>" <?php echo(isset($_GET['module'])&&$_GET['module']===$mod?'selected':'');?>><?php echo htmlspecialchars($mod);?></option>
                <?php endforeach;?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button> <a href="log.php" class="btn btn-sm btn-secondary">Reset</a></div>
    </div>
</form>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light"><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>Record ID</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach($rows as $row): ?>
            <tr>
                <td><small><?php echo $row['created_at']?date('m/d H:i',strtotime($row['created_at'])):'';?></small></td>
                <td><small><?php echo htmlspecialchars((isset($row['name']) ? $row['name'] : ($row['user_type']==='admin'?'Admin':'#'.$row['user_id'])));?></small></td>
                <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['action']);?></span></td>
                <td><small><?php echo htmlspecialchars($row['module']);?></small></td>
                <td><?php echo $row['record_id']?:'—';?></td>
                <td><small><?php echo htmlspecialchars(mb_substr((isset($row['detail']) ? $row['detail'] : ''),0,80));?></small></td>
                <td><small><?php echo htmlspecialchars((isset($row['ip_address']) ? $row['ip_address'] : ''));?></small></td>
            </tr>
            <?php endforeach;?>
            <?php if(empty($rows)):?><tr><td colspan="7" class="text-center text-muted p-3">No audit entries yet.</td></tr><?php endif;?>
            </tbody>
        </table>
    </div>
    <?php if($totalPages>1): $qp=$_GET;unset($qp['page']);$qs=($t=http_build_query($qp))?'&'.$t:''; ?>
    <div class="card-footer"><ul class="pagination pagination-sm mb-0 float-right">
        <?php if($page>1):?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1;?><?php echo $qs;?>">‹</a></li><?php endif;?>
        <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++):?>
        <li class="page-item <?php echo $p===$page?'active':'';?>"><a class="page-link" href="?page=<?php echo $p;?><?php echo $qs;?>"><?php echo $p;?></a></li>
        <?php endfor;?>
        <?php if($page<$totalPages):?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1;?><?php echo $qs;?>">›</a></li><?php endif;?>
    </ul></div>
    <?php endif;?>
</div>
</div></section>
<?php include("../layout/footer.php");?>
