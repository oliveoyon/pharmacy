@extends('web.layouts.app')

@php($title = __('app.units'))

@section('content')
    <div class="crud-grid">
        <section class="panel">
            <h4>{{ __('app.create_unit') }}</h4>
            <form method="POST" action="{{ route('admin.units.store') }}" class="form-grid">
                @csrf
                <label>{{ __('app.name') }}</label>
                <input name="name" required>

                <label>{{ __('app.short_code') }}</label>
                <input name="short_code" required>

                <label class="checkbox-row"><input type="checkbox" name="is_base" value="1"> <span>{{ __('app.base_unit') }}</span></label>
                <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> <span>{{ __('app.active') }}</span></label>
                <button class="btn btn-primary" type="submit">{{ __('app.save') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.units_list') }}</h4>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.short_code') }}</th>
                        <th>{{ __('app.status') }}</th>
                        <th>{{ __('app.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($units as $unit)
                        <tr>
                            <td>{{ $unit->name }}</td>
                            <td>{{ $unit->short_code }}</td>
                            <td>{{ $unit->is_active ? __('app.active') : __('app.inactive') }}</td>
                            <td class="actions">
                                <form method="POST" action="{{ route('admin.units.update', $unit) }}" class="inline-edit">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $unit->name }}" required>
                                    <input name="short_code" value="{{ $unit->short_code }}" required>
                                    <button class="btn btn-light btn-xs" type="submit">{{ __('app.update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.units.destroy', $unit) }}" class="delete-form">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs" type="submit">{{ __('app.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">{{ __('app.no_data') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $units->links() }}
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
