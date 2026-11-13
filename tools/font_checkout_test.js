'use strict';
const fs=require('node:fs');
const path=require('node:path');
const os=require('node:os');
const cp=require('node:child_process');
const crypto=require('node:crypto');
const assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..');
const manifest=JSON.parse(fs.readFileSync(path.join(root,'public/assets/fonts/manifest.json'),'utf8'));
const pixi=JSON.parse(fs.readFileSync(path.join(root,'public/assets/vendor/pixi-csp.source.json'),'utf8'));
const files=Object.entries(manifest.sources).map(([file,asset])=>['public/assets/fonts/'+file,asset.sha256]);
files.push(['public/assets/vendor/pixi-csp.min.js',pixi.sha256]);
const tempRoot=path.resolve(os.tmpdir());
const directory=fs.mkdtempSync(path.join(tempRoot,'toinky-asset-checkout-'));
const gitDir=cp.execFileSync('git',['rev-parse','--absolute-git-dir'],{cwd:root,encoding:'utf8'}).trim();
const git=args=>cp.execFileSync('git',['-c','core.autocrlf=true','--git-dir='+gitDir,'--work-tree='+directory,...args],{cwd:root,stdio:'pipe'});
try{
  // Use the real index and a disposable work tree. Never mutate the source index.
  git(['checkout-index','--force','--','.gitattributes']);
  git(['checkout-index','--force','--',...files.map(([file])=>file)]);
  for(const [file,expected] of files){
    const bytes=fs.readFileSync(path.join(directory,file));
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'),expected,'Windows-style checkout changed '+file);
  }
  console.log('PASS: '+files.length+' vendored font, license and Pixi assets retain verified bytes with core.autocrlf=true.');
}finally{
  const target=path.resolve(directory);
  assert(target.startsWith(tempRoot+path.sep)&&path.basename(target).startsWith('toinky-asset-checkout-'),'Cleanup must remain inside the test temp directory');
  fs.rmSync(target,{recursive:true,force:true});
}
