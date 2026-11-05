/* ============================================================
 *  Dhyra v5 — halaman modul Tahap 3
 *  Kartu stok · Kartu member · Inventaris Aset · SDM & Penggajian · KPI · Toko Online
 *  Memakai fungsi bersama dari kasir.html (S, api, call, modal, ask, toast, ...).
 * ============================================================ */
(function(){
const lv=()=>S.lvl||0;
const tgl=s=>s?fd(String(s)):'–';
const BULAN=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const perNama=p=>p?BULAN[+p.slice(5,7)-1]+' '+p.slice(0,4):'';
const perNow=()=>ymd(today()).slice(0,7);
const perShift=(p,n)=>{const d=new Date(+p.slice(0,4),+p.slice(5,7)-1+n,1);return d.getFullYear()+'-'+pad(d.getMonth()+1)};
const field=(lbl,inner,cls='')=>`<div class="fg ${cls}"><label class="lb">${lbl}</label>${inner}</div>`;
const B=(cls,t)=>`<span class="bdg ${cls}">${esc(t)}</span>`;
const WA=(hp,txt)=>{let n=String(hp||'').replace(/\D/g,'');if(n.startsWith('0'))n='62'+n.slice(1);return n?`https://wa.me/${n}?text=${encodeURIComponent(txt)}`:''};
const kpiBox=(l,v,i,sub='')=>`<div class="card kpi"><div class="kl"><span class="ic">${ic(i)}</span>${l}</div><div class="kv">${v}</div>${sub?`<div class="muted sm">${sub}</div>`:''}</div>`;
const perPicker=(id,p)=>`<div class="perp"><button class="ib" data-act="xper" data-for="${id}" data-n="-1" aria-label="Bulan sebelumnya">${ic('cl')}</button><b id="${id}" data-p="${p}">${perNama(p)}</b><button class="ib" data-act="xper" data-for="${id}" data-n="1" aria-label="Bulan berikutnya">${ic('cr')}</button></div>`;
const PERCB={};
ACT.xper=b=>{const el=document.getElementById(b.dataset.for);if(!el)return;const p=perShift(el.dataset.p,+b.dataset.n);el.dataset.p=p;el.textContent=perNama(p);(PERCB[b.dataset.for]||(()=>{}))(p)};
const fileToData=(inp,max)=>new Promise((ok,no)=>{const f=inp.files&&inp.files[0];if(!f)return ok(null);compress(f,max||900).then(ok,no)});
const akunOpts=(list,sel)=>(list||[]).map(a=>`<option value="${esc(a.kode)}" ${a.kode===sel?'selected':''}>${esc(a.kode)} · ${esc(a.nama)}</option>`).join('');
/* pemilih produk sederhana */
function pickProd(box,onPick,ph){
  box.innerHTML=`<div class="ppk"><div class="sb">${ic('search')}<input class="in" placeholder="${ph||'Cari nama / SKU / barcode'}" autocomplete="off"></div><div class="sugg hide"></div></div>`;
  const q=$('input',box),sg=$('.sugg',box);let list=[],ix=0;
  const draw=()=>{if(!list.length){sg.classList.add('hide');return}sg.innerHTML=list.map((p,i)=>`<button type="button" class="${i===ix?'on':''}" data-i="${i}"><span><b>${esc(p.nama)}</b><br><small class="mono">${esc(p.sku||'')}${p.barcode?' · '+esc(p.barcode):''}</small></span><small>stok ${nf(p.stokTotal!=null?p.stokTotal:p.stok)}</small></button>`).join('');sg.classList.remove('hide')};
  q.addEventListener('input',()=>{const t=q.value.trim();list=t?S.prods.filter(p=>match(p,t)).slice(0,8):[];ix=0;draw()});
  q.addEventListener('keydown',e=>{if(e.key==='ArrowDown'){e.preventDefault();ix=Math.min(ix+1,list.length-1);draw()}else if(e.key==='ArrowUp'){e.preventDefault();ix=Math.max(ix-1,0);draw()}
    else if(e.key==='Enter'){e.preventDefault();const t=q.value.trim().toLowerCase();const p=S.prods.find(p=>(p.barcode&&p.barcode.toLowerCase()===t)||(p.sku&&p.sku.toLowerCase()===t))||list[ix];if(p){onPick(p);q.value='';list=[];draw()}}});
  sg.addEventListener('mousedown',e=>{const b=e.target.closest('[data-i]');if(b){e.preventDefault();onPick(list[+b.dataset.i]);q.value='';list=[];draw()}});
  q.addEventListener('blur',()=>setTimeout(()=>{list=[];draw()},150));
  return q;
}

/* ======================================================================
 *  KARTU STOK (Inventaris)
 * ==================================================================== */
NAV.push({id:'kartustok',t:'Kartu stok',i:'book',m:'inventaris',lv:1});
PS.ks={pid:'',lok:'',from:ymd(addD(today(),-30)),to:ymd(today()),res:null};
ACT.kstok=b=>{PS.ks.pid=b.dataset.id;PS.ks.lok=S.curLok||'';go('kartustok')};
PAGES.kartustok=function(){
  const P=PS.ks;
  $('#view').innerHTML=head('Kartu stok','Riwayat masuk–keluar barang dengan saldo berjalan per lokasi. Cocokkan dengan hitungan fisik saat opname.',
    `<button class="btn" data-act="xksx" data-f="xlsx">${ic('xls')}Excel</button><button class="btn" data-act="xksx" data-f="pdf">${ic('pdf')}PDF</button>`)+
   `<div class="card tb"><div id="kspick" style="flex:2;min-width:240px"></div><select class="in" id="kslok" style="flex:1;min-width:160px">${lokOpts(P.lok,true).replace('Semua lokasi (total)','Semua lokasi')}</select>
    <input type="date" class="in" id="ksf" value="${P.from}" style="flex:0 0 160px"><input type="date" class="in" id="kst" value="${P.to}" style="flex:0 0 160px"></div><div id="ksv"></div>`;
  pickProd($('#kspick'),p=>{P.pid=p.id;loadKs()},'Pilih produk: ketik nama / SKU / scan barcode');
  $('#kslok').onchange=e=>{P.lok=e.target.value;loadKs()};$('#ksf').onchange=e=>{P.from=e.target.value;loadKs()};$('#kst').onchange=e=>{P.to=e.target.value;loadKs()};
  if(P.pid)loadKs();else $('#ksv').innerHTML=`<div class="card">${emptyBox('Pilih produk untuk melihat kartu stoknya','book')}</div>`;
};
async function loadKs(){
  const P=PS.ks,el=$('#ksv');if(!el)return;el.innerHTML='<div class="card"><div class="skel" style="margin:14px"></div></div>';
  try{P.res=await api('kartuStok',{pid:P.pid,lokasi:P.lok,from:P.from,to:P.to},true)}catch(e){el.innerHTML=failBox(e.message);return}
  const r=P.res,p=byId(r.produk.id)||r.produk,jc={'Penjualan':'err','Penjualan online':'err','Stok masuk':'ok','Mutasi masuk':'ok','Mutasi keluar':'warn','Retur pemasok':'warn','Batal penjualan':'pri','Stok awal':'pri'};
  el.innerHTML=`<div class="card pad ksh"><div class="pcell">${thumb(p)}<div><b style="font-size:16px">${esc(r.produk.nama)}</b><div class="muted sm"><span class="mono b">${esc(r.produk.sku||'-')}</span>${r.produk.barcode?' · '+esc(r.produk.barcode):''} · ${esc(r.produk.satuan)} · ${esc(r.lokasiNama)}</div></div></div>
    <div class="kss"><div><small>Saldo awal ${fd(r.from)}</small><b>${nf(r.awal)}</b></div><div><small>Masuk</small><b class="ok-t">+${nf(r.masuk)}</b></div><div><small>Keluar</small><b class="err-t">−${nf(r.keluar)}</b></div><div><small>Saldo akhir ${fd(r.to)}</small><b>${nf(r.akhir)}</b></div></div></div>
   <div class="card np"><div class="tw">${r.rows.length?`<table class="tbl"><thead><tr><th>Waktu</th><th>Jenis</th><th>Referensi</th><th>Keterangan</th>${r.lokasi?'':'<th>Lokasi</th>'}<th class="r">Masuk</th><th class="r">Keluar</th><th class="r">Saldo</th><th>Oleh</th></tr></thead>
    <tbody><tr class="muted"><td>${fd(r.from)}</td><td colspan="${r.lokasi?5:6}"><i>Saldo awal</i></td><td class="r b">${nf(r.awal)}</td><td></td></tr>${r.rows.map(x=>`<tr><td style="white-space:nowrap">${fd(x.t)} <span class="muted">${ft(x.t)}</span></td><td>${B(jc[x.jenis]||'',x.jenis)}</td><td class="mono sm">${esc(x.ref)}</td><td class="sm">${esc(x.ket)}</td>${r.lokasi?'':`<td class="sm">${esc(x.lok)}</td>`}
     <td class="r ok-t">${x.masuk?'+'+nf(x.masuk):''}</td><td class="r err-t">${x.keluar?'−'+nf(x.keluar):''}</td><td class="r b">${nf(x.saldo)}</td><td class="sm muted">${esc(x.user)}</td></tr>`).join('')}</tbody>
    <tfoot><tr><td colspan="${r.lokasi?4:5}">Jumlah</td><td class="r">+${nf(r.masuk)}</td><td class="r">−${nf(r.keluar)}</td><td class="r">${nf(r.akhir)}</td><td></td></tr></tfoot></table>`:emptyBox('Tidak ada pergerakan stok pada periode ini','book')}</div></div>`;
}
ACT.xksx=b=>{const r=PS.ks.res;if(!r)return toast('Pilih produk dulu','err');
  const rep=repBase('Kartu Stok — '+r.produk.nama+' ('+(r.produk.sku||'-')+')',r.from,r.to);rep.title+=' · '+r.lokasiNama;
  rep.kpi.push({l:'Saldo awal',v:r.awal,k:'dec'},{l:'Masuk',v:r.masuk,k:'dec'},{l:'Keluar',v:r.keluar,k:'dec'},{l:'Saldo akhir',v:r.akhir,k:'dec'});
  rep.sections.push({title:'Mutasi stok',cols:[{h:'Waktu',k:'datetime'},{h:'Jenis',k:'text'},{h:'Referensi',k:'text'},{h:'Keterangan',k:'text',wrap:1},{h:'Lokasi',k:'text'},{h:'Masuk',k:'dec'},{h:'Keluar',k:'dec'},{h:'Saldo',k:'dec'}],
    rows:[[r.from+' 00:00:00','Saldo awal','','','',null,null,r.awal],...r.rows.map(x=>[x.t,x.jenis,x.ref,x.ket,x.lok,x.masuk||null,x.keluar||null,x.saldo])],total:['Jumlah','','','','',r.masuk,r.keluar,r.akhir]});
  rep.file=slug('kartu-stok-'+(r.produk.sku||r.produk.nama))+'_'+r.from+'_'+r.to;exportReport(b.dataset.f,rep,b)};

/* ======================================================================
 *  KARTU MEMBER — cetak ukuran kartu (85,6 × 54 mm) dengan barcode Code128
 * ==================================================================== */
const C128='212222 222122 222221 121223 121322 131222 122213 122312 132212 221213 221312 231212 112232 122132 122231 113222 123122 123221 223211 221132 221231 213212 223112 312131 311222 321122 321221 312212 322112 322211 212123 212321 232121 111323 131123 131321 112313 132113 132311 211313 231113 231311 112133 112331 132131 113123 113321 133121 313121 211331 231131 213113 213311 213131 311123 311321 331121 312113 312311 332111 314111 221411 431111 111224 111422 121124 121421 141122 141221 112214 112412 122114 122411 142112 142211 241211 221114 413111 241112 134111 111242 121142 121241 114212 124112 124211 411212 421112 421211 212141 214121 412121 111143 111341 131141 114113 114311 411113 411311 113141 114131 311141 411131 211412 211214 211232 2331112'.split(' ');
function code128Svg(txt,h=40,color='#14212B'){
  const s=String(txt).replace(/[^\x20-\x7E]/g,'');const codes=[104,...[...s].map(c=>c.charCodeAt(0)-32)];
  let sum=104;for(let i=1;i<codes.length;i++)sum+=i*codes[i];codes.push(sum%103,106);
  let x=10,bars='';for(const c of codes){const w=C128[c];for(let i=0;i<w.length;i++){const n=+w[i];if(i%2===0)bars+=`<rect x="${x}" y="0" width="${n}" height="${h}"/>`;x+=n}}
  return `<svg viewBox="0 0 ${x+10} ${h}" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" fill="${color}" shape-rendering="crispEdges">${bars}</svg>`;
}
window.code128Svg=code128Svg;
/* barcode umum: EAN-13 bila 13 digit valid, Code128-C untuk angka genap, selain itu Code128-B */
const EAN_L='0001101 0011001 0010011 0111101 0100011 0110001 0101111 0111011 0110111 0001011'.split(' ');
const EAN_P='LLLLLL LLGLGG LLGGLG LLGGGL LGLLGG LGGLLG LGGGLL LGLGLG LGLGGL LGGLGL'.split(' ');
function eanOk(s){if(!/^\d{13}$/.test(s))return false;let t=0;for(let i=0;i<12;i++)t+=(+s[i])*(i%2?3:1);return (10-t%10)%10===+s[12]}
function ean13Svg(s,h,color){
  const R=d=>EAN_L[d].replace(/./g,c=>c==='0'?'1':'0'),G=d=>R(d).split('').reverse().join('');
  let bits='101';const par=EAN_P[+s[0]];for(let i=1;i<7;i++)bits+=par[i-1]==='L'?EAN_L[+s[i]]:G(+s[i]);bits+='01010';for(let i=7;i<13;i++)bits+=R(+s[i]);bits+='101';
  let r='',x=11;for(let i=0;i<bits.length;i++){if(bits[i]==='1'){let j=i;while(bits[j+1]==='1')j++;r+=`<rect x="${x+i}" y="0" width="${j-i+1}" height="${h}"/>`;i=j}}
  return `<svg viewBox="0 0 ${bits.length+18} ${h}" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" fill="${color}" shape-rendering="crispEdges">${r}</svg>`;
}
function barSvg(txt,h=40,color='#000'){
  const s=String(txt||'').trim();
  if(eanOk(s))return ean13Svg(s,h,color);
  if(/^\d+$/.test(s)&&s.length>=4&&s.length%2===0){const codes=[105];for(let i=0;i<s.length;i+=2)codes.push(+s.substr(i,2));let sum=105;for(let i=1;i<codes.length;i++)sum+=i*codes[i];codes.push(sum%103,106);
    let x=10,bars='';for(const c of codes){const w=C128[c];for(let i=0;i<w.length;i++){const n=+w[i];if(i%2===0)bars+=`<rect x="${x}" y="0" width="${n}" height="${h}"/>`;x+=n}}
    return `<svg viewBox="0 0 ${x+10} ${h}" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" fill="${color}" shape-rendering="crispEdges">${bars}</svg>`}
  return code128Svg(s,h,color);
}
window.barSvg=barSvg;
ACT.xmcard=async b=>{
  const one=b.dataset.id;let d;try{d=await call('kartuMember',one?{ids:[one]}:{},b)}catch(e){return}
  if(!d.member.length)return toast('Belum ada member aktif','err');
  const m=modal({title:'Cetak kartu member',cls:'sm',body:`<p class="muted sm" style="margin-top:0">${d.member.length} kartu · ukuran kartu ATM/KTP (85,6 × 54 mm), tampak depan & belakang.</p>
    <label class="lb">Kertas</label><div class="seg s3" id="mck"><button type="button" class="on" data-k="a4">A4 (10 kartu)</button><button type="button" data-k="kartu">Printer kartu PVC</button><button type="button" data-k="depan">A4 depan saja</button></div>
    <label class="lb" style="margin-top:12px">Warna</label><div class="seg s3" id="mcw"><button type="button" class="on" data-w="toska">Toska</button><button type="button" data-w="tinta">Gelap</button><button type="button" data-w="amber">Emas</button></div>
    <div id="mcprev" style="margin-top:14px"></div>`,foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="mcgo">${ic('print')}Cetak</button>`});
  let k='a4',w='toska';const prev=()=>{const f=document.createElement('iframe');f.title='Pratinjau kartu';f.style.cssText='width:100%;height:230px;border:0;border-radius:12px;background:var(--card2)';$('#mcprev',m).innerHTML='';$('#mcprev',m).appendChild(f);
    const doc=cardDoc({toko:d.toko,member:[d.member[0]]},'depan',w).replace('</style>','body{display:flex;justify-content:center;align-items:center;height:100vh;background:transparent}.mc{transform:scale(.68);outline:0;box-shadow:0 8px 24px rgba(0,0,0,.2)}.sheet{display:block}</style>');f.srcdoc=doc};
  $('#mck',m).onclick=e=>{const x=e.target.closest('[data-k]');if(!x)return;k=x.dataset.k;$$('#mck button',m).forEach(y=>y.classList.toggle('on',y===x))};
  $('#mcw',m).onclick=e=>{const x=e.target.closest('[data-w]');if(!x)return;w=x.dataset.w;$$('#mcw button',m).forEach(y=>y.classList.toggle('on',y===x));prev()};
  prev();$('#mcgo',m).onclick=()=>{printHtml(cardDoc(d,k,w));m.remove()};
};
const CW={toska:['#0B7A6D','#10A391','#2FC6B3'],tinta:['#14212B','#24384A','#3B5BDB'],amber:['#8A5A00','#C98A12','#F5B83D']};
function cardHtml(mb,toko,w,side){
  const c=CW[w]||CW.toska;
  if(side==='back')return `<div class="mc back"><div class="bt">Kartu ini milik pelanggan terdaftar <b>${esc(toko.nama)}</b>. Tunjukkan atau sebutkan nomor HP saat membayar untuk mengumpulkan & menukar poin.</div>
    <div class="bc">${code128Svg(mb.kode,36)}<div class="mono">${esc(mb.kode)}</div></div><div class="bf">${esc(toko.alamat||'')}${toko.telepon?' · '+esc(toko.telepon):''}<br>Bila menemukan kartu ini, mohon kembalikan ke toko kami.</div></div>`;
  return `<div class="mc" style="background:linear-gradient(135deg,${c[0]} 0%,${c[1]} 62%,${c[2]} 100%)"><div class="dec"></div><div class="dec d2"></div>
    <div class="top"><div class="tn">${esc(toko.nama)}</div><div class="lb2">MEMBER CARD</div></div>
    <div class="chip"></div><div class="nm">${esc(mb.nama)}</div><div class="row"><div><small>No. member</small><b class="mono">${esc(mb.kode)}</b></div><div><small>Member sejak</small><b>${esc(fd(mb.sejak))}</b></div></div>
    <div class="bcw">${code128Svg(mb.kode,30)}</div></div>`;
}
function cardDoc(d,k,w){
  const css=`*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}body{margin:0;font-family:'Plus Jakarta Sans',Arial,sans-serif}
  .mc{width:85.6mm;height:54mm;border-radius:3.2mm;position:relative;overflow:hidden;color:#fff;padding:4.2mm 5mm;break-inside:avoid}
  .mc .dec{position:absolute;right:-14mm;top:-18mm;width:46mm;height:46mm;border-radius:10mm;background:rgba(255,255,255,.10);transform:rotate(45deg)}.mc .d2{right:auto;left:-20mm;top:auto;bottom:-26mm;background:rgba(0,0,0,.10)}
  .top{display:flex;justify-content:space-between;align-items:flex-start;position:relative}.tn{font-weight:800;font-size:4mm;letter-spacing:-.02em;max-width:52mm;line-height:1.1}.lb2{font-size:2.2mm;font-weight:800;letter-spacing:.18em;opacity:.85;margin-top:.6mm}
  .chip{width:9mm;height:6.6mm;border-radius:1.4mm;background:linear-gradient(135deg,#F7DD8F,#D9A73C);margin:3.2mm 0 2.2mm;position:relative}
  .nm{font-size:4.3mm;font-weight:800;letter-spacing:.01em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;position:relative}
  .row{display:flex;gap:7mm;margin-top:1.4mm;position:relative}.row small{display:block;font-size:1.9mm;opacity:.8;text-transform:uppercase;letter-spacing:.08em}.row b{font-size:3mm}
  .bcw{position:absolute;right:4mm;bottom:3.6mm;width:30mm;height:9mm;background:#fff;border-radius:1.2mm;padding:1.1mm 1.4mm}.bcw svg{width:100%;height:100%;display:block}
  .mono{font-family:'Courier New',monospace}
  .back{background:#fff;color:#14212B;border:.2mm solid #cfd6d2;padding:4mm 5mm}.bt{font-size:2.5mm;line-height:1.45}.bc{margin:3mm auto 0;width:58mm;text-align:center}.bc svg{width:100%;height:11mm;display:block}.bc div{font-size:3mm;letter-spacing:.2em;margin-top:.8mm}
  .bf{position:absolute;left:5mm;right:5mm;bottom:3.4mm;font-size:2mm;color:#5B6870;line-height:1.4}`;
  let body='',page='';
  if(k==='kartu'){page='@page{size:85.6mm 54mm;margin:0}.mc{border-radius:0;page-break-after:always}';body=d.member.map(mb=>cardHtml(mb,d.toko,w,'front')+cardHtml(mb,d.toko,w,'back')).join('')}
  else{page='@page{size:A4;margin:12mm}.sheet{display:grid;grid-template-columns:85.6mm 85.6mm;gap:6mm 8mm;justify-content:center}.mc{outline:.15mm dashed #9aa}';
    body=`<div class="sheet">${d.member.map(mb=>cardHtml(mb,d.toko,w,'front')+(k==='depan'?'':cardHtml(mb,d.toko,w,'back'))).join('')}</div>`}
  return `<!doctype html><html><head><meta charset="utf-8"><title>Kartu member</title><style>${css}${page}</style></head><body>${body}</body></html>`;
}

/* ======================================================================
 *  INVENTARIS ASET
 * ==================================================================== */
NAV.push({id:'aset',t:'Daftar aset',i:'box',m:'aset',lv:1},{id:'susut',t:'Penyusutan',i:'trend',m:'aset',lv:1});
MAINNAV.aset=['aset','susut'];
PS.aset={q:'',st:'Aktif',kat:'',kond:'',lok:'',page:1,size:25,data:null,meta:null,sel:new Set()};
const KOND={'Baik':'ok','Perlu perbaikan':'warn','Rusak':'err','Hilang':'err'};
const asMeta=async f=>{if(!PS.aset.meta||f)PS.aset.meta=await api('asetMeta',{},true);return PS.aset.meta};
PAGES.aset=async function(){
  const P=PS.aset;
  $('#view').innerHTML=head('Inventaris aset','Peralatan toko — mesin kasir, rak, etalase, kulkas, kendaraan — beserta kondisi, lokasi, dan nilai bukunya. Penyusutan & jurnal otomatis.',
    `<button class="btn" data-act="xaslbl">${ic('tag')}Cetak label</button>${lv()>=2?`<button class="btn" data-act="xasimp">${ic('inb')}Import Excel</button>`:''}<button class="btn" data-act="xasx" data-f="xlsx">${ic('xls')}Excel</button><button class="btn" data-act="xasx" data-f="pdf">${ic('pdf')}PDF</button>${lv()>=2?`<button class="btn pri" data-act="xasnew">${ic('plus')}Aset baru</button>`:''}`)+
   `<div class="kpis k5" id="ask">${'<div class="skel"></div>'.repeat(5)}</div><div id="asal"></div>
    <div class="card tb"><div class="sb" style="flex:2;min-width:200px">${ic('search')}<input class="in" id="asq" placeholder="Cari / scan label: kode, nama, merek, no. seri…" value="${esc(P.q)}" enterkeyhint="search"></div>
     <select class="in" id="asst" style="flex:0 0 130px">${['Aktif','Dilepas','Semua'].map(x=>`<option ${P.st===x?'selected':''}>${x}</option>`).join('')}</select>
     <select class="in" id="askat" style="flex:1;min-width:150px"><option value="">Semua kategori</option></select><select class="in" id="askond" style="flex:0 0 160px"><option value="">Semua kondisi</option>${Object.keys(KOND).map(x=>`<option ${P.kond===x?'selected':''}>${x}</option>`).join('')}</select>
     <select class="in" id="aslok" style="flex:0 0 160px">${lokOpts(P.lok,true).replace('Semua lokasi (total)','Semua lokasi')}</select></div>
    <div class="card np"><div class="tw" id="aslist"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#asq').addEventListener('input',deb(e=>{P.q=e.target.value;P.page=1;drawAs()},150));
  // scan label aset (barcode = kode aset) → langsung buka detailnya
  $('#asq').addEventListener('keydown',e=>{if(e.key!=='Enter')return;e.preventDefault();const v=e.target.value.trim().toUpperCase();const a=P.data&&P.data.rows.find(x=>x.kode.toUpperCase()===v);if(a){e.target.value='';P.q='';drawAs();asDetail(a.id)}});
  [['asst','st'],['askat','kat'],['askond','kond'],['aslok','lok']].forEach(([i,k])=>$('#'+i).onchange=e=>{P[k]=e.target.value;P.page=1;drawAs()});
  try{const [m,d]=await Promise.all([asMeta(),api('asetList',{},true)]);P.data=d;
    $('#askat').innerHTML='<option value="">Semua kategori</option>'+m.kategori.map(x=>`<option ${P.kat===x?'selected':''}>${esc(x)}</option>`).join('');
    const s=d.sum;$('#ask').innerHTML=kpiBox('Aset aktif',n0(s.n),'box')+kpiBox('Nilai perolehan',rp(s.harga),'wallet')+kpiBox('Akumulasi penyusutan',rp(s.akum),'trend',s.harga?Math.round(s.akum/s.harga*100)+'% dari perolehan':'')+kpiBox('Nilai buku',rp(s.buku),'book')+kpiBox('Perlu tindakan',n0(s.perbaikan+s.rusak),'alert',`${n0(s.perbaikan)} perlu perbaikan · ${n0(s.rusak)} rusak/hilang`);
    $('#asal').innerHTML=d.pending?`<a class="card alr warn" href="#susut" data-act="go" data-r="susut" style="margin-bottom:12px"><span class="ai">${ic('trend')}</span><div><b>${d.pending} penyusutan bulanan belum dibukukan</b><span class="muted sm">Buka menu Penyusutan untuk membukukannya ke akuntansi sekali klik.</span></div></a>`:'';
    drawAs()}catch(e){$('#aslist').innerHTML=failBox(e.message)}
};
const asFilt=()=>{const P=PS.aset,q=P.q.toLowerCase();return (P.data?P.data.rows:[]).filter(a=>(P.st==='Semua'||a.status===P.st)&&(!P.kat||a.kategori===P.kat)&&(!P.kond||a.kondisi===P.kond)&&(!P.lok||a.lokasi===P.lok)&&(!q||(a.kode+' '+a.nama+' '+a.merek+' '+a.noSeri+' '+a.pj).toLowerCase().includes(q)))};
function drawAs(){
  const P=PS.aset,el=$('#aslist');if(!el||!P.data)return;const l=asFilt(),pg=l.slice((P.page-1)*P.size,P.page*P.size);
  P.sel.forEach(id=>{if(!P.data.rows.some(a=>a.id===id))P.sel.delete(id)});
  el.innerHTML=(P.sel.size?`<div class="bulkbar" style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--priS);border-bottom:1px solid var(--bd)"><b>${P.sel.size} aset dipilih</b><span style="flex:1"></span><button class="btn xs" data-act="xasselx">Batal pilih</button><button class="btn xs pri" data-act="xaslbl">${ic('tag')}Cetak label terpilih</button></div>`:'')+(pg.length?`<table class="tbl rs click"><thead><tr><th style="width:36px"><input type="checkbox" data-act="xasselall" aria-label="Pilih semua" ${pg.every(a=>P.sel.has(a.id))?'checked':''}></th><th>Aset</th><th>Kategori</th><th>Lokasi</th><th>Kondisi</th><th>Diperoleh</th><th class="r">Harga perolehan</th><th class="r">Nilai buku</th><th style="width:120px">Tersusut</th></tr></thead><tbody>${pg.map(a=>`<tr data-act="xasdet" data-id="${a.id}"><td class="ck"><input type="checkbox" data-act="xassel" data-id="${a.id}" aria-label="Pilih ${esc(a.kode)}" ${P.sel.has(a.id)?'checked':''}></td>
    <td class="nl"><div class="pcell">${thumb({nama:a.nama,foto:a.foto})}<div><b>${esc(a.nama)}</b><span class="muted sm mono">${esc(a.kode)}${a.merek?' · '+esc(a.merek):''}</span></div></div></td><td data-l="Kategori" class="sm">${esc(a.kategori)}</td><td data-l="Lokasi" class="sm">${esc(a.lokasiNama)}${a.pj?`<div class="muted sm">PJ: ${esc(a.pj)}</div>`:''}</td>
    <td data-l="Kondisi">${a.status==='Aktif'?B(KOND[a.kondisi]||'',a.kondisi):B('','Dilepas')}</td><td data-l="Diperoleh" class="sm">${tgl(a.tglBeli)}</td><td class="r" data-l="Harga">${rp(a.harga)}</td><td class="r b" data-l="Nilai buku">${rp(a.status==='Aktif'?a.buku:0)}</td>
    <td data-l="Tersusut"><div class="prog"><span style="width:${Math.min(100,a.persen)}%;background:var(--warn)"></span></div><small class="muted">${String(a.persen).replace('.',',')}%</small></td></tr>`).join('')}</tbody></table>`:(P.data.rows.length?emptyBox('Tidak ada aset yang cocok','box'):emptyBox('Belum ada aset. Catat peralatan toko Anda: mesin kasir, rak, etalase, kulkas, dll.','box')+(lv()>=2?`<div class="c" style="padding:0 0 22px"><button class="btn" data-act="xasimp">${ic('inb')}Import dari Excel</button> ${lv()>=3?`<button class="btn" data-act="xascth">${ic('plus')}Isi 5 data contoh</button>`:''}</div>`:'')))+pager(l.length,P.page,P.size,'aset');
}
DRAW.aset=drawAs;
ACT.xassel=b=>{const S2=PS.aset.sel;b.checked?S2.add(b.dataset.id):S2.delete(b.dataset.id);drawAs()};
ACT.xasselall=b=>{const P=PS.aset,l=asFilt().slice((P.page-1)*P.size,P.page*P.size);l.forEach(a=>b.checked?P.sel.add(a.id):P.sel.delete(a.id));drawAs()};
ACT.xasselx=()=>{PS.aset.sel.clear();drawAs()};
ACT.xaslbl=b=>{const P=PS.aset;const l=b.dataset.id?(P.data?P.data.rows:[]).filter(a=>a.id===b.dataset.id):P.sel.size?(P.data?P.data.rows:[]).filter(a=>P.sel.has(a.id)):asFilt();asLabel(l)};

/* ---------- Label barcode aset (stiker untuk ditempel di peralatan) ---------- */
const LBL={
  s5030:{t:'Stiker 50 × 30 mm',d:'Printer label thermal (gulungan)',w:50,h:30,roll:1},
  s4030:{t:'Stiker 40 × 30 mm',d:'Printer label thermal (gulungan)',w:40,h:30,roll:1},
  s6040:{t:'Stiker 60 × 40 mm',d:'Printer label thermal (gulungan)',w:60,h:40,roll:1},
  a4:{t:'Kertas A4 · 3 × 8',d:'Stiker A4 / HVS · 24 label',w:63.5,h:33.9,cols:3,rows:8}
};
function asLabelHtml(a,o,toko){
  const L=LBL[o.uk],sm=L.h<=30;
  const info=[o.lok&&a.lokasiNama?esc(a.lokasiNama):'',o.tgl?tgl(a.tglBeli):''].filter(Boolean).join(' · ');
  return `<div class="lb">${o.toko?`<div class="tk">${esc(toko)}</div>`:''}<div class="nm">${esc(a.nama)}</div><div class="bc">${code128Svg(a.kode,40,'#000')}</div><div class="kd">${esc(a.kode)}</div>${info?`<div class="in">${info}</div>`:''}${o.milik?`<div class="ml">MILIK ${esc(toko).toUpperCase()} · JANGAN DILEPAS</div>`:''}</div>`;
}
function asLabelDoc(list,o){
  const L=LBL[o.uk],toko=S.set.namaToko||'';const items=[];list.forEach(a=>{for(let i=0;i<o.n;i++)items.push(a)});
  const fs=L.h>=40?1.15:L.w<=40?.85:1;
  const css=`*{box-sizing:border-box;margin:0;padding:0}body{font-family:Arial,Helvetica,sans-serif;color:#000}
  .lb{width:${L.w}mm;height:${L.h}mm;padding:1.6mm 2mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;overflow:hidden;break-inside:avoid}
  .tk{font-size:${2.1*fs}mm;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
  .nm{font-size:${2.7*fs}mm;font-weight:700;line-height:1.12;max-width:100%;overflow:hidden;display:-webkit-box;-webkit-line-clamp:${L.h>=40?2:1};-webkit-box-orient:vertical;margin:.3mm 0 .6mm}
  .bc{width:100%;height:${(L.h>=40?12:L.h>=33?9.5:8.5)}mm}.bc svg{width:100%;height:100%;display:block}
  .kd{font-family:'Courier New',monospace;font-weight:700;font-size:${3*fs}mm;letter-spacing:.12em;margin-top:.4mm}
  .in{font-size:${1.9*fs}mm;color:#222;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}
  .ml{font-size:${1.6*fs}mm;font-weight:700;letter-spacing:.03em;margin-top:.3mm;white-space:nowrap;overflow:hidden;max-width:100%}`;
  let page,body;
  if(L.roll){page=`@page{size:${L.w}mm ${L.h}mm;margin:0}.lb{page-break-after:always}.lb:last-child{page-break-after:auto}`;body=items.map(a=>asLabelHtml(a,o,toko)).join('')}
  else{page=`@page{size:A4;margin:12.9mm 7.2mm}.sh{display:grid;grid-template-columns:repeat(${L.cols},${L.w}mm);grid-auto-rows:${L.h}mm;column-gap:2.5mm;justify-content:center}.lb{outline:.1mm dashed #bbb}.pg{page-break-after:always}.pg:last-child{page-break-after:auto}`;
    const per=L.cols*L.rows,skip=Math.min(per-1,Math.max(0,o.skip||0)),all=[...Array(skip).fill(null),...items],pages=[];
    for(let i=0;i<all.length;i+=per)pages.push(all.slice(i,i+per));
    body=pages.map(pg=>`<div class="pg sh">${pg.map(a=>a?asLabelHtml(a,o,toko):'<div class="lb" style="outline:0"></div>').join('')}</div>`).join('')}
  return `<!doctype html><html><head><meta charset="utf-8"><title>Label aset</title><style>${css}${page}</style></head><body>${body}</body></html>`;
}
function asLabel(list){
  if(!list.length)return toast('Tidak ada aset untuk dicetak','err');
  let o;try{o=Object.assign({uk:'s5030',n:1,toko:true,lok:true,tgl:false,milik:false,skip:0},JSON.parse(localStorage.getItem('dhyra_lblaset')||'{}'))}catch(e){o={uk:'s5030',n:1,toko:true,lok:true,tgl:false,milik:false,skip:0}}
  if(!LBL[o.uk])o.uk='s5030';
  const m=modal({title:'Cetak label barcode aset',cls:'lg',body:`<p class="muted sm" style="margin-top:0"><b>${list.length} aset</b> ${list.length<=3?'('+list.map(a=>esc(a.kode)).join(', ')+')':''}. Barcode berisi kode aset, jadi saat audit cukup scan label di kolom pencarian Daftar aset untuk membuka datanya.</p>
   <label class="lb" style="margin-top:12px;display:block">Ukuran label</label><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px" id="lbuk">${Object.entries(LBL).map(([k,v])=>`<button type="button" class="btn ${o.uk===k?'pri':''}" data-uk="${k}" style="flex-direction:column;align-items:flex-start;height:auto;padding:9px 12px;text-align:left"><b>${v.t}</b><small style="opacity:.8;font-weight:500;white-space:normal">${v.d}</small></button>`).join('')}</div>
   <div class="grid2" style="margin-top:12px">${field('Jumlah label per aset',`<input class="in" id="lbn" type="number" min="1" max="20" value="${o.n}">`)}<div id="lbskw">${field('Lewati label pertama (kertas A4 bekas)',`<input class="in" id="lbsk" type="number" min="0" max="23" value="${o.skip||0}">`)}</div></div>
   <label class="lb">Tampilkan</label><div style="display:flex;gap:14px;flex-wrap:wrap" id="lbop">${[['toko','Nama toko'],['lok','Lokasi'],['tgl','Tanggal perolehan'],['milik','Tulisan "Milik toko · jangan dilepas"']].map(([k,t])=>`<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" data-o="${k}" ${o[k]?'checked':''}>${t}</label>`).join('')}</div>
   <div class="lb" style="margin-top:14px">Pratinjau</div><div id="lbpv" style="background:var(--card2);border-radius:12px;padding:14px;display:flex;justify-content:center"></div>
   <p class="muted sm" style="margin-top:8px">Untuk printer label thermal: pilih ukuran kertas yang sama di pengaturan printer, skala 100% (bukan "Fit to page"), margin None.</p>`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="lbgo">${ic('print')}Cetak ${list.length} aset</button>`});
  const prev=()=>{const L=LBL[o.uk];$('#lbskw',m).style.display=L.roll?'none':'';const f=document.createElement('iframe');f.title='Pratinjau label';const sc=Math.min(2.6,300/L.w/3.78);
    f.style.cssText=`width:${Math.round(L.w*3.78*sc)+4}px;height:${Math.round(L.h*3.78*sc)+4}px;border:0;background:#fff;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.15)`;
    $('#lbpv',m).innerHTML='';$('#lbpv',m).appendChild(f);
    f.srcdoc=`<!doctype html><html><head><meta charset="utf-8"><style>${asLabelDoc([list[0]],Object.assign({},o,{n:1,uk:o.uk})).match(/<style>([\s\S]*)<\/style>/)[1]}body{zoom:${sc};margin:0}.lb{outline:0!important}</style></head><body>${asLabelHtml(list[0],o,S.set.namaToko||'')}</body></html>`};
  const save=()=>{try{localStorage.setItem('dhyra_lblaset',JSON.stringify(o))}catch(e){}};
  $('#lbuk',m).onclick=e=>{const x=e.target.closest('[data-uk]');if(!x)return;o.uk=x.dataset.uk;$$('#lbuk button',m).forEach(y=>y.classList.toggle('pri',y===x));prev()};
  $('#lbop',m).onchange=e=>{const x=e.target.closest('[data-o]');if(x){o[x.dataset.o]=x.checked;prev()}};
  $('#lbn',m).oninput=e=>{o.n=Math.max(1,Math.min(20,parseInt(e.target.value)||1))};
  $('#lbsk',m).oninput=e=>{o.skip=Math.max(0,Math.min(23,parseInt(e.target.value)||0))};
  prev();
  $('#lbgo',m).onclick=()=>{save();printHtml(asLabelDoc(list,o));m.remove()};
}
window.asLabelDoc=asLabelDoc;

/* ======================================================================
 *  LABEL BARCODE & HARGA PRODUK (untuk produk yang belum berlabel)
 * ==================================================================== */
const PLBL={
  p5030:{t:'Stiker 50 × 30 mm',d:'Printer label thermal',w:50,h:30,c:1},
  p4030:{t:'Stiker 40 × 30 mm',d:'Printer label thermal',w:40,h:30,c:1},
  p3825:{t:'Stiker 38 × 25 mm',d:'Printer label thermal',w:38,h:25,c:1},
  p3315:{t:'33 × 15 mm · 3 kolom',d:'Gulungan label harga 3 baris',w:33,h:15,c:3,gap:2},
  a4:{t:'Kertas A4 · 3 × 8',d:'Stiker A4 / HVS · 24 label',w:63.5,h:33.9,cols:3,rows:8}
};
const pBarVal=(p,o)=>p.barcode||(o.pakaiSku?p.sku:'');
function pLabelHtml(p,o,toko){
  const L=PLBL[o.uk],kecil=L.h<20,bv=pBarVal(p,o);
  if(kecil)return `<div class="lb k"><div class="nm">${esc(p.nama)}</div>${bv?`<div class="bc">${barSvg(bv,30)}</div>`:''}<div class="rw"><span class="kd">${esc(bv)}</span>${o.harga?`<b>${rp(p.hargaJual)}</b>`:''}</div></div>`;
  return `<div class="lb">${o.toko?`<div class="tk">${esc(toko)}</div>`:''}<div class="nm">${esc(p.nama)}</div>${o.harga?`<div class="hg">${rp(p.hargaJual)}${o.sat?`<small> / ${esc(p.satuan)}</small>`:''}</div>`:''}${bv?`<div class="bc">${barSvg(bv,40)}</div><div class="kd">${esc(bv)}</div>`:'<div class="kd">(belum ada barcode)</div>'}${o.sku&&p.sku&&p.sku!==bv?`<div class="sk">SKU ${esc(p.sku)}</div>`:''}</div>`;
}
function pLabelDoc(rows,o){
  const L=PLBL[o.uk],toko=S.set.namaToko||'';const items=[];rows.forEach(r=>{for(let i=0;i<r.n;i++)items.push(r.p)});
  const big=L.h>=30,fs=L.w<=40?.88:1;
  const css=`*{box-sizing:border-box;margin:0;padding:0}body{font-family:Arial,Helvetica,sans-serif;color:#000}
  .lb{width:${L.w}mm;height:${L.h}mm;padding:1.4mm 1.8mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;overflow:hidden;break-inside:avoid}
  .tk{font-size:${1.9*fs}mm;font-weight:700;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap;overflow:hidden;max-width:100%}
  .nm{font-size:${2.5*fs}mm;font-weight:700;line-height:1.12;max-width:100%;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
  .hg{font-size:${(big?4.4:3.6)*fs}mm;font-weight:800;margin:.3mm 0 .4mm;line-height:1}.hg small{font-size:${1.9*fs}mm;font-weight:600}
  .bc{width:100%;height:${L.h>=33?10:L.h>=30?8.5:7}mm}.bc svg{width:100%;height:100%;display:block}
  .kd{font-family:'Courier New',monospace;font-weight:700;font-size:${2.4*fs}mm;letter-spacing:.08em;margin-top:.3mm}
  .sk{font-size:${1.8*fs}mm;color:#222}
  .k{padding:.8mm 1mm;justify-content:space-between}.k .nm{font-size:1.75mm;-webkit-line-clamp:1;white-space:nowrap;display:block;text-overflow:ellipsis;width:100%}.k .bc{height:6.6mm}
  .k .rw{display:flex;justify-content:space-between;align-items:baseline;width:100%}.k .kd{font-size:1.6mm;letter-spacing:0;margin:0}.k b{font-size:2.4mm}`;
  let page,body;
  if(L.c){const W=L.w*L.c+(L.gap||0)*(L.c-1);page=`@page{size:${W}mm ${L.h}mm;margin:0}.rl{display:flex;gap:${L.gap||0}mm;width:${W}mm;height:${L.h}mm;page-break-after:always}.rl:last-child{page-break-after:auto}`;
    const rr=[];for(let i=0;i<items.length;i+=L.c)rr.push(items.slice(i,i+L.c));body=rr.map(r=>`<div class="rl">${r.map(p=>pLabelHtml(p,o,toko)).join('')}</div>`).join('')}
  else{page=`@page{size:A4;margin:12.9mm 7.2mm}.sh{display:grid;grid-template-columns:repeat(${L.cols},${L.w}mm);grid-auto-rows:${L.h}mm;column-gap:2.5mm;justify-content:center}.lb{outline:.1mm dashed #bbb}.pg{page-break-after:always}.pg:last-child{page-break-after:auto}`;
    const per=L.cols*L.rows,skip=Math.min(per-1,Math.max(0,o.skip||0)),all=[...Array(skip).fill(null),...items],pages=[];
    for(let i=0;i<all.length;i+=per)pages.push(all.slice(i,i+per));
    body=pages.map(pg=>`<div class="pg sh">${pg.map(p=>p?pLabelHtml(p,o,toko):'<div class="lb" style="outline:0"></div>').join('')}</div>`).join('')}
  return `<!doctype html><html><head><meta charset="utf-8"><title>Label produk</title><style>${css}${page}</style></head><body>${body}</body></html>`;
}
function prodLabel(list){
  if(!list.length)return toast('Tidak ada produk untuk dicetak','err');
  if(list.length>300){toast('Maksimal 300 produk sekali cetak. Saring dulu daftarnya.','err');return}
  let o;try{o=Object.assign({uk:'p5030',toko:true,harga:true,sat:false,sku:true,pakaiSku:true,skip:0,mode:'1'},JSON.parse(localStorage.getItem('dhyra_lblprod')||'{}'))}catch(e){o={uk:'p5030',toko:true,harga:true,sat:false,sku:true,pakaiSku:true,skip:0,mode:'1'}}
  if(!PLBL[o.uk])o.uk='p5030';o.mode='1';
  const lok=S.curLok,stokOf=p=>Math.max(0,Math.floor(lok?((p.lok||{})[lok]||{}).q||0:p.stok||0));
  let R=list.map(p=>({p,n:o.mode==='stok'?stokOf(p):1}));
  const adm=typeof isAdm==='function'&&isAdm();
  const m=modal({title:'Cetak label barcode & harga produk',cls:'lg',body:`
   <div id="plnb"></div>
   <label class="lb" style="display:block">Ukuran label</label><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px" id="pluk">${Object.entries(PLBL).map(([k,v])=>`<button type="button" class="btn ${o.uk===k?'pri':''}" data-uk="${k}" style="flex-direction:column;align-items:flex-start;height:auto;padding:9px 12px;text-align:left"><b>${v.t}</b><small style="opacity:.8;font-weight:500;white-space:normal">${v.d}</small></button>`).join('')}</div>
   <label class="lb" style="margin-top:12px;display:block">Tampilkan</label><div style="display:flex;gap:14px;flex-wrap:wrap" id="plop">${[['toko','Nama toko'],['harga','Harga jual'],['sat','Satuan'],['sku','SKU']].map(([k,t])=>`<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" data-o="${k}" ${o[k]?'checked':''}>${t}</label>`).join('')}</div>
   <div class="grid2" style="margin-top:12px"><div><label class="lb">Jumlah label</label><div class="seg s2" id="plmd"><button type="button" data-md="1" class="${o.mode!=='stok'?'on':''}">1 per produk</button><button type="button" data-md="stok" class="${o.mode==='stok'?'on':''}">Sesuai stok${lok?' lokasi ini':''}</button></div></div><div id="plskw">${field('Lewati label pertama (kertas A4 bekas)',`<input class="in" id="plsk" type="number" min="0" max="23" value="${o.skip||0}">`)}</div></div>
   <div class="tw" style="max-height:220px;margin-top:10px"><table class="tbl"><thead><tr><th>Produk</th><th>Barcode</th><th class="r" style="width:110px">Jumlah label</th></tr></thead><tbody id="plrows"></tbody></table></div>
   <div class="lb" style="margin-top:12px">Pratinjau</div><div id="plpv" style="background:var(--card2);border-radius:12px;padding:14px;display:flex;justify-content:center"></div>
   <p class="muted sm" style="margin-top:8px">Printer label thermal: pilih ukuran kertas yang sama di dialog cetak, skala 100%, margin None.</p>`,
   foot:`<span class="muted sm" id="pltot" style="flex:1"></span><button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="plgo">${ic('print')}Cetak</button>`});
  const tanpa=()=>R.filter(r=>!r.p.barcode);
  const nb=()=>{const t=tanpa().length;$('#plnb',m).innerHTML=t?`<div class="hint" style="margin:0 0 12px"><b>${t} produk belum punya barcode.</b> Pilih salah satu:
     <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px"><label style="display:flex;gap:8px;align-items:flex-start"><input type="radio" name="plbc" value="sku" ${o.pakaiSku?'checked':''}><span><b>Pakai SKU sebagai barcode</b> — langsung bisa discan di kasir, data produk tidak diubah.</span></label>
     ${adm?`<label style="display:flex;gap:8px;align-items:flex-start"><input type="radio" name="plbc" value="gen"><span><b>Buatkan barcode EAN-13 otomatis</b> (awalan 20, kode internal toko) dan simpan ke produk — standar barcode toko ritel.</span></label>`:''}
     <label style="display:flex;gap:8px;align-items:flex-start"><input type="radio" name="plbc" value="none" ${o.pakaiSku?'':'checked'}><span>Cetak tanpa barcode (hanya nama & harga)</span></label></div>
     ${adm?`<button class="btn xs pri hide" id="plgen" style="margin-top:8px">${ic('tag')}Buat ${t} barcode sekarang</button>`:''}</div>`:'';
    $$('[name=plbc]',m).forEach(r=>r.onchange=()=>{const v=$('[name=plbc]:checked',m).value;if(v!=='gen')o.pakaiSku=v==='sku';const g=$('#plgen',m);if(g)g.classList.toggle('hide',v!=='gen');rows();prev()});
    const g=$('#plgen',m);if(g)g.onclick=async ev=>{try{const r=await call('barcodeIsi',{ids:tanpa().map(x=>x.p.id)},ev.currentTarget);if(typeof upsertProds==='function')upsertProds(r.items);
      const by={};r.items.forEach(x=>by[x.id]=x);R.forEach(x=>{const n=S.prods.find(y=>y.id===x.p.id)||by[x.p.id];if(n)x.p=n});toast(r.n+' barcode dibuat & disimpan','ok');nb();rows();prev();DRAW[S.route]&&DRAW[S.route]()}catch(e){}}};
  const rows=()=>{$('#plrows',m).innerHTML=R.map((r,i)=>`<tr><td><b>${esc(r.p.nama)}</b><div class="muted sm mono">${esc(r.p.sku||'')}</div></td><td class="mono sm">${r.p.barcode?esc(r.p.barcode):o.pakaiSku&&r.p.sku?`<span class="muted">SKU: ${esc(r.p.sku)}</span>`:'<span class="muted">–</span>'}</td><td class="r"><input class="in" type="number" min="0" max="500" data-pn="${i}" value="${r.n}" style="width:90px;text-align:right"></td></tr>`).join('');tot()};
  const tot=()=>{const n=R.reduce((a,r)=>a+r.n,0);$('#pltot',m).textContent=`${n} label dari ${R.filter(r=>r.n>0).length} produk`;$('#plgo',m).disabled=!n};
  const prev=()=>{const L=PLBL[o.uk];$('#plskw',m).style.visibility=L.cols?'visible':'hidden';const p=(R.find(r=>r.n>0)||R[0]).p;const cnt=L.c||1;const W=L.w*cnt+(L.gap||0)*(cnt-1);
    const sc=Math.min(3,330/W/3.78);const f=document.createElement('iframe');f.title='Pratinjau label';
    f.style.cssText=`width:${Math.round(W*3.78*sc)+4}px;height:${Math.round(L.h*3.78*sc)+4}px;border:0;background:#fff;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,.15)`;$('#plpv',m).innerHTML='';$('#plpv',m).appendChild(f);
    const doc=pLabelDoc([{p,n:cnt}],o);f.srcdoc=doc.replace('</style>',`body{zoom:${sc}}.lb{outline:0!important}.sh{display:block}</style>`)};
  $('#pluk',m).onclick=e=>{const x=e.target.closest('[data-uk]');if(!x)return;o.uk=x.dataset.uk;$$('#pluk button',m).forEach(y=>y.classList.toggle('pri',y===x));prev()};
  $('#plop',m).onchange=e=>{const x=e.target.closest('[data-o]');if(x){o[x.dataset.o]=x.checked;prev()}};
  $('#plmd',m).onclick=e=>{const x=e.target.closest('[data-md]');if(!x)return;o.mode=x.dataset.md;$$('#plmd button',m).forEach(y=>y.classList.toggle('on',y===x));R.forEach(r=>r.n=o.mode==='stok'?stokOf(r.p):1);rows()};
  $('#plrows',m).oninput=e=>{const x=e.target.closest('[data-pn]');if(!x)return;R[+x.dataset.pn].n=Math.max(0,Math.min(500,parseInt(x.value)||0));tot()};
  $('#plsk',m).oninput=e=>{o.skip=Math.max(0,Math.min(23,parseInt(e.target.value)||0))};
  nb();rows();prev();
  $('#plgo',m).onclick=()=>{const n=R.reduce((a,r)=>a+r.n,0);if(n>3000)return toast('Terlalu banyak label sekali cetak (maks. 3000)','err');
    try{localStorage.setItem('dhyra_lblprod',JSON.stringify(o))}catch(e){}printHtml(pLabelDoc(R.filter(r=>r.n>0),o));m.remove()};
}
window.prodLabel=prodLabel;window.pLabelDoc=pLabelDoc;
ACT.plbl=b=>{const P=PS.produk;const l=b.dataset.id?S.prods.filter(p=>p.id===b.dataset.id):P.sel&&P.sel.size?S.prods.filter(p=>P.sel.has(p.id)):(typeof filtProd==='function'?filtProd():S.prods);prodLabel(l)};
ACT.plblno=()=>prodLabel(S.prods.filter(p=>!p.barcode));
ACT.xasx=b=>{const l=asFilt();if(!l.length)return toast('Tidak ada data','err');const t=ymd(today());const rep=repBase('Daftar Inventaris Aset',t,t);rep.periode='Posisi '+fd(t);
  rep.kpi.push({l:'Jumlah aset',v:l.length,k:'int'},{l:'Nilai perolehan',v:l.reduce((a,x)=>a+x.harga,0),k:'money'},{l:'Akumulasi penyusutan',v:l.reduce((a,x)=>a+x.akum,0),k:'money'},{l:'Nilai buku',v:l.reduce((a,x)=>a+(x.status==='Aktif'?x.buku:0),0),k:'money'});
  rep.sections.push({title:'Aset',cols:[{h:'Kode',k:'text'},{h:'Nama aset',k:'text',wrap:1},{h:'Kategori',k:'text'},{h:'Lokasi',k:'text'},{h:'Kondisi',k:'text'},{h:'Tgl perolehan',k:'date'},{h:'Umur (bln)',k:'int'},{h:'Harga perolehan',k:'money'},{h:'Akum. penyusutan',k:'money'},{h:'Nilai buku',k:'money'}],
    rows:l.map(a=>[a.kode,a.nama,a.kategori,a.lokasiNama,a.status==='Aktif'?a.kondisi:'Dilepas',a.tglBeli,a.umur,a.harga,a.akum,a.status==='Aktif'?a.buku:0]),total:['Total','','','','','',null,l.reduce((a,x)=>a+x.harga,0),l.reduce((a,x)=>a+x.akum,0),l.reduce((a,x)=>a+(x.status==='Aktif'?x.buku:0),0)]});
  rep.file='inventaris-aset_'+t;exportReport(b.dataset.f,rep,b)};
ACT.xasnew=()=>asForm();
ACT.xasimp=()=>xlImport(Object.assign({},IMPORT_ASET,{contoh:lv()>=3?'asetContoh':'',done:()=>{PS.aset.meta=null;PAGES.aset()}}));
ACT.xascth=async b=>{try{const r=await call('asetContoh',{},b);toast(r.n+' aset contoh ditambahkan','ok');PAGES.aset()}catch(e){}};
ACT.xasdet=b=>asDetail(b.dataset.id);
async function asForm(a){
  const M=await asMeta();a=a||{tglBeli:ymd(today()),umur:48,nilaiSisa:0,sumber:'Kas',kondisi:'Baik',kategori:M.kategori[0],lokasi:S.lokJual};const ed=!!a.id,lock=ed&&a.susutCount;
  let foto=null;
  const m=modal({title:ed?'Ubah aset '+esc(a.kode):'Catat aset baru',cls:'lg',body:`<div class="grid2">${field('Nama aset *',`<input class="in" id="an" value="${esc(a.nama||'')}" placeholder="mis. Mesin kasir + printer struk">`)}${field('Kode aset',`<input class="in mono" id="ak" value="${esc(a.kode||'')}" placeholder="Kosongkan = otomatis (AST-0001)" style="text-transform:uppercase">`)}</div>
    <div class="grid2">${field('Kategori',`<select class="in" id="akat">${M.kategori.map(x=>`<option ${a.kategori===x?'selected':''}>${esc(x)}</option>`).join('')}</select>`)}${field('Lokasi',`<select class="in" id="al">${lokOpts(a.lokasi,false)}</select>`)}</div>
    <div class="grid2">${field('Merek / tipe',`<input class="in" id="am" value="${esc(a.merek||'')}">`)}${field('No. seri',`<input class="in mono" id="as" value="${esc(a.noSeri||'')}">`)}</div>
    <div class="grid2">${field('Penanggung jawab',`<input class="in" id="apj" value="${esc(a.pj||'')}" placeholder="mis. Kepala toko">`)}${field('Pemasok / dibeli di',`<input class="in" id="ap" value="${esc(a.pemasok||'')}">`)}</div>
    <h4 class="sub">Nilai & penyusutan ${lock?'<span class="bdg warn">terkunci — penyusutan sudah dibukukan</span>':''}</h4>
    <div class="grid2">${field('Tanggal perolehan *',`<input type="date" class="in" id="at" value="${a.tglBeli}" max="${ymd(today())}" ${lock?'disabled':''}>`)}${field('Harga perolehan (Rp) *',`<input class="in" id="ah" data-num="int" inputmode="numeric" value="${a.harga?numStr(a.harga):''}" ${lock?'disabled':''}>`)}</div>
    <div class="grid2">${field('Umur manfaat',`<select class="in" id="au" ${lock?'disabled':''}>${[[12,'1 tahun'],[24,'2 tahun'],[36,'3 tahun'],[48,'4 tahun (peralatan, elektronik)'],[60,'5 tahun'],[96,'8 tahun (kendaraan, furnitur berat)'],[120,'10 tahun'],[240,'20 tahun (bangunan)']].map(x=>`<option value="${x[0]}" ${+a.umur===x[0]?'selected':''}>${x[1]}</option>`).join('')}</select>`)}${field('Nilai sisa di akhir umur (Rp)',`<input class="in" id="asisa" data-num="int" inputmode="numeric" value="${a.nilaiSisa?numStr(a.nilaiSisa):'0'}" ${lock?'disabled':''}>`)}</div>
    <div class="hint" id="ahint"></div>
    ${ed?'':`<label class="lb" style="margin-top:12px">Dibayar dari</label><div class="seg s3" id="asrc">${[['Kas','Kas tunai'],['Bank','Transfer bank'],['Modal','Sudah dimiliki']].map(x=>`<button type="button" class="${a.sumber===x[0]?'on':''}" data-s="${x[0]}">${x[1]}</button>`).join('')}</div>
     <div id="asrcx" style="margin-top:10px"></div>`}
    ${field('Kondisi',`<select class="in" id="akond">${Object.keys(KOND).map(x=>`<option ${a.kondisi===x?'selected':''}>${x}</option>`).join('')}</select>`)}
    ${field('Catatan',`<input class="in" id="acat" value="${esc(a.catatan||'')}">`)}
    ${field('Foto (opsional)',`<input type="file" class="in" id="af" accept="image/*">`)}`,
    foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="ago">${ic('check')}Simpan</button>`});
  let src=a.sumber;
  const hint=()=>{const h=pn($('#ah',m).value),s=pn($('#asisa',m).value),u=+$('#au',m).value;$('#ahint',m).textContent=h>0?`Penyusutan garis lurus ${rp(Math.round((h-s)/u))} per bulan selama ${u} bulan, mulai bulan perolehan.`:''};
  ['#ah','#asisa','#au'].forEach(x=>$(x,m).addEventListener('input',hint));hint();
  const srcx=()=>{const el=$('#asrcx',m);if(!el)return;el.innerHTML=src==='Modal'?`<div class="grid2">${field('Akumulasi penyusutan sampai saat dicatat (Rp)',`<input class="in" id="asa" data-num="int" inputmode="numeric" placeholder="0">`)}<p class="muted sm" style="align-self:end">Untuk peralatan yang sudah lama dimiliki: dicatat sebagai tambahan modal pemilik, tanpa mengurangi kas.</p></div>`:
    field('Akun '+(src==='Bank'?'bank':'kas'),`<select class="in" id="aka">${akunOpts(M.akunKas.filter(x=>src==='Bank'?x.kode!=='1101':true),src==='Bank'?'1102':'1101')}</select>`)};
  if($('#asrc',m)){$('#asrc',m).onclick=e=>{const x=e.target.closest('[data-s]');if(!x)return;src=x.dataset.s;$$('#asrc button',m).forEach(y=>y.classList.toggle('on',y===x));srcx()};srcx()}
  $('#af',m).onchange=async e=>{try{foto=await fileToData(e.target,900)}catch(er){toast(er.message,'err')}};
  $('#ago',m).onclick=async ev=>{try{const r=await call('asetSave',{id:a.id||'',nama:$('#an',m).value,kode:$('#ak',m).value,kategori:$('#akat',m).value,lokasi:$('#al',m).value,merek:$('#am',m).value,noSeri:$('#as',m).value,pj:$('#apj',m).value,pemasok:$('#ap',m).value,
      tglBeli:$('#at',m).value,harga:pn($('#ah',m).value),umur:+$('#au',m).value,nilaiSisa:pn($('#asisa',m).value),sumber:src,akunKas:($('#aka',m)||{}).value||'',susutAwal:pn(($('#asa',m)||{}).value||0),kondisi:$('#akond',m).value,catatan:$('#acat',m).value,fotoData:foto},ev.currentTarget);
    m.remove();toast(ed?'Aset diperbarui':`Aset ${r.kode} dicatat`,'ok');PAGES.aset();asDetail(r.id)}catch(e){}};
}
async function asDetail(id){
  let a;try{a=await call('asetGet',{id})}catch(e){return}
  const M=await asMeta(),act=a.status==='Aktif';
  const m=modal({title:esc(a.kode)+' · '+esc(a.nama),cls:'lg',body:`<div class="asd"><div class="asf">${a.foto?`<img src="${esc(a.foto)}" alt="">`:`<div class="ph" data-i="${esc(initials(a.nama))}" style="width:100%;height:100%;font-size:28px"></div>`}</div>
     <div><div style="display:flex;gap:6px;flex-wrap:wrap">${act?B(KOND[a.kondisi]||'',a.kondisi):B('err','Dilepas '+tgl(a.lepasTgl))}${B('',a.kategori)}${B('pri',a.lokasiNama)}</div>
      <div class="sm" style="margin-top:8px;line-height:1.7">${a.merek?`Merek: <b>${esc(a.merek)}</b><br>`:''}${a.noSeri?`No. seri: <span class="mono">${esc(a.noSeri)}</span><br>`:''}${a.pj?`Penanggung jawab: <b>${esc(a.pj)}</b><br>`:''}Diperoleh ${tgl(a.tglBeli)}${a.pemasok?' dari '+esc(a.pemasok):''} · ${a.sumber==='Modal'?'sudah dimiliki':'dibayar '+esc(a.sumber)}${a.catatan?`<br>📝 ${esc(a.catatan)}`:''}</div></div></div>
    <div class="mbs" style="margin-top:14px"><div><small>Harga perolehan</small><b>${rp(a.harga)}</b></div><div><small>Akumulasi penyusutan</small><b>${rp(a.akum)}</b><small>${String(a.persen).replace('.',',')}%</small></div><div><small>Nilai buku</small><b>${rp(act?a.buku:0)}</b></div><div><small>Penyusutan / bulan</small><b>${rp(a.perBulan)}</b><small>${a.umur} bln · sisa ${rp(a.nilaiSisa)}</small></div></div>
    ${!act?`<div class="hint">Dilepas ${tgl(a.lepasTgl)} · ${esc(a.lepasAlasan)} · nilai ${rp(a.lepasNilai)}</div>`:''}
    <div class="tabs"><button class="tab on" data-t="log">Riwayat</button><button class="tab" data-t="jad">Jadwal penyusutan</button></div><div id="adt"></div>`,
    foot:`${act&&lv()>=3?`<button class="btn err" data-x="lepas">${ic('out')}Lepas / jual</button>`:''}${act&&lv()>=3&&!a.susut.length?`<button class="btn err" data-x="hapus">${ic('trash')}Hapus</button>`:''}<span style="flex:1"></span><button class="btn" data-act="xaslbl" data-id="${a.id}">${ic('tag')}Label</button>${act&&lv()>=2?`<button class="btn" data-x="kond">${ic('alert')}Ubah kondisi</button><button class="btn" data-x="prb">${ic('sliders')}Catat perbaikan</button><button class="btn pri" data-x="edit">${ic('edit')}Ubah</button>`:''}`});
  const tab=t=>{$$('.tab',m).forEach(b=>b.classList.toggle('on',b.dataset.t===t));$('#adt',m).innerHTML=t==='log'?(a.log.length?`<div class="tw" style="max-height:260px"><table class="tbl"><thead><tr><th>Waktu</th><th>Kejadian</th><th>Kondisi</th><th>Keterangan</th><th class="r">Biaya / nilai</th><th>Oleh</th></tr></thead><tbody>${a.log.map(l=>`<tr><td class="sm" style="white-space:nowrap">${fd(l.t)} ${ft(l.t)}</td><td>${esc(l.jenis)}</td><td>${l.kondisi?B(KOND[l.kondisi]||'',l.kondisi):''}</td><td class="sm">${esc(l.ket)}</td><td class="r">${l.biaya?rp(l.biaya):''}</td><td class="sm muted">${esc(l.user)}</td></tr>`).join('')}</tbody></table></div>`:emptyBox('Belum ada riwayat')):
    `<div class="tw" style="max-height:260px"><table class="tbl"><thead><tr><th>Periode</th><th class="r">Penyusutan</th><th class="r">Nilai buku</th><th>Status</th></tr></thead><tbody>${a.susut.slice(-6).map(x=>`<tr><td>${perNama(x.periode)}</td><td class="r">${rp(x.nilai)}</td><td></td><td>${B('ok','Dibukukan')}</td></tr>`).join('')}${a.jadwal.map(x=>`<tr><td>${perNama(x.periode)}</td><td class="r">${rp(x.nilai)}</td><td class="r">${rp(x.buku)}</td><td>${B('','Rencana')}</td></tr>`).join('')}</tbody></table></div><p class="muted sm">${a.sisaBulan>12?`Ditampilkan 12 bulan berikutnya dari ${a.sisaBulan} bulan tersisa.`:''}</p>`};
  $$('.tab',m).forEach(b=>b.onclick=()=>tab(b.dataset.t));tab('log');
  m.addEventListener('click',async e=>{const x=e.target.closest('[data-x]');if(!x)return;const k=x.dataset.x;
    if(k==='edit'){m.remove();asForm(Object.assign({},a,{susutCount:a.susut.length}))}
    if(k==='kond'){const mm=modal({title:'Ubah kondisi',cls:'sm',body:field('Kondisi',`<select class="in" id="kk">${Object.keys(KOND).map(y=>`<option ${a.kondisi===y?'selected':''}>${y}</option>`).join('')}</select>`)+field('Keterangan',`<input class="in" id="kt" placeholder="mis. kaca retak, layar mati">`),foot:`<button class="btn pri" id="kgo">Simpan</button>`});
      $('#kgo',mm).onclick=async ev=>{try{await call('asetKondisi',{id:a.id,kondisi:$('#kk',mm).value,ket:$('#kt',mm).value},ev.currentTarget);mm.remove();m.remove();toast('Kondisi diperbarui','ok');PAGES.aset();asDetail(a.id)}catch(er){}}}
    if(k==='prb'){const mm=modal({title:'Catat perbaikan / servis',body:field('Keterangan *',`<input class="in" id="pk" placeholder="mis. Servis AC, ganti kompresor">`)+`<div class="grid2">${field('Biaya (Rp)',`<input class="in" id="pb" data-num="int" inputmode="numeric" placeholder="0 bila gratis / garansi">`)}${field('Tanggal',`<input type="date" class="in" id="pt" value="${ymd(today())}">`)}</div><div class="grid2">${field('Dibayar dari',`<select class="in" id="pa">${akunOpts(M.akunKas,'1101')}</select>`)}${field('Kondisi setelah perbaikan',`<select class="in" id="pkd">${Object.keys(KOND).map(y=>`<option>${y}</option>`).join('')}</select>`)}</div><p class="muted sm">Biaya dicatat sebagai Beban perbaikan & pemeliharaan.</p>`,foot:`<button class="btn pri" id="pgo">Simpan</button>`});
      $('#pgo',mm).onclick=async ev=>{try{await call('asetPerbaikan',{id:a.id,ket:$('#pk',mm).value,biaya:pn($('#pb',mm).value),tanggal:$('#pt',mm).value,akunKas:$('#pa',mm).value,kondisi:$('#pkd',mm).value},ev.currentTarget);mm.remove();m.remove();toast('Perbaikan dicatat','ok');PAGES.aset();asDetail(a.id)}catch(er){}}}
    if(k==='lepas'){const mm=modal({title:'Lepas aset '+esc(a.kode),body:`<p class="muted sm" style="margin-top:0">Nilai buku saat ini <b>${rp(a.buku)}</b>. Bukukan dulu penyusutan bulan berjalan agar nilai bukunya tepat.</p>`+field('Alasan *',`<select class="in" id="lr"><option>Dijual</option><option>Rusak berat, dibuang</option><option>Hilang / dicuri</option><option>Dihibahkan</option></select>`)+`<div class="grid2">${field('Hasil penjualan (Rp)',`<input class="in" id="ln" data-num="int" inputmode="numeric" placeholder="0">`)}${field('Tanggal',`<input type="date" class="in" id="lt" value="${ymd(today())}">`)}</div>${field('Uang diterima di',`<select class="in" id="la">${akunOpts(M.akunKas,'1101')}</select>`)}<div class="hint" id="lh"></div>`,foot:`<button class="btn err" id="lgo">Lepas aset</button>`});
      const lh=()=>{const v=pn($('#ln',mm).value),d=v-a.buku;$('#lh',mm).textContent=d===0?'Tanpa laba / rugi.':(d>0?'Laba pelepasan ':'Rugi pelepasan ')+rp(Math.abs(d))+' dicatat otomatis di jurnal.'};$('#ln',mm).addEventListener('input',lh);lh();
      $('#lgo',mm).onclick=async ev=>{try{await call('asetLepas',{id:a.id,alasan:$('#lr',mm).value,nilai:pn($('#ln',mm).value),tanggal:$('#lt',mm).value,akunKas:$('#la',mm).value},ev.currentTarget);mm.remove();m.remove();toast('Aset dilepas','ok');PAGES.aset()}catch(er){}}}
    if(k==='hapus'){if(!await ask(`Hapus aset <b>${esc(a.nama)}</b>? Jurnal perolehannya ikut dihapus. Gunakan hanya bila salah input.`,'Hapus',1))return;try{await call('asetHapus',{id:a.id});m.remove();toast('Aset dihapus','ok');PAGES.aset()}catch(er){}}
  });
}
PAGES.susut=async function(){
  const v=$('#view');v.innerHTML=head('Penyusutan aset','Penyusutan garis lurus dihitung per bulan. Bukukan setiap akhir bulan: Beban penyusutan ↔ Akumulasi penyusutan.')+`<div id="ssv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  let d;try{d=await api('asetSusutCek',{},true)}catch(e){$('#ssv').innerHTML=failBox(e.message);return}
  $('#ssv').innerHTML=`<div class="card pad" style="margin-bottom:12px">${d.n?`<div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap"><span class="prk t-bxgy">${ic('trend')}</span><div style="flex:1;min-width:220px"><b style="font-size:17px">${rp(d.total)} belum dibukukan</b><div class="muted sm">${d.n} baris penyusutan · ${d.periode.map(x=>perNama(x.periode)+' '+rp(x.nilai)).join(' · ')}</div></div>${lv()>=3?`<button class="btn pri" data-act="xsusut">${ic('check')}Bukukan s.d. ${perNama(d.bulanIni)}</button>`:'<span class="muted sm">Hanya manajer aset yang dapat membukukan.</span>'}</div>`:
    `<div style="display:flex;gap:12px;align-items:center"><span class="ai" style="background:var(--okS);color:var(--ok);width:40px;height:40px;border-radius:12px;display:grid;place-items:center">${ic('check')}</span><div><b>Penyusutan sudah up to date</b><div class="muted sm">Semua penyusutan sampai ${perNama(d.bulanIni)} sudah tercatat di akuntansi.</div></div></div>`}</div>
   <div class="card np"><div class="tw">${d.riwayat.length?`<table class="tbl"><thead><tr><th>Periode</th><th class="r">Jumlah aset</th><th class="r">Penyusutan</th></tr></thead><tbody>${d.riwayat.map(x=>`<tr><td>${perNama(x.periode)}</td><td class="r">${x.aset}</td><td class="r b">${rp(x.nilai)}</td></tr>`).join('')}</tbody></table>`:emptyBox('Belum ada penyusutan yang dibukukan','trend')}</div></div>`;
};
ACT.xsusut=async b=>{if(!await ask('Bukukan semua penyusutan yang tertunda ke jurnal akuntansi?','Bukukan'))return;try{const r=await call('asetSusut',{},b);toast(`${r.n} penyusutan (${rp(r.total)}) dibukukan`,'ok');PAGES.susut()}catch(e){}};

/* ======================================================================
 *  SDM & PENGGAJIAN
 * ==================================================================== */
NAV.push({id:'sdmhadir',t:'Absensi hari ini',i:'check',m:'sdm',lv:1},{id:'sdmrekap',t:'Rekap absensi',i:'list',m:'sdm',lv:1},{id:'sdmjadwal',t:'Jadwal & shift',i:'grid',m:'sdm',lv:1},
  {id:'sdmaju',t:'Izin, cuti & lembur',i:'clip',m:'sdm',lv:1},{id:'sdmkry',t:'Karyawan',i:'users',m:'sdm',lv:1},{id:'sdmgaji',t:'Penggajian',i:'wallet',m:'sdm',lv:1},
  {id:'sdmkasbon',t:'Kasbon',i:'book',m:'sdm',lv:1},{id:'sdmset',t:'Aturan absen & akses',i:'pin',m:'sdm',lv:3});
MAINNAV.sdm=['sdmhadir','sdmrekap','sdmaju','sdmgaji'];
const STC={Hadir:'ok',Telat:'warn',Izin:'pri',Sakit:'pri',Cuti:'pri',Alpa:'err',Libur:'','Belum absen':'warn',Terjadwal:'','-':''};
const HARI=['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
let SDM=null;const sdMeta=async f=>{if(!SDM||f)SDM=await api('sdmMeta',{},true);return SDM};
PS.sd={tgl:ymd(today()),per:perNow(),jw:null,aju:'Menunggu',gaji:null};
const fotoTh=(u,t)=>u?`<button class="abf" data-act="xfoto" data-u="${esc(u)}" data-t="${esc(t)}"><img src="${esc(u)}" alt="" loading="lazy"></button>`:'';
ACT.xfoto=b=>modal({title:esc(b.dataset.t),cls:'sm',body:`<img src="${esc(b.dataset.u)}" alt="" style="width:100%;border-radius:12px">`});
const jarakT=j=>j==null?'':`<small class="muted">${n0(j)} m</small>`;
PAGES.sdmhadir=async function(){
  const P=PS.sd;
  $('#view').innerHTML=head('Absensi','Karyawan absen sendiri dari HP (selfie + lokasi) lewat menu <b>Absen saya</b>. Koreksi manual bisa dilakukan di sini.',
    `<input type="date" class="in" id="sdt" value="${P.tgl}" style="width:170px"><a class="btn" href="absen.html" target="_blank">${ic('ext')}Buka halaman absen</a>`)+`<div id="sdhv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#sdt').onchange=e=>{P.tgl=e.target.value;PAGES.sdmhadir()};
  let d;try{d=await api('absenHari',{tanggal:P.tgl},true)}catch(e){$('#sdhv').innerHTML=failBox(e.message);return}
  const s=d.sum,c=k=>s[k]||0;
  $('#sdhv').innerHTML=`<div class="kpis k5">${kpiBox('Hadir',n0(c('Hadir')+c('Telat')),'check',c('Telat')?`${c('Telat')} terlambat`:'tepat waktu semua')+kpiBox('Belum absen',n0(c('Belum absen')),'alert')+kpiBox('Izin / sakit / cuti',n0(c('Izin')+c('Sakit')+c('Cuti')),'clip')+kpiBox('Alpa',n0(c('Alpa')),'x')+kpiBox('Libur',n0(c('Libur')),'sun')}</div>
   <div class="card np"><div class="tw">${d.rows.length?`<table class="tbl rs"><thead><tr><th>Karyawan</th><th>Jadwal</th><th>Status</th><th>Masuk</th><th>Pulang</th><th>Catatan</th>${lv()>=2?'<th></th>':''}</tr></thead><tbody>${d.rows.map(x=>`<tr>
    <td class="nl"><b>${esc(x.nama)}</b><div class="muted sm">${esc(x.posisi||'')}</div></td><td class="sm" data-l="Jadwal">${esc(x.shift||'Libur')}${x.ganti?`<div class="muted">↔ menggantikan ${esc(x.ganti)}</div>`:''}</td><td data-l="Status">${B(STC[x.st]||'',x.st)}${x.telat?`<div class="sm warn-t">telat ${x.telat} mnt</div>`:''}</td>
    <td data-l="Masuk"><div class="abc">${fotoTh(x.fotoMasuk,x.nama+' · masuk '+x.masuk)}<div><b>${x.masuk||'–'}</b><br>${jarakT(x.jarakMasuk)}</div></div></td><td data-l="Pulang"><div class="abc">${fotoTh(x.fotoPulang,x.nama+' · pulang '+x.pulang)}<div><b>${x.pulang||'–'}</b><br>${jarakT(x.jarakPulang)}${x.cepat?`<small class="warn-t"> −${x.cepat} mnt</small>`:''}</div></div></td>
    <td class="sm" data-l="Catatan">${x.sumber==='Manual'?B('','manual')+' ':''}${x.lembur?B('pri','lembur '+nf(x.lembur/60)+' jam')+' ':''}${esc(x.catatan||'')}</td>${lv()>=2?`<td class="r"><button class="ib" data-act="xabm" data-id="${x.id}" data-n="${esc(x.nama)}" data-st="${esc(x.st)}" data-m="${x.masuk}" data-p="${x.pulang}" data-l="${x.lembur}" aria-label="Ubah">${ic('edit')}</button></td>`:''}</tr>`).join('')}</tbody></table>`:emptyBox('Belum ada karyawan. Tambahkan di menu Karyawan.','users')}</div></div>`;
};
ACT.xabm=b=>{const st=['Hadir','Telat'].includes(b.dataset.st)?'Hadir':(['Izin','Sakit','Cuti','Alpa','Libur'].includes(b.dataset.st)?b.dataset.st:'Hadir');
  const m=modal({title:'Absen manual · '+esc(b.dataset.n),cls:'sm',body:`<p class="muted sm" style="margin-top:0">${fd(PS.sd.tgl)}</p>`+field('Status',`<select class="in" id="ms">${['Hadir','Izin','Sakit','Cuti','Alpa','Libur'].map(x=>`<option ${x===st?'selected':''}>${x}</option>`).join('')}</select>`)+
    `<div class="grid2" id="mjam">${field('Jam masuk',`<input type="time" class="in" id="mm" value="${b.dataset.m||''}">`)}${field('Jam pulang',`<input type="time" class="in" id="mp" value="${b.dataset.p||''}">`)}</div>`+field('Lembur (menit)',`<input class="in" id="ml" inputmode="numeric" value="${+b.dataset.l||''}">`)+field('Catatan',`<input class="in" id="mc" placeholder="mis. lupa absen, HP rusak">`),
    foot:`<button class="btn err" id="mdel">Hapus absen</button><span style="flex:1"></span><button class="btn pri" id="mgo">Simpan</button>`});
  const tj=()=>$('#mjam',m).style.display=$('#ms',m).value==='Hadir'?'':'none';$('#ms',m).onchange=tj;tj();
  const sv=async(ev,status)=>{try{await call('absenManual',{karyawan:b.dataset.id,tanggal:PS.sd.tgl,status:status||$('#ms',m).value,masuk:$('#mm',m).value,pulang:$('#mp',m).value,lembur:pn($('#ml',m).value),catatan:$('#mc',m).value},ev.currentTarget);m.remove();toast('Absen disimpan','ok');PAGES.sdmhadir()}catch(e){}};
  $('#mgo',m).onclick=e=>sv(e);$('#mdel',m).onclick=e=>sv(e,'Hapus')};
PAGES.sdmrekap=async function(){
  const P=PS.sd;
  $('#view').innerHTML=head('Rekap absensi','H hadir · T terlambat · I izin · S sakit · C cuti · A alpa (tidak masuk tanpa keterangan).',`${perPicker('rkp',P.per)}<button class="btn" data-act="xrkx">${ic('xls')}Excel</button>`)+`<div class="card np"><div class="tw" id="rkv"><div class="skel" style="margin:14px"></div></div></div>`;
  PERCB.rkp=p=>{P.per=p;PAGES.sdmrekap()};
  try{P.rk=await api('absenRekap',{periode:P.per},true)}catch(e){$('#rkv').innerHTML=failBox(e.message);return}
  const d=P.rk,cc={H:'ok',T:'warn',I:'pri',S:'pri',C:'pri',A:'err','?':'warn'};
  $('#rkv').innerHTML=d.rows.length?`<table class="tbl rkt"><thead><tr><th class="stk">Karyawan</th>${Array.from({length:d.hari},(_,i)=>`<th class="c">${i+1}</th>`).join('')}<th class="c">H</th><th class="c">T</th><th class="c">I</th><th class="c">S</th><th class="c">C</th><th class="c">A</th><th class="r">Lembur</th></tr></thead>
    <tbody>${d.rows.map(x=>`<tr><td class="stk"><b>${esc(x.nama)}</b></td>${x.h.map(c=>`<td class="c"><span class="rkc ${cc[c]||''}">${c}</span></td>`).join('')}<td class="c b">${x.r.hadir}</td><td class="c">${x.r.telat||''}</td><td class="c">${x.r.izin||''}</td><td class="c">${x.r.sakit||''}</td><td class="c">${x.r.cuti||''}</td><td class="c ${x.r.alpa?'err-t b':''}">${x.r.alpa||''}</td><td class="r">${x.r.lembur?nf(x.r.lembur/60)+' j':''}</td></tr>`).join('')}</tbody></table>`:emptyBox('Belum ada karyawan','users');
};
ACT.xrkx=b=>{const d=PS.sd.rk;if(!d)return;const rep=repBase('Rekap Absensi '+perNama(d.periode),d.from,d.to);
  rep.sections.push({title:'Rekap',cols:[{h:'Karyawan',k:'text'},{h:'Hari kerja',k:'int'},{h:'Hadir',k:'int'},{h:'Terlambat',k:'int'},{h:'Menit telat',k:'int'},{h:'Izin',k:'int'},{h:'Sakit',k:'int'},{h:'Cuti',k:'int'},{h:'Alpa',k:'int'},{h:'Lembur (jam)',k:'dec'}],
    rows:d.rows.map(x=>[x.nama,x.r.jadwal,x.r.hadir,x.r.telat,x.r.telatMenit,x.r.izin,x.r.sakit,x.r.cuti,x.r.alpa,Math.round(x.r.lembur/6)/10])});
  rep.sections.push({title:'Harian',cols:[{h:'Karyawan',k:'text'},...Array.from({length:d.hari},(_,i)=>({h:String(i+1),k:'c'}))],rows:d.rows.map(x=>[x.nama,...x.h])});
  rep.file='rekap-absensi_'+d.periode;exportReport('xlsx',rep,b)};
PAGES.sdmjadwal=async function(){
  const P=PS.sd;if(!P.jw){const t=today(),d=(t.getDay()+6)%7;P.jw=ymd(addD(t,-d))}
  $('#view').innerHTML=head('Jadwal & shift','Pola kerja mingguan diatur per karyawan (menu Karyawan). Di sini Anda bisa mengubah jadwal hari tertentu: tukar shift, libur, dll.',
    `<div class="perp"><button class="ib" data-act="xjw" data-n="-7">${ic('cl')}</button><b>${fd(P.jw)} – ${fd(ymd(addD(new Date(P.jw),6)))}</b><button class="ib" data-act="xjw" data-n="7">${ic('cr')}</button></div><button class="btn" data-act="xjwx">${ic('xls')}Unduh jadwal Excel</button>${lv()>=2?`<button class="btn pri" data-act="xjwimp">${ic('inb')}Import Excel</button>`:''}`)+
   `<div class="card np" style="margin-bottom:12px"><div class="tw" id="jwv"><div class="skel" style="margin:14px"></div></div></div><div id="shv"></div>`;
  let d,M;try{[d,M]=await Promise.all([api('jadwalGet',{from:P.jw,hari:7},true),sdMeta(true)])}catch(e){$('#jwv').innerHTML=failBox(e.message);return}
  const SH={};M.shift.forEach(s=>SH[s.id]=s);const can=lv()>=2,td=ymd(today());
  $('#jwv').innerHTML=d.rows.length?`<table class="tbl jwt"><thead><tr><th>Karyawan</th>${d.rows[0].cells.map(c=>{const x=new Date(c.t);return `<th class="c ${c.t===td?'tdy':''}">${HARI[(x.getDay()+6)%7]}<br><small>${x.getDate()}</small></th>`}).join('')}</tr></thead>
    <tbody>${d.rows.map(r=>`<tr><td><b>${esc(r.nama)}</b><div class="muted sm">${esc(r.posisi||'')}</div></td>${r.cells.map(c=>{const s=SH[c.s];return `<td class="c"><button class="jwc ${c.x?'x':''}" ${can?`data-act="xjwc" data-k="${r.id}" data-t="${c.t}" data-s="${c.s}"`:'disabled'} style="${s?`--c:${s.warna}`:''}">${s?`<b>${esc(s.nama)}</b><small>${s.masuk}–${s.pulang}</small>`:'<span class="muted">Libur</span>'}${c.g?`<small style="color:var(--pri);font-weight:700">↔ ganti ${esc(c.g.split(' ')[0])}</small>`:''}${c.st&&!['Terjadwal','Libur','-'].includes(c.st)?`<i class="bdg ${STC[c.st]||''}">${esc(c.st)}</i>`:''}</button></td>`}).join('')}</tr>`).join('')}</tbody></table>`:emptyBox('Belum ada karyawan aktif','users');
  $('#shv').innerHTML=`<div class="ph-row" style="margin-top:6px"><div><h2 style="font-size:17px;margin:0">Shift kerja</h2><p class="muted sm">Toleransi = menit keterlambatan yang masih dianggap tepat waktu.</p></div>${lv()>=3?`<div class="acts"><button class="btn" data-act="xshf">${ic('plus')}Shift baru</button></div>`:''}</div>
   <div class="prg">${M.shift.map(s=>`<div class="card prc ${s.aktif?'':'off'}"><div class="prt"><span class="prk" style="background:${s.warna}22;color:${s.warna}">${ic('sun')}</span><div class="g"><b>${esc(s.nama)}</b><span class="muted sm">${s.masuk} – ${s.pulang} · toleransi ${s.toleransi} menit</span></div>${lv()>=3?`<button class="ib" data-act="xshf" data-id="${s.id}">${ic('edit')}</button>`:''}</div></div>`).join('')}</div>`;
};
/* ---------- Jadwal shift: unduh & import Excel (baris = karyawan, kolom = tanggal) ---------- */
const JW_LIBUR=['libur','l','off','offday','-','x','lib','free','tidakmasuk'],JW_POLA=['pola','*','ikutipola','default','normal'];
const jwNorm=v=>String(v||'').toLowerCase().replace(/[^a-z0-9*\-]/g,'');
function jwShift(v,SH){const n=String(v||'').trim().toLowerCase().replace(/\s+/g,' ');if(!n)return undefined;const c=n.replace(/[^a-z0-9]/g,'');
  if(JW_LIBUR.includes(n.replace(/\s/g,''))||n==='-'||n==='x')return '';if(JW_POLA.includes(n.replace(/\s/g,''))||n==='*')return '*';
  for(const s of SH)if(c===s.nama.toLowerCase().replace(/[^a-z0-9]/g,'')||c===s.id.toLowerCase())return s.id;
  const m=/^(\d{1,2})[.:](\d{2})\s*[-–s\/]+\s*(\d{1,2})[.:](\d{2})/.exec(n);if(m){const a=pad(+m[1])+':'+m[2],b=pad(+m[3])+':'+m[4];const f=SH.find(s=>s.masuk===a&&s.pulang===b);if(f)return f.id}
  const h=SH.filter(s=>s.aktif&&c&&s.nama.toLowerCase().replace(/[^a-z0-9]/g,'').startsWith(c));return h.length===1?h[0].id:null}
function jwDate(v,yr){
  if(v&&typeof v==='object'&&isFinite(v.n)&&v.n>20000&&v.n<80000)return new Date(Date.UTC(1899,11,30)+Math.round(v.n)*864e5).toISOString().slice(0,10);
  const s=cellStr(v);let m=/(\d{4})-(\d{1,2})-(\d{1,2})/.exec(s);if(m)return m[1]+'-'+pad(+m[2])+'-'+pad(+m[3]);
  m=/(\d{1,2})[\/.\-](\d{1,2})(?:[\/.\-](\d{2,4}))?/.exec(s);if(m){let y=m[3]?+m[3]:yr;if(!y)return null;if(y<100)y+=2000;const d=+m[1],mo=+m[2];if(mo<1||mo>12||d<1||d>31)return null;return y+'-'+pad(mo)+'-'+pad(d)}
  return null}
const hexLite=(h,a)=>{h=(h||'#10A391').replace('#','');const n=parseInt(h,16),r=n>>16&255,g=n>>8&255,b=n&255,f=x=>Math.round(x+(255-x)*(1-a)).toString(16).padStart(2,'0').toUpperCase();return f(r)+f(g)+f(b)};
async function jwTemplate(from,hari,btn){
  let d,M;try{[d,M]=await Promise.all([api('jadwalGet',{from,hari},true),sdMeta(true)])}catch(e){return toast(e.message,'err')}
  if(!d.rows.length)return toast('Belum ada karyawan aktif. Tambahkan karyawan dulu di menu Karyawan.','err');
  const SH={};M.shift.forEach(s=>SH[s.id]=s);const dates=d.rows[0].cells.map(c=>c.t);
  const hs={b:1,c:'FFFFFF',bg:X_TEAL,h:'center',v:'center',bb:['thin',X_DEEP],wrap:1},hw={...hs,bg:'B4322B'},tx={bb:['thin',X_LINE],v:'center'},cc=bg=>({bb:['thin',X_LINE],br:['thin',X_LINE],h:'center',v:'center',bg});
  const rows=[{h:34,cells:[{v:'Kode',s:hs},{v:'Nama karyawan',s:hs},{v:'Posisi',s:hs},...dates.map(t=>{const x=new Date(t);return {v:HARI[(x.getDay()+6)%7]+' '+pad(x.getDate())+'/'+pad(x.getMonth()+1)+'/'+x.getFullYear(),s:x.getDay()===0?hw:hs}})]}];
  d.rows.forEach(r=>rows.push({h:20,cells:[{v:r.kode||'',s:tx},{v:r.nama,s:{...tx,b:1}},{v:r.posisi||'',s:tx},...r.cells.map(c=>{const sh=SH[c.s];return sh?{v:sh.nama,s:cc(hexLite(sh.warna,.22))}:{v:'Libur',s:cc('F1F3F2')}})]}));
  const opts=[...M.shift.filter(s=>s.aktif).map(s=>s.nama),'Libur','Pola'];
  const last=xcol(2+dates.length)+(rows.length+50);
  const info=[['Petunjuk import jadwal shift',{b:1,sz:13,c:X_TEAL}],[''],['• Setiap baris = 1 karyawan, setiap kolom = 1 tanggal. Jangan ubah judul kolom tanggal di baris 1.'],['• Isi sel dengan nama shift, atau pilih dari daftar (klik sel → panah kecil).'],
    ['• "Libur" = tidak masuk. "Pola" = kembali ikut pola mingguan karyawan (diatur di menu Karyawan).'],['• Sel kosong = jadwal tanggal itu tidak diubah.'],['• Boleh juga singkatan: P = Pagi, S = Siang (bila tidak ada shift lain berawalan huruf sama), atau jam 08:00-16:00.'],
    ['• Karyawan dicocokkan dari kolom Kode, lalu Nama. Karyawan yang tidak perlu diubah boleh dihapus barisnya.'],['• Rentang tanggal boleh diubah (mis. tambah kolom tanggal berikutnya) — maksimal 62 hari sekali import.'],[''],['Daftar shift',{b:1,c:X_TEAL}],
    ...M.shift.map(s=>['   '+s.nama+'  :  '+s.masuk+' – '+s.pulang+(s.aktif?'':'  (nonaktif)')]),['   Libur  :  tidak masuk'],['   Pola  :  ikuti pola mingguan']];
  const data=xlsxBuild([{name:'Jadwal',cols:[10,24,16,...dates.map(()=>12)],rows,freeze:1,freezeCol:3,selected:true,land:true,dv:[{ref:'D2:'+last,list:opts}]},
    {name:'Petunjuk',cols:[110],rows:info.map(r=>({cells:[{v:r[0],s:r[1]||{}}]}))}],'Jadwal shift '+(S.set.namaToko||''),'Jadwal shift');
  saveFile('jadwal-shift_'+dates[0]+'_'+dates[dates.length-1]+'.xlsx',data,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}
function jwPeriode(m){const v=$('#jwp',m).value;if(v==='minggu'){return {from:PS.sd.jw,hari:7}}const [y,mo]=v.split('-').map(Number);return {from:y+'-'+pad(mo)+'-01',hari:new Date(y,mo,0).getDate()}}
function jwPerOpts(){const t=today(),o=[`<option value="minggu">Minggu yang tampil (${fd(PS.sd.jw)} – ${fd(ymd(addD(new Date(PS.sd.jw),6)))})</option>`];for(let i=0;i<4;i++){const d=new Date(t.getFullYear(),t.getMonth()+i,1);o.push(`<option value="${d.getFullYear()}-${d.getMonth()+1}" ${i===1?'selected':''}>${X_MON_L[d.getMonth()]} ${d.getFullYear()}</option>`)}return o.join('')}
const X_MON_L=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
ACT.xjwx=()=>{const m=modal({title:'Unduh jadwal shift (Excel)',cls:'sm',body:`<label class="lb">Periode</label><select class="in" id="jwp">${jwPerOpts()}</select><p class="muted sm">File berisi jadwal yang berlaku saat ini (pola mingguan + perubahan khusus). Bisa langsung diedit lalu di-import kembali.</p>`,foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="jwgo">${ic('dl')}Unduh</button>`});
  $('#jwgo',m).onclick=async ev=>{ev.currentTarget.disabled=true;const p=jwPeriode(m);await jwTemplate(p.from,p.hari);m.remove()}};
ACT.xjwimp=async()=>{
  const M=await sdMeta(true);let P=null;
  const m=modal({title:'Import jadwal shift dari Excel',cls:'xl',sticky:1,body:`<div class="imp-steps">
    <div class="card pad imp-s"><b>1. Unduh template</b><p class="muted sm">Berisi semua karyawan aktif dan jadwal yang berlaku saat ini — tinggal ubah selnya.</p><select class="in" id="jwp" style="margin-bottom:8px">${jwPerOpts()}</select><button class="btn" id="jwt">${ic('dl')}Unduh template Excel</button></div>
    <div class="card pad imp-s"><b>2. Pilih file</b><p class="muted sm">Format <b>.xlsx</b> atau <b>.csv</b>. Baris = karyawan, kolom = tanggal.</p><label class="imp-drop" id="jwd"><input type="file" id="jwf" accept=".xlsx,.csv" hidden>${ic('xls')}<span id="jwfn">Klik atau seret file ke sini</span></label></div>
    <div class="card pad imp-s"><b>3. Periksa & import</b><p class="muted sm">Isi sel: nama shift (${M.shift.filter(s=>s.aktif).map(s=>'<b>'+esc(s.nama)+'</b>').join(', ')}), <b>Libur</b>, atau <b>Pola</b>. Sel kosong tidak diubah. Bila ada yang salah, tidak ada yang tersimpan.</p></div></div><div id="jwpv"></div>`,
    foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="jwgo" disabled>${ic('check')}Import</button>`});
  $('#jwt',m).onclick=async ev=>{const b=ev.currentTarget;b.disabled=true;const p=jwPeriode(m);await jwTemplate(p.from,p.hari);b.disabled=false};
  const SH={};M.shift.forEach(s=>SH[s.id]=s);
  const parse=rows=>{
    let hi=-1,ci={},dc=[];
    for(let r=0;r<Math.min(rows.length,12);r++){const R=rows[r]||[];const c={};const dd=[];R.forEach((v,i)=>{const n=jwNorm(cellStr(v));if(c.kode==null&&['kode','kodekaryawan','nik','nip','id'].includes(n))c.kode=i;else if(c.nama==null&&['nama','namakaryawan','karyawan','namalengkap'].includes(n))c.nama=i});
      const yr=(()=>{for(const v of R){const t=jwDate(v,0);if(t)return +t.slice(0,4)}return new Date().getFullYear()})();
      R.forEach((v,i)=>{if(i===c.kode||i===c.nama)return;const t=jwDate(v,yr);if(t)dd.push([i,t])});
      if((c.kode!=null||c.nama!=null)&&dd.length){hi=r;ci=c;dc=dd;break}}
    if(hi<0)throw new Error('Judul kolom tidak ditemukan. Baris judul harus berisi "Kode" atau "Nama karyawan" dan tanggal (mis. 01/11/2026). Gunakan template agar mudah.');
    const ts=dc.map(x=>x[1]).sort();if(new Set(ts).size!==ts.length)throw new Error('Ada kolom tanggal yang dobel di baris judul');
    if((new Date(ts[ts.length-1])-new Date(ts[0]))/864e5>61)throw new Error('Rentang tanggal lebih dari 62 hari. Pecah menjadi beberapa file.');
    const items=[];
    for(let r=hi+1;r<rows.length;r++){const R=rows[r]||[];const kode=ci.kode!=null?cellStr(R[ci.kode]):'',nama=ci.nama!=null?cellStr(R[ci.nama]):'';if(!kode&&!nama)continue;
      const cells={},bad=[];dc.forEach(([i,t])=>{const v=cellStr(R[i]);if(!v)return;cells[t]=v;if(jwShift(v,M.shift)===null)bad.push(fd(t).replace(/ \d{4}$/,'')+': "'+v+'"')});
      items.push({_row:r+1,kode,nama,cells,_err:bad.length?'Shift tidak dikenal — '+bad.slice(0,3).join(', ')+(bad.length>3?' …':''):''})}
    if(!items.length)throw new Error('Tidak ada baris karyawan di bawah judul kolom');
    return {items,dates:dc.map(x=>x[1])};
  };
  const draw=()=>{const bad=P.items.filter(x=>x._err),n=P.items.reduce((a,x)=>a+Object.keys(x.cells).length,0);const D=[...P.dates].sort();
    const chip=v=>{if(v==null)return '<span class="muted">·</span>';const id=jwShift(v,M.shift);if(id===null)return `<span class="bdg err">${esc(v)}</span>`;if(id==='')return '<span class="muted sm">Libur</span>';if(id==='*')return '<span class="muted sm">Pola</span>';const s=SH[id];return `<span class="jwch" style="background:${s.warna}22;color:${s.warna};border:1px solid ${s.warna}55;border-radius:6px;padding:1px 6px;font-weight:700;font-size:12px">${esc(s.nama)}</span>`};
    $('#jwpv',m).innerHTML=`<div class="imp-sum"><span class="bdg ok">${n0(P.items.length-bad.length)} karyawan siap</span>${bad.length?`<span class="bdg err">${n0(bad.length)} baris bermasalah</span>`:''}<span class="muted sm">${n0(n)} sel jadwal · ${fd(D[0])} – ${fd(D[D.length-1])}</span></div>
     <div class="card np"><div class="tw" style="max-height:46vh"><table class="tbl jwt"><thead><tr><th>Baris</th><th>Karyawan</th>${D.map(t=>{const x=new Date(t);return `<th class="c ${x.getDay()===0?'err':''}">${HARI[(x.getDay()+6)%7]}<br><small>${x.getDate()}/${x.getMonth()+1}</small></th>`}).join('')}</tr></thead><tbody>${P.items.slice(0,300).map(x=>`<tr><td class="muted">${x._row}</td><td style="white-space:nowrap"><b>${esc(x.nama||x.kode)}</b>${x.kode&&x.nama?`<div class="muted sm mono">${esc(x.kode)}</div>`:''}${x._err?`<div class="sm" style="color:var(--err);white-space:normal;max-width:240px">${esc(x._err)}</div>`:''}</td>${D.map(t=>`<td class="c">${chip(x.cells[t])}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`;
    $('#jwgo',m).disabled=!n||bad.length>0};
  const load=async f=>{if(!f)return;$('#jwfn',m).textContent=f.name;$('#jwpv',m).innerHTML='<div class="skel" style="margin:14px 0"></div>';
    try{const rows=/\.csv$/i.test(f.name)?readCsv(await f.text()):await readXlsx(await f.arrayBuffer());P=parse(rows);draw()}catch(e){P=null;$('#jwpv',m).innerHTML=`<div class="err-msg">${esc(e.message)}</div>`;$('#jwgo',m).disabled=true}};
  $('#jwf',m).onchange=e=>load(e.target.files[0]);
  const dz=$('#jwd',m);dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('on')});dz.addEventListener('dragleave',()=>dz.classList.remove('on'));dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('on');load(e.dataTransfer.files[0])});
  $('#jwgo',m).onclick=async ev=>{try{const r=await call('jadwalImport',{items:P.items.map(x=>({_row:x._row,kode:x.kode,nama:x.nama,cells:x.cells}))},ev.currentTarget);m.remove();
    toast(`Jadwal ${n0(r.karyawan)} karyawan diimpor (${fd(r.from)} – ${fd(r.to)}): ${n0(r.khusus)} jadwal khusus, ${n0(r.pola)} sesuai pola`,'ok');
    const d=new Date(r.from),w=(d.getDay()+6)%7;PS.sd.jw=ymd(addD(d,-w));PAGES.sdmjadwal()}catch(e){}};
};
ACT.xjw=b=>{PS.sd.jw=ymd(addD(new Date(PS.sd.jw),+b.dataset.n));PAGES.sdmjadwal()};
ACT.xjwc=async b=>{const M=await sdMeta();const m=modal({title:'Jadwal '+fd(b.dataset.t),cls:'sm',body:`<div class="mres">${[...M.shift.filter(s=>s.aktif).map(s=>`<button class="li mpick" data-s="${s.id}"><span class="av" style="background:${s.warna}">${esc(s.nama[0])}</span><div class="g"><b>${esc(s.nama)}</b><span class="muted sm">${s.masuk} – ${s.pulang}</span></div></button>`),`<button class="li mpick" data-s=""><span class="av" style="background:var(--mut)">L</span><div class="g"><b>Libur</b></div></button>`,`<button class="li mpick" data-s="*"><span class="av" style="background:var(--card2);color:var(--txt)">↺</span><div class="g"><b>Ikuti pola mingguan</b><span class="muted sm">Hapus perubahan khusus tanggal ini</span></div></button>`].join('')}</div>`});
  m.addEventListener('click',async e=>{const x=e.target.closest('[data-s]');if(!x)return;try{await call('jadwalSave',{from:PS.sd.jw,hari:7,cells:[{k:b.dataset.k,t:b.dataset.t,s:x.dataset.s}]});m.remove();PAGES.sdmjadwal()}catch(er){}})};
ACT.xshf=async b=>{const M=await sdMeta();const s=M.shift.find(x=>x.id===b.dataset.id)||{nama:'',masuk:'08:00',pulang:'16:00',toleransi:10,warna:'#10A391',aktif:true};
  const m=modal({title:s.id?'Ubah shift':'Shift baru',cls:'sm',body:field('Nama shift',`<input class="in" id="sn" value="${esc(s.nama)}" placeholder="mis. Pagi, Siang, Malam">`)+`<div class="grid2">${field('Jam masuk',`<input type="time" class="in" id="sm" value="${s.masuk}">`)}${field('Jam pulang',`<input type="time" class="in" id="sp" value="${s.pulang}">`)}</div><div class="grid2">${field('Toleransi telat (menit)',`<input class="in" id="st" inputmode="numeric" value="${s.toleransi}">`)}${field('Warna',`<input type="color" class="in" id="sw" value="${s.warna}" style="padding:4px;height:44px">`)}</div><label class="li" style="padding:6px 0"><input type="checkbox" id="sa" ${s.aktif?'checked':''} style="width:18px;height:18px"> Aktif</label>`,foot:`<button class="btn pri" id="sgo">Simpan</button>`});
  $('#sgo',m).onclick=async ev=>{try{SDM=await call('shiftSave',{id:s.id||'',nama:$('#sn',m).value,masuk:$('#sm',m).value,pulang:$('#sp',m).value,toleransi:pn($('#st',m).value),warna:$('#sw',m).value,aktif:$('#sa',m).checked},ev.currentTarget);m.remove();toast('Shift disimpan','ok');PAGES.sdmjadwal()}catch(e){}}};
const PJC={Izin:'pri',Sakit:'warn',Cuti:'ok',Lembur:'',Akses:'err'},PJN={Akses:'Izin akses'},PJS={Menunggu:'warn',Disetujui:'ok',Ditolak:'err',Dibatalkan:''};
const pjTgl=x=>`${fd(x.mulai)}${x.selesai!==x.mulai?' – '+fd(x.selesai):''}<div class="muted">${x.jamMulai?x.jamMulai+'–'+x.jamSelesai+' · '+nf(x.menit/60)+' jam':x.jenis==='Lembur'?nf(x.menit/60)+' jam':x.hari+' hari'}</div>`;
PAGES.sdmaju=async function(){
  const P=PS.sd;
  $('#view').innerHTML=head('Izin, sakit, cuti, lembur & akses','Karyawan mengajukan dari halaman <b>Absen saya</b>; <b>atasan langsung</b> menyetujui dari HP-nya (menu Tim saya) atau bagian SDM dari sini. Lembur yang disetujui otomatis masuk gaji.',
    lv()>=2?`<button class="btn pri" data-act="xpjlem">${ic('plus')}Perintah lembur</button>`:'')+
   `<div class="card tb"><div class="chips">${['Menunggu','Disetujui','Ditolak','Dibatalkan','Semua'].map(x=>`<button class="chip ${P.aju===x?'on':''}" data-act="xajf" data-s="${x}">${x}</button>`).join('')}</div></div><div class="card np"><div class="tw" id="ajv"><div class="skel" style="margin:14px"></div></div></div>`;
  let d;try{d=await api('pjList',{status:P.aju==='Semua'?'':P.aju},true)}catch(e){$('#ajv').innerHTML=failBox(e.message);return}
  P.pj=d;
  $('#ajv').innerHTML=d.rows.length?`<table class="tbl rs"><thead><tr><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th>Alasan</th><th>Pengganti</th><th>Status</th>${lv()>=2?'<th class="r">Aksi</th>':''}</tr></thead><tbody>${d.rows.map(x=>`<tr><td class="nl"><b>${esc(x.nama)}</b><div class="muted sm">${x.atasanNama?'atasan: '+esc(x.atasanNama):'tanpa atasan'} · ${x.diajukanOleh&&x.status==='Disetujui'&&x.oleh===x.diajukanOleh&&x.jenis==='Lembur'?'perintah '+esc(x.diajukanOleh):'diajukan '+fd(x.dibuat)}</div></td><td data-l="Jenis">${B(PJC[x.jenis],PJN[x.jenis]||x.jenis)}</td>
    <td data-l="Tanggal" class="sm">${pjTgl(x)}</td><td class="sm" data-l="Alasan">${esc(x.alasan)}${x.lampiran?` · <a href="${esc(x.lampiran)}" target="_blank">lampiran</a>`:''}${x.catatan?`<div class="muted">↳ ${esc(x.catatan)}</div>`:''}</td>
    <td class="sm" data-l="Pengganti">${x.penggantiNama?esc(x.penggantiNama):'<span class="muted">–</span>'}</td>
    <td data-l="Status">${B(PJS[x.status]||'',x.status)}${x.oleh?`<div class="muted sm">${esc(x.oleh)}</div>`:''}</td>${lv()>=2?`<td class="r" style="white-space:nowrap">${x.status==='Menunggu'?`<button class="btn sm pri" data-act="xajd" data-id="${x.id}">Proses</button>`:x.status==='Disetujui'?`<button class="btn sm" data-act="xajb" data-id="${x.id}">Batalkan</button>`:''}</td>`:''}</tr>`).join('')}</tbody></table>`:emptyBox(P.aju==='Menunggu'?'Tidak ada pengajuan yang menunggu persetujuan':'Belum ada pengajuan','clip');
};
ACT.xajf=b=>{PS.sd.aju=b.dataset.s;PAGES.sdmaju()};
/* rencana pengganti dalam tabel kecil */
const gantiTbl=r=>!r||!r.rows.length?'<p class="muted sm" style="margin:6px 0 0">Pemohon tidak terjadwal masuk pada tanggal tersebut, jadi tidak perlu pengganti.</p>':
  `<div class="tw" style="max-height:200px;margin-top:8px"><table class="tbl"><thead><tr><th>Tanggal</th><th>Shift pemohon</th><th>Jadwal ${esc(r.pengganti.split(' ')[0])}</th><th>Hasil</th></tr></thead><tbody>${r.rows.map(o=>`<tr><td class="sm">${fd(o.t)}</td><td class="sm">${esc(o.shiftNama)}</td><td class="sm">${esc(o.b)}</td><td class="sm">${B({ekstra:'ok',tukar:'warn',sama:'',bentrok:'err'}[o.aksi],{ekstra:'Masuk (hari libur)',tukar:'Tukar shift',sama:'Dilewati',bentrok:'Dilewati'}[o.aksi])}<div class="muted">${esc(o.ket)}</div></td></tr>`).join('')}</tbody></table></div>`;
ACT.xajd=async b=>{
  const x=(PS.sd.pj.rows||[]).find(r=>r.id===b.dataset.id);if(!x)return;const ganti=['Izin','Sakit','Cuti'].includes(x.jenis);
  const kar=(PS.sd.pj.karyawan||[]).filter(k=>k.id!==x.karyawan);
  const m=modal({title:'Proses pengajuan',cls:'lg',body:`<div class="mbs" style="margin-bottom:12px"><div><small>Karyawan</small><b>${esc(x.nama)}</b><small>${x.atasanNama?'atasan: '+esc(x.atasanNama):'tanpa atasan langsung'}</small></div><div><small>Jenis</small><b>${esc(PJN[x.jenis]||x.jenis)}</b></div><div><small>Tanggal</small><b class="sm">${pjTgl(x)}</b></div></div>
    <p style="margin:0 0 10px"><b>Alasan:</b> ${esc(x.alasan)}${x.lampiran?` · <a href="${esc(x.lampiran)}" target="_blank">lihat lampiran</a>`:''}</p>
    ${ganti?`${field('Karyawan pengganti (backup) selama tidak masuk',`<select class="in" id="pjg"><option value="">— tanpa pengganti —</option>${kar.map(k=>`<option value="${k.id}" ${k.id===x.pengganti?'selected':''}>${esc(k.nama)}${k.posisi?' · '+esc(k.posisi):''}</option>`).join('')}</select>`)}<div id="pjgv"></div>`:''}
    ${x.jenis==='Akses'?'<div class="hint">Selama jam ini karyawan boleh membuka aplikasi meski di luar jadwal kerjanya.</div>':''}${x.jenis==='Lembur'?'<div class="hint">Upah lembur dihitung dari jam yang benar-benar dijalani (absen pulang), maksimal sesuai jam yang disetujui — bisa diubah di Titik absen & aturan.</div>':''}
    ${field('Catatan untuk karyawan (opsional)',`<input class="in" id="pjc" placeholder="mis. Disetujui, serahkan kunci ke Sari">`)}`,
    foot:`<button class="btn err" id="pjno">Tolak</button><span style="flex:1"></span><button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="pjok">${ic('check')}Setujui</button>`});
  const prev=async()=>{const g=$('#pjg',m);if(!g)return;if(!g.value){$('#pjgv',m).innerHTML='';return}$('#pjgv',m).innerHTML='<div class="skel" style="margin:8px 0"></div>';try{$('#pjgv',m).innerHTML=gantiTbl(await api('pjCekGanti',{id:x.id,pengganti:g.value},true))}catch(e){$('#pjgv',m).innerHTML=`<div class="err-msg">${esc(e.message)}</div>`}};
  if($('#pjg',m)){$('#pjg',m).onchange=prev;prev()}
  const go=async(ev,ok)=>{try{const r=await call('pjProses',{id:x.id,setuju:ok,catatan:$('#pjc',m).value,pengganti:$('#pjg',m)?$('#pjg',m).value:'',status:PS.sd.aju==='Semua'?'':PS.sd.aju},ev.currentTarget);m.remove();toast(ok?'Pengajuan disetujui':'Pengajuan ditolak','ok');PAGES.sdmaju()}catch(e){}};
  $('#pjok',m).onclick=ev=>go(ev,true);$('#pjno',m).onclick=ev=>go(ev,false);
};
ACT.xajb=async b=>{const x=(PS.sd.pj.rows||[]).find(r=>r.id===b.dataset.id);if(!x)return;if(!await ask(`Batalkan ${esc(PJN[x.jenis]||x.jenis)} <b>${esc(x.nama)}</b> (${fd(x.mulai)})?${x.penggantiNama?' Jadwal pengganti '+esc(x.penggantiNama)+' ikut dihapus.':''}`,'Batalkan',1))return;
  try{await call('pjBatal',{id:x.id},b);toast('Pengajuan dibatalkan','ok');PAGES.sdmaju()}catch(e){}};
ACT.xpjlem=async()=>{const M=await sdMeta();const m=modal({title:'Perintah lembur',body:`${field('Karyawan',`<select class="in" id="plk">${M.karyawan.map(k=>`<option value="${k.id}">${esc(k.nama)}${k.posisi?' · '+esc(k.posisi):''}</option>`).join('')}</select>`)}
  <div class="grid2">${field('Tanggal',`<input type="date" class="in" id="plt" value="${ymd(today())}">`)}<div class="grid2">${field('Mulai',`<input type="time" class="in" id="plm" value="17:00">`)}${field('Selesai',`<input type="time" class="in" id="pls" value="20:00">`)}</div></div>
  ${field('Pekerjaan yang dilemburkan',`<input class="in" id="pla" placeholder="mis. Stok opname akhir bulan">`)}<p class="muted sm">Perintah lembur langsung berstatus disetujui dan karyawan mendapat notifikasi WhatsApp (bila nomor HP & gateway WA sudah diatur).</p>`,
  foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="plgo">${ic('check')}Simpan perintah</button>`});
  $('#plgo',m).onclick=async ev=>{try{await call('pjPerintah',{karyawan:$('#plk',m).value,tanggal:$('#plt',m).value,jamMulai:$('#plm',m).value,jamSelesai:$('#pls',m).value,alasan:$('#pla',m).value},ev.currentTarget);m.remove();toast('Perintah lembur disimpan','ok');PS.sd.aju='Disetujui';PAGES.sdmaju()}catch(e){}}};
PAGES.sdmkry=async function(){
  $('#view').innerHTML=head('Karyawan','Data karyawan, akun login untuk absen, jadwal kerja, dan komponen gaji.',(S.user.admin?`<button class="btn" data-act="xkyakun">${ic('user')}Buat akun login</button>`:'')+(lv()>=3?`<button class="btn" data-act="xkyimp">${ic('inb')}Import Excel</button><button class="btn pri" data-act="xkry">${ic('plus')}Karyawan baru</button>`:''))+`<div class="card np"><div class="tw" id="kyv"><div class="skel" style="margin:14px"></div></div></div>`;
  let d;try{[d]=await Promise.all([api('kryList',{},true),sdMeta(true)])}catch(e){$('#kyv').innerHTML=failBox(e.message);return}
  PS.sd.kry=d;
  $('#kyv').innerHTML=d.length?`<table class="tbl rs click"><thead><tr><th>Karyawan</th><th>Akun login</th><th>Atasan</th><th>Jadwal</th><th>Gaji</th><th>Sisa cuti</th><th>Kasbon</th><th>Hari ini</th></tr></thead><tbody>${d.map(k=>`<tr data-act="${lv()>=3?'xkry':''}" data-id="${k.id}" class="${k.status==='Aktif'?'':'off'}">
    <td class="nl"><div class="pcell"><span class="av">${esc(initials(k.nama))}</span><div><b>${esc(k.nama)}</b><span class="muted sm">${esc(k.kode)} · ${esc(k.posisi||'-')} · ${esc(k.lokasiNama)}</span></div></div></td>
    <td data-l="Akun">${k.user?`<span class="mono sm">@${esc(k.user)}</span>`:B('warn','belum punya akun')}</td><td class="sm" data-l="Atasan">${k.atasanNama?esc(k.atasanNama):'<span class="muted">–</span>'}</td><td class="sm" data-l="Jadwal">${esc(k.shiftNama)}<div class="muted">${[...k.hariKerja].map(h=>HARI[h-1]).join(', ')}</div></td>
    <td class="sm" data-l="Gaji">${rp(k.gajiPokok)}<div class="muted">${k.tipeGaji==='Harian'?'per hari':'per bulan'}</div></td><td data-l="Cuti">${k.sisaCuti}/${k.jatahCuti}</td><td data-l="Kasbon" class="${k.kasbon?'warn-t b':''}">${k.kasbon?rp(k.kasbon):'–'}</td>
    <td data-l="Hari ini">${k.status==='Aktif'?B(STC[k.hariIni]||'',k.hariIni)+(k.masuk?` <small class="muted">${k.masuk}</small>`:''):B('err','Nonaktif')}</td></tr>`).join('')}</tbody></table>`:emptyBox('Belum ada karyawan. Tambahkan karyawan, lalu hubungkan dengan akun login agar bisa absen dari HP.','users')+(lv()>=3?`<div class="c" style="padding:0 0 22px"><button class="btn" data-act="xkyimp">${ic('inb')}Import dari Excel</button> <button class="btn" data-act="xkycth">${ic('plus')}Isi 5 data contoh</button></div>`:'');
};
ACT.xkry=async b=>{const M=await sdMeta();const k=(PS.sd.kry||[]).find(x=>x.id===b.dataset.id)||{tipeGaji:'Bulanan',hariKerja:'123456',jatahCuti:12,status:'Aktif',shift:(M.shift[0]||{}).id,lokasi:S.lokJual,tglMasuk:ymd(today())};
  const num=(id,v,ph='')=>`<input class="in" id="${id}" data-num="int" inputmode="numeric" value="${v?numStr(v):''}" placeholder="${ph}">`;
  const m=modal({title:k.id?'Ubah karyawan':'Karyawan baru',cls:'lg',body:`<div class="grid2">${field('Nama lengkap *',`<input class="in" id="kn" value="${esc(k.nama||'')}">`)}${field('Posisi / jabatan kerja',`<input class="in" id="kp" value="${esc(k.posisi||'')}" placeholder="mis. Kasir, Staf gudang">`)}</div>
    <div class="grid2">${field('Akun login (untuk absen dari HP)',`<select class="in" id="ku"><option value="">— belum dihubungkan —</option>${M.users.map(u=>`<option value="${u.id}" ${u.id===k.userId?'selected':''}>${esc(u.nama)} (@${esc(u.username)})</option>`).join('')}</select>`)}${field('Penempatan / titik absen',`<select class="in" id="kl">${lokOpts(k.lokasi,false)}</select>`)}</div>
    <div class="grid2">${field('Atasan langsung (yang menyetujui izin, cuti, lembur)',`<select class="in" id="kat"><option value="">— tidak ada (disetujui bagian SDM) —</option>${M.karyawan.filter(x=>x.id!==k.id).map(x=>`<option value="${x.id}" ${x.id===k.atasan?'selected':''}>${esc(x.nama)}${x.posisi?' · '+esc(x.posisi):''}</option>`).join('')}</select>`)}<div></div></div>
    <div class="grid2">${field('No. HP (untuk notifikasi WhatsApp)',`<input class="in" id="kh" value="${esc(k.hp||'')}" inputmode="tel">`)}${field('Tanggal masuk kerja',`<input type="date" class="in" id="kt" value="${k.tglMasuk||''}">`)}</div>
    <h4 class="sub">Jadwal kerja</h4><div class="grid2">${field('Shift',`<select class="in" id="ks">${M.shift.map(s=>`<option value="${s.id}" ${s.id===k.shift?'selected':''}>${esc(s.nama)} (${s.masuk}–${s.pulang})</option>`).join('')}</select>`)}${field('Hari kerja',`<div class="hk" id="khk">${HARI.map((h,i)=>`<button type="button" class="${k.hariKerja.includes(String(i+1))?'on':''}" data-h="${i+1}">${h}</button>`).join('')}</div>`)}</div>
    <h4 class="sub">Gaji</h4><div class="grid2">${field('Sistem gaji',`<div class="seg s3" style="grid-template-columns:1fr 1fr" id="ktg">${['Bulanan','Harian'].map(x=>`<button type="button" class="${k.tipeGaji===x?'on':''}" data-g="${x}">${x}</button>`).join('')}</div>`)}${field('<span id="kgl">Gaji pokok per bulan</span> (Rp)',num('kg',k.gajiPokok))}</div>
    <div class="grid2">${field('Tunjangan tetap / bulan',num('ktj',k.tunjangan,'0'))}${field('Uang makan / hari hadir',num('kum',k.uangMakan,'0'))}</div>
    <div class="grid2">${field('Upah lembur / jam',num('klb',k.lemburJam,'0'))}${field('Denda terlambat / kejadian',num('kdt',k.dendaTelat,'0'))}</div>
    <div class="grid2">${field('Jatah cuti / tahun (hari)',`<input class="in" id="kc" inputmode="numeric" value="${k.jatahCuti}">`)}${field('Template KPI',`<select class="in" id="kk"><option value="">— tanpa KPI —</option>${M.kpi.map(x=>`<option value="${x.id}" ${x.id===k.kpi?'selected':''}>${esc(x.nama)}</option>`).join('')}</select>`)}</div>
    <div class="grid2">${field('Bank',`<input class="in" id="kb" value="${esc(k.bank||'')}">`)}${field('No. rekening',`<input class="in mono" id="kr" value="${esc(k.rekening||'')}">`)}</div>
    <div class="grid2">${field('Kode karyawan',`<input class="in mono" id="kkd" value="${esc(k.kode||'')}" placeholder="otomatis">`)}${field('Status',`<select class="in" id="kst"><option ${k.status==='Aktif'?'selected':''}>Aktif</option><option ${k.status!=='Aktif'?'selected':''}>Nonaktif</option></select>`)}</div>`,
    foot:`${k.id?`<button class="btn err" id="kdel">${ic('trash')}Hapus</button>`:''}<span style="flex:1"></span><button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="kgo">${ic('check')}Simpan</button>`});
  let tg=k.tipeGaji;const gl=()=>$('#kgl',m).textContent=tg==='Harian'?'Upah per hari hadir':'Gaji pokok per bulan';gl();
  $('#ktg',m).onclick=e=>{const x=e.target.closest('[data-g]');if(!x)return;tg=x.dataset.g;$$('#ktg button',m).forEach(y=>y.classList.toggle('on',y===x));gl()};
  $('#khk',m).onclick=e=>{const x=e.target.closest('[data-h]');if(x)x.classList.toggle('on')};
  $('#kgo',m).onclick=async ev=>{try{await call('krySave',{id:k.id||'',nama:$('#kn',m).value,posisi:$('#kp',m).value,userId:$('#ku',m).value,lokasi:$('#kl',m).value,hp:$('#kh',m).value,tglMasuk:$('#kt',m).value,shift:$('#ks',m).value,hariKerja:$$('#khk .on',m).map(x=>x.dataset.h).join(''),
    tipeGaji:tg,gajiPokok:pn($('#kg',m).value),tunjangan:pn($('#ktj',m).value),uangMakan:pn($('#kum',m).value),lemburJam:pn($('#klb',m).value),dendaTelat:pn($('#kdt',m).value),jatahCuti:pn($('#kc',m).value),kpi:$('#kk',m).value,bank:$('#kb',m).value,rekening:$('#kr',m).value,kode:$('#kkd',m).value,status:$('#kst',m).value,atasan:$('#kat',m).value},ev.currentTarget);m.remove();toast('Data karyawan disimpan','ok');SDM=null;PAGES.sdmkry()}catch(e){}};
  if($('#kdel',m))$('#kdel',m).onclick=async()=>{if(!await ask(`Hapus <b>${esc(k.nama)}</b>?`,'Hapus',1))return;try{await call('kryDel',{id:k.id});m.remove();toast('Karyawan dihapus','ok');PAGES.sdmkry()}catch(e){}};
};
ACT.xkyimp=()=>xlImport(Object.assign({},IMPORT_KRY,{done:()=>{SDM=null;PAGES.sdmkry()}}));
ACT.xkycth=async b=>{try{const r=await call('kryContoh',{},b);toast(r.n+' karyawan contoh ditambahkan','ok');PAGES.sdmkry()}catch(e){}};
/* ---- penggajian ---- */
const GST={Draf:'',Final:'pri',Dibayar:'ok'};
PAGES.sdmgaji=async function(){
  const P=PS.sd;
  $('#view').innerHTML=head('Penggajian','Gaji dihitung otomatis dari absensi, lembur, KPI, dan kasbon. Final → jurnal beban gaji; Bayar → kas/bank berkurang.',perPicker('gjp',P.per))+`<div id="gjv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  PERCB.gjp=p=>{P.per=p;PAGES.sdmgaji()};
  try{P.gaji=await api('gajiList',{periode:P.per},true)}catch(e){$('#gjv').innerHTML=failBox(e.message);return}
  drawGaji();
};
function drawGaji(){
  const d=PS.sd.gaji,el=$('#gjv');if(!el)return;const R=d.rows,sum=k=>R.reduce((a,x)=>a+x[k],0),nd=R.filter(x=>x.status==='Draf').length,nf_=R.filter(x=>x.status==='Final').length,can=lv()>=3;
  el.innerHTML=`<div class="kpis k5">${kpiBox('Slip gaji',n0(R.length),'file',`${nd} draf · ${nf_} final · ${R.length-nd-nf_} dibayar`)+kpiBox('Total pendapatan',rp(sum('pendapatan')),'trend')+kpiBox('Total potongan',rp(sum('potongan')),'minus')+kpiBox('Gaji bersih',rp(sum('total')),'wallet')+kpiBox('Belum dihitung',n0(d.belum.length),'alert',d.belum.slice(0,3).map(x=>esc(x.nama)).join(', '))}</div>
   ${can?`<div class="card tb"><button class="btn" data-act="xgh">${ic('sync')}${R.length?'Hitung ulang draf':'Hitung gaji '+perNama(d.periode)}</button>${nd?`<button class="btn pri" data-act="xgf">${ic('check')}Finalkan ${nd} draf</button>`:''}${nf_?`<button class="btn pri" data-act="xgb">${ic('wallet')}Bayar ${nf_} slip</button>`:''}<span style="flex:1"></span>${R.length?`<button class="btn" data-act="xgpall">${ic('print')}Cetak semua slip</button>`:''}</div>`:''}
   <div class="card np"><div class="tw">${R.length?`<table class="tbl rs click"><thead><tr><th>Karyawan</th><th class="r">Hadir</th><th class="r">Pendapatan</th><th class="r">Potongan</th><th class="r">Gaji bersih</th><th>Status</th></tr></thead><tbody>${R.map(x=>`<tr data-act="xgd" data-id="${x.id}"><td class="nl"><b>${esc(x.kary.nama)}</b><div class="muted sm">${esc(x.kary.posisi||'')} · ${esc(x.kary.tipe)}</div></td>
    <td class="r" data-l="Hadir">${x.rekap.hadir}/${x.rekap.jadwal}${x.rekap.alpa?`<div class="err-t sm">${x.rekap.alpa} alpa</div>`:''}</td><td class="r" data-l="Pendapatan">${rp(x.pendapatan)}</td><td class="r err-t" data-l="Potongan">${x.potongan?'−'+rp(x.potongan):''}</td><td class="r b" data-l="Bersih">${rp(x.total)}</td><td data-l="Status">${B(GST[x.status],x.status)}${x.dibayar?`<div class="muted sm">${fd(x.dibayar)}</div>`:''}</td></tr>`).join('')}</tbody>
    <tfoot><tr><td>Total</td><td></td><td class="r">${rp(sum('pendapatan'))}</td><td class="r">−${rp(sum('potongan'))}</td><td class="r">${rp(sum('total'))}</td><td></td></tr></tfoot></table>`:emptyBox(`Belum ada perhitungan gaji ${perNama(d.periode)}.${can?' Klik "Hitung gaji".':''}`,'wallet')}</div></div>`;
}
ACT.xgh=async b=>{try{PS.sd.gaji=await call('gajiHitung',{periode:PS.sd.per},b);toast('Gaji dihitung dari data absensi terbaru','ok');drawGaji()}catch(e){}};
ACT.xgf=async b=>{if(!await ask(`Finalkan semua slip draf ${perNama(PS.sd.per)}? Jurnal beban gaji & hutang gaji dibuat, cicilan kasbon dipotong.`,'Finalkan'))return;try{PS.sd.gaji=await call('gajiFinal',{periode:PS.sd.per},b);toast('Gaji difinalkan','ok');drawGaji()}catch(e){}};
ACT.xgb=async b=>{const M=await sdMeta(),R=PS.sd.gaji.rows.filter(x=>x.status==='Final');
  const m=modal({title:'Bayar gaji '+perNama(PS.sd.per),body:`<div class="mres" style="max-height:240px">${R.map(x=>`<label class="li"><input type="checkbox" value="${x.id}" checked style="width:18px;height:18px"><div class="g"><b>${esc(x.kary.nama)}</b><span class="muted sm">${esc(x.kary.bank||'')} ${esc(x.kary.rekening||'')}</span></div><b>${rp(x.total)}</b></label>`).join('')}</div>
    <div class="grid2">${field('Tanggal bayar',`<input type="date" class="in" id="bt" value="${ymd(today())}">`)}${field('Dibayar dari',`<select class="in" id="ba">${akunOpts(M.akunKas,'1102')}</select>`)}</div>`,foot:`<button class="btn pri" id="bgo">${ic('wallet')}Catat pembayaran</button>`});
  $('#bgo',m).onclick=async ev=>{const ids=$$('input[type=checkbox]:checked',m).map(x=>x.value);try{PS.sd.gaji=await call('gajiBayar',{ids,tanggal:$('#bt',m).value,akunKas:$('#ba',m).value},ev.currentTarget);m.remove();toast(ids.length+' slip dibayar','ok');drawGaji()}catch(e){}}};
ACT.xgd=b=>{const x=PS.sd.gaji.rows.find(r=>r.id===b.dataset.id);if(!x)return;const can=lv()>=3,dr=x.status==='Draf';
  const m=modal({title:'Slip gaji · '+esc(x.kary.nama),cls:'lg',body:slipBody(x)+(can&&dr?`<h4 class="sub">Penyesuaian manual</h4><div class="grid2">${field('Bonus / insentif (Rp)',`<input class="in" id="gb" data-num="int" inputmode="numeric" value="${x.man.bonus?numStr(x.man.bonus):''}">`)}${field('Keterangan bonus',`<input class="in" id="gbk" value="${esc(x.man.bonusKet||'')}" placeholder="mis. Bonus target">`)}</div>
    <div class="grid2">${field('Potongan lain (Rp)',`<input class="in" id="gp" data-num="int" inputmode="numeric" value="${x.man.potLain?numStr(x.man.potLain):''}">`)}${field('Keterangan potongan',`<input class="in" id="gpk" value="${esc(x.man.potLainKet||'')}" placeholder="mis. BPJS, kerusakan barang">`)}</div>`:''),
    foot:`${can&&x.status!=='Draf'?`<button class="btn err" id="gbat">${x.status==='Dibayar'?'Batalkan pembayaran':'Batalkan final'}</button>`:''}${can&&dr?`<button class="btn err" id="gbat">Hapus draf</button>`:''}<span style="flex:1"></span><button class="btn" id="gprn">${ic('print')}Cetak slip</button>${can&&dr?`<button class="btn pri" id="gsv">${ic('check')}Simpan</button>`:''}`});
  $('#gprn',m).onclick=()=>printSlip([x],PS.sd.gaji.toko);
  if($('#gsv',m))$('#gsv',m).onclick=async ev=>{try{PS.sd.gaji=await call('gajiSave',{id:x.id,bonus:pn($('#gb',m).value),bonusKet:$('#gbk',m).value,potLain:pn($('#gp',m).value),potLainKet:$('#gpk',m).value},ev.currentTarget);m.remove();toast('Slip diperbarui','ok');drawGaji()}catch(e){}};
  if($('#gbat',m))$('#gbat',m).onclick=async ev=>{if(!await ask('Lanjutkan pembatalan? Jurnal terkait ikut dihapus.','Ya, batalkan',1))return;try{PS.sd.gaji=await call('gajiBatal',{id:x.id},ev.currentTarget);m.remove();toast('Dibatalkan','ok');drawGaji()}catch(e){}};
};
ACT.xgpall=()=>printSlip(PS.sd.gaji.rows,PS.sd.gaji.toko);
function slipBody(x){
  const r=x.rekap,row=(a,b,c='')=>`<div class="sr ${c}"><span>${a}</span><span>${b}</span></div>`;
  return `<div class="slp"><div class="slh"><div><b>${esc(x.kary.nama)}</b><div class="muted sm">${esc(x.kary.posisi||'')} · ${esc(x.kary.tipe)}</div></div><div class="r"><b>${perNama(x.periode)}</b><div>${B(GST[x.status],x.status)}</div></div></div>
   <div class="slr">${[['Hari kerja',r.jadwal],['Hadir',r.hadir],['Terlambat',r.telat],['Izin',r.izin],['Sakit',r.sakit],['Cuti',r.cuti],['Alpa',r.alpa],['Lembur',nf(r.lembur/60)+' j']].map(z=>`<div><small>${z[0]}</small><b>${z[1]}</b></div>`).join('')}</div>
   <div class="grid2"><div><h4 class="sub">Pendapatan</h4>${x.items.map(i=>row(esc(i.n),rp(i.v))).join('')}${row('<b>Total pendapatan</b>','<b>'+rp(x.pendapatan)+'</b>','stt')}</div>
   <div><h4 class="sub">Potongan</h4>${x.pot.length?x.pot.map(i=>row(esc(i.n),'−'+rp(i.v))).join(''):'<p class="muted sm">Tidak ada potongan</p>'}${row('<b>Total potongan</b>','<b>−'+rp(x.potongan)+'</b>','stt')}</div></div>
   <div class="sltot"><span>Gaji bersih diterima</span><b>${rp(x.total)}</b></div>${x.kpi?`<p class="muted sm">KPI ${esc(x.kpi.tpl)}: nilai ${String(x.kpi.skor).replace('.',',')} · grade ${x.kpi.grade}</p>`:''}</div>`;
}
function printSlip(list,toko){
  const doc=list.map(x=>{const r=x.rekap,tr=(a,b)=>`<tr><td>${a}</td><td class="r">${b}</td></tr>`;
    return `<section class="slip"><header><div><h1>${esc(toko.nama||'')}</h1><div class="m">${esc(toko.alamat||'')}${toko.telepon?' · '+esc(toko.telepon):''}</div></div><div class="r"><div class="t">SLIP GAJI</div><div>${perNama(x.periode)}</div></div></header>
      <table class="id"><tr><td>Nama</td><td><b>${esc(x.kary.nama)}</b></td><td>Kode</td><td>${esc(x.kary.kode||'')}</td></tr><tr><td>Posisi</td><td>${esc(x.kary.posisi||'-')}</td><td>Rekening</td><td>${esc((x.kary.bank||'')+' '+(x.kary.rekening||''))}</td></tr>
      <tr><td>Kehadiran</td><td colspan="3">Hadir ${r.hadir} dari ${r.jadwal} hari · terlambat ${r.telat}× · izin ${r.izin} · sakit ${r.sakit} · cuti ${r.cuti} · alpa ${r.alpa} · lembur ${nf(r.lembur/60)} jam</td></tr></table>
      <div class="cols"><table><thead><tr><th>Pendapatan</th><th class="r">Rp</th></tr></thead><tbody>${x.items.map(i=>tr(esc(i.n),n0(i.v))).join('')}</tbody><tfoot>${tr('Total pendapatan',n0(x.pendapatan))}</tfoot></table>
      <table><thead><tr><th>Potongan</th><th class="r">Rp</th></tr></thead><tbody>${x.pot.map(i=>tr(esc(i.n),n0(i.v))).join('')||tr('-','0')}</tbody><tfoot>${tr('Total potongan',n0(x.potongan))}</tfoot></table></div>
      <div class="net"><span>GAJI BERSIH DITERIMA</span><b>Rp ${n0(x.total)}</b></div><div class="sg"><div>Diterima oleh,<i></i>${esc(x.kary.nama)}</div><div>Disetujui oleh,<i></i>&nbsp;</div></div></section>`}).join('');
  printHtml(`<!doctype html><html><head><meta charset="utf-8"><title>Slip gaji</title><style>@page{size:A5 landscape;margin:9mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:10.5px;color:#14212B;margin:0}
   .slip{page-break-after:always}.slip:last-child{page-break-after:auto}header{display:flex;justify-content:space-between;border-bottom:2px solid #10A391;padding-bottom:6px;margin-bottom:8px}h1{font-size:15px;margin:0}.m{color:#5B6870}.t{font-weight:800;font-size:14px;color:#10A391;letter-spacing:.1em}.r{text-align:right}
   table{width:100%;border-collapse:collapse}.id td{padding:2px 4px}.id td:nth-child(odd){color:#5B6870;width:70px}.cols{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:8px}.cols th{text-align:left;background:#E1F4F1;padding:4px}.cols td{padding:3px 4px;border-bottom:1px solid #eee}.cols tfoot td{font-weight:700;border-top:1px solid #10A391}
   .net{display:flex;justify-content:space-between;align-items:center;background:#14212B;color:#fff;padding:8px 12px;border-radius:6px;margin-top:10px;font-weight:700}.net b{font-size:16px}.sg{display:flex;justify-content:space-between;margin-top:14px}.sg div{width:40%;text-align:center}.sg i{display:block;height:34px}</style></head><body>${doc}</body></html>`);
}
PAGES.sdmkasbon=async function(){
  $('#view').innerHTML=head('Kasbon karyawan','Pinjaman karyawan dicatat sebagai piutang dan dipotong otomatis dari gaji sesuai cicilan.',lv()>=3?`<button class="btn pri" data-act="xkb">${ic('plus')}Catat kasbon</button>`:'')+`<div class="card np"><div class="tw" id="kbv"><div class="skel" style="margin:14px"></div></div></div>`;
  let d;try{d=await api('kasbonList',{},true)}catch(e){$('#kbv').innerHTML=failBox(e.message);return}
  $('#kbv').innerHTML=d.length?`<table class="tbl"><thead><tr><th>Tanggal</th><th>Karyawan</th><th>Keterangan</th><th class="r">Jumlah</th><th class="r">Cicilan / gaji</th><th class="r">Sisa</th></tr></thead><tbody>${d.map(x=>`<tr><td>${fd(x.tanggal)}</td><td><b>${esc(x.nama)}</b></td><td class="sm">${esc(x.ket)}</td><td class="r">${rp(x.jumlah)}</td><td class="r">${rp(x.cicilan)}</td><td class="r b ${x.sisa?'warn-t':'ok-t'}">${x.sisa?rp(x.sisa):'Lunas'}</td></tr>`).join('')}</tbody></table>`:emptyBox('Belum ada kasbon','book');
};
ACT.xkb=async()=>{const M=await sdMeta();let K;try{K=await api('kryList',{},true)}catch(e){return}
  const m=modal({title:'Catat kasbon',body:field('Karyawan',`<select class="in" id="kbk">${K.filter(k=>k.status==='Aktif').map(k=>`<option value="${k.id}">${esc(k.nama)}</option>`).join('')}</select>`)+`<div class="grid2">${field('Jumlah (Rp)',`<input class="in" id="kbj" data-num="int" inputmode="numeric">`)}${field('Cicilan per gajian (Rp)',`<input class="in" id="kbc" data-num="int" inputmode="numeric" placeholder="kosong = lunas sekali potong">`)}</div><div class="grid2">${field('Tanggal',`<input type="date" class="in" id="kbt" value="${ymd(today())}">`)}${field('Diambil dari',`<select class="in" id="kba">${akunOpts(M.akunKas,'1101')}</select>`)}</div>`+field('Keterangan',`<input class="in" id="kbn">`),foot:`<button class="btn pri" id="kbgo">Simpan</button>`});
  $('#kbgo',m).onclick=async ev=>{try{await call('kasbonSave',{karyawan:$('#kbk',m).value,jumlah:pn($('#kbj',m).value),cicilan:pn($('#kbc',m).value)||pn($('#kbj',m).value),tanggal:$('#kbt',m).value,akunKas:$('#kba',m).value,ket:$('#kbn',m).value},ev.currentTarget);m.remove();toast('Kasbon dicatat','ok');PAGES.sdmkasbon()}catch(e){}}};
PAGES.sdmset=async function(){
  $('#view').innerHTML=head('Aturan absen & akses','Titik absen per lokasi, aturan gaji, lembur, karyawan pengganti, dan batas jam akses aplikasi.')+`<div id="stv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  let M;try{M=await sdMeta(true)}catch(e){$('#stv').innerHTML=failBox(e.message);return}const st=M.set;
  $('#stv').innerHTML=`<div class="prg" style="margin-bottom:14px">${M.lokasi.map(l=>`<div class="card prc"><div class="prt"><span class="prk">${ic('pin')}</span><div class="g"><b>${esc(l.nama)}</b><span class="muted sm">${l.lat!=null?`${(+l.lat).toFixed(5)}, ${(+l.lng).toFixed(5)} · radius ${l.radius} m`:'Titik absen belum diatur'}</span></div></div>
     <div class="grid2" style="margin-top:12px">${field('Lintang (lat)',`<input class="in mono" data-lat="${l.id}" value="${l.lat!=null?l.lat:''}" placeholder="-6.2000">`)}${field('Bujur (lng)',`<input class="in mono" data-lng="${l.id}" value="${l.lng!=null?l.lng:''}" placeholder="106.8166">`)}</div>
     <div class="grid2">${field('Radius (meter)',`<input class="in" data-rad="${l.id}" inputmode="numeric" value="${l.radius}">`)}<div class="fg" style="align-self:end;display:flex;gap:6px"><button class="btn sm" data-act="xgps" data-id="${l.id}">${ic('pin')}Pakai lokasi saya</button><button class="btn sm pri" data-act="xgpsv" data-id="${l.id}">Simpan</button></div></div>
     ${l.lat!=null?`<a class="sm" target="_blank" rel="noopener" href="https://www.google.com/maps?q=${l.lat},${l.lng}">Lihat di Google Maps ↗</a>`:''}</div>`).join('')}</div>
   <div class="card pad"><h3 style="margin:0 0 12px">Aturan penggajian & absensi</h3><div class="grid2">${field('Hari kerja per bulan (untuk potongan alpa/izin)',`<input class="in" id="shk" inputmode="numeric" value="${st.hariKerja}">`)}${field('Absensi mulai dihitung sejak',`<input type="date" class="in" id="smu" value="${st.mulai}">`)}</div>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="sip" ${st.izinPotong?'checked':''} style="width:18px;height:18px"><div class="g"><b>Potong gaji untuk izin</b><span class="muted sm">Karyawan bulanan yang izin dipotong seperti alpa. Sakit & cuti tidak dipotong.</span></div></label>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="stg" ${st.tanpaGps?'checked':''} style="width:18px;height:18px"><div class="g"><b>Izinkan absen tanpa cek jarak</b><span class="muted sm">Hanya untuk sementara (mis. GPS bermasalah). Lokasi tetap dicatat bila tersedia.</span></div></label>
    <h3 style="margin:18px 0 6px">Lembur & karyawan pengganti</h3>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="sla" ${st.lemburAktual?'checked':''} style="width:18px;height:18px"><div class="g"><b>Lembur dibayar sesuai jam yang benar-benar dijalani</b><span class="muted sm">Dihitung dari jam lembur yang disetujui dan jam absen pulang (diambil yang lebih pendek). Matikan bila lembur dibayar penuh sesuai pengajuan.</span></div></label>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="sgb" ${st.gantiBayar?'checked':''} style="width:18px;height:18px"><div class="g"><b>Bayar karyawan bulanan yang masuk menggantikan rekan di hari liburnya</b><span class="muted sm">Ditambah upah 1 hari (gaji pokok ÷ hari kerja per bulan) per hari pengganti. Karyawan harian otomatis dibayar karena terhitung hadir.</span></div></label>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="snw" ${st.notifWa?'checked':''} style="width:18px;height:18px"><div class="g"><b>Kirim notifikasi WhatsApp pengajuan</b><span class="muted sm">Ke atasan saat ada pengajuan baru, ke karyawan saat disetujui/ditolak, dan ke karyawan pengganti. Memakai gateway di Pengaturan → Laporan WhatsApp dan No. HP karyawan.</span></div></label>
    <h3 style="margin:18px 0 6px">Akses dari HP</h3>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="shp" ${st.hpAbsen?'checked':''} style="width:18px;height:18px"><div class="g"><b>Di HP, karyawan hanya bisa membuka Absen saya</b><span class="muted sm">Kasir, gudang, akuntansi, dan menu lain hanya bisa dibuka dari komputer atau tablet. Aplikasi Dhyra yang dipasang di HP karyawan langsung membuka halaman absen. Tablet tidak terpengaruh.</span></div></label>
    <div class="fg"><label class="lb">Jabatan yang tetap boleh membuka semua menu di HP (Administrator selalu boleh)</label><div style="display:flex;gap:14px;flex-wrap:wrap" id="shb">${M.jabatan.filter(j=>!j.admin).map(j=>`<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" value="${j.id}" ${st.hpBebas.includes(j.id)?'checked':''}>${esc(j.nama)}</label>`).join('')}</div><small class="muted">Centang <b>Kasir</b> bila toko Anda memakai HP sebagai mesin kasir.</small></div>
    <h3 style="margin:18px 0 6px">Akses aplikasi sesuai jam kerja</h3>
    <p class="muted sm" style="margin:0 0 8px">Mencegah kasir, gudang, atau akuntansi dibuka di luar jam kerja. Karyawan tetap bisa login ke <b>Absen saya</b> untuk absen dan mengajukan lembur / izin akses. Lembur dan izin akses yang disetujui atasan otomatis membuka akses pada jam tersebut.</p>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="saj" ${st.aksesJam?'checked':''} style="width:18px;height:18px"><div class="g"><b>Batasi akses sesuai jadwal shift</b><span class="muted sm">Berlaku untuk akun yang terhubung ke data karyawan.</span></div></label>
    <div class="grid2">${field('Boleh dibuka sebelum shift mulai (menit)',`<input class="in" id="sab" inputmode="numeric" value="${st.aksesSebelum}">`)}${field('Masih boleh dipakai setelah shift selesai (menit)',`<input class="in" id="sas" inputmode="numeric" value="${st.aksesSesudah}">`)}</div>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="sam" ${st.aksesAbsen?'checked':''} style="width:18px;height:18px"><div class="g"><b>Wajib absen masuk dulu sebelum memakai aplikasi</b><span class="muted sm">Kasir baru bisa dibuka setelah karyawan absen masuk (selfie + lokasi).</span></div></label>
    <div class="fg"><label class="lb">Jabatan yang bebas dari batas jam (Administrator selalu bebas)</label><div style="display:flex;gap:14px;flex-wrap:wrap" id="sbj">${M.jabatan.filter(j=>!j.admin).map(j=>`<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" value="${j.id}" ${st.aksesBebas.includes(j.id)?'checked':''}>${esc(j.nama)}</label>`).join('')}</div></div>
    <button class="btn pri" data-act="xsdset" style="margin-top:8px">${ic('check')}Simpan aturan</button></div>
   <div class="card pad" style="margin-top:14px"><h3 style="margin:0 0 4px">Percobaan akses di luar jam kerja</h3><p class="muted sm" style="margin:0 0 10px">Termasuk penjualan kasir offline yang terjadi di luar jam kerja (transaksinya tetap tersimpan).</p><div id="aklog"><div class="skel"></div></div></div>`;
  try{const L=await api('aksesLog',{},true);$('#aklog').innerHTML=(L.tanpaKaryawan.length&&st.aksesJam?`<div class="hint" style="margin:0 0 10px">${L.tanpaKaryawan.length} akun tidak terhubung ke data karyawan sehingga <b>tidak dibatasi jam kerja</b>: ${L.tanpaKaryawan.map(u=>esc(u.nama)+' (@'+esc(u.username)+')').join(', ')}. Hubungkan di menu Karyawan.</div>`:'')+(L.rows.length?`<div class="tw" style="max-height:320px"><table class="tbl"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Membuka</th><th>Keterangan</th></tr></thead><tbody>${L.rows.map(r=>`<tr><td class="sm" style="white-space:nowrap">${fd(r.waktu)} ${ft(r.waktu)}</td><td><b>${esc(r.nama)}</b><div class="muted sm">@${esc(r.username)}</div></td><td class="sm">${esc(r.konteks)}</td><td class="sm">${esc(r.ket)}</td></tr>`).join('')}</tbody></table></div>`:emptyBox('Belum ada percobaan akses di luar jam kerja','check'))}catch(e){$('#aklog').innerHTML=failBox(e.message)}
};
ACT.xgps=b=>{if(!navigator.geolocation)return toast('Browser tidak mendukung GPS','err');toast('Mengambil lokasi…');navigator.geolocation.getCurrentPosition(p=>{$(`[data-lat="${b.dataset.id}"]`).value=p.coords.latitude.toFixed(7);$(`[data-lng="${b.dataset.id}"]`).value=p.coords.longitude.toFixed(7);toast(`Lokasi terbaca (akurasi ±${Math.round(p.coords.accuracy)} m). Klik Simpan.`,'ok')},e=>toast(location.protocol!=='https:'&&location.hostname!=='localhost'?'GPS hanya bisa dipakai di alamat HTTPS':'Gagal membaca lokasi: '+e.message,'err'),{enableHighAccuracy:true,timeout:15000})};
ACT.xgpsv=async b=>{const id=b.dataset.id;try{SDM=await call('gpsSave',{lokasi:id,lat:$(`[data-lat="${id}"]`).value.trim(),lng:$(`[data-lng="${id}"]`).value.trim(),radius:pn($(`[data-rad="${id}"]`).value)},b);toast('Titik absen disimpan','ok');PAGES.sdmset()}catch(e){}};
ACT.xsdset=async b=>{try{SDM=await call('sdmSet',{hariKerja:pn($('#shk').value),izinPotong:$('#sip').checked,mulai:$('#smu').value,lemburAktual:$('#sla').checked,gantiBayar:$('#sgb').checked,notifWa:$('#snw').checked,
    aksesJam:$('#saj').checked,aksesSebelum:pn($('#sab').value),aksesSesudah:pn($('#sas').value),aksesAbsen:$('#sam').checked,aksesBebas:$$('#sbj input:checked').map(x=>x.value),hpAbsen:$('#shp').checked,hpBebas:$$('#shb input:checked').map(x=>x.value)},b);await call('gpsSave',{lokasi:SDM.lokasi[0].id,lat:SDM.lokasi[0].lat,lng:SDM.lokasi[0].lng,radius:SDM.lokasi[0].radius,tanpaGps:$('#stg').checked});toast('Aturan disimpan','ok')}catch(e){}};

/* ======================================================================
 *  KPI KARYAWAN
 * ==================================================================== */
NAV.push({id:'kpihasil',t:'Hasil KPI',i:'trend',m:'kpi',lv:1},{id:'kpitpl',t:'Template & bonus',i:'sliders',m:'kpi',lv:1});
MAINNAV.kpi=['kpihasil','kpitpl'];
const GRC={A:'ok',B:'pri',C:'warn',D:'err'};
PS.kp={per:perNow(),res:null};
PAGES.kpihasil=async function(){
  const P=PS.kp;
  $('#view').innerHTML=head('KPI karyawan','Nilai otomatis dari kehadiran, ketepatan waktu, penjualan, dan member baru, ditambah penilaian atasan. Skor ≥95 = A, ≥80 = B, ≥65 = C.',perPicker('kpp',P.per)+(lv()>=2?`<button class="btn" data-act="xkpimp">${ic('inb')}Import nilai Excel</button>`:''))+`<div id="kpv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  PERCB.kpp=p=>{P.per=p;PAGES.kpihasil()};
  try{P.res=await api('kpiHasil',{periode:P.per},true)}catch(e){$('#kpv').innerHTML=failBox(e.message);return}
  const R=P.res.rows,ok=R.filter(r=>r.kpi),avg=ok.length?ok.reduce((a,r)=>a+r.kpi.skor,0)/ok.length:0,cnt=g=>ok.filter(r=>r.kpi.grade===g).length;
  $('#kpv').innerHTML=`<div class="kpis k5">${kpiBox('Rata-rata skor',String(Math.round(avg*10)/10).replace('.',','),'trend',ok.length+' karyawan dinilai')+['A','B','C','D'].map(g=>kpiBox('Grade '+g,n0(cnt(g)),g==='A'?'check':g==='D'?'alert':'users',P.res.bonus[g]?'bonus '+rp(P.res.bonus[g]):'')).join('')}</div>
   <div class="kpl">${R.map((r,i)=>{const k=r.kpi;return `<button class="card kpc" data-act="xkpd" data-id="${r.id}"><span class="kpn">${k?i+1:'–'}</span><div class="g"><b>${esc(r.nama)}</b><span class="muted sm">${esc(r.posisi||'')}${k?' · '+esc(k.tpl):''}</span>
     ${k?`<div class="prog" style="margin-top:8px"><span style="width:${Math.min(100,k.skor)}%;background:var(--${GRC[k.grade]==='pri'?'pri':GRC[k.grade]})"></span></div>`:'<div class="muted sm" style="margin-top:6px">Belum diberi template KPI</div>'}</div>
     ${k?`<div class="kps"><b>${String(k.skor).replace('.',',')}</b><span class="bdg ${GRC[k.grade]}">Grade ${k.grade}</span>${k.belumDinilai?`<small class="warn-t">${k.belumDinilai} belum dinilai</small>`:''}</div>`:''}</button>`}).join('')||emptyBox('Belum ada karyawan aktif','users')}</div>`;
};
ACT.xkpd=b=>{const r=PS.kp.res.rows.find(x=>x.id===b.dataset.id);if(!r||!r.kpi)return lv()>=3?go('kpitpl'):toast('Karyawan ini belum diberi template KPI','err');const k=r.kpi,S_=PS.kp.res.sumber,can=lv()>=2;
  const fmtV=(x,v)=>v==null?'<span class="warn-t">belum dinilai</span>':(x.sumber==='omzet'?rp(v):x.sumber==='hadir'||x.sumber==='tepat'?String(v).replace('.',',')+'%':nf(v));
  const m=modal({title:'KPI · '+esc(r.nama)+' · '+perNama(PS.kp.per),cls:'lg',body:`<div class="mbs"><div><small>Skor akhir</small><b>${String(k.skor).replace('.',',')}</b></div><div><small>Grade</small><b>${k.grade}</b></div><div><small>Hadir</small><b>${k.rekap.hadir}/${k.rekap.jadwalLewat}</b><small>${k.rekap.telat} kali terlambat</small></div><div><small>Template</small><b style="font-size:14px">${esc(k.tpl)}</b></div></div>
    <div class="tw"><table class="tbl"><thead><tr><th>Indikator</th><th class="r">Bobot</th><th class="r">Target</th><th class="r">Pencapaian</th><th style="width:150px">Skor</th></tr></thead><tbody>${k.indikator.map(x=>`<tr><td><b>${esc(x.nama)}</b><div class="muted sm">${esc((S_[x.sumber]||[''])[0])}${x.sumber==='manual'?' · diisi atasan':' · otomatis'}</div></td><td class="r">${nf(x.bobot)}</td><td class="r">${fmtV(x,x.target)}</td>
      <td class="r">${x.sumber==='manual'&&can?`<input class="in" data-kpm="${x.id}" inputmode="decimal" value="${x.nilai==null?'':numStr(x.nilai)}" style="width:110px;text-align:right">`:fmtV(x,x.nilai)}</td><td><div class="prog"><span style="width:${Math.min(100,x.skor/1.2)}%"></span></div><small class="muted">${String(x.skor).replace('.',',')}</small></td></tr>`).join('')}</tbody></table></div>
    <p class="muted sm">Skor indikator = pencapaian ÷ target × 100 (maksimal 120). Skor akhir = rata-rata berbobot.</p>`,foot:can&&k.indikator.some(x=>x.sumber==='manual')?`<button class="btn pri" id="kpsv">${ic('check')}Simpan penilaian</button>`:''});
  if($('#kpsv',m))$('#kpsv',m).onclick=async ev=>{try{PS.kp.res=await call('kpiNilai',{periode:PS.kp.per,karyawan:r.id,nilai:$$('[data-kpm]',m).map(i=>({id:i.dataset.kpm,nilai:i.value===''?null:pn(i.value)}))},ev.currentTarget);m.remove();toast('Penilaian disimpan','ok');PAGES.kpihasil()}catch(e){}};
};
PAGES.kpitpl=async function(){
  $('#view').innerHTML=head('Template KPI & bonus','Buat template per jabatan, pasang ke karyawan, dan tentukan bonus per grade yang otomatis masuk slip gaji.',lv()>=3?`<button class="btn" data-act="xkptimp">${ic('inb')}Import Excel</button><button class="btn pri" data-act="xkpt">${ic('plus')}Template baru</button>`:'')+`<div id="ktv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  let d,K;try{[d,K]=await Promise.all([api('kpiTpl',{},true),api('kryList',{},true)])}catch(e){$('#ktv').innerHTML=failBox(e.message);return}
  PS.kp.tpl=d;const can=lv()>=3;
  $('#ktv').innerHTML=`<div class="prg" style="margin-bottom:14px">${d.tpl.map(t=>`<div class="card prc"><div class="prt"><span class="prk">${ic('trend')}</span><div class="g"><b>${esc(t.nama)}</b><span class="muted sm">${t.indikator.length} indikator · dipakai ${t.dipakai} karyawan</span></div>${can?`<button class="ib" data-act="xkpt" data-id="${t.id}">${ic('edit')}</button>`:''}</div>
     <div style="margin-top:10px">${t.indikator.map(x=>`<div class="sr"><span>${esc(x.nama)}</span><span class="muted">${nf(x.bobot)}%</span></div>`).join('')}</div></div>`).join('')}</div>
   <div class="grid2" style="align-items:start"><div class="card np"><div class="srh"><b>Template per karyawan</b></div><div class="tw"><table class="tbl"><tbody>${K.filter(k=>k.status==='Aktif').map(k=>`<tr><td><b>${esc(k.nama)}</b><div class="muted sm">${esc(k.posisi||'')}</div></td><td style="width:55%"><select class="in" data-kpa="${k.id}" ${can?'':'disabled'}><option value="">— tanpa KPI —</option>${d.tpl.map(t=>`<option value="${t.id}" ${t.id===k.kpi?'selected':''}>${esc(t.nama)}</option>`).join('')}</select></td></tr>`).join('')}</tbody></table></div></div>
    <div class="card pad"><b>Bonus KPI per grade</b><p class="muted sm">Otomatis ditambahkan ke slip gaji bulan yang sama. Isi 0 bila tidak ada bonus.</p>${['A','B','C','D'].map(g=>`<div class="grid2" style="align-items:center;margin-bottom:8px"><span><span class="bdg ${GRC[g]}">Grade ${g}</span> <small class="muted">${{A:'skor ≥ 95',B:'80 – 94',C:'65 – 79',D:'< 65'}[g]}</small></span><input class="in" data-kb="${g}" data-num="int" inputmode="numeric" value="${numStr(d.bonus[g])}" ${can?'':'disabled'}></div>`).join('')}${can?`<button class="btn pri" data-act="xkbs">${ic('check')}Simpan bonus</button>`:''}</div></div>`;
  $$('[data-kpa]').forEach(s=>s.onchange=async()=>{try{await call('kpiAssign',{karyawan:s.dataset.kpa,kpi:s.value});toast('Template KPI diperbarui','ok')}catch(e){}});
};
ACT.xkptimp=()=>xlImport(Object.assign({},IMPORT_KPI_TPL,{done:()=>PAGES.kpitpl()}));
ACT.xkpimp=()=>{const P=PS.kp;const opts=[-1,0,1].map(n=>perShift(P.per,n)).concat([perNow()]).filter((v,i,a)=>a.indexOf(v)===i).sort();
  xlImport(Object.assign({},IMPORT_KPI_NILAI,{extra:`<select class="in" id="kpper" style="margin-bottom:8px">${opts.map(p=>`<option value="${p}" ${p===P.per?'selected':''}>${perNama(p)}</option>`).join('')}</select>`,
    tplFn:m=>kpiNilaiTemplate($('#kpper',m).value),done:r=>{if(r&&r.periode&&r.periode.length===1)P.per=r.periode[0];PAGES.kpihasil()}}))};
ACT.xkbs=async b=>{const o={};$$('[data-kb]').forEach(i=>o[i.dataset.kb]=pn(i.value));try{await call('kpiSet',o,b);toast('Bonus KPI disimpan','ok')}catch(e){}};
ACT.xkpt=b=>{const d=PS.kp.tpl,t=d.tpl.find(x=>x.id===b.dataset.id)||{nama:'',indikator:[{id:'',nama:'Kehadiran',sumber:'hadir',target:100,bobot:30},{id:'',nama:'Tepat waktu',sumber:'tepat',target:100,bobot:20},{id:'',nama:'',sumber:'manual',target:100,bobot:50}]};
  const rowH=x=>`<div class="kir"><input class="in" data-f="nama" value="${esc(x.nama)}" placeholder="Nama indikator"><select class="in" data-f="sumber">${Object.entries(d.sumber).map(([k,v])=>`<option value="${k}" ${x.sumber===k?'selected':''}>${v[0]}</option>`).join('')}</select><input class="in" data-f="target" inputmode="decimal" value="${numStr(x.target)}" placeholder="Target"><input class="in" data-f="bobot" inputmode="decimal" value="${numStr(x.bobot)}" placeholder="Bobot %"><input type="hidden" data-f="id" value="${esc(x.id)}"><button class="ib err" data-kx>${ic('x')}</button></div>`;
  const m=modal({title:t.id?'Ubah template KPI':'Template KPI baru',cls:'lg',body:field('Nama template',`<input class="in" id="ktn" value="${esc(t.nama)}" placeholder="mis. KPI Kasir">`)+`<div class="kir kih"><span>Indikator</span><span>Sumber data</span><span>Target</span><span>Bobot %</span><span></span></div><div id="kil">${t.indikator.map(rowH).join('')}</div>
    <button class="btn sm" id="kia">${ic('plus')}Tambah indikator</button><p class="muted sm" id="kib"></p><p class="muted sm">Target persen untuk Kehadiran / Tepat waktu (mis. 100), rupiah untuk Omzet, jumlah untuk Transaksi / Member baru, dan skala bebas (mis. 100) untuk penilaian manual.</p>`,
    foot:`${t.id?`<button class="btn err" id="ktd">${ic('trash')}Hapus</button>`:''}<span style="flex:1"></span><button class="btn pri" id="ktgo">${ic('check')}Simpan</button>`});
  const tot=()=>{const s=$$('[data-f=bobot]',m).reduce((a,i)=>a+pn(i.value),0);$('#kib',m).innerHTML=`Total bobot: <b class="${Math.abs(s-100)<.01?'ok-t':'warn-t'}">${nf(s)}%</b>${Math.abs(s-100)<.01?'':' (disarankan 100%)'}`};
  m.addEventListener('input',tot);tot();
  $('#kia',m).onclick=()=>{$('#kil',m).insertAdjacentHTML('beforeend',rowH({id:'',nama:'',sumber:'manual',target:100,bobot:10}));tot()};
  $('#kil',m).onclick=e=>{const x=e.target.closest('[data-kx]');if(x){x.closest('.kir').remove();tot()}};
  $('#ktgo',m).onclick=async ev=>{const ind=$$('#kil .kir',m).map(r=>{const g=f=>$(`[data-f=${f}]`,r).value;return{id:g('id'),nama:g('nama'),sumber:g('sumber'),target:pn(g('target')),bobot:pn(g('bobot'))}});
    try{await call('kpiTplSave',{id:t.id||'',nama:$('#ktn',m).value,indikator:ind},ev.currentTarget);m.remove();toast('Template disimpan','ok');PAGES.kpitpl()}catch(e){}};
  if($('#ktd',m))$('#ktd',m).onclick=async()=>{if(!await ask('Hapus template ini?','Hapus',1))return;try{await call('kpiTplDel',{id:t.id});m.remove();PAGES.kpitpl()}catch(e){}};
};

/* ======================================================================
 *  TOKO ONLINE
 * ==================================================================== */
NAV.push({id:'onpesan',t:'Pesanan online',i:'cart',m:'online',lv:1},{id:'onproduk',t:'Produk di toko',i:'box',m:'online',lv:1},{id:'onset',t:'Pengaturan toko',i:'sliders',m:'online',lv:1});
MAINNAV.online=['onpesan','onproduk','onset'];
const OSC={Baru:'warn',Diproses:'pri',Dikirim:'pri',Selesai:'ok',Batal:'err'};
PS.on={st:'aktif',q:'',rows:null,cfg:null};
const tokoUrl=()=>location.href.replace(/[^/]*$/,'')+'toko.html';
PAGES.onpesan=async function(){
  const P=PS.on;
  $('#view').innerHTML=head('Pesanan online','Pesanan dari halaman toko online. Konfirmasi ke pelanggan lewat WhatsApp, lalu tandai Selesai — stok & jurnal penjualan otomatis tercatat.',`<a class="btn" href="toko.html" target="_blank">${ic('ext')}Lihat toko</a>`)+
   `<div id="onk"></div><div class="card tb"><div class="chips">${[['aktif','Perlu diproses'],['Baru','Baru'],['Diproses','Diproses'],['Dikirim','Dikirim'],['Selesai','Selesai'],['Batal','Batal'],['','Semua']].map(x=>`<button class="chip ${P.st===x[0]?'on':''}" data-act="xonf" data-s="${x[0]}">${x[1]}</button>`).join('')}</div><div class="sb" style="flex:1;min-width:180px">${ic('search')}<input class="in" id="onq" placeholder="No. pesanan, nama, HP" value="${esc(P.q)}"></div></div>
   <div class="card np"><div class="tw" id="onv"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#onq').addEventListener('input',deb(e=>{P.q=e.target.value;loadOn()},300));loadOn();
};
async function loadOn(){
  const P=PS.on;let d;try{d=await api('onPesanan',{status:P.st,q:P.q},true)}catch(e){$('#onv').innerHTML=failBox(e.message);return}
  P.rows=d.rows;P.cfg=d.cfg;const s=d.stat;
  if($('#onk'))$('#onk').innerHTML=(d.cfg.aktif?'':`<a class="card alr warn" data-act="go" data-r="onset" href="#onset" style="margin-bottom:12px"><span class="ai">${ic('alert')}</span><div><b>Toko online belum dibuka</b><span class="muted sm">Aktifkan dan isi nomor WhatsApp di Pengaturan toko.</span></div></a>`)+`<div class="kpis">${kpiBox('Pesanan baru',n0(s.baru),'alert')+kpiBox('Sedang diproses',n0(s.proses),'sync')+kpiBox('Omzet online bulan ini',rp(s.bulanIni),'wallet')+kpiBox('Produk tampil',n0(s.produk),'box')}</div>`;
  $('#onv').innerHTML=d.rows.length?`<table class="tbl rs click"><thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Pengiriman</th><th class="r">Total</th><th>Status</th></tr></thead><tbody>${d.rows.map(o=>`<tr data-act="xond" data-no="${o.no}"><td class="nl"><b class="mono">${esc(o.no)}</b><div class="muted sm">${fd(o.tanggal)} ${ft(o.tanggal)} · ${o.items.length} produk</div></td>
    <td data-l="Pelanggan"><b>${esc(o.nama)}</b>${o.member?' '+B('pri','member'):''}<div class="muted sm">${esc(o.hp)}</div></td><td class="sm" data-l="Kirim">${esc(o.kirim)}<div class="muted">${esc(o.bayar)}${o.dibayar?' · '+B('ok','lunas'):''}</div></td><td class="r b" data-l="Total">${rp(o.total)}</td><td data-l="Status">${B(OSC[o.status],o.status)}</td></tr>`).join('')}</tbody></table>`:emptyBox(P.st==='aktif'?'Tidak ada pesanan yang perlu diproses':'Belum ada pesanan','cart');
}
ACT.xonf=b=>{PS.on.st=b.dataset.s;$$('[data-act=xonf]').forEach(x=>x.classList.toggle('on',x===b));loadOn()};
function onMsg(o,k){const c=PS.on.cfg||{},toko=S.set.namaToko,it=o.items.map(i=>`• ${i.nama} × ${nf(i.qty)}`).join('\n');
  if(k==='proses')return `Halo ${o.nama}, terima kasih sudah memesan di ${toko} 🙏\n\nPesanan *${o.no}* sudah kami terima:\n${it}\n\nOngkir (${o.kirim}): ${rp(o.ongkir)}\n*Total: ${rp(o.total)}*\n\n`+(o.bayar==='Transfer'?`Silakan transfer ke:\n${c.rekening||'-'}\nlalu kirim bukti transfer di chat ini ya.`:o.kirim==='Ambil di toko'?'Pesanan bisa diambil di toko, bayar saat pengambilan.':'Pembayaran tunai saat barang diterima (COD).');
  if(k==='kirim')return `Halo ${o.nama}, pesanan *${o.no}* dari ${toko} sedang dalam pengiriman 🚚. Terima kasih!`;
  if(k==='selesai')return `Terima kasih ${o.nama}! Pesanan *${o.no}* sudah selesai. Ditunggu belanja berikutnya di ${toko} 😊`;
  return `Mohon maaf ${o.nama}, pesanan *${o.no}* di ${toko} tidak dapat kami proses.`}
ACT.xond=b=>{const o=PS.on.rows.find(x=>x.no===b.dataset.no);if(!o)return;const can=lv()>=2,open=!['Selesai','Batal'].includes(o.status);
  const m=modal({title:'Pesanan '+esc(o.no),cls:'lg',body:`<div class="pohd"><div>${B(OSC[o.status],o.status)}<h2>${esc(o.nama)}</h2><div class="muted sm">${esc(o.hp)} · ${fd(o.tanggal)} ${ft(o.tanggal)}</div></div><div class="r sm"><b>${esc(o.kirim)}</b><div class="muted">${esc(o.bayar)}</div><div style="margin-top:4px">${o.dibayar?B('ok','Sudah dibayar'):B('warn','Belum dibayar')}</div></div></div>
    ${o.alamat?`<div class="hint" style="margin:0 0 10px">📍 ${esc(o.alamat)}</div>`:''}${o.catatan?`<p class="sm">📝 ${esc(o.catatan)}</p>`:''}
    <div class="tw"><table class="tbl"><thead><tr><th>Produk</th><th class="r">Qty</th><th class="r">Harga</th><th class="r">Subtotal</th></tr></thead><tbody>${o.items.map(i=>`<tr><td><b>${esc(i.nama)}</b><div class="muted sm mono">${esc(i.sku||'')}</div>${i.promo?`<span class="bdg ok">${esc(i.promo)}</span>`:''}</td><td class="r">${nf(i.qty)} ${esc(i.satuan)}</td><td class="r">${rp(i.harga)}</td><td class="r">${rp(i.net)}</td></tr>`).join('')}</tbody>
    <tfoot><tr><td colspan="3">Subtotal</td><td class="r">${rp(o.subtotal)}</td></tr>${o.diskon?`<tr><td colspan="3">Diskon / promo</td><td class="r">−${rp(o.diskon)}</td></tr>`:''}<tr><td colspan="3">Ongkir</td><td class="r">${rp(o.ongkir)}</td></tr><tr><td colspan="3"><b>Total</b></td><td class="r"><b>${rp(o.total)}</b></td></tr></tfoot></table></div>
    ${o.dibayar?`<p class="sm">💰 Pembayaran ${rp(o.total)} diterima ${fd(o.dibayar)} ${ft(o.dibayar)} — sudah tercatat di Akuntansi sebagai <b>uang muka pelanggan</b>${o.trx?'':', menjadi penjualan saat pesanan ditandai Selesai'}.</p>`:open?`<div class="hint" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:10px"><span style="flex:1;min-width:220px"><b>Belum dibayar.</b> Begitu uang ${o.bayar==='Transfer'?'transfer':''} ${esc(rp(o.total))} diterima, tandai di sini agar langsung tercatat di Akuntansi.</span>${can?'<button class="btn pri" data-x="bayar">💰 Tandai sudah dibayar</button>':'<span class="muted sm">(perlu hak akses Petugas)</span>'}</div>`:''}
    ${o.trx?`<p class="sm">Tercatat sebagai penjualan <b class="mono">${esc(o.trx)}</b> (jurnal <i>Penjualan Online</i> di Akuntansi).</p>`:''}<details style="margin-top:8px"><summary class="sm muted">Riwayat status</summary>${o.log.map(l=>`<div class="sm">${fd(l.t)} ${ft(l.t)} · <b>${esc(l.s)}</b> · ${esc(l.u)} ${esc(l.k||'')}</div>`).join('')}</details>`,
    foot:`<a class="btn" target="_blank" rel="noopener" href="${WA(o.hp,onMsg(o,o.status==='Baru'?'proses':o.status==='Diproses'?'kirim':'selesai'))}">${ic('ext')}WhatsApp pelanggan</a><span style="flex:1"></span>${can&&open?`<button class="btn err" data-x="batal">Batalkan</button>${o.status==='Baru'?'<button class="btn" data-x="proses">Proses</button>':''}${o.status!=='Dikirim'&&o.kirim!=='Ambil di toko'?'<button class="btn" data-x="kirim">Dikirim</button>':''}<button class="btn pri" data-x="selesai">${ic('check')}Selesai & catat penjualan</button>`:''}`});
  m.addEventListener('click',async e=>{const x=e.target.closest('[data-x]');if(!x)return;const k=x.dataset.x;
    if(k==='batal'&&!await ask('Batalkan pesanan ini?'+(o.dibayar?`<br><br>Pesanan sudah dibayar: pengembalian dana ${rp(o.total)} ke pelanggan akan dicatat di Akuntansi.`:''),'Batalkan',1))return;
    if(k==='bayar'&&!await ask(`Pembayaran ${rp(o.total)} (${esc(o.bayar)}) dari ${esc(o.nama)} sudah diterima?<br><span class="muted sm">Dicatat di Akuntansi: kas/bank bertambah sebagai uang muka pelanggan. Penjualan & stok tercatat saat pesanan ditandai Selesai.</span>`,'Ya, sudah dibayar'))return;
    if(k==='selesai'&&!await ask(`Tandai selesai? Penjualan ${rp(o.total-o.ongkir)} + ongkir ${rp(o.ongkir)} dicatat, stok lokasi toko online berkurang.`,'Selesai'))return;
    const run=async(extra={})=>{const r=await call('onStatus',Object.assign({no:o.no,aksi:k},extra),x);if(r.stok)applyStok(r.stok);m.remove();toast(k==='bayar'?'Pembayaran dicatat di Akuntansi':'Pesanan '+r.status.toLowerCase(),'ok');
      if(['proses','kirim','batal'].includes(k)){const w=window.open(WA(r.hp,onMsg(r,k)),'_blank');if(!w)toast('Buka WhatsApp dari tombol di detail pesanan')}loadOn()};
    try{await run()}catch(er){if(er.code==='PRICE'&&await ask(er.message+'<br><br>Pakai harga terbaru?','Pakai harga terbaru'))try{await run({terimaHargaBaru:1})}catch(e2){}}});
};
PAGES.onproduk=async function(){
  $('#view').innerHTML=head('Produk di toko online','Pilih produk yang tampil di toko online dan tambahkan deskripsi. Harga & promo mengikuti data kasir, stok dari lokasi toko online.',lv()>=2?`<button class="btn" data-act="xonps" data-v="1">Tampilkan terpilih</button><button class="btn" data-act="xonps" data-v="0">Sembunyikan terpilih</button>`:'')+
   `<div class="card tb"><div class="sb" style="flex:1">${ic('search')}<input class="in" id="opq" placeholder="Cari produk…"></div></div><div class="card np"><div class="tw" id="opv"><div class="skel" style="margin:14px"></div></div></div>`;
  let d;try{d=await api('onProduk',{},true)}catch(e){$('#opv').innerHTML=failBox(e.message);return}
  const can=lv()>=2,draw=q=>{const l=d.filter(p=>!q||(p.nama+' '+p.sku+' '+p.kategori).toLowerCase().includes(q.toLowerCase()));
    $('#opv').innerHTML=`<table class="tbl rs"><thead><tr>${can?'<th style="width:36px"><input type="checkbox" id="opa"></th>':''}<th>Produk</th><th>SKU</th><th class="r">Harga</th><th class="r">Stok online</th><th>Deskripsi</th><th>Tampil</th></tr></thead><tbody>${l.map(p=>`<tr>${can?`<td class="ck"><input type="checkbox" data-op="${p.id}"></td>`:''}<td class="nl"><div class="pcell">${thumb(p)}<div><b>${esc(p.nama)}</b><span class="muted sm">${esc(p.kategori||'')}</span></div></div></td><td class="mono sm" data-l="SKU">${esc(p.sku)}</td><td class="r" data-l="Harga">${rp(p.harga)}</td><td class="r" data-l="Stok">${nf(p.stok)} ${esc(p.satuan)}</td>
      <td class="sm" data-l="Deskripsi" style="max-width:260px">${esc((p.deskripsi||'').slice(0,80))}${can?` <button class="ib" data-act="xondesc" data-id="${p.id}" aria-label="Ubah deskripsi">${ic('edit')}</button>`:''}</td><td data-l="Tampil">${can?`<label class="sw"><input type="checkbox" data-ot="${p.id}" ${p.online?'checked':''}><span></span></label>`:p.online?'Ya':'Tidak'}</td></tr>`).join('')}</tbody></table>`;
    $$('[data-ot]').forEach(c=>c.onchange=async()=>{try{await call('onProdukSet',{ids:[c.dataset.ot],online:c.checked});d.find(x=>x.id===c.dataset.ot).online=c.checked}catch(e){c.checked=!c.checked}});
    if($('#opa'))$('#opa').onchange=e=>$$('[data-op]').forEach(c=>c.checked=e.target.checked)};
  PS.on.prod=d;draw('');$('#opq').addEventListener('input',deb(e=>draw(e.target.value),150));
};
ACT.xonps=async b=>{const ids=$$('[data-op]:checked').map(c=>c.dataset.op);if(!ids.length)return toast('Centang produk dulu','err');try{await call('onProdukSet',{ids,online:b.dataset.v==='1'},b);toast(ids.length+' produk diperbarui','ok');PAGES.onproduk()}catch(e){}};
ACT.xondesc=b=>{const p=PS.on.prod.find(x=>x.id===b.dataset.id);const m=modal({title:'Deskripsi · '+esc(p.nama),body:`<textarea class="in" id="odx" rows="6" maxlength="2000" placeholder="Ukuran, bahan, rasa, cara pakai…">${esc(p.deskripsi||'')}</textarea>`,foot:`<button class="btn pri" id="odgo">Simpan</button>`});
  $('#odgo',m).onclick=async ev=>{try{await call('onProdukSet',{id:p.id,deskripsi:$('#odx',m).value},ev.currentTarget);p.deskripsi=$('#odx',m).value;m.remove();toast('Deskripsi disimpan','ok');PAGES.onproduk()}catch(e){}}};
PAGES.onset=async function(){
  $('#view').innerHTML=head('Pengaturan toko online','Atur nomor WhatsApp penerima pesanan, ongkir, metode bayar, dan bagikan link toko ke pelanggan.')+`<div id="osv"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  let d;try{d=await api('onSetGet',{},true)}catch(e){$('#osv').innerHTML=failBox(e.message);return}
  const c=d.cfg,can=lv()>=3,url=tokoUrl(),dis=can?'':'disabled';
  $('#osv').innerHTML=`<div class="card pad osl"><div><b>Link toko Anda</b><div class="mono sm" style="word-break:break-all">${esc(url)}</div><div class="muted sm" style="margin-top:4px">${/localhost|127\.0\.0\.1|192\.168\./.test(url)?'⚠ Ini alamat lokal (XAMPP). Setelah dipasang di hosting, link berubah menjadi domain Anda.':'Bagikan ke pelanggan lewat WhatsApp, Instagram, atau cetak di struk.'}</div></div>
     <div class="acts"><button class="btn" data-act="xoncp">Salin link</button><a class="btn" target="_blank" rel="noopener" href="https://wa.me/?text=${encodeURIComponent('Belanja di '+d.toko+' sekarang bisa online! Pesan di sini: '+url)}">${ic('ext')}Bagikan WA</a><a class="btn pri" href="toko.html" target="_blank">Buka toko</a></div></div>
   <div class="card pad" style="margin-top:12px"><label class="li" style="padding:0 0 12px;border:0"><input type="checkbox" id="osa" ${c.aktif?'checked':''} ${dis} style="width:20px;height:20px"><div class="g"><b>Toko online buka</b><span class="muted sm">Bila dimatikan, pelanggan melihat pesan "toko sedang tutup".</span></div></label>
    <div class="grid2">${field('Nomor WhatsApp penerima pesanan *',`<input class="in" id="osw" value="${esc(c.wa)}" inputmode="tel" placeholder="08…" ${dis}>`)}${field('Ambil stok dari lokasi',`<select class="in" id="osl" ${dis}>${d.lokasi.map(l=>`<option value="${l.id}" ${l.id===c.lokasi?'selected':''}>${esc(l.nama)}</option>`).join('')}</select>`)}</div>
    ${field('Info toko (tampil di halaman toko)',`<input class="in" id="osi" value="${esc(c.info)}" placeholder="mis. Buka 08.00–21.00 · Gratis ongkir dalam 3 km" ${dis}>`)}
    <h4 class="sub">Pengiriman</h4><label class="li" style="padding:4px 0;border:0"><input type="checkbox" id="osam" ${c.ambil?'checked':''} ${dis} style="width:18px;height:18px"> Pelanggan boleh ambil di toko</label>
    <div id="oso">${c.ongkir.map(o=>`<div class="osr"><input class="in" data-on value="${esc(o.nama)}" placeholder="Nama layanan (mis. Kurir toko < 5 km)" ${dis}><input class="in" data-ob data-num="int" inputmode="numeric" value="${numStr(o.biaya)}" placeholder="Ongkir" ${dis}>${can?`<button class="ib err" data-osx>${ic('x')}</button>`:''}</div>`).join('')}</div>${can?`<button class="btn sm" id="osadd">${ic('plus')}Tambah pilihan ongkir</button>`:''}
    <h4 class="sub">Pembayaran</h4><label class="li" style="padding:4px 0;border:0"><input type="checkbox" id="osc" ${c.cod?'checked':''} ${dis} style="width:18px;height:18px"> Terima bayar di tempat (COD) / bayar saat ambil</label>
    ${field('Rekening untuk transfer',`<textarea class="in" id="osr" rows="3" placeholder="BCA 1234567890 a.n. Toko …&#10;QRIS: tersedia saat konfirmasi WA" ${dis}>${esc(c.rekening)}</textarea>`)}
    <div class="grid2">${field('Minimal belanja (Rp)',`<input class="in" id="osm" data-num="int" inputmode="numeric" value="${c.min?numStr(c.min):''}" placeholder="0" ${dis}>`)}<label class="li" style="padding:8px 0;border:0;align-self:end"><input type="checkbox" id="osh" ${c.tampilHabis?'checked':''} ${dis} style="width:18px;height:18px"> Tampilkan produk yang stoknya habis</label></div>
    ${can?`<button class="btn pri" data-act="xonsv" style="margin-top:6px">${ic('check')}Simpan pengaturan</button>`:''}</div>`;
  if($('#osadd'))$('#osadd').onclick=()=>$('#oso').insertAdjacentHTML('beforeend',`<div class="osr"><input class="in" data-on placeholder="Nama layanan"><input class="in" data-ob data-num="int" inputmode="numeric" placeholder="Ongkir"><button class="ib err" data-osx>${ic('x')}</button></div>`);
  $('#oso').onclick=e=>{const x=e.target.closest('[data-osx]');if(x)x.closest('.osr').remove()};
};
ACT.xoncp=()=>{navigator.clipboard&&navigator.clipboard.writeText(tokoUrl()).then(()=>toast('Link disalin','ok'),()=>toast(tokoUrl()))};
ACT.xonsv=async b=>{try{await call('onSet',{aktif:$('#osa').checked,wa:$('#osw').value,lokasi:$('#osl').value,info:$('#osi').value,ambil:$('#osam').checked,cod:$('#osc').checked,rekening:$('#osr').value,min:pn($('#osm').value),tampilHabis:$('#osh').checked,
  ongkir:$$('.osr').map(r=>({nama:$('[data-on]',r).value,biaya:pn($('[data-ob]',r).value)}))},b);toast('Pengaturan toko disimpan','ok');PAGES.onset()}catch(e){}};
})();
