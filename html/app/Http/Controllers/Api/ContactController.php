<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse {
        // Validate the request
        $validated = $request->validate([
            'iptName' => 'required|string|max:30',
            'iptEmail' => 'required|email|max:50',
            'iptMessage' => 'required|string|max:500',
        ]);

        // Store the data in the database
        Contact::create([
            'name' => $validated['iptName'],
            'email' => $validated['iptEmail'],
            'message' => $validated['iptMessage'],
        ]);

        return response()->json(['message' => 'Form submitted successfully']);
    }
}
