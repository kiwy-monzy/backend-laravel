<?php

namespace App\Http\Controllers\Api;

use App\Models\Donation;
use App\Models\Website;
use App\Services\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DonationController extends ApiController
{
    public function __construct(private MediaLibrary $media)
    {
    }

    public function create(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if (trim($data['name'] ?? '') === '' || (float) ($data['amount'] ?? 0) <= 0) {
            return $this->fail('name required, amount must be > 0', 400);
        }

        $donation = Donation::create([
            'id' => (string) Str::uuid(),
            'website_id' => Website::FGE_WEBSITE_ID,
            'name' => $data['name'],
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'amount' => (float) $data['amount'],
            'currency' => $data['currency'] ?? 'TZS',
            'transaction_message' => $data['transaction_message'] ?? '',
            // Written to storage on the way in, so a proof-of-payment
            // screenshot never lands in the database as base64.
            'transaction_image' => $this->media->materialise(
                $data['transaction_image'] ?? '', 'donation-proof', $this->organizationId($request), 'invoices',
            ),
            'status' => 'pending',
        ]);

        return $this->json($donation->fresh()->toApi());
    }

    public function list(): JsonResponse
    {
        $donations = Donation::orderByDesc('created_at')->get();
        $total = Donation::where('status', 'verified')->sum('amount');

        return $this->json([
            'donations' => $donations->map(fn (Donation $d) => $d->toApi())->values(),
            'total' => $total,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $donation = Donation::find($data['id'] ?? '');
        if (! $donation) {
            return $this->fail('Not found', 404);
        }

        foreach (['name', 'email', 'phone', 'currency', 'transaction_message', 'transaction_image', 'status'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $donation->$field = $data[$field];
            }
        }
        if (array_key_exists('amount', $data) && $data['amount'] !== null) {
            $donation->amount = (float) $data['amount'];
        }
        $donation->save();

        return $this->json($donation->fresh()->toApi());
    }

    public function delete(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $removed = Donation::destroy($data['id'] ?? '') > 0;

        return $this->json(['success' => $removed]);
    }
}