<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Admission Application API',
    version: '1.0.0',
    description: 'REST API for the Secondary School Admission Registration System. Manages applicant biodata, next-of-kin information, and health records.',
    contact: new OA\Contact(email: 'admin@school.edu.ng')
)]
#[OA\Server(url: '/api/v1', description: 'Version 1')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter the token returned by POST /api/v1/auth/login'
)]

// ── Schemas ──────────────────────────────────────────────────────────────────

#[OA\Schema(
    schema: 'ApplicantBiodata',
    required: ['first_name', 'last_name', 'gender', 'phone_number', 'home_address', 'state', 'local_government'],
    properties: [
        new OA\Property(property: 'first_name',       type: 'string',  example: 'Chukwuemeka'),
        new OA\Property(property: 'last_name',        type: 'string',  example: 'Okafor'),
        new OA\Property(property: 'middle_name',      type: 'string',  nullable: true, example: 'James'),
        new OA\Property(property: 'gender',           type: 'string',  enum: ['Male', 'Female'], example: 'Male'),
        new OA\Property(property: 'phone_number',     type: 'string',  example: '08012345678'),
        new OA\Property(property: 'home_address',     type: 'string',  example: '12 Aba Road, Owerri'),
        new OA\Property(property: 'state',            type: 'string',  example: 'Imo'),
        new OA\Property(property: 'local_government', type: 'string',  example: 'Owerri North'),
    ]
)]
#[OA\Schema(
    schema: 'NextOfKinInput',
    required: ['name', 'relationship', 'phone_number', 'home_address'],
    properties: [
        new OA\Property(property: 'name',         type: 'string', example: 'Ngozi Okafor'),
        new OA\Property(property: 'relationship', type: 'string', enum: ['Father', 'Mother', 'Guardian', 'Sibling', 'Spouse', 'Other'], example: 'Mother'),
        new OA\Property(property: 'phone_number', type: 'string', example: '08098765432'),
        new OA\Property(property: 'home_address', type: 'string', example: '12 Aba Road, Owerri'),
    ]
)]
#[OA\Schema(
    schema: 'HealthRecordInput',
    required: ['blood_group', 'genotype'],
    properties: [
        new OA\Property(property: 'blood_group', type: 'string', enum: ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], example: 'O+'),
        new OA\Property(property: 'genotype',    type: 'string', enum: ['AA', 'AS', 'AC', 'SS', 'SC', 'CC'], example: 'AA'),
        new OA\Property(property: 'ailments',    type: 'string', nullable: true, example: 'Mild asthma'),
    ]
)]
#[OA\Schema(
    schema: 'ApplicantResource',
    properties: [
        new OA\Property(property: 'id',                 type: 'integer', example: 1),
        new OA\Property(property: 'application_number', type: 'string',  example: 'APP-6672A1B2C3D4'),
        new OA\Property(property: 'first_name',         type: 'string',  example: 'Chukwuemeka'),
        new OA\Property(property: 'last_name',          type: 'string',  example: 'Okafor'),
        new OA\Property(property: 'middle_name',        type: 'string',  nullable: true, example: 'James'),
        new OA\Property(property: 'gender',             type: 'string',  enum: ['Male', 'Female'], example: 'Male'),
        new OA\Property(property: 'phone_number',       type: 'string',  example: '08012345678'),
        new OA\Property(property: 'email',              type: 'string',  format: 'email', example: 'chukwuemeka@example.com'),
        new OA\Property(property: 'passport_url',       type: 'string',  nullable: true, example: 'http://localhost/storage/passports/1/photo.jpg'),
        new OA\Property(property: 'home_address',       type: 'string',  example: '12 Aba Road, Owerri'),
        new OA\Property(property: 'state',              type: 'string',  example: 'Imo'),
        new OA\Property(property: 'local_government',   type: 'string',  example: 'Owerri North'),
        new OA\Property(property: 'status',             type: 'string',  enum: ['pending', 'under_review', 'approved', 'rejected'], example: 'pending'),
        new OA\Property(property: 'created_at',         type: 'string',  format: 'date-time'),
        new OA\Property(property: 'updated_at',         type: 'string',  format: 'date-time'),
        new OA\Property(property: 'next_of_kin',        ref: '#/components/schemas/NextOfKinInput'),
        new OA\Property(property: 'health_record',      ref: '#/components/schemas/HealthRecordInput'),
    ]
)]
#[OA\Schema(
    schema: 'ApplicantRequest',
    required: ['first_name', 'last_name', 'gender', 'phone_number', 'email', 'password', 'home_address', 'state', 'local_government', 'next_of_kin', 'health_record'],
    properties: [
        new OA\Property(property: 'first_name',       type: 'string',  example: 'Chukwuemeka'),
        new OA\Property(property: 'last_name',        type: 'string',  example: 'Okafor'),
        new OA\Property(property: 'middle_name',      type: 'string',  nullable: true, example: 'James'),
        new OA\Property(property: 'gender',           type: 'string',  enum: ['Male', 'Female'], example: 'Male'),
        new OA\Property(property: 'phone_number',     type: 'string',  example: '08012345678'),
        new OA\Property(property: 'email',            type: 'string',  format: 'email', example: 'chukwuemeka@example.com'),
        new OA\Property(property: 'password',         type: 'string',  format: 'password', minLength: 8, example: 'secret1234'),
        new OA\Property(property: 'passport',         type: 'string',  format: 'binary',   description: 'Optional passport photo (jpg/jpeg/png, max 2 MB). Send as multipart/form-data when included.'),
        new OA\Property(property: 'home_address',     type: 'string',  example: '12 Aba Road, Owerri'),
        new OA\Property(property: 'state',            type: 'string',  example: 'Imo'),
        new OA\Property(property: 'local_government', type: 'string',  example: 'Owerri North'),
        new OA\Property(property: 'next_of_kin',      ref: '#/components/schemas/NextOfKinInput'),
        new OA\Property(property: 'health_record',    ref: '#/components/schemas/HealthRecordInput'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string'))
        ),
    ]
)]
class SwaggerInfo {}
