<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckpointController extends Controller
{
    public function index(): View
    {
        return view('system.checkpoints.index', [
            'checkpoints' => Checkpoint::where('status', 'active')
                ->orderBy('code')
                ->paginate(10),
            'newCheckpoint' => new Checkpoint(['status' => 'active']),
        ]);
    }

    public function create(): View
    {
        return view('system.checkpoints.form', [
            'checkpoint' => new Checkpoint(['status' => 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $checkpoint = Checkpoint::create($this->validatedData($request));

        AuditLogger::record('checkpoint_created', 'Checkpoint created.', $checkpoint, [
            'code' => $checkpoint->code,
            'device_uid' => $checkpoint->device_uid,
        ]);

        return redirect()->route('checkpoints.index')->with('status', 'Checkpoint created.');
    }

    public function edit(Checkpoint $checkpoint): View
    {
        return view('system.checkpoints.form', compact('checkpoint'));
    }

    public function update(Request $request, Checkpoint $checkpoint): RedirectResponse
    {
        $before = $checkpoint->only(['code', 'name', 'location', 'device_uid', 'status']);

        $checkpoint->update($this->validatedData($request, $checkpoint));

        AuditLogger::record('checkpoint_updated', 'Checkpoint updated.', $checkpoint, [
            'before' => $before,
            'after' => $checkpoint->only(['code', 'name', 'location', 'device_uid', 'status']),
        ]);

        return redirect()->route('checkpoints.index')->with('status', 'Checkpoint updated.');
    }

    public function destroy(Checkpoint $checkpoint): RedirectResponse
    {
        AuditLogger::record('checkpoint_deleted', 'Checkpoint removed.', $checkpoint, [
            'code' => $checkpoint->code,
            'device_uid' => $checkpoint->device_uid,
        ]);

        $checkpoint->delete();

        return redirect()->route('checkpoints.index')->with('status', 'Checkpoint removed.');
    }

    private function validatedData(Request $request, ?Checkpoint $checkpoint = null): array
    {
        $checkpointId = $checkpoint?->id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('checkpoints', 'code')->ignore($checkpointId)],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'device_uid' => ['nullable', 'string', 'max:100', Rule::unique('checkpoints', 'device_uid')->ignore($checkpointId)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['device_uid'] = filled($data['device_uid'] ?? null) ? strtoupper(trim($data['device_uid'])) : null;

        return $data;
    }
}
