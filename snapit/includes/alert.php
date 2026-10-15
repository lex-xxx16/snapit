<?php
require_once __DIR__ . '/functions.php';
$flash = get_flash_message();
if ($flash):
    $type = $flash['type'] === 'danger' ? 'danger' : ($flash['type'] === 'success' ? 'success' : ($flash['type'] === 'info' ? 'info' : 'warning'));
?>
<div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
    <strong><?= e($flash['message']) ?></strong>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
