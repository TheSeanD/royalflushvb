// reCAPTCHA v3 on the lead forms (script loads on first interaction, token fetched on submit)
(function () {
  var SITE_KEY = '6LcCo_ctAAAAAEmGFaLeDKPYnYkzOQxjxO4HnWwG'; // Royal Flush reCAPTCHA v3 SITE key (Google admin console); leave blank to skip reCAPTCHA
  if (!SITE_KEY) return;
  var forms = document.querySelectorAll('form[action="/contact.php"]');
  if (!forms.length) return;

  var loading;
  function load() {
    if (loading) return loading;
    loading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = 'https://www.google.com/recaptcha/api.js?render=' + SITE_KEY;
      s.async = true;
      s.onload = function () { window.grecaptcha.ready(resolve); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
    return loading;
  }

  forms.forEach(function (form) {
    form.addEventListener('focusin', load, { once: true });
    var note = document.createElement('p');
    note.style.cssText = 'font-size:12px;line-height:1.5;margin:12px 0 0;opacity:.8';
    note.innerHTML = 'Protected by reCAPTCHA. Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline">Terms of Service</a> apply.';
    form.appendChild(note);

    form.addEventListener('submit', function (e) {
      if (form.dataset.captchaDone) return;
      e.preventDefault();
      var btn = form.querySelector('[type="submit"]');
      if (btn) btn.disabled = true;
      function send(token) {
        var i = form.querySelector('input[name="g-recaptcha-response"]');
        if (!i) { i = document.createElement('input'); i.type = 'hidden'; i.name = 'g-recaptcha-response'; form.appendChild(i); }
        i.value = token || '';
        form.dataset.captchaDone = '1';
        form.submit();
      }
      load().then(function () {
        return window.grecaptcha.execute(SITE_KEY, { action: 'contact' });
      }).then(send, function () { send(''); });
    });
  });

  if (/[?&]captcha=failed/.test(location.search)) {
    var msg = document.createElement('div');
    msg.setAttribute('role', 'alert');
    msg.style.cssText = 'background:#fdecea;color:#8a1f11;padding:14px 18px;border-radius:3px;margin:0 0 16px;font-size:15px';
    msg.innerHTML = 'We couldn’t verify your submission. Please try again, or call <a href="tel:+17573773603" style="color:inherit;text-decoration:underline">(757) 377-3603</a>.';
    var why = (location.search.match(/[?&]reason=([^&]+)/) || [])[1];
    if (why) { var small = document.createElement('div'); small.style.cssText = 'font-size:12px;opacity:.7;margin-top:4px'; small.textContent = 'Error code: ' + decodeURIComponent(why); msg.appendChild(small); }
    forms[0].parentNode.insertBefore(msg, forms[0]);
  }
})();
