<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

/**
 * Base Validator class for specialized business entity validators
 */
abstract class BaseValidator
{
    protected array $rules = [];
    protected array $errors = [];

    public function validate(array $data): bool
    {
        $validator = Validator::make($data, $this->rules);
        if ($validator->fails()) {
            $this->errors = $validator->errors();
            return false;
        }
        return true;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
