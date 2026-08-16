<?php

namespace FluentForm\Framework\Validator;

class ValidationException extends \Exception
{
    /** @return array<string, mixed> */
    public function errors(): array
    {
        return array();
    }
}

