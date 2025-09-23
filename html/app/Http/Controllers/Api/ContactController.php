<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\Api\ContactService;
use App\Services\Api\GeolocationService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class ContactController
 */
class ContactController extends Controller
{
  /** @var ContactService */
  protected ContactService $contactService;

  /** @var GeolocationService */
  protected GeolocationService $geolocationService;

  /**
   * ContactController constructor.
   */
  public function __construct()
  {
    $this->contactService = new ContactService();
    $this->geolocationService = new GeolocationService();
  }

  /**
   * Display a listing of the resource.
   *
   * @return JsonResponse
   */
  public function index(): JsonResponse
  {
    $contacts = Contact::all();
    return response()->json([
      'data' => $contacts
    ]);
  }

  /**
   * Store a Contact Information.
   *
   * @param  Request  $request
   * @return JsonResponse
   * @throws GuzzleException On Geolocation API failure.
   * @throws Exception
   */
  public function store(Request $request): JsonResponse
  {
    // Validate the request
    $validated = $request->validate([
      'iptName' => 'required|string|max:30',
      'iptEmail' => 'required|email|max:50',
      'iptMessage' => 'required|string|max:500',
    ]);

    $ip = $request->ip();
    $validated['user_ip'] = $ip;
    $validated['tenant_id'] = 1;
    // This is a placeholder for EG, the actual store_id will be set later.

    // Get the geolocation data using an external API.
    $validated['geo_location'] = $this->geolocationService->getGeolocationByIp($ip);

    $response = $this->contactService->store($validated);

    if ($response) {
      return response()->json(['message' => 'Form submitted successfully']);
    } else {
      return response()->json(
        ['message' => 'No se pudo guardar el mensaje, intente de nuevo mas tarde'],
        500
      );
    }
  }
}
