<?php
// $id_number and $back_tab must be set by the including file
$back_tab = $back_tab ?? '';
?>
<div class="form-actions">
    <a href="employee_profile.php?id_number=<?= urlencode($id_number) ?><?= $back_tab ? '#'.$back_tab : '' ?>" class="btn btn-secondary">
        <i class="fas fa-times"></i> Cancel
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Save Changes
    </button>
</div>
