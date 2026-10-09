import { readFileSync } from 'node:fs';
import { createContext, runInContext } from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/operations/borrowings/show.blade.php', import.meta.url), 'utf8');
const start = blade.indexOf('(function () {');
const end = blade.indexOf('})();', start);
if (start < 0 || end < 0) {
    throw new Error('Approval script was not found in the detail view.');
}
const script = blade.slice(start, end + 5);

function el(id, extra = {}) {
    const attrs = { ...(extra.attrs || {}) };
    const tag = extra.tag || (id === 'tr' || id === 'td' ? id : 'div');
    const classes = new Set(extra.classes || []);
    const listeners = {};
    const node = {
        id,
        tag,
        value: extra.value || '',
        textContent: extra.text || '',
        innerHTML: '',
        disabled: false,
        parent: extra.parent || null,
        children: [],
        attrs,
        classList: {
            add(...names) { names.forEach((name) => classes.add(name)); },
            remove(...names) { names.forEach((name) => classes.delete(name)); },
            toggle(name, force) {
                const on = force === undefined ? !classes.has(name) : force;
                if (on) classes.add(name);
                else classes.delete(name);
                return on;
            },
            contains(name) { return classes.has(name); },
        },
        getAttribute(name) { return attrs[name] ?? null; },
        setAttribute(name, value) { attrs[name] = String(value); },
        addEventListener(type, fn) { (listeners[type] ||= []).push(fn); },
        click() { (listeners.click || []).forEach((fn) => fn({})); },
        focus() { node.focused = true; },
        scrollIntoView() {},
        querySelector(selector) { return node.querySelectorAll(selector)[0] || null; },
        querySelectorAll(selector) {
            const all = [node, ...node.children.flatMap((child) => [child, ...descendant(child)])];
            return all.filter((child) => matches(child, selector));
        },
        closest(selector) {
            let current = node;
            while (current) {
                if (matches(current, selector)) return current;
                current = current.parent;
            }
            return null;
        },
        fire(type) { (listeners[type] || []).forEach((fn) => fn({})); },
    };
    (extra.children || []).forEach((child) => {
        child.parent = node;
        node.children.push(child);
    });
    return node;
}

function descendant(node) {
    return node.children.flatMap((child) => [child, ...descendant(child)]);
}

function matches(node, selector) {
    if (selector.startsWith('#')) return node.id === selector.slice(1);
    if (selector.startsWith('.')) {
        const [classPart, attr] = selector.slice(1).split('[');
        if (!classPart.split('.').every((name) => node.classList.contains(name))) return false;
        if (!attr) return true;
        const body = attr.replace(']', '');
        const [name, raw] = body.split('=');
        return node.getAttribute(name) === raw.replaceAll('"', '');
    }
    return node.tag === selector;
}

const rows = [1, 2, 3, 4, 5].map((id) => {
    const approve = el('', { classes: ['js-decide-btn'], attrs: { 'data-action': 'approve', 'data-item-number': 'N' + id, 'data-item-name': 'Barang ' + id } });
    const reject = el('', { classes: ['js-decide-btn'], attrs: { 'data-action': 'reject', 'data-item-number': 'CBB-00000' + id, 'data-item-name': 'Pulpen ' + id } });
    const reason = el('', { classes: ['js-reject-reason'] });
    const badge = el('', { classes: ['js-approval-badge'], text: 'Menunggu', attrs: { 'data-default-color': 'warning', 'data-default-label': 'Menunggu' } });
    const note = el('', { classes: ['js-draft-note', 'd-none'] });
    const decision = el('', { classes: ['js-item-decision'], attrs: { 'data-item-id': String(id) }, children: [approve, reject, reason] });
    const statusCell = el('td', { children: [badge, note] });
    return el('tr', { children: [statusCell, decision] });
});

const byId = {};
function remember(node) {
    if (node.id) byId[node.id] = node;
    node.children.forEach(remember);
}
rows.forEach(remember);
['draftApprovedCount', 'draftRejectedCount', 'draftPendingCount', 'saveApprovalTotal', 'saveApprovalApprovedCount', 'saveApprovalRejectedCount', 'rejectItemNumber', 'rejectItemName'].forEach((id) => {
    byId[id] = el(id, { text: id === 'draftPendingCount' ? '5' : '0' });
});
byId.rejectItemReason = el('rejectItemReason');
byId.rejectItemReasonError = el('rejectItemReasonError', { text: 'Alasan penolakan wajib diisi.' });
byId.confirmRejectItem = el('confirmRejectItem');
byId.rejectItemModal = el('rejectItemModal');
byId.openSaveApprovalModal = el('openSaveApprovalModal');
byId.confirmSaveApproval = el('confirmSaveApproval');
byId.saveApprovalsForm = el('saveApprovalsForm');
byId.saveApprovalsFields = el('saveApprovalsFields');
byId.saveApprovalClientError = el('saveApprovalClientError', { classes: ['d-none'] });
byId.saveApprovalModal = el('saveApprovalModal');

const document = {
    querySelectorAll(selector) {
        return rows.flatMap((row) => row.querySelectorAll(selector));
    },
    getElementById(id) { return byId[id] || null; },
};

const modalState = { open: null };
const bootstrap = {
    Modal: {
        getOrCreateInstance(target) {
            return {
                show() { modalState.open = target.id; },
                hide() { modalState.open = null; target.fire('hidden.bs.modal'); },
            };
        },
    },
};

runInContext(script, createContext({ document, bootstrap, console }));

const firstReject = rows[0].querySelector('.js-decide-btn[data-action="reject"]');
firstReject.click();
byId.confirmRejectItem.click();

const emptyState = {
    modal: modalState.open,
    invalid: byId.rejectItemReason.classList.contains('is-invalid'),
    approved: byId.draftApprovedCount.textContent,
    rejected: byId.draftRejectedCount.textContent,
    pending: byId.draftPendingCount.textContent,
    badge: rows[0].querySelector('.js-approval-badge').textContent,
};

byId.rejectItemReason.value = 'Inventaris sedang diperlukan untuk kegiatan operasional.';
byId.confirmRejectItem.click();

const validState = {
    modal: modalState.open,
    approved: byId.draftApprovedCount.textContent,
    rejected: byId.draftRejectedCount.textContent,
    pending: byId.draftPendingCount.textContent,
    badge: rows[0].querySelector('.js-approval-badge').textContent,
    reason: rows[0].querySelector('.js-reject-reason').value,
    number: byId.rejectItemNumber.textContent,
};

rows[1].querySelector('.js-decide-btn[data-action="approve"]').click();
const mixedState = {
    approved: byId.draftApprovedCount.textContent,
    rejected: byId.draftRejectedCount.textContent,
    pending: byId.draftPendingCount.textContent,
    firstReason: rows[0].querySelector('.js-reject-reason').value,
};

const failures = [];
if (!(emptyState.modal === 'rejectItemModal' && emptyState.invalid && emptyState.rejected === '0' && emptyState.pending === '5' && emptyState.badge === 'Menunggu')) {
    failures.push(['empty reason did not stay pending', emptyState]);
}
if (!(validState.modal === null && validState.approved === '0' && validState.rejected === '1' && validState.pending === '4' && validState.badge.includes('Belum Disimpan') && validState.number === 'CBB-000001')) {
    failures.push(['valid reason did not update the draft summary', validState]);
}
if (!(mixedState.approved === '1' && mixedState.rejected === '1' && mixedState.pending === '3' && mixedState.firstReason.startsWith('Inventaris sedang diperlukan'))) {
    failures.push(['second decision overwrote the first rejection', mixedState]);
}

if (failures.length) {
    console.error(JSON.stringify(failures, null, 2));
    process.exit(1);
}

console.log(JSON.stringify({ emptyState, validState, mixedState }));
