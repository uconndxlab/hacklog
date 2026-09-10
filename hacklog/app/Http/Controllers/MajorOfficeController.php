<?php

namespace App\Http\Controllers;

use App\Models\MajorOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MajorOfficeController extends Controller
{
    public function index()
    {
        $this->authorizeInventoryManagement();

        $majorOffices = MajorOffice::query()
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        return view('major-offices.index', compact('majorOffices'));
    }

    public function store(Request $request)
    {
        $this->authorizeInventoryManagement();

        $validated = $this->validateInventoryName($request, [
            'name' => ['required', 'string', 'max:255', Rule::unique('major_offices', 'name')],
        ]);

        $office = MajorOffice::create([
            'name' => trim($validated['name']),
        ]);
        $office->loadCount('projects');

        if ($this->isHtmx($request)) {
            return view('major-offices.partials.office-row', compact('office'));
        }

        return redirect()->route('major-offices.index');
    }

    public function update(Request $request, MajorOffice $majorOffice)
    {
        $this->authorizeInventoryManagement();

        $validated = $this->validateInventoryName($request, [
            'name' => ['required', 'string', 'max:255', Rule::unique('major_offices', 'name')->ignore($majorOffice->id)],
        ]);

        $majorOffice->update([
            'name' => trim($validated['name']),
        ]);
        $majorOffice->loadCount('projects');

        if ($this->isHtmx($request)) {
            return view('major-offices.partials.office-row', ['office' => $majorOffice]);
        }

        return redirect()->route('major-offices.index');
    }

    public function destroy(Request $request, MajorOffice $majorOffice)
    {
        $this->authorizeInventoryManagement();

        $majorOffice->delete();

        if ($this->isHtmx($request)) {
            return response('', 200);
        }

        return redirect()->route('major-offices.index');
    }

    protected function validateInventoryName(Request $request, array $rules): array
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($this->isHtmx($request)) {
                abort(response('', 422));
            }

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    protected function isHtmx(Request $request): bool
    {
        return $request->headers->has('HX-Request');
    }

    protected function authorizeInventoryManagement(): void
    {
        $user = auth()->user();

        if (!$user || (!$user->isAdmin() && !$user->isTeam())) {
            abort(403, 'You are not authorized to manage major offices.');
        }
    }
}
