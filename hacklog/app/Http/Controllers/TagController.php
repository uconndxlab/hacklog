<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeTagManagement();

        $query = Tag::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('name', 'like', '%' . $search . '%');
        }

        $tags = $query->withCount('projects')->get();

        return view('tags.index', compact('tags'));
    }

    public function create()
    {
        $this->authorizeTagManagement();

        return view('tags.create');
    }

    public function store(Request $request)
    {
        $this->authorizeTagManagement();

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $slug = Tag::slugifyName($validated['name']);

        if ($slug === '') {
            return back()->withErrors(['name' => 'Tag name must include letters or numbers.'])->withInput();
        }

        $exists = Tag::where('slug', $slug)->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'That tag already exists.'])->withInput();
        }

        Tag::create($validated);

        return redirect()->route('tags.index')->with('success', 'Tag created successfully.');
    }

    public function edit(Tag $tag)
    {
        $this->authorizeTagManagement();

        return view('tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag)
    {
        $this->authorizeTagManagement();

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $slug = Tag::slugifyName($validated['name']);

        if ($slug === '') {
            return back()->withErrors(['name' => 'Tag name must include letters or numbers.'])->withInput();
        }

        $exists = Tag::where('slug', $slug)
            ->where('id', '!=', $tag->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'That tag already exists.'])->withInput();
        }

        $tag->update($validated);

        return redirect()->route('tags.index')->with('success', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag)
    {
        $this->authorizeTagManagement();

        $tag->delete();

        return redirect()->route('tags.index')->with('success', 'Tag deleted successfully.');
    }

    protected function authorizeTagManagement(): void
    {
        $user = auth()->user();

        if (!$user || (!$user->isAdmin() && !$user->isTeam())) {
            abort(403, 'You are not authorized to manage tags.');
        }
    }
}
