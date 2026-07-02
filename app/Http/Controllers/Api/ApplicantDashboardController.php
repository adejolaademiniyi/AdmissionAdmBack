<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ApplicantDashboardController extends Controller
{
    #[OA\Get(
        path: '/applicant/dashboard',
        tags: ['Applicant Dashboard'],
        summary: 'Get the authenticated applicant\'s full application',
        description: 'Returns the complete application record — biodata, next-of-kin, health record and current admission status — for the logged-in applicant.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Full application record',
                content: new OA\JsonContent(ref: '#/components/schemas/ApplicantResource')
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — token does not belong to an applicant'),
        ]
    )]
    public function application(Request $request): JsonResponse
    {
        if (! ($request->user() instanceof Applicant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var Applicant $applicant */
        $applicant = $request->user();
        $applicant->load(['nextOfKin', 'healthRecord']);

        return response()->json($applicant);
    }

    #[OA\Get(
        path: '/applicant/status',
        tags: ['Applicant Dashboard'],
        summary: 'Get the applicant\'s admission status',
        description: 'Returns only the application number and current admission status. Useful for a lightweight status-check call.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admission status',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'application_number', type: 'string', example: 'APP-6A36A61045B86'),
                        new OA\Property(property: 'status',             type: 'string', enum: ['pending', 'under_review', 'approved', 'rejected'], example: 'pending'),
                        new OA\Property(property: 'submitted_at',       type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — token does not belong to an applicant'),
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        if (! ($request->user() instanceof Applicant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var Applicant $applicant */
        $applicant = $request->user();

        return response()->json([
            'application_number' => $applicant->application_number,
            'status'             => $applicant->status,
            'submitted_at'       => $applicant->created_at,
        ]);
    }

    #[OA\Post(
        path: '/applicant/passport',
        tags: ['Applicant Dashboard'],
        summary: 'Upload or replace passport photo',
        description: 'Uploads a passport photo for the authenticated applicant. Send as `multipart/form-data`. Replaces any previously uploaded photo.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['passport'],
                    properties: [
                        new OA\Property(
                            property: 'passport',
                            type: 'string',
                            format: 'binary',
                            description: 'Image file (jpg, jpeg, png). Max 2 MB.'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Passport uploaded successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message',      type: 'string', example: 'Passport uploaded successfully.'),
                        new OA\Property(property: 'passport_url', type: 'string', example: 'http://localhost/storage/passports/1/photo.jpg'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — token does not belong to an applicant'),
            new OA\Response(response: 422, description: 'Validation error (invalid file type or size)'),
        ]
    )]
    public function uploadPassport(Request $request): JsonResponse
    {
        if (! ($request->user() instanceof Applicant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'passport' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        /** @var Applicant $applicant */
        $applicant = $request->user();

        // Delete the old photo if one exists
        if ($applicant->passport_path) {
            Storage::disk('public')->delete($applicant->passport_path);
        }

        $path = $request->file('passport')->store("passports/{$applicant->id}", 'public');
        $applicant->update(['passport_path' => $path]);

        return response()->json([
            'message'      => 'Passport uploaded successfully.',
            'passport_url' => $applicant->passport_url,
        ]);
    }

    #[OA\Get(
        path: '/applicant/print',
        tags: ['Applicant Dashboard'],
        summary: 'Get print-ready data for the authenticated applicant\'s form',
        description: 'Returns a structured payload with all information needed to render and print the admission application form for the logged-in applicant.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Print-ready application data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'print_data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'application_number', type: 'string',  example: 'APP-6672A1B2C3D4'),
                                new OA\Property(property: 'submitted_at',       type: 'string',  format: 'date-time'),
                                new OA\Property(property: 'passport_url',       type: 'string',  nullable: true, example: 'http://localhost:8000/storage/passports/1/photo.jpg'),
                                new OA\Property(property: 'passport_data_uri',  type: 'string',  nullable: true, description: 'Base64 data URI of the passport photo. Use this for printing/PDF so the image is never blocked by CORS.', example: 'data:image/jpeg;base64,/9j/4AAQSkZJRg...'),
                                new OA\Property(property: 'status',             type: 'string',  enum: ['pending', 'under_review', 'approved', 'rejected'], example: 'pending'),
                                new OA\Property(property: 'biodata', type: 'object', properties: [
                                    new OA\Property(property: 'full_name',        type: 'string', example: 'Chukwuemeka James Okafor'),
                                    new OA\Property(property: 'gender',           type: 'string', example: 'Male'),
                                    new OA\Property(property: 'phone_number',     type: 'string', example: '08012345678'),
                                    new OA\Property(property: 'home_address',     type: 'string', example: '12 Aba Road, Owerri'),
                                    new OA\Property(property: 'state',            type: 'string', example: 'Imo'),
                                    new OA\Property(property: 'local_government', type: 'string', example: 'Owerri North'),
                                ]),
                                new OA\Property(property: 'next_of_kin',   ref: '#/components/schemas/NextOfKinInput'),
                                new OA\Property(property: 'health_record', ref: '#/components/schemas/HealthRecordInput'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — token does not belong to an applicant'),
        ]
    )]
    public function print(Request $request): JsonResponse
    {
        if (! ($request->user() instanceof Applicant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var Applicant $applicant */
        $applicant = $request->user();
        $applicant->load(['nextOfKin', 'healthRecord']);

        return response()->json([
            'print_data' => [
                'application_number' => $applicant->application_number,
                'submitted_at'       => $applicant->created_at->toDateTimeString(),
                'passport_url'       => $applicant->passport_url,
                'passport_data_uri'  => $applicant->passportDataUri(),
                'status'             => $applicant->status,
                'biodata'            => [
                    'full_name'         => trim("{$applicant->first_name} {$applicant->middle_name} {$applicant->last_name}"),
                    'gender'            => $applicant->gender,
                    'phone_number'      => $applicant->phone_number,
                    'home_address'      => $applicant->home_address,
                    'state'             => $applicant->state,
                    'local_government'  => $applicant->local_government,
                ],
                'next_of_kin'        => $applicant->nextOfKin,
                'health_record'      => $applicant->healthRecord,
            ],
        ]);
    }
}
