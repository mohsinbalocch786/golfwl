<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM campaigns WHERE id='$id'");
$data=mysqli_fetch_assoc($chk);
if(!$data){
    header("Location:list.php");
    exit;
}
assertOwnership($data);

include("../layout/header.php");
include("../layout/sidebar.php");

$msg="";

if($_POST){
    verifyCsrf();

$name=mysqli_real_escape_string($conn,$_POST['name']);
$type=$_POST['type'];
$template_id=$_POST['template_id'];
$send_per_day=$_POST['send_per_day'];
$date=$_POST['start_date'];
$time=$_POST['send_time'];
$status=$_POST['status'];

if(empty($time)){
$msg="Please select sending time";
}else{

$repeat_days="";
if(isset($_POST['repeat_days']))
$repeat_days=implode(",",$_POST['repeat_days']);

$schedule_datetime=$date." ".$time.":00";

/* update campaign */
$update_query = "
UPDATE campaigns SET
name='$name',
type='$type',
template_id='$template_id',
send_per_day='$send_per_day',
schedule_datetime='$schedule_datetime',
send_time='$time',
repeat_days='$repeat_days',
status='$status'
WHERE id='$id'
";
mysqli_query($conn, $update_query);

/* update groups */

mysqli_query($conn,"DELETE FROM campaign_groups WHERE campaign_id='$id'");

if(isset($_POST['groups'])){

foreach($_POST['groups'] as $gid){

$gid = (int)$gid;
mysqli_query($conn,"
INSERT INTO campaign_groups
(campaign_id,group_id)
VALUES
('$id','$gid')
");

}

}

$msg="Campaign Updated";

// refresh data after update
$chk=mysqli_query($conn,"SELECT * FROM campaigns WHERE id='$id'");
$data=mysqli_fetch_assoc($chk);

}

}


$repeat_days=explode(",",$data['repeat_days']);

/* load groups - scoped to ownership */
$gw = ownershipWhere('');
$groups=mysqli_query($conn,"SELECT * FROM contact_groups WHERE $gw ORDER BY name");

/* load selected groups */
$selected_groups=[];
$sg=mysqli_query($conn,"SELECT group_id FROM campaign_groups WHERE campaign_id='$id'");
while($row=mysqli_fetch_assoc($sg)){
$selected_groups[]=$row['group_id'];
}

/* templates - scoped to visibility */
if(isSuperAdmin()){
    $tWhere = "1=1";
} else {
    $uid = currentUserId();
    $mid = currentManagerId();
    $visParts = [];
    $visParts[] = "visibility='global'";
    $visParts[] = "user_id=$uid";
    if(canViewTeam()){
        $visParts[] = "(visibility='team' AND manager_id=$mid)";
    }
    $tWhere = "(" . implode(" OR ", $visParts) . ")";
}

$type_esc = mysqli_real_escape_string($conn, $data['type']);
$templates=mysqli_query($conn,"
SELECT id,name
FROM templates
WHERE type='$type_esc'
AND status='active'
AND $tWhere
");

?>

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit Campaign</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post" id="campaignForm">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Campaign Name</label>

<input type="text"
name="name"
class="form-control"
value="<?php echo htmlspecialchars($data['name']); ?>"
required>

</div>


<div class="form-group">
<label>Campaign Type</label>

<select name="type" id="type" class="form-control">

<option value="">Select</option>

<option value="email" <?php if($data['type']=="email") echo "selected"; ?>>Email</option>
<option value="sms" <?php if($data['type']=="sms") echo "selected"; ?>>SMS</option>

</select>

</div>


<div class="form-group">
<label>Template</label>

<select name="template_id" id="template" class="form-control">

<option value="">Select Template</option>

<?php while($t=mysqli_fetch_assoc($templates)){ ?>

<option value="<?php echo $t['id']; ?>"
<?php if($data['template_id']==$t['id']) echo "selected"; ?>>

<?php echo htmlspecialchars($t['name']); ?>

</option>

<?php } ?>

</select>

</div>


<div class="form-group">
    <label>Target Groups</label>

    <select name="groups[]" id="groups" class="form-control select2" multiple>
        <?php while($g=mysqli_fetch_assoc($groups)){ ?>
            
            <option value="<?php echo $g['id']; ?>"
                <?php if(in_array($g['id'], $selected_groups)) echo "selected"; ?>>
                
                <?php echo htmlspecialchars($g['name']); ?>
            
            </option>

        <?php } ?>
    </select>
</div>

<div class="form-group">
<label>Start Date</label>

<input type="date"
name="start_date"
id="date"
class="form-control"
value="<?php echo date('Y-m-d',strtotime($data['schedule_datetime'])); ?>">

</div>


<div class="form-group">

<label>Select Time</label>

<div>
<?php

for ($hour = 4; $hour <= 22; $hour++) {
    for ($min = 0; $min < 60; $min += 15) {

        $time = sprintf("%02d:%02d", $hour, $min);

        $active="btn-outline-primary";

        if($data['send_time']==$time)
        $active="btn-primary";

        echo "<button type='button' class='btn $active timebtn m-1' data-time='$time'>$time</button>";
    }
}

?>
</div>

<input type="hidden" name="send_time" id="send_time" value="<?php echo $data['send_time']; ?>">

</div>


<div class="form-group">

<label>Repeat Days</label><br>

<?php

$days = ["MON","TUE","WED","THU","FRI"];

$selectedDays = [];

if (!empty($data['repeat_days'])) {
    $selectedDays = explode(',', $data['repeat_days']);
}

foreach ($days as $d) {

    $checked = "";

    if (in_array($d, $selectedDays)) {
        $checked = "checked";
    }

    echo "<label style='margin-right:10px;'>
    <input type='checkbox'
    name='repeat_days[]'
    value='$d'
    $checked> $d
    </label>";
}
?>

</div>


<div class="form-group">

<label>Send Per Day</label>

<select name="send_per_day" class="form-control">
    <option value="1000" <?php if($data['send_per_day']==1000) echo "selected"; ?>>1000</option>
    <option value="2000" <?php if($data['send_per_day']==2000) echo "selected"; ?>>2000</option>
    <option value="3000" <?php if($data['send_per_day']==3000) echo "selected"; ?>>3000</option>
    <option value="5000" <?php if($data['send_per_day']==5000) echo "selected"; ?>>5000</option>
    <option value="10000" <?php if($data['send_per_day']==10000) echo "selected"; ?>>10000</option>
</select>

</div>


<div class="form-group">

<label>Status</label>

<select name="status" class="form-control">

<option value="pending" <?php if($data['status']=="pending") echo "selected"; ?>>Pending</option>
<option value="in-progress" <?php if($data['status']=="in-progress") echo "selected"; ?>>In Progress</option>
<option value="completed" <?php if($data['status']=="completed") echo "selected"; ?>>Completed</option>

</select>

</div>


<button class="btn btn-primary">
<i class="fas fa-save"></i> Update Campaign
</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    $(document).ready(function() {
$('select[name="groups[]"]').select2();
    });
/* load templates */

$("#type").change(function(){

var type=$(this).val();
$("#template").load("../ajax/get_templates.php?type="+type);

});


/* select time */

$(".timebtn").click(function(){

var date=$("#date").val();

if(date==""){
alert("Please select date first");
return;
}

var time=$(this).data("time");

$(".timebtn").removeClass("btn-primary");
$(".timebtn").addClass("btn-outline-primary");

$(this).removeClass("btn-outline-primary");
$(this).addClass("btn-primary");

$("#send_time").val(time);

/* check schedule */

$.post("../ajax/check_time.php",
{date:date,time:time},
function(res){

if(res=="booked"){
alert("Time already booked for selected date");
$("#send_time").val("");
}

});

});


/* form validation */

$("#campaignForm").submit(function(e){

if($("#send_time").val()==""){
alert("Please select sending time");
e.preventDefault();
return false;
}

});

</script>

<?php include("../layout/footer.php"); ?>
