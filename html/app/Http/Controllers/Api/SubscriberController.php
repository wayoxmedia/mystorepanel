<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\Api\SubscriberService;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    protected $subscriberService;

    public function __construct()
    {
        $this->subscriberService = new SubscriberService();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $isInactive = false;
        // Validate the request.
        $validated = $request->validate([
            'iptAddress' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($request, &$isInactive) {
                    $existingSubscriber = Subscriber::where('address', $value)
                        ->where('address_type', $request['selAddressType'])
                        ->first();

                    if ($existingSubscriber && $existingSubscriber->active) {
                        $fail('Esta dirección ya esta registrada.');
                    }
                    if ($existingSubscriber && !$existingSubscriber->active) {
                        $isInactive = true;
                    }
                },
                function ($attribute, $value, $fail) use ($request) {
                    if ($request['selAddressType'] === 'e' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $fail('Por favor use una dirección de email válida si selecciona la opción "Email".');
                    } elseif ($request['selAddressType'] === 'p' && !preg_match('/^\+?[0-9]{10,15}$/', $value)) {
                        $fail('Por favor use un número de teléfono válido si selecciona la opción "Teléfono".');
                    }
                },
            ],
            'selAddressType' => 'required|in:p,e',
        ], [
            'iptAddress.required' => 'La dirección es requerida.',
            'selAddressType.required' => 'El tipo de dirección es requerido.',
            'iptAddress.string' => 'La dirección debe ser solo caracteres válidos.',
            'iptAddress.max' => 'La dirección no puede tener mas de 100 caracteres.',
            'selAddressType.in' => 'Por favor elija entre teléfono o email.',
        ]);
        $validated['user_ip'] = $request->ip();

        if ($isInactive) {
            $this->subscriberService->updateActiveStatus($validated);
        }
        else {
            $this->subscriberService->store($validated);
        }

        return response()->json(['message' => 'Suscripción exitosa.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
