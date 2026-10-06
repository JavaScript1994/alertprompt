<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Consents\StoreConsentRequest;
use App\Http\Resources\ConsentResource;
use App\Models\Consent;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsentController extends Controller
{
    public function index(Contact $contact): AnonymousResourceCollection
    {
        return ConsentResource::collection(
            $contact->consents()->orderByDesc('granted_at')->get()
        );
    }

    public function store(StoreConsentRequest $request, Contact $contact): ConsentResource
    {
        $channel = $request->validated('channel');

        abort_if(
            $contact->hasConsentFor($channel),
            422,
            'Ya existe un consentimiento vigente para este canal.',
        );

        $consent = $contact->consents()->create([
            'channel' => $channel,
            'granted_at' => now(),
            'source' => $request->validated('source'),
            'ip' => $request->ip(),
            'evidence_text' => $request->validated('evidence_text'),
        ]);

        return new ConsentResource($consent);
    }

    public function revoke(Request $request, Contact $contact, Consent $consent): ConsentResource
    {
        abort_unless($consent->contact_id === $contact->id, 404);

        if ($consent->isActive()) {
            $consent->revoke();
        }

        return new ConsentResource($consent);
    }
}
