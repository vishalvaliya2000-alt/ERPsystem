<?php

use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductDocumentShare;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->tenant = Tenant::first() ?? Tenant::create([
        'name' => 'Acme Spice & Agro Exports',
        'slug' => 'acme-spice-agro-exports',
        'industry' => 'Dehydrated Foods',
        'plan' => 'Enterprise',
        'currency_code' => 'INR',
        'currency_symbol' => '₹',
    ]);

    $this->user = User::first() ?? User::factory()->create(['tenant_id' => $this->tenant->id]);
    if (! $this->user->tenant_id) {
        $this->user->tenant_id = $this->tenant->id;
        $this->user->save();
    }

    TenantManager::setTenant($this->tenant);

    $this->product = Product::create([
        'tenant_id' => $this->tenant->id,
        'product_code' => 'DGP-100-TEST',
        'product_name' => 'Dehydrated Garlic Powder (80-100 Mesh)',
        'standard_rate' => 140.00,
        'hsn_code' => '07129020',
        'packaging' => '20 KGs Poly Liner Paper Bag',
        'is_active' => true,
    ]);
});

test('products index page loads and shows document indicator column', function () {
    $response = $this->actingAs($this->user)->get(route('products.index'));

    $response->assertOk();
    $response->assertSee('Documents & Media', false);
    $response->assertSee('DGP-100-TEST');
});

test('api returns product documents, photos, and shares in JSON', function () {
    $response = $this->actingAs($this->user)->getJson(route('products.documents.api', $this->product->id));

    $response->assertOk();
    $response->assertJson([
        'success' => true,
        'product' => [
            'id' => $this->product->id,
            'product_code' => 'DGP-100-TEST',
        ],
        'photos' => [],
        'documents' => [],
        'shares' => [],
    ]);
});

test('user can upload multiple photos and first photo becomes primary', function () {
    $photo1 = UploadedFile::fake()->image('cut_inspection_1.jpg', 600, 600);
    $photo2 = UploadedFile::fake()->image('packaging_box_2.png', 800, 800);

    $response = $this->actingAs($this->user)->postJson(route('products.photos.upload', $this->product->id), [
        'photos' => [$photo1, $photo2],
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $photos = ProductDocument::where('product_id', $this->product->id)
        ->where('document_type', ProductDocument::TYPE_PHOTO)
        ->orderBy('sort_order')
        ->get();

    expect($photos)->toHaveCount(2);
    expect($photos[0]->is_primary)->toBeTrue();
    expect($photos[1]->is_primary)->toBeFalse();
    expect($photos[0]->file_name)->toBe('cut_inspection_1.jpg');

    Storage::disk('public')->assertExists($photos[0]->file_path);
    Storage::disk('public')->assertExists($photos[1]->file_path);
});

test('user can set another photo as primary and reorder photos', function () {
    $photo1 = UploadedFile::fake()->image('photo_1.jpg');
    $photo2 = UploadedFile::fake()->image('photo_2.jpg');

    $this->actingAs($this->user)->postJson(route('products.photos.upload', $this->product->id), [
        'photos' => [$photo1, $photo2],
    ]);

    $photos = ProductDocument::where('product_id', $this->product->id)->get();
    $p1 = $photos[0];
    $p2 = $photos[1];

    expect($p1->is_primary)->toBeTrue();

    // Set p2 as primary
    $resp = $this->actingAs($this->user)->postJson(route('products.photos.set-primary', [
        'id' => $this->product->id,
        'docId' => $p2->id,
    ]));
    $resp->assertOk();

    expect($p2->fresh()->is_primary)->toBeTrue();
    expect($p1->fresh()->is_primary)->toBeFalse();

    // Reorder photos
    $reorderResp = $this->actingAs($this->user)->postJson(route('products.photos.reorder', $this->product->id), [
        'photo_ids' => [$p2->id, $p1->id],
    ]);
    $reorderResp->assertOk();

    expect($p2->fresh()->sort_order)->toBe(0);
    expect($p1->fresh()->sort_order)->toBe(1);
});

test('user can upload technical documents with COA, spec, validity, and versioning', function () {
    $pdf = UploadedFile::fake()->create('COA_Batch_2026.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->user)->postJson(route('products.documents.upload', $this->product->id), [
        'document' => $pdf,
        'document_type' => 'coa',
        'file_name' => 'COA Batch 2026-Q1',
        'version' => '1.0',
        'valid_until' => '2026-12-31',
        'is_latest' => '1',
        'notes' => 'Tested for Salmonella and E. coli negative',
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $doc = ProductDocument::where('product_id', $this->product->id)
        ->where('document_type', ProductDocument::TYPE_COA)
        ->first();

    expect($doc)->not->toBeNull();
    expect($doc->file_name)->toContain('COA Batch 2026-Q1');
    expect($doc->version)->toBe('1.0');
    expect($doc->valid_until->format('Y-m-d'))->toBe('2026-12-31');
    expect($doc->is_latest)->toBeTrue();
    expect($doc->is_expired)->toBeFalse();
    expect($doc->notes)->toBe('Tested for Salmonella and E. coli negative');

    Storage::disk('public')->assertExists($doc->file_path);
});

test('uploading a new latest document demotes existing latest of same type', function () {
    $fileV1 = UploadedFile::fake()->create('TDS_v1.pdf', 300, 'application/pdf');
    $res1 = $this->actingAs($this->user)->postJson(route('products.documents.upload', $this->product->id), [
        'document' => $fileV1,
        'document_type' => 'specification_sheet',
        'file_name' => 'Technical Data Sheet v1.0',
        'version' => '1.0',
        'is_latest' => '1',
    ]);
    $res1->assertOk();

    $docV1 = ProductDocument::where('file_name', 'like', '%Technical Data Sheet v1.0%')->first();
    expect($docV1)->not->toBeNull();
    expect($docV1->is_latest)->toBeTrue();

    // Upload v2 marked as latest
    $fileV2 = UploadedFile::fake()->create('TDS_v2.pdf', 320, 'application/pdf');
    $res2 = $this->actingAs($this->user)->postJson(route('products.documents.upload', $this->product->id), [
        'document' => $fileV2,
        'document_type' => 'specification_sheet',
        'file_name' => 'Technical Data Sheet v2.0',
        'version' => '2.0',
        'is_latest' => '1',
    ]);
    $res2->assertOk();

    expect($docV1->fresh()->is_latest)->toBeFalse();
    $docV2 = ProductDocument::where('file_name', 'like', '%Technical Data Sheet v2.0%')->first();
    expect($docV2)->not->toBeNull();
    expect($docV2->is_latest)->toBeTrue();
});

test('can download all product documents and photos as a ZIP archive', function () {
    $photo = UploadedFile::fake()->image('sample_photo.jpg');
    $coa = UploadedFile::fake()->create('sample_coa.pdf', 200, 'application/pdf');

    $this->actingAs($this->user)->postJson(route('products.photos.upload', $this->product->id), [
        'photos' => [$photo],
    ]);
    $this->actingAs($this->user)->postJson(route('products.documents.upload', $this->product->id), [
        'document' => $coa,
        'document_type' => 'coa',
        'file_name' => 'Certificate_Of_Analysis.pdf',
    ]);

    $response = $this->actingAs($this->user)->get(route('products.documents.download-all', $this->product->id));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/zip');
});

test('user can generate a public share link with optional password and expiry', function () {
    $coa = UploadedFile::fake()->create('sample_coa.pdf', 200, 'application/pdf');
    $uploadResp = $this->actingAs($this->user)->postJson(route('products.documents.upload', $this->product->id), [
        'document' => $coa,
        'document_type' => 'coa',
        'file_name' => 'Client_COA.pdf',
    ]);
    $uploadResp->assertOk();
    $doc = ProductDocument::where('product_id', $this->product->id)->first();
    expect($doc)->not->toBeNull();

    $response = $this->actingAs($this->user)->postJson(route('products.documents.share.create', $this->product->id), [
        'title' => 'Client Quality Dossier',
        'recipient_email' => 'importer@germany.de',
        'expires_in' => '7d',
        'password' => 'SafePass123',
        'selected_document_ids' => [$doc->id],
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $share = ProductDocumentShare::first();
    expect($share)->not->toBeNull();
    expect($share->title)->toBe('Client Quality Dossier');
    expect($share->recipient_email)->toBe('importer@germany.de');
    expect($share->has_password)->toBeTrue();
    expect($share->is_expired)->toBeFalse();
    expect($share->verifyPassword('SafePass123'))->toBeTrue();
    expect($share->verifyPassword('WrongPass'))->toBeFalse();
});

test('public customer share portal requires password if protected and allows viewing once unlocked', function () {
    $doc = ProductDocument::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'document_type' => ProductDocument::TYPE_COA,
        'file_name' => 'Verified_COA.pdf',
        'file_path' => 'product_documents/'.$this->product->id.'/test.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'is_latest' => true,
    ]);
    Storage::disk('public')->put($doc->file_path, 'dummy content');

    $share = ProductDocumentShare::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'share_token' => 'test-public-token-12345',
        'title' => 'Public Share Test',
        'password_hash' => Hash::make('SecretPIN'),
        'expires_at' => Carbon::now()->addDays(7),
        'selected_document_ids' => [$doc->id],
    ]);

    // 1. Initial request without auth -> password prompt view
    $response = $this->get(route('products.documents.shared', $share->share_token));
    $response->assertOk();
    $response->assertSee('Password Protected Share');
    $response->assertSee('Unlock Documents');

    // 2. Submit wrong password
    $verifyFail = $this->post(route('products.documents.shared.verify', $share->share_token), [
        'password' => 'WrongPIN',
    ]);
    $verifyFail->assertSessionHas('error');

    // 3. Submit correct password
    $verifySuccess = $this->post(route('products.documents.shared.verify', $share->share_token), [
        'password' => 'SecretPIN',
    ]);
    $verifySuccess->assertRedirect(route('products.documents.shared', $share->share_token));

    // 4. View portal unlocked
    $responseUnlocked = $this->get(route('products.documents.shared', $share->share_token));
    $responseUnlocked->assertOk();
    $responseUnlocked->assertSee('DGP-100-TEST');
    $responseUnlocked->assertSee('Verified_COA.pdf');
    $responseUnlocked->assertSee('ERPSaaS Documentation Portal');

    // Check views count incremented
    expect($share->fresh()->views_count)->toBeGreaterThan(0);
});

test('expired public share displays expired notice', function () {
    $share = ProductDocumentShare::create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'share_token' => 'expired-token-999',
        'title' => 'Expired Dossier',
        'expires_at' => Carbon::now()->subDay(),
        'selected_document_ids' => [],
    ]);

    $response = $this->get(route('products.documents.shared', $share->share_token));
    $response->assertOk();
    $response->assertSee('This Share Link Has Expired');
});
