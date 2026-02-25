<?php
session_start();
include('../includes/config.php');

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: ../login.php');
    exit();
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Messages
$msg = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';
$error = isset($_SESSION['error_msg']) ? $_SESSION['error_msg'] : '';
unset($_SESSION['success_msg']);
unset($_SESSION['error_msg']);

// Search filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = "WHERE status = 'active'";
if($search){
    $where_clause .= " AND (first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR other_name LIKE '%$search%' OR staff_phone LIKE '%$search%' OR email LIKE '%$search%' OR facility_name LIKE '%$search%' OR department_name LIKE '%$search%' OR cadre_name LIKE '%$search%' OR id_number LIKE '%$search%')";
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM county_staff $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Fetch staff records
$query = "SELECT * FROM county_staff $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff List</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #011f88; margin-bottom: 25px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .search-box { flex: 1; min-width: 300px; }
        .search-box input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #011f88; color: #fff; }
        .btn-primary:hover { background: #013bb8; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-warning:hover { background: #e0a800; }
        .btn-danger { background: #dc3545; color: #fff; }
        .btn-danger:hover { background: #c82333; }
        .btn-info { background: #17a2b8; color: #fff; }
        .btn-info:hover { background: #138496; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #011f88; color: #fff; font-weight: bold; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
        .pagination a:hover { background: #011f88; color: #fff; }
        .pagination .active { background: #011f88; color: #fff; }
        .action-buttons { display: flex; gap: 5px; }
        .photo-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; cursor: pointer; }
        .no-photo { width: 40px; height: 40px; background: #ddd; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { flex: 1; padding: 15px; background: #f8f9fa; border-left: 4px solid #011f88; border-radius: 4px; }
        .stat-card h3 { font-size: 24px; color: #011f88; }
        .stat-card p { color: #666; margin-top: 5px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 768px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>County Staff List</h2>
        
        <?php if($msg): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $total_records; ?></h3>
                <p>Total Staff Members</p>
            </div>
        </div>
        
        <div class="toolbar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by name, phone, email, facility, department..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div>
                <a href="add_staff.php" class="btn btn-primary">Add New Staff</a>
                <a href="export_staff.php?search=<?php echo urlencode($search); ?>" class="btn btn-success">Export to Excel</a>
            </div>
        </div>
        
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>ID Number</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Facility</th>
                        <th>Department</th>
                        <th>Cadre</th>
                        <th>County</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = $offset + 1;
                    while($row = mysqli_fetch_assoc($result)): 
                    ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td>
                            <?php if($row['photo']): ?>
                                <img src="display_photo.php?staff_id=<?php echo $row['staff_id']; ?>" class="photo-thumb" alt="Photo" onclick="window.open(this.src, '_blank')">
                            <?php else: ?>
                                <div class="no-photo">No Photo</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $row['first_name'] . ' ' . $row['other_name'] . ' ' . $row['last_name']; ?></td>
                        <td><?php echo $row['id_number']; ?></td>
                        <td><?php echo $row['staff_phone']; ?></td>
                        <td><?php echo $row['email']; ?></td>
                        <td><?php echo $row['facility_name']; ?></td>
                        <td><?php echo $row['department_name']; ?></td>
                        <td><?php echo $row['cadre_name']; ?></td>
                        <td><?php echo $row['county_name']; ?></td>
                        <td>
                            <span style="background: #8FFF8F; color: #000;  border-radius: 4px; padding: 5px 10px;
                                display: inline-block;  font-size: 14px;  font-weight: 500; text-transform: uppercase;
                                letter-spacing: 0.3px; line-height: 1; "><?php echo $row['status']; ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="view_staff.php?staff_id=<?php echo $row['staff_id']; ?>" class="btn btn-info btn-sm">View</a>
                                <a href="update_staff.php?staff_id=<?php echo $row['staff_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="disable_staff.php?staff_id=<?php echo $row['staff_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to disable this staff member?')">Disable</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if(mysqli_num_rows($result) == 0): ?>
                    <tr>
                        <td colspan="11" style="text-align: center; padding: 20px;">No staff members found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-filter as you type
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function(){
            clearTimeout(searchTimeout);
            const searchValue = this.value;
            searchTimeout = setTimeout(function(){
                window.location.href = '?search=' + encodeURIComponent(searchValue);
            }, 500);
        });
    </script>
</body>
</html>
