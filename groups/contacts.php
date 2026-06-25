<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$group_id=(int)$_GET['group_id'];

$g=mysqli_query($conn,"SELECT * FROM contact_groups WHERE id='$group_id'");
$group=mysqli_fetch_assoc($g);
if(!$group){
    header("Location:list.php");
    exit;
}
assertOwnership($group);

include("../layout/header.php");
include("../layout/sidebar.php");

/* ADD CONTACT TO GROUP */

if(isset($_POST['add_contact'])){

$contact_id=(int)$_POST['contact_id'];

// verify contact belongs to current user's scope
$cchk = mysqli_query($conn, "SELECT * FROM contacts WHERE id='$contact_id'");
$crow = mysqli_fetch_assoc($cchk);

if($crow){
    assertOwnership($crow);
    mysqli_query($conn,"INSERT INTO contact_group_map
    (contact_id,group_id)
    VALUES
    ('$contact_id','$group_id')");
}

}

/* REMOVE CONTACT */

if(isset($_GET['remove'])){

$cid=(int)$_GET['remove'];

mysqli_query($conn,"DELETE FROM contact_group_map
WHERE contact_id='$cid' AND group_id='$group_id'");

}

/* CONTACTS IN GROUP */

$contacts=mysqli_query($conn,"
SELECT c.*
FROM contacts c
JOIN contact_group_map m ON m.contact_id=c.id
WHERE m.group_id='$group_id'
");

/* CONTACT LIST FOR ADD — scoped to ownership */
$cw = ownershipWhere('');
$all_contacts=mysqli_query($conn,"
SELECT * FROM contacts
WHERE $cw
ORDER BY name
");

?>

<div class="row">

<div class="col-md-6">

<div class="card">

<div class="card-header">
<h3 class="card-title">
Contacts in Group: <?php echo htmlspecialchars($group['name']); ?>
</h3>
</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th>Name</th>
<th>Email</th>
<th></th>
</tr>

<?php while($c=mysqli_fetch_assoc($contacts)){ ?>

<tr>

<td><?php echo htmlspecialchars($c['name']); ?></td>
<td><?php echo htmlspecialchars($c['email']); ?></td>

<td>

<a href="?group_id=<?php echo $group_id ?>&remove=<?php echo $c['id']; ?>"
class="btn btn-danger btn-sm">

Remove

</a>

</td>

</tr>

<?php } ?>

</table>

</div>
</div>

</div>


<div class="col-md-6">

<div class="card">

<div class="card-header">
<h3 class="card-title">Add Contact to Group</h3>
</div>

<div class="card-body">

<form method="post">

<div class="form-group">

<select name="contact_id" class="form-control">

<?php while($c=mysqli_fetch_assoc($all_contacts)){ ?>

<option value="<?php echo $c['id']; ?>">
<?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
</option>

<?php } ?>

</select>

</div>

<button name="add_contact" class="btn btn-primary">
Add to Group
</button>

</form>

</div>

</div>

</div>

</div>

<?php include("../layout/footer.php"); ?>
