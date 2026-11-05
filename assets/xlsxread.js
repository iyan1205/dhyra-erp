/* ============================================================
 *  Dhyra — pembaca Excel (.xlsx/.csv) mandiri & kartu akun login
 *  Dipakai halaman yang tidak memuat kasir.html (mis. Pengguna & Akses).
 * ============================================================ */
(function(W){
'use strict';
const pn=s=>{s=String(s==null?'':s).replace(/\./g,'').replace(',','.');const n=parseFloat(s);return isNaN(n)?0:n};
async function unzipEntries(buf){
  const u=new Uint8Array(buf),dv=new DataView(buf);let e=-1;
  for(let i=u.length-22;i>=Math.max(0,u.length-70000);i--){if(dv.getUint32(i,true)===0x06054b50){e=i;break}}
  if(e<0)throw new Error('File bukan .xlsx yang valid');
  const n=dv.getUint16(e+10,true);let p=dv.getUint32(e+16,true);const out={},dec=new TextDecoder();
  for(let k=0;k<n;k++){
    if(dv.getUint32(p,true)!==0x02014b50)break;
    const meth=dv.getUint16(p+10,true),csz=dv.getUint32(p+20,true),nl=dv.getUint16(p+28,true),xl=dv.getUint16(p+30,true),cl=dv.getUint16(p+32,true),off=dv.getUint32(p+42,true);
    const name=dec.decode(u.subarray(p+46,p+46+nl));p+=46+nl+xl+cl;
    out[name]={meth,off,csz};
  }
  out.__read=async name=>{
    const f=out[name];if(!f)return null;
    const ln=dv.getUint16(f.off+26,true),lx=dv.getUint16(f.off+28,true),st=f.off+30+ln+lx,data=u.subarray(st,st+f.csz);
    if(f.meth===0)return dec.decode(data);
    if(f.meth!==8)throw new Error('Kompresi file tidak didukung');
    if(typeof DecompressionStream==='undefined')throw new Error('Browser ini belum mendukung baca Excel. Gunakan Chrome/Edge terbaru, atau simpan file sebagai CSV.');
    const ds=new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
    return await new Response(ds).text();
  };
  return out;
}
async function readXlsx(buf){
  const z=await unzipEntries(buf),X=t=>new DOMParser().parseFromString(t,'application/xml');
  const tag=(el,n)=>Array.from(el.getElementsByTagName(n));
  let sheetPath='xl/worksheets/sheet1.xml';
  const wb=await z.__read('xl/workbook.xml'),rel=await z.__read('xl/_rels/workbook.xml.rels');
  if(wb&&rel){
    const sh=tag(X(wb),'sheet')[0];
    const rid=sh&&(sh.getAttribute('r:id')||sh.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id'));
    const r=tag(X(rel),'Relationship').find(x=>x.getAttribute('Id')===rid);
    if(r){let t=r.getAttribute('Target');t=t.replace(/^\/?xl\//,'').replace(/^\//,'');sheetPath='xl/'+t}
  }
  const ssT=await z.__read('xl/sharedStrings.xml'),ss=ssT?tag(X(ssT),'si').map(si=>tag(si,'t').map(t=>t.textContent).join('')):[];
  const shT=await z.__read(sheetPath);if(!shT)throw new Error('Sheet pertama tidak ditemukan di file');
  const col=ref=>{let c=0;for(const ch of ref.replace(/\d+/g,''))c=c*26+(ch.charCodeAt(0)-64);return c-1};
  const rows=[];
  tag(X(shT),'row').forEach(r=>{
    const ri=(+r.getAttribute('r')||rows.length+1)-1,arr=[];
    tag(r,'c').forEach((c,ci)=>{
      const ref=c.getAttribute('r'),i=ref?col(ref):ci,t=c.getAttribute('t'),v=tag(c,'v')[0];let val='';
      if(t==='s')val=v?(ss[+v.textContent]||''):'';
      else if(t==='inlineStr')val=tag(c,'t').map(x=>x.textContent).join('');
      else if(t==='str'||t==='e')val=v?v.textContent:'';
      else if(t==='b')val=v?v.textContent==='1':'';
      else val=v?{n:Number(v.textContent)}:'';
      arr[i]=val;
    });
    rows[ri]=arr;
  });
  return Array.from(rows,r=>r||[]);
}
function readCsv(text){
  text=text.replace(/^﻿/,'');const first=text.split(/\r?\n/)[0]||'';
  const d=[';','\t',','].map(x=>[x,first.split(x).length]).sort((a,b)=>b[1]-a[1])[0][0];
  const rows=[];let row=[],f='',q=false;
  for(let i=0;i<text.length;i++){const c=text[i];
    if(q){if(c==='"'){if(text[i+1]==='"'){f+='"';i++}else q=false}else f+=c}
    else if(c==='"')q=true;else if(c===d){row.push(f);f=''}else if(c==='\n'||c==='\r'){if(c==='\r'&&text[i+1]==='\n')i++;row.push(f);rows.push(row);row=[];f=''}else f+=c}
  if(f||row.length){row.push(f);rows.push(row)}
  return rows;
}
const cellStr=v=>v==null?'':typeof v==='object'?(Number.isInteger(v.n)?String(v.n):String(v.n)):typeof v==='boolean'?(v?'1':'0'):String(v).trim();
const cellNum=v=>{if(v==null||v==='')return null;if(typeof v==='object')return v.n;const s=String(v).replace(/rp/ig,'').replace(/[^\d.,-]/g,'');if(!s)return null;
  if(/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)||/,\d{1,3}$/.test(s)&&!/\.\d{1,2}$/.test(s))return pn(s);return parseFloat(s.replace(/,/g,''))};

const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function read(file){if(/\.csv$/i.test(file.name))return readCsv(await file.text());return readXlsx(await file.arrayBuffer())}
/* ---- daftar akun hasil pembuatan massal ---- */
const appUrl=()=>location.href.replace(/[^/]*([?#].*)?$/,'');
function akunTable(list){return `<div style="max-height:46vh;overflow:auto;border:1px solid #E2E6E1;border-radius:12px"><table style="width:100%;border-collapse:collapse;font-size:14px"><thead><tr style="background:#F0F2EE;text-align:left"><th style="padding:9px 12px">Nama</th><th style="padding:9px 12px">Username</th><th style="padding:9px 12px">Password</th><th style="padding:9px 12px">Jabatan</th><th style="padding:9px 12px">Keterangan</th></tr></thead><tbody>${list.map(a=>`<tr style="border-top:1px solid #E2E6E1"><td style="padding:8px 12px"><b>${esc(a.nama)}</b></td><td style="padding:8px 12px;font-family:monospace">${esc(a.username)}</td><td style="padding:8px 12px;font-family:monospace">${esc(a.password)}</td><td style="padding:8px 12px">${esc(a.jabatan)}</td><td style="padding:8px 12px;color:#5B6870;font-size:13px">${esc(a.status||'')}</td></tr>`).join('')}</tbody></table></div>`}
function akunCsv(list){const rows=[['Nama','Username','Password','Jabatan','Alamat login'],...list.map(a=>[a.nama,a.username,a.password,a.jabatan,appUrl()+'beranda.html'])];
  const csv='\ufeffsep=;\r\n'+rows.map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(';')).join('\r\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));a.download='akun-login-dhyra-'+new Date().toISOString().slice(0,10)+'.csv';document.body.appendChild(a);a.click();a.remove()}
function akunPrint(list,toko){
  const url=appUrl()+'beranda.html',ab=appUrl()+'absen.html';
  const html=`<!doctype html><html><head><meta charset="utf-8"><title>Akun login Dhyra</title><style>@page{size:A4;margin:10mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;color:#14212B}
   .g{display:grid;grid-template-columns:1fr 1fr;gap:6mm}.c{border:1px dashed #9aa;border-radius:3mm;padding:5mm;break-inside:avoid}.c h3{margin:0 0 1mm;font-size:13pt}.t{color:#0B7A6D;font-weight:bold;font-size:9pt;letter-spacing:.08em}
   table{width:100%;font-size:11pt;margin:3mm 0}td{padding:1mm 0}td:first-child{color:#5B6870;width:28mm}.m{font-family:'Courier New',monospace;font-weight:bold;font-size:13pt}.f{font-size:8.5pt;color:#5B6870;line-height:1.4}</style></head><body><div class="g">${list.map(a=>`<div class="c"><div class="t">${esc(toko||'DHYRA')} · AKUN LOGIN</div><h3>${esc(a.nama)}</h3>
   <table><tr><td>Username</td><td class="m">${esc(a.username)}</td></tr><tr><td>Password</td><td class="m">${esc(a.password)}</td></tr><tr><td>Jabatan</td><td>${esc(a.jabatan)}</td></tr></table>
   <div class="f">Masuk di: <b>${esc(url)}</b><br>Absen dari HP: <b>${esc(ab)}</b><br>Segera ganti password setelah login pertama (menu akun → Ganti password). Jangan berikan ke orang lain.</div></div>`).join('')}</div><script>onload=()=>setTimeout(()=>print(),300)<\/script></body></html>`;
  const w=W.open('','_blank');if(!w){alert('Izinkan pop-up untuk mencetak');return}w.document.write(html);w.document.close();
}
function akunWa(a){return 'Halo '+a.nama+', berikut akun Dhyra Anda:\nUsername: '+a.username+'\nPassword: '+a.password+'\nMasuk: '+appUrl()+'beranda.html\nAbsen dari HP: '+appUrl()+'absen.html\nSilakan ganti password setelah login pertama.'}
W.XLR={read,cellStr,cellNum,akunTable,akunCsv,akunPrint,akunWa,esc};
})(window);
