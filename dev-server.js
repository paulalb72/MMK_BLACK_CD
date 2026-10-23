// Lokale Vorschau ohne PHP: node dev-server.js -> http://localhost:8080
// Bildet /bild/?n=, /api/anfrage.php (ohne Mailversand), Slash-Redirects und die 404-Seite nach.
const http = require('http'), fs = require('fs'), path = require('path');
const ROOT = __dirname, PORT = process.env.PORT || 8080;
const UMLEITUNGEN = { '/leistungen/ki-check/': '/leistungen/prozess-check/', '/leistungen/ki-assistenten/': '/leistungen/wissensassistent/', '/leistungen/tools/': '/leistungen/softwaretools/', '/leistungen/automatisierung/': '/leistungen/', '/leistungen/schulung/': '/leistungen/', '/ablauf/': '/#vorgehen' };
const TYPES = { '.html': 'text/html; charset=utf-8', '.txt': 'text/plain', '.xml': 'application/xml', '.webp': 'image/webp', '.css': 'text/css; charset=utf-8', '.woff2': 'font/woff2' };

function send(res, code, file, headers = {}) {
  res.writeHead(code, { 'Content-Type': TYPES[path.extname(file)] || 'application/octet-stream', ...headers });
  fs.createReadStream(file).pipe(res);
}

http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const p = decodeURIComponent(url.pathname);

  const ziel = UMLEITUNGEN[p.endsWith('/') ? p : p + '/'];
  if (ziel) { res.writeHead(301, { Location: ziel }); return res.end(); }
  if (/^\/branchen(\/|$)/.test(p)) { res.writeHead(301, { Location: '/' }); return res.end(); }

  if (p === '/bild/' || p === '/bild/index.php') {
    const n = url.searchParams.get('n') || '';
    const f = path.join(ROOT, 'bild/img', n + '.webp');
    if (/^[a-z0-9-]{1,64}$/.test(n) && fs.existsSync(f)) return send(res, 200, f, { 'Cache-Control': 'public, max-age=31536000, immutable' });
    res.writeHead(404); return res.end();
  }

  if (p === '/api/anfrage.php') {
    if (req.method !== 'POST') { res.writeHead(303, { Location: '/' }); return res.end(); }
    let body = '';
    req.on('data', c => body += c);
    return req.on('end', () => {
      const d = new URLSearchParams(body);
      console.log('\n[Anfrage]', Object.fromEntries([...d.keys()].map(k => [k, d.getAll(k).join(', ')])));
      const ok = d.get('website_url') || (d.get('name') && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.get('email') || '') && d.get('datenschutz') === 'ja');
      res.writeHead(303, { Location: ok ? '/danke/' : '/fehler/' }); res.end();
    });
  }

  if (p === '/statistik/erfassen.php') {
    let body = '';
    req.on('data', c => body += c);
    return req.on('end', () => { console.log('[Statistik]', body); res.writeHead(204); res.end(); });
  }

  const f = path.join(ROOT, p);
  if (!f.startsWith(ROOT) || /(^|\/)(\.|api\/|bild\/img\/|statistik\/)/.test(p.slice(1))) return send(res, 404, path.join(ROOT, 'fehler/index.html'));
  if (fs.existsSync(f) && fs.statSync(f).isDirectory()) {
    if (!p.endsWith('/')) { res.writeHead(301, { Location: p + '/' + url.search }); return res.end(); }
    const idx = path.join(f, 'index.html');
    if (fs.existsSync(idx)) return send(res, 200, idx);
  } else if (fs.existsSync(f) && !f.endsWith('.php') && !f.endsWith('.js')) {
    return send(res, 200, f);
  }
  send(res, 404, path.join(ROOT, 'fehler/index.html'));
}).listen(PORT, () => console.log(`MakeMyKI läuft auf http://localhost:${PORT}`));
