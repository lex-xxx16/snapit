<?php
// Admin tool: put REAL photos on the camera presets.
//  - "Download all": fetches photos from Wikimedia (needs internet on this PC).
//  - Or upload your own photo for any preset. Files are stored in uploads/preset_cache/ and work offline.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';
require_admin();
@set_time_limit(300);

$names = ['Kodak Gold 200','Kodak Portra 400','Fuji X Classic Chrome','Fuji Superia 400','Canon G7X II','iPhone 5S','iPhone 4S','DV Camcorder','Disposable Flash','Polaroid Instant','Y2K Digicam','B&W Film'];
$dir = __DIR__ . '/uploads/preset_cache';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$key = function($n) { return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $n); };
$has = function($n) use ($dir, $key) {
    foreach (['jpg','jpeg','png','webp'] as $e) { $f = "$dir/" . $key($n) . "_w240.$e"; if (is_file($f) && filesize($f) > 100) return $f; }
    return null;
};
$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { $log[] = 'Invalid security token.'; }
    elseif (isset($_POST['download_all'])) {
        foreach ($names as $n) {
            if ($has($n)) { $log[] = "[SKIP] $n (already has a photo)"; continue; }
            $src = camera_preset_source_file($n);
            $url = 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($src) . '?width=480';
            $raw = ''; $err = 'curl extension missing (enable extension=curl in php.ini)';
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>40, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_USERAGENT=>'Mozilla/5.0 (SnapIt photobooth preset cache; contact: admin@localhost)']);
                $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = $code >= 400 ? "HTTP $code" : curl_error($ch); curl_close($ch);
            }
            if (is_string($raw) && strlen($raw) > 100 && $err === '') { file_put_contents("$dir/" . $key($n) . "_w240.jpg", $raw); $log[] = "[OK]   $n"; }
            else { $log[] = "[FAIL] $n - " . ($err ?: 'empty response'); }
        }
    } elseif (isset($_POST['upload_for'])) {
        $n = $_POST['upload_for'];
        if (!in_array($n, $names, true)) { $log[] = 'Unknown preset.'; }
        elseif (empty($_FILES['photo']['tmp_name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) { $log[] = "[FAIL] $n - no file received"; }
        else {
            $info = @getimagesize($_FILES['photo']['tmp_name']);
            $map = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_WEBP=>'webp'];
            if (!$info || !isset($map[$info[2]])) { $log[] = "[FAIL] $n - must be a JPG, PNG or WEBP image"; }
            else {
                foreach (['jpg','jpeg','png','webp'] as $e) @unlink("$dir/" . $key($n) . "_w240.$e");
                move_uploaded_file($_FILES['photo']['tmp_name'], "$dir/" . $key($n) . "_w240." . $map[$info[2]]);
                $log[] = "[OK]   $n photo saved";
            }
        }
    }
}
include __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
  <h2 class="fw-bold mb-2">Camera preset photos</h2>
  <p class="text-muted">Real photos are stored on this computer, so they show even without internet.</p>
  <?php if ($log): ?><pre class="p-3 border rounded" style="white-space:pre-wrap"><?= e(implode("\n", $log)) ?></pre><?php endif; ?>
  <form method="POST" class="mb-4">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <button name="download_all" value="1" class="btn btn-snapit"><i class="fa-solid fa-download me-1"></i>Download all missing photos (needs internet)</button>
  </form>
  <div class="row g-3">
  <?php foreach ($names as $n): $f = $has($n); ?>
    <div class="col-md-6 col-lg-4"><div class="card p-3 h-100">
      <div class="fw-bold mb-2"><?= e($n) ?></div>
      <div style="height:90px;display:flex;align-items:center;justify-content:center;">
        <?php if ($f): ?><img src="<?= e(camera_photo_url($n)) ?>" style="max-height:90px;max-width:100%;border-radius:8px;"><?php else: ?><span class="text-danger small">No real photo yet</span><?php endif; ?>
      </div>
      <form method="POST" enctype="multipart/form-data" class="mt-2">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="upload_for" value="<?= e($n) ?>">
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2" required>
        <button class="btn btn-sm btn-outline-secondary w-100">Upload photo</button>
      </form>
    </div></div>
  <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
