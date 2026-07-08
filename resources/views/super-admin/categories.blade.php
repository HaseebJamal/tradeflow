@extends('layouts.dashboard')
@section('page-title', 'Categories')
@section('content')
<div class="tf-card p-4 mb-4"><form method="POST" action="{{ route('admin.categories.store') }}" class="row g-3">@csrf<div class="col-md-6"><input name="name" class="form-control" placeholder="Category name"></div><div class="col-md-3"><select name="status" class="form-select"><option>Active</option><option>Inactive</option></select></div><div class="col-md-3"><button class="btn btn-tf-primary w-100">Save Category</button></div></form></div>
<x-table><thead><tr><th>Name</th><th>Status</th></tr></thead><tbody>@foreach($categories as $category)<tr><td>{{ $category->name }}</td><td>{{ $category->status }}</td></tr>@endforeach</tbody></x-table>
@endsection
