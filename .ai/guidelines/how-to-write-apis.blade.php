# API
This document describes how we are building APIs at xentral.

## Design Principles
RESTful Design: Endpoints should follow standard REST conventions.
Consistent Naming: Use standardized field names, path structures, and request/response formats.
Eloquent-Only Implementation: Use Laravel's Eloquent ORM instead of Dotrine.
Testability: Use PHPUnit with Database Factories to ensure easy testing.

## Routing
All endpoints that you generate are in the routes/v3.php
Each business document type will expose the following endpoints:
The examples are taken form the real implementation of the Sales Order business document

* Create POST /
* List GET /
* View GET /{id}
* Update PATCH /{id}
* Delete DELETE /{id}

Any document can have a actions /actions/{action}. Actions are by design PATCH requests, since they are changing the state of a resource.

## Implementation
We will leverage the composer package `bambamboole/laravel-openapi`  to bundle all the needed logic to build api endpoints.
As soon as we are happy with the state, we can take this over into the xentral organisation.

## Specification
We leverage prepared PHP attributes for that to have a simple to use abstraction and a better way to post process everything.

## Actions
Actions are classes which bundle the logic of a specific state changing action. THis can be Create, Update, Delete or any
other action which is not a simple CRUD operation.

View and list endpoints are handled by the controller itself, so no action is needed for that.

## Responses

We are leveraging exclusively Laravels JsonResources. We Provide a {{ \Xentral\LaravelApi\Http\ApiResource::class }} class
which extends theLaravel JsonResource an has extra helpers.

## Listing records
We are using Laravels built in pagination for listing records.
By default we are using the simple pagination via the apiPaginate() method on the QueryBuilder.
The specification can be easily adapted to the used pagination via the paginationType property on the ListEndpoint attribute.
Available options are simple , table and cursor.

### Filtering records
Another part of the `bambamboole/laravel-openapi` package is a QueryBuilder built on top of the spatie/laravel-query-builder
package. It provides a powerful way to filter eloquent records based on predefined filters.

## Validation
We use exclusively Laravels validator for request validation. It can be integrated via custom FormRequests
or Data Objects (via spatie/laravel-data).
