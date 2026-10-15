<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/config.php';

$packages_stmt = mysqli_prepare($conn, "SELECT * FROM packages WHERE is_active = 1 AND package_type = 'event' ORDER BY base_price ASC");
mysqli_stmt_execute($packages_stmt);
$packages = mysqli_stmt_get_result($packages_stmt);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/alert.php';
?>

<section class="hero-lux">
    <div class="hero-light" aria-hidden="true"></div>
    <div class="hero-curtain" aria-hidden="true"></div>
    <div class="hero-floor" aria-hidden="true"></div>

    <div class="hero-copy">
        <h1>We are artisans<br>elevating moments</h1>
        <p class="hero-sub">Capture. Customize. Celebrate. An all-in-one photo customization booth and rental experience, tailored to make every event unforgettable.</p>
        <div class="hero-actions">
        <?php if (!is_loggedin()): ?>
            <a href="<?= e(site_url('booking/create.php')) ?>" class="btn btn-snapit">Book us now!</a>
            <a href="<?= e(site_url('users/register.php')) ?>" class="btn btn-ghost">Create Account</a>
        <?php else: ?>
            <a href="<?= e(site_url('booking/create.php')) ?>" class="btn btn-snapit">Book us now!</a>
            <a href="<?= e(site_url('walkin/index.php')) ?>" class="btn btn-ghost">Walk-in Photo</a>
        <?php endif; ?>
        </div>
    </div>

    <div class="hero-art">
<svg viewBox="0 0 520 330" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="A vintage camera with photo strips of smiling guests">
<defs>
 <linearGradient id="paper" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fffaf0"/><stop offset="1" stop-color="#e3d6bf"/></linearGradient>
 <radialGradient id="fglow" cx=".5" cy=".3" r=".8"><stop offset="0" stop-color="#fff" stop-opacity=".22"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
 <clipPath id="fclip"><rect width="72" height="56" rx="5"/></clipPath>
 <linearGradient id="cap" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fbf5e9"/><stop offset=".55" stop-color="#e6d9c0"/><stop offset="1" stop-color="#c9b894"/></linearGradient>
 <linearGradient id="leather" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a3322"/><stop offset="1" stop-color="#22170e"/></linearGradient>
 <linearGradient id="metal" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f1e3c4"/><stop offset=".5" stop-color="#a88a5a"/><stop offset="1" stop-color="#5a432a"/></linearGradient>
 <radialGradient id="glass" cx=".36" cy=".32" r=".85"><stop offset="0" stop-color="#6d5a8a"/><stop offset=".25" stop-color="#2a2236"/><stop offset=".7" stop-color="#0d0a12"/><stop offset="1" stop-color="#050307"/></radialGradient>
 <radialGradient id="flash" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#fff6d8"/><stop offset=".6" stop-color="#e8cf8f"/><stop offset="1" stop-color="#b8924a"/></radialGradient>
 <radialGradient id="halo" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#ffe9b0" stop-opacity=".55"/><stop offset="1" stop-color="#ffe9b0" stop-opacity="0"/></radialGradient>
 <filter id="soft" x="-20%" y="-300%" width="140%" height="700%"><feGaussianBlur stdDeviation="8"/></filter>
</defs>
<ellipse cx="260" cy="314" rx="236" ry="13" fill="#000" opacity=".55" filter="url(#soft)"/>

<!-- left strip -->
<g transform="translate(46 58) rotate(-8 44 256)"><rect width="88" height="256" rx="7" fill="url(#paper)"/><rect width="88" height="256" rx="7" fill="none" stroke="#fff" stroke-opacity=".5"/><g transform="translate(8 8)"><rect width="72" height="56" rx="5" fill="#4a3523"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(-5 23 34)"><path d="M6 62 q0 -17 17 -17 q17 0 17 17Z" fill="#e8cf8f"/><circle cx="23" cy="31" r="11.5" fill="#f0cfae"/><path d="M11 29 q1 -14 12 -14 q11 0 12 14 q-6 -7 -12 -7 q-7 0 -12 7Z" fill="#2b1d12"/><path d="M17 31 q2 -2.4 4 0 M25 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M18 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="15" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="31" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><g transform="rotate(6 50 34)"><path d="M33 62 q0 -17 17 -17 q17 0 17 17Z" fill="#f3ece0"/><circle cx="50" cy="31" r="11.5" fill="#d9a981"/><path d="M37.5 32 q-2 -18 12.5 -17.5 q14.5 -0.5 12.5 17.5 q-3 -9 -12.5 -9 q-9.5 0 -12.5 9Z" fill="#1b120b"/><rect x="37" y="30" width="5" height="14" rx="2.5" fill="#1b120b"/><rect x="58" y="30" width="5" height="14" rx="2.5" fill="#1b120b"/><path d="M44 31 q2 -2.4 4 0 M52 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M45 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="42" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="58" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g></g></g><g transform="translate(8 70)"><rect width="72" height="56" rx="5" fill="#6a4a2e"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(0 36 34)"><path d="M19 62 q0 -17 17 -17 q17 0 17 17Z" fill="#c9a96b"/><circle cx="36" cy="31" r="11.5" fill="#b9805a"/><path d="M24.5 27 q0 -13 11.5 -13 q11.5 0 11.5 13 q-5 -4 -11.5 -4 q-6.5 0 -11.5 4Z" fill="#5a3a22"/><path d="M30 31 q2 -2.4 4 0 M38 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M31 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="28" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="44" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><path d="M52 20 l2 5 5 .6 -3.8 3.4 1.2 5 -4.4 -2.7 -4.4 2.7 1.2 -5 -3.8 -3.4 5 -.6Z" fill="#e8cf8f" transform="scale(.55) translate(40 -4)"/></g></g><g transform="translate(8 132)"><rect width="72" height="56" rx="5" fill="#3a2a1c"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(-6 21 34)"><path d="M4 62 q0 -17 17 -17 q17 0 17 17Z" fill="#8c6b45"/><circle cx="21" cy="31" r="11.5" fill="#e7bd97"/><path d="M8.5 32 q-2 -18 12.5 -17.5 q14.5 -0.5 12.5 17.5 q-3 -9 -12.5 -9 q-9.5 0 -12.5 9Z" fill="#8a5a2e"/><rect x="8" y="30" width="5" height="14" rx="2.5" fill="#8a5a2e"/><rect x="29" y="30" width="5" height="14" rx="2.5" fill="#8a5a2e"/><path d="M15 31 q2 -2.4 4 0 M23 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M16 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="13" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="29" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><g transform="rotate(4 46 34)"><path d="M29 62 q0 -17 17 -17 q17 0 17 17Z" fill="#e8cf8f"/><circle cx="46" cy="31" r="11.5" fill="#f0cfae"/><path d="M34 29 q1 -14 12 -14 q11 0 12 14 q-6 -7 -12 -7 q-7 0 -12 7Z" fill="#5a3a22"/><path d="M40 31 q2 -2.4 4 0 M48 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M41 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="38" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="54" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><path d="M62 12 c-2 -3 -6 -1 -4 2 l4 4 4 -4 c2 -3 -2 -5 -4 -2Z" fill="#e58a7b"/></g></g><g transform="translate(8 194)"><rect width="72" height="56" rx="5" fill="#7a5736"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(-5 23 34)"><path d="M6 62 q0 -17 17 -17 q17 0 17 17Z" fill="#e8cf8f"/><circle cx="23" cy="31" r="11.5" fill="#f0cfae"/><path d="M11 29 q1 -14 12 -14 q11 0 12 14 q-6 -7 -12 -7 q-7 0 -12 7Z" fill="#2b1d12"/><path d="M17 31 q2 -2.4 4 0 M25 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M18 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="15" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="31" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><g transform="rotate(6 50 34)"><path d="M33 62 q0 -17 17 -17 q17 0 17 17Z" fill="#f3ece0"/><circle cx="50" cy="31" r="11.5" fill="#d9a981"/><path d="M37.5 32 q-2 -18 12.5 -17.5 q14.5 -0.5 12.5 17.5 q-3 -9 -12.5 -9 q-9.5 0 -12.5 9Z" fill="#1b120b"/><rect x="37" y="30" width="5" height="14" rx="2.5" fill="#1b120b"/><rect x="58" y="30" width="5" height="14" rx="2.5" fill="#1b120b"/><path d="M44 31 q2 -2.4 4 0 M52 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M45 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="42" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="58" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g></g></g><text x="44" y="253" text-anchor="middle" font-family="Inter Tight, Arial, sans-serif" font-size="6" letter-spacing="2" fill="#8c6b45">SNAP IT</text></g>
<!-- right strip -->
<g transform="translate(386 120) rotate(7 44 194)"><rect width="88" height="194" rx="7" fill="url(#paper)"/><rect width="88" height="194" rx="7" fill="none" stroke="#fff" stroke-opacity=".5"/><g transform="translate(8 8)"><rect width="72" height="56" rx="5" fill="#4a3523"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(-5 23 34)"><path d="M6 62 q0 -17 17 -17 q17 0 17 17Z" fill="#e8cf8f"/><circle cx="23" cy="31" r="11.5" fill="#f0cfae"/><path d="M11 29 q1 -14 12 -14 q11 0 12 14 q-6 -7 -12 -7 q-7 0 -12 7Z" fill="#2b1d12"/><path d="M17 31 q2 -2.4 4 0 M25 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M18 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="15" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="31" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><g transform="rotate(6 50 34)"><path d="M33 62 q0 -17 17 -17 q17 0 17 17Z" fill="#f3ece0"/><circle cx="50" cy="31" r="11.5" fill="#d9a981"/><path d="M37.5 32 q-2 -18 12.5 -17.5 q14.5 -0.5 12.5 17.5 q-3 -9 -12.5 -9 q-9.5 0 -12.5 9Z" fill="#1b120b"/><rect x="37" y="30" width="5" height="14" rx="2.5" fill="#1b120b"/><rect x="58" y="30" width="5" height="14" rx="2.5" fill="#1b120b"/><path d="M44 31 q2 -2.4 4 0 M52 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M45 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="42" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="58" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g></g></g><g transform="translate(8 70)"><rect width="72" height="56" rx="5" fill="#6a4a2e"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(0 36 34)"><path d="M19 62 q0 -17 17 -17 q17 0 17 17Z" fill="#c9a96b"/><circle cx="36" cy="31" r="11.5" fill="#b9805a"/><path d="M24.5 27 q0 -13 11.5 -13 q11.5 0 11.5 13 q-5 -4 -11.5 -4 q-6.5 0 -11.5 4Z" fill="#5a3a22"/><path d="M30 31 q2 -2.4 4 0 M38 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M31 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="28" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="44" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><path d="M52 20 l2 5 5 .6 -3.8 3.4 1.2 5 -4.4 -2.7 -4.4 2.7 1.2 -5 -3.8 -3.4 5 -.6Z" fill="#e8cf8f" transform="scale(.55) translate(40 -4)"/></g></g><g transform="translate(8 132)"><rect width="72" height="56" rx="5" fill="#3a2a1c"/><g clip-path="url(#fclip)"><rect width="72" height="56" fill="url(#fglow)"/><g transform="rotate(-6 21 34)"><path d="M4 62 q0 -17 17 -17 q17 0 17 17Z" fill="#8c6b45"/><circle cx="21" cy="31" r="11.5" fill="#e7bd97"/><path d="M8.5 32 q-2 -18 12.5 -17.5 q14.5 -0.5 12.5 17.5 q-3 -9 -12.5 -9 q-9.5 0 -12.5 9Z" fill="#8a5a2e"/><rect x="8" y="30" width="5" height="14" rx="2.5" fill="#8a5a2e"/><rect x="29" y="30" width="5" height="14" rx="2.5" fill="#8a5a2e"/><path d="M15 31 q2 -2.4 4 0 M23 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M16 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="13" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="29" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><g transform="rotate(4 46 34)"><path d="M29 62 q0 -17 17 -17 q17 0 17 17Z" fill="#e8cf8f"/><circle cx="46" cy="31" r="11.5" fill="#f0cfae"/><path d="M34 29 q1 -14 12 -14 q11 0 12 14 q-6 -7 -12 -7 q-7 0 -12 7Z" fill="#5a3a22"/><path d="M40 31 q2 -2.4 4 0 M48 31 q2 -2.4 4 0" stroke="#2b1d12" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M41 36 q5 5.5 10 0" stroke="#7a2f2a" stroke-width="1.7" fill="#fff" stroke-linejoin="round"/><circle cx="38" cy="35" r="2" fill="#e58a7b" opacity=".45"/><circle cx="54" cy="35" r="2" fill="#e58a7b" opacity=".45"/></g><path d="M62 12 c-2 -3 -6 -1 -4 2 l4 4 4 -4 c2 -3 -2 -5 -4 -2Z" fill="#e58a7b"/></g></g><text x="44" y="191" text-anchor="middle" font-family="Inter Tight, Arial, sans-serif" font-size="6" letter-spacing="2" fill="#8c6b45">SNAP IT</text></g>

<!-- flash glow -->
<circle cx="326" cy="132" r="54" fill="url(#halo)"/>

<!-- camera body -->
<g>
 <rect x="150" y="150" width="220" height="148" rx="24" fill="url(#leather)"/>
 <path d="M174 150h172a24 24 0 0 1 24 24v26H150v-26a24 24 0 0 1 24-24Z" fill="url(#cap)"/>
 <rect x="150" y="196" width="220" height="5" fill="#8c6b45" opacity=".55"/>
 <!-- leather texture dots -->
 <g fill="#000" opacity=".18"><circle cx="172" cy="238" r="1.6"/><circle cx="184" cy="252" r="1.6"/><circle cx="172" cy="266" r="1.6"/><circle cx="184" cy="280" r="1.6"/><circle cx="348" cy="238" r="1.6"/><circle cx="336" cy="252" r="1.6"/><circle cx="348" cy="266" r="1.6"/><circle cx="336" cy="280" r="1.6"/></g>
 <!-- viewfinder -->
 <rect x="172" y="128" width="54" height="30" rx="7" fill="url(#leather)"/>
 <rect x="178" y="133" width="42" height="19" rx="4" fill="#0f0a07"/>
 <rect x="181" y="136" width="14" height="5" rx="2.5" fill="#fff" opacity=".28"/>
 <!-- flash -->
 <rect x="294" y="116" width="64" height="40" rx="9" fill="url(#metal)"/>
 <rect x="300" y="122" width="52" height="28" rx="6" fill="url(#flash)"/>
 <path d="M309 124v24M318 124v24M327 124v24M336 124v24M345 124v24" stroke="#b8924a" stroke-width="1" opacity=".5"/>
 <!-- shutter -->
 <circle cx="248" cy="143" r="9" fill="url(#metal)"/><circle cx="248" cy="141.5" r="6" fill="#f1e3c4"/>
 <!-- dials -->
 <rect x="162" y="160" width="26" height="9" rx="4.5" fill="#bfae8a"/>
 <circle cx="352" cy="176" r="5" fill="#8c6b45"/>
 <!-- lens -->
 <circle cx="260" cy="236" r="70" fill="#120c07"/>
 <circle cx="260" cy="236" r="66" fill="url(#metal)"/>
 <circle cx="260" cy="236" r="57" fill="#1a120c"/>
 <circle cx="260" cy="236" r="52" fill="none" stroke="#d8c09a" stroke-opacity=".55" stroke-width="1.5"/>
 <circle cx="260" cy="236" r="46" fill="url(#glass)"/>
 <circle cx="260" cy="236" r="28" fill="none" stroke="#4a3d63" stroke-width="2"/>
 <circle cx="260" cy="236" r="14" fill="#07050a"/>
 <ellipse cx="240" cy="217" rx="13" ry="8" fill="#fff" opacity=".42" transform="rotate(-35 240 217)"/>
 <circle cx="282" cy="256" r="4" fill="#fff" opacity=".2"/>
 <!-- lens ticks -->
 <g stroke="#d8c09a" stroke-opacity=".7" stroke-width="1.6" stroke-linecap="round">
  <path d="M260 175v6M260 291v6M199 236h6M315 236h6M217 193l4 4M303 279l4 4M303 193l-4 4M217 279l4-4"/>
 </g>
</g>

<!-- sparkles -->
<g fill="#e8cf8f">
 <path d="M122 96l3 9 9 3-9 3-3 9-3-9-9-3 9-3Z"/>
 <path d="M410 88l2.4 7 7 2.4-7 2.4-2.4 7-2.4-7-7-2.4 7-2.4Z" opacity=".85"/>
 <path d="M376 112l1.6 4.4 4.4 1.6-4.4 1.6-1.6 4.4-1.6-4.4-4.4-1.6 4.4-1.6Z" opacity=".7"/>
 <path d="M200 92l1.6 4.4 4.4 1.6-4.4 1.6-1.6 4.4-1.6-4.4-4.4-1.6 4.4-1.6Z" opacity=".6"/>
</g>
</svg>
    </div>

    <div class="hero-stats">
        <div><div class="stat-num">10</div><div class="stat-label">Recipients per Delivery</div></div>
        <div><div class="stat-num">PNG</div><div class="stat-label">Instant Downloads</div></div>
        <div><div class="stat-num">0</div><div class="stat-label">Double Bookings</div></div>
    </div>
</section>

<section class="container section-lux" id="experience">
    <div class="section-title">
        <span class="eyebrow">The Experience</span>
        <h2>Crafted down to every frame</h2>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card feature-card h-100">
                <span class="feature-no">01</span>
                <i class="fa-solid fa-camera feature-icon"></i>
                <h5 class="mb-3">The Live Capture Suite</h5>
                <p class="text-muted mb-0">Filters, presets, layouts &mdash; your guests shoot, retake, and keep every shot with a graceful countdown.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card h-100">
                <span class="feature-no">02</span>
                <i class="fa-regular fa-calendar-check feature-icon"></i>
                <h5 class="mb-3">Seamless Reservations</h5>
                <p class="text-muted mb-0">Prevent double-bookings with conflict checks and choose the package that fits the scale of your event.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card feature-card h-100">
                <span class="feature-no">03</span>
                <i class="fa-solid fa-envelopes-bulk feature-icon"></i>
                <h5 class="mb-3">Instant Photo Delivery</h5>
                <p class="text-muted mb-0">Email up to 10 recipients with the finished photo set, or download directly as PNG.</p>
            </div>
        </div>
    </div>
</section>

<section class="container section-lux" id="packages">
    <div class="section-title">
        <span class="eyebrow">Our Packages</span>
        <h2>Choose your collection</h2>
    </div>
    <div class="row g-4">
        <?php while ($pkg = mysqli_fetch_assoc($packages)): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card card-package h-100">
                    <div class="card-header py-3 text-center">
                        <h4 class="mb-0"><?= e($pkg['name']) ?></h4>
                        <div class="small text-muted mt-2"><?= (int)$pkg['duration_hours'] ?> hours &bull;
                            <?= (int)$pkg['softcopy_count'] ?> soft / <?= (int)$pkg['hardcopy_count'] ?> hard copies
                        </div>
                    </div>
                    <div class="card-body p-4 pt-3">
                        <p class="text-muted small mb-3"><?= e($pkg['description'] ?? '') ?></p>
                        <div class="mb-3">
                            <span class="badge badge-snapit me-2"><i class="fa-regular fa-file-image me-1"></i><?= (int)$pkg['softcopy_count'] ?> Soft Copies</span>
                            <span class="badge bg-secondary"><i class="fa-solid fa-print me-1"></i><?= (int)$pkg['hardcopy_count'] ?> Hard Copies</span>
                        </div>
                        <ul class="list-unstyled mb-4 small">
                            <li class="mb-2"><i class="fa-solid fa-check me-2 text-primary"></i>Live countdown capture</li>
                            <li class="mb-2"><i class="fa-solid fa-check me-2 text-primary"></i>Filters, layouts &amp; frame designs</li>
                            <li class="mb-2"><i class="fa-solid fa-check me-2 text-primary"></i>Conflict-free date booking</li>
                            <?php if ($pkg['has_softcopy_addon']): ?>
                                <li class="mb-2"><i class="fa-solid fa-plus me-2 text-primary"></i>Softcopy add-on available</li>
                            <?php endif; ?>
                        </ul>
                        <div class="d-flex align-items-end justify-content-between pt-3 border-top">
                            <div>
                                <div class="small text-muted">Starting at</div>
                                <div class="price-tag"><?= e(format_money($pkg['base_price'])) ?></div>
                                <?php if ($pkg['has_softcopy_addon'] && $pkg['softcopy_addon_price'] > 0): ?>
                                    <div class="small text-muted mt-1">+ <?= e(format_money($pkg['softcopy_addon_price'])) ?> softcopy add-on</div>
                                <?php endif; ?>
                            </div>
                            <a href="<?= e(site_url('booking/create.php?package=' . (int)$pkg['package_id'])) ?>" class="btn btn-snapit">Book Now</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<?php
$cfg_path = __DIR__ . '/includes/config.php';
$db_ready = false;
if (file_exists($cfg_path)) {
    $cfg_include = @include $cfg_path;
    if (isset($conn) && $conn instanceof mysqli && !mysqli_connect_error()) {
        $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'packages'");
        if ($tbl && mysqli_num_rows($tbl) > 0) {
            $db_ready = true;
        }
    }
}
?>

<section class="container section-lux pb-0">
    <div class="card cta-panel">
        <span class="eyebrow mb-3">Begin</span>
        <h3>Ready to elevate your event?</h3>
        <p>From debuts to corporate launches, we have a collection for every celebration.</p>
        <div>
        <?php if (!$db_ready): ?>
            <a href="<?= e(site_url('install.php')) ?>" class="btn btn-snapit btn-lg px-5">
                Run the Installer to get started
            </a>
        <?php else: ?>
            <?php if (!is_loggedin()): ?>
                <a href="<?= e(site_url('users/register.php')) ?>" class="btn btn-snapit btn-lg px-5 me-2">
                    Create Your Account
                </a>
                <a href="<?= e(site_url('booking/create.php')) ?>" class="btn btn-ghost btn-lg px-5">
                    Book Now
                </a>
            <?php else: ?>
                <a href="<?= e(site_url('booking/create.php')) ?>" class="btn btn-snapit btn-lg px-5">
                    Book Your Next Event
                </a>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
