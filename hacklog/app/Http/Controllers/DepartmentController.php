<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    public function index()
    {
        $this->authorizeInventoryManagement();

        $departments = Department::home()
            ->withCount(['children', 'projects'])
            ->with(['children' => function ($query) {
                $query->withCount('nestedProjects')->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        return view('departments.index', compact('departments'));
    }

    public function store(Request $request)
    {
        $this->authorizeInventoryManagement();

        $validated = $this->validateInventoryName($request, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->whereNull('parent_id'),
            ],
        ]);

        $department = Department::create([
            'name' => trim($validated['name']),
            'parent_id' => null,
        ]);
        $department->loadCount(['children', 'projects']);
        $department->setRelation('children', collect());

        if ($this->isHtmx($request)) {
            return view('departments.partials.department-group', compact('department'));
        }

        return redirect()->route('departments.index');
    }

    public function update(Request $request, Department $department)
    {
        $this->authorizeInventoryManagement();

        if (!$department->isHomeDepartment()) {
            abort(404);
        }

        $validated = $this->validateInventoryName($request, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->whereNull('parent_id')->ignore($department->id),
            ],
        ]);

        $department->update([
            'name' => trim($validated['name']),
        ]);

        if ($this->isHtmx($request)) {
            return view('departments.partials.department-group', [
                'department' => $this->departmentGroupPayload($department),
            ]);
        }

        return redirect()->route('departments.index');
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorizeInventoryManagement();

        if (!$department->isHomeDepartment()) {
            abort(404);
        }

        $department->delete();

        if ($this->isHtmx($request)) {
            return response('', 200);
        }

        return redirect()->route('departments.index');
    }

    public function storeNested(Request $request, Department $department)
    {
        $this->authorizeInventoryManagement();

        if (!$department->isHomeDepartment()) {
            abort(404);
        }

        $validated = $this->validateInventoryName($request, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where('parent_id', $department->id),
            ],
        ]);

        $department->children()->create([
            'name' => trim($validated['name']),
        ]);

        if ($this->isHtmx($request)) {
            return view('departments.partials.department-group', [
                'department' => $this->departmentGroupPayload($department),
            ]);
        }

        return redirect()->route('departments.index');
    }

    public function updateNested(Request $request, Department $department, Department $nested)
    {
        $this->authorizeInventoryManagement();

        $this->assertNestedBelongsToHome($department, $nested);

        $validated = $this->validateInventoryName($request, [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where('parent_id', $department->id)->ignore($nested->id),
            ],
        ]);

        $nested->update([
            'name' => trim($validated['name']),
        ]);

        if ($this->isHtmx($request)) {
            return view('departments.partials.department-group', [
                'department' => $this->departmentGroupPayload($department),
            ]);
        }

        return redirect()->route('departments.index');
    }

    public function destroyNested(Request $request, Department $department, Department $nested)
    {
        $this->authorizeInventoryManagement();

        $this->assertNestedBelongsToHome($department, $nested);

        $nested->delete();

        if ($this->isHtmx($request)) {
            return response('', 200);
        }

        return redirect()->route('departments.index');
    }

    public function nestedOptions(Request $request)
    {
        $this->authorizeInventoryManagement();

        $departmentId = $request->input('department_id');
        $selectedNestedId = $request->input('nested_department_id');

        $nestedDepartments = collect();
        $departmentSelected = $departmentId && is_numeric($departmentId);
        if ($departmentSelected) {
            $nestedDepartments = Department::where('parent_id', (int) $departmentId)
                ->orderBy('name')
                ->get();
        }

        return view('departments.partials.nested-options', compact('nestedDepartments', 'selectedNestedId', 'departmentSelected'));
    }

    protected function departmentGroupPayload(Department $department): Department
    {
        $department->loadCount(['children', 'projects']);
        $department->load(['children' => function ($query) {
            $query->withCount('nestedProjects')->orderBy('name');
        }]);

        return $department;
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

    protected function assertNestedBelongsToHome(Department $department, Department $nested): void
    {
        if (!$department->isHomeDepartment() || (int) $nested->parent_id !== (int) $department->id) {
            abort(404);
        }
    }

    protected function authorizeInventoryManagement(): void
    {
        $user = auth()->user();

        if (!$user || (!$user->isAdmin() && !$user->isTeam())) {
            abort(403, 'You are not authorized to manage departments.');
        }
    }
}
