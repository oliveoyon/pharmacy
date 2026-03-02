<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = $this->organizationId($request);
        $units = Unit::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate(15);

        return view('web.admin.units.index', compact('units'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = $this->organizationId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:20'],
            'is_base' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Unit::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'short_code' => strtolower($validated['short_code']),
            'is_base' => (bool) ($validated['is_base'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.saved'),
            'text' => __('app.unit_created'),
        ]);
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->ensureOwned($request, $unit->organization_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:20'],
            'is_base' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update([
            'name' => $validated['name'],
            'short_code' => strtolower($validated['short_code']),
            'is_base' => (bool) ($validated['is_base'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.updated'),
            'text' => __('app.unit_updated'),
        ]);
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $this->ensureOwned($request, $unit->organization_id);
        $unit->delete();

        return back()->with('toast', [
            'type' => 'success',
            'title' => __('app.deleted'),
            'text' => __('app.unit_deleted'),
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

