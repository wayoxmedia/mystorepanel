<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\Api\GeolocationService;
use App\Services\Api\SubscriberService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class SubscriberController
 */
class SubscriberController extends Controller
{
    /** @var SubscriberService */
    protected SubscriberService $subscriberService;

    protected GeolocationService $geolocationService;

    /**
     * SubscriberController constructor.
     */
    public function __construct()
    {
        $this->subscriberService = new SubscriberService();
        $this->geolocationService = new GeolocationService();
    }

    /**
     * Display a listing of the resource.
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $subscribers = Subscriber::all();
        return response()->json([
            'data' => $subscribers
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created subscriber in our system.
     *
     * Required fields: iptAddress, selAddressType
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException On Geolocation API failure.
     */
    public function store(Request $request): JsonResponse
    {
        $isInactive = false;
        // Validate the request.
        $validated = $request->validate([
            'iptAddress' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    $maxLength = $request['selAddressType'] === 'e' ? 100 : 10;
                    if (strlen($value) > $maxLength) {
                        $fail("La dirección no puede tener más de $maxLength caracteres.");
                    }
                },
                function ($attribute, $value, $fail) use ($request, &$isInactive) {
                    $existingSubscriber = Subscriber::query()
                        ->where('address', $value)
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
                    } elseif ($request['selAddressType'] === 'p' && !ctype_digit($value)) {
                        $fail('Por favor use solo números si selecciona la opción "Teléfono".');
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
        $ip = $request->ip();
        $validated['user_ip'] = $ip;
        $validated['store_id'] = 1; // This is a placeholder for EG, the actual store_id will be set later.

        // Get the geolocation data using an external API.
        $validated['geo_location'] = $this->geolocationService->getGeolocationByIp($ip);

        if ($isInactive) {
            $this->subscriberService->updateActiveStatus($validated);
        } else {
            $this->subscriberService->store($validated);
        }

        return response()->json(['message' => 'Suscripción exitosa.']);
    }

    /**
     * Display the specified resource.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        // Validate the ID manually
        if (!is_numeric($id)
            || !Subscriber::query()
                ->where('id', $id)
                ->exists()
        ) {
            return response()->json(['message' => 'El ID proporcionado no es válido o no existe.'], 422);
        }

        $subscriber = $this->subscriberService->showById($id);

        if (!$subscriber) {
            return response()->json(['message' => 'Suscriptor no encontrado.'], 404);
        }

        return response()->json($subscriber);
    }

    /**
     * Show the form for editing the specified resource.
     * @param string $id
     * @return void
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param string  $id
     * @return void
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param string $id
     * @return void
     */
    public function destroy(string $id)
    {
        //
    }
}
