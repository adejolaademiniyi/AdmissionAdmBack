<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // On PUT / PATCH the fields are optional so a status-only update works.
        // On POST (store) every field is required.
        $req = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // Biodata
            'first_name'              => [$req, 'string', 'max:100'],
            'last_name'               => [$req, 'string', 'max:100'],
            'middle_name'             => ['nullable', 'string', 'max:100'],
            'gender'                  => [$req, 'in:Male,Female'],
            'phone_number'            => [$req, 'string', 'max:20'],
            'email'                   => [
                $req,
                'email',
                'max:255',
                $this->isMethod('POST')
                    ? 'unique:applicants,email'
                    : Rule::unique('applicants', 'email')->ignore($this->route('id')),
            ],
            'password'                => $this->isMethod('POST') ? ['required', 'string', 'min:8'] : [],

            // Passport photo (optional on both store and update)
            'passport'                => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            'home_address'            => [$req, 'string'],
            'state'                   => [$req, 'string', 'max:100'],
            'local_government'        => [$req, 'string', 'max:100'],

            // Status (admin update only)
            'status'                  => ['sometimes', 'in:pending,under_review,approved,rejected'],

            // Next of Kin
            'next_of_kin'                    => [$req, 'array'],
            'next_of_kin.name'               => [$req, 'string', 'max:200'],
            'next_of_kin.relationship'        => [$req, 'in:Father,Mother,Guardian,Sibling,Spouse,Other'],
            'next_of_kin.phone_number'        => [$req, 'string', 'max:20'],
            'next_of_kin.home_address'        => [$req, 'string'],

            // Health Record
            'health_record'               => [$req, 'array'],
            'health_record.blood_group'   => [$req, 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'health_record.genotype'      => [$req, 'in:AA,AS,AC,SS,SC,CC'],
            'health_record.ailments'      => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.in'                         => 'Gender must be Male or Female.',
            'next_of_kin.relationship.in'        => 'Relationship must be one of: Father, Mother, Guardian, Sibling, Spouse, Other.',
            'health_record.blood_group.in'       => 'Invalid blood group. Accepted values: A+, A-, B+, B-, AB+, AB-, O+, O-.',
            'health_record.genotype.in'          => 'Invalid genotype. Accepted values: AA, AS, AC, SS, SC, CC.',
        ];
    }
}
