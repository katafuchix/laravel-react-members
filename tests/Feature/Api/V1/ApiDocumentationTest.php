<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_openapi_document_describes_every_api_route(): void
    {
        $path = sys_get_temp_dir().'/members-api-'.uniqid().'.json';

        Artisan::call('scramble:export', ['--path' => $path]);

        $document = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        $this->assertSame('会員 API', $document['info']['title']);
        $this->assertSame('bearer', $document['components']['securitySchemes']['http']['scheme']);

        $operations = [];
        foreach ($document['paths'] as $uri => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations[] = strtoupper($method).' '.$uri;
            }
        }

        $this->assertEqualsCanonicalizing([
            'POST /v1/register',
            'POST /v1/login',
            'POST /v1/logout',
            'GET /v1/email/verify/{id}',
            'POST /v1/email/verify',
            'POST /v1/email/resend',
            'GET /v1/me',
            'PATCH /v1/me',
            'DELETE /v1/me',
            'PUT /v1/me/password',
        ], $operations);

        // 公開のエンドポイントは、認証なしと明示されている。
        $this->assertSame([], $document['paths']['/v1/login']['post']['security']);
        $this->assertArrayNotHasKey('security', $document['paths']['/v1/me']['get']);
    }
}
