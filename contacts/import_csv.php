<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$msg="";
$error="";

if(isset($_POST['import'])){

    if(isset($_FILES['file']['tmp_name']) && $_FILES['file']['tmp_name']!=""){

        $file = $_FILES['file']['tmp_name'];
        $handle = fopen($file,"r");

        $row = 0;
        $imported = 0;

        // ownership stamp for imported records
        list($owner_uid, $owner_mid) = ownershipStamp();

        while(($data = fgetcsv($handle,1000,",")) !== FALSE){

            $row++;

            if($row == 1){
                continue; // skip header
            }

            $name  = mysqli_real_escape_string($conn, isset($data[0]) ? $data[0] : '');
            $email = mysqli_real_escape_string($conn, isset($data[1]) ? $data[1] : '');
            $phone = mysqli_real_escape_string($conn, isset($data[2]) ? $data[2] : '');
            $group_name = mysqli_real_escape_string($conn, isset($data[3]) ? $data[3] : '');

            // 1. Insert Contact
            mysqli_query($conn,"INSERT IGNORE INTO contacts
                (name,email,phone,status,created_at,user_id,manager_id)
                VALUES
                ('$name','$email','$phone','active','$currentTime','$owner_uid','$owner_mid')");

            $contact_id = mysqli_insert_id($conn);

            // 2. Handle Group (scoped to current user's groups)
            if($group_name != ''){

                $gq = mysqli_query($conn, "SELECT id FROM contact_groups WHERE name = '$group_name' AND user_id='$owner_uid' LIMIT 1");

                if(mysqli_num_rows($gq) > 0){
                    $gdata = mysqli_fetch_assoc($gq);
                    $group_id = $gdata['id'];
                } else {
                    // create new group owned by current user
                    mysqli_query($conn, "INSERT INTO contact_groups (name, created_at, user_id, manager_id)
                        VALUES ('$group_name', '$currentTime', '$owner_uid', '$owner_mid')");

                    $group_id = mysqli_insert_id($conn);
                }

                // 3. Map contact to group
                mysqli_query($conn, "INSERT INTO contact_group_map (contact_id, group_id)
                    VALUES ('$contact_id', '$group_id')");
            }

            $imported++;
        }

        fclose($handle);

        $msg = "$imported contacts imported successfully";

    } else {
        $error = "Please select a CSV file";
    }
}

?>

<div class="row">

<div class="col-md-8">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Import Contacts CSV</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success">
<?php echo $msg; ?>
</div>
<?php } ?>

<?php if($error!=""){ ?>
<div class="alert alert-danger">
<?php echo $error; ?>
</div>
<?php } ?>

<form method="post" enctype="multipart/form-data">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Select CSV File</label>

<input type="file" name="file" class="form-control" required>

</div>

<button type="submit" name="import" class="btn btn-success">

<i class="fas fa-upload"></i> Import Contacts

</button>

<a href="list.php" class="btn btn-secondary">

Back

</a>

</form>

</div>

</div>

<div class="card">

<div class="card-header">
<h3 class="card-title">CSV Format</h3>
</div>

<div class="card-body">

<p>Your CSV file must follow this format:</p>

<pre>
name,email,phone,group
John Doe,john@gmail.com,9999999999,abc
Jane Smith,jane@gmail.com,8888888888,Xyz
</pre>
<p class="text-muted">Imported contacts and any newly created groups will be owned by you.</p>

</div>

</div>

</div>

</div>

<?php include("../layout/footer.php"); ?>
