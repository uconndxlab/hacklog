<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LdapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Subfission\Cas\Facades\Cas;

/**
 * CAS Authentication Controller
 * 
 * Handles NetID-based login via CAS with local user authorization.
 * Authentication flow:
 * 1. User visits /login -> redirected to CAS
 * 2. CAS authenticates and returns NetID 
 * 3. Check if local user exists and is active
 * 4. If authorized, log user in via Laravel Auth
 * 5. Redirect to dashboard
 */
class AuthController extends Controller
{
    protected LdapService $ldapService;

    public function __construct(LdapService $ldapService)
    {
        $this->ldapService = $ldapService;
    }

    /**
     * Show login page with NetID instructions.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Initiate CAS login - redirect user to CAS server.
     * When CAS_MASQUERADE is set, skip actual CAS and use masquerade NetID.
     */
    public function login()
    {
        $masqueradeNetid = config('cas.cas_masquerade');
        
        \Log::info('Login attempt', [
            'masquerade_enabled' => !empty($masqueradeNetid),
            'masquerade_netid' => $masqueradeNetid
        ]);
        
        // Check if masquerade is enabled (dev environment only)
        if ($masqueradeNetid) {
            // In masquerade mode, skip CAS and use the configured NetID
            \Log::info('Using CAS masquerade mode', ['netid' => $masqueradeNetid]);
            
            return $this->handleMasqueradeLogin($masqueradeNetid);
        }
        
        \Log::info('Proceeding with real CAS authentication');
        
        // Force authentication with CAS server
        Cas::authenticate();
        
        // After CAS authentication, handle the callback
        return $this->handleCasCallback();
    }

    /**
     * Handle masquerade login (development only).
     */
    protected function handleMasqueradeLogin($netid)
    {
        // Look up local user by NetID
        $user = User::where('netid', $netid)->first();

        // Check if user exists locally
        if (!$user) {
            \Log::warning('Masquerade login denied - no local user found', ['netid' => $netid]);
            return redirect()->route('login')->with('error', 
                'Access denied. NetID "' . $netid . '" is not authorized for this application. Please create this user first.');
        }

        // Check if user account is active
        if (!$user->isActive()) {
            \Log::warning('Masquerade login denied - inactive user', ['netid' => $netid, 'user_id' => $user->id]);
            return redirect()->route('login')->with('error', 
                'Your account is inactive. Please contact an administrator.');
        }

        // Optionally refresh user details from LDAP on login
        $this->refreshUserFromLdap($user);

        // Log user in with Laravel Auth
        Auth::login($user, true); // true = remember user

        \Log::info('Successful masquerade login', ['netid' => $netid, 'user_id' => $user->id]);

        // Redirect to dashboard
        return redirect()->route('dashboard');
    }

    /**
     * Handle CAS callback after successful authentication.
     * This is called automatically after CAS::authenticate() succeeds.
     */
    public function handleCasCallback()
    {
        // Verify user is authenticated with CAS
        if (!Cas::isAuthenticated()) {
            return redirect()->route('login')->with('error', 'CAS authentication failed. Please try again.');
        }

        // Get NetID from CAS
        $netid = Cas::user();
        
        if (!$netid) {
            return redirect()->route('login')->with('error', 'No NetID received from CAS. Please contact support.');
        }

        // Look up local user by NetID
        $user = User::where('netid', $netid)->first();

        // Check if user exists locally
        if (!$user) {
            \Log::warning('CAS login denied - no local user found', ['netid' => $netid]);
            return redirect()->route('login')->with('error', 
                'Access denied. Your NetID is not authorized for this application. Please contact an administrator.');
        }

        // Check if user account is active
        if (!$user->isActive()) {
            \Log::warning('CAS login denied - inactive user', ['netid' => $netid, 'user_id' => $user->id]);
            return redirect()->route('login')->with('error', 
                'Your account is inactive. Please contact an administrator.');
        }

        // Optionally refresh user details from LDAP on login
        $this->refreshUserFromLdap($user);

        // Log user in with Laravel Auth
        Auth::login($user, true); // true = remember user

        \Log::info('Successful CAS login', ['netid' => $netid, 'user_id' => $user->id]);

        // Redirect to dashboard
        return redirect()->route('dashboard');
    }

    /**
     * Logout user from both Laravel and CAS.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        
        // Log the logout
        if ($user) {
            \Log::info('User logout', ['netid' => $user->netid, 'user_id' => $user->id]);
        }

        // Logout from Laravel
        Auth::logout();
        
        // Invalidate session
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Logout from CAS (this will redirect to CAS logout page)
        Cas::logout();
        
        // This line will only execute if CAS logout doesn't redirect
        return redirect()->route('login')->with('message', 'You have been logged out.');
    }

    /**
     * Refresh user's name and email from LDAP (optional enhancement).
     */
    protected function refreshUserFromLdap(User $user): void
    {
        try {
            $ldapData = $this->ldapService->lookupUser($user->netid);
            
            if ($ldapData) {
                // Only update if the data has changed to avoid unnecessary DB writes
                $needsUpdate = false;
                
                if ($user->name !== $ldapData['name']) {
                    $user->name = $ldapData['name'];
                    $needsUpdate = true;
                }
                
                if ($user->email !== $ldapData['email']) {
                    $user->email = $ldapData['email'];
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $user->save();
                    \Log::info('Updated user details from LDAP', ['netid' => $user->netid]);
                }
            }
        } catch (\Exception $e) {
            // Don't fail login if LDAP refresh fails
            \Log::warning('Failed to refresh user from LDAP', [
                'netid' => $user->netid,
                'error' => $e->getMessage()
            ]);
        }
    }
}
