<?php

namespace App\Services;

use LdapRecord\Container;
use LdapRecord\Models\Entry;

/**
 * Service for performing LDAP user lookups.
 * Used when creating users and optionally for refreshing user data on login.
 */
class LdapService
{
    /**
     * Look up a user by NetID and return their attributes.

    /**
     * Look up a user by NetID and return their attributes.
     * 
     * @param string $netid
     * @return array|null Returns array with 'name' and 'email' keys, or null if not found
     */
    public function lookupUser(string $netid): ?array
    {
        try {
            // Search for user by NetID using Entry model
            // Try multiple common NetID attribute names
            $user = Entry::where('uid', '=', $netid)->first();
            
            if (!$user) {
                // Try alternative attribute names
                $user = Entry::where('samAccountName', '=', $netid)->first();
            }
            
            if (!$user) {
                // Try another common attribute
                $user = Entry::where('cn', '=', $netid)->first();
            }
            
            if (!$user) {
                \Log::info('LDAP lookup - user not found', ['netid' => $netid]);
                return null;
            }

            \Log::info('LDAP lookup - user found', [
                'netid' => $netid,
                'attributes' => $user->getAttributes()
            ]);

            // Extract name and email from LDAP attributes
            // Try multiple attribute names for name
            $name = $this->getAttributeValue($user, 'displayName') 
                   ?? $this->getAttributeValue($user, 'cn')
                   ?? $this->getAttributeValue($user, 'givenName')
                   ?? $this->getAttributeValue($user, 'name')
                   ?? $this->getAttributeValue($user, 'fullName');
                   
            // Try multiple attribute names for email
            $email = $this->getAttributeValue($user, 'mail')
                    ?? $this->getAttributeValue($user, 'email')
                    ?? $this->getAttributeValue($user, 'userPrincipalName')
                    ?? $this->getAttributeValue($user, 'emailAddress');

            // If email is just the NetID or missing, construct it
            if (!$email || $email === $netid || strpos($email, '@') === false) {
                $email = $netid . '@uconn.edu';
            }

            return [
                'name' => $name ?: $netid, // Fallback to NetID if no display name
                'email' => $email,
            ];

        } catch (\Exception $e) {
            // Log the error but don't break the flow
            \Log::error('LDAP lookup failed for NetID: ' . $netid, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Search for users by name (partial match).
     * 
     * @param string $searchTerm
     * @return array Array of users with 'netid', 'name', and 'email' keys
     */
    public function searchUsersByName(string $searchTerm): array
    {
        try {
            $searchTerm = trim($searchTerm);
            
            if (empty($searchTerm)) {
                return [];
            }

            // Search for users by name using wildcards
            // Try multiple common name attribute names
            $users = collect();
            
            // Search by NetID first (exact or partial match)
            $results = Entry::where('uid', 'contains', $searchTerm)->limit(20)->get();
            $users = $users->merge($results);
            
            if ($users->count() < 10) {
                $results = Entry::where('samAccountName', 'contains', $searchTerm)->limit(20)->get();
                $users = $users->merge($results);
            }
            
            // Search by display name
            if ($users->count() < 10) {
                $results = Entry::where('displayName', 'contains', $searchTerm)->limit(20)->get();
                $users = $users->merge($results);
            }
            
            // Search by common name if we don't have enough results
            if ($users->count() < 10) {
                $results = Entry::where('cn', 'contains', $searchTerm)->limit(20)->get();
                $users = $users->merge($results);
            }
            
            // Search by surname if we still don't have enough results
            if ($users->count() < 10) {
                $results = Entry::where('sn', 'contains', $searchTerm)->limit(20)->get();
                $users = $users->merge($results);
            }

            // Remove duplicates and map to our standard format
            $users = $users->unique(function($user) {
                // Try to get a unique identifier
                return $this->getAttributeValue($user, 'uid') 
                    ?? $this->getAttributeValue($user, 'samAccountName')
                    ?? $this->getAttributeValue($user, 'cn');
            });

            $formattedUsers = [];
            foreach ($users as $user) {
                // Get NetID
                $netid = $this->getAttributeValue($user, 'uid')
                    ?? $this->getAttributeValue($user, 'samAccountName')
                    ?? $this->getAttributeValue($user, 'cn');
                
                if (!$netid) {
                    continue; // Skip if we can't determine NetID
                }

                // Get name
                $name = $this->getAttributeValue($user, 'displayName')
                    ?? $this->getAttributeValue($user, 'cn')
                    ?? $this->getAttributeValue($user, 'givenName')
                    ?? $this->getAttributeValue($user, 'name')
                    ?? $this->getAttributeValue($user, 'fullName')
                    ?? $netid;

                // Get email
                $email = $this->getAttributeValue($user, 'mail')
                    ?? $this->getAttributeValue($user, 'email')
                    ?? $this->getAttributeValue($user, 'userPrincipalName')
                    ?? $this->getAttributeValue($user, 'emailAddress');

                // Construct email if missing
                if (!$email || $email === $netid || strpos($email, '@') === false) {
                    $email = $netid . '@uconn.edu';
                }

                $formattedUsers[] = [
                    'netid' => $netid,
                    'name' => $name,
                    'email' => $email,
                ];
            }

            \Log::info('LDAP search by name', [
                'search_term' => $searchTerm,
                'results_count' => count($formattedUsers)
            ]);

            return array_slice($formattedUsers, 0, 20); // Limit to 20 results

        } catch (\Exception $e) {
            \Log::error('LDAP search by name failed', [
                'search_term' => $searchTerm,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get the first value of an LDAP attribute.
     */
    protected function getAttributeValue($user, string $attribute): ?string
    {
        $value = $user->getFirstAttribute($attribute);
        return empty($value) ? null : trim($value);
    }
}