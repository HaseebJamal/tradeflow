@extends('layouts.dashboard')

@section('title', 'Company Created Successfully')
@section('page-title', 'Company Created Successfully')
@section('page-subtitle', 'Review and share the owner access details when you are ready.')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="tf-card p-4 mx-auto" style="max-width: 920px;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1">Company Created Successfully</h2>
                <p class="tf-muted mb-0">The company is approved. Access details are available only during this one-time onboarding step.</p>
            </div>
            <span class="tf-badge tf-badge-success">Approved</span>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                'Company Name' => $context['company_name'],
                'Platform' => $context['platform_name'],
                'Owner Name' => $context['owner_name'],
                'Owner Email' => $context['owner_email'] ?: 'Not provided',
                'Owner Phone' => $context['owner_phone'] ?: 'Not provided',
                'Login Email' => $context['login_email'] ?: 'Not provided',
            ] as $label => $value)
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="small tf-muted mb-1">{{ $label }}</div>
                        <div class="text-break">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="small tf-muted mb-1">Temporary Password</div>
                    <input class="form-control form-control-sm" type="text" value="{{ $context['temporary_password'] }}" readonly aria-label="Temporary password">
                    <div class="small text-danger mt-2">Copy or send this temporary password now. It cannot be viewed again after leaving this page.</div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if(filled($context['owner_email']))
                <button class="btn btn-tf-primary" type="button" data-bs-toggle="modal" data-bs-target="#companyOnboardingEmailModal"><i class="bi bi-envelope me-1"></i>Send Email</button>
            @else
                <button class="btn btn-outline-secondary" type="button" disabled title="The owner has no email address."><i class="bi bi-envelope me-1"></i>Send Email</button>
            @endif

            @if(filled($context['whatsapp_digits']))
                <form method="POST" action="{{ route('admin.companies.onboarding.whatsapp', $company) }}" target="_blank">
                    @csrf
                    <button class="btn btn-outline-success" type="submit"><i class="bi bi-whatsapp me-1"></i>Open WhatsApp</button>
                </form>
            @else
                <button class="btn btn-outline-secondary" type="button" disabled title="The owner has no valid international phone number."><i class="bi bi-whatsapp me-1"></i>Open WhatsApp Draft</button>
            @endif

            <button class="btn btn-outline-primary" type="button" data-onboarding-copy><i class="bi bi-clipboard me-1"></i>Copy Access Details</button>

            <form method="POST" action="{{ route('admin.companies.onboarding.done', $company) }}" class="ms-sm-auto" data-onboarding-done-form>
                @csrf
                <button class="btn btn-outline-secondary" type="submit" data-onboarding-done-submit>Done</button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="companyOnboardingEmailModal" tabindex="-1" aria-labelledby="companyOnboardingEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <form method="POST" action="{{ route('admin.companies.onboarding.email', $company) }}" class="modal-content" data-onboarding-email-form>
            @csrf
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="companyOnboardingEmailModalLabel">Send Access Details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="onboardingEmailTo">To</label>
                    <input id="onboardingEmailTo" class="form-control" value="{{ $context['owner_email'] }}" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="onboardingEmailSubject">Subject</label>
                    <input id="onboardingEmailSubject" name="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject', $emailSubject) }}" required maxlength="255">
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label" for="onboardingEmailMessage">Message</label>
                    <textarea id="onboardingEmailMessage" name="message" class="form-control @error('message') is-invalid @enderror" rows="7" required maxlength="5000">{{ old('message', $emailMessage) }}</textarea>
                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-tf-primary" data-onboarding-email-submit>Send Email</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const copyButton = document.querySelector('[data-onboarding-copy]');
    const accessDetails = @json($copyMessage);
    copyButton?.addEventListener('click', async function () {
        try {
            await navigator.clipboard.writeText(accessDetails);
            window.Swal ? Swal.fire({icon: 'success', title: 'Copied', text: 'Access details copied.', timer: 1800, showConfirmButton: false}) : alert('Access details copied.');
        } catch (error) {
            const helper = document.createElement('textarea');
            helper.value = accessDetails;
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            helper.remove();
            window.Swal ? Swal.fire({icon: 'success', title: 'Copied', text: 'Access details copied.', timer: 1800, showConfirmButton: false}) : alert('Access details copied.');
        }
    });

    const emailForm = document.querySelector('[data-onboarding-email-form]');
    emailForm?.addEventListener('submit', function () {
        if (!emailForm.checkValidity()) return;
        const submit = emailForm.querySelector('[data-onboarding-email-submit]');
        submit.disabled = true;
        submit.textContent = 'Sending...';
    });

    const doneForm = document.querySelector('[data-onboarding-done-form]');
    const doneSubmit = doneForm?.querySelector('[data-onboarding-done-submit]');
    let completingOnboarding = false;

    doneForm?.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (completingOnboarding || !window.Swal) return;

        const confirmation = await Swal.fire({
            icon: 'warning',
            title: 'Complete Onboarding?',
            text: 'Make sure you have copied or shared the owner\'s access details. The temporary password cannot be viewed again after leaving this page.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Complete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            reverseButtons: true,
        });

        if (!confirmation.isConfirmed) return;

        completingOnboarding = true;
        doneSubmit.disabled = true;
        doneSubmit.textContent = 'Completing...';
        Swal.fire({
            title: 'Completing onboarding...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading(),
        });

        try {
            const response = await fetch(doneForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': doneForm.querySelector('[name="_token"]').value,
                },
                body: new FormData(doneForm),
            });
            const payload = await response.json();
            if (!response.ok || !payload.redirect) throw new Error(payload.message || 'Unable to complete onboarding.');

            await Swal.fire({
                icon: 'success',
                title: 'Onboarding Completed',
                text: 'Company setup has been completed successfully.',
                confirmButtonText: 'OK',
            });
            window.location.assign(payload.redirect);
        } catch (error) {
            await Swal.fire({
                icon: 'error',
                title: 'Unable to Complete',
                text: 'Company onboarding could not be completed. Please try again.',
                confirmButtonText: 'OK',
            });
            completingOnboarding = false;
            doneSubmit.disabled = false;
            doneSubmit.textContent = 'Done';
        }
    });

    @if($errors->has('subject') || $errors->has('message'))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('companyOnboardingEmailModal')).show();
    @endif
});
</script>
@endpush
