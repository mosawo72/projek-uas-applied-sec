'use strict';
const root=document.querySelector('main'), nav=document.querySelector('nav'), notice=document.querySelector('#notice');
let s, filter=0, nextProduct=0, busy=false;
const e=v=>String(v??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
const rp=v=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(v);
const staff=()=>s.user && s.user.role!=='customer';
const admin=()=>s.user?.role==='administrator';
const link=(hash,title,cls='')=>`<a class="${cls}" href="#${hash}">${title}</a>`;
const field=(label,input)=>`<label class="field">${label}${input}</label>`;
const input=(name,type='text',value='',attrs='')=>`<input name="${name}" type="${type}" value="${e(value)}" ${attrs} required>`;
const options=(rows,selected)=>rows.map(([v,t])=>`<option value="${e(v)}" ${String(v)===String(selected)?'selected':''}>${e(t)}</option>`).join('');
const select=(name,rows,current)=>`<select name="${name}" required>${options(rows,current)}</select>`;
const form=(action,content,label,id)=>`<form class="stack" data-action="${action}" ${id?`data-id="${id}"`:''}>${content}<div><button class="primary">${label}</button></div></form>`;
const panel=(title,content)=>`<section class="panel"><h2>${title}</h2>${content}</section>`;
const table=(heads,rows)=>rows.length?`<div class="table-wrap"><table><thead><tr>${heads.map(h=>`<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(x=>`<td>${x}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`:'<p>Belum ada data.</p>';
const photo=field('Gambar (PNG atau JPEG)', '<input type="file" name="picture" accept="image/png,image/jpeg">');
const active=(v)=>field('Status',select('active',[[1,'Aktif'],[0,'Nonaktif']],v));
const priceField=(name,label,v)=>field(label,input(name,'number',v,'min="1" max="100000000"'));
const available=()=>s.products.filter(p=>p.active && p.categoryActive);
const productSelect=()=>field('Barang',select('pid',available().map(p=>[p.id,p.name])));
const qty=(max)=>field('Jumlah',input('qty','number',1,`min="1" max="${max}"`));
const message=t=>{notice.textContent=t; if(t)notice.scrollIntoView({block:'nearest'});};
const route=()=>location.hash.slice(1)||'katalog';
function catalog() {
 const products=available().filter(p=>!filter||p.category===filter);
 return `<section class="hero"><div class="hero-copy"><span class="kicker">PATISERI TORTA SHOP</span><h1>Sepotong kue,<br><em>sejuta cerita.</em></h1><p>Pilih kue untuk teman minum kopi, hadiah kecil, atau perayaan istimewa Anda.</p><button class="primary" data-scroll>Kunjungi etalase</button></div><img class="hero-photo" src="/img/hero.jpg" alt="Kue cokelat berhias ceri"></section>
 <section id="etalase" class="catalog"><div class="section-head"><h2>Etalase kue</h2><span class="muted">${products.length} pilihan kue</span></div><div class="filters">${[[0,'Semua kue'],...s.categories.filter(c=>c.active).map(c=>[c.id,c.name])].map(([id,name])=>`<button data-filter="${id}" aria-pressed="${id===filter}">${e(name)}</button>`).join('')}</div><div class="grid">${products.map(p=>`<article class="card">${p.hasPhoto||p.image?`<img src="${p.hasPhoto?'/api/photo/'+p.id:'/img/'+e(p.image)}" alt="${e(p.name)}">`:'<div class="placeholder">Torta Shop</div>'}<div class="card-body"><small>${e(p.categoryName)}</small><h3>${e(p.name)}</h3><p class="price">${rp(p.price)}</p><div class="actions">${p.stock?link('pesan/'+p.id,'Pesan kue','secondary'):'<span>Stok habis</span>'}</div></div></article>`).join('')||'Belum ada kue dalam kategori ini.'}</div></section>`;
}
function auth(register) {
 const title=register?'Daftar akun':'Masuk';
 return `<div class="narrow">${panel(title,form(register?'register':'login',(register?field('Nama lengkap',input('name','text','','maxlength="100" autocomplete="name"')):'')+field('Email',input('email','email','','autocomplete="email" maxlength="254"'))+field(register?'Kata sandi (minimal 12 karakter)':'Kata sandi',input('password','password','',register?'minlength="12" maxlength="128" autocomplete="new-password"':'autocomplete="current-password" maxlength="128"')),title)+`<p class="under">${register?link('masuk','Sudah punya akun? Masuk'):link('daftar','Belum punya akun? Daftar')}</p>`)}</div>`;
}
function sales() {
 const rows=s.sales.map(x=>[`#${x.id}`,e(x.buyer),e(x.product),x.qty,rp(x.total),e(x.status),x.status==='Menunggu pembayaran'?`<div class="stack">${staff()?form('pay',field('Metode pembayaran',select('method',[['Tunai','Tunai'],['Transfer bank','Transfer bank']])),'Konfirmasi lunas',x.id):'Bayar melalui kasir.'}${form('cancel','','Batalkan pesanan',x.id)}</div>`:e(x.method||'—')]);
 return panel(staff()?'Penjualan':'Pesanan saya',table(['Pesanan','Pemesan','Barang','Jumlah','Total','Status','Pembayaran'],rows));
}
function purchase() {
 return panel('Buat purchase order',form('purchase',field('Nama pemasok',input('supplier'))+productSelect()+qty(10000)+priceField('cost','Harga beli per unit (Rp)',''),'Pesan ke pemasok'))+panel('Pembelian dari pemasok',table(['PO','Pemasok','Barang','Jumlah','Total','Status','Penerimaan'],s.purchases.map(x=>[`#${x.id}`,e(x.supplier),e(x.product),x.qty,rp(x.total),e(x.status),x.status==='Dipesan'?form('receive','','Terima barang',x.id):'Selesai'])));
}
function master(kind,id) {
 const groups={barang:['Barang',s.products],kategori:['Kategori',s.categories],pengguna:['Pengguna',s.users]};
 const [label,items]=groups[kind], item=id?items.find(x=>x.id===id):{};
 if(!item)return panel('Data tidak ditemukan','');
 let editor='';
 if(kind==='barang') editor=form('product',field('Nama barang',input('name','text',item.name))+field('Kategori',select('category',s.categories.filter(c=>c.active||c.id===item.category).map(c=>[c.id,c.name]),item.category))+priceField('price','Harga jual (Rp)',item.price)+(id?active(item.active):field('Stok awal',input('stock','number',0,'min="0" max="100000"')))+photo,'Simpan barang',id);
 if(kind==='kategori') editor=form('category',field('Nama kategori',input('name','text',item.name,'maxlength="60"'))+(id?active(item.active):''),'Simpan kategori',id);
 if(kind==='pengguna'&&id) editor=form('user',field('Nama',input('name','text',item.name))+field('Peran',select('role',['administrator','pegawai','customer'].map(r=>[r,r]),item.role))+active(item.active),'Simpan pengguna',id);
 return panel('Master '+label,table(['Nama',kind==='barang'?'Stok':kind==='pengguna'?'Peran':'ID','Status',''],items.map(x=>[e(x.name)+(x.email?`<br><small>${e(x.email)}</small>`:''),e(x.stock??x.role??x.id),x.active?'Aktif':'Nonaktif',kind==='pengguna'&&x.id===s.user.id?'Akun Anda':link(kind+'/'+x.id,'Ubah')])))+(editor?panel((id?'Ubah ':'Tambah ')+label,editor+(id?link(kind,'Kembali ke daftar'):'')):'');
}
const localDate=x=>new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Jakarta'}).format(new Date(x.replace(' ','T')+'Z'));
function report(from='',to='') {
 const rows=s.sales.filter(x=>x.status==='Lunas'&&(!from||localDate(x.paid)>=from)&&(!to||localDate(x.paid)<=to));
 return panel('Periode penjualan',`<form class="actions" data-action="period">${field('Mulai',`<input type="date" name="from" value="${e(from)}">`)}${field('Sampai',`<input type="date" name="to" value="${e(to)}">`)}<button>Terapkan</button><button type="button" data-print>Cetak / simpan PDF</button></form>`)+`<section id="report" class="panel"><small>Torta Shop</small><h2>Laporan penjualan</h2><p>${e(from||'Awal pencatatan')} hingga ${e(to||'sekarang')} · WIB</p>${table(['Tanggal lunas','Pesanan','Barang','Jumlah','Metode','Total'],rows.map(x=>[localDate(x.paid),'#'+x.id,e(x.product),x.qty,e(x.method),rp(x.total)]))}<p class="under"><strong>Total penjualan lunas &nbsp; ${rp(rows.reduce((t,x)=>t+x.total,0))}</strong></p></section>`;
}
function render(){
 const [page,arg]=route().split('/'),id=Number(arg);
 nav.innerHTML=link('katalog','Etalase')+(s.user?link('pesanan',staff()?'Penjualan':'Pesanan saya')+(staff()?link('kelola','Kelola toko'):'')+link('profil','Profil','secondary'):link('masuk','Masuk')+link('daftar','Daftar','primary'));
 root.className=page==='katalog'?'':'wrap content';
 if(page==='katalog')root.innerHTML=catalog();
 else if(page==='masuk'||page==='daftar')root.innerHTML=auth(page==='daftar');
 else if(!s.user){if(page==='pesan')nextProduct=id; location.hash='masuk';return;}
 else if(page==='pesan'){
  const p=available().find(x=>x.id===id);
  root.innerHTML=p?panel(e(p.name),`<p>${rp(p.price)} per kue · Stok ${p.stock}</p>`+(p.stock?form('sale',`<input type="hidden" name="pid" value="${id}">`+qty(Math.min(1000,p.stock))+`<output id="subtotal">Total ${rp(p.price)}</output><p>Ambil dan bayar pesanan di kasir Torta Shop.</p>`,'Buat pesanan'):'Stok habis.')):panel('Barang tidak tersedia','');
 } else if(page==='pesanan')root.innerHTML=sales();
 else if(page==='profil')root.innerHTML=panel('Profil saya',`${s.user.hasPhoto?'<img class="avatar" src="/api/avatar" alt="Foto profil">':''}<p>${e(s.user.email)} · ${e(s.user.role)}</p>`+form('profile',field('Nama lengkap',input('name','text',s.user.name))+photo,'Simpan profil')+form('logout','','Keluar'));
 else if(staff()&&['kelola','pembelian','barang','kategori','laporan',...(admin()?['pengguna']:[])].includes(page)){
  root.innerHTML=`<div class="actions subnav">${[['barang','Barang'],['kategori','Kategori'],['pembelian','Pembelian / PO'],['laporan','Laporan penjualan'],...(admin()?[['pengguna','Pengguna']]:[])].map(([p,t])=>link(p,t,p===page?'primary':'secondary')).join('')}</div>`+(page==='pembelian'?purchase():page==='laporan'?`<div id="report-area">${report()}</div>`:master(page==='kelola'?'barang':page,id));
 } else root.innerHTML=panel('Halaman tidak tersedia',link('katalog','Kembali ke etalase'));
}
async function refresh(){const r=await fetch('/api/state');if(!r.ok)throw Error('Gagal memuat toko.');s=await r.json();render();}
async function resize(file){
 if(!['image/png','image/jpeg'].includes(file.type)||file.size>5000000)throw Error('Pilih PNG/JPEG berukuran maksimal 5 MB.');
 const img=await createImageBitmap(file),scale=Math.min(1,480/Math.max(img.width,img.height)),canvas=document.createElement('canvas');
 canvas.width=Math.max(1,Math.round(img.width*scale));canvas.height=Math.max(1,Math.round(img.height*scale));canvas.getContext('2d').drawImage(img,0,0,canvas.width,canvas.height);img.close();
 return canvas.toDataURL('image/png');
}
document.addEventListener('click',ev=>{
 const b=ev.target.closest('button'); if(!b)return;
 if(b.hasAttribute('data-filter')){filter=Number(b.dataset.filter);render();}
 if(b.hasAttribute('data-scroll'))document.querySelector('#etalase').scrollIntoView();
 if(b.hasAttribute('data-print'))window.print();
});
document.addEventListener('input',ev=>{
 if(ev.target.name==='qty' && document.querySelector('#subtotal')){
  const p=s.products.find(p=>p.id===Number(ev.target.form.elements.pid.value));document.querySelector('#subtotal').textContent='Total '+rp(p.price*Number(ev.target.value));
 }
});
document.addEventListener('submit',async ev=>{
 ev.preventDefault(); if(busy)return;
 const f=ev.target,a=f.dataset.action,b=Object.fromEntries(new FormData(f));
 if(a==='period'){if(b.from&&b.to&&b.from>b.to)return message('Tanggal akhir harus sama atau sesudah tanggal mulai.');document.querySelector('#report-area').innerHTML=report(b.from,b.to);message('');return;}
 if(['pay','cancel','receive'].includes(a)&&!confirm(({pay:'Pembayaran sudah Anda terima?',cancel:'Batalkan pesanan dan kembalikan stok?',receive:'Barang dari pemasok sudah diterima?'})[a]))return;
 busy=true; const button=f.querySelector('button');button.disabled=true;
 try {
  if(b.picture?.size)b.photo=await resize(b.picture);delete b.picture;
  for(const k of ['stock','active','qty','price','cost','pid','category'])if(k in b)b[k]=Number(b[k]);
  if(f.dataset.id)b.id=Number(f.dataset.id);
  const r=await fetch('/api/'+a,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':s.csrf||''},body:JSON.stringify(b)});
  if(!r.ok)throw Error((await r.json()).error);
  const dest=({login:nextProduct?'pesan/'+nextProduct:'katalog',register:'masuk',logout:'katalog',sale:'pesanan',product:'barang',category:'kategori',user:'pengguna'})[a];
  if(a==='login'||a==='logout')nextProduct=0;
  if(dest)history.pushState(null,'','#'+dest);
  await refresh();
  message(({register:'Akun berhasil dibuat. Silakan masuk.',sale:'Pesanan tercatat. Bayar saat pengambilan di kasir.',login:'Berhasil masuk.',logout:'Anda telah keluar.',receive:'Barang diterima dan stok diperbarui.',pay:'Pembayaran tercatat.'})[a]||'Perubahan tersimpan.');
 } catch(err){message(err.message);}finally{busy=false;button.disabled=false;}
});
window.addEventListener('hashchange',()=>{message('');if(s)render();scrollTo(0,0);});
refresh().catch(err=>message(err.message));
