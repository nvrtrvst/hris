<?php

namespace App\Http\Controllers;

use App\Models\PayrollReferenceType;
use App\Models\PayrollReferenceValue;
use Illuminate\Http\Request;

class PayrollReferenceValueController extends Controller
{
    public function store(Request $request, $referenceTypeId)
    {
        $type = PayrollReferenceType::findOrFail($referenceTypeId);
        $validated = $request->validate([
            'komponen_gaji_id' => 'required|exists:komponen_gaji,id',
            'reference_key' => 'required|string|max:100',
            'nominal' => 'required|numeric|min:0',
        ]);

        PayrollReferenceValue::create([
            'komponen_gaji_id' => $validated['komponen_gaji_id'],
            'payroll_reference_type_id' => $type->id,
            'reference_key' => $validated['reference_key'],
            'nominal' => $validated['nominal'],
        ]);

        return redirect()->back()->with('message', 'Nilai ditambah.');
    }

    public function update(Request $request, $valueId)
    {
        $value = PayrollReferenceValue::findOrFail($valueId);
        $validated = $request->validate([
            'reference_key' => 'required|string|max:100',
            'nominal' => 'required|numeric|min:0',
        ]);

        $value->update($validated);

        return redirect()->back()->with('message', 'Nilai diperbarui.');
    }

    public function destroy($valueId)
    {
        PayrollReferenceValue::destroy($valueId);

        return redirect()->back()->with('message', 'Nilai dihapus.');
    }
}
