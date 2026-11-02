/* Native QPay presentation. Checkout owns form submission; this handler owns pending payments. */
(function () {
    'use strict';
    if (typeof ohmylms_qpay_params === 'undefined') return;
    var config = ohmylms_qpay_params, i18n = config.i18n;
    var attempt = null, form = null, modal = null, timer = null, generation = 0, started = 0, opener = null;
    var storageKey = 'ohmylms.qpay.' + location.pathname;
    function save() { try { sessionStorage.setItem(storageKey, JSON.stringify(attempt)); } catch (_) {} }
    function clear() {
        attempt = null;
        try { sessionStorage.removeItem(storageKey); } catch (_) {}
        document.querySelectorAll('.ohmylms-qpay-resume').forEach(function (button) { button.remove(); });
    }
    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) node.className = className;
        if (text) node.textContent = text;
        return node;
    }
    function fingerprint(checkout) {
        var fields = [];
        new FormData(checkout).forEach(function (value, name) {
            if (typeof value === 'string' && !/nonce|password|card|secret|_wp_http_referer/i.test(name)) fields.push({name: name, value: value});
        });
        fields = JSON.stringify(fields);
        // Only a comparison hash is persisted, never the student's form data.
        var hash = 2166136261;
        for (var i = 0; i < fields.length; i++) hash = Math.imul(hash ^ fields.charCodeAt(i), 16777619);
        return String(hash >>> 0);
    }
    function unlock() {
        if (!form) return;
        form.classList.remove('processing');
        form.querySelectorAll('.ohmylms-place-order-button').forEach(function (button) { button.disabled = false; });
        form.querySelectorAll('.ohmylms-loader').forEach(function (loader) { loader.style.display = 'none'; });
    }
    function stop() { clearTimeout(timer); generation++; }
    function close() { stop(); modal.close(); unlock(); if (opener && opener.focus) opener.focus(); }
    function status(message) { modal.querySelector('.ohmylms-qpay-status').textContent = message; }
    function build() {
        if (modal) return;
        modal = element('dialog', 'ohmylms-qpay-modal');
        modal.setAttribute('aria-labelledby', 'ohmylms-qpay-title');
        var title = element('h2', '', i18n.title);
        title.id = 'ohmylms-qpay-title';
        var closeButton = element('button', 'ohmylms-qpay-close', i18n.close);
        closeButton.type = 'button';
        closeButton.addEventListener('click', close);
        var qr = element('img', 'ohmylms-qpay-qr');
        qr.alt = 'QPay QR';
        var state = element('p', 'ohmylms-qpay-status');
        state.setAttribute('role', 'status');
        state.setAttribute('aria-live', 'polite');
        var check = element('button', 'ohmylms-qpay-check', i18n.check);
        check.type = 'button';
        check.addEventListener('click', resumeAttempt);
        modal.append(title, closeButton, qr, element('p', 'ohmylms-qpay-bank-label', i18n.banks), element('div', 'ohmylms-qpay-banks'), state, check);
        modal.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
        document.body.appendChild(modal);
    }
    function validBankLink(value) {
        // Bank-specific URI schemes are allowed; executable/document schemes are not.
        return typeof value === 'string' && /^[a-z][a-z0-9+.-]*:\/\//i.test(value)
            && !/^(javascript|data|vbscript|file|blob):/i.test(value);
    }
    function render(data) {
        build();
        var image = typeof data.qr_image === 'string' ? data.qr_image.replace(/^data:image\/png;base64,/, '') : '';
        var qr = modal.querySelector('.ohmylms-qpay-qr');
        qr.style.display = image ? '' : 'none';
        qr.setAttribute('src', image && /^[A-Za-z0-9+/=\r\n]+$/.test(image) ? 'data:image/png;base64,' + image : '');
        var banks = modal.querySelector('.ohmylms-qpay-banks');
        banks.replaceChildren();
        (Array.isArray(data.urls) ? data.urls : []).forEach(function (bank) {
            if (!bank || !validBankLink(bank.link)) return;
            var link = element('a', 'ohmylms-qpay-bank', bank.description || bank.name || 'Bank');
            link.href = bank.link;
            link.rel = 'noopener noreferrer';
            banks.appendChild(link);
        });
        modal.querySelector('.ohmylms-qpay-bank-label').style.display = banks.children.length ? '' : 'none';
        opener = document.activeElement;
        if (!modal.open) modal.showModal();
        status(data.payment_error || i18n.waiting);
    }
    /** Resolves with the JSON answer of a 2xx response; rejects with the server's message, if any. */
    function request(action) {
        return fetch(config.ajax_url, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: new URLSearchParams({action: action, nonce: config.nonce, order_id: attempt.order_id, payment_token: attempt.payment_token})
        }).then(function (response) {
            return response.json().catch(function () { return null; }).then(function (json) {
                if (response.ok && json) return json;
                throw new Error(json && json.data && json.data.message || i18n.error);
            });
        }, function () { throw new Error(i18n.error); });
    }
    function paid(data) {
        stop(); status(i18n.paid); clear();
        if (data.redirect_url) window.location.assign(data.redirect_url);
    }
    function poll() {
        stop(); started = Date.now();
        var current = generation;
        status(i18n.waiting);
        function tick() {
            if (!attempt || generation !== current || !modal.open) return;
            if (Date.now() - started > 300000) { status(i18n.paused); return; }
            request('ohmylms_qpay_check_payment').then(function (response) {
                if (generation !== current) return;
                if (response.success && response.data.status === 'paid') { paid(response.data); return; }
                status(response.success ? i18n.waiting : (response.data && response.data.message || i18n.error));
                timer = setTimeout(tick, 3000);
            }, function (error) {
                if (generation !== current) return;
                status(error.message);
                // No overlapping requests; temporary failures can be resumed explicitly.
            });
        }
        tick();
    }
    function banner() {
        var checkout = document.getElementById('ohmylms-checkout-form');
        if (document.querySelector('.ohmylms-qpay-resume') || !checkout) return;
        var button = element('button', 'ohmylms-qpay-resume', i18n.resume);
        button.type = 'button';
        button.addEventListener('click', resumeAttempt);
        checkout.before(button);
    }
    function resumeAttempt() {
        if (!attempt) return;
        render(attempt); stop();
        var current = generation;
        request('ohmylms_qpay_resume').then(function (response) {
            if (generation !== current) return;
            if (!response.success) { status(response.data && response.data.message || i18n.error); return; }
            if (response.data.status === 'paid') { paid(response.data); return; }
            attempt = Object.assign({}, attempt, response.data); save(); render(attempt); poll();
        }, function (error) {
            if (generation !== current) return;
            status(error.message);
        }).finally(unlock);
    }
    // Checkout passes its <form> element to both hooks.
    window.registerPaymentGatewayHandler('qpay', {
        open: function (data, checkout) {
            form = checkout; attempt = Object.assign({}, data, {fingerprint: fingerprint(checkout)});
            save(); banner(); render(attempt); unlock();
            if (!data.payment_error) poll();
        },
        resume: function (checkout) {
            form = checkout;
            if (!attempt || attempt.fingerprint !== fingerprint(checkout)) return false;
            resumeAttempt(); return true;
        }
    });
    function init() {
        form = form || document.getElementById('ohmylms-checkout-form');
        try { attempt = attempt || JSON.parse(sessionStorage.getItem(storageKey)); } catch (_) {}
        if (attempt && attempt.order_id && attempt.payment_token) banner(); else attempt = null;
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
