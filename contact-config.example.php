<?php
// Copy this file to contact-config.php on the server and fill it in. contact-config.php holds secrets:
// keep it out of version control and never share it.
return [
    // reCAPTCHA v3 SECRET key (Google reCAPTCHA admin console). Blank = verification skipped.
    'recaptcha_secret' => '',
    // Optional: also Bcc every lead to this address (testing). Blank = off.
    // Alternatively set the CONTACT_BCC environment variable.
    'bcc' => '',
];
