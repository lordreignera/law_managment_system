const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = name => fs.readFileSync(path.join(__dirname, '../../public/admin/assets/js', name), 'utf8');
function element(properties = {}) {
    const listeners = {};
    return Object.assign({
        dataset: {}, hidden: false, checked: false, disabled: false, value: '', textContent: '',
        addEventListener: (name, listener) => { listeners[name] = listener; },
        fire: (name, event = {}) => listeners[name]?.({ preventDefault() { this.prevented = true; }, ...event }),
        focus() { this.focused = true; },
    }, properties);
}
function workspace(options = {}) {
    const ready = {};
    const storage = new Map([['kfms.workspace.history.7', JSON.stringify(options.history || [])]]);
    const back = element({ href: '/dashboard' });
    const window = {
        location: new URL(options.url || 'http://localhost:82/settings/system'),
        sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        addEventListener: (name, listener) => { ready[name] = listener; },
    };
    window.history = { state: null, replaceState: (state, title, url) => { window.location = new URL(url, window.location.origin); } };
    const document = {
        body: { dataset: { workspaceUser: '7' } },
        addEventListener: (name, listener) => { ready[name] = listener; },
        querySelector: selector => ({ '[data-settings-search]': options.searchForm, '[data-bulk-approvals]': options.bulk, '[data-workspace-back]': back, '[data-settings-empty]': options.empty })[selector] || null,
        querySelectorAll: () => options.cards || [],
        getElementById: () => options.status,
    };
    vm.runInNewContext(source('kfms-workspace.js'), { document, window, URL });
    ready.DOMContentLoaded();
    return { window, back, ready, storage };
}

test('settings filter starts with the first letter, searches phrases, and restores all cards when cleared', () => {
    const input = element();
    const clear = element();
    const status = element();
    const empty = element();
    const cards = [
        element({ dataset: { settingTitle: 'banks', settingSearch: 'banks approved bank records' } }),
        element({ dataset: { settingTitle: 'bank branches', settingSearch: 'bank branches office locations' } }),
        element({ dataset: { settingTitle: 'courts', settingSearch: 'courts bank litigation venues' } }),
    ];
    const searchForm = element({ querySelector: selector => selector.includes('clear') ? clear : input });
    const app = workspace({ searchForm, cards, status, empty });
    input.value = 'B'; input.fire('input');
    assert.deepEqual(cards.map(card => card.hidden), [false, false, true]);
    assert.equal(status.textContent, '2 settings found');
    assert.equal(app.window.location.search, '?search=B');
    input.value = 'office'; input.fire('input');
    assert.deepEqual(cards.map(card => card.hidden), [true, false, true]);
    input.value = 'no such setting'; input.fire('input');
    assert.equal(empty.hidden, false);
    clear.fire('click');
    assert.deepEqual(cards.map(card => card.hidden), [false, false, false]);
    assert.equal(app.window.location.search, '');
    assert.equal(input.focused, true);
});

test('bulk selection chooses five or ten eligible requests and excludes incomplete requests', () => {
    const users = Array.from({ length: 17 }, (_, index) => element({ disabled: index === 1 || index === 4 }));
    const all = element(); const count = element(); const submit = element();
    const five = element({ dataset: { bulkFirst: '5' } });
    const ten = element({ dataset: { bulkFirst: '10' } });
    const form = element();
    const bulk = {
        querySelector: selector => ({ '#bulk-approvals': form, '[data-bulk-all]': all, '[data-bulk-count]': count, '[data-bulk-submit]': submit })[selector],
        querySelectorAll: selector => selector.includes('first') ? [five, ten] : users,
    };
    const app = workspace({ bulk });
    assert.equal(submit.disabled, true);
    five.fire('click');
    assert.equal(users.filter(user => user.checked).length, 5);
    ten.fire('click');
    assert.equal(users.filter(user => user.checked).length, 10);
    assert.equal(users[1].checked, false);
    assert.equal(users[4].checked, false);
    assert.equal(all.indeterminate, true);
    all.checked = true; all.fire('change');
    assert.equal(users.filter(user => user.checked).length, 15);
    assert.equal(count.textContent, '15 selected on this page');
    form.fire('submit');
    assert.equal(submit.textContent, 'Approving...');
    app.ready.pageshow();
    assert.equal(submit.disabled, false);
    all.checked = false; all.fire('change');
    assert.equal(submit.disabled, true);
});

test('Back restores filtered pages and removes later history when returning', () => {
    const app = workspace({ url: 'http://localhost:82/settings/system/banks', history: ['/settings/system?search=B'] });
    assert.equal(app.back.href, '/settings/system?search=B');
    const returned = workspace({ url: 'http://localhost:82/settings/system?search=B', history: ['/dashboard', '/settings/system?search=B', '/settings/system/banks'] });
    assert.equal(returned.back.href, '/dashboard');
    assert.deepEqual(JSON.parse(returned.storage.get('kfms.workspace.history.7')), ['/dashboard', '/settings/system?search=B']);
});

test('Back ignores external and sign-in history entries', () => {
    const app = workspace({ history: ['https://untrusted.example/', '//untrusted.example/', '/login', '/client-portal/login', '/dashboard'] });
    assert.equal(app.back.href, '/dashboard');
    assert.deepEqual(JSON.parse(app.storage.get('kfms.workspace.history.7')), ['/dashboard', '/settings/system']);
});

function clientAccess(fetch) {
    const email = element({ dataset: { clientLookupUrl: '/client-portal/lookup' }, checkValidity() { return this.value.includes('@'); } });
    const phone = element(); const message = element();
    let ready; let submissions = 0;
    const form = element({ requestSubmit() { submissions++; this.fire('submit'); } });
    email.closest = () => form;
    const window = { location: { origin: 'http://localhost:82' }, setTimeout: () => 1, clearTimeout() {} };
    const document = {
        addEventListener: (name, listener) => { ready = listener; },
        querySelector: selector => selector.includes('message') ? message : email,
        getElementById: id => id === 'phone' ? phone : null,
    };
    vm.runInNewContext(source('kfms-client-access.js'), { document, window, URL, AbortController, fetch });
    ready();
    return { email, phone, message, form, submissions: () => submissions };
}
const response = phoneHint => ({ ok: true, json: async () => ({ exists: true, phone_hint: phoneHint }) });
const settle = () => new Promise(resolve => setImmediate(resolve));

test('client activation automatically displays only the masked phone hint', async () => {
    const app = clientAccess(async () => response('•••• 1222'));
    app.email.value = 'client@example.test'; app.email.fire('blur');
    await settle();
    assert.equal(app.phone.value, '•••• 1222');
    assert.equal(app.phone.readOnly, true);
});

test('client activation ignores stale lookups when the email changes', async () => {
    const requests = [];
    const app = clientAccess(() => new Promise(resolve => requests.push(resolve)));
    app.email.value = 'first@example.test'; app.email.fire('blur');
    app.email.value = 'second@example.test'; app.email.fire('input'); app.email.fire('blur');
    requests[1](response('•••• 2222')); await settle();
    requests[0](response('•••• 1111')); await settle();
    assert.equal(app.phone.value, '•••• 2222');
    app.email.value = ''; app.email.fire('input');
    assert.equal(app.phone.value, '');
});

test('client activation waits for the masked lookup before submitting once', async () => {
    let finish;
    const app = clientAccess(() => new Promise(resolve => { finish = resolve; }));
    app.email.value = 'client@example.test';
    const submitted = app.form.fire('submit');
    app.form.fire('submit');
    assert.equal(app.submissions(), 0);
    finish(response('•••• 3333'));
    await submitted;
    assert.equal(app.submissions(), 1);
    assert.equal(app.phone.value, '•••• 3333');
});
