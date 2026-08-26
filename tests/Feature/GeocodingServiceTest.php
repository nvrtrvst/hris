<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    private const SAMPLE_NOMINATIM = [
        'address' => [
            'subdistrict' => 'Cisurupan',
            'village' => null,
            'city' => 'Garut',
            'state' => 'Jawa Barat',
            'country' => 'Indonesia',
        ],
    ];

    public function test_parse_kecamatan_from_subdistrict(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(self::SAMPLE_NOMINATIM, 200),
        ]);

        $result = app(GeocodingService::class)->reverse(-7.31329870, 107.79306990);

        $this->assertSame('Cisurupan', $result['kecamatan']);
        $this->assertNull($result['kelurahan']);
    }

    public function test_parse_kelurahan_when_present(): void
    {
        $sample = self::SAMPLE_NOMINATIM;
        $sample['address']['village'] = 'Sukamukti';

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response($sample, 200),
        ]);

        $result = app(GeocodingService::class)->reverse(-7.31, 107.79);

        $this->assertSame('Cisurupan', $result['kecamatan']);
        $this->assertSame('Sukamukti', $result['kelurahan']);
    }

    public function test_fail_open_on_http_error(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(null, 500),
        ]);

        $result = app(GeocodingService::class)->reverse(-7.3, 107.7);

        $this->assertNull($result['kecamatan']);
        $this->assertNull($result['kelurahan']);
    }

    public function test_null_coordinate_returns_empty_without_http(): void
    {
        $result = app(GeocodingService::class)->reverse(null, null);

        $this->assertNull($result['kecamatan']);
        $this->assertNull($result['kelurahan']);
    }
}
