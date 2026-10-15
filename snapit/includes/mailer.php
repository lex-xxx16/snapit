<?php
if (!defined('SNAPIT_ROOT')) define('SNAPIT_ROOT', dirname(__DIR__));
require_once __DIR__ . '/config.php';

if (file_exists(SNAPIT_ROOT . '/vendor/autoload.php')) {
    require_once SNAPIT_ROOT . '/vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function snapit_mailer_enabled() {
    return class_exists(PHPMailer::class);
}

function snapit_current_mailer_cfg() {
    global $MAILER;
    $service = $MAILER['service'] ?? 'mailtrap';
    if (!isset($MAILER[$service])) {
        $service = 'mailtrap';
    }
    return [
        'service_key' => $service,
        'global'      => $MAILER,
        'svc'         => $MAILER[$service],
    ];
}

function snapit_build_full_url($relative = '') {
    if (strpos($relative, 'http') === 0) return $relative;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST']   ?? 'localhost';
    $base   = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/snapit/index.php')), '/\\');
    if ($base === '.' || $base === '\\') $base = '';
    if (strpos($relative, '/') !== 0) $relative = '/' . $relative;
    return $scheme . '://' . $host . $base . $relative;
}

function snapit_smtp_debug_callback($level, $message) {
    static $buffer = [];
    $buffer[] = '[' . $level . '] ' . rtrim($message);
    return $buffer;
}

function snapit_configure_smtp(PHPMailer $mail, $overrideService = null) {
    $cfg = snapit_current_mailer_cfg();
    $global = $cfg['global'];
    $svc    = $cfg['svc'];
    if ($overrideService && isset($global[$overrideService])) {
        $svc = $global[$overrideService];
        $cfg['service_key'] = $overrideService;
    }
    $mail->isSMTP();
    $mail->Host       = $svc['host'];
    $mail->Port       = (int)$svc['port'];
    $mail->SMTPAuth   = (bool)($svc['smtp_auth']   ?? true);
    $mail->Username   = $svc['username'];
    $mail->Password   = $svc['password'];
    if (!empty($svc['smtp_secure']) && $svc['smtp_secure'] !== 'none') {
        if (version_compare(PHP_VERSION, '5.5', '>=') && defined('PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS')) {
            if (strcasecmp($svc['smtp_secure'], 'ssl') === 0) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mail->SMTPSecure = strcasecmp($svc['smtp_secure'], 'ssl') === 0 ? 'ssl' : 'tls';
        }
    } else {
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = '';
    }
    $mail->Timeout    = (int)($svc['timeout'] ?? 30);
    $mail->CharSet    = $global['charset'] ?? 'UTF-8';
    $mail->XMailer    = 'SnapIt Photobooth Mailer (PHPMailer)';
    $mail->setFrom($global['from_email'], $global['from_name']);
    if (!empty($global['reply_to'])) {
        try { $mail->addReplyTo($global['reply_to'], $global['from_name']); } catch (Throwable $t) { /* ignore */ }
    }
    return $cfg;
}

function snapit_send_email($options = []) {
    global $MAILER;
    $result = [
        'ok'            => false,
        'message_id'    => null,
        'error'         => null,
        'detail'        => null,
        'service'       => null,
        'recipients_ok' => 0,
        'recipients_total' => 0,
        'smtp_log'      => [],
        'used_phpmailer' => false,
    ];
    $to          = $options['to']          ?? [];
    $cc          = $options['cc']          ?? [];
    $bcc         = $options['bcc']         ?? [];
    $subject     = $options['subject']     ?? '(No subject)';
    $body_html   = $options['body_html']   ?? '';
    $body_text   = $options['body_text']   ?? '';
    $attachments = $options['attachments'] ?? [];
    $overrideSvc = $options['service']     ?? null;
    $debug       = array_key_exists('debug', $options) ? (bool)$options['debug'] : (((int)($MAILER['debug_level'] ?? 0)) > 0);

    if (!is_array($to)) $to  = $to  ? [$to]  : [];
    if (!is_array($cc)) $cc  = $cc  ? [$cc]  : [];
    if (!is_array($bcc))$bcc = $bcc ? [$bcc] : [];

    $valid_to = [];
    foreach ($to as $addr) {
        $norm = trim((string)$addr);
        if ($norm && filter_var($norm, FILTER_VALIDATE_EMAIL)) $valid_to[] = $norm;
    }
    $result['recipients_total'] = count($to);
    $result['recipients_ok']    = 0;
    if (empty($valid_to)) {
        $result['error']  = 'no_valid_recipients';
        $result['detail'] = 'No valid "To" addresses provided (' . count($to) . ' attempted).';
        return $result;
    }

    if (!snapit_mailer_enabled()) {
        $result['error']  = 'phpmailer_missing';
        $result['detail'] = 'PHPMailer is not installed. Run `composer install` from snapit/ OR copy '
                          . 'PHPMailer src files (Exception,SMTP,PHPMailer,OAuth) into vendor/phpmailer/src/ '
                          . '(see vendor/README.md). Falling back to PHP mail() is intentionally disabled to '
                          . 'guarantee SMTP deliverability; please install PHPMailer.';
        return $result;
    }
    $result['used_phpmailer'] = true;

    try {
        $mail = new PHPMailer(true);
        $cfg = snapit_configure_smtp($mail, $overrideSvc);
        $result['service'] = $cfg['service_key'];

        if ($debug) {
            $mail->SMTPDebug = defined('SMTP::DEBUG_SERVER') ? SMTP::DEBUG_SERVER : 3;
            $mail->Debugoutput = function ($str, $level) use (&$result) {
                $result['smtp_log'][] = '[SMTP ' . $level . '] ' . rtrim($str);
            };
        }

        foreach ($valid_to as $addr) {
            try { $mail->addAddress($addr); } catch (PHPMailerException $e) {
                $result['detail'] = ($result['detail'] ? $result['detail'] . '; ' : '') . 'Invalid TO: ' . $addr;
            }
        }
        foreach ((array)$cc as $addr) {
            $addr = trim((string)$addr);
            if ($addr && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                try { $mail->addCC($addr); } catch (Throwable $t) { /* ignore */ }
            }
        }
        foreach ((array)$bcc as $addr) {
            $addr = trim((string)$addr);
            if ($addr && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                try { $mail->addBCC($addr); } catch (Throwable $t) { /* ignore */ }
            }
        }
        if (empty($body_text) && $body_html !== '') {
            $body_text = trim(preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $body_html)));
        }
        if ($body_html !== '') {
            $mail->isHTML(true);
            $mail->Body    = $body_html;
            if ($body_text !== '') $mail->AltBody = $body_text;
        } else {
            $mail->isHTML(false);
            $mail->Body = $body_text !== '' ? $body_text : $subject;
        }
        $mail->Subject = $subject;

        foreach ($attachments as $att) {
            if (is_string($att) && file_exists($att)) {
                try { $mail->addAttachment($att); } catch (Throwable $t) {
                    $result['detail'] = ($result['detail'] ? $result['detail'] . '; ' : '')
                                      . 'Could not attach file: ' . basename($att);
                }
            } elseif (is_array($att) && !empty($att['path']) && file_exists($att['path'])) {
                try {
                    $mail->addAttachment(
                        $att['path'],
                        $att['name'] ?? '',
                        PHPMailer::ENCODING_BASE64,
                        $att['type'] ?? '',
                        $att['disposition'] ?? 'attachment'
                    );
                } catch (Throwable $t) {
                    $result['detail'] = ($result['detail'] ? $result['detail'] . '; ' : '')
                                      . 'Could not attach: ' . ($att['name'] ?? basename($att['path']));
                }
            }
        }

        if ($mail->send()) {
            $result['ok'] = true;
            $result['recipients_ok'] = count($valid_to);
            if (!empty($mail->getLastMessageID())) {
                $result['message_id'] = $mail->getLastMessageID();
            }
        } else {
            $result['error']  = 'send_failed';
            $result['detail'] = 'PHPMailer send() returned false but no exception: ' . $mail->ErrorInfo;
        }
    } catch (PHPMailerException $e) {
        $result['error']  = 'phpmailer_exception';
        $result['detail'] = $e->getMessage() . ' | SMTP last error: ' . ($mail->ErrorInfo ?? 'n/a');
    } catch (Throwable $t) {
        $result['error']  = 'unexpected_exception';
        $result['detail'] = get_class($t) . ': ' . $t->getMessage() . ' @ ' . $t->getFile() . ':' . $t->getLine();
    }
    return $result;
}

function snapit_email_wrap_html($title, $bodyHtml, $baseUrl = null) {
    if ($baseUrl === null) $baseUrl = snapit_build_full_url('');
    $css = 'body{font-family:Arial,Helvetica,sans-serif;background:#f8f5ff;color:#1f1b2e;margin:0;padding:24px}'
         . '.wrap{max-width:600px;margin:0 auto;background:#fff;border:1px solid #e6defd;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(111,66,193,0.08)}'
         . '.hero{background:linear-gradient(135deg,#6f42c1,#e83e8c);color:#fff;padding:28px 32px}'
         . '.hero h1{margin:0;font-size:20px;letter-spacing:0.3px}'
         . '.hero p{margin:4px 0 0;opacity:0.9;font-size:13px}'
         . '.inner{padding:28px 32px;line-height:1.6;font-size:15px;color:#1f1b2e}'
         . '.inner h2{font-size:17px;color:#6f42c1;margin:0 0 12px}'
         . '.meta{background:#faf7ff;border-left:4px solid #fd7e14;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0;font-size:14px}'
         . '.cta{display:inline-block;background:#6f42c1;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;margin:14px 0}'
         . '.foot{padding:20px 32px;background:#faf7ff;color:#6b6480;font-size:12px;border-top:1px solid #efe7ff;text-align:center}'
         . '.foot a{color:#6f42c1;text-decoration:none}';
    $title = $title ? $title : 'Snap It Photobooth';
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
         . '<title>' . htmlspecialchars($title) . '</title></head><body style="margin:0;padding:24px;background:#f8f5ff">'
         . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td>'
         . '<div class="wrap" style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e6defd;border-radius:12px;overflow:hidden;box-shadow:0 6px 24px rgba(111,66,193,0.08)">'
         . '<div class="hero" style="background:linear-gradient(135deg,#6f42c1,#e83e8c);color:#fff;padding:28px 32px">'
         . '<h1 style="margin:0;font-size:20px;letter-spacing:0.3px">📸 Snap It Photobooth</h1>'
         . '<p style="margin:4px 0 0;opacity:0.9;font-size:13px">' . htmlspecialchars($title) . '</p>'
         . '</div>'
         . '<div class="inner" style="padding:28px 32px;line-height:1.6;font-size:15px;color:#1f1b2e">'
         . $bodyHtml
         . '</div>'
         . '<div class="foot" style="padding:20px 32px;background:#faf7ff;color:#6b6480;font-size:12px;border-top:1px solid #efe7ff;text-align:center">'
         . '&copy; ' . date('Y') . ' Snap It Photobooth &middot; '
         . '<a href="' . htmlspecialchars($baseUrl) . '" style="color:#6f42c1;text-decoration:none">'
         . preg_replace('#^https?://#', '', rtrim($baseUrl, '/')) . '</a>'
         . '</div></div></td></tr></table></body></html>';
}

function snapit_mark_notification_sent($conn, $notification_id, $success, $errorDetail = '', $messageId = '') {
    if (!$conn || !(int)$notification_id) return false;
    $status = $success ? 'sent' : 'failed';
    $sentAt = $success ? 'NOW()' : 'NULL';
    $sql = "UPDATE notifications SET status = ?, sent_at = " . $sentAt
         . ", error_detail = ?, message_id = ? WHERE notification_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    $errShort = mb_substr((string)$errorDetail, 0, 1000);
    $idShort  = mb_substr((string)$messageId, 0, 255);
    mysqli_stmt_bind_param($stmt, 'sssi', $status, $errShort, $idShort, $notification_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function snapit_dispatch_notification_row($conn, $row, $attachments = []) {
    if (!is_array($row) || empty($row['recipient_email'])) {
        return ['ok' => false, 'error' => 'no_recipient', 'detail' => 'Notification row missing recipient_email'];
    }
    $to   = $row['recipient_email'];
    $subj = $row['subject']       ?: 'Snap It Update';
    $body = $row['message_body']  ?: '';
    $html = '<h2 style="font-size:17px;color:#6f42c1;margin:0 0 12px">' . htmlspecialchars($subj) . '</h2>'
          . '<div style="white-space:pre-wrap">' . htmlspecialchars($body) . '</div>';
    return snapit_send_email([
        'to'          => $to,
        'subject'     => $subj,
        'body_html'   => snapit_email_wrap_html($subj, $html),
        'body_text'   => strip_tags($body),
        'attachments' => $attachments,
    ]);
}

function snapit_send_booth_softcopy_email($toAddress, $booking, $session, $photoRows, $galleryUrl) {
    $evt = htmlspecialchars($booking['event_name'] ?? 'Snap It Event');
    $bookingId = (int)($booking['booking_id'] ?? 0);
    $sessionId = (int)($session['session_id']   ?? 0);
    $customer  = htmlspecialchars($booking['customer_name'] ?? $booking['name'] ?? 'Friend');

    $photoHtml = '';
    if (!empty($photoRows) && is_array($photoRows)) {
        $photoHtml = '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:18px 0">';
        foreach ($photoRows as $p) {
            $url = !empty($p['photo_path']) ? snapit_build_full_url(ltrim($p['photo_path'], '/')) : '';
            if (!$url) continue;
            $photoHtml .= '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener" style="display:block;border:1px solid #efe7ff;border-radius:10px;overflow:hidden;box-shadow:0 3px 10px rgba(111,66,193,0.08)">'
                        . '<img src="' . htmlspecialchars($url) . '" alt="Photo" loading="lazy" crossorigin="anonymous" '
                        . 'style="display:block;width:100%;height:auto"></a>';
        }
        $photoHtml .= '</div>';
    }

    $body = '<h2 style="font-size:17px;color:#6f42c1;margin:0 0 12px">Your photos are ready, ' . $customer . '! 🎉</h2>'
          . '<p>Thanks for visiting the Snap It booth at <strong>' . $evt . '</strong>. Here\'s your softcopy set from Session #' . $sessionId . ' (Booking #' . $bookingId . ').</p>'
          . $photoHtml
          . '<p><a class="cta" href="' . htmlspecialchars($galleryUrl) . '" style="display:inline-block;background:#6f42c1;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;margin:14px 0">View Full Gallery &amp; Download as PNG</a></p>'
          . '<div class="meta" style="background:#faf7ff;border-left:4px solid #fd7e14;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0;font-size:14px">'
          . 'Click each photo above to open its full-resolution version. To save: right-click &rarr; Save Image As, or use the <strong>Download as PNG</strong> button on the gallery page for the full framed strip.'
          . '</div>';
    $attachments = [];
    return snapit_send_email([
        'to'          => $toAddress,
        'subject'     => 'Your Snap It Photos from ' . $evt . ' (Session #' . $sessionId . ')',
        'body_html'   => snapit_email_wrap_html('Softcopy delivery for ' . $evt, $body, $galleryUrl),
        'body_text'   => 'Your photos are ready: ' . $galleryUrl . "\n\n" . strip_tags($body),
        'attachments' => $attachments,
    ]);
}

function snapit_send_event_reminder($booking, $daysBefore) {
    $evt = htmlspecialchars($booking['event_name'] ?? 'Your Event');
    $bookingId = (int)($booking['booking_id'] ?? 0);
    $customer  = htmlspecialchars($booking['customer_name'] ?? $booking['name'] ?? '');
    $whenDate  = htmlspecialchars($booking['event_date'] ?? '');
    $whenTime  = isset($booking['start_time']) ? htmlspecialchars(date('h:i A', strtotime($booking['start_time']))) : '';
    $venue     = htmlspecialchars($booking['venue'] ?? '');
    $hours     = (int)($booking['duration_hours'] ?? 0);
    $viewUrl   = snapit_build_full_url('booking/view.php?id=' . $bookingId);

    $body = '<h2 style="font-size:17px;color:#6f42c1;margin:0 0 12px">Friendly reminder: ' . $evt . ' is in ' . (int)$daysBefore . ' day(s)! 🗓️</h2>'
          . '<p>Hi ' . $customer . ',</p>'
          . '<p>We\'re getting ready for your event. Here are the details we have on file for Booking #' . $bookingId . ':</p>'
          . '<div class="meta" style="background:#faf7ff;border-left:4px solid #fd7e14;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0;font-size:14px">'
          . '<div><strong>Event:</strong> ' . $evt . '</div>'
          . '<div><strong>Date:</strong> ' . $whenDate . '</div>'
          . '<div><strong>Start time:</strong> ' . $whenTime . '</div>'
          . '<div><strong>Duration:</strong> ' . $hours . ' hours</div>'
          . '<div><strong>Venue:</strong> ' . $venue . '</div>'
          . '</div>'
          . '<p>If anything needs to be adjusted, please reply to this email or call us as soon as possible.</p>'
          . '<a class="cta" href="' . htmlspecialchars($viewUrl) . '" style="display:inline-block;background:#6f42c1;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;margin:14px 0">View Booking Details</a>';
    return snapit_send_email([
        'to'        => $booking['customer_email'] ?? $booking['email'] ?? null,
        'subject'   => 'Reminder: ' . $evt . ' in ' . (int)$daysBefore . ' day(s) (Booking #' . $bookingId . ')',
        'body_html' => snapit_email_wrap_html('Event reminder for ' . $evt, $body, $viewUrl),
        'body_text' => strip_tags($body),
    ]);
}
