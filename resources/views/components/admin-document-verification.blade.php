@props(['company', 'document', 'label'])

@if($document?->file_path)
    @php
        $status = match (strtolower(trim((string) $document->status))) {
            'pending', 'pending verification' => 'Pending Verification',
            'verified' => 'Verified',
            'rejected' => 'Rejected',
            're-upload requested' => 'Re-upload Requested',
            default => $document->status ?: 'Pending Verification',
        };
        $badge = match ($status) {
            'Verified' => 'tf-badge-success',
            'Rejected' => 'tf-badge-danger',
            'Re-upload Requested' => 'tf-badge-warning',
            default => 'tf-badge-warning',
        };
        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($document->file_path);
        $isPdf = str_ends_with(strtolower($document->file_path), '.pdf');
        $availableActions = match ($status) {
            'Pending Verification' => ['approve', 'reject'],
            default => [],
        };
    @endphp
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="tf-badge {{ $badge }}">{{ $status }}</span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#document-preview-{{ $document->id }}">Preview</button>
        @if(in_array('approve', $availableActions, true))
            <form method="POST" action="{{ route('admin.companies.documents.verify', [$company, $document]) }}" data-tf-confirm-message="Verify this {{ strtolower($label) }}?">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success">Verify</button></form>
        @endif
        @if(in_array('reject', $availableActions, true))
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#document-reject-{{ $document->id }}">Reject</button>
        @endif
    </div>

    <div class="modal fade" id="document-preview-{{ $document->id }}" tabindex="-1" aria-hidden="true" data-tf-document-modal>
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">{{ $label }} Preview</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                @if($isPdf)
                    <object data="{{ $url }}" type="application/pdf" class="w-100 tf-document-preview">Your browser cannot preview this PDF.</object>
                @else
                    <img src="{{ $url }}" alt="{{ $label }}" class="img-fluid rounded border">
                @endif
            </div>
            <div class="modal-footer"><a class="btn btn-outline-primary" href="{{ $url }}" target="_blank" rel="noopener">Open in New Tab</a><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>

    @foreach(['reject' => ['Reject '.$label, 'reject', 'btn-danger', 'Reason for rejection']] as $key => [$title, $decision, $buttonClass, $reasonLabel])
        @if(in_array($decision, $availableActions, true))
        <div class="modal fade" id="document-{{ $key }}-{{ $document->id }}" tabindex="-1" aria-hidden="true" data-tf-document-modal>
            <div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('admin.companies.documents.verify', [$company, $document]) }}">
                @csrf @method('PATCH')<input type="hidden" name="decision" value="{{ $decision }}">
                <div class="modal-header"><h2 class="modal-title h5">{{ $title }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><label class="form-label">{{ $reasonLabel }}</label><textarea name="reason" class="form-control" rows="3" maxlength="2000" required></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn {{ $buttonClass }}">Save</button></div>
            </form></div></div>
        </div>
        @endif
    @endforeach
@else
    <span class="detail-value tf-muted">Not uploaded</span>
@endif
