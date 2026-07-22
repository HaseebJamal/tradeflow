(() => {
    const message = 'Enter a valid international phone number.';
    const initialisers = new WeakMap();

    const initialise = (field, visible, hidden = null) => {
        if (initialisers.has(field) || !window.intlTelInput || !visible) return;
        if (!visible || !hidden) return;

        const instance = window.intlTelInput(visible, {
            initialCountry: visible.dataset.defaultCountry || 'pk',
            separateDialCode: true,
            nationalMode: false,
            formatOnDisplay: true,
            autoPlaceholder: 'polite',
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js',
        });
        initialisers.set(field, instance);
        if (hidden?.value || visible.value) instance.setNumber(hidden?.value || visible.value);

        const sync = () => {
            if (!visible.value.trim()) { if (hidden) hidden.value = ''; visible.setCustomValidity(''); return true; }
            const valid = instance.isValidNumber();
            if (hidden) hidden.value = valid ? instance.getNumber() : '';
            visible.setCustomValidity(valid ? '' : message);
            return valid;
        };
        visible.addEventListener('input', sync);
        visible.addEventListener('countrychange', sync);
        visible.closest('form')?.addEventListener('submit', (event) => {
            if (visible.required && !visible.value.trim()) return;
            if (!sync()) { event.preventDefault(); visible.reportValidity(); return; }
            if (!hidden && visible.value.trim()) visible.value = instance.getNumber();
        });
    };
    const init = (root = document) => {
        root.querySelectorAll?.('[data-tf-phone-field]')?.forEach((field) => initialise(field, field.querySelector('[data-tf-phone-visible]'), field.querySelector('[data-tf-phone-value]')));
        root.querySelectorAll?.('[data-tf-phone-standalone]')?.forEach((visible) => initialise(visible, visible));
        root.querySelectorAll?.('form:not([method="GET"]):not([method="get"]) input[name]')?.forEach((visible) => {
            const name = visible.name.toLowerCase();
            if (initialisers.has(visible) || visible.type === 'hidden' || !(name.includes('phone') || name.includes('mobile') || name.includes('contact') || name.includes('whatsapp'))) return;
            visible.dataset.tfPhoneStandalone = '1';
            initialise(visible, visible);
        });
    };

    window.TradeFlowPhone = { init, e164: (field) => initialisers.get(field)?.getNumber() || field?.value || '' };
    document.addEventListener('DOMContentLoaded', () => init());
})();
