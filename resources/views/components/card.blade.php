<div class="tf-card tf-stat-card h-100">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="tf-muted small">{{ $label ?? 'Metric' }}</div>
            <div class="h3 fw-bold mb-1">{{ $value ?? '0' }}</div>
            <div class="small {{ ($trend ?? '') === 'down' ? 'text-danger' : 'text-green' }}">{{ $note ?? 'Live data' }}</div>
        </div>
        <div class="tf-icon-tile {{ $color ?? 'bg-blue' }} text-white"><i class="bi {{ $icon ?? 'bi-graph-up' }}"></i></div>
    </div>
</div>
