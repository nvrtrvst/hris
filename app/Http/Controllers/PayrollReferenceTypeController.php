<?php

namespace App\Http\Controllers;

use App\Models\KomponenGaji;
use App\Models\PayrollReferenceType;
use App\Models\PayrollReferenceValue;
use Illuminate\Http\Request;

class PayrollReferenceTypeController extends Controller
{
    public function index()
    {
        $types = PayrollReferenceType::with('values.komponenGaji')->orderBy('kode')->get();
        $allowedSources = PenggajianController::referenceSourceFields();
        $komponens = KomponenGaji::where('is_active', true)
            ->select('id', 'kode', 'nama', 'jenis', 'unit_sekolah_id')
            ->orderBy('kode')
            ->get();

        return inertia('Payroll/ReferenceTypes', [
            'types' => $types,
            'allowedSources' => $allowedSources,
            'komponens' => $komponens,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:payroll_reference_types,kode',
            'nama' => 'required|string|max:100',
            'source_field' => 'required|string|in:'.implode(',', PenggajianController::referenceSourceFields()),
            'is_active' => 'boolean',
        ]);

        PayrollReferenceType::create($validated);

        return redirect()->back()->with('message', 'Reference type ditambah.');
    }

    public function update(Request $request, $id)
    {
        $type = PayrollReferenceType::findOrFail($id);
        $validated = $request->validate([
            'kode' => 'required|string|max:50|unique:payroll_reference_types,kode,'.$type->id,
            'nama' => 'required|string|max:100',
            'source_field' => 'required|string|in:'.implode(',', PenggajianController::referenceSourceFields()),
            'is_active' => 'boolean',
        ]);

        $type->update($validated);

        return redirect()->back()->with('message', 'Reference type diperbarui.');
    }

    public function destroy($id)
    {
        $type = PayrollReferenceType::withCount('values')->findOrFail($id);
        if ($type->values_count > 0) {
            return redirect()->back()->with('error', 'Tidak bisa hapus: type masih punya '.$type->values_count.' nilai.');
        }

        PayrollReferenceValue::where('payroll_reference_type_id', $type->id)->delete();
        $type->delete();

        return redirect()->back()->with('message', 'Reference type dihapus.');
    }
}
