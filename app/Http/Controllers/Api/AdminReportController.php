<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Get daily report.
     */
    public function daily(Request $request): JsonResponse
    {
        $date = $request->input('date');

        $report = $this->analyticsService->getDailyReport($date);

        return response()->success($report, 'Daily report retrieved successfully.');
    }

    /**
     * Get monthly report.
     */
    public function monthly(Request $request): JsonResponse
    {
        $year = $request->input('year');
        $month = $request->input('month');

        $report = $this->analyticsService->getMonthlyReport($year, $month);

        return response()->success($report, 'Monthly report retrieved successfully.');
    }
}
