<?php

namespace App\Service;

class TokenService
{
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
