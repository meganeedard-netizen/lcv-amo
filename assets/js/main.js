// La Clef de Voûte — scripts du site
(function () {
  "use strict";

  /* Menu mobile */
  var burger = document.querySelector("[data-burger]");
  var navMobile = document.querySelector("[data-nav-mobile]");
  if (burger && navMobile) {
    burger.addEventListener("click", function () {
      var open = navMobile.classList.toggle("is-open");
      burger.classList.toggle("is-open", open);
      burger.setAttribute("aria-expanded", open ? "true" : "false");
      document.body.classList.toggle("nav-locked", open);
    });
    navMobile.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        navMobile.classList.remove("is-open");
        burger.classList.remove("is-open");
        document.body.classList.remove("nav-locked");
      });
    });
  }

  /* Formulaire de contact */
  var form = document.querySelector("[data-contact-form]");
  if (form) {
    var statusBox = form.querySelector("[data-form-status]");
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      // Piège à robots (honeypot)
      if (form.querySelector('[name="site_web"]').value) {
        return;
      }

      if (!form.checkValidity()) {
        showStatus(false, "Merci d'indiquer toutes vos coordonnées nécessaires à l'envoi de votre demande.");
        return;
      }

      var submitBtn = form.querySelector('[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
      submitBtn.textContent = "Envoi en cours…";

      fetch(form.getAttribute("action"), {
        method: "POST",
        body: new FormData(form),
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
        .then(function (res) { return res.json().catch(function () { return { ok: res.ok }; }); })
        .then(function (data) {
          if (data && data.ok) {
            showStatus(true, "Merci ! Votre message a bien été envoyé. Fanny vous répondra rapidement.");
            form.reset();
          } else {
            showStatus(false, "Une erreur est survenue lors de l'envoi. Vous pouvez aussi écrire directement à contact@lcv-amo.fr.");
          }
        })
        .catch(function () {
          showStatus(false, "Une erreur est survenue lors de l'envoi. Vous pouvez aussi écrire directement à contact@lcv-amo.fr.");
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = submitBtn.dataset.originalText;
        });
    });

    function showStatus(ok, message) {
      if (!statusBox) return;
      statusBox.textContent = message;
      statusBox.classList.remove("success", "error");
      statusBox.classList.add(ok ? "success" : "error", "is-visible");
      statusBox.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  /* Année courante dans le footer */
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  /* Masquer le bouton WhatsApp flottant quand le pied de page est visible, pour ne pas cacher les liens légaux */
  var whatsappFloat = document.querySelector(".whatsapp-float");
  var siteFooter = document.querySelector(".site-footer");
  if (whatsappFloat && siteFooter && "IntersectionObserver" in window) {
    var footerObserver = new IntersectionObserver(function (entries) {
      whatsappFloat.classList.toggle("is-hidden", entries[0].isIntersecting);
    });
    footerObserver.observe(siteFooter);
  }

  /* Titre héros : bascule sur le texte statique si la vidéo ne se lance pas toute seule (certains mobiles/navigateurs) */
  var heroTag = document.querySelector(".hero-tag");
  var heroVideo = heroTag && heroTag.querySelector("video");
  if (heroTag && heroVideo) {
    var heroVideoStarted = false;
    var showHeroFallback = function () { heroTag.classList.add("video-failed"); };
    heroVideo.addEventListener("playing", function () { heroVideoStarted = true; });
    heroVideo.muted = true;
    var playPromise = heroVideo.play();
    if (playPromise && typeof playPromise.catch === "function") {
      playPromise.catch(showHeroFallback);
    }
    heroVideo.addEventListener("stalled", showHeroFallback);
    heroVideo.addEventListener("error", showHeroFallback);
    setTimeout(function () {
      if (!heroVideoStarted) showHeroFallback();
    }, 1500);
  }
})();
