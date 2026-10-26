<?php

namespace App\Http\Controllers;

use App\Models\ApprovalPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApprovalPolicyController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/ApprovalPolicies', [
            'policies' => ApprovalPolicy::orderBy('jenis')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'policies' => 'required|array|min:1',
            'policies.*.jenis' => ['required', Rule::in(['izin', 'cuti', 'koreksi', 'pulang_cepat', 'lembur', 'tugas_luar'])],
            'policies.*.levels' => 'required|integer|min:1|max:2',
            'policies.*.second_level_min_days' => 'nullable|integer|min:1|max:30',
            'policies.*.sla_days' => 'required|integer|min:1|max:30',
            'policies.*.tembusan_yayasan' => 'boolean',
        ]);

        foreach ($validated['policies'] as $row) {
            ApprovalPolicy::updateOrCreate(
                ['jenis' => $row['jenis']],
                [
                    'levels' => $row['levels'],
                    'second_level_min_days' => $row['levels'] > 1 ? ($row['second_level_min_days'] ?? null) : null,
                    'sla_days' => $row['sla_days'],
                    'tembusan_yayasan' => (bool) ($row['tembusan_yayasan'] ?? false),
                    'is_active' => true,
                ],
            );
        }

        return back()->with('message', 'Aturan persetujuan diperbarui.');
    }
}
