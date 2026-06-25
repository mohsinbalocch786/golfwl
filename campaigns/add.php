<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$msg="";

/* load groups - scoped to ownership */
$gw = ownershipWhere('');
$groups=mysqli_query($conn,"SELECT * FROM contact_groups WHERE $gw ORDER BY name");

if($_POST){
    verifyCsrf();

$name=mysqli_real_escape_string($conn,$_POST['name']);
$type=$_POST['type'];
$template_id=$_POST['template_id'];
$send_per_day=$_POST['send_per_day'];
$date=$_POST['start_date'];
$time=$_POST['send_time'];

$repeat_days="";
if(isset($_POST['repeat_days']))
$repeat_days=implode(",",$_POST['repeat_days']);

$schedule_datetime=$date." ".$time.":00";

// ownership stamp
list($owner_uid, $owner_mid) = ownershipStamp();

/* insert campaign */

mysqli_query($conn,"
INSERT INTO campaigns
(name,type,template_id,send_per_day,schedule_datetime,send_time,repeat_days,status,created_at,user_id,manager_id)
VALUES
('$name','$type','$template_id','$send_per_day','$schedule_datetime','$time','$repeat_days','pending','$currentTime','$owner_uid','$owner_mid')
");

$campaign_id=mysqli_insert_id($conn);

/* save groups */

if(isset($_POST['groups'])){

foreach($_POST['groups'] as $gid){

$gid = (int)$gid;
mysqli_query($conn,"
INSERT INTO campaign_groups
(campaign_id,group_id)
VALUES
('$campaign_id','$gid')
");

}

}

$msg="Campaign Created";
header("Location:list.php");
exit;
}
include("../layout/header.php");
include("../layout/sidebar.php");
?>

<div class="card card-primary">
<div class="card-header">
<h3>Email Campaign Add</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post" id="campaignForm">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Campaign Name</label>
<input type="text" name="name" class="form-control">
</div>


<div class="form-group">
<label>Campaign Type</label>

<select name="type" id="type" class="form-control">
<option value="">Select</option>
<option value="email">Email</option>
<option value="sms">SMS</option>
</select>

</div>


<div class="form-group">
<label>Template</label>

<select name="template_id" id="template" class="form-control">
<option value="">Select Template</option>
</select>

</div>


<div class="form-group">
    <label>Target Groups</label>

    <select name="groups[]" id="groups" class="form-control select2" multiple>
        <?php while($g=mysqli_fetch_assoc($groups)){ ?>
            
            <option value="<?php echo $g['id']; ?>">
                <?php echo htmlspecialchars($g['name']); ?>
            </option>

        <?php } ?>
    </select>
</div>


<div class="form-group">
<label>Start Date</label>
<input type="date" name="start_date" id="date" class="form-control">
</div>


<div class="form-group">

<label>Select Time</label>

<div>
<?php

for ($hour = 4; $hour <= 22; $hour++) {
    for ($min = 0; $min < 60; $min += 15) {

        $time = sprintf("%02d:%02d", $hour, $min);

        echo "<button type='button' class='btn btn-outline-primary timebtn m-1' data-time='$time'>$time</button>";
    }
}

?>
</div>

<input type="hidden" name="send_time" id="send_time">

</div>


<div class="form-group">

<label>Repeat Days</label><br>

<?php

$days=["MON","TUE","WED","THU","FRI"];

foreach($days as $d){

echo "<label>
<input type='checkbox' name='repeat_days[]' value='$d' checked> $d
</label> ";

}

?>

</div>


<select name="send_per_day" class="form-control">
    <option value="1000">1000</option>
    <option value="2000">2000</option>
    <option value="3000">3000</option>
    <option value="5000">5000</option>
    <option value="10000">10000</option>
</select>


<button class="btn btn-primary mt-4">Create Campaign</button>

<a href="list.php" class="btn btn-secondary mt-4">Back</a>

</form>

</div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
   $(document).ready(function() {
$('select[name="groups[]"]').select2();
    });
/* validation */

$("#campaignForm").submit(function(e){

var time=$("#send_time").val();
var date=$("#date").val();
var type=$("#type").val();
var template=$("#template").val();

if(type==""){
alert("Please select campaign type");
e.preventDefault();
return false;
}

if(template==""){
alert("Please select template");
e.preventDefault();
return false;
}

if(date==""){
alert("Please select start date");
e.preventDefault();
return false;
}

if(time==""){
alert("Please select sending time");
e.preventDefault();
return false;
}

});


/* load templates */

$("#type").change(function(){

var type=$(this).val();

$("#template").load("../ajax/get_templates.php?type="+type);

});


/* select time */

$(".timebtn").click(function(){

var time=$(this).data("time");

$("#send_time").val(time);

$(".timebtn").removeClass("btn-primary");
$(this).addClass("btn-primary");

var date=$("#date").val();

$.post("../ajax/check_time.php",
{date:date,time:time},
function(res){

if(res=="booked"){

alert("Time already booked for selected date");
$("#send_time").val("");

}

});

});

</script>

<?php include("../layout/footer.php"); ?>
