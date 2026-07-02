<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicantRequest;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ApplicantController extends Controller
{
    #[OA\Get(
        path: '/applicants',
        tags: ['Applicants'],
        summary: 'List applicants with optional filtering and pagination',
        parameters: [
            new OA\Parameter(name: 'page',     in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'pageSize', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 10)),
            new OA\Parameter(name: 'search',   in: 'query', required: false, description: 'Search by name, phone or application number', schema: new OA\Schema(type: 'string', example: 'john')),
            new OA\Parameter(name: 'status',   in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending', 'under_review', 'approved', 'rejected'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of applicants',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data',         type: 'array', items: new OA\Items(ref: '#/components/schemas/ApplicantResource')),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'last_page',    type: 'integer', example: 5),
                        new OA\Property(property: 'per_page',     type: 'integer', example: 10),
                        new OA\Property(property: 'total',        type: 'integer', example: 95),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $pageSize = (int) $request->query('pageSize', 10);
        $search   = $request->query('search');
        $status   = $request->query('status');

        $query = Applicant::with(['nextOfKin', 'healthRecord'])
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name',         'like', "%{$search}%")
                  ->orWhere('last_name',         'like', "%{$search}%")
                  ->orWhere('middle_name',       'like', "%{$search}%")
                  ->orWhere('phone_number',      'like', "%{$search}%")
                  ->orWhere('application_number','like', "%{$search}%");
            });
        }

        if ($status && in_array($status, Applicant::STATUSES)) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate($pageSize));
    }

    #[OA\Get(
        path: '/applicants/lookup',
        tags: ['Applicants'],
        summary: 'Look up an application by application number (public)',
        parameters: [
            new OA\Parameter(name: 'applicationNumber', in: 'query', required: true, description: 'e.g. APP-6A36A61045B86', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200,  description: 'Application found', content: new OA\JsonContent(ref: '#/components/schemas/ApplicantResource')),
            new OA\Response(response: 404,  description: 'Application not found'),
            new OA\Response(response: 422,  description: 'applicationNumber query param is required'),
        ]
    )]
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'applicationNumber' => ['required', 'string'],
        ]);

        $applicant = Applicant::with(['nextOfKin', 'healthRecord'])
            ->where('application_number', $request->query('applicationNumber'))
            ->firstOrFail();

        return response()->json($applicant);
    }

    #[OA\Post(
        path: "/applicants",
        tags: ["Applicants"],
        summary: "Submit a new admission application",
        description: "Creates an applicant record together with next-of-kin and health record in one request. An application number is auto-generated. Send as `multipart/form-data` when including a passport photo.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/ApplicantRequest')
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Application submitted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message",   type: "string", example: "Application submitted successfully."),
                        new OA\Property(property: "applicant", ref: "#/components/schemas/ApplicantResource"),
                    ]
                )
            ),
            new OA\Response(response: 422, description: "Validation error", content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")),
        ]
    )]
    public function store(StoreApplicantRequest $request): JsonResponse
    {
        $applicant = DB::transaction(function () use ($request) {
            $applicant = Applicant::create($request->only([
                "first_name", "last_name", "middle_name",
                "gender", "phone_number", "email", "password",
                "home_address", "state", "local_government",
            ]));

            $applicant->nextOfKin()->create($request->input("next_of_kin"));
            $applicant->healthRecord()->create($request->input("health_record"));

            if ($request->hasFile('passport')) {
                $applicant->update([
                    'passport_path' => $request->file('passport')->store("passports/{$applicant->id}", 'public'),
                ]);
            }

            return $applicant;
        });

        $applicant->load(["nextOfKin", "healthRecord"]);
        return response()->json(["message" => "Application submitted successfully.", "applicant" => $applicant], 201);
    }

    #[OA\Get(
        path: "/applicants/{id}",
        tags: ["Applicants"],
        summary: "Get a single application",
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, description: "Applicant ID", schema: new OA\Schema(type: "integer", example: 1))],
        responses: [
            new OA\Response(response: 200, description: "Applicant details", content: new OA\JsonContent(ref: "#/components/schemas/ApplicantResource")),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $applicant = Applicant::with(["nextOfKin", "healthRecord"])->findOrFail($id);
        return response()->json($applicant);
    }

    #[OA\Put(
        path: "/applicants/{id}",
        tags: ["Applicants"],
        summary: "Update an existing application",
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, description: "Applicant ID", schema: new OA\Schema(type: "integer", example: 1))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/ApplicantRequest')
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Application updated successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message",   type: "string", example: "Application updated successfully."),
                        new OA\Property(property: "applicant", ref: "#/components/schemas/ApplicantResource"),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Not found"),
            new OA\Response(response: 422, description: "Validation error", content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")),
        ]
    )]
    public function update(StoreApplicantRequest $request, string $id): JsonResponse
    {
        $applicant = DB::transaction(function () use ($request, $id) {
            $applicant = Applicant::findOrFail($id);

            // Update whichever biodata fields were sent (empty on status-only requests)
            $applicant->update($request->only([
                "first_name", "last_name", "middle_name",
                "gender", "phone_number", "home_address",
                "state", "local_government", "status",
            ]));

            if ($request->has("next_of_kin")) {
                $applicant->nextOfKin()->updateOrCreate(
                    ["applicant_id" => $applicant->id],
                    $request->input("next_of_kin")
                );
            }

            if ($request->has("health_record")) {
                $applicant->healthRecord()->updateOrCreate(
                    ["applicant_id" => $applicant->id],
                    $request->input("health_record")
                );
            }

            if ($request->hasFile('passport')) {
                if ($applicant->passport_path) {
                    Storage::disk('public')->delete($applicant->passport_path);
                }
                $applicant->update([
                    'passport_path' => $request->file('passport')->store("passports/{$applicant->id}", 'public'),
                ]);
            }

            return $applicant;
        });

        $applicant->load(["nextOfKin", "healthRecord"]);
        return response()->json(["message" => "Application updated successfully.", "applicant" => $applicant]);
    }

    #[OA\Delete(
        path: "/applicants/{id}",
        tags: ["Applicants"],
        summary: "Delete an application",
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, description: "Applicant ID", schema: new OA\Schema(type: "integer", example: 1))],
        responses: [
            new OA\Response(response: 200, description: "Application deleted", content: new OA\JsonContent(properties: [new OA\Property(property: "message", type: "string", example: "Application deleted successfully.")])),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function destroy(string $id): JsonResponse
    {
        Applicant::findOrFail($id)->delete();
        return response()->json(["message" => "Application deleted successfully."]);
    }

    #[OA\Get(
        path: "/applicants/{id}/print",
        tags: ["Applicants"],
        summary: "Get print-ready data for an application form",
        description: "Returns a structured payload with all information needed to render and print the admission application form.",
        parameters: [new OA\Parameter(name: "id", in: "path", required: true, description: "Applicant ID", schema: new OA\Schema(type: "integer", example: 1))],
        responses: [
            new OA\Response(
                response: 200,
                description: "Print-ready application data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: "print_data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "application_number", type: "string", example: "APP-6672A1B2C3D4"),
                                new OA\Property(property: "submitted_at",       type: "string", format: "date-time"),
                                new OA\Property(property: "passport_url",       type: "string", nullable: true, example: "http://localhost:8000/storage/passports/1/photo.jpg"),
                                new OA\Property(property: "passport_data_uri",  type: "string", nullable: true, description: "Base64 data URI of the passport photo. Use this for printing/PDF so the image is never blocked by CORS.", example: "data:image/jpeg;base64,/9j/4AAQSkZJRg..."),
                                new OA\Property(property: "biodata", type: "object", properties: [
                                    new OA\Property(property: "full_name",        type: "string", example: "Chukwuemeka James Okafor"),
                                    new OA\Property(property: "gender",           type: "string", example: "Male"),
                                    new OA\Property(property: "phone_number",     type: "string", example: "08012345678"),
                                    new OA\Property(property: "home_address",     type: "string", example: "12 Aba Road, Owerri"),
                                    new OA\Property(property: "state",            type: "string", example: "Imo"),
                                    new OA\Property(property: "local_government", type: "string", example: "Owerri North"),
                                ]),
                                new OA\Property(property: "next_of_kin",   ref: "#/components/schemas/NextOfKinInput"),
                                new OA\Property(property: "health_record", ref: "#/components/schemas/HealthRecordInput"),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Not found"),
        ]
    )]
    public function print(string $id): JsonResponse
    {
        $applicant = Applicant::with(["nextOfKin", "healthRecord"])->findOrFail($id);
        return response()->json([
            "print_data" => [
                "application_number" => $applicant->application_number,
                "submitted_at"       => $applicant->created_at->toDateTimeString(),
                "passport_url"       => $applicant->passport_url,
                "passport_data_uri"  => $applicant->passportDataUri(),
                "biodata"            => [
                    "full_name"         => trim("{$applicant->first_name} {$applicant->middle_name} {$applicant->last_name}"),
                    "gender"            => $applicant->gender,
                    "phone_number"      => $applicant->phone_number,
                    "home_address"      => $applicant->home_address,
                    "state"             => $applicant->state,
                    "local_government"  => $applicant->local_government,
                ],
                "next_of_kin"        => $applicant->nextOfKin,
                "health_record"      => $applicant->healthRecord,
            ],
        ]);
    }
}
