<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\CouponEvent; // Assuming you have an Eloquent model for the logs

class LogCouponEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $eventName;
    public string $couponCode;
    public ?int $userId;
    public ?int $cartId;
    public ?array $ruleSnapshot;
    public array $metadata;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $eventName, 
        string $couponCode, 
        ?int $userId = null, 
        ?int $cartId = null, 
        ?array $ruleSnapshot = null, 
        array $metadata = []
    ) {
        $this->eventName = $eventName;
        $this->couponCode = $couponCode;
        $this->userId = $userId;
        $this->cartId = $cartId;
        $this->ruleSnapshot = $ruleSnapshot;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Store the event in MySQL for standard auditing and debugging.
        // This captures exactly what the rules were at the time of the event.
        CouponEvent::create([
            'event_type' => $this->eventName, // 'validated', 'reserved', 'failed', etc.
            'coupon_code' => $this->couponCode,
            'user_id' => $this->userId,
            'cart_id' => $this->cartId,
            'rule_snapshot' => $this->ruleSnapshot, // Automatically cast to JSON in the Model
            'metadata' => $this->metadata,          // Automatically cast to JSON in the Model
            'created_at' => now(),
        ]);

        // 2. Bonus: Emit to an external logging system or Message Broker (e.g., Kafka)
        // If you need high-performance analytics in something like ClickHouse or Elasticsearch,
        // you can push the payload here.
        $this->emitToBroker();
    }

    /**
     * Optional: Send the event to a message broker or centralized log.
     */
    protected function emitToBroker(): void
    {
        $payload = [
            'event' => $this->eventName,
            'coupon' => $this->couponCode,
            'user_id' => $this->userId,
            'cart_id' => $this->cartId,
            'snapshot' => $this->ruleSnapshot,
            'metadata' => $this->metadata,
            'timestamp' => now()->toIso8601String(),
        ];

        // Example: Logging to a dedicated JSON file for Datadog/Splunk to ingest
        Log::channel('coupon_audit')->info('Coupon Event', $payload);

        // Example: If using a Kafka package like mateusjunges/laravel-kafka
        /*
        \Junges\Kafka\Facades\Kafka::publishOn('coupon-lifecycle-events')
            ->withBodyKey('event', $payload)
            ->send();
        */
    }
}