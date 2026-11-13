'use strict';
// Exercise the shipped polling script with a deterministic DOM/fetch fixture.
// This is not a substitute for live browser verification after deployment.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/play.php'), 'utf8');
const start = source.indexOf('let liveBusy=');
assert.notEqual(start, -1);
assert.ok(source.slice(source.lastIndexOf('<script', start), start).includes('SecurityHeaders::nonce()'));
const script = source.slice(start, source.indexOf('</script>', start)).replaceAll('<?=$gid?>', '123');
let checks = 0;
function fixture() {
    const listeners = {}, windowListeners = {};
    const counts = {fetch: 0, replace: 0, clear: 0, notices: 0};
    let now = 10000, error = false, resolveFetch;
    const current = {dataset: {status: 'RUNNING'}, querySelectorAll: () => [], querySelector: () => error ? {} : null,
        replaceWith: () => { counts.replace++; }};
    const next = {dataset: {status: 'RUNNING'}, querySelectorAll: () => []};
    const document = {hidden: false, activeElement: null, querySelectorAll: () => [],
        querySelector: selector => selector === '#game-live' ? current : (selector === '[data-choice-error]' && error ? {} : null),
        addEventListener: (event, handler) => { listeners[event] = handler; },
        createElement: () => ({dataset: {}, setAttribute() {}, textContent: ''})};
    const window = {scrollY: 50, scrollTo() {}, addEventListener: (event, handler) => { windowListeners[event] = handler; }};
    const context = vm.createContext({document, window, Date: {now: () => now}, Object, JSON,
        sessionStorage: {getItem: () => '{}', setItem() {}},
        setInterval: () => 1, clearInterval: () => { counts.clear++; }, setTimeout() {},
        DOMParser: class { parseFromString() { return {querySelector: () => next}; } },
        fetch: () => { counts.fetch++; return new Promise(resolve => {resolveFetch = () => resolve({ok: true, text: async () => '<html>'});}); }});
    vm.runInContext(script, context);
    return {context, counts, document, listeners, windowListeners, next,
        refresh: () => vm.runInContext('refreshLiveGame()', context),
        advance: () => {now += 2000;}, error: value => {error = value;},
        finish: () => {assert.ok(resolveFetch); resolveFetch();},
        form: {matches: () => true, querySelector: () => ({value: 'decide'}), setAttribute() {}, appendChild(node) {counts.notices++; counts.status = node.textContent;}}};
}
async function test(label, run) { await run(); checks++; console.log('PASS ' + label); }
(async () => {
    await test('ordinary refresh still replaces the story', async () => {
        const f = fixture(), pending = f.refresh(); f.finish(); await pending;
        assert.equal(f.counts.replace, 1);
    });
    await test('pointer interaction prevents a new poll', async () => {
        const f = fixture(); f.listeners.pointerdown({target: {closest: () => ({})}});
        await f.refresh(); assert.equal(f.counts.fetch, 0);
    });
    await test('in-flight refresh cannot replace controls after pointerdown', async () => {
        const f = fixture(), pending = f.refresh();
        f.listeners.pointerdown({target: {closest: () => ({})}});
        f.finish(); await pending; assert.equal(f.counts.replace, 0);
    });
    await test('keyboard interaction receives the same protection', async () => {
        const f = fixture(), pending = f.refresh();
        f.listeners.keydown({target: {closest: () => ({})}});
        f.finish(); await pending; assert.equal(f.counts.replace, 0);
    });
    await test('focused choice form stays stable until focus leaves', async () => {
        const f = fixture(); f.document.activeElement = {closest: () => f.form};
        await f.refresh(); assert.equal(f.counts.fetch, 0);
        f.document.activeElement = null;
        const pending = f.refresh(); f.finish(); await pending; assert.equal(f.counts.replace, 1);
    });
    await test('polling resumes after a brief interaction ends', async () => {
        const f = fixture(); f.listeners.pointerdown({target: {closest: () => ({})}});
        f.advance(); const pending = f.refresh(); f.finish(); await pending; assert.equal(f.counts.replace, 1);
    });
    await test('submit shows progress and suppresses duplicate posts', async () => {
        const f = fixture(); let prevented = 0;
        const event = {target: f.form, preventDefault() {prevented++;}};
        f.listeners.submit(event); f.listeners.submit(event);
        assert.equal(f.counts.notices, 1); assert.equal(prevented, 1);
        await f.refresh(); assert.equal(f.counts.fetch, 0);
    });
    await test('solo continue shows progress and receives polling protection', async () => {
        const f = fixture(); f.form.querySelector = () => ({value: 'advance_solo'});
        f.listeners.submit({target: f.form, preventDefault() {}});
        assert.equal(f.counts.status, 'Continuing the story…');
        await f.refresh(); assert.equal(f.counts.fetch, 0);
    });
    await test('unrelated forms retain native submission', async () => {
        const f = fixture(); let prevented = false;
        f.listeners.submit({target: {matches: () => false}, preventDefault() {prevented = true;}});
        assert.equal(prevented, false);
        const pending = f.refresh(); f.finish(); await pending; assert.equal(f.counts.replace, 1);
    });
    await test('server error remains visible instead of disappearing on poll', async () => {
        const f = fixture(); f.error(true); await f.refresh(); assert.equal(f.counts.fetch, 0);
    });
    await test('pageshow releases a submission lock after back navigation', async () => {
        const f = fixture(); f.listeners.submit({target: f.form, preventDefault() {}});
        f.windowListeners.pageshow(); const pending = f.refresh(); f.finish(); await pending;
        assert.equal(f.counts.replace, 1);
    });
    await test('completion still stops the polling timer', async () => {
        const f = fixture(); f.next.dataset.status = 'COMPLETE';
        const pending = f.refresh(); f.finish(); await pending; assert.equal(f.counts.clear, 1);
    });
    console.log(`Player interaction: ${checks} checks passed.`);
})().catch(error => {console.error(error); process.exitCode = 1;});
