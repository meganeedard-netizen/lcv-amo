#!/usr/bin/env node
/**
 * Génère les pages du blog (/blog/*.html, /blog/index.html) et le sitemap.xml
 * à partir des fichiers Markdown de /content/blog/.
 *
 * Utilisation : node scripts/build-blog.js
 * (déclenché automatiquement par la CI à chaque publication via Decap CMS)
 */

const fs = require("fs");
const path = require("path");
const matter = require("gray-matter");
const { marked } = require("marked");

const ROOT = path.join(__dirname, "..");
const CONTENT_DIR = path.join(ROOT, "content", "blog");
const BLOG_DIR = path.join(ROOT, "blog");
const SITE_URL = "https://lcv-amo.fr";

const STATIC_PAGES = [
  { loc: "/", priority: "1.0" },
  { loc: "/services.html", priority: "0.9" },
  { loc: "/qui-suis-je.html", priority: "0.8" },
  { loc: "/blog/", priority: "0.8" },
  { loc: "/contact.html", priority: "0.8" },
  { loc: "/mentions-legales.html", priority: "0.2" },
  { loc: "/politique-confidentialite.html", priority: "0.2" }
];

/**
 * Le champ `cover` du Markdown est écrit depuis la racine du site (ex.
 * "assets/img/photo.jpg" ou "/content/uploads/photo.jpg" via Decap CMS).
 * Les pages qui l'affichent vivent dans /blog/, donc il faut un "../"
 * devant pour un usage en <img src>, et l'URL absolue pour og:image.
 */
function resolveCover(cover) {
  if (!cover) return null;
  if (/^https?:\/\//.test(cover)) return { src: cover, abs: cover };
  const rootRelative = cover.startsWith("/") ? cover : `/${cover}`;

  // Cache-busting : le CDN garde une image en cache 7 jours par URL. Si un
  // article republie une photo sous le même nom de fichier (même slug), il
  // faut une URL différente pour que la nouvelle photo s'affiche tout de
  // suite. On utilise la date de modification du fichier comme version.
  let version = "";
  try {
    const mtime = fs.statSync(path.join(ROOT, rootRelative.slice(1))).mtimeMs;
    version = `?v=${Math.round(mtime)}`;
  } catch {
    // Fichier introuvable au moment du build : pas de version, tant pis.
  }

  return { src: `..${rootRelative}${version}`, abs: `${SITE_URL}${rootRelative}${version}` };
}

function fmtDate(d) {
  return new Date(d).toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });
}

function head({ title, description, canonical, ogImage }) {
  return `<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title}</title>
<meta name="description" content="${description}">
<link rel="canonical" href="${canonical}">
<meta property="og:type" content="article">
<meta property="og:site_name" content="La Clef de Voûte">
<meta property="og:title" content="${title}">
<meta property="og:description" content="${description}">
<meta property="og:url" content="${canonical}">
<meta property="og:image" content="${ogImage}">
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="../assets/img/favicon.ico" sizes="any">
<link rel="icon" type="image/png" href="../assets/img/favicon-32.png" sizes="32x32">
<link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
<link rel="manifest" href="../site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css?v=20260921b">`;
}

function header(activeBlog) {
  return `<a class="skip-link" href="#main">Aller au contenu</a>

<header class="site-header">
  <div class="container">
    <a href="../index.html" class="brand" aria-label="La Clef de Voûte — accueil">
      <img src="../assets/img/logo-header@2x.png" srcset="../assets/img/logo-header@1x.png 240w, ../assets/img/logo-header@2x.png 480w, ../assets/img/logo-header@3x.png 960w" sizes="60px" alt="La Clef de Voûte — Assistance à Maîtrise d'Ouvrage" width="240" height="223">
    </a>
    <nav class="nav-desktop" aria-label="Navigation principale">
      <ul>
        <li><a href="../index.html">Accueil</a></li>
        <li><a href="../services.html">Mes services</a></li>
        <li><a href="../qui-suis-je.html">Qui suis-je ?</a></li>
        <li><a href="index.html"${activeBlog ? ' class="active"' : ""}>Blog</a></li>
        <li><a href="../contact.html">Contact</a></li>
      </ul>
    </nav>
    <a href="../contact.html" class="btn btn--pink nav-cta">Parlons de votre projet</a>
    <button class="burger" data-burger aria-label="Ouvrir le menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<nav class="nav-mobile" data-nav-mobile aria-label="Navigation mobile">
  <ul>
    <li><a href="../index.html">Accueil</a></li>
    <li><a href="../services.html">Mes services</a></li>
    <li><a href="../qui-suis-je.html">Qui suis-je ?</a></li>
    <li><a href="index.html"${activeBlog ? ' class="active"' : ""}>Blog</a></li>
    <li><a href="../contact.html">Contact</a></li>
  </ul>
  <a href="../contact.html" class="btn btn--pink btn--block">Parlons de votre projet</a>
</nav>`;
}

function footer() {
  return `<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="../assets/img/logo-header@1x.png" alt="La Clef de Voûte">
        <p>Assistance à Maîtrise d'Ouvrage portée par Fanny Prieto. Un expert technique à vos côtés, de la première idée à la remise des clés.</p>
      </div>
      <div>
        <h5>Navigation</h5>
        <ul>
          <li><a href="../index.html">Accueil</a></li>
          <li><a href="../services.html">Mes services</a></li>
          <li><a href="../qui-suis-je.html">Qui suis-je ?</a></li>
          <li><a href="index.html">Blog</a></li>
          <li><a href="../contact.html">Contact</a></li>
        </ul>
      </div>
      <div>
        <h5>Zone d'intervention</h5>
        <ul>
          <li>Loiret (45)</li>
          <li>Yonne (89)</li>
          <li>Seine-et-Marne (77)</li>
        </ul>
      </div>
      <div>
        <h5>Contact</h5>
        <ul>
          <li><a href="mailto:contact@lcv-amo.fr">contact@lcv-amo.fr</a></li>
          <li><a href="tel:+33680643283">06 80 64 32 83</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <span data-year>2026</span> La Clef de Voûte — Fanny Prieto. Tous droits réservés.</span>
      <ul>
        <li><a href="../mentions-legales.html">Mentions légales</a></li>
        <li><a href="../politique-confidentialite.html">Politique de confidentialité</a></li>
      </ul>
    </div>
  </div>
</footer>

<script src="../assets/js/main.js"></script>`;
}

function articlePage(post) {
  const canonical = `${SITE_URL}/blog/${post.slug}.html`;
  const resolvedCover = resolveCover(post.cover);
  const ogImage = resolvedCover ? resolvedCover.abs : `${SITE_URL}/assets/img/og-image.jpg`;
  const cover = resolvedCover
    ? `<div class="article-cover"><img src="${resolvedCover.src}" alt="${post.title}" loading="eager"></div>`
    : "";

  return `<!doctype html>
<html lang="fr">
<head>
${head({ title: `${post.title} | Blog La Clef de Voûte`, description: post.excerpt, canonical, ogImage })}
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": ${JSON.stringify(post.title)},
  "datePublished": "${new Date(post.date).toISOString()}",
  "author": { "@type": "Person", "name": "Fanny Prieto" },
  "publisher": { "@type": "Organization", "name": "La Clef de Voûte" },
  "description": ${JSON.stringify(post.excerpt)},
  "mainEntityOfPage": "${canonical}"
}
</script>
</head>
<body>
${header(true)}

<main id="main">
  <article>
    <div class="container article-header">
      <div class="breadcrumb"><a href="../index.html">Accueil</a> / <a href="index.html">Blog</a> / ${post.title}</div>
      <span class="eyebrow">${post.category}</span>
      <h1>${post.title}</h1>
      ${post.subtitle ? `<p class="lede">${post.subtitle}</p>` : ""}
      <div class="article-meta">
        <span>Par Fanny Prieto</span>
        <span>${fmtDate(post.date)}</span>
      </div>
      ${cover}
    </div>
    <div class="container">
      <div class="article-body">
        ${post.html}
      </div>
      <div class="cta-band article-footer-cta">
        <div>
          <h2>Un projet similaire en tête ?</h2>
          <p>Échangeons pour voir comment je peux vous accompagner.</p>
        </div>
        <a href="../contact.html" class="btn btn--pink">Parlons de votre projet</a>
      </div>
    </div>
  </article>
</main>

${footer()}
</body>
</html>
`;
}

function indexPage(posts) {
  const cards = posts
    .map(
      (p) => `        <article class="post-card">
          <div class="post-card__media"><img src="${resolveCover(p.cover)?.src || "../assets/img/og-image.jpg"}" alt="" loading="lazy"></div>
          <div class="post-card__body">
            <span class="post-card__meta">${p.category}</span>
            <h3><a href="${p.slug}.html">${p.title}</a></h3>
            <p>${p.excerpt}</p>
            <a href="${p.slug}.html" class="card-link">Lire l'article</a>
          </div>
        </article>`
    )
    .join("\n");

  return `<!doctype html>
<html lang="fr">
<head>
${head({
    title: "Blog — Conseils AMO pour vos projets | La Clef de Voûte",
    description: "Réglementation, étapes d'un projet, conseils pratiques : le blog de La Clef de Voûte pour les collectivités et porteurs de projets du Loiret, de l'Yonne et de la Seine-et-Marne.",
    canonical: `${SITE_URL}/blog/`,
    ogImage: `${SITE_URL}/assets/img/og-image.jpg`
  })}
</head>
<body>
${header(true)}

<main id="main">
  <section class="hero" style="padding-bottom:20px;">
    <div class="container" style="grid-template-columns:1fr; text-align:center;">
      <div style="max-width:700px; margin:0 auto;">
        <span class="eyebrow" style="justify-content:center;">Blog</span>
        <h1>Conseils &amp; actualités pour vos projets</h1>
        <p class="lede center">Réglementation, étapes d'un projet, conseils pratiques : des articles courts pour avancer sereinement, à destination des collectivités et des porteurs de projets du Loiret, de l'Yonne et de la Seine-et-Marne.</p>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:0;">
    <div class="container">
      <div class="blog-grid">
${cards}
      </div>
    </div>
  </section>
</main>

${footer()}
</body>
</html>
`;
}

function buildSitemap(posts) {
  const urls = [
    ...STATIC_PAGES.map((p) => `  <url><loc>${SITE_URL}${p.loc}</loc><priority>${p.priority}</priority></url>`),
    ...posts.map(
      (p) => `  <url><loc>${SITE_URL}/blog/${p.slug}.html</loc><lastmod>${new Date(p.date).toISOString().slice(0, 10)}</lastmod><priority>0.6</priority></url>`
    )
  ];
  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls.join("\n")}
</urlset>
`;
}

function main() {
  if (!fs.existsSync(CONTENT_DIR)) {
    console.error("Dossier content/blog introuvable :", CONTENT_DIR);
    process.exit(1);
  }

  fs.mkdirSync(BLOG_DIR, { recursive: true });

  const files = fs.readdirSync(CONTENT_DIR).filter((f) => f.endsWith(".md"));

  const posts = files.map((file) => {
    const raw = fs.readFileSync(path.join(CONTENT_DIR, file), "utf8");
    const { data, content } = matter(raw);
    const slug = data.slug || file.replace(/\.md$/, "");
    return {
      slug,
      title: data.title || slug,
      subtitle: data.subtitle || "",
      date: data.date || new Date().toISOString(),
      category: data.category || "Actualités",
      excerpt: data.excerpt || "",
      cover: data.cover || "",
      html: marked.parse(content)
    };
  });

  posts.sort((a, b) => new Date(b.date) - new Date(a.date));

  // Nettoyage des anciennes pages générées
  fs.readdirSync(BLOG_DIR)
    .filter((f) => f.endsWith(".html") && f !== "index.html")
    .forEach((f) => fs.unlinkSync(path.join(BLOG_DIR, f)));

  posts.forEach((post) => {
    fs.writeFileSync(path.join(BLOG_DIR, `${post.slug}.html`), articlePage(post), "utf8");
  });

  fs.writeFileSync(path.join(BLOG_DIR, "index.html"), indexPage(posts), "utf8");
  fs.writeFileSync(path.join(ROOT, "sitemap.xml"), buildSitemap(posts), "utf8");

  console.log(`✓ ${posts.length} article(s) généré(s) dans /blog`);
  console.log("✓ sitemap.xml mis à jour");
}

main();
