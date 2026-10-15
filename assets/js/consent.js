/* Mesure d'audience Google Analytics, activée uniquement après accord du visiteur (CNIL). */
(function () {
  var GA_ID = 'G-ZM6G3Z4D49';
  var KEY = 'lcv_cookie_consent';
  var MAX_AGE = 1000 * 60 * 60 * 24 * 182; // choix redemandé au bout de 6 mois
  var privacy = (location.pathname.indexOf('/blog/') !== -1 ? '../' : '') + 'politique-confidentialite.html';

  var T = {
    text: 'Nous utilisons des cookies (Google Analytics) uniquement pour mesurer la fréquentation du site. Aucune publicité. Vous acceptez ?',
    more: 'En savoir plus', ok: 'Accepter', no: 'Refuser', link: 'Cookies'
  };

  function read() {
    try {
      var v = JSON.parse(localStorage.getItem(KEY));
      if (v && Date.now() - v.t < MAX_AGE) return v.c;
    } catch (e) {}
    return null;
  }
  function save(c) {
    try { localStorage.setItem(KEY, JSON.stringify({ c: c, t: Date.now() })); } catch (e) {}
  }

  var loaded = false;
  function loadGA() {
    if (loaded) return;
    loaded = true;
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', GA_ID);
  }

  function clearGA() {
    document.cookie.split(';').forEach(function (c) {
      var name = c.split('=')[0].trim();
      if (name.indexOf('_ga') === 0) {
        document.cookie = name + '=; Max-Age=0; path=/; domain=.' + location.hostname.replace(/^www\./, '');
        document.cookie = name + '=; Max-Age=0; path=/';
      }
    });
  }

  function css() {
    if (document.getElementById('kt-cookie-css')) return;
    var st = document.createElement('style');
    st.id = 'kt-cookie-css';
    st.textContent =
      '#kt-cookie{position:fixed;left:16px;right:16px;bottom:16px;z-index:10000;max-width:560px;margin:0 auto;' +
      'background:#242E47;color:#FAF9F6;border-radius:8px;' +
      'padding:1.2rem 1.35rem;box-shadow:0 10px 30px rgba(16,20,30,.3);font-family:inherit;font-size:.88rem;line-height:1.6}' +
      '#kt-cookie p{margin:0 0 .9rem}' +
      '#kt-cookie a{color:#E9E8E3;text-decoration:underline}' +
      '#kt-cookie .kt-btns{display:flex;gap:.6rem}' +
      '#kt-cookie button{flex:1;cursor:pointer;font:inherit;font-weight:500;padding:.65rem 1rem;border-radius:4px;letter-spacing:.03em}' +
      '#kt-cookie .kt-ok{background:#862762;color:#fff;border:1px solid #862762;font-weight:600}' +
      '#kt-cookie .kt-no{background:transparent;color:#FAF9F6;border:1px solid rgba(250,249,246,0.5);font-weight:600}' +
      '#kt-cookie button:hover{filter:brightness(1.1)}';
    document.head.appendChild(st);
  }

  function banner() {
    if (document.getElementById('kt-cookie')) return;
    css();
    var d = document.createElement('div');
    d.id = 'kt-cookie';
    d.setAttribute('role', 'dialog');
    d.setAttribute('aria-label', 'Cookies');
    d.innerHTML = '<p>' + T.text + ' <a href="' + privacy + '">' + T.more + '</a></p>' +
      '<div class="kt-btns"><button type="button" class="kt-no">' + T.no + '</button>' +
      '<button type="button" class="kt-ok">' + T.ok + '</button></div>';
    d.querySelector('.kt-ok').onclick = function () { save('granted'); d.remove(); loadGA(); };
    d.querySelector('.kt-no').onclick = function () { save('denied'); d.remove(); clearGA(); };
    document.body.appendChild(d);
  }

  function footerLink() {
    var ul = document.querySelector('footer .footer-bottom ul');
    if (!ul || ul.querySelector('.kt-cookie-link')) return;
    var li = document.createElement('li');
    var a = document.createElement('a');
    a.href = '#';
    a.className = 'kt-cookie-link';
    a.textContent = T.link;
    a.onclick = function (e) { e.preventDefault(); banner(); };
    li.appendChild(a);
    ul.appendChild(li);
  }

  function init() {
    var c = read();
    if (c === 'granted') loadGA();
    else if (c === null) banner();
    footerLink();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
