(() => {
    const invalidMessage = 'Please enter a valid phone number for the selected country.';
    const incompleteMessage = 'Please complete the phone number for the selected country.';
    const e164Pattern = /^\+[1-9]\d{7,14}$/;
    const initialisers = new WeakMap();
    const synchronisers = new WeakMap();
    const normalizedNumbers = new WeakMap();

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
        const normaliseVisibleValue = ({ truncate = false } = {}) => {
            const limit = nationalDigitLimit();
            const digits = visible.value.replace(/\D/g, '');
            const normalized = truncate ? digits.slice(0, limit) : digits;
            visible.maxLength = limit;
            visible.dataset.phoneDigitLimit = String(limit);
            if (visible.value !== normalized) visible.value = normalized;
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

        const normalisedNumber = () => {
            const number = String(instance.getNumber() || '');
            const normalised = number.startsWith('+')
                ? `+${number.slice(1).replace(/\D/g, '')}`
                : '';

            return e164Pattern.test(normalised) ? normalised : fallbackE164Number();
        };
        const isIncomplete = () => {
            const validationError = instance.getValidationError?.();
            const tooShort = window.intlTelInputUtils?.validationError?.TOO_SHORT;
            if (typeof tooShort === 'number' && validationError === tooShort) return true;

            return visible.value.replace(/\D/g, '').length < nationalDigitLimit();
        };
        const hasSelectedCountryMismatch = () => {
            const nationalDigits = visible.value.replace(/\D/g, '');
            const selectedCountry = instance.getSelectedCountryData?.()?.iso2;

            // A Pakistani mobile number is commonly entered as 03XXXXXXXXX.
            // Do not let another country's metadata reinterpret that local
            // format as a different valid-looking number after a flag change.
            return selectedCountry !== 'pk' && /^03\d{9}$/.test(nationalDigits);
        };
        const feedbackFor = (create = false) => {
            if (!visible.id) visible.id = `tf-phone-${Math.random().toString(36).slice(2)}`;

            const wrapper = field.closest('[data-tf-phone-field]');
            const scope = wrapper || visible.closest('.iti')?.parentElement || visible.parentElement;
            const existing = wrapper?.querySelector('[data-tf-phone-feedback]')
                || scope?.querySelector(`[data-tf-phone-feedback-for="${visible.id}"]`);
            if (existing) return existing;
            if (!create) return null;

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-none';
            feedback.dataset.tfPhoneFeedback = '';
            feedback.dataset.tfPhoneFeedbackFor = visible.id;
            (visible.closest('.iti') || visible).insertAdjacentElement('afterend', feedback);
            return feedback;
        };
        const setFeedback = (message = '') => {
            const feedback = feedbackFor(Boolean(message));
            if (!feedback) return;

            feedback.textContent = message;
            feedback.classList.toggle('d-block', Boolean(message));
            feedback.classList.toggle('d-none', !message);
        };
        const sync = ({ truncate = false } = {}) => {
            normaliseVisibleValue({ truncate });
            if (!visible.value.trim()) {
                if (hidden) hidden.value = '';
                normalizedNumbers.set(field, '');
                visible.setCustomValidity('');
                visible.classList.remove('is-invalid');
                setFeedback();
                return true;
            }

            const number = normalisedNumber();
            const validByCountry = typeof instance.isValidNumber === 'function' && instance.isValidNumber();
            const validByPreciseMetadata = typeof instance.isValidNumberPrecise === 'function'
                ? instance.isValidNumberPrecise()
                : true;
            const valid = !hasSelectedCountryMismatch()
                && Boolean(number)
                && (utilitiesReady ? validByCountry && validByPreciseMetadata : !isIncomplete());
            const validationMessage = valid ? '' : (isIncomplete() ? incompleteMessage : invalidMessage);

            if (hidden) hidden.value = valid ? number : '';
            normalizedNumbers.set(field, valid ? number : '');
            visible.setCustomValidity(validationMessage);
            visible.classList.toggle('is-invalid', !valid);
            setFeedback(validationMessage);
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
        visible.addEventListener('input', () => sync({ truncate: true }));
        visible.addEventListener('blur', () => sync());
        visible.addEventListener('countrychange', () => sync());
        visible.closest('form')?.addEventListener('submit', (event) => {
            if (visible.required && !visible.value.trim()) return;
            if (!sync()) { event.preventDefault(); visible.reportValidity(); return; }
            if (!hidden && visible.value.trim()) visible.value = normalizedNumbers.get(field) || '';
        }, true);
        visible.closest('form')?.addEventListener('formdata', (event) => {
            if (hidden?.name) event.formData.set(hidden.name, hidden.value || '');
        });
    };
    const init = (root = document) => {
        root.querySelectorAll?.('[data-tf-phone-field]')?.forEach((field) => initialise(field, field.querySelector('[data-tf-phone-visible]'), field.querySelector('[data-tf-phone-value]')));
        root.querySelectorAll?.('[data-tf-phone-standalone]')?.forEach((visible) => initialise(visible, visible));
        root.querySelectorAll?.('form:not([method="GET"]):not([method="get"]) input[name]')?.forEach((visible) => {
            const name = visible.name.toLowerCase();
            const textLikePhoneControl = ['text', 'tel'].includes(visible.type);
            if (initialisers.has(visible) || !textLikePhoneControl || !(name.includes('phone') || name.includes('mobile') || name.includes('contact') || name.includes('whatsapp'))) return;
            visible.dataset.tfPhoneStandalone = '1';
            initialise(visible, visible);
        });
    };

    window.TradeFlowPhone = {
        init,
        sync: (field) => synchronisers.get(field)?.() ?? true,
        setNumber: (field, value = '') => {
            const instance = initialisers.get(field);
            if (!instance) {
                if (field) field.value = value;
                return synchronisers.get(field)?.() ?? true;
            }

            instance.setNumber(value);
            return synchronisers.get(field)?.() ?? true;
        },
        e164: (field) => {
            synchronisers.get(field)?.();
            return normalizedNumbers.get(field) ?? (e164Pattern.test(field?.value || '') ? field.value : '');
        },
        isValid: (field) => !field?.value.trim() || Boolean(window.TradeFlowPhone?.e164(field)),
    };
    window.initializePhoneInputs = init;
    document.addEventListener('DOMContentLoaded', () => init());
    document.addEventListener('shown.bs.modal', (event) => init(event.target));
})();
