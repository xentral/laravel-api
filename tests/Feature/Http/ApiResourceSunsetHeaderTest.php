<?php declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Http\Resources\InvoiceResource;
use Workbench\App\Models\Invoice;

it('adds sunset header when deprecatedSince is called', function () {
    $invoice = Invoice::factory()->create();
    $resource = new InvoiceResource($invoice);

    $deprecationDate = new DateTime('2025-12-31');
    $resource->deprecatedSince($deprecationDate);

    $request = Request::create('/test');
    $response = $resource->toResponse($request);

    expect($response->headers->has('Sunset'))->toBeTrue()
        ->and($response->headers->get('Sunset'))->toBe($deprecationDate->format(DATE_RFC7231));
});

it('uses correct RFC 7231 date format for sunset header', function () {
    $invoice = Invoice::factory()->create();
    $resource = new InvoiceResource($invoice);

    $deprecationDate = new DateTime('2025-06-15 14:30:00');
    $resource->deprecatedSince($deprecationDate);

    $request = Request::create('/test');
    $response = $resource->toResponse($request);

    $sunsetHeader = $response->headers->get('Sunset');

    // Should match RFC 7231 format: Sun, 15 Jun 2025 14:30:00 GMT
    expect($sunsetHeader)->toMatch('/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/')
        ->and($sunsetHeader)->toContain('15 Jun 2025 00:00:00 GMT');
});

it('does not add sunset header when deprecatedSince is not called', function () {
    $invoice = Invoice::factory()->create();
    $resource = new InvoiceResource($invoice);

    $request = Request::create('/test');
    $response = $resource->toResponse($request);

    expect($response->headers->has('Sunset'))->toBeFalse();
});

it('allows method chaining with deprecatedSince', function () {
    $invoice = Invoice::factory()->create();
    $resource = new InvoiceResource($invoice);

    $deprecationDate = new DateTime('2025-12-31');
    $chainedResource = $resource->deprecatedSince($deprecationDate);

    expect($chainedResource)->toBe($resource);

    $request = Request::create('/test');
    $response = $resource->toResponse($request);

    expect($response->headers->has('Sunset'))->toBeTrue();
});

it('demonstrates practical usage with conditional deprecation', function () {
    $oldInvoice = Invoice::factory()->create(['created_at' => now()->subYears(2)]);
    $newInvoice = Invoice::factory()->create(['created_at' => now()]);

    $oldResource = new InvoiceResource($oldInvoice);
    $newResource = new InvoiceResource($newInvoice);

    // Only deprecate resources older than a certain date
    if ($oldInvoice->created_at->isBefore(now()->subYear())) {
        $oldResource->deprecatedSince(new DateTime('2025-06-01'));
    }

    $request = Request::create('/test');

    $oldResponse = $oldResource->toResponse($request);
    $newResponse = $newResource->toResponse($request);

    // Verify both data and headers work correctly
    $oldResponseData = json_decode($oldResponse->getContent(), true);
    $newResponseData = json_decode($newResponse->getContent(), true);

    expect($oldResponse->headers->has('Sunset'))->toBeTrue()
        ->and($newResponse->headers->has('Sunset'))->toBeFalse()
        ->and($oldResponseData['data']['id'])->toBe($oldInvoice->id)
        ->and($newResponseData['data']['id'])->toBe($newInvoice->id);
});
