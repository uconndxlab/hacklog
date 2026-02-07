<?php

return [
    App\Providers\AppServiceProvider::class,
    
    // CAS Authentication
    Subfission\Cas\CasServiceProvider::class,
    
    // LDAP for user lookup
    LdapRecord\Laravel\LdapServiceProvider::class,
];
