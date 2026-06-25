<?php

include("../config/db.php");
include("../layout/header.php");
include("../layout/sidebar.php");

$msg="";

if($_POST){

$name=$_POST['name'];
$type=$_POST['type'];
$template_id=$_POST['template_id'];
$send_per_day=$_POST['send_per_day'];
$date=$_POST['start_date'];
$time=$_POST['send_time'];

$repeat_days="";

if(isset($_POST['repeat_days']))
$repeat_days=implode(",",$_POST['repeat_days']);

$schedule_datetime=$date." ".$time.":00";

mysqli_query($conn,"
INSERT INTO campaigns
(name,type,template_id,send_per_day,schedule_datetime,send_time,repeat_days,status,created_at)
VALUES
('$name','$type','$template_id','$send_per_day','$schedule_datetime','$time','$repeat_days','pending',NOW())
");

$msg="Campaign Created";

}

?>

<div class="card">
<div class="card-header">
<h3>Email Campaign Add</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post" id="campaignForm">

<div class="form-group">

<label>Campaign Name</label>

<input type="text" name="name" id="name" class="form-control">

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

<label>Start Date</label>

<input type="date" name="start_date" id="date" class="form-control">

</div>


<div class="form-group">

<label>Select Time</label>

<div>

<?php

$times=[
"04:00","05:00","06:00","07:00","08:00",
"09:00","10:00","11:00","12:00",
"13:00","14:00","15:00","16:00","17:00","18:00","19:00","20:00","21:00","22:00"
];

foreach($times as $t){

echo "<button type='button' class='btn btn-outline-primary timebtn' data-time='$t'>$t</button> ";

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

echo "<label><input type='checkbox' name='repeat_days[]' value='$d' checked> $d</label> ";

}

?>

</div>


<div class="form-group">

<label>Send Per Day</label>

<select name="send_per_day" class="form-control">

<?php

for($i=1000;$i<=5000;$i+=250){

echo "<option value='$i'>$i</option>";

}

?>

</select>

</div>


<button class="btn btn-primary">Create Campaign</button>

<a href="list.php" class="btn btn-secondary">
Back
</a>
</form>

</div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$("#campaignForm").submit(function(e){

    var time = $("#send_time").val();
    var date = $("#date").val();
    var type = $("#type").val();
    var template = $("#template").val();

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

/* check schedule */

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