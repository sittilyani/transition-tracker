<?php
include('../includes/config.php');

if(isset($_GET['staff_id'])){
    $staff_id = (int)$_GET['staff_id'];
    
    $query = "SELECT photo FROM county_staff WHERE staff_id = $staff_id";
    $result = mysqli_query($conn, $query);
    
    if($row = mysqli_fetch_assoc($result)){
        if($row['photo']){
            header("Content-Type: image/jpeg");
            echo $row['photo'];
        } else {
            // Default placeholder image
            header("Content-Type: image/png");
            readfile('../assets/default-avatar.png'); // You can create a default avatar
        }
    }
}
?>
