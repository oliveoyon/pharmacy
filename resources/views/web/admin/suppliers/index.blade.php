@extends('web.layouts.app')

@php($title = __('app.suppliers'))

@section('content')
    <div class="crud-grid">
        <section class="panel">
            <h4>{{ __('app.create_supplier') }}</h4>
            <form method="POST" action="{{ route('admin.suppliers.store') }}" class="form-grid">
                @csrf
                <label>{{ __('app.name') }}</label>
                <input name="name" required>

                <label>{{ __('app.code') }}</label>
                <input name="code">

                <label>{{ __('app.phone') }}</label>
                <input name="phone">

                <label>{{ __('app.email') }}</label>
                <input name="email" type="email">

                <label>{{ __('app.credit_limit') }}</label>
                <input name="credit_limit" type="number" step="0.01">

                <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> <span>{{ __('app.active') }}</span></label>
                <button class="btn btn-primary" type="submit">{{ __('app.save') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.suppliers_list') }}</h4>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.code') }}</th>
                        <th>{{ __('app.phone') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>{{ $supplier->name }}</td>
                            <td>{{ $supplier->code }}</td>
                            <td>{{ $supplier->phone }}</td>
                            <td>{{ $supplier->is_active ? __('app.active') : __('app.inactive') }}</td>
                            <td class="actions">
                                <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}" class="inline-edit">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $supplier->name }}" required>
                                    <input name="code" value="{{ $supplier->code }}">
                                    <button class="btn btn-light btn-xs" type="submit">{{ __('app.update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" class="delete-form">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs" type="submit">{{ __('app.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $suppliers->links() }}
        </section>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('.delete-form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: @json(__('app.are_you_sure')),
                        text: @json(__('app.cannot_undo')),
                        showCancelButton: true,
                        confirmButtonText: @json(__('app.yes_delete')),
                        cancelButtonText: @json(__('app.cancel'))
                    }).then((result) => {
                        if (result.isConfirmed) form.submit();
                    });
                });
            });
        </script>
    @endpush
@endsection
