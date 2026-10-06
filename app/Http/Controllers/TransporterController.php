<?php

namespace App\Http\Controllers;

use App\Models\Transporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransporterController extends Controller
{
    public function index()
    {
        $transporters = Transporter::orderBy('transporter_name')->get();

        return view('transporters.index', compact('transporters'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transporter_name' => 'required|string|max:150',
            'transporter_id_gst' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'branch_address' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $transporter = Transporter::create([
                'transporter_name' => $validated['transporter_name'],
                'transporter_id_gst' => $validated['transporter_id_gst'] ?? null,
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? 'Gujarat',
                'branch_address' => $validated['branch_address'] ?? null,
                'is_active' => true,
            ]);

            DB::commit();

            return back()->with('success', "✓ Transporter '{$transporter->transporter_name}' added successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create transporter: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to add transporter: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $transporter = Transporter::findOrFail($id);

        $validated = $request->validate([
            'transporter_name' => 'required|string|max:150',
            'transporter_id_gst' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'branch_address' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $transporter->update($validated);
            DB::commit();

            return back()->with('success', "✓ Transporter '{$transporter->transporter_name}' updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update transporter #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update transporter: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $transporter = Transporter::findOrFail($id);
        $name = $transporter->transporter_name;

        DB::beginTransaction();
        try {
            $transporter->delete();
            DB::commit();

            return back()->with('success', "✓ Transporter '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete transporter #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete transporter: '.$e->getMessage());
        }
    }
}
