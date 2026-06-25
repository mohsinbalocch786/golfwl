<?php

include("../config/db.php");

$group_id = (int)$_POST['group_id'];

$sql = "
SELECT
    c.id,
    c.name,
    c.phone
FROM contacts c
INNER JOIN contact_group_map gm
    ON gm.contact_id = c.id
WHERE gm.group_id = $group_id
ORDER BY c.name
";

$result = mysqli_query($conn,$sql);

echo '<option value="">Select Contact</option>';

while($row = mysqli_fetch_assoc($result))
{
    echo '<option value="'.$row['id'].'">';
    echo $row['name'].' - '.$row['phone'];
    echo '</option>';
}