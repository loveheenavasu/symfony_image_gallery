<?php

// config/packages/security.php
use App\Entity\User;
use Symfony\Config\SecurityConfig;

return static function (SecurityConfig $security) {
    // ...

    $security->provider('app_user_provider')
        ->entity()
            ->class(User::class)
            ->property('email')
    ;

    $security->passwordHasher(PasswordAuthenticatedUserInterface::class)
        ->algorithm('auto')
    ;
    
};