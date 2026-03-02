@extends('web.layouts.app')

@php($title = __('app.products'))

@section('content')
    <div class="crud-grid">
        <section class="panel">
            <h4>{{ __('app.create_product') }}</h4>
            <form method="POST" action="{{ route('admin.products.store') }}" class="form-grid">
                @csrf
                <label>{{ __('app.name') }}</label>
                <input name="name" required>

                <label>{{ __('app.sku') }}</label>
                <input name="sku" required>

                <label>{{ __('app.generic') }}</label>
                <input name="generic_name">

                <label>{{ __('app.strength') }}</label>
                <input name="strength">

                <label>{{ __('app.purchase_price') }}</label>
                <input name="purchase_price" type="number" step="0.01">

                <label>{{ __('app.selling_price') }}</label>
                <input name="selling_price" type="number" step="0.01">

                <label>{{ __('app.unit') }}</label>
                <select name="stock_unit_id">
                    <option value="">{{ __('app.select_one') }}</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->short_code }})</option>
                    @endforeach
                </select>

                <button class="btn btn-primary" type="submit">{{ __('app.save') }}</button>
            </form>
        </section>

        <section class="panel">
            <h4>{{ __('app.products_list') }}</h4>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr>
                        <th>{{ __('app.name') }}</th>
                        <th>{{ __('app.sku') }}</th>
                        <th>{{ __('app.unit') }}</th>
                        <th>{{ __('app.selling_price') }}</th>
                        <th>{{ __('app.action') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>{{ $product->stockUnit?->short_code }}</td>
                            <td>{{ number_format((float) $product->selling_price, 2) }}</td>
                            <td class="actions">
                                <form method="POST" action="{{ route('admin.products.update', $product) }}" class="inline-edit">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $product->name }}" required>
                                    <input name="sku" value="{{ $product->sku }}" required>
                                    <button class="btn btn-light btn-xs" type="submit">{{ __('app.update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="delete-form">
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
            {{ $products->links() }}
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

