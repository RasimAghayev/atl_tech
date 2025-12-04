<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CallEvent;

use App\Modules\CallEvent\Models\CallEventLog;
use App\Shared\Contracts\EventPublisherInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CallEventApiTest extends TestCase
{
    use RefreshDatabase;

    private string $validToken = 'test-api-token';

    protected function setUp(): void
    {
        parent::setUp();

        config(['call-event.api_token' => $this->validToken]);
    }

    /**
     * Create mock for EventPublisher that expects to be called
     */
    private function mockEventPublisher(): void
    {
        $this->mock(EventPublisherInterface::class, function (MockInterface $mock) {
            $mock->expects('publish')
                ->andReturnNull();
        });
    }

    public function test_can_submit_call_started_event_successfully(): void
    {
        $this->mockEventPublisher();

        $payload = [
            'call_id' => 'CALL-12345',
            'caller_number' => '+994501234561',
            'callee_number' => '+994551234562',
            'event_type' => 'call_started',
            'timestamp' => '2025-12-04 10:30:00',
        ];

        $response = $this->withToken($this->validToken)
            ->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('call_event_logs', [
            'call_id' => 'CALL-12345',
            'event_type' => 'call_started',
        ]);
    }

    public function test_can_submit_call_ended_event_with_duration(): void
    {
        $this->mockEventPublisher();

        $payload = [
            'call_id' => 'CALL-67890',
            'caller_number' => '+994501234563',
            'callee_number' => '+994551234564',
            'event_type' => 'call_ended',
            'timestamp' => '2025-12-04 10:35:00',
            'duration' => 300,
        ];

        $response = $this->withToken($this->validToken)
            ->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'queued']);

        $log = CallEventLog::where('call_id', 'CALL-67890')->first();
        $this->assertNotNull($log);
        $this->assertEquals(300, $log->payload['duration']);
    }

    public function test_validation_fails_when_duration_missing_for_call_ended(): void
    {
        $payload = [
            'call_id' => 'CALL-12345',
            'caller_number' => '+994501234565',
            'callee_number' => '+994551234566',
            'event_type' => 'call_ended',
            'timestamp' => '2025-12-04 10:30:00',
            // Missing duration - should fail validation
        ];

        $response = $this->withToken($this->validToken)
            ->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.duration', fn ($value) => !empty($value));
    }

    public function test_validation_fails_with_invalid_phone_number(): void
    {
        $payload = [
            'call_id' => 'CALL-12345',
            'caller_number' => 'invalid-phone',
            'callee_number' => '+994551234567',
            'event_type' => 'call_started',
            'timestamp' => '2025-12-04 10:30:00',
        ];

        $response = $this->withToken($this->validToken)
            ->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.caller_number', fn ($value) => !empty($value));
    }

    public function test_validation_fails_with_invalid_event_type(): void
    {
        $payload = [
            'call_id' => 'CALL-12345',
            'caller_number' => '+994501234568',
            'callee_number' => '+994551234569',
            'event_type' => 'invalid_event',
            'timestamp' => '2025-12-04 10:30:00',
        ];

        $response = $this->withToken($this->validToken)
            ->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('error.event_type', fn ($value) => !empty($value));
    }

    public function test_authentication_fails_without_token(): void
    {
        $payload = [
            'call_id' => 'CALL-12345',
            'caller_number' => '+994501234570',
            'callee_number' => '+994551234571',
            'event_type' => 'call_started',
            'timestamp' => '2025-12-04 10:30:00',
        ];

        $response = $this->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized. Invalid or missing API token.']);
    }

    public function test_authentication_fails_with_invalid_token(): void
    {
        $payload = [
            'call_id' => 'CALL-12345',
            'caller_number' => '+994501234572',
            'callee_number' => '+994551234573',
            'event_type' => 'call_started',
            'timestamp' => '2025-12-04 10:30:00',
        ];

        $response = $this->withToken('wrong-token')
            ->postJson('/api/v1/call-events', $payload);

        $response->assertStatus(401);
    }

    public function test_validation_fails_when_required_fields_missing(): void
    {
        $response = $this->withToken($this->validToken)
            ->postJson('/api/v1/call-events', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.call_id', fn ($value) => !empty($value))
            ->assertJsonPath('error.caller_number', fn ($value) => !empty($value))
            ->assertJsonPath('error.callee_number', fn ($value) => !empty($value))
            ->assertJsonPath('error.event_type', fn ($value) => !empty($value))
            ->assertJsonPath('error.timestamp', fn ($value) => !empty($value));
    }
}