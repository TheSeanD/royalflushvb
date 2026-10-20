(function () {
  var STORAGE_KEY = "rfvb_cookie_consent";
  if (localStorage.getItem(STORAGE_KEY)) return;

  var banner = document.createElement("div");
  banner.id = "cookie-consent-banner";
  banner.setAttribute("role", "dialog");
  banner.setAttribute("aria-label", "Cookie consent");
  banner.style.cssText =
    "position:fixed;left:0;right:0;bottom:0;z-index:9999;background:#0b1a2b;color:#fff;" +
    "padding:16px 20px;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;" +
    "gap:16px;font-family:'Montserrat',sans-serif;font-size:14px;box-shadow:0 -2px 10px rgba(0,0,0,.25);";

  banner.innerHTML =
    '<span style="max-width:640px;line-height:1.5;">We use cookies to improve your experience on our site. By continuing to browse, you agree to our ' +
    '<a href="/privacy-policy" style="color:#9fd3ff;text-decoration:underline;">Privacy Policy</a>.</span>' +
    '<span style="display:flex;gap:10px;">' +
    '<button id="cookie-consent-accept" style="background:#e8b13a;color:#0b1a2b;border:none;padding:8px 18px;border-radius:4px;font-weight:600;cursor:pointer;">Accept</button>' +
    '<button id="cookie-consent-dismiss" style="background:transparent;color:#fff;border:1px solid #fff;padding:8px 18px;border-radius:4px;cursor:pointer;">Dismiss</button>' +
    '</span>';

  document.body.appendChild(banner);

  function close(value) {
    localStorage.setItem(STORAGE_KEY, value);
    banner.remove();
  }

  document.getElementById("cookie-consent-accept").addEventListener("click", function () {
    close("accepted");
  });
  document.getElementById("cookie-consent-dismiss").addEventListener("click", function () {
    close("dismissed");
  });
})();
