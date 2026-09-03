<?php

namespace App\Http\Controllers\Api;

use App\Models\ContentSection;
use App\Models\Volunteer;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VolunteerController extends ApiController
{
    public function create(Request $request): JsonResponse
    {
        $data = $this->body($request);
        if (trim($data['name'] ?? '') === '' || trim($data['email'] ?? '') === '') {
            return $this->fail('name and email required', 400);
        }

        $volunteer = Volunteer::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'skills' => $data['skills'] ?? '',
            'availability' => $data['availability'] ?? '',
            'motivation' => $data['motivation'] ?? '',
            'status' => 'pending',
        ]);

        return $this->json($volunteer->fresh()->toApi());
    }

    public function list(): JsonResponse
    {
        $volunteers = Volunteer::orderByDesc('created_at')->get();

        return $this->json([
            'volunteers' => $volunteers->map(fn (Volunteer $v) => $v->toApi())->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $volunteer = Volunteer::find($data['id'] ?? '');
        if (! $volunteer) {
            return $this->fail('Not found', 404);
        }

        $oldStatus = $volunteer->status;
        foreach (['name', 'email', 'phone', 'skills', 'availability', 'motivation', 'status'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $volunteer->$field = $data[$field];
            }
        }
        $volunteer->save();

        if ($oldStatus !== 'accepted' && $volunteer->status === 'accepted') {
            $this->addVolunteerToTeam($volunteer->fresh());
        }

        return $this->json($volunteer->fresh()->toApi());
    }

    public function delete(Request $request): JsonResponse
    {
        $data = $this->body($request);
        $removed = Volunteer::destroy($data['id'] ?? '') > 0;

        return $this->json(['success' => $removed]);
    }

    private function addVolunteerToTeam(Volunteer $v): void
    {
        $team = ContentSection::where('website_id', Website::FGE_WEBSITE_ID)->where('section', 'team')->first();
        $members = $team?->data['members'] ?? [];

        foreach ($members as $member) {
            if (($member['email'] ?? '') === $v->email) {
                return;
            }
        }

        $firstSkillLine = trim(explode("\n", $v->skills)[0] ?? '');
        $members[] = [
            'id' => (string) Str::uuid(),
            'name' => $v->name,
            'role' => $firstSkillLine !== '' ? $firstSkillLine : 'Volunteer',
            'category' => 'Volunteer',
            'image' => '',
            'status' => 'active',
            'email' => $v->email,
            'phone' => $v->phone,
            'joined_at' => now()->toRfc3339String(),
        ];

        ContentSection::updateOrCreate(
            ['website_id' => Website::FGE_WEBSITE_ID, 'section' => 'team'],
            ['data' => ['members' => $members]]
        );
    }
}