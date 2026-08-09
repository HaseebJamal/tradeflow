(() => {
    const form = document.querySelector('[data-tf-register-form]');
    if (!form || form.dataset.registrationReady === '1') return;
    form.dataset.registrationReady = '1';

    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="password_confirmation"]');
    const confirmationError = form.querySelector('[data-register-confirmation-error]');
    const strength = form.querySelector('[data-register-password-strength]');
    const submit = form.querySelector('[data-tf-step-submit]');
    const logo = form.querySelector('[name="logo"]');
    const logoName = form.querySelector('[data-register-logo-name]');
    let confirmationTouched = false;

    const passwordMessage = () => {
        const value = password?.value || '';
        if (!value) return 'Use 8+ characters with uppercase, number, and symbol.';
        const strong = value.length >= 8 && /[a-z]/.test(value) && /[A-Z]/.test(value) && /\d/.test(value) && /[^A-Za-z0-9]/.test(value);
        return strong ? 'Strong password' : 'Add uppercase, lowercase, number, and symbol.';
    };
    const validateConfirmation = (force = false) => {
        if (!confirmationTouched && !force) return true;
        const message = !confirmation.value ? 'Please confirm your password.' : (confirmation.value !== password.value ? 'Passwords do not match.' : '');
        confirmation.classList.toggle('is-invalid', Boolean(message));
        confirmationError.textContent = message;
        return !message;
    };

    password?.addEventListener('input', () => { strength.textContent = passwordMessage(); validateConfirmation(); });
    confirmation?.addEventListener('input', () => { confirmationTouched = true; validateConfirmation(); });
    confirmation?.addEventListener('blur', () => { confirmationTouched = true; validateConfirmation(); });
    logo?.addEventListener('change', () => { logoName.textContent = logo.files?.[0]?.name || 'No file selected'; });

    form.addEventListener('submit', (event) => {
        confirmationTouched = true;
        const phone = form.querySelector('[data-tf-phone-visible]');
        const phoneValue = form.querySelector('[data-tf-phone-value]');
        if (!form.checkValidity() || !validateConfirmation(true) || !phoneValue?.value || phone?.dataset.phoneValid === 'false') {
            event.preventDefault();
            form.classList.add('was-validated');
            (form.querySelector('.is-invalid, :invalid') || phone)?.focus();
            return;
        }
        submit.disabled = true;
        submit.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Creating workspace…</span>';
    });
})();
