<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LdapService;
use Illuminate\Http\Request;

/**
 * User Management Controller for Admins
 * 
 * Supports two authentication modes:
 * - CAS: Users identified by NetID with LDAP lookup for details
 * - Local: Users with email/password credentials
 * 
 * The controller adapts based on config('hacklog_auth.driver').
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
    public function index(Request $request)
    {
        $query = User::orderBy('name');
        
        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('active', $request->input('status') === 'active');
        }
        
        $users = $query->get();
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
     * For CAS: NetID is required, name/email are looked up via LDAP.
     * For Local: Name, email, and password are required.
     */
    public function store(Request $request)
    {
        \Log::info('User creation attempt', ['request_data' => $request->except('password', 'password_confirmation')]);

        if (config('hacklog_auth.driver') === 'cas') {
            // CAS Authentication - NetID + LDAP lookup
            $validated = $request->validate([
                'netid' => 'required|string|max:255|unique:users',
                'role' => 'required|in:admin,team,client',
                'active' => 'nullable|boolean',
            ]);

            \Log::info('Validation passed (CAS)', ['validated' => $validated]);

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

            $successMessage = 'User created successfully with details from directory.';
        } else {
            // Local Authentication - Manual entry
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|in:admin,team,client',
                'active' => 'nullable|boolean',
            ]);

            \Log::info('Validation passed (Local)', ['validated' => array_diff_key($validated, ['password' => ''])]);

            // Create user with manual data
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'role' => $validated['role'],
                'active' => $request->boolean('active', true),
            ];

            $successMessage = 'User created successfully.';
        }

        \Log::info('Creating user', ['user_data' => array_diff_key($userData, ['password' => ''])]);

        try {
            $user = User::create($userData);
            \Log::info('User created successfully', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            \Log::error('User creation failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withErrors([
                'email' => 'Failed to create user: ' . $e->getMessage()
            ])->withInput();
        }

        return redirect()->route('users.index')->with('success', $successMessage);
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
     * For CAS: Allow updating role and active status. Name/email can be refreshed from LDAP.
     * For Local: Allow updating name, email, password, role, and active status.
     */
    public function update(Request $request, User $user)
    {
        if (config('hacklog_auth.driver') === 'cas') {
            // CAS Authentication - limited fields + optional LDAP refresh
            $validated = $request->validate([
                'role' => 'required|in:admin,team,client',
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
                    $message = 'User updated successfully. Details refreshed from directory.';
                } else {
                    return back()->withErrors([
                        'refresh_ldap' => 'Could not refresh user details from directory.'
                    ]);
                }
            } else {
                $message = 'User updated successfully.';
            }
        } else {
            // Local Authentication - editable fields
            $validationRules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
                'role' => 'required|in:admin,team,client',
            ];

            // Password is optional - only validate if provided
            if ($request->filled('password')) {
                $validationRules['password'] = 'string|min:8|confirmed';
            }

            $validated = $request->validate($validationRules);

            // Handle active checkbox
            $validated['active'] = $request->has('active');

            // Only update password if provided
            if ($request->filled('password')) {
                $validated['password'] = bcrypt($request->password);
            } else {
                unset($validated['password']);
            }

            $message = 'User updated successfully.';
        }

        $user->update($validated);

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
