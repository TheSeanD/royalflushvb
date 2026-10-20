<?php
/**
 * Form handler for royalflushvb.com — receives native POSTs from the
 * site's quote/contact forms and emails them to the service inbox.
 */

$recipient  = 'service@royalflushbbb.com';
$from       = 'forms@royalflushvb.com'; // must be a mailbox/domain on this cPanel account for SPF/DMARC
$thank_you  = '/thank-you/';
$logo_url   = 'https://www.royalflushvb.com/images/66968ee746d15a1abea201c6_RoyalFlushHorizontal_Color-p-800.png'; // colour logo, shown on the white header band

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Secret key lives in contact-config.php (not committed, not web-readable) — see contact-config.example.php
$config = __DIR__ . '/contact-config.php';
$cfg = is_file($config) ? (array) (require $config) : [];
$recaptcha_secret = (string) ($cfg['recaptcha_secret'] ?? '');
// Optional Bcc copy of every lead (handy for testing): contact-config.php 'bcc' or the CONTACT_BCC env var
$bcc = trim((string) (($cfg['bcc'] ?? '') ?: (getenv('CONTACT_BCC') ?: '')));
if (!filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
    $bcc = '';
}

// Honeypot: hidden field real users never fill
if (!empty($_POST['website'])) {
    header('Location: ' . $thank_you);
    exit;
}

// reCAPTCHA v3: verify the token the page attached to the form
if ($recaptcha_secret !== '') {
    $token  = (string) ($_POST['g-recaptcha-response'] ?? '');
    $reason = '';
    if ($token === '') {
        $reason = 'no-token'; // script blocked, wrong site key/domain, or JS error in the browser
    } else {
        $fields = http_build_query(['secret' => $recaptcha_secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if ($raw === false || $raw === '') {
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $fields, 'timeout' => 8,
            ]]);
            $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        }
        $res = $raw ? json_decode($raw, true) : null;
        if (!is_array($res)) {
            // Could not reach Google (host blocks outbound requests?): don't lose a real lead
            error_log('contact.php: reCAPTCHA verification unreachable; allowing submission');
        } elseif (empty($res['success'])) {
            $reason = 'google-' . implode(',', $res['error-codes'] ?? ['unknown']);
        } elseif (($res['action'] ?? '') !== 'contact') {
            $reason = 'action';
        } elseif (($res['score'] ?? 0) < 0.5) {
            $reason = 'score-' . ($res['score'] ?? 0);
        }
        if ($reason !== '') {
            error_log('contact.php: reCAPTCHA rejected (' . json_encode($res) . ')');
        }
    }
    if ($reason !== '') {
        header('Location: /contact-us/?captcha=failed&reason=' . rawurlencode($reason));
        exit;
    }
} else {
    error_log('contact.php: reCAPTCHA secret not configured; skipping verification');
}

$form_name = trim($_POST['form_name'] ?? 'Website Form');
$page      = trim($_POST['source_page'] ?? ($_SERVER['HTTP_REFERER'] ?? 'unknown'));

// Find the submitter's email for Reply-To (field names vary per form)
$reply_to = '';
foreach ($_POST as $key => $value) {
    if (stripos($key, 'email') !== false && filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
        $reply_to = trim($value);
        break;
    }
}

// Collect every submitted field (label cleaned for display)
$skip   = ['website', 'form_name', 'source_page', 'g-recaptcha-response'];
$fields = [];
foreach ($_POST as $key => $value) {
    if (in_array($key, $skip, true)) {
        continue;
    }
    $value = trim(str_replace("\r", '', (string) $value));
    // Strip header-injection attempts from field names, then drop Webflow's "-2" style suffixes
    $label = trim(preg_replace('/[^\w\s\-\*\.]/', '', $key));
    $label = trim(preg_replace('/\s+/', ' ', preg_replace('/[-_]+|\b\d+\b/', ' ', $label)));
    $fields[] = [$label, $value];
}

$has_content = false;
foreach ($fields as $f) {
    if ($f[1] !== '') {
        $has_content = true;
        break;
    }
}
if (!$has_content) {
    header('Location: /');
    exit;
}

$form_name = trim(preg_replace('/[\r\n]+/', ' ', $form_name));
$page      = trim(preg_replace('/[\r\n]+/', ' ', $page));
$time      = date('F j, Y \a\t g:i A T');
$esc       = function ($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };

// Plain-text alternative
$text = $form_name . " submission\n"
      . "Page: " . $page . "\n"
      . "Time: " . $time . "\n"
      . str_repeat('-', 40) . "\n";
foreach ($fields as $f) {
    $text .= $f[0] . ': ' . $f[1] . "\n";
}

// HTML body (table layout + inline styles for email-client compatibility)
$rows = '';
foreach ($fields as $f) {
    $val = $f[1] === '' ? '<span style="color:#9aa3ab;">&mdash;</span>' : nl2br($esc($f[1]), false);
    if ($f[0] !== '' && stripos($f[0], 'email') !== false && filter_var($f[1], FILTER_VALIDATE_EMAIL)) {
        $val = '<a href="mailto:' . $esc($f[1]) . '" style="color:#181515;text-decoration:none;">' . $esc($f[1]) . '</a>';
    }
    $rows .= '<tr><td style="padding:14px 24px;border-bottom:1px solid #e6e9ec;">'
           . '<div style="font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6b747b;padding-bottom:4px;">' . $esc($f[0]) . '</div>'
           . '<div style="font-size:15px;line-height:1.5;color:#181515;">' . $val . '</div>'
           . '</td></tr>';
}

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
      . '<body style="margin:0;padding:0;background:#f5f8fa;font-family:Helvetica,Arial,sans-serif;">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fa;"><tr><td align="center" style="padding:24px 12px;">'
      . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e6e9ec;border-radius:6px;overflow:hidden;">'
      . '<tr><td align="center" style="background:#ffffff;padding:24px;">'
      . '<img src="' . $logo_url . '" alt="Royal Flush" width="180" style="display:block;border:0;height:auto;max-width:180px;">'
      . '</td></tr>'
      . '<tr><td style="height:4px;line-height:4px;font-size:0;background:#181515;">&nbsp;</td></tr>'
      . '<tr><td style="padding:28px 24px 12px;">'
      . '<div style="font-size:12px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#181515;">New website inquiry</div>'
      . '<div style="font-size:22px;font-weight:bold;color:#181515;padding-top:6px;">' . $esc($form_name) . '</div>'
      . '</td></tr>'
      . $rows
      . '<tr><td style="padding:18px 24px;background:#f5f8fa;font-size:12px;line-height:1.6;color:#6b747b;">'
      . 'Submitted ' . $esc($time) . '<br>From page: ' . $esc($page) . '<br>Reply directly to this email to respond to the sender.'
      . '</td></tr>'
      . '</table></td></tr></table></body></html>';

$boundary = 'rf_' . bin2hex(random_bytes(8));
$body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
      . chunk_split(base64_encode($text))
      . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
      . chunk_split(base64_encode($html))
      . "--$boundary--";

$headers = 'From: Royal Flush Website <' . $from . '>' . "\r\n"
         . ($reply_to !== '' ? 'Reply-To: ' . $reply_to . "\r\n" : '')
         . ($bcc !== '' ? 'Bcc: ' . $bcc . "\r\n" : '')
         . 'MIME-Version: 1.0' . "\r\n"
         . 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n"
         . 'X-Mailer: PHP/' . phpversion();

$subject = '=?UTF-8?B?' . base64_encode('New website inquiry: ' . $form_name) . '?=';
$sent = mail($recipient, $subject, $body, $headers);

if (!$sent) {
    error_log('contact.php: mail() failed for ' . $form_name . ' from ' . $reply_to);
}

// Confirmation email to the person who submitted the form (only when the team email was sent
// and the address passed validation above)
if ($sent && $reply_to !== '') {
    $first = '';
    foreach ($fields as $f) {
        if (stripos($f[0], 'name') !== false && trim($f[1]) !== '') {
            $first = mb_substr(trim(preg_split('/\s+/', trim($f[1]))[0]), 0, 40);
            break;
        }
    }
    $hello = $first !== '' ? 'Hi ' . $first . ',' : 'Hello,';

    $recap_text = '';
    $recap_html = '';
    foreach ($fields as $f) {
        if ($f[1] === '') {
            continue;
        }
        $recap_text .= $f[0] . ': ' . $f[1] . "\n";
        $recap_html .= '<tr><td style="padding:6px 0;font-size:13px;color:#6b747b;width:150px;vertical-align:top;">' . $esc($f[0]) . '</td>'
                     . '<td style="padding:6px 0;font-size:14px;color:#181515;">' . nl2br($esc($f[1]), false) . '</td></tr>';
    }

    $ack_text = $hello . "\n\n"
              . "We received your request and will be in touch shortly.\n\n"
              . "Need something sooner? Call us at (757) 377-3603, Mon-Fri 8:00 AM - 5:00 PM.\n\n"
              . "Here's what you sent us:\n" . str_repeat('-', 40) . "\n" . $recap_text . "\n"
              . "Royal Flush\nhttps://www.royalflushvb.com/\n";

    $ack_html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
              . '<body style="margin:0;padding:0;background:#f5f8fa;font-family:Helvetica,Arial,sans-serif;">'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f8fa;"><tr><td align="center" style="padding:24px 12px;">'
              . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e6e9ec;border-radius:6px;overflow:hidden;">'
              . '<tr><td align="center" style="background:#ffffff;padding:24px;">'
              . '<img src="' . $logo_url . '" alt="Royal Flush" width="180" style="display:block;border:0;height:auto;max-width:180px;">'
              . '</td></tr>'
              . '<tr><td style="height:4px;line-height:4px;font-size:0;background:#181515;">&nbsp;</td></tr>'
              . '<tr><td style="padding:28px 24px 8px;">'
              . '<div style="font-size:22px;font-weight:bold;color:#181515;">We received your request</div>'
              . '<p style="font-size:15px;line-height:1.6;color:#181515;margin:14px 0 0;">' . $esc($hello) . '</p>'
              . '<p style="font-size:15px;line-height:1.6;color:#181515;margin:10px 0 0;">Thanks for contacting Royal Flush. We received your request and will be in touch shortly.</p>'
              . '<p style="font-size:15px;line-height:1.6;color:#181515;margin:10px 0 0;">Need something sooner? Call us at <a href="tel:+17573773603" style="color:#181515;font-weight:bold;">(757) 377-3603</a>, Mon-Fri 8:00 AM - 5:00 PM.</p>'
              . '</td></tr>'
              . '<tr><td style="padding:18px 24px 6px;"><div style="font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#6b747b;padding-bottom:6px;border-bottom:1px solid #e6e9ec;">What you sent us</div>'
              . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $recap_html . '</table></td></tr>'
              . '<tr><td style="padding:18px 24px;background:#f5f8fa;font-size:12px;line-height:1.6;color:#6b747b;">'
              . 'Royal Flush &middot; Portable toilet &amp; restroom trailer rentals in Virginia Beach<br>'
              . '<a href="https://www.royalflushvb.com/" style="color:#6b747b;">www.royalflushvb.com</a>'
              . '</td></tr></table></td></tr></table></body></html>';

    $ack_boundary = 'rf_ack_' . bin2hex(random_bytes(8));
    $ack_body = "--$ack_boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($ack_text))
              . "--$ack_boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($ack_html))
              . "--$ack_boundary--";
    $ack_headers = 'From: Royal Flush <' . $from . '>' . "\r\n"
                 . 'Reply-To: ' . $recipient . "\r\n"
                 . 'Auto-Submitted: auto-replied' . "\r\n"
                 . 'MIME-Version: 1.0' . "\r\n"
                 . 'Content-Type: multipart/alternative; boundary="' . $ack_boundary . '"' . "\r\n"
                 . 'X-Mailer: PHP/' . phpversion();
    $ack_subject = '=?UTF-8?B?' . base64_encode('We received your request | Royal Flush') . '?=';
    if (!mail($reply_to, $ack_subject, $ack_body, $ack_headers)) {
        error_log('contact.php: confirmation email failed for ' . $reply_to);
    }
}

header('Location: ' . $thank_you);
exit;
