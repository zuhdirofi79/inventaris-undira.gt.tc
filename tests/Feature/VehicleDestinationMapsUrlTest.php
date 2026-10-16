<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\OperationalRoute;
use App\Models\VehicleBorrowing;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class VehicleDestinationMapsUrlTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_vehicle_submission_succeeds_without_maps_url(): void
    {
        $admin = $this->makeAdmin(['email' => 'maps.empty@undira.ac.id']);
        $campus = $this->campus();

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id, [
                'destination_maps_url' => '',
            ]))
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->latest('id')->first();
        $this->assertNotNull($saved);
        $this->assertNull($saved->destination_maps_url);
        $this->assertSame('Grogol, Jakarta Barat', $saved->destination);
    }

    public function test_vehicle_submission_stores_valid_google_maps_url(): void
    {
        $admin = $this->makeAdmin(['email' => 'maps.valid@undira.ac.id']);
        $campus = $this->campus();
        $url = 'https://maps.app.goo.gl/abcXYZ123';

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id, [
                'destination_maps_url' => $url,
            ]))
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->latest('id')->first();
        $this->assertSame($url, $saved?->destination_maps_url);

        $this->actingAs($admin)
            ->get(route('vehicle-borrowings.show', $saved))
            ->assertOk()
            ->assertSee('Lihat Lokasi di Google Maps', false)
            ->assertSee($url, false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('target="_blank"', false);
    }

    public function test_vehicle_submission_accepts_standard_google_maps_url(): void
    {
        $admin = $this->makeAdmin(['email' => 'maps.full@undira.ac.id']);
        $campus = $this->campus();
        $url = 'https://www.google.com/maps/place/Universitas+Dian+Nusantara';

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id, [
                'destination_maps_url' => $url,
            ]))
            ->assertRedirect();

        $this->assertSame($url, VehicleBorrowing::query()->latest('id')->value('destination_maps_url'));
    }

    public function test_vehicle_submission_rejects_invalid_or_dangerous_url(): void
    {
        $admin = $this->makeAdmin(['email' => 'maps.bad@undira.ac.id']);
        $campus = $this->campus();

        $this->actingAs($admin)
            ->from(route('vehicle-borrowings.create'))
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id, [
                'destination_maps_url' => 'bukan-url',
            ]))
            ->assertRedirect(route('vehicle-borrowings.create'))
            ->assertSessionHasErrors('destination_maps_url');

        $this->actingAs($admin)
            ->from(route('vehicle-borrowings.create'))
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id, [
                'destination_maps_url' => 'javascript:alert(1)',
            ]))
            ->assertRedirect(route('vehicle-borrowings.create'))
            ->assertSessionHasErrors('destination_maps_url');

        $this->actingAs($admin)
            ->from(route('vehicle-borrowings.create'))
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id, [
                'destination_maps_url' => 'https://example.com/not-maps',
            ]))
            ->assertRedirect(route('vehicle-borrowings.create'))
            ->assertSessionHasErrors('destination_maps_url');

        $this->assertSame(0, VehicleBorrowing::query()->count());
    }

    public function test_detail_without_maps_url_shows_placeholder(): void
    {
        $admin = $this->makeAdmin(['email' => 'maps.detail@undira.ac.id']);
        $campus = $this->campus();

        $this->actingAs($admin)
            ->post(route('vehicle-borrowings.store'), $this->payload($campus->id))
            ->assertRedirect();

        $saved = VehicleBorrowing::query()->latest('id')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('vehicle-borrowings.show', $saved))
            ->assertOk()
            ->assertSee('Tidak dicantumkan')
            ->assertDontSee('Lihat Lokasi di Google Maps');
    }

    public function test_create_form_shows_optional_maps_field(): void
    {
        $admin = $this->makeAdmin(['email' => 'maps.form@undira.ac.id']);

        $this->actingAs($admin)
            ->get(route('vehicle-borrowings.create'))
            ->assertOk()
            ->assertSee('Link Google Maps Tujuan (Opsional)')
            ->assertSee('name="destination_maps_url"', false)
            ->assertSee('Tempelkan link Google Maps lokasi tujuan');
    }

    private function campus(): Campus
    {
        return Campus::query()->create([
            'code' => 'MP'.substr(uniqid(), -3),
            'name' => 'Kampus Maps '.uniqid(),
            'slug' => 'kampus-maps-'.uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(int $campusId, array $overrides = []): array
    {
        $routeId = array_key_exists('route_id', $overrides)
            ? $overrides['route_id']
            : OperationalRoute::query()->create([
                'name' => 'Rute Maps '.uniqid(),
                'is_active' => true,
            ])->id;

        return array_merge([
            'campus_id' => $campusId,
            'route_id' => $routeId,
            'borrower_name' => 'Pemohon Maps',
            'borrower_email' => 'pemohon.maps@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Perjalanan dinas',
            'service_type' => 'drop_off',
            'destination' => 'Grogol, Jakarta Barat',
            'passenger_count' => 1,
            'passengers' => [
                ['name' => 'Pemohon', 'unit' => 'Biro', 'title' => 'Staff'],
            ],
            'starts_at' => '2026-11-02T09:00',
            'ends_at' => '2026-11-02T11:00',
        ], $overrides);
    }
}
