'use strict';
const fs=require('fs');
const vm=require('vm');
const assert=require('assert/strict');
const listeners={};let closed=0,added=0,removed=0,changed=null;
const window={close(){closed++;},addBrandColor(){added++;},toinkyPBSelectSync(value){changed=value;}};
const document={addEventListener(type,fn){assert(!listeners[type],'Handlers must bind only once');listeners[type]=fn;}};
const context=vm.createContext({window,document});
const source=fs.readFileSync(__dirname+'/../public/assets/toinky-actions.js','utf8');
vm.runInContext(source,context);vm.runInContext(source,context);
const overlay={hidden:true};
function control(attribute){return {hasAttribute(name){return name===attribute;},closest(){return attribute==='data-brand-color-remove'?{remove(){removed++;}}:overlay;},nextElementSibling:overlay};}
function click(attribute,nested=false){const button=control(attribute);const target=nested?{closest(){return button;}}:Object.assign(button,{closest(selector){return selector.startsWith('[data-')?button:overlay;}});listeners.click({target});}
click('data-window-close',true);assert.equal(closed,1);
click('data-brand-color-add',true);assert.equal(added,1);
const rowButton=control('data-brand-color-remove');listeners.click({target:{closest(){return rowButton;}}});assert.equal(removed,1);
click('data-media-confirm-open');assert.equal(overlay.hidden,false);
const backdrop={hidden:false,hasAttribute(name){return name==='data-media-confirm-backdrop';}};
listeners.click({target:{closest(){return backdrop;}}});assert.equal(backdrop.hidden,false,'Clicks inside dialog must not dismiss it');
backdrop.closest=()=>backdrop;listeners.click({target:backdrop});assert.equal(backdrop.hidden,true);
overlay.hidden=false;click('data-media-confirm-close',true);assert.equal(overlay.hidden,true);
const select={};listeners.change({target:{closest(){return select;}}});assert.equal(changed,select);
listeners.click({target:{closest(){return null;}}});
console.log('PASS: Authored actions preserve nested clicks, modal dismissal, row editing and select synchronization.');
