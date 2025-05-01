<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Api\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ContactController
 */
class ContactController extends Controller
{
    /** @var ContactService */
    protected ContactService $contactService;

    /**
     * ContactController constructor.
     */
    public function __construct()
    {
        $this->contactService = new ContactService();
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validate the request
        $validated = $request->validate([
            'iptName' => 'required|string|max:30',
            'iptEmail' => 'required|email|max:50',
            'iptMessage' => 'required|string|max:500',
        ]);

        $this->contactService->store($validated);

        return response()->json(['message' => 'Form submitted successfully']);
    }
}
