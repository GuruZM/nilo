<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdatePlanRequest extends StorePlanRequest
{
    /**
     * The slug is deliberately absent: application logic keys off the "free"
     * and "enterprise" slugs, so it is fixed once the plan exists.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->forget('slug')
            ->all();
    }
}
