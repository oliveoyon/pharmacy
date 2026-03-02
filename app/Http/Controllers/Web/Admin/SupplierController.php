<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);
        $suppliers = Supplier::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate(15);

        return view('web.admin.suppliers.index', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'due_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Supplier::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            ...$validated,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'due_days' => $validated['due_days'] ?? 0,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.supplier_created'),
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->ensureOwned($request, $supplier->organization_id);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'due_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $supplier->update([
            ...$validated,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'due_days' => $validated['due_days'] ?? 0,
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.updated'),
            'text' => __('app.supplier_updated'),
        ]);
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->ensureOwned($request, $supplier->organization_id);
        $supplier->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.deleted'),
            'text' => __('app.supplier_deleted'),
        ]);
    }

    private function organizationId(Request $request): int
    {
        $organizationId = (int) ($request->user()?->organization_id ?? 0);
        abort_if($organizationId <= 0, 403, 'Organization context required.');

        return $organizationId;
    }

    private function ensureOwned(Request $request, int $organizationId): void
    {
        abort_if($organizationId !== $this->organizationId($request), 403);
    }
}

