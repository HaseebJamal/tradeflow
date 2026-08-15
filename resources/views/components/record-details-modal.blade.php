@props([
    'id',
    'title',
    'status' => null,
    'openUrl' => null,
    'openLabel' => 'Open full record',
])

<div class="modal fade tf-record-details-modal" data-tf-record-details-modal id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-1" id="{{ $id }}Title">{{ $title }}</h2>
                    @if($status)
                        <span class="badge text-bg-light border">{{ $status }}</span>
                    @endif
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
            <div class="modal-footer">
                @if($openUrl)
                    <a href="{{ $openUrl }}" class="btn btn-outline-primary">{{ $openLabel }}</a>
                @endif
                <button type="button" class="btn btn-tf-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
