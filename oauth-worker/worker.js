/**
 * Relais OAuth GitHub pour Decap CMS — La Clef de Voûte
 *
 * Hostinger (hébergeur du site) ne peut pas gérer l'échange OAuth nécessaire
 * pour connecter Decap CMS (/admin) à GitHub. Ce petit worker Cloudflare
 * (gratuit) sert uniquement de relais sécurisé pour cette connexion.
 *
 * Voir README.md dans ce dossier pour la procédure de déploiement complète.
 */

const GITHUB_AUTH_URL = "https://github.com/login/oauth/authorize";
const GITHUB_TOKEN_URL = "https://github.com/login/oauth/access_token";

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (url.pathname === "/auth") {
      const redirectUri = `${url.origin}/callback`;
      const authUrl = new URL(GITHUB_AUTH_URL);
      authUrl.searchParams.set("client_id", env.GITHUB_CLIENT_ID);
      authUrl.searchParams.set("redirect_uri", redirectUri);
      authUrl.searchParams.set("scope", "repo,user");
      authUrl.searchParams.set("state", crypto.randomUUID());
      return Response.redirect(authUrl.toString(), 302);
    }

    if (url.pathname === "/callback") {
      const code = url.searchParams.get("code");

      if (!code) {
        return htmlResponse(renderMessage("error", "Code d'autorisation manquant."));
      }

      const tokenRes = await fetch(GITHUB_TOKEN_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json"
        },
        body: JSON.stringify({
          client_id: env.GITHUB_CLIENT_ID,
          client_secret: env.GITHUB_CLIENT_SECRET,
          code
        })
      });

      const data = await tokenRes.json();

      if (data.error || !data.access_token) {
        return htmlResponse(renderMessage("error", data.error_description || data.error || "Échec de l'authentification."));
      }

      return htmlResponse(renderMessage("success", data.access_token));
    }

    return new Response("Relais OAuth GitHub — La Clef de Voûte. Ce service est utilisé uniquement par /admin pour la publication du blog.", {
      status: 200,
      headers: { "Content-Type": "text/plain; charset=utf-8" }
    });
  }
};

function htmlResponse(body) {
  return new Response(body, { headers: { "Content-Type": "text/html; charset=utf-8" } });
}

function renderMessage(status, content) {
  const payload = status === "success" ? { token: content, provider: "github" } : { message: content };
  const messageString = `authorization:github:${status}:${JSON.stringify(payload)}`;

  return [
    "<!doctype html><html><body>",
    "<script>",
    "(function() {",
    `  var messageString = ${JSON.stringify(messageString)};`,
    "  function receiveMessage(e) {",
    "    window.opener.postMessage(messageString, e.origin);",
    "    window.removeEventListener('message', receiveMessage, false);",
    "  }",
    "  window.addEventListener('message', receiveMessage, false);",
    "  window.opener.postMessage('authorizing:github', '*');",
    "})();",
    "</script>",
    "</body></html>"
  ].join("\n");
}
