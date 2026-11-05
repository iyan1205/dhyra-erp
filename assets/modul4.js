/* ============================================================
 *  Dhyra v5.1 — Printer struk (per perangkat)
 * ============================================================ */
(function(){
if(!window.DhyraPrint)return;
const P=window.DhyraPrint;
NAV.push({id:'printer',t:'Printer struk',i:'print',m:'kasir',lv:2});
const MODES=[
  ['browser','Dialog cetak browser','Cara lama: muncul jendela cetak. Cocok untuk semua printer yang sudah terpasang di komputer.',true],
  ['bt','Printer Bluetooth','Printer thermal Bluetooth (58/80 mm). Chrome di Android, Windows, Mac, ChromeOS.',P.supported.bt],
  ['usb','Printer USB langsung','Printer thermal USB tanpa driver. Chrome / Edge di komputer.',P.supported.usb],
  ['serial','Printer USB-Serial / COM','Printer yang terbaca sebagai port COM (driver serial). Chrome / Edge di komputer.',P.supported.serial],
  ['rawbt','Aplikasi RawBT (Android)','Cetak lewat aplikasi gratis RawBT dari Play Store. Paling mudah untuk HP / tablet Android.',P.supported.rawbt]
];
PAGES.printer=function(){
  const c=P.cfg(),def=(S.set&&S.set.kertas)||'58';
  $('#view').innerHTML=head('Printer struk','Pengaturan ini tersimpan di perangkat ini saja. Atur di setiap komputer / tablet kasir yang dipakai.')+
  `<div class="prtw"><div class="card pad"><h3 style="margin:0 0 12px">Cara mencetak</h3><div class="pmode">${MODES.map(m=>`<label class="${m[3]?'':'dis'}"><input type="radio" name="pm" value="${m[0]}" ${c.mode===m[0]?'checked':''} ${m[3]?'':'disabled'}><span><b>${m[1]}</b><small>${m[3]?m[2]:'Tidak didukung di browser / perangkat ini.'}</small></span></label>`).join('')}</div></div>
   <div class="card pad"><h3 style="margin:0 0 12px">Printer</h3><div id="pst" class="pst"></div>
    <div class="grid2">${'<div class="fg"><label class="lb">Lebar kertas</label><select class="in" id="pl"><option value="">Ikuti pengaturan toko ('+def+' mm)</option><option value="58" '+(c.lebar==='58'?'selected':'')+'>58 mm (32 karakter)</option><option value="80" '+(c.lebar==='80'?'selected':'')+'>80 mm (48 karakter)</option></select></div>'}
     <div class="fg"><label class="lb">Jumlah salinan</label><select class="in" id="ps">${[1,2,3].map(n=>`<option ${+c.salinan===n?'selected':''}>${n}</option>`).join('')}</select></div></div>
    <div class="grid2"><div class="fg"><label class="lb">Baris kosong sebelum potong</label><input class="in" id="pf" inputmode="numeric" value="${c.feed}"></div><div class="fg" id="pbw"><label class="lb">Baud rate (serial)</label><select class="in" id="pb">${[9600,19200,38400,57600,115200].map(n=>`<option ${+c.baud===n?'selected':''}>${n}</option>`).join('')}</select></div></div>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="pa" ${c.auto?'checked':''} style="width:18px;height:18px"><div class="g"><b>Cetak otomatis setelah bayar</b><span class="muted sm">Struk langsung keluar tanpa menekan tombol cetak.</span></div></label>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="pc" ${c.potong?'checked':''} style="width:18px;height:18px"><div class="g"><b>Potong kertas otomatis</b><span class="muted sm">Untuk printer yang punya pisau (auto-cutter).</span></div></label>
    <label class="li" style="padding:8px 0"><input type="checkbox" id="pd" ${c.laci?'checked':''} style="width:18px;height:18px"><div class="g"><b>Buka laci uang saat bayar tunai</b><span class="muted sm">Laci uang terhubung ke port RJ11 printer.</span></div></label>
    <div class="acts" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><button class="btn pri" data-act="xpsv">${ic('check')}Simpan</button><button class="btn" data-act="xpcon" id="pcon">${ic('sync')}Hubungkan printer</button><button class="btn" data-act="xptes">${ic('print')}Cetak tes</button><button class="btn" data-act="xplaci">Buka laci</button></div></div>
   <div class="card pad prth"><h3 style="margin:0 0 8px">Petunjuk singkat</h3><ol class="sm">
    <li><b>Bluetooth:</b> nyalakan printer, aktifkan Bluetooth & Lokasi di HP, pilih <i>Printer Bluetooth</i>, klik <i>Hubungkan printer</i>, pilih nama printer (mis. RPP02N, MTP-II, BlueTooth Printer).</li>
    <li><b>RawBT:</b> pasang aplikasi <i>RawBT</i> dari Play Store, sambungkan printer di aplikasi itu sekali, lalu pilih mode RawBT di sini.</li>
    <li><b>USB di Windows:</b> bila <i>USB langsung</i> gagal karena driver, pakai mode <i>USB-Serial / COM</i> atau <i>Dialog cetak browser</i>.</li>
    <li>iPhone / iPad belum mendukung Bluetooth dari browser — gunakan dialog cetak (AirPrint) atau printer di komputer kasir.</li></ol></div></div>`;
  const tog=()=>{const m=($('input[name=pm]:checked')||{}).value;$('#pbw').style.display=m==='serial'?'':'none';$('#pcon').style.display=['bt','usb','serial'].includes(m)?'':'none';stat()};
  $$('input[name=pm]').forEach(r=>r.onchange=()=>{P.save({mode:r.value});tog()});tog();
};
function stat(){const c=P.cfg(),el=$('#pst');if(!el)return;const con=P.connected();
  el.innerHTML=c.mode==='browser'?`<span class="bdg">Dialog cetak browser</span>`:`<span class="bdg ${con?'ok':'warn'}">${con?'Terhubung':'Belum terhubung'}</span> <b>${esc(c.nama||'')}</b>${!con&&c.mode!=='rawbt'?'<span class="muted sm"> · klik Hubungkan printer, atau printer akan dihubungkan otomatis saat mencetak.</span>':''}`}
const form=()=>({lebar:$('#pl').value,salinan:+$('#ps').value,feed:Math.max(1,Math.min(10,pn($('#pf').value)||3)),baud:+$('#pb').value,auto:$('#pa').checked,potong:$('#pc').checked,laci:$('#pd').checked});
ACT.xpsv=()=>{P.save(form());toast('Pengaturan printer disimpan di perangkat ini','ok');stat()};
ACT.xpcon=async b=>{P.save(form());b.disabled=true;try{const n=await P.connect(true);toast('Terhubung: '+n,'ok')}catch(e){if(e&&e.name!=='NotFoundError')toast(e.message||String(e),'err')}finally{b.disabled=false;stat()}};
ACT.xptes=async b=>{P.save(form());b.disabled=true;try{if(P.isDirect()){await P.test(S.set);toast('Halaman tes dikirim ke printer','ok')}else printHtml(receiptHtml({no:'TES-PRINTER',tanggal:ymd(new Date())+' '+pad(new Date().getHours())+':'+pad(new Date().getMinutes())+':00',kasir:S.user.username,pelanggan:'',items:[{nama:'Contoh produk',qty:2,satuan:'pcs',harga:5000,diskon:0,subtotal:10000}],subtotal:10000,diskon:0,pajak:0,total:10000,metode:'Tunai',bayar:10000,kembali:0}))}catch(e){toast(e.message||String(e),'err')}finally{b.disabled=false;stat()}};
ACT.xplaci=async b=>{if(!P.isDirect())return toast('Laci uang hanya bisa dibuka lewat printer yang terhubung langsung','err');try{await P.drawer();toast('Perintah buka laci dikirim','ok')}catch(e){toast(e.message,'err')}};
})();

/* ============================================================
 *  Import Excel umum (Inventaris Aset, Karyawan) — template berisi 5 contoh
 * ============================================================ */
(function(){
const norm=x=>cellStr(x).toLowerCase().replace(/[^a-z0-9]/g,'');
const xlDate=v=>{if(v==null||v==='')return '';if(typeof v==='object'&&isFinite(v.n)){const d=new Date(Date.UTC(1899,11,30)+Math.round(v.n)*864e5);return d.toISOString().slice(0,10)}return String(v).trim()};
const val=(c,v)=>c.t==='n'?cellNum(v):c.t==='d'?xlDate(v):cellStr(v);
function template(o){
  const hs={b:1,c:'FFFFFF',bg:X_TEAL,h:'center',bb:['thin',X_DEEP],wrap:1},rq={b:1,c:'FFFFFF',bg:'B4322B',h:'center',bb:['thin',X_DEEP],wrap:1},tx={bb:['thin',X_LINE],fmt:'@'},nm={bb:['thin',X_LINE],h:'right',fmt:'#,##0'};
  const rows=[{h:32,cells:o.cols.map(c=>({v:c.h+(c.req?' *':''),s:c.req?rq:hs}))},...o.sample.map(r=>({cells:r.map((v,i)=>({v,s:o.cols[i].t==='n'?nm:tx}))}))];
  for(let i=0;i<300;i++)rows.push({cells:o.cols.map(c=>({v:'',s:c.t==='n'?nm:tx}))});
  const info=[['Petunjuk import '+o.nama],[''],...o.tips.map(t=>['• '+t]),[''],['Kolom bertanda * (merah) wajib diisi. Judul kolom di baris 1 jangan diubah.'],['Tanggal ditulis 2025-01-31 atau 31/01/2025. Angka tanpa titik/Rp juga boleh.'],['Baris 2–6 berisi 5 contoh — hapus atau ganti dengan data Anda sebelum import.']];
  const data=xlsxBuild([{name:o.sheet,cols:o.cols.map(c=>c.w||14),rows,freeze:1,selected:true},{name:'Petunjuk',cols:[110],rows:info.map((r,i)=>({cells:[{v:r[0],s:i?{}:{b:1,sz:13,c:X_TEAL}}]}))}],'Template import '+o.nama,'Template import '+o.nama);
  saveFile('template-import-'+slug(o.nama)+'.xlsx',data,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
}
function parse(o,rows){
  let hi=-1,map=null;
  for(let r=0;r<Math.min(rows.length,15);r++){
    const m={};(rows[r]||[]).forEach((cell,i)=>{const n=norm(cell);if(!n)return;for(const c of o.cols){if(m[c.k]==null&&(c.a.includes(n)||norm(c.h)===n)){m[c.k]=i;break}}});
    if(m.nama!=null){hi=r;map=m;break}
  }
  if(hi<0)throw new Error('Judul kolom tidak ditemukan. Pastikan ada kolom "'+o.cols.find(c=>c.k==='nama').h+'" — gunakan template agar mudah.');
  const out=[];
  for(let r=hi+1;r<rows.length;r++){
    const R=rows[r]||[],x={_row:r+1};let any=false;
    o.cols.forEach(c=>{if(map[c.k]==null)return;const v=val(c,R[map[c.k]]);if(v!==''&&v!=null){x[c.k]=v;any=true}else x[c.k]=''});
    if(!any)continue;
    const err=o.cols.filter(c=>c.req&&(x[c.k]===''||x[c.k]==null)).map(c=>c.h+' kosong');
    if(o.cek)err.push(...o.cek(x));
    x._err=err.join(', ');out.push(x);
  }
  return {items:out,cols:o.cols.filter(c=>map[c.k]!=null)};
}
window.xlImport=function(o){
  let P=null;
  const m=modal({title:'Import '+o.nama+' dari Excel',cls:'xl',sticky:1,body:`<div class="imp-steps">
    <div class="card pad imp-s"><b>1. Unduh template</b><p class="muted sm">${o.tplInfo||`Template sudah berisi <b>5 contoh</b> dan petunjuk pengisian. Kolom wajib: ${o.cols.filter(c=>c.req).map(c=>'<b>'+esc(c.h)+'</b>').join(', ')}.`}</p>${o.extra||''}<button class="btn" id="xit">${ic('dl')}Unduh template Excel</button></div>
    <div class="card pad imp-s"><b>2. Pilih file</b><p class="muted sm">Format <b>.xlsx</b> atau <b>.csv</b>. Sheet pertama yang dibaca.</p><label class="imp-drop" id="xid"><input type="file" id="xif" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>${ic('xls')}<span id="xifn">Klik atau seret file ke sini</span></label></div>
    <div class="card pad imp-s"><b>3. Periksa & import</b><p class="muted sm">${o.info}</p>${o.contoh?`<button class="btn sm" id="xic">${ic('plus')}Atau isi 5 data contoh</button>`:''}</div></div><div id="xip"></div>`,
    foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="xigo" disabled>${ic('check')}Import</button>`});
  $('#xit',m).onclick=async ev=>{if(!o.tplFn)return template(o);const b=ev.currentTarget;b.disabled=true;try{await o.tplFn(m)}catch(e){toast(e.message,'err')}finally{b.disabled=false}};
  const load=async f=>{if(!f)return;$('#xifn',m).textContent=f.name;$('#xip',m).innerHTML='<div class="skel" style="margin:14px 0"></div>';
    try{const rows=/\.csv$/i.test(f.name)?readCsv(await f.text()):await readXlsx(await f.arrayBuffer());P=parse(o,rows);draw()}catch(e){P=null;$('#xip',m).innerHTML=`<div class="err-msg">${esc(e.message)}</div>`;$('#xigo',m).disabled=true}};
  $('#xif',m).onchange=e=>load(e.target.files[0]);
  const dz=$('#xid',m);dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('on')});dz.addEventListener('dragleave',()=>dz.classList.remove('on'));dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('on');load(e.dataTransfer.files[0])});
  function draw(){
    const bad=P.items.filter(x=>x._err),C=P.cols;
    $('#xip',m).innerHTML=`<div class="imp-sum"><span class="bdg ok">${n0(P.items.length-bad.length)} baris siap</span>${bad.length?`<span class="bdg err">${n0(bad.length)} baris bermasalah</span>`:''}<span class="muted sm">${bad.length?'Perbaiki baris bertanda merah di Excel lalu pilih file lagi.':'Semua baris valid.'}</span></div>
     <div class="card np"><div class="tw" style="max-height:44vh"><table class="tbl"><thead><tr><th>Baris</th><th>Status</th>${C.map(c=>`<th class="${c.t==='n'?'r':''}">${esc(c.h)}</th>`).join('')}</tr></thead><tbody>${P.items.slice(0,300).map(x=>`<tr><td class="muted">${x._row}</td><td>${x._err?`<span class="bdg err">Perlu diperbaiki</span><div class="sm" style="color:var(--err)">${esc(x._err)}</div>`:'<span class="bdg ok">OK</span>'}</td>${C.map(c=>`<td class="${c.t==='n'?'r':''} ${c.k==='nama'?'b':''}">${x[c.k]===''?'<span class="muted">–</span>':c.t==='n'?nf(x[c.k]):c.t==='d'?esc(/^\d{4}-\d\d-\d\d$/.test(x[c.k])?fd(x[c.k]):x[c.k]):esc(x[c.k])}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`;
    $('#xigo',m).disabled=!P.items.length||bad.length>0;
  }
  $('#xigo',m).onclick=async ev=>{try{const r=await call(o.act,{items:P.items.map(x=>{const y=Object.assign({},x);delete y._err;return y})},ev.currentTarget);
    m.remove();if(o.msg){toast(o.msg(r),'ok');o.done&&o.done(r);return}toast(`Import selesai: ${n0(r.baru)} baru${r.ubah?`, ${n0(r.ubah)} diperbarui`:''}${r.lewat?`, ${n0(r.lewat)} dilewati (kode sudah ada)`:''}${r.akun&&r.akun.length?`, ${r.akun.length} akun login dibuat`:''}`,'ok');o.done&&o.done();if(r.akun&&r.akun.length)showAkun(r.akun)}catch(e){}};
  if($('#xic',m))$('#xic',m).onclick=async ev=>{try{const r=await call(o.contoh,{},ev.currentTarget);m.remove();toast(r.n?`${r.n} data contoh ditambahkan`:'Data contoh sudah ada','ok');o.done&&o.done()}catch(e){}};
};
const Y=new Date().getFullYear();
window.IMPORT_ASET={nama:'aset',sheet:'Aset',act:'asetImport',contoh:'asetContoh',
  info:'Aset dengan kode yang sudah ada dilewati. Sumber "Sudah dimiliki" + akumulasi kosong: penyusutan sampai bulan lalu dihitung otomatis dan dicatat sebagai modal.',
  cols:[{k:'kode',h:'Kode aset',a:['kode','kodeaset','noaset','nomoraset'],w:12},{k:'nama',h:'Nama aset',a:['nama','namaaset','aset','barang','namabarang'],req:1,w:34},{k:'kategori',h:'Kategori',a:['kategori','jenis','golongan'],w:22},
    {k:'lokasi',h:'Lokasi',a:['lokasi','penempatan','ruang'],w:16},{k:'merek',h:'Merek / tipe',a:['merek','merk','tipe','merektipe'],w:18},{k:'noSeri',h:'No. seri',a:['noseri','serial','nomorseri','sn'],w:14},
    {k:'pj',h:'Penanggung jawab',a:['penanggungjawab','pj','pemegang'],w:16},{k:'pemasok',h:'Pemasok / dibeli di',a:['pemasok','supplier','dibelidi','pemasokdibelidi'],w:18},
    {k:'tglBeli',h:'Tanggal perolehan',a:['tanggalperolehan','tglperolehan','tanggalbeli','tglbeli','tanggal'],t:'d',req:1,w:15},{k:'harga',h:'Harga perolehan',a:['hargaperolehan','harga','hargabeli','nilaiperolehan'],t:'n',req:1,w:15},
    {k:'umur',h:'Umur (bulan)',a:['umurbulan','umur','umurmanfaat','umurmanfaatbulan'],t:'n',w:11},{k:'nilaiSisa',h:'Nilai sisa',a:['nilaisisa','residu'],t:'n',w:12},
    {k:'sumber',h:'Sumber dana',a:['sumberdana','sumber','dibayardari'],w:15},{k:'susutAwal',h:'Akumulasi penyusutan awal',a:['akumulasipenyusutanawal','akumulasipenyusutan','akumulasi','penyusutanawal'],t:'n',w:16},
    {k:'kondisi',h:'Kondisi',a:['kondisi'],w:14},{k:'catatan',h:'Catatan',a:['catatan','keterangan'],w:24}],
  sample:[['AST-0101','Mesin kasir POS + printer struk','Mesin & peralatan kasir','Toko Utama','Epson TM-T82X','X5K8123456','Kepala toko','Toko Komputer Jaya',(Y-1)+'-08-15',6500000,48,0,'Sudah dimiliki','','Baik',''],
    ['AST-0102','Rak gondola besi 5 susun (4 unit)','Rak & etalase','Toko Utama','','','Staf gudang','UD Rak Sentosa',(Y-2)+'-09-01',8400000,96,800000,'Sudah dimiliki','','Baik',''],
    ['AST-0103','Etalase kaca 1,5 m','Rak & etalase','Toko Utama','','','Kepala toko','',(Y-1)+'-03-10',3200000,60,300000,'Sudah dimiliki','','Perlu perbaikan','Kaca samping retak'],
    ['AST-0104','Kulkas showcase 2 pintu','Elektronik','Toko Utama','Polytron SCN-500','PL2207788','Kepala toko','Electronic City',(Y-1)+'-02-20',7800000,60,500000,'Kas','','Baik',''],
    ['AST-0105','Timbangan digital 30 kg','Peralatan gudang','Gudang Utama','Camry','','Staf gudang','',(Y-1)+'-06-05',950000,36,0,'Bank','','Baik','']],
  tips:['Kategori: Mesin & peralatan kasir, Rak & etalase, Elektronik, Furnitur, Kendaraan, Peralatan gudang, Bangunan & renovasi, Lainnya.','Lokasi diisi nama atau kode lokasi (mis. Toko Utama, Gudang Utama). Kosong = lokasi penjualan.',
    'Umur dalam bulan (48 = 4 tahun). Kosong = 48 bulan. Nilai sisa = perkiraan harga jual di akhir umur, boleh 0.',
    'Sumber dana: "Sudah dimiliki" untuk peralatan lama (dicatat sebagai modal pemilik), "Kas" atau "Bank" bila baru dibeli & uangnya keluar dari kas/bank.',
    'Akumulasi penyusutan awal: kosongkan agar dihitung otomatis dari tanggal perolehan sampai bulan lalu (hanya untuk "Sudah dimiliki").','Kondisi: Baik, Perlu perbaikan, Rusak, Hilang. Kosong = Baik.']};
const KPI_SB={hadir:'Kehadiran',tepat:'Tepat waktu',omzet:'Omzet penjualan',trx:'Jumlah transaksi',member:'Member baru',manual:'Penilaian atasan'};
window.IMPORT_KPI_TPL={nama:'template KPI',sheet:'Template KPI',act:'kpiTplImport',
  info:'Satu baris = satu indikator. Baris dengan nama template yang sama digabung. Template yang sudah ada diganti indikatornya; nilai penilaian lama tetap tersambung bila nama indikatornya sama.',
  cols:[{k:'template',h:'Nama template',a:['namatemplate','template','templatekpi','jabatan'],w:22},{k:'nama',h:'Indikator',a:['indikator','namaindikator','kpi','ukuran'],req:1,w:34},
    {k:'sumber',h:'Sumber data',a:['sumberdata','sumber','cara','jenis'],w:20},{k:'target',h:'Target',a:['target','sasaran'],t:'n',req:1,w:14},{k:'bobot',h:'Bobot (%)',a:['bobot','bobotpersen','bobot%','persen'],t:'n',req:1,w:11}],
  cek:x=>{const e=[];if(x.target!==''&&x.target<=0)e.push('Target harus > 0');if(x.bobot!==''&&x.bobot<=0)e.push('Bobot harus > 0');
    if(x.sumber&&!Object.values(KPI_SB).concat(Object.keys(KPI_SB),['Omzet','Member','Manual','Penilaian manual','Absensi','Transaksi']).some(v=>v.toLowerCase().replace(/[^a-z]/g,'')===String(x.sumber).toLowerCase().replace(/[^a-z]/g,'')))e.push('Sumber data tidak dikenal');return e},
  sample:[['KPI Akuntan','Kehadiran','Kehadiran',100,25],['KPI Akuntan','Tepat waktu','Tepat waktu',100,15],['KPI Akuntan','Laporan keuangan tepat waktu (tgl 5)','Penilaian atasan',100,30],
    ['KPI Akuntan','Ketelitian jurnal & rekonsiliasi bank','Penilaian atasan',100,30],['KPI Kasir Senior','Omzet penjualan','Omzet penjualan',40000000,40],['KPI Kasir Senior','Jumlah transaksi','Jumlah transaksi',900,20],
    ['KPI Kasir Senior','Member baru didaftarkan','Member baru',25,15],['KPI Kasir Senior','Selisih kas (100 = tidak pernah selisih)','Penilaian atasan',100,25]],
  tips:['Sumber data: Kehadiran (%), Tepat waktu (%), Omzet penjualan (Rp, dari kasir), Jumlah transaksi, Member baru — dihitung otomatis. Penilaian atasan = diisi atasan tiap bulan.',
    'Target: angka yang dianggap 100%. Kehadiran & tepat waktu biasanya 100; omzet dalam rupiah per bulan; penilaian atasan biasanya skala 100.',
    'Bobot: tingkat kepentingan indikator. Sebaiknya total bobot satu template = 100.','Nama template kosong = mengikuti baris di atasnya.',
    'Setelah import, pasang template ke karyawan di KPI → Template & bonus, atau lewat kolom Template KPI di import data karyawan.']};
window.IMPORT_KPI_NILAI={nama:'penilaian KPI',sheet:'Penilaian KPI',act:'kpiNilaiImport',
  info:'Hanya indikator <b>Penilaian atasan</b> yang diisi; indikator lain dihitung otomatis. Nilai kosong = tidak diubah.',
  tplInfo:'Template berisi semua karyawan yang punya KPI beserta indikator yang perlu dinilai atasan dan nilai yang sudah ada. Tinggal isi kolom <b>Nilai</b>.',
  cols:[{k:'periode',h:'Periode',a:['periode','bulan'],req:1,w:11},{k:'kode',h:'Kode',a:['kode','kodekaryawan','nik'],w:10},{k:'nama',h:'Nama karyawan',a:['namakaryawan','nama','karyawan'],req:1,w:24},
    {k:'template',h:'Template',a:['template','templatekpi'],w:18},{k:'indikator',h:'Indikator',a:['indikator','namaindikator'],req:1,w:38},{k:'target',h:'Target',a:['target'],t:'n',w:9},
    {k:'nilai',h:'Nilai',a:['nilai','skor','pencapaian','realisasi'],t:'n',w:9},{k:'catatan',h:'Catatan',a:['catatan','keterangan','komentar'],w:34}],
  cek:x=>{const e=[];if(x.periode&&!/^\d{4}-\d{1,2}|^\d{1,2}[\/.-]\d{4}$|^[A-Za-z]+ \d{4}$/.test(String(x.periode)))e.push('Periode tidak valid (contoh 2026-11)');if(x.nilai!==''&&x.nilai<0)e.push('Nilai tidak boleh negatif');return e},
  msg:r=>`Penilaian KPI tersimpan: ${n0(r.nilai)} nilai untuk ${n0(r.karyawan)} karyawan${r.lewat?` (${n0(r.lewat)} baris kosong dilewati)`:''}`};
window.kpiNilaiTemplate=async function(per){
  const d=await api('kpiNilaiTpl',{periode:per},true);
  if(!d.rows.length)throw new Error('Belum ada karyawan dengan template KPI yang punya indikator "Penilaian atasan". Pasang template KPI ke karyawan dulu.');
  const o=IMPORT_KPI_NILAI,hs={b:1,c:'FFFFFF',bg:X_TEAL,h:'center',v:'center',bb:['thin',X_DEEP],wrap:1},tx={bb:['thin',X_LINE],v:'center',fmt:'@'},gr={bb:['thin',X_LINE],v:'center',c:'5F7773',fmt:'@'},
    nv={bb:['thin',X_LINE],h:'right',v:'center',bg:'FFF8E6',b:1},tg={bb:['thin',X_LINE],h:'right',v:'center',c:'5F7773',fmt:'#,##0'},ct={bb:['thin',X_LINE],v:'center',bg:'FFF8E6',wrap:1};
  const rows=[{h:30,cells:o.cols.map(c=>({v:c.h+(c.k==='nilai'?' ✎':''),s:c.k==='nilai'||c.k==='catatan'?{...hs,bg:'C98A12'}:hs}))}];
  let prev='';d.rows.forEach(r=>{const first=r.kode+r.nama!==prev;prev=r.kode+r.nama;rows.push({h:20,cells:[{v:r.periode,s:gr},{v:r.kode,s:first?tx:gr},{v:r.nama,s:first?{...tx,b:1}:gr},{v:r.template,s:gr},{v:r.indikator,s:tx},{v:r.target,s:tg},{v:r.nilai==null?'':r.nilai,s:nv},{v:r.catatan||'',s:ct}]})});
  const BL=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  const info=[['Petunjuk import penilaian KPI — '+BL[+d.periode.slice(5,7)-1]+' '+d.periode.slice(0,4),{b:1,sz:13,c:X_TEAL}],[''],['• Isi kolom Nilai (kuning) untuk setiap indikator. Skala mengikuti Target (biasanya 100 = sangat baik / sesuai target).'],
    ['• Nilai boleh di atas target (skor maksimal dihitung 120). Nilai kosong = tidak diubah.'],['• Catatan opsional, tampil di detail KPI karyawan.'],['• Jangan ubah kolom Periode, Kode, Nama karyawan, dan Indikator.'],
    ['• Kehadiran, tepat waktu, omzet, transaksi, dan member baru dihitung otomatis oleh Dhyra, jadi tidak ada di file ini.'],['• Setelah diisi: KPI → Hasil KPI → Import nilai Excel → pilih file ini.']];
  const data=xlsxBuild([{name:o.sheet,cols:o.cols.map(c=>c.w),rows,freeze:1,freezeCol:3,selected:true,land:true},{name:'Petunjuk',cols:[110],rows:info.map(r=>({cells:[{v:r[0],s:r[1]||{}}]}))}],'Penilaian KPI '+d.periode,'Penilaian KPI');
  saveFile('penilaian-kpi_'+d.periode+'.xlsx',data,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
};
window.IMPORT_KRY={nama:'karyawan',sheet:'Karyawan',act:'kryImport',contoh:'kryContoh',
  info:'Karyawan dengan kode yang sudah ada diperbarui datanya; yang lain ditambahkan. Akun login dihubungkan lewat kolom Username.',
  cols:[{k:'kode',h:'Kode',a:['kode','kodekaryawan','nik','nip','id'],w:10},{k:'nama',h:'Nama lengkap',a:['nama','namalengkap','namakaryawan','karyawan'],req:1,w:24},{k:'posisi',h:'Posisi',a:['posisi','jabatan','bagian'],w:16},
    {k:'username',h:'Username login',a:['usernamelogin','username','akun','akunlogin','login'],w:15},{k:'jabatanLogin',h:'Jabatan login',a:['jabatanlogin','jabatanakun','hakakses','aksesmenu'],w:20},{k:'password',h:'Password awal',a:['passwordawal','password','katasandi'],w:14},{k:'lokasi',h:'Penempatan',a:['penempatan','lokasi','cabang'],w:15},{k:'hp',h:'No. HP',a:['nohp','hp','telepon','telp','wa','nowa'],w:15},
    {k:'tglMasuk',h:'Tanggal masuk',a:['tanggalmasuk','tglmasuk','mulaikerja','tanggalbergabung'],t:'d',w:14},{k:'shift',h:'Shift',a:['shift'],w:10},{k:'hariKerja',h:'Hari kerja',a:['harikerja','hari','jadwal'],w:13},
    {k:'tipeGaji',h:'Sistem gaji',a:['sistemgaji','tipegaji','jenisgaji'],w:12},{k:'gajiPokok',h:'Gaji pokok / upah harian',a:['gajipokokupahharian','gajipokok','gaji','upah','upahharian'],t:'n',w:16},
    {k:'tunjangan',h:'Tunjangan / bulan',a:['tunjanganbulan','tunjangan'],t:'n',w:14},{k:'uangMakan',h:'Uang makan / hari',a:['uangmakanhari','uangmakan','makan'],t:'n',w:14},
    {k:'lemburJam',h:'Lembur / jam',a:['lemburjam','upahlembur','lembur'],t:'n',w:12},{k:'dendaTelat',h:'Denda telat',a:['dendatelat','denda','potongantelat'],t:'n',w:12},
    {k:'jatahCuti',h:'Jatah cuti',a:['jatahcuti','cuti'],t:'n',w:10},{k:'kpi',h:'Template KPI',a:['templatekpi','kpi'],w:16},{k:'bank',h:'Bank',a:['bank'],w:10},{k:'rekening',h:'No. rekening',a:['norekening','rekening','norek'],w:16},
    {k:'email',h:'Email',a:['email','surel'],w:20},{k:'alamat',h:'Alamat',a:['alamat'],w:26},{k:'atasan',h:'Atasan langsung',a:['atasanlangsung','atasan','supervisor','penyetuju'],w:18}],
  sample:[['K-0101','Rina Marlina','Kasir','rina','Kasir','','Toko Utama','081234560001',(Y-1)+'-05-02','Pagi','Sen-Sab','Bulanan',2800000,200000,15000,18000,10000,12,'KPI Kasir','BCA','1234567801','','','K-0104'],
    ['K-0102','Sari Wulandari','Kasir','sari','Kasir','','Toko Utama','081234560002',Y+'-03-01','Siang','Sel-Min','Bulanan',2800000,200000,15000,18000,10000,12,'KPI Kasir','BRI','1234567802','','','K-0104'],
    ['K-0103','Dedi Kurniawan','Staf gudang','dedi','Staf Gudang','','Gudang Utama','081234560003',(Y-1)+'-11-15','Pagi','Sen-Sab','Harian',110000,0,0,15000,5000,12,'KPI Staf Gudang','','','','','K-0104'],
    ['K-0104','Andi Pratama','Kepala toko','andi','Kepala Toko','','Toko Utama','081234560004',(Y-3)+'-01-10','Pagi','Sen-Jum','Bulanan',4500000,750000,20000,0,0,12,'','Mandiri','1234567804','','',''],
    ['K-0105','Maya Sapitri','Admin & keuangan','maya','Akuntan','','Toko Utama','081234560005',(Y-1)+'-08-01','Pagi','Sen-Jum','Bulanan',3500000,300000,15000,20000,10000,12,'','BCA','1234567805','','','K-0104']],
  tips:['Username login: bila akun belum ada, Dhyra langsung membuatkannya (khusus Administrator) dengan Jabatan login & Password awal di kolom sebelahnya. Kosongkan username bila tidak perlu akun.','Jabatan login: Kasir, Staf Gudang, Kepala Toko, Akuntan, Karyawan (absen saja), dll. Kosong = ditebak dari Posisi.','Password awal: minimal 6 karakter. Kosong = dibuatkan otomatis; daftar username & password tampil setelah import untuk dicetak / dibagikan.','Shift diisi nama shift (mis. Pagi, Siang). Kosong = shift pertama.',
    'Hari kerja: Sen-Sab, Sel-Min, Sen-Jum, "Setiap hari", atau angka 123456 (1 = Senin … 7 = Minggu).','Sistem gaji: Bulanan (gaji pokok per bulan) atau Harian (upah per hari hadir).',
    'Template KPI diisi nama template (mis. KPI Kasir). Kosong = tanpa KPI.','Atasan langsung: kode atau nama karyawan yang menyetujui izin, cuti & lembur (boleh karyawan di baris lain pada file yang sama). Kosong = disetujui bagian SDM.']};
})();

/* ============================================================
 *  Akun login karyawan — dibuat massal, ditampilkan sekali untuk dicetak / dibagikan
 * ============================================================ */
function showAkun(list){
  if(!window.XLR)return;
  const m=modal({title:'Akun login siap dibagikan',cls:'lg',sticky:1,body:`<div class="hint" style="background:var(--warnS);color:var(--warn);margin:0 0 12px">Password hanya ditampilkan <b>sekali ini</b>. Cetak kartu akun atau unduh daftarnya sekarang.</div>${XLR.akunTable(list)}
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">${list.map((a,i)=>`<a class="btn sm" target="_blank" rel="noopener" data-wa="${i}">${ic('ext')}WA ${esc(a.nama.split(' ')[0])}</a>`).join('')}</div>`,
    foot:`<button class="btn" data-act="mclose">Tutup</button><button class="btn" id="akc">${ic('dl')}Unduh daftar</button><button class="btn pri" id="akp">${ic('print')}Cetak kartu akun</button>`});
  $$('[data-wa]',m).forEach(b=>{const a=list[+b.dataset.wa],k=(PS.sd&&PS.sd.kry||[]).find(x=>x.nama===a.nama),hp=k&&k.hp?String(k.hp).replace(/\D/g,'').replace(/^0/,'62'):'';b.href='https://wa.me/'+hp+'?text='+encodeURIComponent(XLR.akunWa(a))});
  $('#akc',m).onclick=()=>XLR.akunCsv(list);$('#akp',m).onclick=()=>XLR.akunPrint(list,S.set.namaToko);
}
window.showAkun=showAkun;
const JPOS=p=>{p=String(p||'').toLowerCase();for(const [k,j] of [['kasir','JKASIR'],['gudang','JGUDANG'],['kepala','JKEPALA'],['manajer','JKEPALA'],['supervisor','JKEPALA'],['akunt','JAKUNTAN'],['keuangan','JAKUNTAN']])if(p.includes(k))return j;return 'JSTAF'};
ACT.xkyakun=async b=>{
  let U,K;try{[U,K]=await Promise.all([DH.sys('users'),api('kryList',{},true)])}catch(e){return toast(e.message,'err')}
  const tanpa=K.filter(k=>k.status==='Aktif'&&!k.user);
  if(!tanpa.length)return toast('Semua karyawan aktif sudah punya akun login','ok');
  const used=new Set(U.users.map(u=>u.username));const sug=n=>{let b=(n.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').split(/\s+/)[0]||'').replace(/[^a-z0-9]/g,'');if(b.length<3)b='staf'+b;let u=b,i=1;while(used.has(u))u=b+(++i);used.add(u);return u};
  const jo=sel=>U.jabatan.map(j=>`<option value="${j.id}" ${j.id===sel?'selected':''}>${esc(j.nama)}</option>`).join('');
  const m=modal({title:'Buat akun login karyawan',cls:'xl',body:`<p class="muted sm" style="margin-top:0">${tanpa.length} karyawan aktif belum punya akun. Username & jabatan sudah disarankan dari nama dan posisi — ubah bila perlu. Password dibuatkan otomatis (atau isi sendiri).</p>
    <div class="card np"><div class="tw" style="max-height:52vh"><table class="tbl"><thead><tr><th style="width:36px"><input type="checkbox" id="aka" checked></th><th>Karyawan</th><th>Username</th><th>Jabatan / hak akses</th><th>Password awal</th></tr></thead><tbody>${tanpa.map(k=>`<tr data-k="${k.id}"><td class="ck"><input type="checkbox" checked></td><td><b>${esc(k.nama)}</b><div class="muted sm">${esc(k.kode)} · ${esc(k.posisi||'-')}</div></td>
      <td><input class="in mono" data-f="u" value="${esc(sug(k.nama))}" style="min-width:130px"></td><td><select class="in" data-f="j">${jo(JPOS(k.posisi))}</select></td><td><input class="in mono" data-f="p" placeholder="otomatis" style="min-width:120px"></td></tr>`).join('')}</tbody></table></div></div>
    <p class="muted sm">Jabatan <b>Karyawan (absen saja)</b>: bisa absen, ajukan izin/cuti, dan lihat slip gaji, tanpa akses ke modul lain.</p>`,
    foot:`<button class="btn" data-act="mclose">Batal</button><button class="btn pri" id="akgo">${ic('check')}Buat akun terpilih</button>`});
  $('#aka',m).onchange=e=>$$('tbody input[type=checkbox]',m).forEach(c=>c.checked=e.target.checked);
  $('#akgo',m).onclick=async ev=>{const items=$$('tbody tr',m).filter(r=>$('input[type=checkbox]',r).checked).map(r=>({karyawan:r.dataset.k,username:$('[data-f=u]',r).value.trim(),jabatan:$('[data-f=j]',r).value,password:$('[data-f=p]',r).value.trim()}));
    if(!items.length)return toast('Centang minimal satu karyawan','err');const bt=ev.currentTarget;bt.disabled=true;
    try{const r=await DH.sys('akunKaryawan',{items});m.remove();toast(r.baru+' akun login dibuat','ok');if(S.route==='sdmkry')PAGES.sdmkry();if(r.akun.length)showAkun(r.akun)}catch(e){toast(e.message,'err')}finally{bt.disabled=false}};
};
