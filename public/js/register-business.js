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
    const billingCycleInputs = [...wizard.querySelectorAll('[data-registration-billing-cycle]')];
    const planInputs = [...wizard.querySelectorAll('[data-registration-plan-input]')];
    const planOptions = [...wizard.querySelectorAll('[data-registration-plan-option]')];
    const restored = document.querySelector('[data-tf-register-restored]');
    const ownerPhone = wizard.querySelector('[data-tf-phone-visible]');
    const ownerPhoneValue = wizard.querySelector('[data-tf-phone-value]');
    const passwordField = wizard.querySelector('[name="password"]');
    const confirmationField = wizard.querySelector('[name="password_confirmation"]');
    const hasServerErrors = wizard.dataset.registrationHasErrors === '1';
    let currentStep = 0;
    let moving = false;

    const clearDraft = () => Object.values(storage).forEach((key) => sessionStorage.removeItem(key));

    const registrationComplete = document.querySelector('[data-tf-registration-complete]');
    if (registrationComplete) {
        clearDraft();
        const redirectUrl = registrationComplete.dataset.redirectUrl || '/';
        const redirectHome = () => window.location.assign(redirectUrl);

        if (!window.Swal) {
            window.setTimeout(redirectHome, 4000);
            return;
        }

        window.Swal.fire({
            icon: 'success',
            title: 'Registration Submitted',
            text: 'Your business registration has been submitted successfully. Please wait for Super Admin approval before logging in. You will be notified once your business has been reviewed.',
            confirmButtonText: 'OK',
            timer: 4000,
            timerProgressBar: true,
            allowOutsideClick: false,
        }).then(redirectHome);
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

    const phoneIsValid = (field) => window.TradeFlowPhone?.isValid(field)
        ?? /^\+[1-9]\d{7,14}$/.test(field.value.replace(/[\s-]/g, ''));
    const passwordIsStrong = (value) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(value);
    const validatePasswordFields = () => {
        if (!passwordField || !confirmationField) return null;

        let firstInvalid = null;
        const passwordMessage = !passwordField.value
            ? 'This field is required.'
            : (!passwordIsStrong(passwordField.value)
                ? 'Use at least 8 characters with uppercase, lowercase, number, and special character.'
                : '');
        setError(passwordField, passwordMessage);
        if (passwordMessage) firstInvalid = passwordField;

        const confirmationMessage = !confirmationField.value
            ? 'Please confirm your password.'
            : (confirmationField.value !== passwordField.value
                ? 'Password and confirm password do not match.'
                : '');
        setError(confirmationField, confirmationMessage);
        return firstInvalid || (confirmationMessage ? confirmationField : null);
    };
    const documentMessage = (field) => ({
        cnic_image: 'Please upload a valid CNIC document.',
        business_document: 'Please upload a valid business document.',
        shop_image: 'Please upload a valid shop or business premises image.',
    }[field.name] || 'Please upload a valid document.');

    const startsWith = (bytes, signature) => signature.every((value, index) => bytes[index] === value);

    const inspectImage = (file, minimumWidth, minimumHeight) => new Promise((resolve) => {
        const image = new Image();
        const url = URL.createObjectURL(file);
        image.onload = () => {
            URL.revokeObjectURL(url);
            resolve(image.naturalWidth >= minimumWidth && image.naturalHeight >= minimumHeight);
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(false);
        };
        image.src = url;
    });

    const validateFile = (field) => {
        const file = field.files?.[0];
        if (!file) return 'Please select this required document.';
        if (file.size > 5 * 1024 * 1024) return 'File size must not exceed 5 MB.';

        const allowed = field.name === 'shop_image'
            ? ['image/jpeg', 'image/png']
            : ['application/pdf', 'image/jpeg', 'image/png'];

        return allowed.includes(file.type) ? '' : documentMessage(field);
    };

    const validateFileContent = async (field) => {
        const basicError = validateFile(field);
        if (basicError) return basicError;

        const file = field.files?.[0];
        const header = new Uint8Array(await file.slice(0, 12).arrayBuffer());
        const isPdf = startsWith(header, [0x25, 0x50, 0x44, 0x46, 0x2d]);
        const isJpeg = startsWith(header, [0xff, 0xd8, 0xff]);
        const isPng = startsWith(header, [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

        if (file.type === 'application/pdf') return isPdf ? '' : documentMessage(field);
        if (!isJpeg && !isPng) return documentMessage(field);

        const minimum = field.name === 'shop_image' ? [320, 180] : [200, 120];
        return (await inspectImage(file, ...minimum)) ? '' : documentMessage(field);
    };

    const startFileValidation = (field) => {
        field.dataset.registerFileState = 'pending';
        field.dataset.registerFileMessage = 'Checking the selected file...';

        validateFileContent(field)
            .then((message) => {
                field.dataset.registerFileState = message ? 'invalid' : 'valid';
                field.dataset.registerFileMessage = message;
                setError(field, message);
            })
            .catch(() => {
                field.dataset.registerFileState = 'invalid';
                field.dataset.registerFileMessage = documentMessage(field);
                setError(field, field.dataset.registerFileMessage);
            });
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
                    const error = field.dataset.registerFileState === 'pending'
                        ? 'Checking the selected file...'
                        : (field.dataset.registerFileState === 'invalid'
                            ? field.dataset.registerFileMessage
                            : validateFile(field));
                    if (error) setInvalid(field, error);
                    return;
                }
                if (field.type === 'radio') return;
                if (field.required && !field.value.trim()) {
                    setInvalid(field, 'This field is required.');
                } else if (field.name === 'phone' && !phoneIsValid(field)) {
                    setInvalid(field, field.validationMessage || 'Please enter a valid phone number for the selected country.');
                } else if (field.name === 'email' && !field.validity.valid) {
                    setInvalid(field, 'Please enter a valid email address.');
                }
            });
        }

        if (index === 0) {
            if (ownerPhone) {
                const phoneValid = phoneIsValid(ownerPhone);
                if (!phoneValid || !ownerPhoneValue?.value) {
                    firstInvalid ??= ownerPhone;
                }
            }
            firstInvalid ??= validatePasswordFields();
        }

        if (index === 3) {
            const selectedPlan = wizard.querySelector('input[name="selected_plan_id"]:checked, input[type="hidden"][name="selected_plan_id"]');
            const selectedCycle = wizard.querySelector('input[name="billing_cycle"]:checked, input[type="hidden"][name="billing_cycle"]');
            if (!selectedPlan) {
                const target = wizard.querySelector('[data-register-error="selected_plan_id"]');
                if (target) target.textContent = 'Please select a subscription plan before continuing.';
                firstInvalid ??= planInputs[0] || null;
            }
            if (!selectedCycle) firstInvalid ??= billingCycleInputs[0] || null;
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
            return { restored: true, step: Number.isInteger(step) && step >= 1 && step <= panels.length ? step - 1 : 0 };
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

    const syncOtherBusinessType = () => {
        const selectedType = wizard.querySelector('input[name="business_type"]:checked')?.value;
        const wrapper = wizard.querySelector('[data-tf-other-business-type]');
        const field = wizard.querySelector('[name="other_business_type"]');
        if (!wrapper || !field) return;

        const isOther = selectedType === 'Other';
        wrapper.classList.toggle('d-none', !isOther);
        field.required = isOther;

        if (!isOther) {
            field.value = '';
            setError(field);
        }
    };

    const refreshPlanPricing = () => {
        const cycle = wizard.querySelector('input[name="billing_cycle"]:checked')?.value || 'Monthly';
        const yearly = cycle === 'Yearly';

        wizard.querySelectorAll('[data-registration-monthly-price]').forEach((price) => price.classList.toggle('d-none', yearly));
        wizard.querySelectorAll('[data-registration-yearly-price]').forEach((price) => price.classList.toggle('d-none', !yearly));
    };

    const refreshPlanSelection = () => {
        const selected = wizard.querySelector('input[name="selected_plan_id"]:checked, input[type="hidden"][name="selected_plan_id"]');
        planOptions.forEach((option) => {
            const input = option.querySelector('[data-registration-plan-input]');
            option.classList.toggle('is-selected', input === selected);
            option.setAttribute('aria-checked', input === selected ? 'true' : 'false');
        });

        if (selected) {
            const error = wizard.querySelector('[data-register-error="selected_plan_id"]');
            if (error) error.textContent = '';
        }
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

    // Laravel's old input is authoritative after a server-side validation error.
    // Restoring the browser draft here would hide those errors and reset the flow.
    const draft = hasServerErrors ? { restored: false, step: 0 } : restoreDraft();
    const serverStep = Math.max(1, Math.min(panels.length, Number(wizard.dataset.registrationStep || 1))) - 1;
    showStep(hasServerErrors ? serverStep : draft.step, false);
    window.applyTradeFlowTabOrder?.(wizard, true);
    syncOwnerName();
    refreshBusinessTypes();
    syncOtherBusinessType();
    refreshPlanPricing();
    refreshPlanSelection();

    tabs.forEach((tab) => tab.addEventListener('click', () => moveTo(Number(tab.dataset.tfStepTab))));
    back.addEventListener('click', () => showStep(currentStep - 1));
    next.addEventListener('click', () => moveTo(currentStep + 1));

    wizard.addEventListener('input', (event) => {
        if (event.target.name === 'name') syncOwnerName();
        if (event.target.type !== 'password') saveDraft();
        if (event.target === passwordField || event.target === confirmationField) validatePasswordFields();
    });

    [passwordField, confirmationField].filter(Boolean).forEach((field) => field.addEventListener('blur', validatePasswordFields));

    wizard.addEventListener('change', (event) => {
        if (event.target.matches('input[name="business_type"]')) {
            refreshBusinessTypes();
            syncOtherBusinessType();
        }
        if (event.target.matches('[data-registration-billing-cycle]')) refreshPlanPricing();
        if (event.target.matches('[data-registration-plan-input]')) refreshPlanSelection();
        if (event.target.matches('[data-register-file]')) {
            const file = event.target.files?.[0];
            const target = wizard.querySelector('[data-file-name="' + event.target.name + '"]');
            if (target) target.textContent = file ? file.name : 'No file selected.';
            if (file) startFileValidation(event.target);
            else {
                event.target.dataset.registerFileState = '';
                event.target.dataset.registerFileMessage = '';
                validateStep(panels.length - 1, false);
            }
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
        if (wizard.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

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

        wizard.dataset.submitting = '1';
        submit.disabled = true;
        submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting';
    });
})();
