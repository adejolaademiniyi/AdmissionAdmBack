<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicantLoginRequest;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ApplicantAuthController extends Controller
{
    #[OA\Post(
        path: '/applicant/auth/login',
        tags: ['Applicant Auth'],
        summary: 'Applicant login',
        description: 'Authenticates an applicant using their application number and password. Returns a Bearer token for use on all protected applicant endpoints.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email',    type: 'string', format: 'email',    example: 'chukwuemeka@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret1234'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'accessToken', type: 'string', example: '2|abc123token...'),
                        new OA\Property(
                            property: 'applicant',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id',                 type: 'integer', example: 1),
                                new OA\Property(property: 'application_number', type: 'string',  example: 'APP-6A36A61045B86'),
                                new OA\Property(property: 'first_name',         type: 'string',  example: 'Chukwuemeka'),
                                new OA\Property(property: 'last_name',          type: 'string',  example: 'Okafor'),
                                new OA\Property(property: 'email',              type: 'string',  example: 'chukwuemeka@example.com'),
                                new OA\Property(property: 'status',             type: 'string',  example: 'pending'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Invalid credentials'),
        ]
    )]
    public function login(ApplicantLoginRequest $request): JsonResponse
    {
        $applicant = Applicant::where('email', $request->email)->first();

        if (! $applicant || ! Hash::check($request->password, $applicant->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke any existing applicant tokens to enforce single-session
        $applicant->tokens()->where('name', 'applicant-token')->delete();

        $token = $applicant->createToken('applicant-token')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            'applicant'   => [
                'id'                 => $applicant->id,
                'application_number' => $applicant->application_number,
                'first_name'         => $applicant->first_name,
                'last_name'          => $applicant->last_name,
                'email'              => $applicant->email,
                'status'             => $applicant->status,
            ],
        ]);
    }

    #[OA\Post(
        path: '/applicant/auth/logout',
        tags: ['Applicant Auth'],
        summary: 'Applicant logout',
        description: 'Revokes the current applicant Bearer token.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully.')]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — token does not belong to an applicant'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        if (! ($request->user() instanceof Applicant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    #[OA\Get(
        path: '/applicant/auth/me',
        tags: ['Applicant Auth'],
        summary: 'Get the authenticated applicant profile',
        description: 'Returns basic profile information for the currently authenticated applicant.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Applicant profile',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id',                 type: 'integer', example: 1),
                        new OA\Property(property: 'application_number', type: 'string',  example: 'APP-6A36A61045B86'),
                        new OA\Property(property: 'first_name',         type: 'string',  example: 'Chukwuemeka'),
                        new OA\Property(property: 'last_name',          type: 'string',  example: 'Okafor'),
                        new OA\Property(property: 'email',              type: 'string',  example: 'chukwuemeka@example.com'),
                        new OA\Property(property: 'status',             type: 'string',  example: 'pending'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — token does not belong to an applicant'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        if (! ($request->user() instanceof Applicant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        /** @var Applicant $applicant */
        $applicant = $request->user();

        return response()->json([
            'id'                 => $applicant->id,
            'application_number' => $applicant->application_number,
            'first_name'         => $applicant->first_name,
            'last_name'          => $applicant->last_name,
            'email'              => $applicant->email,
            'status'             => $applicant->status,
        ]);
    }
}
