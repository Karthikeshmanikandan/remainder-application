<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $orgId = auth()->user()->organization_id ?? 1;
        $query = Project::where('organization_id', $orgId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $projects = $query->withCount('tasks')->latest()->paginate(10);

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request)
    {
        $orgId = auth()->user()->organization_id ?? 1;
        Project::create(array_merge($request->validated(), [
            'organization_id' => $orgId,
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('projects.index')->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        $this->ensureSameOrganization($project);

        $project->load(['tasks.assignee', 'tasks.assignedTelegramEmployee']);

        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $this->ensureSameOrganization($project);

        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->ensureSameOrganization($project);

        $project->update($request->validated());

        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $this->ensureSameOrganization($project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }

    private function ensureSameOrganization(Project $project): void
    {
        if (auth()->check() && auth()->user()->organization_id && $project->organization_id) {
            if (auth()->user()->organization_id !== $project->organization_id) {
                abort(403, 'Unauthorized action for this organization.');
            }
        }
    }
}
