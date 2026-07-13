(() => {
    const wizard = document.querySelector('[data-tf-register-form]');
    if (!wizard || wizard.dataset.registrationWizardReady === '1') return;

    wizard.dataset.registrationWizardReady = '1';

    const storage = {
        step: 'tradeflow_business_registration_step',
        data: 'tradeflow_business_registration_data',
        savedAt: 'tradeflow_business_registration_saved_at',
    };
    const maxAge = 24 * 60 * 60 * 1000;
    const tabs = [...wizard.querySelectorAll('[data-tf-step-tab]')];
    const panels = [...wizard.querySelectorAll('[data-tf-step-panel]')];
    const back = wizard.querySelector('[data-tf-step-back]');
    const next = wizard.querySelector('[data-tf-step-next]');
    const submit = wizard.querySelector('[data-tf-step-submit]');
    const restored = document.querySelector('[data-tf-register-restored]');
    const hasServerErrors = wizard.dataset.registrationHasErrors === '1';
    let currentStep = 0;
    let moving = false;

    const clearDraft = () => Object.values(storage).forEach((key) => sessionStorage.removeItem(key));

    if (document.querySelector('[data-tf-registration-complete]')) {
        clearDraft();
        return;
    }

    const nonSensitiveFields = () => [...wizard.querySelectorAll('input, select, textarea')].filter((field) => (
        field.name
        && !['password', 'password_confirmation'].includes(field.name)
        && field.type !== 'file'
        && field.type !== 'hidden'
        && !field.readOnly
    ));

    const panelFields = (index) => [...panels[index].querySelectorAll('input, select, textarea')].filter((field) => (
        field.name && field.type !== 'hidden' && !field.readOnly
    ));

    const setError = (field, message = '') => {
        const target = wizard.querySelector('[data-register-error="' + field.name + '"]');
        field.classList.toggle('is-invalid', Boolean(message));
        if (target) target.textContent = message;
    };

    const resetErrors = (index) => {
        panelFields(index).forEach((field) => {
            if (field.type !== 'radio') setError(field);
        });
        if (index === 1) {
            wizard.querySelectorAll('.tf-business-type-card').forEach((card) => card.classList.remove('is-invalid'));
            const target = wizard.querySelector('[data-register-error="business_type"]');
            if (target) target.textContent = '';
        }
    };

    const phoneIsValid = (value) => /^(?:\+92|92|0)3\d{9}$/.test(value.replace(/[\s-]/g, ''));
    const passwordIsStrong = (value) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(value);

    const validateFile = (field) => {
        const file = field.files?.[0];
        if (!file) return 'Please select this required document.';
        if (file.size > 5 * 1024 * 1024) return 'File size must not exceed 5 MB.';

        const allowed = field.name === 'shop_image'
            ? ['image/jpeg', 'image/png']
            : ['application/pdf', 'image/jpeg', 'image/png'];

        return allowed.includes(file.type) ? '' : 'Use an approved PDF, JPG, JPEG, or PNG file.';
    };

    const validateStep = (index, focus = true) => {
        resetErrors(index);
        let firstInvalid = null;
        const setInvalid = (field, message) => {
            if (!firstInvalid) firstInvalid = field;
            setError(field, message);
        };

        if (index === 1) {
            const selected = wizard.querySelector('input[name="business_type"]:checked');
            if (!selected) {
                const firstType = wizard.querySelector('input[name="business_type"]');
                wizard.querySelectorAll('.tf-business-type-card').forEach((card) => card.classList.add('is-invalid'));
                const target = wizard.querySelector('[data-register-error="business_type"]');
                if (target) target.textContent = 'Please select one business type before continuing.';
                firstInvalid = firstType;
            }
        } else {
            panelFields(index).forEach((field) => {
                if (field.type === 'password') return;
                if (field.type === 'file') {
                    const error = validateFile(field);
                    if (error) setInvalid(field, error);
                    return;
                }
                if (field.required && !field.value.trim()) {
                    setInvalid(field, 'This field is required.');
                } else if (field.name === 'phone' && !phoneIsValid(field.value)) {
                    setInvalid(field, 'Enter a valid Pakistani phone number, for example 03001234567.');
                } else if (field.name === 'email' && !field.validity.valid) {
                    setInvalid(field, 'Please enter a valid email address.');
                }
            });
        }

        if (index === 0) {
            const password = wizard.querySelector('[name="password"]');
            const confirmation = wizard.querySelector('[name="password_confirmation"]');
            if (!passwordIsStrong(password.value)) {
                setInvalid(password, 'Use at least 8 characters with uppercase, lowercase, number, and special character.');
            }
            if (password.value !== confirmation.value) {
                setInvalid(confirmation, 'Password and confirm password do not match.');
            }
        }

        if (firstInvalid && focus) {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return !firstInvalid;
    };

    const saveDraft = () => {
        const values = {};
        nonSensitiveFields().forEach((field) => {
            if (field.type === 'radio') {
                if (field.checked) values[field.name] = field.value;
            } else if (field.type === 'checkbox') {
                values[field.name] = field.checked;
            } else {
                values[field.name] = field.value;
            }
        });

        sessionStorage.setItem(storage.data, JSON.stringify(values));
        sessionStorage.setItem(storage.step, String(currentStep + 1));
        sessionStorage.setItem(storage.savedAt, String(Date.now()));
    };

    const restoreDraft = () => {
        const savedAt = Number(sessionStorage.getItem(storage.savedAt) || 0);
        if (!savedAt || Date.now() - savedAt > maxAge) {
            clearDraft();
            return { restored: false, step: 0 };
        }

        try {
            const values = JSON.parse(sessionStorage.getItem(storage.data) || '{}');
            nonSensitiveFields().forEach((field) => {
                if (!(field.name in values)) return;
                if (field.type === 'radio') field.checked = values[field.name] === field.value;
                else if (field.type === 'checkbox') field.checked = Boolean(values[field.name]);
                else field.value = values[field.name];
            });

            const step = Number(sessionStorage.getItem(storage.step));
            restored?.classList.remove('d-none');
            return { restored: true, step: Number.isInteger(step) && step >= 1 && step <= 4 ? step - 1 : 0 };
        } catch (_) {
            clearDraft();
            return { restored: false, step: 0 };
        }
    };

    const syncOwnerName = () => {
        const ownerCopy = wizard.querySelector('[data-tf-owner-copy]');
        if (ownerCopy) ownerCopy.value = wizard.querySelector('[name="name"]')?.value || '';
    };

    const refreshBusinessTypes = () => {
        wizard.querySelectorAll('.tf-business-type-card').forEach((card) => {
            card.classList.toggle('active', Boolean(card.querySelector('input')?.checked));
        });
    };

    const showStep = (step, persist = true) => {
        currentStep = Math.max(0, Math.min(step, panels.length - 1));
        tabs.forEach((tab, index) => tab.classList.toggle('active', index === currentStep));
        panels.forEach((panel, index) => {
            const active = index === currentStep;
            panel.classList.toggle('active', active);
            panel.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !active;
            });
        });

        back.disabled = currentStep === 0;
        next.classList.toggle('d-none', currentStep === panels.length - 1);
        submit.classList.toggle('d-none', currentStep !== panels.length - 1);
        if (persist) saveDraft();
    };

    const moveTo = (target) => {
        if (moving) return;
        moving = true;

        if (target > currentStep) {
            for (let index = currentStep; index < target; index += 1) {
                if (!validateStep(index)) {
                    showStep(index);
                    moving = false;
                    return;
                }
            }
        }

        showStep(target);
        moving = false;
    };

    const draft = restoreDraft();
    const serverStep = Math.max(1, Math.min(4, Number(wizard.dataset.registrationStep || 1))) - 1;
    showStep(hasServerErrors ? serverStep : draft.step, false);
    window.applyTradeFlowTabOrder?.(wizard, true);
    syncOwnerName();
    refreshBusinessTypes();

    tabs.forEach((tab) => tab.addEventListener('click', () => moveTo(Number(tab.dataset.tfStepTab))));
    back.addEventListener('click', () => showStep(currentStep - 1));
    next.addEventListener('click', () => moveTo(currentStep + 1));

    wizard.addEventListener('input', (event) => {
        if (event.target.name === 'name') syncOwnerName();
        if (event.target.type !== 'password') saveDraft();
        if (event.target.name === 'password' || event.target.name === 'password_confirmation') validateStep(0, false);
    });

    wizard.addEventListener('change', (event) => {
        if (event.target.matches('input[name="business_type"]')) refreshBusinessTypes();
        if (event.target.matches('[data-register-file]')) {
            const file = event.target.files?.[0];
            const target = wizard.querySelector('[data-file-name="' + event.target.name + '"]');
            if (target) target.textContent = file ? file.name : 'No file selected.';
            validateStep(3, false);
        }
        if (event.target.type !== 'file') saveDraft();
    });

    wizard.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && currentStep < panels.length - 1) {
            event.preventDefault();
            moveTo(currentStep + 1);
        }
    });

    wizard.addEventListener('submit', (event) => {
        panels.forEach((panel) => panel.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = false;
        }));

        for (let index = 0; index < panels.length; index += 1) {
            if (!validateStep(index, index === currentStep)) {
                event.preventDefault();
                showStep(index);
                return;
            }
        }

        submit.disabled = true;
        submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting';
    });
})();
