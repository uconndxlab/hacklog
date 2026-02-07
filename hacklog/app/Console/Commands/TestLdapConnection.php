<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LdapRecord\Container;
use LdapRecord\Models\Entry;

class TestLdapConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:test {netid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test LDAP connection and optionally lookup a NetID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Testing LDAP connection...');
            
            // Get default connection
            $connection = Container::getDefaultConnection();
            $this->info("Connection class: " . get_class($connection));
            
            // Test connection
            if ($connection->isConnected()) {
                $this->info('✓ LDAP connection is active');
            } else {
                $this->info('Attempting to connect...');
                $connection->connect();
                $this->info('✓ LDAP connection established');
            }
            
            // Show connection details (without password)
            $this->info('Connection details:');
            $this->line('  Host: ' . implode(', ', $connection->getConfiguration()->get('hosts')));
            $this->line('  Port: ' . $connection->getConfiguration()->get('port'));
            $this->line('  Base DN: ' . $connection->getConfiguration()->get('base_dn'));
            $this->line('  SSL: ' . ($connection->getConfiguration()->get('use_ssl') ? 'Yes' : 'No'));
            $this->line('  Username: ' . $connection->getConfiguration()->get('username'));
            
            // Test basic search
            $this->info('Testing basic search...');
            $entries = Entry::limit(5)->get();
            $this->info('✓ Basic search successful. Found ' . $entries->count() . ' entries');
            
            // Show sample entry structure
            if ($entries->isNotEmpty()) {
                $this->info('Sample entry attributes:');
                $sampleEntry = $entries->first();
                $attributes = $sampleEntry->getAttributes();
                foreach (array_slice($attributes, 0, 10) as $key => $values) {
                    if (is_array($values)) {
                        $this->line("  {$key}: " . implode(', ', array_slice($values, 0, 3)));
                    } else {
                        $this->line("  {$key}: {$values}");
                    }
                }
            }
            
            // If NetID provided, test lookup
            if ($netid = $this->argument('netid')) {
                $this->info("Testing lookup for NetID: {$netid}");
                
                // Try different search attributes
                $searchAttributes = ['uid', 'samAccountName', 'cn'];
                $found = false;
                
                foreach ($searchAttributes as $attr) {
                    $this->line("  Searching by {$attr}...");
                    $user = Entry::where($attr, '=', $netid)->first();
                    
                    if ($user) {
                        $this->info("  ✓ Found user by {$attr}");
                        $this->line("  Attributes available:");
                        
                        $attributes = $user->getAttributes();
                        foreach ($attributes as $key => $values) {
                            if (is_array($values)) {
                                $this->line("    {$key}: " . implode(', ', array_slice($values, 0, 3)));
                            } else {
                                $this->line("    {$key}: {$values}");
                            }
                        }
                        $found = true;
                        break;
                    } else {
                        $this->line("  - No results for {$attr}");
                    }
                }
                
                if (!$found) {
                    $this->warn("No user found for NetID: {$netid}");
                }
            }
            
        } catch (\Exception $e) {
            $this->error('LDAP test failed: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
}
