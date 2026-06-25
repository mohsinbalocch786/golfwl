<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$campaign_id=(int)$_GET['campaign_id'];

// verify ownership of the campaign before generating its queue
$chk = mysqli_query($conn, "SELECT * FROM campaigns WHERE id='$campaign_id'");
$campaign = mysqli_fetch_assoc($chk);
if(!$campaign){
    echo "Campaign not found";
    exit;
}
assertOwnership($campaign);

$owner_uid = (int)$campaign['user_id'];
$owner_mid = (int)$campaign['manager_id'];

// contacts belonging to the campaign's target groups,
// scoped to the campaign owner's contacts only
$q="
SELECT contacts.*
FROM contacts
JOIN contact_group_map
ON contacts.id=contact_group_map.contact_id
JOIN campaign_groups
ON campaign_groups.group_id=contact_group_map.group_id
WHERE campaign_groups.campaign_id='$campaign_id'
AND contacts.user_id='$owner_uid'
";

$r=mysqli_query($conn,$q);

while($c=mysqli_fetch_assoc($r)){

mysqli_query($conn,"
INSERT INTO campaign_queue
(campaign_id,contact_id,email,phone,status,user_id,manager_id)
VALUES
('$campaign_id','".$c['id']."','".mysqli_real_escape_string($conn,$c['email'])."','".mysqli_real_escape_string($conn,$c['phone'])."','pending','$owner_uid','$owner_mid')
");

}

echo "Queue Generated";

?>
