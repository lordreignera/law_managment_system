const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = name => fs.readFileSync(path.join(__dirname, '../../public/admin/assets/js', name), 'utf8');

function element(properties = {}) {
    const listeners = {};
    return Object.assign({
        dataset: {}, hidden: false, disabled: false, textContent: '', attributes: {},
        addEventListener(name, handler) { listeners[name] = handler; },
        fire(name, event = {}) { return listeners[name]?.({ preventDefault() { this.prevented = true; }, ...event }); },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        focus() { this.focused = true; },
    }, properties);
}

function verification(fetch, initialCooldown = 0) {
    let ready; let tick; let now = 100000;
    const button = element(); const status = element(); const reload = element({ hidden: true });
    const form = element({ action: '/email/verification-notification', dataset: { retryAfter: initialCooldown }, querySelector: () => button });
    const window = { setInterval(handler) { tick = handler; return 1; }, clearInterval() {}, location: { assign(url) { this.destination = url; } } };
    const document = {
        addEventListener(name, handler) { ready = handler; }, getElementById: () => element(),
        querySelector(selector) { return { '[data-verification-resend]': form, '[data-verification-status]': status, '[data-verification-reload]': reload }[selector]; },
    };
    vm.runInNewContext(source('kfms-verification.js'), { document, window, fetch, Date: { now: () => now }, FormData: class {} });
    ready();
    return { form, button, status, reload, window, advance(seconds) { now += seconds * 1000; tick?.(); } };
}
const response = (status, payload = {}, headers = {}) => ({ status, ok: status < 400, redirected: false, json: async () => payload, headers: { get: name => headers[name] } });

test('verification resend prevents repeated clicks and enables retry after cooldown', async () => {
    let finish; let requests = 0;
    const app = verification(() => { requests++; return new Promise(resolve => { finish = resolve; }); }, 60);
    assert.equal(app.button.disabled, true);
    await app.form.fire('submit'); assert.equal(requests, 0);
    app.advance(61); assert.equal(app.button.disabled, false);
    const pending = app.form.fire('submit');
    await app.form.fire('submit'); assert.equal(requests, 1);
    finish(response(200, { message: 'A new verification link has been sent.', retry_after: 60 }));
    await pending;
    assert.equal(app.status.dataset.state, 'success'); assert.equal(app.button.disabled, true);
    app.advance(60); assert.equal(app.button.disabled, false);
});

test('verification resend displays delivery failure and keeps retry available', async () => {
    const app = verification(async () => response(503, { message: 'Email service unavailable. Please try again.' }));
    await app.form.fire('submit');
    assert.equal(app.status.dataset.state, 'error'); assert.match(app.status.textContent, /service unavailable/);
    assert.equal(app.button.disabled, false);
});

test('verification resend recovers expired sessions without resubmitting the request', async () => {
    let requests = 0;
    const app = verification(async () => { requests++; return response(419); });
    await app.form.fire('submit');
    assert.equal(requests, 1); assert.equal(app.reload.hidden, false);
    assert.match(app.status.textContent, /session has expired/); assert.equal(app.status.dataset.state, 'error');
});

test('verification resend uses server rate-limit feedback and network failure feedback', async () => {
    const app = verification(async () => response(429, { message: 'Please wait.' }, { 'Retry-After': '30' }));
    await app.form.fire('submit'); assert.equal(app.button.textContent, 'Resend in 30s');
    app.advance(30); assert.equal(app.button.disabled, false);
    const offline = verification(async () => { throw new Error('Offline'); });
    await offline.form.fire('submit'); assert.match(offline.status.textContent, /connection/); assert.equal(offline.button.disabled, false);
});

function layout() {
    let ready; let resize; let keydown;
    const classes = new Set(); const body = { classList: { toggle(name, state) { state ? classes.add(name) : classes.delete(name); } } };
    const toggle = element(); const close = element(); const backdrop = element(); const workspace = element(); const link = element();
    const sidebar = element({ querySelector: () => close, querySelectorAll: selector => selector === 'a' ? [link] : [close, link] });
    const media = { matches: true, addEventListener(name, handler) { resize = handler; } };
    const document = {
        body, activeElement: null,
        addEventListener(name, handler) { if (name === 'DOMContentLoaded') ready = handler; else if (name === 'keydown') keydown = handler; },
        getElementById: () => sidebar,
        querySelector(selector) { return { '[data-client-menu-toggle]': toggle, '.kfms-client-backdrop': backdrop, '.kfms-client-workspace': workspace }[selector]; },
        querySelectorAll: () => [close, backdrop],
    };
    [toggle, close, link].forEach(node => { node.focus = () => { document.activeElement = node; }; });
    const window = { matchMedia: () => media, addEventListener() {} };
    vm.runInNewContext(source('kfms-client-layout.js'), { document, window }); ready();
    return { toggle, close, backdrop, sidebar, workspace, media, classes, document, link, resize: () => resize(), keydown: event => keydown({ preventDefault() {}, ...event }) };
}

test('mobile client navigation opens, traps focus, and closes using Escape and backdrop', () => {
    const app = layout();
    assert.equal(app.sidebar.inert, true); assert.equal(app.backdrop.hidden, true);
    app.toggle.fire('click');
    assert.equal(app.toggle.attributes['aria-expanded'], 'true'); assert.equal(app.sidebar.inert, false);
    assert.equal(app.workspace.inert, true); assert.equal(app.backdrop.hidden, false); assert.equal(app.document.activeElement, app.close);
    app.keydown({ key: 'Tab', shiftKey: true }); assert.equal(app.document.activeElement, app.link);
    app.keydown({ key: 'Tab', shiftKey: false }); assert.equal(app.document.activeElement, app.close);
    app.keydown({ key: 'Escape' });
    assert.equal(app.sidebar.inert, true); assert.equal(app.workspace.inert, false); assert.equal(app.document.activeElement, app.toggle);
    app.toggle.fire('click'); app.backdrop.fire('click'); assert.equal(app.backdrop.hidden, true);
});

test('desktop resize resets the mobile drawer and restores accessible navigation', () => {
    const app = layout(); app.toggle.fire('click');
    app.media.matches = false; app.resize();
    assert.equal(app.sidebar.inert, false); assert.equal(app.workspace.inert, false);
    assert.equal(app.backdrop.hidden, true); assert.equal(app.classes.has('kfms-client-menu-open'), false);
    assert.equal(app.toggle.attributes['aria-expanded'], 'false');
});

test('profile resend countdown expires after a background tab resumes', () => {
    const view = fs.readFileSync(path.join(__dirname, '../../resources/views/profile/update-profile-information-form.blade.php'), 'utf8');
    const expression = view.match(/data-profile-verification x-data="([^"]+)"/)[1]
        .split('@js($verificationRetryAfter)').join('60');
    let now = 100000; let tick; let cleared = false;
    const widget = vm.runInNewContext('(' + expression + ')', {
        Date: { now: () => now }, Math,
        setInterval(handler) { tick = handler; return 1; },
        clearInterval() { cleared = true; },
    });
    widget.init();
    assert.equal(widget.remaining, 60);
    now += 90000; tick();
    assert.equal(widget.remaining, 0);
    widget.destroy(); assert.equal(cleared, true);
});
