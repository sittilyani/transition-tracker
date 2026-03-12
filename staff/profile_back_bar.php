<?php
// $id_number and $back_tab must be set by the including file
$back_tab = $back_tab ?? '';
?>
<div class="back-bar">
    <a href="employee_profile.php?id_number=<?= urlencode($id_number) ?><?= $back_tab ? '#'.$back_tab : '' ?>">
        <i class="fas fa-arrow-left"></i> Back to Profile
    </a>
</div>
<?php if (!empty($_SESSION['success_message'])): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?></div>
<?php unset($_SESSION['success_message']); endif; ?>
<?php if (!empty($_SESSION['error_message'])): ?>
<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['error_message'] ?></div>
<?php unset($_SESSION['error_message']); endif; ?>
