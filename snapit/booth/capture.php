<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$session_id = current_booth_session_id($conn);
if (!$session_id) {
    set_flash('No active session found. Start a new session.', 'warning');
    redirect(site_url('booth/index.php'));
}

$presets = mysqli_query($conn, "SELECT * FROM camera_presets WHERE is_active = 1 ORDER BY preset_id ASC");
$filters = mysqli_query($conn, "SELECT * FROM filters WHERE is_active = 1 ORDER BY filter_id ASC");
$layouts = mysqli_query($conn, "SELECT * FROM layouts WHERE is_active = 1 ORDER BY photo_count ASC");
$frames  = mysqli_query($conn, "SELECT * FROM frame_designs WHERE is_active = 1 ORDER BY design_id ASC");

$first_preset = mysqli_fetch_assoc(mysqli_query($conn, "SELECT preset_id FROM camera_presets WHERE is_active = 1 ORDER BY preset_id ASC LIMIT 1"));
$first_filter = mysqli_fetch_assoc(mysqli_query($conn, "SELECT filter_id FROM filters WHERE is_active = 1 ORDER BY filter_id ASC LIMIT 1"));
$first_layout = mysqli_fetch_assoc(mysqli_query($conn, "SELECT layout_id FROM layouts WHERE is_active = 1 ORDER BY photo_count ASC LIMIT 1"));
$first_frame  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT design_id FROM frame_designs WHERE is_active = 1 ORDER BY design_id ASC LIMIT 1"));

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container py-4">
    <div class="text-center mb-3">
        <h2 class="fw-bold">
            <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Customize Your Booth
        </h2>
        <p class="text-muted mb-0">Choose your preset, filter, layout, and frame design.</p>
    </div>

    <div class="step-indicator no-print">
        <div class="step active" data-step="1">1</div>
        <div class="step" data-step="2">2</div>
        <div class="step" data-step="3">3</div>
        <div class="step" data-step="4">4</div>
    </div>

    <form method="POST" action="<?= e(site_url('booth/store_selection.php')) ?>" id="selectionForm">
        <div class="row g-4">
            <div class="col-lg-7">

                <div class="card p-3 mb-4" id="step-preset">
                    <div class="card-header bg-transparent border-bottom mb-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-sliders me-2"></i>1. Camera Presets</h5>
                    </div>
                    <div class="row g-3">
                        <?php while ($p = mysqli_fetch_assoc($presets)): ?>
                            <label class="col-md-6 col-lg-4">
                                <input type="radio" name="preset_id" value="<?= (int)$p['preset_id'] ?>"
                                       class="d-none preset-radio"
                                       data-filter="<?= e($p['cam_filter'] ?? '') ?>" data-grain="<?= (int)($p['fx_grain'] ?? 0) ?>" data-vig="<?= (int)($p['fx_vignette'] ?? 0) ?>" data-fade="<?= (int)($p['fx_fade'] ?? 0) ?>"
                                       <?= $first_preset && $p['preset_id'] == $first_preset['preset_id'] ? 'checked' : '' ?>>
                                <div class="preset-card">
                                    <div class="fw-bold mb-1"><?= e($p['name']) ?></div>
                                    <?php $cam_img = camera_photo_url($p['name']); ?>
                                    <div class="preset-photo-wrap">
                                        <?php if ($cam_img): ?>
                                            <img src="<?= e($cam_img) ?>" alt="<?= e($p['name']) ?>" class="preset-photo" loading="lazy"
                                                
                                                 data-preset-name="<?= e($p['name']) ?>"
                                                 data-width="240"
                                                 onerror="window.__presetOnError && window.__presetOnError(this)"
                                                 onload="window.__presetOnLoad && window.__presetOnLoad(this)">
                                        <?php else: ?>
                                            <i class="fa-solid fa-camera preset-photo-icon"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted"><?= e($p['description'] ?? '') ?></div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="card p-3 mb-4" id="step-filter">
                    <div class="card-header bg-transparent border-bottom mb-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-palette me-2"></i>2. Filters</h5>
                    </div>
                    <div class="row g-3">
                        <?php while ($f = mysqli_fetch_assoc($filters)): ?>
                            <label class="col-md-6 col-lg-4">
                                <input type="radio" name="filter_id" value="<?= (int)$f['filter_id'] ?>"
                                       class="d-none filter-radio"
                                       data-css="<?= e($f['css_filter']) ?>"
                                       <?= $first_filter && $f['filter_id'] == $first_filter['filter_id'] ? 'checked' : '' ?>>
                                <div class="filter-card">
                                    <div class="filter-preview" style="background-image:url('<?= e(site_url('includes/img/filter_sample.svg')) ?>');background-size:cover;background-position:center;filter:<?= e($f['css_filter']) ?>"></div>
                                    <div class="fw-bold"><?= e($f['name']) ?></div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="card p-3 mb-4" id="step-layout">
                    <div class="card-header bg-transparent border-bottom mb-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-table-cells me-2"></i>3. Layouts</h5>
                    </div>
                    <div class="row g-3">
                        <?php while ($L = mysqli_fetch_assoc($layouts)): ?>
                            <label class="col-md-6 col-lg-4">
                                <input type="radio" name="layout_id" value="<?= (int)$L['layout_id'] ?>"
                                       class="d-none layout-radio"
                                       data-count="<?= (int)$L['photo_count'] ?>"
                                       data-cols="<?= (int)$L['grid_cols'] ?>"
                                       data-rows="<?= (int)$L['grid_rows'] ?>"
                                       data-print="<?= e($L['print_size'] ?? '') ?>"
                                       <?= $first_layout && $L['layout_id'] == $first_layout['layout_id'] ? 'checked' : '' ?>>
                                <div class="layout-card">
                                    <div class="mb-2">
                                        <?php
                                        $ps = explode('x', $L['print_size'] ?: '3x2'); $mw = ((int)$ps[0] <= 2) ? 44 : 64;
$items = str_repeat('<div style="background:#d8c09a;border-radius:3px;"></div>', (int)$L['photo_count']);
echo '<div style="display:grid;gap:3px;margin:0 auto;padding:4px;border:1px solid #ddd;max-width:' . $mw . 'px;aspect-ratio:' . (int)$ps[0] . '/' . (int)$ps[1] . ';grid-template-columns:repeat(' . (int)$L['grid_cols'] . ',1fr);grid-template-rows:repeat(' . (int)$L['grid_rows'] . ',1fr);">' . $items . '</div>';
                                        ?>
                                    </div>
                                    <div class="fw-bold mb-0"><?= e($L['name']) ?></div>
                                    <div class="small text-muted"><?= (int)$L['photo_count'] ?> photos</div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="card p-3 mb-4" id="step-frame">
                    <div class="card-header bg-transparent border-bottom mb-3">
                        <h5 class="fw-bold mb-0"><i class="fa-regular fa-image me-2"></i>4. Frame Designs</h5>
                    </div>
                    <div class="row g-3">
                        <?php while ($fr = mysqli_fetch_assoc($frames)): ?>
                            <label class="col-md-6 col-lg-4">
                                <input type="radio" name="design_id" value="<?= (int)$fr['design_id'] ?>"
                                       class="d-none frame-radio"
                                       data-border-color="<?= e($fr['border_color']) ?>"
                                       data-border-width="<?= (int)$fr['border_width'] ?>"
                                       data-bg="<?= e($fr['bg_color']) ?>" data-fg="<?= e($fr['text_color']) ?>" data-gap="<?= (int)$fr['photo_gap'] ?>" data-rad="<?= (int)$fr['photo_radius'] ?>" data-pattern="<?= e($fr['pattern'] ?? '') ?>"
                                       <?= $first_frame && $fr['design_id'] == $first_frame['design_id'] ? 'checked' : '' ?>>
                                <div class="frame-card">
                                    <div style="border:<?= (int)$fr['border_width'] ?>px solid <?= e($fr['border_color']) ?>;border-radius:10px;height:90px;background:<?= e($fr['bg_color']) ?>;display:flex;align-items:center;justify-content:center;margin-bottom:6px;">
                                        <i class="fa-regular fa-image text-muted"></i>
                                    </div>
                                    <div class="fw-bold"><?= e($fr['name']) ?></div>
                                    <div class="small text-muted"><?= (int)$fr['border_width'] ?>px border</div>
                                </div>
                            </label>
                        <?php endwhile; ?>
                    </div>
                </div>

            </div>

            <div class="col-lg-5">
                <div class="sticky-top" style="top:1rem;">
                    <div class="card p-4">
                        <h5 class="fw-bold mb-3 text-center"><i class="fa-regular fa-eye me-2"></i>Live Preview</h5>
                        <div id="previewFrame" class="booth-frame" style="border:0;padding:0;background:transparent;min-height:0;position:relative;overflow:hidden;border-radius:16px;">
                            <div id="previewVideoWrap" style="position:relative;width:100%;line-height:0;border-radius:16px;background:#000;min-height:220px;display:none;">
                                <video id="previewVideo" autoplay playsinline muted
                                       style="width:100%;max-height:300px;object-fit:contain;border-radius:16px;background:#000;transform:scaleX(-1);-webkit-transform:scaleX(-1);"></video>
                                <div id="previewCamFx" style="position:absolute;inset:0;border-radius:16px;pointer-events:none;"></div>
                            </div>
                            <div id="previewStaticFallback">
                                <div id="previewPhotos"></div>
                            </div>
                            <button type="button" id="enablePreviewBtn" class="btn btn-sm btn-outline-secondary mt-2 w-100">
                                <i class="fa-solid fa-video me-1"></i>Allow camera for live preview
                            </button>
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-snapit btn-lg fw-semibold">
                                <i class="fa-solid fa-camera me-2"></i>Start Capturing
                            </button>
                            <a href="<?= e(site_url('booth/index.php')) ?>" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-arrow-left me-1"></i>Back to bookings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" name="preset_id_val" id="preset_id_val" value="<?= (int)($first_preset['preset_id'] ?? 0) ?>">
        <input type="hidden" name="filter_id_val" id="filter_id_val" value="<?= (int)($first_filter['filter_id'] ?? 0) ?>">
        <input type="hidden" name="layout_id_val" id="layout_id_val" value="<?= (int)($first_layout['layout_id'] ?? 0) ?>">
        <input type="hidden" name="design_id_val" id="design_id_val" value="<?= (int)($first_frame['design_id'] ?? 0) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    </form>
</div>

<script>
(function() {
    var LS_PREFIX = 'snapit_preset_v1:';

    function presetKey(name, width) {
        return LS_PREFIX + (width || 240) + ':' + String(name || '');
    }

    function imgToDataURL(img) {
        try {
            var c = document.createElement('canvas');
            c.width = img.naturalWidth || img.width || 240;
            c.height = img.naturalHeight || img.height || 180;
            var ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', 0.88);
        } catch (err) {
            return null;
        }
    }

    window.__presetOnLoad = function(imgEl) {
        if (!imgEl) return;
        try {
            var name = imgEl.getAttribute('data-preset-name');
            var w = imgEl.getAttribute('data-width') || '240';
            if (!name) return;
            setTimeout(function() {
                var du = imgToDataURL(imgEl);
                if (du) {
                    try { localStorage.setItem(presetKey(name, w), du); } catch (e) {}
                }
            }, 200);
            if (window.fetch && typeof imgEl.getAttribute === 'function') {
                setTimeout(function() {
                    try {
                        fetch('<?= e(site_url('booth/ajax_cache_preset.php')) ?>', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'preset_name=' + encodeURIComponent(name) + '&width=' + encodeURIComponent(w) + '&csrf_token=' + encodeURIComponent('<?= e(csrf_token()) ?>')
                        }).catch(function(){});
                    } catch (e) {}
                }, 600);
            }
        } catch (e) {}
    };

    window.__presetOnError = function(imgEl) {
        if (!imgEl) return;
        try {
            var name = imgEl.getAttribute('data-preset-name');
            var w = imgEl.getAttribute('data-width') || '240';
            if (!name) return;
            var cached = null;
            try { cached = localStorage.getItem(presetKey(name, w)); } catch (e) {}
            if (cached && typeof cached === 'string' && cached.indexOf('data:') === 0) {
                imgEl.onerror = null;
                imgEl.src = cached;
                return;
            }
            imgEl.onerror = null;
            var parent = imgEl.parentNode;
            if (parent) {
                var icon = document.createElement('i');
                icon.className = 'fa-solid fa-camera preset-photo-icon';
                imgEl.style.display = 'none';
                parent.appendChild(icon);
            }
        } catch (e) {}
    };

    function updateSelectionCards(radioClass, cardClass) {
        document.querySelectorAll('.' + radioClass).forEach(r => {
            const card = r.closest('label').querySelector('.' + cardClass);
            if (r.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }

    function updateCountdownCards() {
        document.querySelectorAll('.countdown-radio').forEach(r => {
            const card = r.closest('label').querySelector('.card');
            if (!card) return;
            if (r.checked) {
                card.classList.add('border-snapit');
                card.style.background = 'linear-gradient(135deg,#fff9ed,#fff)';
            } else {
                card.classList.remove('border-snapit');
                card.style.background = '';
            }
        });
    }

    function syncHidden() {
        const p = document.querySelector('input[name="preset_id"]:checked');
        const f = document.querySelector('input[name="filter_id"]:checked');
        const L = document.querySelector('input[name="layout_id"]:checked');
        const fr = document.querySelector('input[name="design_id"]:checked');
        if (p) document.getElementById('preset_id_val').value = p.value;
        if (f) document.getElementById('filter_id_val').value = f.value;
        if (L) document.getElementById('layout_id_val').value = L.value;
        if (fr) document.getElementById('design_id_val').value = fr.value;
    }

    function getMirror() {
        return true;
    }

    function applyMirrorOnVideo(videoEl) {
        if (!videoEl) return;
        if (getMirror()) {
            videoEl.style.transform = 'scaleX(-1)';
            videoEl.style.webkitTransform = 'scaleX(-1)';
        } else {
            videoEl.style.transform = '';
            videoEl.style.webkitTransform = '';
        }
    }

    function updateLiveVideoPreview() {
        const videoEl = document.getElementById('previewVideo');
        const fxEl = document.getElementById('previewCamFx');
        if (!videoEl || videoEl.style.display === 'none' && !videoEl.srcObject) return;

        const layoutEl = document.querySelector('input[name="layout_id"]:checked');
        const filterEl = document.querySelector('input[name="filter_id"]:checked');
        const frameEl  = document.querySelector('input[name="design_id"]:checked');
        const presetEl = document.querySelector('input[name="preset_id"]:checked');
        const pd = presetEl ? presetEl.dataset : {};
        const cssFilter = ((pd.filter || '') + ' ' + (filterEl ? filterEl.dataset.css : 'none'));
        const trimmed = (cssFilter || '').trim() || 'none';
        videoEl.style.filter = trimmed;
        videoEl.style.webkitFilter = trimmed;

        applyMirrorOnVideo(videoEl);

        const borderColor = frameEl ? frameEl.dataset.borderColor : '#6f42c1';
        const borderWidth = frameEl ? parseInt(frameEl.dataset.borderWidth) : 6;

        const previewFrame = document.getElementById('previewFrame');
        if (previewFrame) {
            previewFrame.style.border = borderWidth + 'px solid ' + borderColor;
            previewFrame.style.borderRadius = '16px';
            const d = frameEl ? frameEl.dataset : {};
            if (d && d.bg) previewFrame.style.background = d.bg;
        }
        if (fxEl && window.CamFX) {
            fxEl.innerHTML = CamFX.overlay({grain: +pd.grain || 0, vignette: +pd.vig || 0, fade: +pd.fade || 0});
        }
    }

    function renderPreview() {
        const layoutEl = document.querySelector('input[name="layout_id"]:checked');
        const filterEl = document.querySelector('input[name="filter_id"]:checked');
        const frameEl  = document.querySelector('input[name="design_id"]:checked');
        const photoCount = layoutEl ? parseInt(layoutEl.dataset.count) : 1;
        const cols = layoutEl ? parseInt(layoutEl.dataset.cols) : 1;
        const rows = layoutEl ? parseInt(layoutEl.dataset.rows) : 1;
        const presetEl = document.querySelector('input[name="preset_id"]:checked');
        const pd = presetEl ? presetEl.dataset : {};
        const cssFilter = ((pd.filter || '') + ' ' + (filterEl ? filterEl.dataset.css : 'none')).trim();
        const borderColor = frameEl ? frameEl.dataset.borderColor : '#6f42c1';
        const borderWidth = frameEl ? parseInt(frameEl.dataset.borderWidth) : 6;

        const container = document.getElementById('previewPhotos');
        const seed = 'preview-' + Date.now();
        const d = frameEl ? frameEl.dataset : {};
        const photos = []; for (let i = 0; i < photoCount; i++) photos.push('https://picsum.photos/seed/' + seed + '-' + i + '/600/400');
        container.style.position = 'relative';
        container.innerHTML = SnapStrip.html(photos, {cols: cols, rows: rows, count: photoCount, print: layoutEl ? layoutEl.dataset.print : '',
            bc: borderColor, bw: borderWidth, bg: d.bg || '#fff', fg: d.fg || '#6f42c1', gap: +d.gap || 10, rad: +d.rad || 8,
               pattern: d.pattern || '', filter: cssFilter, caption: 'Snap It! · ' + new Date().toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})})
            + '<div style="position:absolute;inset:0;pointer-events:none;">' + CamFX.overlay({grain: +pd.grain || 0, vignette: +pd.vig || 0, fade: +pd.fade || 0}) + '</div>';

        updateLiveVideoPreview();
    }

    function markSteps() {
        const steps = [1,2,3,4];
        steps.forEach(s => {
            const el = document.querySelector('.step-indicator .step[data-step="' + s + '"]');
            if (!el) return;
            el.classList.remove('active','done');
        });
        const p = document.querySelector('input[name="preset_id"]:checked');
        const f = document.querySelector('input[name="filter_id"]:checked');
        const L = document.querySelector('input[name="layout_id"]:checked');
        const fr = document.querySelector('input[name="design_id"]:checked');
        if (p) document.querySelector('.step-indicator .step[data-step="1"]').classList.add('done');
        if (p && f) document.querySelector('.step-indicator .step[data-step="2"]').classList.add('done');
        if (p && f && L) document.querySelector('.step-indicator .step[data-step="3"]').classList.add('done');
        if (p && f && L && fr) {
            document.querySelector('.step-indicator .step[data-step="4"]').classList.add('done');
        } else {
            let active = 1;
            if (!p) active = 1;
            else if (!f) active = 2;
            else if (!L) active = 3;
            else active = 4;
            document.querySelector('.step-indicator .step[data-step="' + active + '"]').classList.add('active');
        }
    }

    function refreshAll() {
        updateSelectionCards('preset-radio', 'preset-card');
        updateSelectionCards('filter-radio', 'filter-card');
        updateSelectionCards('layout-radio', 'layout-card');
        updateSelectionCards('frame-radio', 'frame-card');
        updateCountdownCards();
        syncHidden();
        renderPreview();
        markSteps();
    }

    let liveMediaStream = null;
    function startLivePreview(cb) {
        const wrap = document.getElementById('previewVideoWrap');
        const videoEl = document.getElementById('previewVideo');
        const fallback = document.getElementById('previewStaticFallback');
        const btn = document.getElementById('enablePreviewBtn');
        if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
            if (btn) btn.style.display = 'none';
            if (cb) cb(false);
            return;
        }
        navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 1280}, height: {ideal: 720}}, audio: false})
            .then(function(stream) {
                liveMediaStream = stream;
                try { videoEl.srcObject = stream; } catch(err) { videoEl.src = window.URL.createObjectURL(stream); }
                videoEl.setAttribute('playsinline', '');
                videoEl.play().catch(function(){});
                if (wrap) wrap.style.display = '';
                if (fallback) fallback.style.display = 'none';
                if (btn) btn.style.display = 'none';
                updateLiveVideoPreview();
                if (cb) cb(true);
            }).catch(function() {
                if (btn) btn.style.display = '';
                if (cb) cb(false);
            });
    }

    document.addEventListener('change', function(e) {
        if (e.target.matches('.preset-radio,.filter-radio,.layout-radio,.frame-radio')) {
            refreshAll();
        } else if (e.target.matches('.countdown-radio')) {
            updateCountdownCards();
        } else if (e.target && e.target.id === 'mirror_preview') {
            const pv = document.getElementById('previewVideo');
            applyMirrorOnVideo(pv);
            const cv = document.getElementById('cameraVideo');
            applyMirrorOnVideo(cv);
        }
    });

    const enableBtn = document.getElementById('enablePreviewBtn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function() { startLivePreview(); });
    }

    document.getElementById('selectionForm').addEventListener('submit', function(e) {
        syncHidden();
        const p = document.getElementById('preset_id_val').value;
        const f = document.getElementById('filter_id_val').value;
        const L = document.getElementById('layout_id_val').value;
        const fr = document.getElementById('design_id_val').value;
        if (!p || !f || !L || !fr) {
            e.preventDefault();
            alert('Please select one option in each category.');
        }
    });

    refreshAll();
    startLivePreview();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
