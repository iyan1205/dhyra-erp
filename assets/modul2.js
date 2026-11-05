/* ============================================================
 *  Dhyra v4 — halaman modul Tahap 2
 *  Pembelian · Member & CRM · Promo · Dashboard Pemilik & Laporan
 *  Memakai fungsi bersama dari kasir.html (S, api, call, modal, ...).
 * ============================================================ */
(function(){
const WA=(hp,txt)=>{let n=String(hp||'').replace(/\D/g,'');if(n.startsWith('0'))n='62'+n.slice(1);return n?`https://wa.me/${n}?text=${encodeURIComponent(txt)}`:''};
const tgl=s=>s?fd(String(s)):'–';
const lv=()=>S.lvl||0;
const sBdg={Draf:'',Dipesan:'pri',Sebagian:'warn',Selesai:'ok',Batal:'err',Berjalan:'ok',Terjadwal:'pri','Di luar jam':'warn',Berakhir:'',Nonaktif:'err'};
const stB=s=>`<span class="bdg ${sBdg[s]||''}">${esc(s)}</span>`;
const today0=()=>ymd(today());
const field=(lbl,inner,cls='')=>`<div class="fg ${cls}"><label class="lb">${lbl}</label>${inner}</div>`;
/* pemilih produk dengan saran (dipakai PO, retur, promo) */
function prodPicker(box,onPick,opts={}){
  box.innerHTML=`<div class="ppk"><div class="sb">${ic('search')}<input class="in" placeholder="${opts.ph||'Cari nama / SKU / barcode, Enter untuk tambah'}" autocomplete="off"></div><div class="sugg hide"></div></div>`;
  const q=$('input',box),sg=$('.sugg',box);let list=[],ix=0;
  const draw=()=>{if(!list.length){sg.classList.add('hide');return}sg.innerHTML=list.map((p,i)=>`<button type="button" class="${i===ix?'on':''}" data-i="${i}"><span><b>${esc(p.nama)}</b><br><small>${esc(p.sku||'')}${p.barcode?' · '+esc(p.barcode):''}</small></span><small>${opts.info?opts.info(p):rp(p.hargaBeli)}</small></button>`).join('');sg.classList.remove('hide')};
  q.addEventListener('input',()=>{const t=q.value.trim();list=t?S.prods.filter(p=>match(p,t)).slice(0,8):[];ix=0;draw()});
  q.addEventListener('keydown',e=>{
    if(e.key==='ArrowDown'){e.preventDefault();ix=Math.min(ix+1,list.length-1);draw()}
    else if(e.key==='ArrowUp'){e.preventDefault();ix=Math.max(ix-1,0);draw()}
    else if(e.key==='Enter'){e.preventDefault();const t=q.value.trim().toLowerCase();const ex=S.prods.find(p=>(p.barcode&&p.barcode.toLowerCase()===t)||(p.sku&&p.sku.toLowerCase()===t));const p=ex||list[ix];if(p){onPick(p);q.value='';list=[];draw()}}
    else if(e.key==='Escape'){list=[];draw()}
  });
  sg.addEventListener('mousedown',e=>{const b=e.target.closest('[data-i]');if(b){e.preventDefault();onPick(list[+b.dataset.i]);q.value='';list=[];draw();q.focus()}});
  q.addEventListener('blur',()=>setTimeout(()=>{list=[];draw()},150));
  return q;
}

/* ======================================================================
 *  PEMBELIAN
 * ==================================================================== */
NAV.push({id:'po',t:'Pesanan pembelian',i:'inb',m:'pembelian',lv:1},{id:'saran',t:'Saran pembelian',i:'alert',m:'pembelian',lv:1},{id:'retur',t:'Retur ke pemasok',i:'swap',m:'pembelian',lv:1},{id:'pemasok',t:'Pemasok',i:'users',m:'pembelian',lv:1});
MAINNAV.pembelian=['po','saran','retur','pemasok'];
let META=null;
const meta=async(f)=>{if(!META||f)META=await api('pbMeta',{},true);return META};
PS.po={st:'Terbuka',q:'',page:1,size:20,rows:null};

PAGES.po=async function(){
  const P=PS.po;
  $('#view').innerHTML=head('Pesanan pembelian','Pesan barang ke pemasok, lalu catat penerimaannya di sini — stok, harga beli, dan hutang otomatis tercatat.',lv()>=2?`<button class="btn pri" data-act="ponew">${ic('plus')}Pesanan baru</button>`:'')+
   `<div class="card tb"><div class="chips">${['Terbuka','Draf','Dipesan','Sebagian','Selesai','Batal','Semua'].map(x=>`<button class="chip ${P.st===x?'on':''}" data-act="post" data-s="${x}">${x}</button>`).join('')}</div><div class="sb" style="flex:1;min-width:200px">${ic('search')}<input class="in" id="poq" placeholder="Cari nomor / pemasok…" value="${esc(P.q)}"></div></div>
   <div class="card np"><div class="tw" id="polist"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#poq').addEventListener('input',deb(e=>{P.q=e.target.value;P.page=1;drawPo()},150));
  try{P.rows=await api('poList',{status:P.st==='Semua'?'':P.st},true);drawPo()}catch(e){$('#polist').innerHTML=failBox(e.message)}
};
function drawPo(){
  const P=PS.po,el=$('#polist');if(!el||!P.rows)return;
  const q=P.q.toLowerCase(),l=P.rows.filter(r=>!q||(r.no+' '+r.pemasok+' '+r.catatan).toLowerCase().includes(q)),pg=l.slice((P.page-1)*P.size,P.page*P.size);
  el.innerHTML=(pg.length?`<table class="tbl rs click"><thead><tr><th>No. pesanan</th><th>Tanggal</th><th>Pemasok</th><th>Ke lokasi</th><th class="r">Total</th><th>Diterima</th><th>Status</th></tr></thead><tbody>${pg.map(r=>`<tr data-act="podet" data-no="${esc(r.no)}"><td class="nl"><b class="mono">${esc(r.no)}</b><span class="muted sm"> · ${r.n} produk</span></td><td data-l="Tanggal">${tgl(r.tanggal)}${r.perkiraan?`<div class="muted sm">tiba ${tgl(r.perkiraan)}</div>`:''}</td><td data-l="Pemasok">${esc(r.pemasok)}</td><td data-l="Lokasi">${esc(r.lokasiNama)}</td><td class="r b" data-l="Total">${rp(r.total)}</td><td data-l="Diterima"><div class="prog"><span style="width:${Math.min(100,r.progres)}%"></span></div><small class="muted">${r.progres}%</small></td><td data-l="Status">${stB(r.status)}</td></tr>`).join('')}</tbody></table>`:emptyBox(P.rows.length?'Tidak ada yang cocok':'Belum ada pesanan pembelian'+(P.st==='Terbuka'?' yang masih terbuka':'')+'.','inb'))+pager(l.length,P.page,P.size,'po');
}
DRAW.po=drawPo;
ACT.post=b=>{PS.po.st=b.dataset.s;PS.po.page=1;PAGES.po()};
ACT.ponew=()=>poForm();
ACT.podet=b=>poDetail(b.dataset.no);

async function poForm(po,pre){
  const mt=await meta(true);
  po=po||{};const lines=(po.items||pre&&pre.items||[]).map(i=>({pid:i.pid,qty:+i.qty||1,harga:+i.harga||0}));
  const m=modal({title:po.no?'Ubah pesanan '+po.no:'Pesanan pembelian baru',cls:'xl',sticky:1,body:`
   <div class="grid3">${field('Pemasok *',`<input class="in" id="po-sup" list="po-sups" value="${esc(po.pemasok||(pre&&pre.pemasok)||'')}" placeholder="Pilih atau ketik nama pemasok baru"><datalist id="po-sups">${mt.pemasok.map(k=>`<option value="${esc(k.nama)}">`).join('')}</datalist>`)}
   ${field('Dikirim ke lokasi',`<select class="in" id="po-lok">${lokOpts(po.lokasi||S.lokMasuk,false)}</select>`)}
   ${field('Tanggal pesan',`<input class="in" type="date" id="po-tgl" value="${po.tanggal||today0()}">`)}</div>
   <div class="grid3">${field('Perkiraan barang tiba',`<input class="in" type="date" id="po-per" value="${po.perkiraan||''}">`)}${field('Catatan',`<input class="in" id="po-cat" value="${esc(po.catatan||'')}" placeholder="mis. kirim pagi, termin 30 hari">`,'span2')}</div>
   <div id="po-pk"></div>
   <div class="card np" style="margin-top:10px"><div class="tw"><table class="tbl"><thead><tr><th>Produk</th><th class="r">Stok di lokasi</th><th class="r" style="width:120px">Jumlah</th><th class="r" style="width:150px">Harga beli</th><th class="r">Subtotal</th><th></th></tr></thead><tbody id="po-lines"></tbody></table></div></div>
   <div class="tot" style="margin-top:12px"><span>Total pesanan</span><b id="po-tot">${rp(0)}</b></div>`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn" id="po-draf">${ic('check')}Simpan draf</button>${lv()>=3?`<button class="btn pri" id="po-pesan">${ic('inb')}Simpan & tandai dipesan</button>`:''}`});
  const draw=()=>{
    const lok=$('#po-lok',m).value;let t=0;
    $('#po-lines',m).innerHTML=lines.length?lines.map((l,i)=>{const p=byId(l.pid)||{nama:'(produk dihapus)',satuan:'',lok:{}};t+=Math.round(l.qty*l.harga);return `<tr><td><b>${esc(p.nama)}</b><div class="muted sm mono">${esc(p.sku||'')}</div></td><td class="r">${nf(((p.lok||{})[lok]||{}).q||0)} ${esc(p.satuan)}</td><td class="r"><input class="in r" data-num="dec" inputmode="decimal" data-pl="${i}" data-f="qty" value="${numStr(l.qty)}"></td><td class="r"><input class="in r" data-num="int" inputmode="numeric" data-pl="${i}" data-f="harga" value="${numStr(l.harga)}"></td><td class="r b">${rp(Math.round(l.qty*l.harga))}</td><td><button class="ib err" data-rm="${i}" aria-label="Hapus">${ic('x')}</button></td></tr>`}).join(''):`<tr><td colspan="6">${emptyBox('Cari produk di atas untuk ditambahkan ke pesanan','box')}</td></tr>`;
    $('#po-tot',m).textContent=rp(t);
  };
  prodPicker($('#po-pk',m),p=>{const l=lines.find(x=>x.pid===p.id);if(l)l.qty=r3(l.qty+1);else lines.push({pid:p.id,qty:1,harga:p.hargaBeli||0});beep();draw()},{info:p=>`stok ${nf(p.stokTotal)} · ${rp(p.hargaBeli)}`});
  m.addEventListener('input',e=>{const t=e.target;if(t.dataset.pl!==undefined){lines[+t.dataset.pl][t.dataset.f]=pn(t.value);const tr=t.closest('tr');let tot=0;lines.forEach(l=>tot+=Math.round(l.qty*l.harga));$('#po-tot',m).textContent=rp(tot);const l=lines[+t.dataset.pl];tr.children[4].textContent=rp(Math.round(l.qty*l.harga))}});
  m.addEventListener('click',e=>{const b=e.target.closest('[data-rm]');if(b){lines.splice(+b.dataset.rm,1);draw()}});
  $('#po-lok',m).onchange=draw;draw();
  const save=async(pesan,btn)=>{
    const sup=$('#po-sup',m).value.trim(),k=mt.pemasok.find(x=>x.nama.toLowerCase()===sup.toLowerCase());
    if(!sup)return toast('Isi nama pemasok','err');if(!lines.length)return toast('Tambahkan minimal satu produk','err');
    try{const r=await call('poSave',{no:po.no||'',pemasok:sup,kontakId:k?k.id:'',lokasi:$('#po-lok',m).value,tanggal:$('#po-tgl',m).value,perkiraan:$('#po-per',m).value,catatan:$('#po-cat',m).value,pesan,items:lines},btn);
      m.remove();toast(`Pesanan ${r.no} disimpan`,'ok');if(S.route==='po')PAGES.po();poDetail(r.no,r)}catch(e){}
  };
  $('#po-draf',m).onclick=e=>save(false,e.currentTarget);
  const pp=$('#po-pesan',m);if(pp)pp.onclick=e=>save(true,e.currentTarget);
}
async function poDetail(no,data){
  let d=data;try{if(!d)d=await call('poGet',{no})}catch(e){return}
  const can2=lv()>=2,can3=lv()>=3,open=['Dipesan','Sebagian'].includes(d.status),got=d.items.some(i=>i.terima>0);
  const m=modal({title:`Pesanan ${esc(d.no)}`,cls:'lg',body:`
   <div class="pohd"><div>${stB(d.status)}<h2>${esc(d.pemasok)}</h2><div class="muted sm">${d.telepon?esc(d.telepon)+' · ':''}dikirim ke <b>${esc(d.lokasiNama)}</b></div></div>
    <div class="kv2"><span>Tanggal</span><b>${tgl(d.tanggal)}</b><span>Perkiraan tiba</span><b>${tgl(d.perkiraan)}</b><span>Dibuat</span><b>${esc(d.dibuat)}</b>${d.dipesan?`<span>Dipesan oleh</span><b>${esc(d.dipesan)}</b>`:''}</div></div>
   ${d.catatan?`<p class="muted sm" style="margin:6px 0 10px">${esc(d.catatan)}</p>`:''}
   <div class="card np"><div class="tw"><table class="tbl"><thead><tr><th>Produk</th><th class="r">Dipesan</th><th class="r">Diterima</th><th class="r">Sisa</th><th class="r">Harga</th><th class="r">Subtotal</th></tr></thead><tbody>${d.items.map(i=>`<tr><td><b>${esc(i.nama)}</b><div class="muted sm mono">${esc(i.sku)}</div></td><td class="r">${nf(i.qty)} ${esc(i.satuan)}</td><td class="r">${i.terima?nf(i.terima):'–'}</td><td class="r">${i.sisa?`<span class="bdg warn">${nf(i.sisa)}</span>`:'<span class="bdg ok">Lengkap</span>'}</td><td class="r">${rp(i.harga)}</td><td class="r b">${rp(Math.round(i.qty*i.harga))}</td></tr>`).join('')}</tbody><tfoot><tr><td colspan="5" class="r b">Total</td><td class="r b">${rp(d.total)}</td></tr></tfoot></table></div></div>
   ${d.terima.length?`<h4 class="sub">Penerimaan barang</h4>${d.terima.map(t=>`<div class="li"><span class="bdg ok">${ic('check')}</span><div class="g"><b class="mono">${esc(t.no)}</b><span class="muted sm">${tgl(t.tanggal)} · ${esc(t.user)} · ${t.bayar==='Hutang'?'secara hutang':'dibayar '+esc(t.bayar)}</span></div><div class="r"><b>${rp(t.total)}</b>${t.faktur?`<div class="sm">${esc(t.faktur.nomor)} · ${t.faktur.sisa>0?`<span class="bdg warn">sisa ${rp(t.faktur.sisa)}</span>`:'<span class="bdg ok">Lunas</span>'}</div>`:''}</div></div>`).join('')}`:''}
   ${d.retur.length?`<h4 class="sub">Retur</h4>${d.retur.map(t=>`<div class="li"><span class="bdg err">${ic('swap')}</span><div class="g"><b class="mono">${esc(t.no)}</b><span class="muted sm">${tgl(t.tanggal)} · ${esc(t.metode==='Tagihan'?'potong tagihan':'uang kembali '+t.metode)}</span></div><b>${rp(t.total)}</b></div>`).join('')}`:''}`,
   foot:`<button class="btn" data-x="print">${ic('print')}Cetak</button>${d.telepon&&d.status!=='Batal'?`<a class="btn" target="_blank" rel="noopener" href="${WA(d.telepon,poWaText(d))}">${ic('ext')}Kirim WhatsApp</a>`:''}<span style="flex:1"></span>
    ${can3&&!['Selesai','Batal'].includes(d.status)?`<button class="btn err" data-x="batal">${got?'Tutup sisa pesanan':'Batalkan'}</button>`:''}
    ${can3&&got?`<button class="btn" data-x="retur">${ic('swap')}Retur</button>`:''}
    ${((d.status==='Draf'&&can2)||(d.status==='Dipesan'&&can3))&&!got?`<button class="btn" data-x="edit">${ic('edit')}Ubah</button>`:''}
    ${d.status==='Draf'&&can3?`<button class="btn pri" data-x="pesan">${ic('inb')}Tandai dipesan</button>`:''}
    ${open&&can2?`<button class="btn pri" data-x="terima">${ic('check')}Terima barang</button>`:''}`});
  m.addEventListener('click',async e=>{const b=e.target.closest('[data-x]');if(!b)return;const x=b.dataset.x;
    if(x==='print')printHtml(poPrintHtml(d));
    if(x==='edit'){m.remove();poForm(d)}
    if(x==='terima'){m.remove();poTerima(d)}
    if(x==='retur'){m.remove();returForm({kontakId:d.kontakId,pemasok:d.pemasok,lokasi:d.lokasi,po:d.no,items:d.items.filter(i=>i.terima>0).map(i=>({pid:i.pid,qty:0,harga:i.harga}))})}
    if(x==='pesan'){try{const r=await call('poPesan',{no:d.no},b);m.remove();toast('Pesanan ditandai dipesan','ok');if(S.route==='po')PAGES.po();poDetail(r.no,r)}catch(er){}}
    if(x==='batal'){if(!await ask(got?'Sisa barang yang belum datang tidak ditunggu lagi dan pesanan ditutup. Lanjutkan?':`Batalkan pesanan <b>${esc(d.no)}</b>?`,got?'Tutup pesanan':'Batalkan',1))return;try{const r=await call('poBatal',{no:d.no});m.remove();toast('Pesanan diperbarui','ok');if(S.route==='po')PAGES.po();poDetail(r.no,r)}catch(er){}}
  });
}
function poWaText(d){return `Halo ${d.pemasok}, kami dari ${S.set.namaToko} ingin memesan:\n`+d.items.map((i,n)=>`${n+1}. ${i.nama} — ${nf(i.qty)} ${i.satuan}`).join('\n')+`\n\nNo. pesanan: ${d.no}\nDikirim ke: ${d.lokasiNama}${d.perkiraan?'\nHarap tiba: '+fd(d.perkiraan):''}\nTerima kasih.`}
function poPrintHtml(d){
  const s=S.set;
  return `<!doctype html><html><head><meta charset="utf-8"><title>${esc(d.no)}</title><style>@page{size:A4;margin:16mm}body{font:12px/1.45 Arial,sans-serif;color:#111}h1{font-size:20px;margin:0 0 2px;letter-spacing:.04em}table{width:100%;border-collapse:collapse;margin-top:14px}th,td{border-bottom:1px solid #ccc;padding:7px 6px;text-align:left}th{background:#eef5f4;font-size:11px;text-transform:uppercase}td.r,th.r{text-align:right}.top{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #0f766e;padding-bottom:10px}.box{margin-top:14px;display:flex;gap:30px}.box div{flex:1}.muted{color:#666}.sign{display:flex;gap:40px;margin-top:50px}.sign div{flex:1;text-align:center}.sign span{display:block;margin-top:60px;border-top:1px solid #333;padding-top:4px}</style></head><body>
  <div class="top"><div><b style="font-size:16px">${esc(s.namaToko)}</b><div class="muted">${esc(s.alamat)}<br>${esc(s.telepon)}</div></div><div style="text-align:right"><h1>PESANAN PEMBELIAN</h1><b>${esc(d.no)}</b><div class="muted">Tanggal ${fd(d.tanggal)}</div></div></div>
  <div class="box"><div><span class="muted">Kepada</span><br><b>${esc(d.pemasok)}</b><br>${esc(d.alamat||'')}${d.telepon?'<br>'+esc(d.telepon):''}</div><div><span class="muted">Kirim ke</span><br><b>${esc(d.lokasiNama)}</b>${d.perkiraan?`<br>Harap tiba: ${fd(d.perkiraan)}`:''}</div></div>
  <table><thead><tr><th>#</th><th>Produk</th><th>SKU</th><th class="r">Jumlah</th><th class="r">Harga</th><th class="r">Subtotal</th></tr></thead><tbody>${d.items.map((i,n)=>`<tr><td>${n+1}</td><td>${esc(i.nama)}</td><td>${esc(i.sku)}</td><td class="r">${nf(i.qty)} ${esc(i.satuan)}</td><td class="r">${n0(i.harga)}</td><td class="r">${n0(Math.round(i.qty*i.harga))}</td></tr>`).join('')}
  <tr><td colspan="5" class="r"><b>TOTAL</b></td><td class="r"><b>Rp ${n0(d.total)}</b></td></tr></tbody></table>
  ${d.catatan?`<p><b>Catatan:</b> ${esc(d.catatan)}</p>`:''}
  <div class="sign"><div>Dipesan oleh<span>${esc(d.dipesan||d.dibuat)}</span></div><div>Disetujui pemasok<span>&nbsp;</span></div></div></body></html>`;
}
async function poTerima(d){
  const mt=await meta(true);
  let bayar='Hutang';
  const m=modal({title:'Terima barang · '+esc(d.no),cls:'lg',sticky:1,body:`<p class="muted sm" style="margin-bottom:10px">Isi jumlah yang benar-benar datang. Stok bertambah di <b>${esc(d.lokasiNama)}</b>. Sisa yang belum datang tetap tercatat di pesanan.</p>
   <div class="card np"><div class="tw"><table class="tbl"><thead><tr><th>Produk</th><th class="r">Sisa pesanan</th><th class="r" style="width:120px">Diterima</th><th class="r" style="width:150px">Harga beli</th></tr></thead><tbody>${d.items.filter(i=>i.sisa>0).map(i=>`<tr><td><b>${esc(i.nama)}</b></td><td class="r">${nf(i.sisa)} ${esc(i.satuan)}</td><td class="r"><input class="in r" data-num="dec" inputmode="decimal" data-tq="${esc(i.pid)}" value="${numStr(i.sisa)}"></td><td class="r"><input class="in r" data-num="int" inputmode="numeric" data-th="${esc(i.pid)}" value="${numStr(i.harga)}"></td></tr>`).join('')}</tbody></table></div></div>
   <div class="grid2" style="margin-top:12px">${field('Tanggal diterima',`<input class="in" type="date" id="tr-tgl" value="${today0()}" max="${today0()}">`)}
   ${field('Pembayaran',`<div class="seg s3" id="tr-pay">${['Hutang','Kas','Bank'].map(x=>`<button type="button" class="${x===bayar?'on':''}" data-p="${x}">${x==='Hutang'?'Tempo (hutang)':x}</button>`).join('')}</div>`)}</div>
   <div class="grid2"><div id="tr-jt">${field('Jatuh tempo',`<input class="in" type="date" id="tr-jtv" value="${ymd(addD(today(),30))}">`)}</div><div id="tr-ak" class="hide">${field('Dibayar dari akun',`<select class="in" id="tr-akv">${mt.kas.map(a=>`<option value="${esc(a.kode)}">${esc(a.kode)} · ${esc(a.nama)}</option>`).join('')}</select>`)}</div>
   <label class="li" style="padding:6px 0;border:0"><span class="sw"><input type="checkbox" id="tr-upd" checked><span></span></span><div class="g"><b>Perbarui harga beli produk</b><span class="muted sm">Harga modal produk ikut harga terbaru ini</span></div></label></div>
   <div class="tot"><span>Nilai penerimaan</span><b id="tr-tot"></b></div>`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="tr-go">${ic('check')}Simpan penerimaan</button>`});
  const tot=()=>{let t=0;$$('[data-tq]',m).forEach(i=>{t+=Math.round(pn(i.value)*pn($(`[data-th="${CSS.escape(i.dataset.tq)}"]`,m).value))});$('#tr-tot',m).textContent=rp(t)};
  m.addEventListener('input',tot);tot();
  $('#tr-pay',m).onclick=e=>{const b=e.target.closest('[data-p]');if(!b)return;bayar=b.dataset.p;$$('#tr-pay button',m).forEach(x=>x.classList.toggle('on',x===b));$('#tr-jt',m).classList.toggle('hide',bayar!=='Hutang');$('#tr-ak',m).classList.toggle('hide',bayar==='Hutang');$('#tr-akv',m).value=bayar==='Bank'?mt.akunBank:mt.akunKas};
  $('#tr-go',m).onclick=async ev=>{
    const items=$$('[data-tq]',m).map(i=>({pid:i.dataset.tq,qty:pn(i.value),harga:pn($(`[data-th="${CSS.escape(i.dataset.tq)}"]`,m).value)})).filter(x=>x.qty>0);
    if(!items.length)return toast('Isi jumlah barang yang diterima','err');
    try{const r=await call('poTerima',{no:d.no,items,bayar,tanggal:$('#tr-tgl',m).value,jatuhTempo:$('#tr-jtv',m).value,akunKas:$('#tr-akv',m).value,updHarga:$('#tr-upd',m).checked},ev.currentTarget);
      applyStok(r.stok);if(r.sm.harga)Object.keys(r.sm.harga).forEach(id=>{const p=byId(id);if(p)p.hargaBeli=r.sm.harga[id]});
      m.remove();toast(`Barang diterima (${r.sm.no})${r.sm.fakturNo?' · tagihan '+r.sm.fakturNo:''}`,'ok');if(S.route==='po')PAGES.po();poDetail(r.po.no,r.po)}catch(e){}
  };
}

/* --- saran pembelian --- */
PS.saran={rows:null,sel:{}};
PAGES.saran=async function(){
  const P=PS.saran;
  $('#view').innerHTML=head('Saran pembelian','Produk yang total stoknya sudah di bawah batas minimum, dikelompokkan per pemasok terakhir. Jumlah saran = 2× minimum − stok − yang sedang dipesan.')+`<div id="srlist"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  try{P.rows=await api('saranBeli',{},true)}catch(e){$('#srlist').innerHTML=failBox(e.message);return}
  if(S.route!=='saran')return;
  if(!P.rows.length){$('#srlist').innerHTML=`<div class="card">${emptyBox('Semua stok aman. Isi <b>Stok minimum</b> produk (menu Inventaris → Produk) agar saran pembelian muncul.','check')}</div>`;return}
  const G={};P.rows.forEach(r=>{const k=r.pemasok||'';(G[k]=G[k]||[]).push(r)});
  $('#srlist').innerHTML=Object.keys(G).sort((a,b)=>(a?0:1)-(b?0:1)||a.localeCompare(b)).map(k=>`<div class="card np srg" data-g="${esc(k)}"><div class="srh"><div><b>${k?esc(k):'Belum ada pemasok tercatat'}</b><span class="muted sm"> · ${G[k].length} produk</span></div>${lv()>=2?`<button class="btn sm pri" data-act="srpo" data-g="${esc(k)}">${ic('inb')}Buat pesanan</button>`:''}</div>
   <div class="tw"><table class="tbl rs"><thead><tr><th style="width:36px"></th><th>Produk</th><th class="r">Stok / min</th><th class="r">Sedang dipesan</th><th class="r" style="width:120px">Pesan</th><th class="r">Harga terakhir</th></tr></thead><tbody>${G[k].map(r=>`<tr><td class="ck"><input type="checkbox" data-sc="${esc(r.pid)}" ${r.saran>0?'checked':''} aria-label="Pilih ${esc(r.nama)}"></td><td class="nl"><div class="pcell">${thumb({nama:r.nama,foto:r.foto})}<div><b>${esc(r.nama)}</b><span class="muted sm mono">${esc(r.sku)}</span></div></div></td><td class="r" data-l="Stok / min"><span class="bdg ${r.stok<=0?'err':'warn'}">${nf(r.stok)}</span> / ${nf(r.min)} ${esc(r.satuan)}</td><td class="r" data-l="Dipesan">${r.dipesan?nf(r.dipesan):'–'}</td><td class="r" data-l="Pesan"><input class="in r" data-num="dec" inputmode="decimal" data-sq="${esc(r.pid)}" value="${r.saran?numStr(r.saran):''}"></td><td class="r" data-l="Harga">${rp(r.harga)}</td></tr>`).join('')}</tbody></table></div></div>`).join('');
};
DRAW.saran=()=>{};
ACT.srpo=b=>{
  const g=b.closest('.srg'),rows=PS.saran.rows.filter(r=>(r.pemasok||'')===b.dataset.g);
  const items=rows.filter(r=>$(`[data-sc="${CSS.escape(r.pid)}"]`,g).checked).map(r=>({pid:r.pid,qty:pn($(`[data-sq="${CSS.escape(r.pid)}"]`,g).value)||1,harga:r.harga}));
  if(!items.length)return toast('Centang produk yang akan dipesan','err');
  poForm(null,{pemasok:b.dataset.g,items});
};

/* --- retur --- */
PS.retur={preset:'30',from:'',to:'',rows:null};
PAGES.retur=async function(){
  const P=PS.retur;if(!P.from){const r=presetRange(P.preset);P.from=r[0];P.to=r[1]}
  $('#view').innerHTML=head('Retur ke pemasok','Barang rusak / kedaluwarsa / salah kirim dikembalikan ke pemasok. Stok berkurang, tagihan dipotong atau uang kembali.',lv()>=3?`<button class="btn pri" data-act="rtnew">${ic('plus')}Retur baru</button>`:'')+
   `<div class="card tb"><select class="in" id="rtp">${[['7','7 hari terakhir'],['30','30 hari terakhir'],['m','Bulan ini'],['lm','Bulan lalu']].map(x=>`<option value="${x[0]}" ${P.preset===x[0]?'selected':''}>${x[1]}</option>`).join('')}</select></div><div class="card np"><div class="tw" id="rtlist"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#rtp').onchange=e=>{P.preset=e.target.value;const r=presetRange(P.preset);P.from=r[0];P.to=r[1];PAGES.retur()};
  try{P.rows=await api('returList',{from:P.from,to:P.to},true)}catch(e){$('#rtlist').innerHTML=failBox(e.message);return}
  $('#rtlist').innerHTML=P.rows.length?`<table class="tbl rs"><thead><tr><th>No. retur</th><th>Tanggal</th><th>Pemasok</th><th>Barang</th><th>Penyelesaian</th><th class="r">Nilai</th></tr></thead><tbody>${P.rows.map(r=>`<tr><td class="nl"><b class="mono">${esc(r.no)}</b>${r.po?`<div class="muted sm">PO ${esc(r.po)}</div>`:''}</td><td data-l="Tanggal">${tgl(r.tanggal)}</td><td data-l="Pemasok">${esc(r.pemasok)}<div class="muted sm">dari ${esc(r.lokasi)}</div></td><td data-l="Barang">${r.items.map(i=>`${esc(i.nama)} <b>${nf(i.qty)}</b> ${esc(i.satuan)}`).join('<br>')}<div class="muted sm">${esc(r.alasan)}</div></td><td data-l="Penyelesaian">${r.metode==='Tagihan'?`<span class="bdg pri">Potong tagihan</span><div class="muted sm">${esc(r.faktur)}</div>`:`<span class="bdg ok">Uang kembali (${esc(r.metode)})</span>`}</td><td class="r b" data-l="Nilai">${rp(r.total)}</td></tr>`).join('')}</tbody></table>`:emptyBox('Belum ada retur pada periode ini','swap');
};
DRAW.retur=()=>{};
ACT.rtnew=()=>returForm({});
async function returForm(pre){
  const mt=await meta(true);
  const lines=(pre.items||[]).map(x=>({...x}));let metode='Tagihan',tag=[];
  const m=modal({title:'Retur barang ke pemasok',cls:'xl',sticky:1,body:`
   <div class="grid3">${field('Pemasok *',`<select class="in" id="rt-k"><option value="">– pilih pemasok –</option>${mt.pemasok.map(k=>`<option value="${esc(k.id)}" ${k.id===pre.kontakId?'selected':''}>${esc(k.nama)}</option>`).join('')}</select>`)}
   ${field('Barang diambil dari',`<select class="in" id="rt-l">${lokOpts(pre.lokasi||S.lokMasuk,false)}</select>`)}
   ${field('Alasan *',`<input class="in" id="rt-a" placeholder="mis. rusak, kedaluwarsa, salah kirim" list="rt-al"><datalist id="rt-al"><option>Barang rusak</option><option>Kedaluwarsa</option><option>Salah kirim</option><option>Kualitas tidak sesuai</option></datalist>`)}</div>
   ${pre.po?`<p class="muted sm">Terkait pesanan <b>${esc(pre.po)}</b></p>`:''}
   <div id="rt-pk"></div>
   <div class="card np" style="margin-top:10px"><div class="tw"><table class="tbl"><thead><tr><th>Produk</th><th class="r">Stok di lokasi</th><th class="r" style="width:120px">Jumlah retur</th><th class="r" style="width:150px">Harga</th><th class="r">Nilai</th><th></th></tr></thead><tbody id="rt-lines"></tbody></table></div></div>
   <div class="grid2" style="margin-top:12px">${field('Penyelesaian',`<div class="seg s3" id="rt-m">${[['Tagihan','Potong tagihan'],['Kas','Uang kembali · Kas'],['Bank','Uang kembali · Bank']].map(x=>`<button type="button" class="${x[0]===metode?'on':''}" data-m="${x[0]}">${x[1]}</button>`).join('')}</div>`)}
   <div id="rt-x"></div></div>
   <div class="tot"><span>Nilai retur</span><b id="rt-tot"></b></div>`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="rt-go">${ic('check')}Simpan retur</button>`});
  const lok=()=>$('#rt-l',m).value;
  const draw=()=>{let t=0;$('#rt-lines',m).innerHTML=lines.length?lines.map((l,i)=>{const p=byId(l.pid)||{nama:'?',satuan:'',lok:{}};t+=Math.round(l.qty*l.harga);return `<tr><td><b>${esc(p.nama)}</b><div class="muted sm mono">${esc(p.sku||'')}</div></td><td class="r">${nf(((p.lok||{})[lok()]||{}).q||0)}</td><td class="r"><input class="in r" data-num="dec" inputmode="decimal" data-rl="${i}" data-f="qty" value="${l.qty?numStr(l.qty):''}" placeholder="0"></td><td class="r"><input class="in r" data-num="int" inputmode="numeric" data-rl="${i}" data-f="harga" value="${numStr(l.harga)}"></td><td class="r b">${rp(Math.round(l.qty*l.harga))}</td><td><button class="ib err" data-rm="${i}">${ic('x')}</button></td></tr>`}).join(''):`<tr><td colspan="6">${emptyBox('Cari produk yang akan diretur','box')}</td></tr>`;$('#rt-tot',m).textContent=rp(t)};
  const drawX=async()=>{
    const x=$('#rt-x',m);
    if(metode!=='Tagihan'){x.innerHTML=field('Uang masuk ke akun',`<select class="in" id="rt-ak">${mt.kas.map(a=>`<option value="${esc(a.kode)}" ${a.kode===(metode==='Bank'?mt.akunBank:mt.akunKas)?'selected':''}>${esc(a.kode)} · ${esc(a.nama)}</option>`).join('')}</select>`);return}
    const k=$('#rt-k',m).value;if(!k){x.innerHTML=field('Tagihan yang dipotong','<div class="muted sm" style="padding:10px 0">Pilih pemasok dulu</div>');return}
    try{tag=await api('tagihanPemasok',{kontakId:k},true)}catch(e){tag=[]}
    x.innerHTML=field('Tagihan yang dipotong',tag.length?`<select class="in" id="rt-f">${tag.map(f=>`<option value="${esc(f.id)}">${esc(f.nomor)} · sisa ${rp(f.sisa)} · jatuh tempo ${fd(f.jt)}</option>`).join('')}</select>`:`<div class="err-msg">Tidak ada tagihan terbuka untuk pemasok ini. Pilih uang kembali (Kas/Bank).</div>`);
  };
  prodPicker($('#rt-pk',m),p=>{const l=lines.find(x=>x.pid===p.id);if(l)l.qty=r3((l.qty||0)+1);else lines.push({pid:p.id,qty:1,harga:p.hargaBeli||0});draw()},{info:p=>`${esc(lokName(lok()))}: ${nf((p.lok[lok()]||{}).q||0)}`});
  m.addEventListener('input',e=>{const t=e.target;if(t.dataset.rl!==undefined){const l=lines[+t.dataset.rl];l[t.dataset.f]=pn(t.value);t.closest('tr').children[4].textContent=rp(Math.round(l.qty*l.harga));let s=0;lines.forEach(x=>s+=Math.round(x.qty*x.harga));$('#rt-tot',m).textContent=rp(s)}});
  m.addEventListener('click',e=>{const b=e.target.closest('[data-rm]');if(b){lines.splice(+b.dataset.rm,1);draw()}});
  $('#rt-m',m).onclick=e=>{const b=e.target.closest('[data-m]');if(!b)return;metode=b.dataset.m;$$('#rt-m button',m).forEach(x=>x.classList.toggle('on',x===b));drawX()};
  $('#rt-k',m).onchange=drawX;$('#rt-l',m).onchange=draw;draw();drawX();
  $('#rt-go',m).onclick=async ev=>{
    const it=lines.filter(l=>l.qty>0);if(!$('#rt-k',m).value)return toast('Pilih pemasok','err');if(!it.length)return toast('Isi jumlah barang yang diretur','err');
    const f=$('#rt-f',m);if(metode==='Tagihan'&&!f)return toast('Tidak ada tagihan yang bisa dipotong. Pilih uang kembali.','err');
    if(!await ask(`Stok akan berkurang dari <b>${esc(lokName(lok()))}</b> dan retur dicatat ke akuntansi. Lanjutkan?`,'Simpan retur'))return;
    try{const r=await call('returSave',{kontakId:$('#rt-k',m).value,lokasi:lok(),po:pre.po||'',alasan:$('#rt-a',m).value,metode,fakturId:f?f.value:'',akunKas:($('#rt-ak',m)||{}).value||'',items:it},ev.currentTarget);
      applyStok(r.stok);m.remove();toast(`Retur ${r.no} tersimpan (${rp(r.total)})`,'ok');if(S.route==='retur')PAGES.retur()}catch(e){}
  };
}

/* --- pemasok --- */
PS.pemasok={q:'',rows:null};
PAGES.pemasok=async function(){
  const P=PS.pemasok;
  $('#view').innerHTML=head('Pemasok','Daftar pemasok, total pembelian, dan hutang yang belum dibayar',lv()>=2?`<button class="btn pri" data-act="pmnew">${ic('plus')}Pemasok baru</button>`:'')+`<div class="card tb"><div class="sb" style="flex:1">${ic('search')}<input class="in" id="pmq" placeholder="Cari pemasok…" value="${esc(P.q)}"></div></div><div id="pmlist" class="pmg"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#pmq').addEventListener('input',deb(e=>{P.q=e.target.value;drawPm()},120));
  try{P.rows=await api('pemasokList',{},true);drawPm()}catch(e){$('#pmlist').innerHTML=failBox(e.message)}
};
function drawPm(){
  const P=PS.pemasok,el=$('#pmlist');if(!el||!P.rows)return;const q=P.q.toLowerCase(),l=P.rows.filter(r=>!q||(r.nama+' '+r.telepon+' '+r.alamat).toLowerCase().includes(q));
  el.innerHTML=l.length?l.map(k=>`<div class="card pmc ${k.aktif?'':'off'}"><div class="pmt"><span class="ph" data-i="${esc(initials(k.nama))}"></span><div class="g"><b>${esc(k.nama)}</b><span class="muted sm">${esc(k.telepon||'tanpa telepon')}${k.aktif?'':' · nonaktif'}</span></div>${lv()>=2?`<button class="ib" data-act="pmedit" data-id="${esc(k.id)}" aria-label="Ubah">${ic('edit')}</button>`:''}</div>
   <div class="pms"><div><small>Total pembelian</small><b>${rp(k.pembelian)}</b></div><div><small>Hutang</small><b class="${k.hutang>0?'warn-t':''}">${rp(k.hutang)}</b></div><div><small>PO terbuka</small><b>${k.poTerbuka}</b></div></div>
   <div class="muted sm">${k.terakhir?'Terakhir kirim '+fd(k.terakhir):'Belum ada penerimaan'}${k.alamat?' · '+esc(k.alamat):''}</div>
   <div class="acts" style="margin-top:10px">${lv()>=2?`<button class="btn sm" data-act="pmpo" data-id="${esc(k.id)}">${ic('inb')}Buat PO</button>`:''}${k.telepon?`<a class="btn sm" target="_blank" rel="noopener" href="${WA(k.telepon,'Halo '+k.nama+', ')}">${ic('ext')}WhatsApp</a>`:''}</div></div>`).join(''):`<div class="card">${emptyBox('Belum ada pemasok','users')}</div>`;
}
DRAW.pemasok=drawPm;
ACT.pmnew=()=>pmForm({aktif:true});ACT.pmedit=b=>pmForm(PS.pemasok.rows.find(x=>x.id===b.dataset.id));
ACT.pmpo=b=>{const k=PS.pemasok.rows.find(x=>x.id===b.dataset.id);poForm(null,{pemasok:k.nama,items:[]})};
function pmForm(k){
  const m=modal({title:k.id?'Ubah pemasok':'Pemasok baru',body:`${field('Nama pemasok *',`<input class="in" id="pm-n" value="${esc(k.nama||'')}">`)}<div class="grid2">${field('Telepon / WhatsApp',`<input class="in" id="pm-t" inputmode="tel" value="${esc(k.telepon||'')}">`)}${field('Email',`<input class="in" id="pm-e" type="email" value="${esc(k.email||'')}">`)}</div>${field('Alamat',`<input class="in" id="pm-a" value="${esc(k.alamat||'')}">`)}${field('NPWP',`<input class="in" id="pm-p" value="${esc(k.npwp||'')}">`)}
   ${k.id?`<label class="li" style="padding:6px 0;border:0"><div class="g"><b>Aktif</b><span class="muted sm">Pemasok nonaktif tidak muncul di pilihan</span></div><span class="sw"><input type="checkbox" id="pm-x" ${k.aktif?'checked':''}><span></span></span></label>`:''}`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="pm-go">${ic('check')}Simpan</button>`});
  $('#pm-go',m).onclick=async ev=>{try{await call('pemasokSave',{id:k.id||'',nama:$('#pm-n',m).value,telepon:$('#pm-t',m).value,email:$('#pm-e',m).value,alamat:$('#pm-a',m).value,npwp:$('#pm-p',m).value,aktif:k.id?$('#pm-x',m).checked:true},ev.currentTarget);META=null;m.remove();toast('Pemasok disimpan','ok');if(S.route==='pemasok')PAGES.pemasok()}catch(e){}};
}

/* ======================================================================
 *  MEMBER & CRM
 * ==================================================================== */
NAV.push({id:'member',t:'Member',i:'users',m:'member',lv:1},{id:'poin',t:'Program poin',i:'sliders',m:'member',lv:3});
MAINNAV.member=['member','poin'];
PS.member={f:'semua',q:'',page:1,size:20,data:null};
const MF=[['semua','Semua'],['ultah','Ulang tahun bulan ini'],['pasif','Belum belanja > 30 hari'],['top','Belanja terbanyak'],['baru','Baru bulan ini'],['nonaktif','Nonaktif']];
PAGES.member=async function(){
  const P=PS.member;
  $('#view').innerHTML=head('Member & CRM','Kenali pelanggan setia: poin, riwayat belanja, ulang tahun, dan siapa yang perlu disapa lagi.',`<button class="btn" data-act="xmcard">${ic('print')}Cetak kartu member</button><button class="btn" data-act="mbexp">${ic('dl')}Ekspor CSV</button>${lv()>=2?`<button class="btn pri" data-act="mbnew">${ic('plus')}Member baru</button>`:''}`)+
   `<div class="kpis" id="mbk">${'<div class="skel"></div>'.repeat(4)}</div>
   <div class="card tb"><div class="chips" style="overflow-x:auto;flex-wrap:nowrap">${MF.map(x=>`<button class="chip ${P.f===x[0]?'on':''}" data-act="mbf" data-f="${x[0]}">${x[1]}</button>`).join('')}</div><div class="sb" style="flex:1;min-width:200px">${ic('search')}<input class="in" id="mbq" placeholder="Cari nama, HP, kode…" value="${esc(P.q)}"></div></div>
   <div class="card np"><div class="tw" id="mblist"><div class="skel" style="margin:14px"></div></div></div>`;
  $('#mbq').addEventListener('input',deb(e=>{P.q=e.target.value;P.page=1;loadMb()},250));
  loadMb();
};
async function loadMb(){
  const P=PS.member;
  try{P.data=await api('mbrList',{f:P.f,q:P.q},true)}catch(e){const l=$('#mblist');if(l)l.innerHTML=failBox(e.message);return}
  if(S.route!=='member')return;
  const s=P.data.sum,pc=P.data.poin,pct=s.omzetTotal?Math.round(s.omzetMember/s.omzetTotal*100):0;
  const k=(l,v,i,sub)=>`<div class="card kpi"><div class="kl"><span class="ic">${ic(i)}</span>${l}</div><div class="kv">${v}</div><div class="muted sm">${sub}</div></div>`;
  $('#mbk').innerHTML=k('Member aktif',n0(s.total),'users',`+${n0(s.baru)} bulan ini`)+k('Belanja 30 hari',n0(s.aktif30),'cart','member yang datang lagi')+k('Porsi omzet member',pct+'%','pct',`${rp(s.omzetMember)} dari ${rp(s.omzetTotal)}`)+k('Poin beredar',n0(s.poin),'wallet',pc.aktif?`setara ${rp(s.poin*pc.nilai)}`:'program poin nonaktif');
  drawMb();
}
function drawMb(){
  const P=PS.member,el=$('#mblist');if(!el||!P.data)return;const l=P.data.list,pg=l.slice((P.page-1)*P.size,P.page*P.size);
  el.innerHTML=(pg.length?`<table class="tbl rs click"><thead><tr><th>Member</th><th>HP</th><th class="r">Poin</th><th class="r">Total belanja</th><th class="r">Transaksi</th><th>Terakhir belanja</th>${P.f==='ultah'?'<th>Ulang tahun</th>':''}</tr></thead><tbody>${pg.map(x=>`<tr data-act="mbdet" data-id="${esc(x.id)}"><td class="nl"><div class="pcell"><span class="av">${esc(initials(x.nama))}</span><div><b>${esc(x.nama)}</b><span class="muted sm mono">${esc(x.kode)}${x.aktif?'':' · nonaktif'}</span></div></div></td><td data-l="HP" class="mono">${esc(x.hp)}</td><td class="r" data-l="Poin"><span class="bdg pri">${n0(x.poin)}</span></td><td class="r b" data-l="Total">${rp(x.total)}</td><td class="r" data-l="Transaksi">${n0(x.trx)}</td><td data-l="Terakhir">${x.terakhir?fd(x.terakhir):'<span class="muted">belum pernah</span>'}</td>${P.f==='ultah'?`<td data-l="Ulang tahun">${x.tglLahir?fd(x.tglLahir).replace(/ \d{4}$/,''):''}</td>`:''}</tr>`).join('')}</tbody></table>`:emptyBox(P.f==='semua'&&!P.q?'Belum ada member. Daftarkan dari sini atau langsung dari layar kasir.':'Tidak ada member yang cocok','users'))+pager(l.length,P.page,P.size,'member');
}
DRAW.member=drawMb;
ACT.mbf=b=>{PS.member.f=b.dataset.f;PS.member.page=1;$$('[data-act=mbf]').forEach(c=>c.classList.toggle('on',c===b));loadMb()};
ACT.mbnew=()=>mbForm({});
ACT.mbdet=b=>mbDetail(b.dataset.id);
ACT.mbexp=()=>{const l=(PS.member.data||{}).list||[];dlCsv('member-'+today0()+'.csv',['Kode','Nama','HP','Email','Tanggal lahir','Poin','Total belanja','Transaksi','Terakhir belanja','Terdaftar'],l.map(x=>[x.kode,x.nama,x.hp,x.email,x.tglLahir,x.poin,x.total,x.trx,x.terakhir,x.dibuat]))};
function mbForm(x,after){
  const m=modal({title:x.id?'Ubah member':'Member baru',body:`<div class="grid2">${field('Nama *',`<input class="in" id="mf-n" value="${esc(x.nama||'')}">`)}${field('Nomor HP / WhatsApp *',`<input class="in" id="mf-h" inputmode="tel" value="${esc(x.hp||'')}">`)}</div>
   <div class="grid3">${field('Tanggal lahir',`<input class="in" type="date" id="mf-l" value="${esc(x.tglLahir||'')}">`)}${field('Jenis kelamin',`<select class="in" id="mf-j"><option value="">–</option><option value="L" ${x.jk==='L'?'selected':''}>Laki-laki</option><option value="P" ${x.jk==='P'?'selected':''}>Perempuan</option></select>`)}${field('Email',`<input class="in" id="mf-e" type="email" value="${esc(x.email||'')}">`)}</div>
   ${field('Alamat',`<input class="in" id="mf-a" value="${esc(x.alamat||'')}">`)}${field('Catatan',`<input class="in" id="mf-c" value="${esc(x.catatan||'')}" placeholder="mis. suka produk organik">`)}
   ${x.id?`<label class="li" style="padding:6px 0;border:0"><div class="g"><b>Aktif</b><span class="muted sm">Member nonaktif tidak bisa dipilih di kasir</span></div><span class="sw"><input type="checkbox" id="mf-x" ${x.aktif?'checked':''}><span></span></span></label>`:''}`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="mf-go">${ic('check')}Simpan</button>`});
  $('#mf-go',m).onclick=async ev=>{try{const r=await call('mbrSave',{id:x.id||'',nama:$('#mf-n',m).value,hp:$('#mf-h',m).value,tglLahir:$('#mf-l',m).value,jk:$('#mf-j',m).value,email:$('#mf-e',m).value,alamat:$('#mf-a',m).value,catatan:$('#mf-c',m).value,...(x.id?{aktif:$('#mf-x',m).checked}:{})},ev.currentTarget);m.remove();toast('Member disimpan','ok');if(S.route==='member')loadMb();if(after)after(r)}catch(e){}};
}
const WAT={ultah:(m,s)=>`Selamat ulang tahun, ${m.nama}! 🎉 Terima kasih sudah jadi pelanggan setia ${s}. Ada kejutan kecil untukmu saat belanja minggu ini.`,
  kangen:(m,s)=>`Halo ${m.nama}, sudah lama tidak mampir ke ${s}. Kami punya promo baru yang mungkin kamu suka — ditunggu kedatangannya ya!`,
  poin:(m,s,p)=>`Halo ${m.nama}, poin belanjamu di ${s} sekarang ${n0(m.poin)} poin${p.nilai?` (senilai ${rp(m.poin*p.nilai)})`:''}. Bisa langsung ditukar saat belanja berikutnya.`};
async function mbDetail(id){
  let d;try{d=await call('mbrGet',{id})}catch(e){return}
  const x=d.m,pc=(PS.member.data||{}).poin||S.poinCfg||{};
  const m=modal({title:'Member '+esc(x.kode),cls:'lg',body:`<div class="mbh"><span class="av lg">${esc(initials(x.nama))}</span><div class="g"><h2>${esc(x.nama)}</h2><div class="muted sm">${esc(x.hp)}${x.email?' · '+esc(x.email):''}${x.tglLahir?' · lahir '+fd(x.tglLahir):''}</div>${x.catatan?`<div class="sm" style="margin-top:4px">📝 ${esc(x.catatan)}</div>`:''}</div></div>
   <div class="mbs"><div><small>Poin</small><b>${n0(x.poin)}</b>${pc.nilai?`<small>= ${rp(x.poin*pc.nilai)}</small>`:''}</div><div><small>Total belanja</small><b>${rp(x.total)}</b></div><div><small>Transaksi</small><b>${n0(x.trx)}</b><small>rata-rata ${rp(x.trx?x.total/x.trx:0)}</small></div><div><small>Terakhir belanja</small><b>${x.terakhir?fd(x.terakhir):'–'}</b><small>member sejak ${fd(x.dibuat)}</small></div></div>
   ${d.fav.length?`<div class="sm" style="margin:4px 0 12px"><b>Sering dibeli:</b> ${d.fav.map(f=>`${esc(f.nama)} <span class="muted">(${nf(f.qty)})</span>`).join(', ')}</div>`:''}
   <div class="tabs"><button class="tab on" data-t="trx">Transaksi</button><button class="tab" data-t="poin">Riwayat poin</button></div>
   <div id="mbt"></div>
   <div class="wabox"><select class="in" id="wat"><option value="kangen">Ajak belanja lagi</option><option value="ultah">Ucapan ulang tahun</option><option value="poin">Info saldo poin</option></select><a class="btn" id="wago" target="_blank" rel="noopener">${ic('ext')}Kirim WhatsApp</a></div>`,
   foot:`${lv()>=3?`<button class="btn" data-x="poin">${ic('wallet')}Koreksi poin</button>`:''}<button class="btn" data-act="xmcard" data-id="${esc(x.id)}">${ic('print')}Kartu member</button><span style="flex:1"></span>${lv()>=2?`<button class="btn pri" data-x="edit">${ic('edit')}Ubah data</button>`:''}`});
  const tab=t=>{$$('.tab',m).forEach(b=>b.classList.toggle('on',b.dataset.t===t));$('#mbt',m).innerHTML=t==='trx'?(d.trx.length?`<div class="tw" style="max-height:260px"><table class="tbl"><thead><tr><th>No.</th><th>Waktu</th><th class="r">Total</th><th class="r">Poin</th><th>Status</th></tr></thead><tbody>${d.trx.map(t=>`<tr><td class="mono">${esc(t.no)}</td><td>${fd(t.tanggal)} ${ft(t.tanggal)}</td><td class="r">${rp(t.total)}</td><td class="r">${t.poinDapat?'+'+t.poinDapat:''}${t.poinTukar?' −'+t.poinTukar:''}</td><td>${t.status==='Batal'?'<span class="bdg err">Batal</span>':'<span class="bdg ok">Selesai</span>'}</td></tr>`).join('')}</tbody></table></div>`:emptyBox('Belum ada transaksi','cart')):(d.poin.length?`<div class="tw" style="max-height:260px"><table class="tbl"><thead><tr><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th class="r">Poin</th><th class="r">Saldo</th></tr></thead><tbody>${d.poin.map(p=>`<tr><td>${fd(p.tanggal)} ${ft(p.tanggal)}</td><td>${esc(p.jenis)}</td><td class="sm">${esc(p.ref)} ${esc(p.ket)}</td><td class="r ${p.poin<0?'neg':'pos'}">${p.poin>0?'+':''}${n0(p.poin)}</td><td class="r b">${n0(p.saldo)}</td></tr>`).join('')}</tbody></table></div>`:emptyBox('Belum ada riwayat poin','wallet'))};
  $$('.tab',m).forEach(b=>b.onclick=()=>tab(b.dataset.t));tab('trx');
  const wa=()=>{$('#wago',m).href=WA(x.hp,WAT[$('#wat',m).value](x,S.set.namaToko,pc))};$('#wat',m).onchange=wa;wa();
  m.addEventListener('click',async e=>{const b=e.target.closest('[data-x]');if(!b)return;
    if(b.dataset.x==='edit'){m.remove();mbForm(x,r=>mbDetail(r.id))}
    if(b.dataset.x==='poin'){const k=modal({title:'Koreksi poin',cls:'sm',body:`<p class="muted sm" style="margin-bottom:10px">Saldo sekarang <b>${n0(x.poin)} poin</b>. Isi angka positif untuk menambah, negatif untuk mengurangi.</p>${field('Jumlah poin',`<input class="in" id="kp-n" inputmode="numeric" placeholder="mis. 50 atau -20">`)}${field('Keterangan *',`<input class="in" id="kp-k" placeholder="mis. bonus pendaftaran">`)}`,foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="kp-go">Simpan</button>`});
      $('#kp-go',k).onclick=async ev=>{try{await call('mbrPoin',{id:x.id,poin:parseInt($('#kp-n',k).value,10)||0,ket:$('#kp-k',k).value},ev.currentTarget);k.remove();m.remove();toast('Poin dikoreksi','ok');mbDetail(x.id);if(S.route==='member')loadMb()}catch(er){}}}
  });
}
PAGES.poin=async function(){
  $('#view').innerHTML=head('Program poin','Atur berapa belanja untuk 1 poin dan berapa nilai 1 poin saat ditukar di kasir')+'<div class="card pad" id="pnbox"><div class="skel"></div></div>';
  let pc;try{pc=(await api('mbrList',{f:'baru'},true)).poin}catch(e){$('#pnbox').innerHTML=failBox(e.message);return}
  $('#pnbox').innerHTML=`<label class="li" style="padding:0 0 14px;border:0"><div class="g"><b>Program poin aktif</b><span class="muted sm">Bila nonaktif, member tetap bisa dipilih di kasir tetapi tidak mendapat atau menukar poin</span></div><span class="sw"><input type="checkbox" id="pn-a" ${pc.aktif?'checked':''}><span></span></span></label>
   <div class="grid3">${field('Setiap belanja (Rp) dapat 1 poin',`<input class="in" id="pn-p" data-num="int" inputmode="numeric" value="${numStr(pc.per)}">`)}${field('Nilai 1 poin saat ditukar (Rp)',`<input class="in" id="pn-v" data-num="int" inputmode="numeric" value="${numStr(pc.nilai)}">`)}${field('Minimal poin sekali tukar',`<input class="in" id="pn-m" data-num="int" inputmode="numeric" value="${numStr(pc.min)}">`)}</div>
   <div class="hint" id="pn-h"></div><div style="margin-top:14px"><button class="btn pri" id="pn-go">${ic('check')}Simpan</button></div>`;
  const h=()=>{const p=pn($('#pn-p').value)||1,v=pn($('#pn-v').value),b=100000,po=Math.floor(b/p);$('#pn-h').innerHTML=`Contoh: belanja <b>${rp(b)}</b> → <b>${n0(po)} poin</b> = ${rp(po*v)} (<b>${(po*v/b*100).toFixed(1).replace('.',',')}%</b> kembali ke pelanggan). Poin dihitung dari total yang dibayar dan tercatat sebagai diskon penjualan saat ditukar.`};
  $('#pnbox').addEventListener('input',h);h();
  $('#pn-go').onclick=async ev=>{try{const r=await call('poinSet',{aktif:$('#pn-a').checked,per:pn($('#pn-p').value),nilai:pn($('#pn-v').value),min:pn($('#pn-m').value)},ev.currentTarget);S.poinCfg=r;toast('Program poin disimpan','ok')}catch(e){}};
};
DRAW.poin=()=>{};

/* ======================================================================
 *  PROMO
 * ==================================================================== */
NAV.push({id:'promo',t:'Promo',i:'tag',m:'promo',lv:1});
MAINNAV.promo=['promo'];
PS.promo={f:'Semua',rows:null};
const PT={diskon:['Diskon produk','Harga coret: potong persen, rupiah, atau harga khusus'],bxgy:['Beli X gratis Y','mis. beli 2 gratis 1 untuk produk yang sama'],grosir:['Harga grosir','Makin banyak beli, makin murah per unit'],nota:['Diskon belanja','Potongan bila total belanja mencapai minimal']};
const HARI=['Sen','Sel','Rab','Kam','Jum','Sab','Min'];
PAGES.promo=async function(){
  const P=PS.promo;
  $('#view').innerHTML=head('Promo','Promo otomatis diterapkan di kasir — kasir tidak perlu menghitung manual. Bila beberapa promo cocok, dipilih yang paling hemat untuk pelanggan.',lv()>=2?`<button class="btn pri" data-act="prnew">${ic('plus')}Promo baru</button>`:'')+
   `<div class="card tb"><div class="chips">${['Semua','Berjalan','Terjadwal','Berakhir','Nonaktif'].map(x=>`<button class="chip ${P.f===x?'on':''}" data-act="prf" data-f="${x}">${x}</button>`).join('')}</div></div><div id="prlist" class="prg"><div class="card"><div class="skel" style="margin:14px"></div></div></div>`;
  try{P.rows=await api('prmList',{},true);drawPr()}catch(e){$('#prlist').innerHTML=failBox(e.message)}
};
function prTarget(p){if(p.tipe==='nota')return `Belanja minimal ${rp(p.min)}`;if(p.target==='semua')return 'Semua produk';if(p.target==='kategori')return 'Kategori: '+p.ids.map(esc).join(', ');const n=p.ids.map(id=>(byId(id)||{}).nama).filter(Boolean);return n.length<=3?n.map(esc).join(', '):`${esc(n.slice(0,2).join(', '))} +${n.length-2} produk`}
function prNilai(p){if(p.tipe==='bxgy')return `Beli ${p.beli} gratis ${p.gratis}`;if(p.tipe==='grosir')return p.tingkat.slice().reverse().map(t=>`≥${nf(t.min)}: ${p.nt==='p'?t.v+'%':p.nt==='h'?rp(t.v)+'/unit':'−'+rp(t.v)}`).join(' · ');const v=p.nt==='p'?p.nilai+'%':p.nt==='h'?'jadi '+rp(p.nilai):rp(p.nilai);return p.tipe==='nota'?`Potong ${v}${p.nt==='p'&&p.maks?' (maks '+rp(p.maks)+')':''}`:(p.nt==='h'?'Harga promo '+rp(p.nilai):'Diskon '+v)}
function prWaktu(p){const h=p.hari.length===7?'Setiap hari':p.hari.split('').map(d=>HARI[d-1]).join(', ');return `${p.mulai||p.selesai?`${p.mulai?fd(p.mulai):'…'} – ${p.selesai?fd(p.selesai):'seterusnya'}`:'Tanpa batas tanggal'} · ${h}${p.jam1?` · ${p.jam1}–${p.jam2}`:''}`}
function drawPr(){
  const P=PS.promo,el=$('#prlist');if(!el||!P.rows)return;const l=P.rows.filter(p=>P.f==='Semua'||p.status===P.f||(P.f==='Berjalan'&&p.status==='Di luar jam'));
  el.innerHTML=l.length?l.map(p=>`<div class="card prc ${p.aktif?'':'off'}"><div class="prt"><span class="prk t-${p.tipe}">${ic(p.tipe==='nota'?'wallet':p.tipe==='grosir'?'box':'tag')}</span><div class="g"><b>${esc(p.nama)}</b><span class="muted sm">${PT[p.tipe][0]}${p.member?' · <b>khusus member</b>':''}</span></div>${stB(p.status)}</div>
   <div class="prv">${esc(prNilai(p))}</div><div class="sm">${prTarget(p)}</div><div class="muted sm" style="margin-top:4px">${prWaktu(p)}</div>
   <div class="prf"><span class="sm"><b>${n0(p.dipakai)}</b> transaksi · hemat <b>${rp(p.diskon)}</b></span><span style="flex:1"></span>${lv()>=2?`<label class="sw" title="Aktif / nonaktif"><input type="checkbox" data-act="prtog" data-id="${esc(p.id)}" ${p.aktif?'checked':''}><span></span></label><button class="ib" data-act="predit" data-id="${esc(p.id)}" aria-label="Ubah">${ic('edit')}</button>`:''}${lv()>=3?`<button class="ib err" data-act="prdel" data-id="${esc(p.id)}" aria-label="Hapus">${ic('trash')}</button>`:''}</div></div>`).join(''):`<div class="card">${emptyBox(P.rows.length?'Tidak ada promo dengan status ini':'Belum ada promo. Buat promo pertama: diskon, beli 2 gratis 1, harga grosir, atau diskon belanja.','tag')}</div>`;
}
DRAW.promo=drawPr;
ACT.prf=b=>{PS.promo.f=b.dataset.f;$$('[data-act=prf]').forEach(c=>c.classList.toggle('on',c===b));drawPr()};
ACT.prnew=()=>prForm({tipe:'diskon',nt:'p',target:'semua',ids:[],hari:'1234567',aktif:true,tingkat:[]});
ACT.predit=b=>prForm(JSON.parse(JSON.stringify(PS.promo.rows.find(x=>x.id===b.dataset.id))));
ACT.prtog=async b=>{try{await call('prmToggle',{id:b.dataset.id});const p=PS.promo.rows.find(x=>x.id===b.dataset.id);p.aktif=!p.aktif;toast(p.aktif?'Promo diaktifkan':'Promo dinonaktifkan','ok');PAGES.promo()}catch(e){b.checked=!b.checked}};
ACT.prdel=async b=>{const p=PS.promo.rows.find(x=>x.id===b.dataset.id);if(!await ask(`Hapus promo <b>${esc(p.nama)}</b>?`,'Hapus',1))return;try{await call('prmDel',{id:p.id});toast('Promo dihapus','ok');PAGES.promo()}catch(e){}};
function prForm(p){
  if(!p.tingkat||!p.tingkat.length)p.tingkat=[{min:'',v:''}];
  const m=modal({title:p.id?'Ubah promo':'Promo baru',cls:'lg',sticky:1,body:`${field('Nama promo *',`<input class="in" id="pr-n" value="${esc(p.nama||'')}" placeholder="mis. Gajian Hemat, Happy Hour Kopi">`)}
   <label class="lb">Jenis promo</label><div class="ptype" id="pr-t">${Object.keys(PT).map(k=>`<button type="button" class="${p.tipe===k?'on':''}" data-t="${k}"><b>${PT[k][0]}</b><small>${PT[k][1]}</small></button>`).join('')}</div>
   <div id="pr-x" style="margin-top:12px"></div>
   <div id="pr-tg"></div>
   <div class="grid2">${field('Mulai tanggal',`<input class="in" type="date" id="pr-m" value="${esc(p.mulai||'')}">`)}${field('Sampai tanggal',`<input class="in" type="date" id="pr-s" value="${esc(p.selesai||'')}">`)}</div>
   <label class="lb">Berlaku pada hari</label><div class="chips" id="pr-h" style="margin-bottom:12px">${HARI.map((h,i)=>`<button type="button" class="chip ${p.hari.includes(String(i+1))?'on':''}" data-h="${i+1}">${h}</button>`).join('')}</div>
   <div class="grid2">${field('Jam mulai (opsional)',`<input class="in" type="time" id="pr-j1" value="${esc(p.jam1||'')}">`)}${field('Jam selesai',`<input class="in" type="time" id="pr-j2" value="${esc(p.jam2||'')}">`)}</div>
   <label class="li" style="padding:6px 0;border:0"><div class="g"><b>Khusus member</b><span class="muted sm">Hanya berlaku bila kasir memilih member di keranjang</span></div><span class="sw"><input type="checkbox" id="pr-mb" ${p.member?'checked':''}><span></span></span></label>
   <label class="li" style="padding:6px 0;border:0"><div class="g"><b>Aktif</b><span class="muted sm">Matikan untuk menyimpan promo tanpa menjalankannya</span></div><span class="sw"><input type="checkbox" id="pr-a" ${p.aktif?'checked':''}><span></span></span></label>
   <div class="hint" id="pr-sum"></div>`,
   foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="pr-go">${ic('check')}Simpan promo</button>`});
  const ntSeg=(opts)=>`<div class="seg s3" id="pr-nt">${opts.map(o=>`<button type="button" class="${p.nt===o[0]?'on':''}" data-nt="${o[0]}">${o[1]}</button>`).join('')}</div>`;
  const drawX=()=>{
    const x=$('#pr-x',m);
    if(p.tipe==='diskon'){if(!['p','r','h'].includes(p.nt))p.nt='p';x.innerHTML=`<label class="lb">Bentuk potongan</label>${ntSeg([['p','Persen (%)'],['r','Potongan Rp / unit'],['h','Harga promo / unit']])}${field(p.nt==='p'?'Besar diskon (%)':p.nt==='h'?'Harga jual selama promo (Rp)':'Potongan per unit (Rp)',`<input class="in" id="pr-v" data-num="dec" inputmode="decimal" value="${p.nilai?numStr(p.nilai):''}">`)}`}
    else if(p.tipe==='bxgy')x.innerHTML=`<div class="grid2">${field('Beli (jumlah)',`<input class="in" id="pr-b" inputmode="numeric" value="${p.beli||2}">`)}${field('Gratis (jumlah)',`<input class="in" id="pr-g" inputmode="numeric" value="${p.gratis||1}">`)}</div><p class="muted sm" style="margin:-4px 0 10px">Berlaku kelipatan untuk produk yang sama: beli 2 gratis 1 → ambil 6 bayar 4.</p>`;
    else if(p.tipe==='grosir'){if(!['p','r','h'].includes(p.nt))p.nt='h';x.innerHTML=`<label class="lb">Tingkat harga</label>${ntSeg([['h','Harga per unit'],['p','Diskon %'],['r','Potongan Rp / unit']])}<div id="pr-tk">${p.tingkat.map((t,i)=>`<div class="tkr"><span>Beli minimal</span><input class="in" data-tk="${i}" data-k="min" data-num="dec" inputmode="decimal" value="${t.min?numStr(t.min):''}" placeholder="12"><span>${p.nt==='h'?'harga/unit Rp':p.nt==='p'?'diskon %':'potong Rp/unit'}</span><input class="in" data-tk="${i}" data-k="v" data-num="dec" inputmode="decimal" value="${t.v?numStr(t.v):''}"><button type="button" class="ib err" data-tkrm="${i}">${ic('x')}</button></div>`).join('')}</div><button type="button" class="btn sm" id="pr-tka" style="margin:4px 0 12px">${ic('plus')}Tambah tingkat</button>`}
    else{if(p.nt==='h')p.nt='r';x.innerHTML=`<div class="grid3">${field('Minimal belanja (Rp)',`<input class="in" id="pr-min" data-num="int" inputmode="numeric" value="${p.min?numStr(p.min):''}">`)}<div>${`<label class="lb">Bentuk potongan</label>`+ntSeg([['r','Rupiah'],['p','Persen']]).replace('s3','s2')}</div>${field(p.nt==='p'?'Diskon (%)':'Potongan (Rp)',`<input class="in" id="pr-v" data-num="dec" inputmode="decimal" value="${p.nilai?numStr(p.nilai):''}">`)}</div>${p.nt==='p'?field('Maksimal potongan (Rp, opsional)',`<input class="in" id="pr-mx" data-num="int" inputmode="numeric" value="${p.maks?numStr(p.maks):''}">`):''}`}
    drawTg();sum();
  };
  const drawTg=()=>{
    const el=$('#pr-tg',m);if(p.tipe==='nota'){el.innerHTML='';return}
    el.innerHTML=`<label class="lb">Berlaku untuk</label><div class="seg s3" id="pr-tt">${[['semua','Semua produk'],['kategori','Kategori'],['produk','Produk tertentu']].map(o=>`<button type="button" class="${p.target===o[0]?'on':''}" data-tt="${o[0]}">${o[1]}</button>`).join('')}</div>`+
     (p.target==='kategori'?`<div class="chips" style="margin-bottom:12px">${S.cats.map(c=>`<button type="button" class="chip ${p.ids.includes(c.nama)?'on':''}" data-kc="${esc(c.nama)}">${esc(c.nama)}</button>`).join('')||'<span class="muted sm">Belum ada kategori</span>'}</div>`:'')+
     (p.target==='produk'?`<div id="pr-pk"></div><div class="chips" id="pr-pl" style="margin:8px 0 12px">${p.ids.map(id=>{const q=byId(id);return q?`<span class="chip on">${esc(q.nama)} <button type="button" class="xbtn" data-pr="${esc(id)}" aria-label="Hapus">×</button></span>`:''}).join('')||'<span class="muted sm">Belum ada produk dipilih</span>'}</div>`:'');
    if(p.target==='produk')prodPicker($('#pr-pk',m),q=>{if(!p.ids.includes(q.id))p.ids.push(q.id);drawTg();sum();$('#pr-pk input',m).focus()},{info:q=>rp(q.hargaJual),ph:'Cari produk lalu Enter / klik'});
  };
  const read=()=>{const v=id=>{const e=$(id,m);return e?pn(e.value):0};
    if(p.tipe==='diskon'||p.tipe==='nota')p.nilai=v('#pr-v');if(p.tipe==='nota'){p.min=v('#pr-min');p.maks=v('#pr-mx')}if(p.tipe==='bxgy'){p.beli=v('#pr-b');p.gratis=v('#pr-g')}
    $$('[data-tk]',m).forEach(i=>{p.tingkat[+i.dataset.tk][i.dataset.k]=pn(i.value)})};
  const sum=()=>{read();const ex=S.prods.find(q=>p.target==='semua'||(p.target==='kategori'&&p.ids.includes(q.kategori))||p.ids.includes(q.id));let t='';
    if(ex&&p.tipe==='diskon'&&p.nilai){const h=ex.hargaJual,nh=p.nt==='p'?h*(1-Math.min(100,p.nilai)/100):p.nt==='h'?p.nilai:h-p.nilai;t=`Contoh di kasir: <b>${esc(ex.nama)}</b> <s>${rp(h)}</s> → <b>${rp(Math.max(0,Math.round(nh)))}</b>`}
    else if(ex&&p.tipe==='bxgy'&&p.beli&&p.gratis)t=`Contoh: ambil ${p.beli+p.gratis} <b>${esc(ex.nama)}</b>, bayar ${p.beli} → hemat <b>${rp(ex.hargaJual*p.gratis)}</b>`;
    else if(p.tipe==='nota'&&p.min&&p.nilai)t=`Contoh: belanja ${rp(p.min)} → potongan <b>${p.nt==='p'?rp(Math.min(p.min*p.nilai/100,p.maks||1e15)):rp(p.nilai)}</b>`;
    else if(ex&&p.tipe==='grosir'){const tk=p.tingkat.filter(x=>x.min&&x.v).sort((a,b)=>a.min-b.min)[0];if(tk){const u=p.nt==='h'?tk.v:p.nt==='p'?ex.hargaJual*(1-tk.v/100):ex.hargaJual-tk.v;t=`Contoh: beli ${nf(tk.min)} <b>${esc(ex.nama)}</b> → ${rp(Math.round(u))}/unit (normal ${rp(ex.hargaJual)})`}}
    $('#pr-sum',m).innerHTML=t?ic('tag')+t:''};
  m.addEventListener('click',e=>{const b=e.target;
    const t=b.closest('[data-t]');if(t){read();p.tipe=t.dataset.t;$$('#pr-t button',m).forEach(x=>x.classList.toggle('on',x===t));drawX();return}
    const nt=b.closest('[data-nt]');if(nt){read();p.nt=nt.dataset.nt;drawX();return}
    const tt=b.closest('[data-tt]');if(tt){if(p.target!==tt.dataset.tt){p.target=tt.dataset.tt;p.ids=[]}drawTg();sum();return}
    const kc=b.closest('[data-kc]');if(kc){const n=kc.dataset.kc;p.ids=p.ids.includes(n)?p.ids.filter(x=>x!==n):p.ids.concat(n);kc.classList.toggle('on');sum();return}
    const pr=b.closest('[data-pr]');if(pr){p.ids=p.ids.filter(x=>x!==pr.dataset.pr);drawTg();sum();return}
    const h=b.closest('[data-h]');if(h){h.classList.toggle('on');return}
    if(b.closest('#pr-tka')){read();p.tingkat.push({min:'',v:''});drawX();return}
    const rm=b.closest('[data-tkrm]');if(rm){read();p.tingkat.splice(+rm.dataset.tkrm,1);if(!p.tingkat.length)p.tingkat.push({min:'',v:''});drawX()}
  });
  m.addEventListener('input',deb(sum,200));
  drawX();
  $('#pr-go',m).onclick=async ev=>{read();
    const hari=$$('#pr-h [data-h]',m).filter(x=>x.classList.contains('on')).map(x=>x.dataset.h).join('');
    try{await call('prmSave',{id:p.id||'',nama:$('#pr-n',m).value,tipe:p.tipe,nt:p.nt,nilai:p.nilai,target:p.target,ids:p.ids,beli:p.beli,gratis:p.gratis,tingkat:p.tingkat.filter(t=>t.min&&t.v),min:p.min,maks:p.maks,
      mulai:$('#pr-m',m).value,selesai:$('#pr-s',m).value,hari,jam1:$('#pr-j1',m).value,jam2:$('#pr-j2',m).value,member:$('#pr-mb',m).checked,aktif:$('#pr-a',m).checked},ev.currentTarget);
      m.remove();toast('Promo disimpan','ok');PAGES.promo()}catch(e){}
  };
}

/* ======================================================================
 *  DASHBOARD PEMILIK & LAPORAN
 * ==================================================================== */
NAV.push({id:'ringkasan',t:'Dashboard pemilik',i:'grid',m:'laporan',lv:1},{id:'lap',t:'Laporan',i:'file',m:'laporan',lv:1});
MAINNAV.laporan=['ringkasan','lap'];
PS.rg={preset:'7',lok:''};
const RG=[['0','Hari ini'],['7','7 hari'],['30','30 hari'],['m','Bulan ini'],['lm','Bulan lalu']];
const short=x=>x>=1e9?(x/1e9).toFixed(1).replace('.',',')+' M':x>=1e6?(x/1e6).toFixed(1).replace('.',',')+' jt':x>=1e3?Math.round(x/1e3)+' rb':String(x);
const dl=(c,p)=>delta(c,p);
PAGES.ringkasan=async function(){
  const P=PS.rg,v=$('#view'),[from,to]=presetRange(P.preset);
  const ctl=`<div class="chips">${RG.map(x=>`<button class="chip ${P.preset===x[0]?'on':''}" data-act="rgp" data-p="${x[0]}">${x[1]}</button>`).join('')}</div><select class="in" id="rgl" style="width:auto">${lokOpts(P.lok,true).replace('Semua lokasi (total)','Semua lokasi / cabang')}</select>`;
  v.innerHTML=head('Dashboard pemilik',`${esc(S.set.namaToko)} · ${fd(from)}${from!==to?' – '+fd(to):''}`,ctl)+`<div class="kpis">${'<div class="skel"></div>'.repeat(5)}</div>`;
  $('#rgl').onchange=e=>{P.lok=e.target.value;PAGES.ringkasan()};
  let d;try{d=await api('dash',{from,to,lokasi:P.lok},true)}catch(e){if(S.route==='ringkasan')v.innerHTML=head('Dashboard pemilik','',ctl)+failBox(e.message);return}
  if(S.route!=='ringkasan')return;
  const k=d.kpi,p=d.prev,c=d.cost;
  const kpi=(l,val,i,sub)=>`<div class="card kpi"><div class="kl"><span class="ic">${ic(i)}</span>${l}</div><div class="kv">${val}</div><div>${sub}</div></div>`;
  const al=(cls,i,t,s,href)=>`<${href?`a href="${href}"`:'div'} class="card alr ${cls}"><span class="ai">${ic(i)}</span><div><b>${t}</b><span class="muted sm">${s}</span></div></${href?'a':'div'}>`;
  const lst=(rows,f,emp,i)=>rows.length?rows.map(f).join(''):emptyBox(emp,i);
  v.innerHTML=head('Dashboard pemilik',`${esc(S.set.namaToko)} · ${fd(from)}${from!==to?' – '+fd(to):''}`,ctl+`<button class="btn" data-act="rgr">${ic('sync')}Muat ulang</button>`)+`
  <div class="kpis k5">
   ${kpi('Omzet',rp(k.omzet),'wallet',dl(k.omzet,p.omzet))}
   ${c?kpi('Laba kotor',rp(k.laba),'trend',dl(k.laba,p.laba)+`<div class="muted sm">margin ${k.omzet?(k.laba/k.omzet*100).toFixed(1).replace('.',','):0}%</div>`):kpi('Diskon & promo',rp(k.diskon),'tag','<span class="muted sm">potongan diberikan</span>')}
   ${kpi('Transaksi',n0(k.n),'list',dl(k.n,p.n))}
   ${kpi('Rata-rata belanja',rp(k.avg),'pct',dl(k.avg,p.avg))}
   ${kpi('Belanja member',(k.omzet?Math.round(k.omzetMember/k.omzet*100):0)+'%','users',`<span class="muted sm">${n0(k.member)} member · ${n0(d.memberBaru)} baru</span>`)}
  </div>
  <div class="alerts">
   ${al(d.stok.habis?'err':d.stok.tipis?'warn':'ok','box',d.stok.habis||d.stok.tipis?`${n0(d.stok.habis)} habis · ${n0(d.stok.tipis)} menipis`:'Stok aman',c&&d.stok.nilai!=null?`Nilai persediaan ${rp(d.stok.nilai)}`:`${n0(d.stok.produk)} produk`,(S.user.akses||{}).inventaris?'inventaris.html#produk':'')}
   ${al(d.hutang.telat?'err':d.hutang.minggu?'warn':'ok','wallet',d.hutang.sisa?`Hutang ${rp(d.hutang.sisa)}`:'Tidak ada hutang',d.hutang.telat?`${d.hutang.nTelat} tagihan lewat jatuh tempo (${rp(d.hutang.telat)})`:d.hutang.minggu?`${rp(d.hutang.minggu)} jatuh tempo 7 hari ke depan`:'Tidak ada yang jatuh tempo minggu ini',(S.user.akses||{}).akuntansi?'akuntansi.html':'')}
   ${al(d.po.n?'pri':'ok','inb',d.po.n?`${n0(d.po.n)} pesanan pembelian terbuka`:'Tidak ada PO terbuka',d.po.n?`Nilai ${rp(d.po.t)} menunggu barang`:'Semua pesanan sudah selesai',(S.user.akses||{}).pembelian?'pembelian.html#po':'')}
   ${al(d.mutasi.jalan||d.mutasi.minta?'warn':'ok','swap',d.mutasi.jalan?`${n0(d.mutasi.jalan)} mutasi dalam perjalanan`:'Tidak ada barang di jalan',d.mutasi.minta?`${n0(d.mutasi.minta)} permintaan barang belum dikirim`:'Semua permintaan sudah diproses',(S.user.akses||{}).inventaris?'inventaris.html#mutasi':'')}
   ${c&&d.kas.length?al('pri','wallet','Kas & bank '+rp(d.kas.reduce((a,x)=>a+x.s,0)),d.kas.map(x=>`${esc(x.nama)} ${rp(x.s)}`).join(' · '),(S.user.akses||{}).akuntansi?'akuntansi.html':''):''}
  </div>
  <div class="g2"><div class="card"><h3>${P.preset==='0'?'Omzet per jam':'Omzet'+(c?' & laba':'')+' harian'}</h3><div class="cv"><canvas id="rg1"></canvas></div></div>
   <div class="card"><h3>Omzet per lokasi / cabang</h3><div class="cv"><canvas id="rg2"></canvas></div></div></div>
  <div class="g3">
   <div class="card np"><h3 style="padding-bottom:6px">Produk terlaris</h3>${lst(d.top,(x,i)=>`<div class="li"><span class="bdg pri">${i+1}</span><div class="g"><b>${esc(x.nama)}</b><span class="muted sm">${nf(x.q)} ${esc(x.satuan)}${c?` · laba ${rp(x.l)}`:''}</span></div><b>${rp(x.o)}</b></div>`,'Belum ada penjualan','box')}</div>
   <div class="card np"><h3 style="padding-bottom:6px">Member teratas</h3>${lst(d.member,(x,i)=>`<div class="li"><span class="av">${esc(initials(x.nama))}</span><div class="g"><b>${esc(x.nama)}</b><span class="muted sm">${esc(x.kode)} · ${n0(x.n)} transaksi</span></div><b>${rp(x.o)}</b></div>`,'Belum ada belanja member','users')}</div>
   <div class="card np"><h3 style="padding-bottom:6px">Promo paling dipakai</h3>${lst(d.promo,x=>`<div class="li"><span class="bdg warn">${ic('tag')}</span><div class="g"><b>${esc(x.nama)}</b><span class="muted sm">${n0(x.n)} transaksi</span></div><b>−${rp(x.d)}</b></div>`,'Belum ada promo terpakai','tag')}</div>
  </div>
  <div class="g2b"><div class="card"><h3>Penjualan per kategori</h3><div class="cv"><canvas id="rg3"></canvas></div></div><div class="card"><h3>Metode pembayaran</h3><div class="cv"><canvas id="rg4"></canvas></div></div></div>`;
  $('#rgl').onchange=e=>{P.lok=e.target.value;PAGES.ringkasan()};
  const opt=(o={})=>Object.assign({responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:x=>' '+x.dataset.label+': '+rp(x.parsed.y!=null&&o.indexAxis!=='y'?x.parsed.y:x.parsed.x)}}},scales:{y:{beginAtZero:true,ticks:{callback:short}},x:{grid:{display:false}}}},o);
  let lb=[],om=[],lab=[];
  if(P.preset==='0'){for(let h=6;h<=22;h++){lb.push(pad(h)+':00');om.push(d.jam[h])}}
  else{const D={};d.day.forEach(x=>D[x.d]=x);for(let t=new Date(from+'T12:00:00');ymd(t)<=to;t=addD(t,1)){const s=ymd(t);lb.push((+s.slice(8))+' '+MON[+s.slice(5,7)-1]);om.push(D[s]?D[s].o:0);lab.push(D[s]?D[s].l:0)}}
  const ds=[{label:'Omzet',data:om,backgroundColor:PAL[0],borderRadius:4,maxBarThickness:28}];if(c&&P.preset!=='0')ds.push({label:'Laba',data:lab,backgroundColor:PAL[2],borderRadius:4,maxBarThickness:28});
  const o1=opt();if(ds.length>1)o1.plugins.legend={display:true,position:'bottom',labels:{boxWidth:10,usePointStyle:true}};
  mkChart('rg1',{type:'bar',data:{labels:lb,datasets:ds},options:o1});
  mkChart('rg2',{type:'bar',data:{labels:d.lokasi.map(x=>x.nama),datasets:[{label:'Omzet',data:d.lokasi.map(x=>x.o),backgroundColor:PAL[0],borderRadius:4,maxBarThickness:40}]},options:opt()});
  mkChart('rg3',{type:'bar',data:{labels:d.kategori.map(x=>x.nama),datasets:[{label:'Omzet',data:d.kategori.map(x=>x.o),backgroundColor:PAL[2],borderRadius:4,maxBarThickness:24}]},options:opt({indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{callback:short}},y:{grid:{display:false}}}})});
  mkChart('rg4',{type:'doughnut',data:{labels:d.metode.length?d.metode.map(x=>x.nama):['Belum ada data'],datasets:[{label:'Omzet',data:d.metode.length?d.metode.map(x=>x.o):[1],backgroundColor:d.metode.length?PAL:['#cbd5d3'],borderWidth:2,borderColor:getComputedStyle(document.documentElement).getPropertyValue('--card').trim()}]},options:{responsive:true,maintainAspectRatio:false,cutout:'64%',plugins:{legend:{position:'bottom',labels:{boxWidth:10,usePointStyle:true}},tooltip:{callbacks:{label:x=>' '+x.label+': '+rp(x.parsed)}}}}});
  if(typeof Chart==='undefined')$$('.cv').forEach(x=>x.innerHTML='<div class="muted sm" style="padding:30px 0;text-align:center">Grafik membutuhkan koneksi internet.</div>');
};
DRAW.ringkasan=()=>{};
ACT.rgp=b=>{PS.rg.preset=b.dataset.p;PAGES.ringkasan()};
ACT.rgr=()=>PAGES.ringkasan();

/* --- pusat laporan --- */
const LAPS=[['Penjualan',[['harian','Penjualan per hari','list'],['produk','Penjualan per produk','box'],['kategori','Per kategori','tag'],['kasir','Per kasir','user'],['lokasi','Per lokasi / cabang','pin'],['metode','Per metode bayar','wallet'],['jam','Per jam (jam ramai)','trend']]],
  ['Pelanggan & promo',[['member','Belanja member','users'],['promo','Pemakaian promo','tag']]],
  ['Pembelian & stok',[['pemasok','Pembelian per pemasok','inb'],['persediaan','Nilai persediaan per lokasi','box'],['tidaklaku','Produk tidak laku','alert']]]];
const NOLOK=['lokasi','promo','pemasok','persediaan','tidaklaku'];
PS.lp={kind:'harian',preset:'30',from:'',to:'',lok:'',res:null,page:1,size:50};
PAGES.lap=function(){
  const P=PS.lp;if(!P.from){const r=presetRange(P.preset);P.from=r[0];P.to=r[1]}
  $('#view').innerHTML=head('Laporan','Pilih laporan di kiri, atur periode, lalu unduh ke Excel atau PDF bila perlu.')+`<div class="lpw"><nav class="card lpn">${LAPS.map(g=>`<div class="lpg">${g[0]}</div>`+g[1].map(x=>`<button class="lpb ${P.kind===x[0]?'on':''}" data-act="lpk" data-k="${x[0]}">${ic(x[2])}${x[1]}</button>`).join('')).join('')}</nav><section id="lpv"></section></div>`;
  drawLp();
};
function drawLp(){
  const P=PS.lp,el=$('#lpv');if(!el)return;const noDate=P.kind==='persediaan',noLok=NOLOK.includes(P.kind);
  el.innerHTML=`<div class="card tb">${noDate?'<span class="muted sm">Posisi stok saat ini</span>':`<select class="in" id="lpp">${[['0','Hari ini'],['7','7 hari terakhir'],['30','30 hari terakhir'],['m','Bulan ini'],['lm','Bulan lalu'],['c','Kustom']].map(x=>`<option value="${x[0]}" ${P.preset===x[0]?'selected':''}>${x[1]}</option>`).join('')}</select><input type="date" class="in" id="lpf" value="${P.from}"><input type="date" class="in" id="lpt" value="${P.to}">`}
   ${noLok?'':`<select class="in" id="lpl">${lokOpts(P.lok,true).replace('Semua lokasi (total)','Semua lokasi')}</select>`}<span style="flex:1"></span><button class="btn" data-act="lpx" data-f="xlsx">${ic('xls')}Excel</button><button class="btn" data-act="lpx" data-f="pdf">${ic('pdf')}PDF</button></div>
   <div class="card np"><div class="tw" id="lpt0"><div class="skel" style="margin:14px"></div></div></div>`;
  const re=()=>{P.page=1;loadLp()};
  if(!noDate){$('#lpp').onchange=e=>{P.preset=e.target.value;const r=presetRange(P.preset);if(r){P.from=r[0];P.to=r[1]}drawLp()};$('#lpf').onchange=e=>{P.from=e.target.value;P.preset='c';re()};$('#lpt').onchange=e=>{P.to=e.target.value;P.preset='c';re()}}
  if(!noLok)$('#lpl').onchange=e=>{P.lok=e.target.value;re()};
  loadLp();
}
async function loadLp(){
  const P=PS.lp,k=P.kind;
  try{P.res=await api('lapRun',{kind:k,from:P.from,to:P.to,lokasi:NOLOK.includes(k)?'':P.lok},true)}catch(e){const t=$('#lpt0');if(t)t.innerHTML=failBox(e.message);return}
  if(P.kind!==k)return;drawLpTbl();
}
const fmtCell=(v,k)=>v==null||v===''?'':k==='money'?rp(v):k==='int'?n0(v):k==='dec'?nf(v):k==='date'?fd(String(v)):k==='datetime'?fd(String(v))+' '+ft(String(v)):esc(v);
function drawLpTbl(){
  const P=PS.lp,el=$('#lpt0');if(!el||!P.res)return;const s=P.res.section,rows=s.rows,pg=rows.slice((P.page-1)*P.size,P.page*P.size),al=k=>['money','int','dec'].includes(k)?'r':k==='c'?'c':'';
  el.innerHTML=(rows.length?`<table class="tbl lpt"><thead><tr>${s.cols.map(c=>`<th class="${al(c.k)}">${esc(c.h)}</th>`).join('')}</tr></thead><tbody>${pg.map(r=>`<tr>${r.map((v,i)=>`<td class="${al(s.cols[i].k)} ${i===0?'b':''}">${fmtCell(v,s.cols[i].k)}</td>`).join('')}</tr>`).join('')}</tbody>${s.total?`<tfoot><tr>${s.total.map((v,i)=>`<td class="${al(s.cols[i].k)}">${i===0?esc(v):fmtCell(v,s.cols[i].k)}</td>`).join('')}</tr></tfoot>`:''}</table>`:emptyBox('Tidak ada data pada periode ini','file'))+
   (s.note?`<p class="muted sm" style="padding:10px 16px">${esc(s.note)}</p>`:'')+(P.res.cost?'':`<p class="muted sm" style="padding:0 16px 12px">Laba, HPP, dan nilai persediaan hanya tampil untuk akses Manajer laporan.</p>`)+pager(rows.length,P.page,P.size,'lp');
}
DRAW.lp=drawLpTbl;DRAW.lap=()=>{};
ACT.lpk=b=>{PS.lp.kind=b.dataset.k;PS.lp.page=1;$$('.lpb').forEach(x=>x.classList.toggle('on',x===b));drawLp()};
ACT.lpx=b=>{const P=PS.lp;if(!P.res||!P.res.section.rows.length)return toast('Tidak ada data untuk diunduh','err');
  const rep=repBase(P.res.title,P.res.from,P.res.to);if(P.lok&&!NOLOK.includes(P.kind))rep.title+=' — '+lokName(P.lok);rep.sections.push(P.res.section);rep.file=slug(rep.title)+'_'+P.res.from+'_'+P.res.to;exportReport(b.dataset.f,rep,b)};
})();
