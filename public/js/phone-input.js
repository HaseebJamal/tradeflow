(() => {
    const message = 'Enter a valid international phone number.';
    const e164Pattern = /^\+[1-9]\d{7,14}$/;
    const initialisers = new WeakMap();
    const synchronisers = new WeakMap();

    const initialise = (field, visible, hidden = null) => {
        if (initialisers.has(field) || !window.intlTelInput || !visible) return;

        let utilitiesReady = false;
        const instance = window.intlTelInput(visible, {
            initialCountry: visible.dataset.defaultCountry || 'pk',
            separateDialCode: true,
            nationalMode: true,
            formatOnDisplay: true,
            autoPlaceholder: 'polite',
            loadUtils: () => import('https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js'),
        });
        if (hidden?.value || visible.value) instance.setNumber(hidden?.value || visible.value);

        const nationalDigitLimit = () => {
            const placeholderDigits = String(instance.getPlaceholder?.() || '').replace(/\D/g, '').length;
            if (placeholderDigits) return placeholderDigits;

            const dialCodeLength = String(instance.getSelectedCountryData()?.dialCode || '').replace(/\D/g, '').length;
            return Math.max(1, 15 - dialCodeLength);
        };
        const normaliseVisibleValue = () => {
            const limit = nationalDigitLimit();
            const digits = visible.value.replace(/\D/g, '').slice(0, limit);
            visible.maxLength = limit;
            visible.dataset.phoneDigitLimit = String(limit);
            if (visible.value !== digits) visible.value = digits;
        };

        const fallbackE164Number = () => {
            const country = instance.getSelectedCountryData?.() || {};
            const dialCode = String(country.dialCode || '').replace(/\D/g, '');
            let nationalNumber = visible.value.replace(/\D/g, '');
            if (!dialCode || !nationalNumber) return '';

            // A pasted E.164 value may include the dial code in the visible
            // field. Avoid duplicating it when constructing the fallback.
            if (nationalNumber.startsWith(dialCode)) {
                nationalNumber = nationalNumber.slice(dialCode.length);
            }

            const number = `+${dialCode}${nationalNumber}`;
            return e164Pattern.test(number) ? number : '';
        };

        const e164Number = () => {
            const number = String(instance.getNumber() || '');
            const normalised = number.startsWith('+')
                ? `+${number.slice(1).replace(/\D/g, '')}`
                : '';

            if (!e164Pattern.test(normalised)) return fallbackE164Number();
            if (utilitiesReady && typeof instance.isValidNumber === 'function' && !instance.isValidNumber()) return '';

            return normalised;
        };
        const sync = () => {
            normaliseVisibleValue();
            if (!visible.value.trim()) { if (hidden) hidden.value = ''; visible.setCustomValidity(''); return true; }
            // The backend accepts normalized E.164 values. Use getNumber()
            // directly so a correctly-entered number is not lost while the
            // optional intl-tel-input utility bundle is still loading.
            const number = e164Number();
            const valid = number !== '';
            if (hidden) hidden.value = number;
            visible.setCustomValidity(valid ? '' : message);
            visible.classList.toggle('is-invalid', Boolean(visible.value.trim()) && !valid);
            return valid;
        };
        initialisers.set(field, instance);
        if (field !== visible) initialisers.set(visible, instance);
        synchronisers.set(field, sync);
        if (field !== visible) synchronisers.set(visible, sync);
        sync();
        if (instance.promise && typeof instance.promise.then === 'function') {
            instance.promise
                .then(() => {
                    utilitiesReady = true;
                    normaliseVisibleValue();
                    sync();
                })
                .catch(() => {
                    // The E.164 fallback keeps form submission functional if
                    // an external utility bundle is temporarily unavailable.
                });
        }
        visible.addEventListener('beforeinput', (event) => {
            if (!event.inputType?.startsWith('insert') || !event.data) return;
            if (!/^\d+$/.test(event.data)) event.preventDefault();
        });
        visible.addEventListener('paste', (event) => {
            event.preventDefault();
            const pastedValue = (event.clipboardData?.getData('text') || '').trim();
            if (/^(?:\+|00)/.test(pastedValue)) {
                instance.setNumber(pastedValue.startsWith('00') ? `+${pastedValue.slice(2)}` : pastedValue);
                sync();
                return;
            }
            const pastedDigits = pastedValue.replace(/\D/g, '');
            const limit = nationalDigitLimit();
            const next = (visible.value.slice(0, visible.selectionStart || 0) + pastedDigits + visible.value.slice(visible.selectionEnd || 0))
                .replace(/\D/g, '')
                .slice(0, limit);
            visible.value = next;
            visible.dispatchEvent(new Event('input', { bubbles: true }));
        });
        visible.addEventListener('input', sync);
        visible.addEventListener('countrychange', sync);
        visible.closest('form')?.addEventListener('submit', (event) => {
            if (visible.required && !visible.value.trim()) return;
            if (!sync()) { event.preventDefault(); visible.reportValidity(); return; }
            if (!hidden && visible.value.trim()) visible.value = e164Number();
        }, true);
        visible.closest('form')?.addEventListener('formdata', (event) => {
            if (hidden?.name) event.formData.set(hidden.name, e164Number());
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

    window.TradeFlowPhone = {
        init,
        sync: (field) => synchronisers.get(field)?.() ?? true,
        e164: (field) => {
            const instance = initialisers.get(field);
            if (!instance) return field?.value || '';
            const number = String(instance.getNumber() || '');
            const normalised = number.startsWith('+') ? `+${number.slice(1).replace(/\D/g, '')}` : '';
            return e164Pattern.test(normalised) ? normalised : '';
        },
        isValid: (field) => !field?.value.trim() || Boolean(window.TradeFlowPhone?.e164(field)),
    };
    document.addEventListener('DOMContentLoaded', () => init());
})();
