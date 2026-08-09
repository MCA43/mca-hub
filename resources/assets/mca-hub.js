(function () {
    'use strict';

    var busyShown = false;

    function busyEl() {
        return document.getElementById('mca-hub-busy');
    }

    function showBusy(message) {
        if (busyShown) return;
        busyShown = true;

        var root = document.documentElement;
        root.classList.add('mca-hub-is-busy');

        var el = busyEl();
        if (!el) return;

        var msg = el.querySelector('[data-mca-hub-busy-msg]');
        if (msg && message) {
            msg.textContent = message;
        }

        el.hidden = false;
        el.setAttribute('aria-hidden', 'false');

        var buttons = document.querySelectorAll('.mca-hub-root button, .mca-hub-root a.mca-ui-btn');
        buttons.forEach(function (btn) {
            if (btn instanceof HTMLButtonElement) {
                btn.disabled = true;
            }
            btn.setAttribute('aria-busy', 'true');
        });
    }

    function defaultBusyMessage(form) {
        return form.getAttribute('data-mca-busy')
            || (window.McaHubI18n && window.McaHubI18n.busy)
            || 'İşlem sürüyor…';
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.closest('.mca-hub-root')) return;

        // First pass of mca-ui confirm: wait for modal approval.
        if (form.hasAttribute('data-mca-confirm') && form.dataset.mcaConfirmed !== '1') {
            return;
        }

        showBusy(defaultBusyMessage(form));
    }, true);
})();
