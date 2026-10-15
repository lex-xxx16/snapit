<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/config.php';
require_login();

$preselected_package = isset($_GET['package']) ? (int)$_GET['package'] : 0;

$pkg_stmt = mysqli_prepare($conn, "SELECT * FROM packages WHERE is_active = 1 AND package_type = 'event' ORDER BY base_price ASC");
mysqli_stmt_execute($pkg_stmt);
$pkg_result = mysqli_stmt_get_result($pkg_stmt);

$packages = [];
while ($p = mysqli_fetch_assoc($pkg_result)) {
    $packages[] = $p;
}

$duration_options = [6, 7, 8, 9, 10, 11, 12];

$tomorrow = date('Y-m-d', strtotime('+1 day'));

$selected_pkg = null;
if ($preselected_package > 0) {
    foreach ($packages as $p) {
        if ($p['package_id'] == $preselected_package) {
            $selected_pkg = $p;
            break;
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/alert.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">
                    Reserve Your Event
                </h2>
                <a href="<?= e(site_url('booking/index.php')) ?>" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back
                </a>
            </div>

            <form method="POST" action="<?= e(site_url('booking/store.php')) ?>" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-box me-2"></i>Package Details</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Select Package <span class="text-danger">*</span></label>
                                <select name="package_id" id="package_id" class="form-select form-select-lg" required>
                                    <option value="">-- Choose a package --</option>
                                    <?php foreach ($packages as $p): ?>
                                        <option value="<?= (int)$p['package_id'] ?>"
                                            <?= ($selected_pkg && $selected_pkg['package_id'] == $p['package_id']) ? 'selected' : '' ?>
                                            data-duration="<?= (int)$p['duration_hours'] ?>"
                                            data-softcopies="<?= (int)$p['softcopy_count'] ?>"
                                            data-hardcopies="<?= (int)$p['hardcopy_count'] ?>"
                                            data-price="<?= e($p['base_price']) ?>"
                                            data-has-addon="<?= (int)$p['has_softcopy_addon'] ?>"
                                            data-addon-price="<?= e($p['softcopy_addon_price']) ?>">
                                            <?= e($p['name']) ?> &mdash;
                                            <?= (int)$p['duration_hours'] ?>h &bull;
                                            <?= (int)$p['softcopy_count'] ?> soft / <?= (int)$p['hardcopy_count'] ?> hard &bull;
                                            <?= e(format_money($p['base_price'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text" id="package_description"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-regular fa-calendar me-2"></i>Event Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Event Name <span class="text-danger">*</span></label>
                                <input type="text" name="event_name" class="form-control" required
                                       maxlength="200" placeholder="e.g. Juan &amp; Maria's Wedding">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Event Date <span class="text-danger">*</span></label>
                                <input type="date" name="event_date" id="event_date" class="form-control"
                                       min="<?= e($tomorrow) ?>" required>
                                <div class="form-text">Bookings must be made at least 1 day in advance.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
                                <select name="start_time" id="start_time" class="form-select" required>
<option value="">Select hour</option>
<option value="08:00">8:00 AM</option><option value="09:00">9:00 AM</option><option value="10:00">10:00 AM</option><option value="11:00">11:00 AM</option><option value="12:00">12:00 PM</option><option value="13:00">1:00 PM</option><option value="14:00">2:00 PM</option><option value="15:00">3:00 PM</option><option value="16:00">4:00 PM</option><option value="17:00">5:00 PM</option><option value="18:00">6:00 PM</option><option value="19:00">7:00 PM</option><option value="20:00">8:00 PM</option><option value="21:00">9:00 PM</option>
</select>
                                <div class="form-text">Pick the starting hour. Business hours: 8:00 AM - 10:00 PM</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Duration (set by package)</label>
                                <input type="hidden" name="duration_hours" id="duration_hours" value="<?= $selected_pkg ? (int)$selected_pkg['duration_hours'] : 0 ?>">
                                <input type="text" id="duration_display" class="form-control" readonly
                                       value="<?= $selected_pkg ? (int)$selected_pkg['duration_hours'] . ' hours' : 'Select a package first' ?>">
                                <div class="form-text" id="duration_hint"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Venue / Address <span class="text-danger">*</span></label>
                                <textarea name="venue" class="form-control" rows="3" required
                                          placeholder="Full venue address, including landmark if any..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-copy me-2"></i>Copies &amp; Add-ons</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Estimated Soft Copies</label>
                                <input type="number" name="estimated_softcopies" id="estimated_softcopies"
                                       class="form-control" min="0"
                                       value="<?= $selected_pkg ? (int)$selected_pkg['softcopy_count'] : 0 ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Estimated Hard Copies</label>
                                <input type="number" name="estimated_hardcopies" id="estimated_hardcopies"
                                       class="form-control" min="0"
                                       value="<?= $selected_pkg ? (int)$selected_pkg['hardcopy_count'] : 0 ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end" id="addon_wrap" style="display:none !important;">
                                <div class="form-check form-switch fs-6">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="softcopy_addon" name="softcopy_addon" value="1">
                                    <label class="form-check-label fw-semibold" for="softcopy_addon">
                                        Unlimited Softcopy Add-on
                                    </label>
                                    <div class="form-text" id="addon_price_text"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-truck me-2"></i>Hardcopy Delivery</h5>
                        <span class="badge bg-light text-secondary small">Optional</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Recipient Name</label>
                                <input type="text" name="hardcopy_delivery_name" class="form-control" maxlength="150"
                                       placeholder="Name of person who will receive hard copies">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="hardcopy_delivery_contact" class="form-control" maxlength="30"
                                       placeholder="Mobile or landline">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Delivery Address</label>
                                <textarea name="hardcopy_delivery_address" class="form-control" rows="2"
                                          placeholder="Full delivery address for hard copies"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-note-sticky me-2"></i>Notes</h5>
                    </div>
                    <div class="card-body p-4">
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Special requests, preferred themes, additional instructions..."></textarea>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-receipt me-2"></i>Price Summary</h5>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td>Package Base Price</td>
                                    <td class="text-end fw-semibold" id="summary_package">PHP 0.00</td>
                                </tr>
                                <tr>
                                    <td>Softcopy Add-on</td>
                                    <td class="text-end fw-semibold" id="summary_addon">PHP 0.00</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="fw-bold fs-5">Total Amount</td>
                                    <td class="text-end fw-bold fs-5" style="color:var(--snapit-gold)" id="summary_total">PHP 0.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex gap-3 justify-content-end">
                    <a href="<?= e(site_url('booking/index.php')) ?>" class="btn btn-outline-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-snapit px-5 fw-semibold">
                        <i class="fa-regular fa-calendar-check me-2"></i>Submit Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const pkgSel = document.getElementById('package_id');
    const softEl = document.getElementById('estimated_softcopies');
    const hardEl = document.getElementById('estimated_hardcopies');
    const durSel = document.getElementById('duration_hours');
    const durHint = document.getElementById('duration_hint');
    const addonWrap = document.getElementById('addon_wrap');
    const addonChk = document.getElementById('softcopy_addon');
    const addonPriceText = document.getElementById('addon_price_text');
    const pkgDesc = document.getElementById('package_description');
    const sumPkg = document.getElementById('summary_package');
    const sumAddon = document.getElementById('summary_addon');
    const sumTotal = document.getElementById('summary_total');
    const fmt = v => 'PHP ' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function disableLateHours(dur) {
        const st = document.getElementById('start_time');
        for (const o of st.options) {
            if (!o.value) continue;
            const h = parseInt(o.value.slice(0, 2), 10);
            o.disabled = dur > 0 && (h + dur > 22);
        }
        if (st.selectedOptions[0] && st.selectedOptions[0].disabled) st.value = '';
    }

    function update() {
        const opt = pkgSel.options[pkgSel.selectedIndex];
        if (!opt || !opt.value) {
            addonWrap.style.display = 'none';
            addonChk.checked = false;
            softEl.value = 0;
            hardEl.value = 0;
            durHint.textContent = '';
            durSel.value = '0';
            document.getElementById('duration_display').value = 'Select a package first';
            pkgDesc.textContent = '';
            sumPkg.textContent = fmt(0);
            sumAddon.textContent = fmt(0);
            sumTotal.textContent = fmt(0);
            return;
        }
        const minDur = parseInt(opt.dataset.duration || '0', 10);
        const soft = parseInt(opt.dataset.softcopies || '0', 10);
        const hard = parseInt(opt.dataset.hardcopies || '0', 10);
        const price = parseFloat(opt.dataset.price || '0');
        const hasAddon = parseInt(opt.dataset.hasAddon || '0', 10) === 1;
        const addonPrice = parseFloat(opt.dataset.addonPrice || '0');

        durSel.value = String(minDur);
        disableLateHours(minDur);
        document.getElementById('duration_display').value = minDur + ' hours';
        durHint.textContent = 'Fixed by the package you selected.';
        softEl.value = soft;
        hardEl.value = hard;
        pkgDesc.textContent = 'Package includes ' + soft + ' soft copies and ' + hard + ' hard copies.';

        if (hasAddon && soft === 0) {
            addonWrap.style.display = '';
            addonPriceText.textContent = '+ ' + fmt(addonPrice) + ' for unlimited soft copies';
        } else {
            addonWrap.style.display = 'none';
            addonChk.checked = false;
        }
        recalc();
    }

    function recalc() {
        const opt = pkgSel.options[pkgSel.selectedIndex];
        const base = opt && opt.value ? parseFloat(opt.dataset.price || '0') : 0;
        const addonPrice = opt && opt.value ? parseFloat(opt.dataset.addonPrice || '0') : 0;
        const addonAmt = addonChk.checked ? addonPrice : 0;
        sumPkg.textContent = fmt(base);
        sumAddon.textContent = fmt(addonAmt);
        sumTotal.textContent = fmt(base + addonAmt);
    }

    pkgSel.addEventListener('change', update);
    addonChk.addEventListener('change', recalc);
    update();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
