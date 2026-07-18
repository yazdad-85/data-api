<?php

use App\Support\Security\RequestId;

if (! function_exists('request_id')) {
    function request_id(): ?string
    {
        return RequestId::current();
    }
}
