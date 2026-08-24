<?php

namespace Tests\Feature\Modules\Companies;

use App\Foundation\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Companies\Actions\CreateCompany;
use App\Modules\Companies\Data\CompanyAssetPurpose;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyAsset;
use App\Modules\Companies\Support\CompanyAssetStorage;
use App\Modules\Companies\Support\CompanyLogoUploadPolicy;
use App\Modules\Identity\Models\Account;
use App\Modules\Identity\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyAssetFoundationTest extends TestCase
{
    use DatabaseMigrations;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_logo_upload_is_content_validated_and_privately_stored_with_verified_metadata(): void
    {
        Storage::fake('company_assets_local');
        config()->set('invumo.company_assets.disk', 'company_assets_local');

        $upload = $this->upload('browser-name.php.png', $this->png());
        $validated = app(CompanyLogoUploadPolicy::class)->inspect($upload);
        $company = $this->company('Alpha SRL');
        $stored = app(CompanyAssetStorage::class)->storeLogo($company->id, $validated);

        Storage::disk($stored->disk)->assertExists($stored->key);
        $this->assertSame('private', Storage::disk($stored->disk)->visibility($stored->key));
        $this->assertStringNotContainsString('browser-name', $stored->key);
        $this->assertStringStartsWith("companies/{$company->id}/assets/", $stored->key);
        $this->assertSame('image/png', $validated->mimeType);
        $this->assertSame('png', $validated->extension);
        $this->assertSame(1, $validated->pixelWidth);
        $this->assertSame(1, $validated->pixelHeight);
        $this->assertSame(hash('sha256', $this->png()), $validated->contentSha256);

        app(CompanyAssetStorage::class)->delete($stored);
        Storage::disk($stored->disk)->assertMissing($stored->key);
    }

    public function test_every_approved_raster_format_is_accepted_from_detected_content(): void
    {
        $formats = [
            'logo.png' => ['image/png', 'png'],
            'logo.jpg' => ['image/jpeg', 'jpg'],
            'logo.webp' => ['image/webp', 'webp'],
        ];

        foreach ($formats as $filename => [$mimeType, $extension]) {
            $validated = app(CompanyLogoUploadPolicy::class)->inspect(
                UploadedFile::fake()->image($filename, 4, 3),
            );

            $this->assertSame($mimeType, $validated->mimeType);
            $this->assertSame($extension, $validated->extension);
            $this->assertSame([4, 3], [$validated->pixelWidth, $validated->pixelHeight]);
        }
    }

    public function test_maximum_dimension_logo_is_inspected_without_expanding_the_raster_in_memory(): void
    {
        $contents = $this->maximumDimensionPng();

        $this->assertLessThan(128 * 1024, strlen($contents));

        $validated = app(CompanyLogoUploadPolicy::class)->inspect(
            $this->upload('compressed-maximum.png', $contents),
        );

        $this->assertSame('image/png', $validated->mimeType);
        $this->assertSame(4096, $validated->pixelWidth);
        $this->assertSame(4096, $validated->pixelHeight);
        $this->assertSame(strlen($contents), $validated->byteSize);
    }

    public function test_company_assets_refuse_the_public_disk(): void
    {
        config()->set('invumo.company_assets.disk', 'public');
        $validated = app(CompanyLogoUploadPolicy::class)->inspect(
            $this->upload('logo.png', $this->png()),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('private filesystem disk');

        app(CompanyAssetStorage::class)->storeLogo('company-id', $validated);
    }

    public function test_logo_policy_rejects_unsupported_malformed_oversized_and_animated_inputs(): void
    {
        $jpeg = UploadedFile::fake()->image('source.jpg', 4, 3)->getContent();
        $webp = UploadedFile::fake()->image('source.webp', 4, 3)->getContent();

        $invalidUploads = [
            $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            UploadedFile::fake()->image('logo.gif'),
            $this->upload('logo.png', 'not an image'),
            $this->upload('truncated.png', substr($this->png(), 0, 40)),
            $this->upload('truncated.jpg', substr($jpeg, 0, -2)),
            $this->upload('truncated.webp', substr($webp, 0, -1)),
            $this->upload('large.png', $this->png().str_repeat('x', 5 * 1024 * 1024)),
            $this->upload('wide.png', $this->pngWithWidth(4097)),
            $this->upload('animated.png', $this->animatedPng()),
        ];

        foreach ($invalidUploads as $upload) {
            try {
                app(CompanyLogoUploadPolicy::class)->inspect($upload);
                $this->fail("[{$upload->getClientOriginalName()}] should be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('logo', $exception->errors());
            }
        }
    }

    public function test_company_asset_metadata_is_tenant_isolated_and_immutable(): void
    {
        $companyA = $this->company('Alpha SRL');
        $companyB = $this->company('Beta SRL');
        $owner = $companyA->memberships()->firstOrFail()->user()->firstOrFail();

        $asset = app(TenantContext::class)->runForMember(
            $owner,
            $companyA->id,
            fn () => CompanyAsset::query()->create([
                'purpose' => CompanyAssetPurpose::CompanyLogo,
                'storage_disk' => 'company_assets_local',
                'storage_key' => "companies/{$companyA->id}/assets/logo.png",
                'mime_type' => 'image/png',
                'byte_size' => strlen($this->png()),
                'content_sha256' => hash('sha256', $this->png()),
                'pixel_width' => 1,
                'pixel_height' => 1,
                'created_by_user_id' => $owner->id,
            ]),
        );

        app(TenantContext::class)->runAsSystem(
            $companyB->id,
            fn () => $this->assertSame(0, CompanyAsset::query()->count()),
        );

        app(TenantContext::class)->runAsSystem($companyA->id, function () use ($asset): void {
            $asset->update(['deleted_at' => now()]);
            $this->assertNotNull($asset->refresh()->deleted_at);

            try {
                $asset->update(['storage_key' => 'companies/changed/assets/logo.png']);
                $this->fail('Immutable Company asset metadata must not be updated.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        });
    }

    private function company(string $name): Company
    {
        $owner = User::factory()->create();
        $account = Account::query()->create([
            'owner_user_id' => $owner->id,
            'plan_id' => Plan::query()->where('code', 'free')->firstOrFail()->id,
        ]);

        return app(CreateCompany::class)->handle($account, $owner, $name);
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    private function png(): string
    {
        return base64_decode(self::PNG_1X1, true) ?: throw new \RuntimeException('Invalid PNG fixture.');
    }

    private function pngWithWidth(int $width): string
    {
        $png = $this->png();
        $png = substr_replace($png, pack('N', $width), 16, 4);

        return substr_replace($png, pack('N', crc32(substr($png, 12, 17))), 29, 4);
    }

    private function animatedPng(): string
    {
        $png = $this->png();
        $chunkBody = 'acTL'.pack('NN', 2, 0);
        $chunk = pack('N', 8).$chunkBody.pack('N', crc32($chunkBody));

        return substr($png, 0, 33).$chunk.substr($png, 33);
    }

    private function maximumDimensionPng(): string
    {
        $compressor = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);

        if ($compressor === false) {
            throw new \RuntimeException('Unable to create the maximum-dimension PNG fixture.');
        }

        $scanline = "\0".str_repeat("\0", 4096 * 4);
        $compressed = '';

        for ($row = 0; $row < 4096; $row++) {
            $part = deflate_add(
                $compressor,
                $scanline,
                $row === 4095 ? ZLIB_FINISH : ZLIB_NO_FLUSH,
            );

            if ($part === false) {
                throw new \RuntimeException('Unable to compress the maximum-dimension PNG fixture.');
            }

            $compressed .= $part;
        }

        return "\x89PNG\r\n\x1a\n"
            .$this->pngChunk('IHDR', pack('NNCCCCC', 4096, 4096, 8, 6, 0, 0, 0))
            .$this->pngChunk('IDAT', $compressed)
            .$this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.hash('crc32b', $type.$data, true);
    }
}
