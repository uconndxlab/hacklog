<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LdapService;
use Illuminate\Http\Request;

/**
 * User Management Controller for Admins
 * 
 * Users are identified by NetID, not email/password.
 * LDAP is used to look up user details when creating users.
 */
class UsersController extends Controller
{
    protected LdapService $ldapService;

    public function __construct(LdapService $ldapService)
    {
        $this->ldapService = $ldapService;
    }

    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created user.
     * NetID is required, name/email are looked up via LDAP.
     */
    public function store(Request $request)
    {
        \Log::info('User creation attempt', ['request_data' => $request->all()]);

        $validated = $request->validate([
            'netid' => 'required|string|max:255|unique:users',
            'role' => 'required|in:admin,user',
            'active' => 'nullable|boolean',
        ]);

        \Log::info('Validation passed', ['validated' => $validated]);

        // Look up user details from LDAP
        $ldapData = $this->ldapService->lookupUser($validated['netid']);
        
        if (!$ldapData) {
            \Log::warning('LDAP lookup failed for user creation', ['netid' => $validated['netid']]);
            return back()->withErrors([
                'netid' => 'NetID not found in directory. Please verify the NetID is correct.'
            ])->withInput();
        }

        \Log::info('LDAP lookup succeeded', ['ldap_data' => $ldapData]);

        // Create user with LDAP data
        $userData = [
            'netid' => $validated['netid'],
            'name' => $ldapData['name'],
            'email' => $ldapData['email'],
            'role' => $validated['role'],
            'active' => $request->boolean('active', true),
            'password' => '', // Not used for CAS authentication
        ];

        \Log::info('Creating user', ['user_data' => $userData]);

        try {
            $user = User::create($userData);
            \Log::info('User created successfully', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            \Log::error('User creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withErrors([
                'netid' => 'Failed to create user: ' . $e->getMessage()
            ])->withInput();
        }

        return redirect()->route('users.index')
            ->with('success', 'User created successfully with details from directory.');
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified user.
     * Allow updating role and active status.
     * Name/email can be refreshed from LDAP.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:admin,user',
            'refresh_ldap' => 'boolean',
        ]);

        // Handle active checkbox - if unchecked, it won't be present in request
        $validated['active'] = $request->has('active');

        // Optionally refresh name/email from LDAP
        if ($request->has('refresh_ldap')) {
            $ldapData = $this->ldapService->lookupUser($user->netid);
            if ($ldapData) {
                $validated['name'] = $ldapData['name'];
                $validated['email'] = $ldapData['email'];
            } else {
                return back()->withErrors([
                    'refresh_ldap' => 'Could not refresh user details from directory.'
                ]);
            }
        }

        $user->update($validated);

        $message = 'User updated successfully.';
        if ($request->has('refresh_ldap') && isset($ldapData)) {
            $message .= ' Details refreshed from directory.';
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    /**
     * AJAX endpoint to search for users by name or NetID.
     * Used for searching before creating users.
     */
    public function searchUsers(Request $request)
    {
        $request->validate([
            'search' => 'required|string|max:255',
        ]);

        $searchTerm = trim($request->search);
        $results = $this->ldapService->searchUsersByName($searchTerm);

        return response()->json([
            'results' => $results,
            'count' => count($results)
        ]);
    }

    /**
     * AJAX endpoint to look up NetID and return user details.
     * Used for previewing user info before creation.
     */
    public function lookupNetid(Request $request)
    {
        $request->validate([
            'netid' => 'required|string|max:255',
        ]);

        $ldapData = $this->ldapService->lookupUser($request->netid);

        if (!$ldapData) {
            return response()->json([
                'found' => false,
                'message' => 'NetID not found in directory'
            ], 404);
        }

        return response()->json([
            'found' => true,
            'name' => $ldapData['name'],
            'email' => $ldapData['email']
        ]);
    }

    /**
     * Delete a user and clean up related assignments.
     */
    public function destroy(User $user)
    {
        // Prevent deletion of the currently logged-in user
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        try {
            $userName = $user->name;
            $userNetid = $user->netid;
            
            // The task_user pivot table has CASCADE DELETE foreign keys,
            // so task assignments will be automatically removed
            $user->delete();

            return redirect()->route('users.index')
                ->with('success', "User '{$userName}' ({$userNetid}) has been deleted successfully. All task assignments have been removed.");

        } catch (\Exception $e) {
            return redirect()->route('users.index')
                ->with('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }
}
