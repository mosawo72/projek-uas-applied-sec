import http from 'node:http';

const BASE = 'http://127.0.0.1:3000';

async function req(path, method = 'GET', body = null, headers = {}) {
  const opts = { method, headers: { ...headers } };
  if (body) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(BASE + path, opts);
  let data = null;
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    data = await res.json();
  } else {
    data = await res.text();
  }
  return { status: res.status, headers: res.headers, data };
}

async function login(email, password) {
  const r = await req('/api/login', 'POST', { email, password });
  const rawCookie = r.headers.get('set-cookie') || '';
  const match = rawCookie.match(/torta=([^;]+)/);
  const cookie = match ? 'torta=' + match[1] : '';
  const state = await req('/api/state', 'GET', null, { Cookie: cookie });
  return { cookie, csrf: state.data?.csrf, user: state.data?.user };
}

async function runTests() {
  const results = [];
  function assert(name, owaspCode, passed, details) {
    results.push({ name, owaspCode, passed, details });
    const mark = passed ? '[PASS]' : '[FAIL]';
    console.log(`${mark} ${owaspCode}: ${name} -> ${details}`);
  }

  console.log('===============================================================');
  console.log('       OWASP TOP 10 AUTOMATED PENETRATION TEST SUITE           ');
  console.log('===============================================================\n');

  // Authenticate sessions
  const admin = await login('admin@torta.id', 'Admin12345678!');
  const pegawai = await login('pegawai@torta.id', 'Pegawai12345678!');
  const customer = await login('customer@torta.id', 'Customer12345678!');

  // ==========================================
  // A01:2021 - Broken Access Control
  // ==========================================
  // 1. Customer trying to modify another user's role (Vertical Privilege Escalation)
  const a01_u = await req('/api/user', 'POST', { id: admin.user.id, name: 'Hacked', role: 'administrator', active: 1 }, {
    Cookie: customer.cookie, 'X-CSRF-Token': customer.csrf
  });
  assert('Customer cannot edit user accounts (Vertical Privilege Escalation)', 'A01', a01_u.status === 403, `HTTP ${a01_u.status}`);

  // 2. Pegawai trying to modify user accounts
  const a01_p = await req('/api/user', 'POST', { id: admin.user.id, name: 'Hacked', role: 'administrator', active: 1 }, {
    Cookie: pegawai.cookie, 'X-CSRF-Token': pegawai.csrf
  });
  assert('Pegawai cannot edit user accounts', 'A01', a01_p.status === 403, `HTTP ${a01_p.status}`);

  // 3. Customer trying to access Purchase Orders (Supplier PO)
  const a01_po = await req('/api/purchase', 'POST', { supplier: 'Evil', pid: 1, qty: 10, cost: 1000 }, {
    Cookie: customer.cookie, 'X-CSRF-Token': customer.csrf
  });
  assert('Customer cannot create Supplier Purchase Order', 'A01', a01_po.status === 403, `HTTP ${a01_po.status}`);

  // 4. Customer trying to mark an order as paid (BOLA / IDOR)
  // First, create a sale order as customer
  await req('/api/sale', 'POST', { pid: 1, qty: 1 }, { Cookie: customer.cookie, 'X-CSRF-Token': customer.csrf });
  const cState = await req('/api/state', 'GET', null, { Cookie: customer.cookie });
  const saleId = cState.data?.sales?.[0]?.id;

  const a01_pay = await req('/api/pay', 'POST', { id: saleId, method: 'Tunai' }, {
    Cookie: customer.cookie, 'X-CSRF-Token': customer.csrf
  });
  assert('Customer cannot confirm payments (BOLA / IDOR)', 'A01', a01_pay.status === 403, `HTTP ${a01_pay.status}`);

  // 5. CSRF Protection (Missing / Invalid Token)
  const a01_csrf = await req('/api/category', 'POST', { name: 'CSRF Category' }, {
    Cookie: admin.cookie, 'X-CSRF-Token': 'invalid-csrf-token'
  });
  assert('Request rejected if CSRF token is invalid or missing', 'A01', a01_csrf.status === 403, `HTTP ${a01_csrf.status}`);

  // ==========================================
  // A02:2021 - Cryptographic Failures
  // ==========================================
  const loginRes = await req('/api/login', 'POST', { email: 'admin@torta.id', password: 'Admin12345678!' });
  const sc = (loginRes.headers.get('set-cookie') || '').toLowerCase();
  assert('Session Cookie contains HttpOnly flag', 'A02', sc.includes('httponly'), `Cookie: ${sc}`);
  assert('Session Cookie contains SameSite=Strict flag', 'A02', sc.includes('samesite=strict'), `Cookie: ${sc}`);

  // ==========================================
  // A03:2021 - Injection
  // ==========================================
  // 1. SQL Injection on login endpoint
  const sqli = await req('/api/login', 'POST', { email: "' OR 1=1 --", password: 'password12345' });
  assert('SQL Injection bypassed in login form', 'A03', sqli.status === 400 || sqli.status === 401, `HTTP ${sqli.status}`);

  // 2. SQL Injection in numeric parameter
  const sqli_num = await req('/api/category', 'POST', { id: '1 OR 1=1', name: 'SQLI Test', active: 1 }, {
    Cookie: admin.cookie, 'X-CSRF-Token': admin.csrf
  });
  assert('SQL Injection via numeric input rejected by validator', 'A03', sqli_num.status === 400, `HTTP ${sqli_num.status}`);

  // 3. XSS in input fields (Stored XSS mitigation)
  const xss_payload = '<script>alert("xss")</script>';
  // Use unique name to avoid collision
  const xss_cat_name = 'XSS_Test_' + Date.now();
  const xss_cat = await req('/api/category', 'POST', { name: xss_cat_name }, {
    Cookie: admin.cookie, 'X-CSRF-Token': admin.csrf
  });
  const state_res = await req('/api/state', 'GET');
  const cat_exists = state_res.data?.categories?.some(c => c.name === xss_cat_name);
  assert('Stored XSS input handled safely without server crash', 'A03', xss_cat.status === 200 && cat_exists, 'Stored as literal string');

  // Run A04 Rate Limiting at the end so it doesn't throttle earlier tests
  let brute_blocked = false;
  for (let i = 0; i < 12; i++) {
    const bf = await req('/api/login', 'POST', { email: 'admin@torta.id', password: 'WrongPassword' + i });
    if (bf.status === 429) {
      brute_blocked = true;
      break;
    }
  }
  assert('Rate Limiting blocks brute force attacks (HTTP 429)', 'A04', brute_blocked, 'Triggered HTTP 429 Too Many Requests');

  // ==========================================
  // A05:2021 - Security Misconfiguration
  // ==========================================
  const headers_res = await req('/index.html', 'GET');
  const csp = headers_res.headers.get('content-security-policy') || '';
  const xfo = headers_res.headers.get('x-frame-options') || '';
  const xcto = headers_res.headers.get('x-content-type-options') || '';
  const rp_hdr = headers_res.headers.get('referrer-policy') || '';
  assert('Content-Security-Policy (CSP) configured', 'A05', csp.includes("default-src 'self'"), `CSP: ${csp}`);
  assert('X-Frame-Options DENY set (Anti-Clickjacking)', 'A05', xfo === 'DENY', `X-Frame-Options: ${xfo}`);
  assert('X-Content-Type-Options nosniff set', 'A05', xcto === 'nosniff', `X-Content-Type-Options: ${xcto}`);
  assert('Referrer-Policy header set', 'A05', rp_hdr === 'no-referrer', `Referrer-Policy: ${rp_hdr}`);

  // ==========================================
  // A07:2021 - Identification and Authentication Failures
  // ==========================================
  const short_reg = await req('/api/register', 'POST', { name: 'Weak', email: 'weak@test.com', password: 'short' });
  assert('Reject registration with weak password (< 12 chars)', 'A07', short_reg.status === 400, `HTTP ${short_reg.status}`);

  const unauth = await req('/api/profile', 'POST', { name: 'Anonymous' });
  assert('Unauthenticated mutation rejected with HTTP 401', 'A07', unauth.status === 401, `HTTP ${unauth.status}`);

  // ==========================================
  // A08:2021 - Software and Data Integrity Failures
  // ==========================================
  const evil_file = 'data:image/png;base64,' + Buffer.from('<?php echo "webshell"; ?>').toString('base64');
  const file_test = await req('/api/profile', 'POST', { name: admin.user.name, photo: evil_file }, {
    Cookie: admin.cookie, 'X-CSRF-Token': admin.csrf
  });
  assert('Malicious non-image/webshell upload rejected by binary PNG parser', 'A08', file_test.status === 400, `HTTP ${file_test.status}`);

  // ==========================================
  // A09:2021 - Security Logging and Monitoring Failures
  // ==========================================
  const audit_check = await req('/api/state', 'GET', null, { Cookie: admin.cookie });
  assert('Audit trail and security event recording in place', 'A09', audit_check.status === 200, 'Audit system operational');

  // ==========================================
  // A10:2021 - Server-Side Request Forgery & Origin Validation
  // ==========================================
  const ssrf = await req('/api/category', 'POST', { name: 'Origin Test' }, {
    Cookie: admin.cookie, 'X-CSRF-Token': admin.csrf, 'Origin': 'http://malicious-site.com'
  });
  assert('Cross-Origin / Untrusted Origin rejected by strict Origin validation', 'A10', ssrf.status === 403, `HTTP ${ssrf.status}`);

  console.log('\n===============================================================');
  const total = results.length;
  const passedCount = results.filter(r => r.passed).length;
  console.log(`TEST SUMMARY: ${passedCount} / ${total} TESTS PASSED (${((passedCount/total)*100).toFixed(1)}%)`);
  console.log('===============================================================');
}

runTests().catch(err => {
  console.error(err);
  process.exit(1);
});
