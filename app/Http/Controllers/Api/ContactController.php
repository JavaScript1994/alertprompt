<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Contacts\ImportContactsRequest;
use App\Http\Requests\Api\Contacts\StoreContactRequest;
use App\Http\Requests\Api\Contacts\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Jobs\ImportContactsCsv;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 20), 100);

        $contacts = Contact::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $like = '%'.mb_strtolower($request->string('search')->toString()).'%';

                $query->where(function ($query) use ($like) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $contact = Contact::create($request->validated());

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $contact->update($request->validated());

        return new ContactResource($contact);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(status: 204);
    }

    public function import(ImportContactsRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('imports', 'local');

        ImportContactsCsv::dispatch($request->user()->tenant_id, $path);

        return response()->json([
            'message' => 'Importación en proceso.',
        ], 202);
    }
}
