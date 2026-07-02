<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    #[OA\Get(
        path: '/dashboard/stats',
        tags: ['Dashboard'],
        summary: 'Admission statistics for the admin dashboard',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Aggregated stats',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total',        type: 'integer', example: 120),
                        new OA\Property(property: 'pending',      type: 'integer', example: 45),
                        new OA\Property(property: 'under_review', type: 'integer', example: 30),
                        new OA\Property(property: 'approved',     type: 'integer', example: 35),
                        new OA\Property(property: 'rejected',     type: 'integer', example: 10),
                        new OA\Property(property: 'today',        type: 'integer', example: 8),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function stats(): JsonResponse
    {
        $counts = Applicant::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'total'        => Applicant::count(),
            'pending'      => (int) ($counts['pending']      ?? 0),
            'under_review' => (int) ($counts['under_review'] ?? 0),
            'approved'     => (int) ($counts['approved']     ?? 0),
            'rejected'     => (int) ($counts['rejected']     ?? 0),
            'today'        => Applicant::whereDate('created_at', today())->count(),
        ]);
    }
}
