import http from 'node:http';
import { randomBytes, createHash, scrypt, timingSafeEqual } from 'node:crypto';
import { promisify } from 'node:util';
import { deflateSync, inflateSync, crc32 } from 'node:zlib';
import { readFile } from 'node:fs/promises';
import { all, one, run, transaction, db } from './db.mjs';

const derive = promisify(scrypt);
const token = () => randomBytes(32).toString('hex');
const digest = s => createHash('sha256').update(s).digest('hex');
const fail = (message,status=400) => { throw Object.assign(new Error(message),{status}); };
const string = (v,max=100) => typeof v === 'string' && v.trim() && v.trim().length<=max ? v.trim() : fail('Isian teks tidak valid.');
const number = (v,max=100000000,min=1) => Number.isSafeInteger(v) && v>=min && v<=max ? v : fail('Nilai angka di luar batas.');
const audit = (u,event,id=null) => run('INSERT INTO audit(actor,event,record) VALUES(?,?,?)',u?.id??null,event,id);
const userView = u => ({id:u.id,name:u.name,email:u.email,role:u.role,hasPhoto:!!u.hasPhoto});
const staff = u => ['administrator','pegawai'].includes(u.role);
const role = ok => ok || fail('Anda tidak memiliki akses.',403);
const activeProduct = id => one('SELECT p.* FROM products p JOIN categories c ON c.id=p.category WHERE p.id=? AND p.active=1 AND c.active=1',number(id)) || fail('Barang tidak tersedia.');
const cookie = (res,value,age) => res.setHeader('Set-Cookie', 'torta='+value+'; Path=/; HttpOnly; SameSite=Strict; Max-Age='+age+(process.env.TORTA_HTTPS==='1'?'; Secure':''));
const session = req => {
  const t = /(?:^|;\s*)torta=([0-9a-f]{64})(?:;|$)/.exec(req.headers.cookie||'')?.[1];
  return t && one('SELECT u.id,u.name,u.email,u.role,u.photo IS NOT NULL hasPhoto,s.csrf,s.token FROM sessions s JOIN users u ON u.id=s.user WHERE s.token=? AND s.expires>? AND u.active=1',digest(t),Date.now());
};
function limit(key,max) {
  const now=Date.now();
  run('DELETE FROM attempts WHERE expires<=?',now);
  const count=one('SELECT count FROM attempts WHERE key=?',key)?.count??0;
  if(count>=max) fail('Terlalu banyak percobaan. Coba lagi dalam 15 menit.',429);
  run('INSERT INTO attempts VALUES(?,1,?) ON CONFLICT(key) DO UPDATE SET count=count+1',key,now+900000);
}
async function body(req) {
  if(req.headers['content-type']!=='application/json') fail('Format permintaan tidak sesuai.',415);
  const chunks=[]; let size=0;
  for await (const chunk of req) {
    size+=chunk.length;
    if(size>1500000) fail('Berkas terlalu besar.',413);
    chunks.push(chunk);
  }
  try { const value=JSON.parse(Buffer.concat(chunks)); if(value && typeof value==='object' && !Array.isArray(value)) return value; } catch {}
  fail('Data tidak dapat dibaca.');
}
// Canvas mengirim PNG RGB/RGBA. Tulis ulang hanya piksel terkompresi dan header standar.
function cleanPhoto(value) {
  if(typeof value!=='string' || !value.startsWith('data:image/png;base64,')) fail('Pilih foto PNG atau JPEG.');
  try {
    const b=Buffer.from(value.slice(22),'base64'), parts=[];
    if(b.subarray(0,8).toString('hex')!=='89504e470d0a1a0a') throw Error();
    let h, end=false;
    for(let i=8;i+12<=b.length;) {
      const len=b.readUInt32BE(i), key=b.toString('ascii',i+4,i+8), next=i+12+len;
      if(next>b.length || crc32(b.subarray(i+4,next-4))!==b.readUInt32BE(next-4)) throw Error();
      const part=b.subarray(i+8,next-4);
      if(i===8 && key!=='IHDR') throw Error();
      if(key==='IHDR') {if(h || len!==13) throw Error(); h=part;}
      if(key==='IDAT') parts.push(part);
      if(key==='IEND') {end=true;break;}
      i=next;
    }
    const w=h.readUInt32BE(),height=h.readUInt32BE(4), channels=({2:3,6:4})[h[9]];
    if(!end||!w||!height||w>640||height>640||!channels||h[8]!==8||h[10]||h[11]||h[12]) throw Error();
    const stride=w*channels+1, raw=inflateSync(Buffer.concat(parts),{maxOutputLength:height*stride});
    if(raw.length!==height*stride) throw Error();
    for(let i=0;i<raw.length;i+=stride) if(raw[i]>4) throw Error();
    function chunk(key,data) {
      const bytes=Buffer.concat([Buffer.alloc(4),Buffer.from(key),data,Buffer.alloc(4)]);
      bytes.writeUInt32BE(data.length); bytes.writeUInt32BE(crc32(bytes.subarray(4,-4)),bytes.length-4); return bytes;
    }
    return Buffer.concat([b.subarray(0,8),chunk('IHDR',h),chunk('IDAT',deflateSync(raw)),chunk('IEND',Buffer.alloc(0))]);
  } catch {fail('Foto tidak valid atau terlalu besar.');}
}
async function auth(action,b,req,res) {
  const email=string(b.email,254).toLowerCase(), pass=b.password;
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)||typeof pass!=='string'||pass.length>128) fail('Periksa email dan kata sandi.');
  limit(action+req.socket.remoteAddress,action==='register'?12:60);
  if(action==='register') {
    if(pass.length<12) fail('Kata sandi minimal 12 karakter.');
    const name=string(b.name), salt=token(), hash=(await derive(pass,salt,64)).toString('hex');
    if(one('SELECT id FROM users WHERE email=?',email)) fail('Email tidak dapat didaftarkan.');
    const id=run('INSERT INTO users(name,email,password) VALUES(?,?,?)',name,email,salt+'.'+hash).lastInsertRowid;
    audit(null,'register',id); return {};
  }
  const key='login-'+digest(email); limit(key,10);
  const u=one('SELECT * FROM users WHERE email=? AND active=1',email);
  const [salt,hash]=u?.password.split('.')??['unavailable','0'.repeat(128)];
  if(!timingSafeEqual(await derive(pass,salt,64),Buffer.from(hash,'hex'))||!u) { audit(null,'login-denied'); fail('Email atau kata sandi salah.',401); }
  const t=token(); run('DELETE FROM attempts WHERE key=?',key);
  run('DELETE FROM sessions WHERE expires<=?',Date.now());
  run('INSERT INTO sessions VALUES(?,?,?,?)',digest(t),u.id,token(),Date.now()+28800000);
  audit(u,'login'); cookie(res,t,28800); return {};
}
function state(u) {
  const items=all('SELECT p.id,p.name,p.price,p.stock,p.category,p.image,p.photo IS NOT NULL hasPhoto,p.active,c.name categoryName,c.active categoryActive FROM products p JOIN categories c ON c.id=p.category '+(u&&staff(u)?'':'WHERE p.active=1 AND c.active=1')+' ORDER BY p.id');
  return {user:u?userView(u):null,csrf:u?.csrf,products:items,categories:all('SELECT * FROM categories '+(u&&staff(u)?'':'WHERE active=1')+' ORDER BY name'),
    sales:!u?[]:all('SELECT s.*,u.name buyer FROM sales s JOIN users u ON u.id=s.user '+(staff(u)?'':'WHERE s.user=?')+' ORDER BY s.id DESC',...(staff(u)?[]:[u.id])),
    purchases:u&&staff(u)?all('SELECT * FROM purchases ORDER BY id DESC'):[],
    users:u?.role==='administrator'?all('SELECT id,name,email,role,active FROM users ORDER BY name'):[]};
}
function mutate(action,b,u,res) {
  const id=b.id;
  switch(action) {
    case 'logout': run('DELETE FROM sessions WHERE token=?',u.token); cookie(res,'',0); break;
    case 'profile':
      run('UPDATE users SET name=? WHERE id=?',string(b.name),u.id);
      if(b.photo) run('UPDATE users SET photo=? WHERE id=?',cleanPhoto(b.photo),u.id);
      break;
    case 'user': {
      role(u.role==='administrator'); number(id); role(id!==u.id);
      if(!['administrator','pegawai','customer'].includes(b.role)) fail('Peran tidak valid.');
      if(!run('UPDATE users SET name=?,role=?,active=? WHERE id=?',string(b.name),b.role,number(b.active,1,0),id).changes) fail('Pengguna tidak ditemukan.');
      run('DELETE FROM sessions WHERE user=?',id); break;
    }
    case 'category': {
      role(staff(u)); const name=string(b.name,60);
      if(one('SELECT id FROM categories WHERE name=? COLLATE NOCASE AND id<>?',name,id??0)) fail('Nama kategori sudah digunakan.');
      if(id) run('UPDATE categories SET name=?,active=? WHERE id=?',name,number(b.active,1,0),number(id));
      else run('INSERT INTO categories(name) VALUES(?)',name);
      break;
    }
    case 'product': {
      role(staff(u)); const args=[string(b.name),number(b.category),number(b.price)];
      if(!one('SELECT id FROM categories WHERE id=?',b.category)) fail('Kategori tidak ditemukan.');
      let pid=id;
      if(id) run('UPDATE products SET name=?,category=?,price=?,active=? WHERE id=?',...args,number(b.active,1,0),number(id));
      else pid=run('INSERT INTO products(name,category,price,stock) VALUES(?,?,?,?)',...args,number(b.stock,100000,0)).lastInsertRowid;
      if(b.photo) run('UPDATE products SET photo=? WHERE id=?',cleanPhoto(b.photo),pid);
      break;
    }
    case 'sale': {
      const p=activeProduct(b.pid), qty=number(b.qty,1000);
      if(!run('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?',qty,p.id,qty).changes) fail('Stok tidak mencukupi.');
      run("INSERT INTO sales(user,product,pid,qty,total,status) VALUES(?,?,?,?,?,'Menunggu pembayaran')",u.id,p.name,p.id,qty,p.price*qty); break;
    }
    case 'pay': case 'cancel': {
      const s=one('SELECT * FROM sales WHERE id=?',number(id)); if(!s) fail('Pesanan tidak ditemukan.');
      if(action==='pay') role(staff(u)); else role(staff(u)||s.user===u.id);
      if(s.status!=='Menunggu pembayaran') fail('Pesanan sudah diproses.');
      if(action==='pay') {
        if(!['Tunai','Transfer bank'].includes(b.method)) fail('Metode tidak valid.');
        run("UPDATE sales SET status='Lunas',method=?,paid=CURRENT_TIMESTAMP WHERE id=?",b.method,id);
      } else {run("UPDATE sales SET status='Dibatalkan' WHERE id=?",id); run('UPDATE products SET stock=stock+? WHERE id=?',s.qty,s.pid);}
      break;
    }
    case 'purchase': {
      role(staff(u)); const p=activeProduct(b.pid),qty=number(b.qty,10000),cost=number(b.cost);
      run("INSERT INTO purchases(supplier,pid,product,qty,total,status) VALUES(?,?,?,?,?,'Dipesan')",string(b.supplier),p.id,p.name,qty,qty*cost); break;
    }
    case 'receive': {
      role(staff(u)); const p=one("SELECT * FROM purchases WHERE id=? AND status='Dipesan'",number(id));
      if(!p) fail('PO sudah diproses atau tidak ditemukan.');
      run("UPDATE purchases SET status='Diterima' WHERE id=?",id); run('UPDATE products SET stock=stock+? WHERE id=?',p.qty,p.pid); break;
    }
    default: fail('Halaman tidak tersedia.',404);
  }
  audit(u,action,id??null); return {};
}
const server=http.createServer(async(req,res)=>{
  const send=(code,type,data)=>{res.writeHead(code,{'Content-Type':type});res.end(data);};
  res.setHeader('Cache-Control','no-store');res.setHeader('X-Content-Type-Options','nosniff');
  res.setHeader('Referrer-Policy','no-referrer');res.setHeader('X-Frame-Options','DENY');
  res.setHeader('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' blob: data:; object-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
  try {
    const path=new URL(req.url,'http://localhost').pathname;
    if(req.method==='GET') {
      const u=session(req);
      if(path==='/api/state') return send(200,'application/json',JSON.stringify(state(u)));
      if(path==='/api/avatar' || /^\/api\/photo\/\d+$/.test(path)) {
        const photo=path==='/api/avatar'?u&&one('SELECT photo FROM users WHERE id=?',u.id)?.photo:one('SELECT photo FROM products WHERE id=?',Number(path.split('/').pop()))?.photo;
        if(!photo) fail('Foto tidak tersedia.',404);return send(200,'image/png',Buffer.from(photo));
      }
      const file=path==='/'?'index.html':path.slice(1);
      if(!['index.html','app.js','style.css'].includes(file)&&!/^img\/[a-z0-9-]+\.(jpg|webp|png|svg)$/.test(file)) fail('Tidak ditemukan.',404);
      const types={html:'text/html; charset=utf-8',js:'text/javascript; charset=utf-8',css:'text/css',jpg:'image/jpeg',png:'image/png',webp:'image/webp',svg:'image/svg+xml'};
      const bytes=await readFile(new URL('./public/'+file,import.meta.url)).catch(()=>fail('Tidak ditemukan.',404)); return send(200,types[file.split('.').pop()],bytes);
    }
    if(req.method!=='POST'||!path.startsWith('/api/')) fail('Tidak ditemukan.',404);
    const origin=req.headers.origin;
    if(origin && origin!==((process.env.TORTA_HTTPS==='1'?'https':'http')+'://'+req.headers.host)) fail('Origin tidak sesuai.',403);
    const action=path.slice(5), b=await body(req); let result;
    if(['login','register'].includes(action)) result=await auth(action,b,req,res);
    else {
      const u=session(req); if(!u) fail('Silakan masuk kembali.',401);
      if(req.headers['x-csrf-token']!==u.csrf) fail('Sesi formulir tidak valid.',403);
      result=transaction(()=>mutate(action,b,u,res));
    }
    send(200,'application/json',JSON.stringify(result));
  } catch(e) {
    if(!e.status) console.error('Kesalahan server',e.code||e.name);
    send(e.status||500,'application/json',JSON.stringify({error:e.status?e.message:'Permintaan gagal diproses.'}));
  }
});
const port=Number(process.env.PORT||3000);
server.requestTimeout=30000;
server.listen(port,'127.0.0.1',()=>console.log('Torta Shop berjalan di http://127.0.0.1:'+server.address().port));
