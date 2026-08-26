<?php

namespace Tests\Feature;

use App\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    private const SAMPLE = '{"latitude":-7.3132987,"localityLanguageRequested":"id","countryName":"Indonesia","principalSubdivision":"Jawa","city":"Cisurupan","locality":"Cisurupan","localityInfo":{"administrative":[{"name":"Indonesia","adminLevel":2},{"name":"Jawa","adminLevel":3},{"name":"Jawa Barat","adminLevel":4},{"name":"Garut","adminLevel":5}],"informative":[{"name":"Cisurupan","description":"kecamatan di Kabupaten Garut, Jawa Barat","order":9}]}}';

    public function test_parse_kecamatan_from_description(): void
    {
        Http::fake([
            'api.bigdatacloud.net/*' => Http::response(json_decode(self::SAMPLE, true), 200),
        ]);

        $result = app(GeocodingService::class)->reverse(-7.31329870, 107.79306990);

        $this->assertSame('Cisurupan', $result['kecamatan']);
        $this->assertNull($result['kelurahan']);
    }

    public function test_parse_kelurahan_when_present(): void
    {
        $sample = json_decode(self::SAMPLE, true);
        $sample['localityInfo']['informative'][] = [
            'name' => 'Sukamukti',
            'description' => 'kelurahan di Kecamatan Cisurupan, Garut',
            'order' => 10,
        ];
        Http::fake([
            'api.bigdatacloud.net/*' => Http::response($sample, 200),
        ]);

        $result = app(GeocodingService::class)->reverse(-7.31, 107.79);

        $this->assertSame('Cisurupan', $result['kecamatan']);
        $this->assertSame('Sukamukti', $result['kelurahan']);
    }

    public function test_fail_open_on_http_error(): void
    {
        Http::fake([
            'api.bigdatacloud.net/*' => Http::response(null, 500),
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
